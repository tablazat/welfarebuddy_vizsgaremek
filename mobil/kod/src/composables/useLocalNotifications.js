/**
 * Lokális értesítések kezelő (Capacitor).
 *
 * Szerver nélküli, APNs/FCM nem kell. A natív plugin egyedül kezeli az
 * ütemezést és megjelenítést – offline is működik.
 *
 * Sablontípusok:
 *   Időzített (naponta/hetente ismétlődő):
 *     - scheduleDailyReminder(hour)   – napi emlékeztető
 *     - scheduleStreakReminder()      – 21:00, sorozat fenntartására
 *     - scheduleNotMeasuredReminder() – 20:00, esti „ma nem mértél"
 *     - scheduleExerciseReminder()    – 18:00, mozgás emlékeztető
 *     - scheduleMorningMotivation()   – 07:30, reggeli motiváció (opcionális)
 *     - scheduleWeeklySummary()       – vasárnap 20:00, heti összefoglaló
 *
 *   Esemény-alapú (azonnali):
 *     - notifyStreakRecord(days)      – új streak rekord
 *     - notifyStreakMilestone(days)   – 7/14/30/50/100 napos mérföldkő
 *     - notifyStepGoalReached(goal)   – napi lépés cél elérve
 *     - notifyStepGoalAlmost(remaining) – napi cél 80%-a (8000+)
 *     - notifyFirstEntry()            – első bejegyzés (egyszer usernként)
 *     - scheduleInactivity(lastTs)    – 48h+ inaktivitás → 24h múlva emlékeztető
 *
 *   Egyéb:
 *     - notifySyncComplete(count)     – sync kész (batch upload után)
 *
 * Permission state: singleton ref-ek (`permissionGranted`, `permissionDenied`).
 */

import { ref } from "vue"
import { storageGet, storageSet } from "@/lib/storage"

// ---------------- State (module-level singleton) ----------------
const permissionGranted = ref(false)
const permissionDenied  = ref(false)
let _plugin = null

// Stable IDs for scheduled (repeating) notifications
const IDS = {
  DAILY:      10001,
  STREAK:     10002,
  NOT_MEASURED: 10003,
  EXERCISE:   10004,
  MORNING:    10005,
  WEEKLY:     10006,
  INACTIVITY: 10007,
}

// Napi limit a "random" (rotált body, fix időzítésű) emlékeztetőkre.
// 5 toggle-ből (daily/not_measured/streak/exercise/morning) max 4 fut egy nap.
// Prioritás: ami fontosabb, az élvez elsőbbséget overflow esetén.
// Esemény-alapú értesítések (streak record, milestone, goal, first entry,
// weekly summary, inactivity) kívül esnek ezen a limiten – mindig mennek.
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

// Rolling ID for one-off event notifications (20000–2100000000)
let _eventId = 20000
function _nextEventId() {
  _eventId = _eventId >= 2_100_000_000 ? 20000 : _eventId + 1
  return _eventId
}

// ---------------- Preferences ----------------
const DEFAULTS = {
  enabled:        true,   // master
  daily:          true,
  daily_hour:     9,
  streak:         true,
  not_measured:   true,
  exercise:       true,
  morning:        false,  // opt-in
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

// ---------------- Plugin bootstrap ----------------
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

// Throws PERMISSION_DENIED if user blocked – callers can surface UI
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

// ---------------- Helpers ----------------
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
  const today = d.getDay() // 0 = Sunday
  let days = today === 0 ? 7 : 7 - today
  d.setDate(d.getDate() + days)
  d.setHours(hour, minute, 0, 0)
  return d
}

// ============================================================
// SCHEDULED NOTIFICATIONS (repeating)
// ============================================================

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

// ============================================================
// EVENT-BASED NOTIFICATIONS (immediate / near-future)
// ============================================================

export async function notifyImmediate(title, body) {
  await _load()
  if (!getNotifPref("enabled")) return
  if (!await _ensurePermission()) return
  return _schedule({ id: _nextEventId(), title, body })
}

// A body-t a hívónak kell vue-i18n placeholder-ekkel kiinterpolálnia
// (pl. t("...streakRecordBody", { days })). Korábban manuális replace volt itt,
// de ha a hívó nem adta át a paramétert a t()-be, vue-i18n már strip-pelte a
// placeholdert → 3 szóköz maradt a notif body-ban.
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

/**
 * Ha 48+ órája nem volt aktivitás, ütemezzünk egy "hiányzol" notifot 24 óra múlva.
 * App indításkor mindig hívjuk – ha időközben a user visszajött, `lastActiveTs`
 * friss és nem lesz ütemezés. Ha nem, ütemezzük (meglévő korábbi ütemezést törölve).
 */
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

// ============================================================
// SYNC COMPLETE (kept for HealthSync back-compat)
// ============================================================

export async function notifySyncComplete(count, t) {
  if (count <= 0) return
  return notifyImmediate(
    t("notifications.syncComplete"),
    t("notifications.syncCompleteBody", { count }),
  )
}

// ============================================================
// MASTER INIT (AppShell mount)
// ============================================================

/**
 * Ütemezi az összes aktív időzített értesítést + inaktivitás check.
 * Ne hívj _requirePermission-t kemény hibával – csak soft return, a Settings
 * jelzi ha denied.
 *
 * @param {(key: string, opts?: object) => string} t   - i18n t()
 * @param {(key: string) => string[] | string}      tm - i18n tm() (tömb üzenetek)
 * @param {object} ctx - opcionális: { weekSteps, weekExercises, streakDays }
 */
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

// ============================================================
// PUBLIC API
// ============================================================

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

// Back-compat alias for old imports (Settings.vue, Admin.vue)
export { permissionDenied as pushPermissionDenied }
