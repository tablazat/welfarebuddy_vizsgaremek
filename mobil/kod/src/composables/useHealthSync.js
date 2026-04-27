import { ref } from "vue"
import api from "@/lib/api"
import { getUploadedIds, markAsUploaded, makeRecordId } from "@/lib/healthUploadTracker"

const LAST_SYNC_KEY    = "health_last_sync"
const LOOKBACK_DAYS    = 30
const QUERY_TIMEOUT_MS = 30000

// Activity type → backend activity ID cache
let _activitiesMap = null

async function getActivitiesMap() {
  if (_activitiesMap) return _activitiesMap
  try {
    const res = await api.get("/activities")
    _activitiesMap = {}
    for (const a of res.data) _activitiesMap[a.type] = a.id
    return _activitiesMap
  } catch { return {} }
}

/** MySQL date format: YYYY-MM-DD */
function toDateStr(d) {
  const dt = d instanceof Date ? d : new Date(d)
  return dt.toISOString().split("T")[0]
}

/** MySQL datetime format: YYYY-MM-DD HH:MM:SS */
function toDateTimeStr(d) {
  const dt = d instanceof Date ? d : new Date(d)
  return dt.toISOString().replace("T", " ").replace(/\.\d+Z$/, "")
}

function waitForPlugin(timeoutMs = 8000) {
  const get = () => window.cordova?.plugins?.health || null
  if (get()) return Promise.resolve(get())

  return new Promise((resolve) => {
    let resolved = false
    const done = (val) => {
      if (resolved) return
      resolved = true
      clearTimeout(timer)
      clearInterval(poll)
      resolve(val)
    }
    const timer   = setTimeout(() => done(null), timeoutMs)
    document.addEventListener("deviceready", () => done(get()), { once: true })
    const poll    = setInterval(() => { if (get()) done(get()) }, 300)
  })
}

function promisify(plugin, method, ...args) {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(
      () => reject(new Error(`${method} timeout (${QUERY_TIMEOUT_MS}ms)`)),
      QUERY_TIMEOUT_MS,
    )
    plugin[method](...args,
      (result) => { clearTimeout(timeout); resolve(result) },
      (err)    => { clearTimeout(timeout); reject(err) },
    )
  })
}

// ── Shared module-level state ──────────────────────────────
const pluginState  = ref("checking")  // checking | unavailable | needsPermission | ready
const isSyncing    = ref(false)
const syncError    = ref(null)
const lastSyncTime = ref(localStorage.getItem(LAST_SYNC_KEY) || null)
const syncedCounts = ref(null)
const currentStep  = ref("")
const debugLog     = ref([])

let _plugin     = null
let _initPromise = null  // Megakadályozza a párhuzamos init() hívásokat

function dbg(msg) {
  const ts = new Date().toLocaleTimeString()
  debugLog.value.push(`[${ts}] ${msg}`)
  if (debugLog.value.length > 100) debugLog.value.shift()
}

export function useHealthSync() {
  const READ_TYPES = {
    read: ["heart_rate", "blood_pressure", "weight", "activity", "steps"],
  }

  // ── init ──────────────────────────────────────────────────
  // Idempotens: ha már fut vagy kész, nem indítja újra
  async function init(force = false) {
    // Ha már fut egy init, várjuk be azt
    if (_initPromise) return _initPromise

    // Ha már van plugin és nem forced, ne reinit
    if (!force && _plugin && pluginState.value !== "checking") {
      dbg(`init: skip (state=${pluginState.value})`)
      return
    }

    _initPromise = _doInit()
    try { await _initPromise } catch { pluginState.value = "unavailable" } finally { _initPromise = null }
  }

  async function _doInit() {
    pluginState.value = "checking"
    syncError.value   = null
    dbg("init: waiting for plugin...")
    _plugin = await waitForPlugin()

    if (!_plugin) {
      dbg("init: plugin NOT found (web/simulator?)")
      pluginState.value = "unavailable"
      return
    }
    dbg(`init: plugin found`)

    try {
      const available = await promisify(_plugin, "isAvailable")
      dbg(`init: isAvailable=${JSON.stringify(available)}`)
      if (!available) {
        pluginState.value = "unavailable"
        return
      }
    } catch (e) {
      dbg(`init: isAvailable error: ${e?.message || e}`)
      pluginState.value = "unavailable"
      return
    }

    // Ellenőrizzük van-e MINDEN jogosultság (nem csak steps!)
    try {
      await promisify(_plugin, "requestAuthorization", READ_TYPES)
      dbg("init: all permissions OK → ready")
      pluginState.value = "ready"
    } catch {
      dbg("init: missing permissions → needsPermission")
      pluginState.value = "needsPermission"
    }
  }

  // ── requestPermissions ────────────────────────────────────
  async function requestPermissions() {
    if (!_plugin) return
    pluginState.value = "checking"
    syncError.value   = null
    dbg("requestPermissions: calling requestAuthorization")
    try {
      await promisify(_plugin, "requestAuthorization", READ_TYPES)
      dbg("requestPermissions: granted → ready")
      pluginState.value = "ready"
    } catch (err) {
      dbg(`requestPermissions: error: ${err?.message || JSON.stringify(err)}`)
      syncError.value   = typeof err === "string" ? err : (err?.message || "Permission request failed")
      pluginState.value = "needsPermission"
    }
  }

  // ── helpers ───────────────────────────────────────────────
  function getSinceDate() {
    if (lastSyncTime.value) return new Date(lastSyncTime.value)
    const d = new Date()
    d.setDate(d.getDate() - LOOKBACK_DAYS)
    return d
  }

  async function safeQuery(dataType, since) {
    dbg(`query(${dataType}): since=${since.toISOString()}`)
    try {
      const result = await promisify(_plugin, "query", {
        startDate: since,
        endDate:   new Date(),
        dataType,
      })
      const arr = Array.isArray(result) ? result : []
      dbg(`query(${dataType}): ${arr.length} records`)
      if (arr.length > 0) dbg(`  first=${JSON.stringify(arr[0]).substring(0, 120)}`)
      return arr
    } catch (e) {
      dbg(`query(${dataType}): ERROR: ${e?.message || JSON.stringify(e)}`)
      return []
    }
  }

  // ── syncAll ───────────────────────────────────────────────
  // NEM kér engedélyt automatikusan – csak "ready" állapotban fut
  async function syncAll() {
    // Ha nincs plugin, próbáljuk inicializálni (de NEM kérünk engedélyt)
    if (!_plugin || pluginState.value === "checking") {
      await init()
    }

    // Ha nincs engedély, nem futtatjuk (a UI hívja meg requestPermissions-t)
    if (pluginState.value !== "ready") {
      dbg(`syncAll: skip – state=${pluginState.value}`)
      return
    }

    if (isSyncing.value) { dbg("syncAll: already syncing"); return }
    isSyncing.value    = true
    syncError.value    = null
    syncedCounts.value = null

    try {
      const since       = getSinceDate()
      const uploadedIds = getUploadedIds()
      dbg(`syncAll: since=${since.toISOString()}, uploadedIds=${uploadedIds.size}`)

      const payload = {
        heart_rates:    [],
        blood_pressures:[],
        weights:        [],
        steps:          [],
        exercises:      [],
      }
      const newIds = {
        heart_rates:    [],
        blood_pressures:[],
        weights:        [],
        steps:          [],
        exercises:      [],
      }

      // PULZUS
      currentStep.value = "heart_rate"
      const hrRecords = await safeQuery("heart_rate", since)
      for (const r of hrRecords) {
        const id  = r.id || makeRecordId("heart_rate", r.startDate)
        const val = Math.round(Number(r.value))
        if (uploadedIds.has(id) || !val || val < 20 || val > 300) continue
        payload.heart_rates.push({ heart_rate: val, recorded_at: toDateTimeStr(r.startDate) })
        newIds.heart_rates.push(id)
      }
      dbg(`HR: ${hrRecords.length} queried, ${payload.heart_rates.length} new`)

      // VÉRNYOMÁS
      currentStep.value = "blood_pressure"
      const bpRecords = await safeQuery("blood_pressure", since)
      for (const r of bpRecords) {
        const id        = r.id || makeRecordId("blood_pressure", r.startDate)
        if (uploadedIds.has(id)) continue
        // iOS: { systolic, diastolic } vagy { value: { systolic, diastolic } }
        const systolic  = r.systolic  ?? r.value?.systolic
        const diastolic = r.diastolic ?? r.value?.diastolic
        if (!systolic || !diastolic) continue
        payload.blood_pressures.push({
          systolic:    Math.round(Number(systolic)),
          diastolic:   Math.round(Number(diastolic)),
          recorded_at: toDateTimeStr(r.startDate),
        })
        newIds.blood_pressures.push(id)
      }
      dbg(`BP: ${bpRecords.length} queried, ${payload.blood_pressures.length} new`)

      // TESTSÚLY
      currentStep.value = "weight"
      const weightRecords = await safeQuery("weight", since)
      for (const r of weightRecords) {
        const id  = r.id || makeRecordId("weight", r.startDate)
        const val = parseFloat(Number(r.value).toFixed(1))
        if (uploadedIds.has(id) || !val || val < 1) continue
        payload.weights.push({ weight: val, recorded_at: toDateTimeStr(r.startDate) })
        newIds.weights.push(id)
      }
      dbg(`WT: ${weightRecords.length} queried, ${payload.weights.length} new`)

      // LÉPÉSSZÁM – aggregálás napra
      currentStep.value = "steps"
      const stepRecords = await safeQuery("steps", since)
      const stepsByDay  = {}
      for (const r of stepRecords) {
        const day = toDateStr(r.startDate)
        stepsByDay[day] = (stepsByDay[day] || 0) + Math.round(Number(r.value))
      }
      for (const [day, totalSteps] of Object.entries(stepsByDay)) {
        const id = `steps_${day}`
        if (uploadedIds.has(id)) continue
        payload.steps.push({ steps: totalSteps, recorded_at: day })
        newIds.steps.push(id)
      }
      dbg(`STEPS: ${stepRecords.length} records → ${Object.keys(stepsByDay).length} days, ${payload.steps.length} new`)

      // EDZÉSEK
      currentStep.value = "activity"
      const activitiesMap = await getActivitiesMap()
      const actRecords    = await safeQuery("activity", since)
      for (const r of actRecords) {
        const id      = r.id || makeRecordId("activity", r.startDate)
        if (uploadedIds.has(id)) continue
        const rawType = (r.value || "other").toLowerCase().replace(/[\s\-]/g, "_")
        const actId   = activitiesMap[rawType] || activitiesMap["other"] || null
        if (!actId) { dbg(`ACT skip: type="${rawType}" → no match`); continue }
        if (!r.endDate) { dbg(`ACT skip: no endDate`); continue }
        payload.exercises.push({
          activity_id: actId,
          begin:       toDateTimeStr(r.startDate),
          end:         toDateTimeStr(r.endDate),
        })
        newIds.exercises.push(id)
      }
      dbg(`ACT: ${actRecords.length} queried, ${payload.exercises.length} new`)

      // BATCH UPLOAD
      const total = Object.values(payload).reduce((s, a) => s + a.length, 0)
      dbg(`batch: ${total} new record(s)`)

      if (total > 0) {
        currentStep.value = "uploading"
        const res = await api.post("/health-sync", payload)
        dbg(`batch: status=${res.status} counts=${JSON.stringify(res.data?.counts)}`)

        markAsUploaded([
          ...newIds.heart_rates,
          ...newIds.blood_pressures,
          ...newIds.weights,
          ...newIds.steps,
          ...newIds.exercises,
        ])

        const c = res.data?.counts || {}
        syncedCounts.value = {
          heartRate:     c.heart_rates     || 0,
          bloodPressure: c.blood_pressures || 0,
          weight:        c.weights         || 0,
          steps:         c.steps           || 0,
          activities:    c.exercises       || 0,
        }
      } else {
        dbg("batch: no new data")
        syncedCounts.value = { heartRate: 0, bloodPressure: 0, weight: 0, steps: 0, activities: 0 }
      }

      const now = new Date().toISOString()
      localStorage.setItem(LAST_SYNC_KEY, now)
      lastSyncTime.value = now
      dbg(`syncAll: done ✓`)

    } catch (err) {
      const status = err?.response?.status
      const body   = JSON.stringify(err?.response?.data || {}).substring(0, 300)
      dbg(`syncAll ERROR: ${status} ${err?.message} body=${body}`)
      syncError.value = typeof err === "string" ? err : (err?.message || "Sync failed")
    } finally {
      isSyncing.value   = false
      currentStep.value = ""
    }
  }

  return {
    init, syncAll, requestPermissions,
    pluginState, isSyncing, syncError, lastSyncTime, syncedCounts, currentStep,
    debugLog,
  }
}
