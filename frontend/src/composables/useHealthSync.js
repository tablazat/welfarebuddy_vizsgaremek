import { ref } from "vue"
import api from "@/lib/api"
import { getUploadedIds, markAsUploaded, makeRecordId } from "@/lib/healthUploadTracker"

const LAST_SYNC_KEY = "health_last_sync"
const LOOKBACK_DAYS = 365
const QUERY_TIMEOUT_MS = 15000

let _activitiesMap = null

async function getActivitiesMap() {
  if (_activitiesMap) return _activitiesMap
  try {
    const res = await api.get("/activities")
    const list = res.data
    _activitiesMap = {}
    for (const a of list) {
      _activitiesMap[a.type] = a.id
    }
    return _activitiesMap
  } catch {
    return {}
  }
}

function toDateStr(d) {
  const dt = d instanceof Date ? d : new Date(d)
  return dt.toISOString().split("T")[0]
}

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
    const timer = setTimeout(() => done(null), timeoutMs)
    document.addEventListener("deviceready", () => done(get()), { once: true })
    const poll = setInterval(() => { if (get()) done(get()) }, 300)
  })
}

function promisify(plugin, method, ...args) {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      reject(new Error(`${method} timeout (${QUERY_TIMEOUT_MS}ms)`))
    }, QUERY_TIMEOUT_MS)

    plugin[method](...args,
      (result) => { clearTimeout(timeout); resolve(result) },
      (err) => { clearTimeout(timeout); reject(err) },
    )
  })
}

// Shared singleton state
const pluginState = ref("checking")
const isSyncing = ref(false)
const syncError = ref(null)
const lastSyncTime = ref(localStorage.getItem(LAST_SYNC_KEY) || null)
const syncedCounts = ref(null)
const currentStep = ref("")
const debugLog = ref([])

function dbg(msg) {
  const ts = new Date().toLocaleTimeString()
  debugLog.value.push(`[${ts}] ${msg}`)
  if (debugLog.value.length > 50) debugLog.value.shift()
}

let _plugin = null

export function useHealthSync() {
  const READ_TYPES = {
    read: ["heart_rate", "blood_pressure", "weight", "activity", "steps"],
  }

  async function init() {
    pluginState.value = "checking"
    dbg("init: waiting for plugin...")
    _plugin = await waitForPlugin()

    if (!_plugin) {
      dbg("init: plugin NOT found")
      pluginState.value = "unavailable"
      return
    }

    try {
      const available = await promisify(_plugin, "isAvailable")
      if (!available) {
        pluginState.value = "unavailable"
        return
      }
    } catch (e) {
      dbg(`init: isAvailable error: ${e?.message || e}`)
      pluginState.value = "unavailable"
      return
    }

    // iOS: requestAuthorization is idempotent – if already granted, it succeeds silently
    // without showing a popup. This lets us detect existing permissions on app restart.
    try {
      await promisify(_plugin, "requestAuthorization", READ_TYPES)
      pluginState.value = "ready"
      return
    } catch {
      pluginState.value = "needsPermission"
    }
  }

  async function requestPermissions() {
    if (!_plugin) return
    pluginState.value = "checking"
    syncError.value = null

    try {
      await promisify(_plugin, "requestAuthorization", READ_TYPES)
      pluginState.value = "ready"
    } catch (err) {
      syncError.value = typeof err === "string" ? err : (err?.message || "Nem sikerült az engedélyek megadása")
      pluginState.value = "needsPermission"
    }
  }

  function getSinceDate() {
    if (lastSyncTime.value) return new Date(lastSyncTime.value)
    const d = new Date()
    d.setDate(d.getDate() - LOOKBACK_DAYS)
    return d
  }

  async function safeQuery(dataType, since) {
    const queryOpts = { startDate: since, endDate: new Date(), dataType }
    try {
      const result = await promisify(_plugin, "query", queryOpts)
      return Array.isArray(result) ? result : []
    } catch (e) {
      dbg(`query(${dataType}): ERROR: ${e?.message || e}`)
      return []
    }
  }

  async function syncAll() {
    if (!_plugin) {
      await init()
      if (pluginState.value !== "ready" && pluginState.value !== "needsPermission") return
    }

    if (pluginState.value === "needsPermission") {
      await requestPermissions()
      if (pluginState.value !== "ready") return
    }

    if (isSyncing.value) return
    isSyncing.value = true
    syncError.value = null
    syncedCounts.value = null

    const counts = { heartRate: 0, bloodPressure: 0, weight: 0, steps: 0, activities: 0 }

    try {
      const since = getSinceDate()
      const uploadedIds = getUploadedIds()

      // Heart rate
      currentStep.value = "heart_rate"
      const hrRecords = await safeQuery("heart_rate", since)
      dbg(`HR: ${hrRecords.length} records from plugin`)
      for (const r of hrRecords) {
        const id = r.id || makeRecordId("heart_rate", r.startDate)
        if (uploadedIds.has(id)) continue
        const payload = { heart_rate: Math.round(r.value), recorded_at: toDateTimeStr(r.startDate) }
        try {
          await api.post("/new-heart-rate", payload)
          markAsUploaded([id])
          counts.heartRate++
        } catch (e) {
          console.error("[HealthSync] HR upload failed:", payload, e?.response?.status, e?.response?.data)
        }
      }

      // Blood pressure
      currentStep.value = "blood_pressure"
      const bpRecords = await safeQuery("blood_pressure", since)
      dbg(`BP: ${bpRecords.length} records from plugin`)
      for (const r of bpRecords) {
        const id = r.id || makeRecordId("blood_pressure", r.startDate)
        if (uploadedIds.has(id)) continue
        const systolic = r.value?.systolic ?? r.systolic
        const diastolic = r.value?.diastolic ?? r.diastolic
        if (!systolic || !diastolic) continue
        const payload = { systolic: Math.round(systolic), diastolic: Math.round(diastolic), recorded_at: toDateTimeStr(r.startDate) }
        try {
          await api.post("/new-blood-pressure", payload)
          markAsUploaded([id])
          counts.bloodPressure++
        } catch (e) {
          console.error("[HealthSync] BP upload failed:", payload, e?.response?.status, e?.response?.data)
        }
      }

      // Weight
      currentStep.value = "weight"
      const weightRecords = await safeQuery("weight", since)
      dbg(`WT: ${weightRecords.length} records from plugin`)
      for (const r of weightRecords) {
        const id = r.id || makeRecordId("weight", r.startDate)
        if (uploadedIds.has(id)) continue
        const payload = { weight: parseFloat(Number(r.value).toFixed(1)), recorded_at: toDateTimeStr(r.startDate) }
        try {
          await api.post("/new-weight", payload)
          markAsUploaded([id])
          counts.weight++
        } catch (e) {
          console.error("[HealthSync] Weight upload failed:", payload, e?.response?.status, e?.response?.data)
        }
      }

      // Steps – use queryAggregated (iOS stores steps as aggregated samples, not individual records)
      currentStep.value = "steps"
      let stepsByDay = {}
      try {
        const stepAggregated = await promisify(_plugin, "queryAggregated", {
          startDate: since,
          endDate: new Date(),
          dataType: "steps",
          bucket: "day",
        })
        const stepArr = Array.isArray(stepAggregated) ? stepAggregated : []
        dbg(`STEPS aggregated: ${stepArr.length} day buckets`)
        for (const r of stepArr) {
          const day = toDateStr(r.startDate)
          stepsByDay[day] = (stepsByDay[day] || 0) + Math.round(r.value)
        }
      } catch (e) {
        dbg(`steps queryAggregated error: ${e?.message || e}`)
        // Fallback: try regular query
        const stepRecords = await safeQuery("steps", since)
        dbg(`STEPS fallback query: ${stepRecords.length} records`)
        for (const r of stepRecords) {
          const day = toDateStr(r.startDate)
          stepsByDay[day] = (stepsByDay[day] || 0) + Math.round(r.value)
        }
      }
      dbg(`STEPS days to upload: ${JSON.stringify(stepsByDay)}`)
      for (const [day, totalSteps] of Object.entries(stepsByDay)) {
        const id = `steps_${day}`
        if (uploadedIds.has(id)) { dbg(`STEPS skip ${id} (already uploaded)`); continue }
        const payload = { steps: totalSteps, recorded_at: day, mode: "add" }
        try {
          await api.post("/new-steps", payload)
          markAsUploaded([id])
          counts.steps++
          dbg(`STEPS uploaded: ${day} = ${totalSteps}`)
        } catch (e) {
          console.error("[HealthSync] Steps upload failed:", payload, e?.response?.status, e?.response?.data)
        }
      }

      // Activities
      currentStep.value = "activity"
      const activitiesMap = await getActivitiesMap()
      const actRecords = await safeQuery("activity", since)
      dbg(`ACT: ${actRecords.length} records from plugin`)
      for (const r of actRecords) {
        const id = r.id || makeRecordId("activity", r.startDate)
        if (uploadedIds.has(id)) continue
        const rawType = r.value ? r.value.toLowerCase().replace(/[\s-]/g, "_") : "other"
        const activityId = activitiesMap[rawType] || activitiesMap["other"] || null
        if (!activityId) { dbg(`ACT skip: no matching id for "${rawType}"`); continue }
        const payload = { activity_id: activityId, begin: toDateTimeStr(r.startDate), end: toDateTimeStr(r.endDate) }
        try {
          await api.post("/new-exercise", payload)
          markAsUploaded([id])
          counts.activities++
        } catch (e) {
          console.error("[HealthSync] Exercise upload failed:", payload, e?.response?.status, e?.response?.data)
        }
      }

      syncedCounts.value = counts
      const now = new Date().toISOString()
      localStorage.setItem(LAST_SYNC_KEY, now)
      lastSyncTime.value = now
    } catch (err) {
      syncError.value = typeof err === "string" ? err : (err?.message || "Nem sikerült az átvétel")
    } finally {
      isSyncing.value = false
      currentStep.value = ""
    }
  }

  function forceSync() {
    // Clear all cached state to force full 30-day re-sync
    localStorage.removeItem(LAST_SYNC_KEY)
    localStorage.removeItem("health_uploaded_ids")
    lastSyncTime.value = null
    dbg("forceSync: cleared cache, starting full re-sync")
    return syncAll()
  }

  return {
    init, syncAll, requestPermissions, forceSync,
    pluginState, isSyncing, syncError, lastSyncTime, syncedCounts, currentStep,
    debugLog,
  }
}
