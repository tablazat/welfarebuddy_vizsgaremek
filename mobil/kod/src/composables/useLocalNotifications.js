

import { ref } from "vue"
import { storageGet, storageSet } from "@/lib/storage"


const permissionGranted = ref(false)
const permissionDenied  = ref(false)
let _plugin = null


const IDS = {
  DAILY:      10001,
  STREAK:     10002,
  NOT_MEASURED: 10003,
  EXERCISE:   10004,
  MORNING:    10005,
  WEEKLY:     10006,
  INACTIVITY: 10007,
}






const RANDOM_DAILY_LIMIT = 4
const RANDOM_KEYS_BY_PRIORITY = ["daily", "not_measured", "streak", "exercise", "morning"]
const RANDOM_KEY_TO_ID = {
  daily:        IDS.DAILY,
  not_measured: IDS.NOT_MEASURED,
  streak:       IDS.STREAK,
  exercise:     IDS.EXERCISE,
  morning:      IDS.MORNING,
}

function _allowedRandomKeys() {
  const enabled = RANDOM_KEYS_BY_PRIORITY.filter(k => getNotifPref(k))
  return new Set(enabled.slice(0, RANDOM_DAILY_LIMIT))
}


let _eventId = 20000
function _nextEventId() {
  _eventId = _eventId >= 2_100_000_000 ? 20000 : _eventId + 1
  return _eventId
}


const DEFAULTS = {
  enabled:        true,   
  daily:          true,
  daily_hour:     9,
  streak:         true,
  not_measured:   true,
  exercise:       true,
  morning:        false,  
  weekly:         true,
  step_goal:      true,
  streak_record:  true,
  inactivity:     true,
}

export function getNotifPref(key) {
  const v = storageGet(`notif_${key}`)
  if (v === null || v === undefined || v === "") return DEFAULTS[key]
  if (typeof DEFAULTS[key] === "boolean") return v === "1"
  if (typeof DEFAULTS[key] === "number")  return Number(v)
  return v
}

export function setNotifPref(key, val) {
  const v = typeof val === "boolean" ? (val ? "1" : "0") : String(val)
  storageSet(`notif_${key}`, v)
}


async function _load() {
  if (!window.Capacitor?.isNativePlatform?.()) return false
  if (_plugin) return true
  try {
    const mod = await import("@capacitor/local-notifications")
    _plugin = mod.LocalNotifications
    return true
  } catch {
    return false
  }
}

async function _ensurePermission() {
  if (!await _load()) return false
  try {
    const perm = await _plugin.requestPermissions()
    const granted = perm.display === "granted"
    permissionGranted.value = granted
    permissionDenied.value  = !granted
    return granted
  } catch {
    permissionDenied.value = true
    return false
  }
}


async function _requirePermission() {
  const ok = await _ensurePermission()
  if (!ok) {
    const err = new Error("notification_permission_denied")
    err.code = "PERMISSION_DENIED"
    throw err
  }
}

async function _schedule(notif) {
  if (!_plugin) return false
  try {
    await _plugin.schedule({
      notifications: [{
        iconColor: "#6366f1",
        ...notif,
      }],
    })
    return true
  } catch {
    return false
  }
}

async function _cancel(id) {
  if (!_plugin) return
  try {
    await _plugin.cancel({ notifications: [{ id }] })
  } catch {}
}


function _pickRotating(messages) {
  if (!Array.isArray(messages) || !messages.length) return messages || ""
  return messages[new Date().getDay() % messages.length]
}

function _pickRandom(messages) {
  if (!Array.isArray(messages) || !messages.length) return messages || ""
  return messages[Math.floor(Math.random() * messages.length)]
}

function _nextDateAt(hour, minute = 0) {
  const d = new Date()
  d.setHours(hour, minute, 0, 0)
  if (d <= new Date()) d.setDate(d.getDate() + 1)
  return d
}

function _nextSundayAt(hour, minute = 0) {
  const d = new Date()
  const today = d.getDay() 
  let days = today === 0 ? 7 : 7 - today
  d.setDate(d.getDate() + days)
  d.setHours(hour, minute, 0, 0)
  return d
}





export async function scheduleDailyReminder(title, body, hour = 9) {
  await _load()
  await _cancel(IDS.DAILY)
  if (!getNotifPref("enabled") || !getNotifPref("daily")) return
  if (!_allowedRandomKeys().has("daily")) return
  await _requirePermission()
  setNotifPref("daily_hour", hour)
  return _schedule({
    id: IDS.DAILY,
    title,
    body: _pickRotating(body),
    schedule: { at: _nextDateAt(hour), repeats: true, every: "day" },
  })
}

export async function scheduleStreakReminder(title, body) {
  await _load()
  await _cancel(IDS.STREAK)
  if (!getNotifPref("enabled") || !getNotifPref("streak")) return
  if (!_allowedRandomKeys().has("streak")) return
  if (!await _ensurePermission()) return
  return _schedule({
    id: IDS.STREAK,
    title,
    body: _pickRotating(body),
    schedule: { at: _nextDateAt(21), repeats: true, every: "day" },
  })
}

export async function scheduleNotMeasuredReminder(title, body) {
  await _load()
  await _cancel(IDS.NOT_MEASURED)
  if (!getNotifPref("enabled") || !getNotifPref("not_measured")) return
  if (!_allowedRandomKeys().has("not_measured")) return
  if (!await _ensurePermission()) return
  return _schedule({
    id: IDS.NOT_MEASURED,
    title,
    body: _pickRotating(body),
    schedule: { at: _nextDateAt(20), repeats: true, every: "day" },
  })
}

export async function scheduleExerciseReminder(title, body) {
  await _load()
  await _cancel(IDS.EXERCISE)
  if (!getNotifPref("enabled") || !getNotifPref("exercise")) return
  if (!_allowedRandomKeys().has("exercise")) return
  if (!await _ensurePermission()) return
  return _schedule({
    id: IDS.EXERCISE,
    title,
    body: _pickRotating(body),
    schedule: { at: _nextDateAt(18), repeats: true, every: "day" },
  })
}

export async function scheduleMorningMotivation(title, body) {
  await _load()
  await _cancel(IDS.MORNING)
  if (!getNotifPref("enabled") || !getNotifPref("morning")) return
  if (!_allowedRandomKeys().has("morning")) return
  if (!await _ensurePermission()) return
  return _schedule({
    id: IDS.MORNING,
    title,
    body: _pickRotating(body),
    schedule: { at: _nextDateAt(7, 30), repeats: true, every: "day" },
  })
}

export async function scheduleWeeklySummary(title, body) {
  await _load()
  await _cancel(IDS.WEEKLY)
  if (!getNotifPref("enabled") || !getNotifPref("weekly")) return
  if (!await _ensurePermission()) return
  return _schedule({
    id: IDS.WEEKLY,
    title,
    body,
    schedule: { at: _nextSundayAt(20), repeats: true, every: "week" },
  })
}





export async function notifyImmediate(title, body) {
  await _load()
  if (!getNotifPref("enabled")) return
  if (!await _ensurePermission()) return
  return _schedule({ id: _nextEventId(), title, body })
}





export async function notifyStreakRecord(title, body) {
  if (!getNotifPref("streak_record")) return
  return notifyImmediate(title, body)
}

const MILESTONES = [7, 14, 30, 50, 100, 200, 365]
export async function notifyStreakMilestone(title, body, days) {
  if (!MILESTONES.includes(Number(days))) return
  const flagKey = `notif_milestone_${days}_shown`
  if (storageGet(flagKey) === "1") return
  storageSet(flagKey, "1")
  return notifyImmediate(title, body)
}

export async function notifyStepGoalReached(title, body) {
  if (!getNotifPref("step_goal")) return
  const key = `notif_step_goal_${new Date().toISOString().slice(0, 10)}`
  if (storageGet(key) === "1") return
  storageSet(key, "1")
  return notifyImmediate(title, body)
}

export async function notifyStepGoalAlmost(title, body) {
  if (!getNotifPref("step_goal")) return
  const key = `notif_step_almost_${new Date().toISOString().slice(0, 10)}`
  if (storageGet(key) === "1") return
  storageSet(key, "1")
  return notifyImmediate(title, body)
}

export async function notifyFirstEntry(title, body) {
  if (storageGet("notif_first_entry_shown") === "1") return
  storageSet("notif_first_entry_shown", "1")
  return notifyImmediate(title, body)
}


export async function scheduleInactivity(title, body, lastActiveTs) {
  await _load()
  await _cancel(IDS.INACTIVITY)
  if (!getNotifPref("enabled") || !getNotifPref("inactivity")) return
  if (!lastActiveTs) return
  const hoursSince = (Date.now() - Number(lastActiveTs)) / (1000 * 60 * 60)
  if (hoursSince < 48) return
  if (!await _ensurePermission()) return
  const when = new Date(Date.now() + 24 * 60 * 60 * 1000)
  return _schedule({
    id: IDS.INACTIVITY,
    title,
    body: _pickRandom(body),
    schedule: { at: when },
  })
}





export async function notifySyncComplete(count, t) {
  if (count <= 0) return
  return notifyImmediate(
    t("notifications.syncComplete"),
    t("notifications.syncCompleteBody", { count }),
  )
}






export async function initLocalNotifications(t, tm, ctx = {}) {
  if (!await _load()) return
  if (!getNotifPref("enabled")) return
  if (!await _ensurePermission()) return

  const hour = getNotifPref("daily_hour")

  await scheduleDailyReminder(
    t("notifications.dailyReminder"),
    tm("notifications.dailyReminderMessages"),
    hour,
  )
  await scheduleStreakReminder(
    t("notifications.streakReminderTitle"),
    tm("notifications.streakReminderMessages"),
  )
  await scheduleNotMeasuredReminder(
    t("notifications.notMeasuredTitle"),
    tm("notifications.notMeasuredMessages"),
  )
  await scheduleExerciseReminder(
    t("notifications.exerciseReminderTitle"),
    tm("notifications.exerciseReminderMessages"),
  )
  await scheduleMorningMotivation(
    t("notifications.morningTitle"),
    tm("notifications.morningMessages"),
  )

  const weekBody = t("notifications.weeklyBody", {
    steps:     ctx.weekSteps     ?? 0,
    exercises: ctx.weekExercises ?? 0,
    streak:    ctx.streakDays    ?? 0,
  })
  await scheduleWeeklySummary(t("notifications.weeklyTitle"), weekBody)

  const lastActiveTs = storageGet("notif_last_active_ts")
  if (lastActiveTs) {
    await scheduleInactivity(
      t("notifications.inactivityTitle"),
      tm("notifications.inactivityMessages"),
      Number(lastActiveTs),
    )
  }
  storageSet("notif_last_active_ts", String(Date.now()))
}





export function useLocalNotifications() {
  return {
    permissionGranted,
    permissionDenied,
    getNotifPref,
    setNotifPref,
    initLocalNotifications,
    scheduleDailyReminder,
    scheduleStreakReminder,
    scheduleNotMeasuredReminder,
    scheduleExerciseReminder,
    scheduleMorningMotivation,
    scheduleWeeklySummary,
    notifyImmediate,
    notifyStreakRecord,
    notifyStreakMilestone,
    notifyStepGoalReached,
    notifyStepGoalAlmost,
    notifyFirstEntry,
    scheduleInactivity,
    notifySyncComplete,
  }
}


export { permissionDenied as pushPermissionDenied }
