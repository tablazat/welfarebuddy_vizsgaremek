<script setup>
import { ref, inject, computed, onMounted, watch, onBeforeUnmount } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { getLocalTimeZone } from "@internationalized/date"
import api from "@/lib/api"
import { storageGet } from "@/lib/storage"
import { Skeleton } from "@/components/ui/skeleton"
import { Button } from "@/components/ui/button"
import {
  Heart, Activity, Weight, Flame, Footprints, Droplet, Moon, Star,
  Plus, NotebookPen, BarChart3, RefreshCw, AlertTriangle,
  CheckCircle2, AlertCircle,
} from "lucide-vue-next"
import { hapticSuccess } from "@/lib/haptics"
import {
  notifyStreakRecord, notifyStreakMilestone,
  notifyStepGoalReached, notifyStepGoalAlmost,
} from "@/composables/useLocalNotifications"

const router       = useRouter()
const { t, locale } = useI18n()
const dateLocale   = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")
const selectedDate    = inject("selectedDate")
const wsEvent         = inject("wsEvent", ref(null))
const refreshTrigger  = inject("refreshTrigger", ref(0))
const tz           = getLocalTimeZone()

const loading         = ref(true)
const refreshing      = ref(false)
const loadError       = ref(false)
const streak          = ref(null)
const user            = ref(null)
const latestHR        = ref(null)
const prevHR          = ref(null)
const latestBP        = ref(null)
const prevBP          = ref(null)
const latestWeight    = ref(null)
const prevWeight      = ref(null)
const todaySteps      = ref(null)
const todayWater      = ref(null)
const lastNightSleep  = ref(null)
const weekHR          = ref([])

function toLocalDateStr(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, "0")
  const d = String(date.getDate()).padStart(2, "0")
  return `${y}-${m}-${d}`
}

const today = computed(() => toLocalDateStr(selectedDate.value.toDate(tz)))
const tomorrow = computed(() => {
  const d = selectedDate.value.toDate(tz)
  d.setDate(d.getDate() + 1)
  return toLocalDateStr(d)
})

function formatDate(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleDateString(dateLocale.value, {
    month: "short", day: "numeric", hour: "2-digit", minute: "2-digit",
  })
}

function trend(current, previous, higherIsBad = false) {
  if (current == null || previous == null) return null
  const diff = current - previous
  const pct = Math.abs(diff) / Math.abs(previous || 1)
  if (pct < 0.02) return { arrow: "→", cls: "text-muted-foreground" }
  if (diff > 0) return { arrow: "↑", cls: higherIsBad ? "text-red-500" : "text-green-500" }
  return { arrow: "↓", cls: higherIsBad ? "text-green-500" : "text-red-500" }
}
const hrTrend     = computed(() => trend(latestHR.value?.heart_rate, prevHR.value?.heart_rate, true))
const bpTrend     = computed(() => trend(latestBP.value?.systolic, prevBP.value?.systolic, true))
const weightTrend = computed(() => trend(latestWeight.value?.weight, prevWeight.value?.weight))

function bpStatus(sys, dia) {
  if (!sys || !dia) return null
  if (sys < 120 && dia < 80)  return { label: t("dashboard.normal"),   color: "text-green-600", icon: CheckCircle2 }
  if (sys < 130 && dia < 80)  return { label: t("dashboard.elevated"), color: "text-yellow-600", icon: AlertCircle }
  if (sys < 140 || dia < 90)  return { label: t("dashboard.high1"),    color: "text-orange-600", icon: AlertTriangle }
  return { label: t("dashboard.high2"), color: "text-red-600", icon: AlertTriangle }
}

function hrStatus(hr) {
  if (!hr) return null
  if (hr < 60)   return { label: t("dashboard.low"),    color: "text-blue-600", icon: AlertCircle }
  if (hr <= 100) return { label: t("dashboard.normal"), color: "text-green-600", icon: CheckCircle2 }
  return { label: t("dashboard.high"), color: "text-red-600", icon: AlertTriangle }
}

function stepsColor(steps) {
  if (!steps) return "text-muted-foreground"
  if (steps >= 10000) return "text-green-600"
  if (steps >= 6000)  return "text-yellow-600"
  return "text-orange-500"
}

async function load(silent = false) {
  if (!silent) loading.value = true
  else refreshing.value = true
  loadError.value = false

  try {
    const results = await Promise.allSettled([
      api.get("/token-check"),
      api.get("/heart-rates"),
      api.get("/blood-pressures"),
      api.get("/weights"),
      api.get("/steps/today"),
      api.get("/waters/today"),
      api.get("/sleeps/last-night"),
    ])
    const [tokenRes, hrRes, bpRes, wRes, stepsRes, waterRes, sleepRes] = results

    if (tokenRes.status === "fulfilled") {
      user.value   = tokenRes.value.data.user
      streak.value = tokenRes.value.data.streak
    }
    if (hrRes.status === "fulfilled") {
      const hrs = hrRes.value.data
      latestHR.value = hrs.length ? hrs[hrs.length - 1] : null
      prevHR.value   = hrs.length > 1 ? hrs[hrs.length - 2] : null
    }
    if (bpRes.status === "fulfilled") {
      const bps = bpRes.value.data
      latestBP.value = bps.length ? bps[bps.length - 1] : null
      prevBP.value   = bps.length > 1 ? bps[bps.length - 2] : null
    }
    if (wRes.status === "fulfilled") {
      const ws = wRes.value.data
      latestWeight.value = ws.length ? ws[ws.length - 1] : null
      prevWeight.value   = ws.length > 1 ? ws[ws.length - 2] : null
    }
    if (stepsRes.status === "fulfilled") {
      todaySteps.value = stepsRes.value.data
    }
    if (waterRes.status === "fulfilled") {
      todayWater.value = waterRes.value.data
    }
    if (sleepRes.status === "fulfilled") {
      lastNightSleep.value = sleepRes.value.data
    }

    if (results.every(r => r.status === "rejected")) {
      loadError.value = true
    }

    // Utolsó 7 nap szívritmus
    const end = tomorrow.value
    const startDate = new Date(selectedDate.value.toDate(tz))
    startDate.setDate(startDate.getDate() - 6)
    const start   = toLocalDateStr(startDate)
    const weekRes = await api.get("/heart-rates", { params: { start_date: start, end_date: end } })
    weekHR.value  = weekRes.data
  } catch {}

  loading.value    = false
  refreshing.value = false
}

onMounted(() => load())
let _debounceTimer = null
watch(selectedDate, () => {
  clearTimeout(_debounceTimer)
  _debounceTimer = setTimeout(() => load(), 300)
})
watch(refreshTrigger, (v, old) => { if (v !== old) load(true) })

// WebSocket: valós idejű frissítés
watch(wsEvent, (e) => {
  if (!e) return
  const entry = e.data
  switch (e.type) {
    case "heart_rate":
      if (entry?.heart_rate) { latestHR.value = entry; weekHR.value.push(entry) }
      break
    case "blood_pressure":
      if (entry?.systolic) latestBP.value = entry
      break
    case "weight":
      if (entry?.weight) latestWeight.value = entry
      break
    case "steps":
      if (entry?.steps != null) todaySteps.value = entry
      break
    case "water":
      load(true)
      break
    case "sleep":
      load(true)
      break
    case "health_sync_complete":
      // Batch sync kész → csendes teljes újratöltés
      load(true)
      break
    default:
      load(true)
  }
})

const streakDays = computed(() => streak.value?.days || 0)

const notMeasuredToday = computed(() => {
  if (!streak.value?.last_day) return true
  const lastDay = new Date(streak.value.last_day).toDateString()
  const todayDate = new Date().toDateString()
  return lastDay !== todayDate
})

const isRecordStreak = computed(() => {
  if (!user.value || !streak.value) return false
  return streak.value.days > 0 && streak.value.days >= (user.value.max_days || 0)
})

// ─── Streak rekord banner ─────────────────────────────────
const showRecordBanner = ref(false)
let recordBannerTimer = null
watch([isRecordStreak, () => streak.value?.days], ([isRecord, days]) => {
  if (!isRecord || !days) return
  const seenKey = "record_banner_seen_days"
  const seenDays = Number(storageGet(seenKey) || 0)
  if (days > seenDays) {
    try { localStorage.setItem(seenKey, String(days)) } catch {}
    showRecordBanner.value = true
    hapticSuccess()
    clearTimeout(recordBannerTimer)
    recordBannerTimer = setTimeout(() => { showRecordBanner.value = false }, 5000)
    // Lokális értesítés: új streak rekord
    notifyStreakRecord(
      t("notifications.streakRecordTitle"),
      t("notifications.streakRecordBody", { days }),
    ).catch(() => {})
  }
})

// Streak mérföldkő (7 / 14 / 30 / 50 / 100 / 200 / 365) – egyszer usernként/fokonként
watch(() => streak.value?.days, (days) => {
  if (!days) return
  notifyStreakMilestone(
    t("notifications.streakMilestone"),
    t("notifications.streakMilestoneBody", { days }),
    days,
  ).catch(() => {})
})

// Lépés cél: 80% (8000) és 100% (10000)
watch(() => todaySteps.value?.steps, (steps, prev) => {
  if (!steps) return
  const goal = user.value?.step_goal_daily || parseInt(storageGet("step_goal") || "10000")
  const almostThreshold = Math.round(goal * 0.8)
  if (steps >= goal && (prev || 0) < goal) {
    notifyStepGoalReached(
      t("notifications.stepGoalReachedTitle"),
      t("notifications.stepGoalReachedBody", { goal }),
    ).catch(() => {})
  } else if (steps >= almostThreshold && steps < goal && (prev || 0) < almostThreshold) {
    notifyStepGoalAlmost(
      t("notifications.stepGoalAlmostTitle"),
      t("notifications.stepGoalAlmostBody", { remaining: goal - steps }),
    ).catch(() => {})
  }
})
onBeforeUnmount(() => clearTimeout(recordBannerTimer))

// Minibar chart – utolsó 7 nap szívritmus átlag
const barData = computed(() => {
  const byDay = {}
  weekHR.value.forEach(h => {
    const d = (h.recorded_at || h.created_at).substring(0, 10)
    if (!byDay[d]) byDay[d] = []
    byDay[d].push(h.heart_rate)
  })
  const days = []
  for (let i = 6; i >= 0; i--) {
    const d = new Date(selectedDate.value.toDate(tz))
    d.setDate(d.getDate() - i)
    const key  = toLocalDateStr(d)
    const vals = byDay[key]
    const avg  = vals ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length) : null
    days.push({ date: key, avg, label: d.toLocaleDateString(dateLocale.value, { weekday: "short" }) })
  }
  return days
})

const barMax = computed(() => {
  const vals = barData.value.map(d => d.avg).filter(Boolean)
  return vals.length ? Math.max(...vals) : 120
})

// Lépés + víz cél – elsőbbség: user DB > localStorage > default.
const STEP_GOAL = computed(() => {
  return user.value?.step_goal_daily || parseInt(storageGet("step_goal") || "10000")
})
const stepProgress = computed(() => {
  const s = todaySteps.value?.steps || 0
  return Math.min(100, Math.round((s / STEP_GOAL.value) * 100))
})

const WATER_GOAL = computed(() => {
  return user.value?.water_goal_ml || parseInt(storageGet("water_goal_ml") || "2500")
})
const waterProgress = computed(() => {
  const w = todayWater.value?.total_ml || 0
  return Math.min(100, Math.round((w / WATER_GOAL.value) * 100))
})

function formatSleepDate(iso) {
  if (!iso) return ""
  return new Date(iso).toLocaleDateString(dateLocale.value, { month: "short", day: "numeric" })
}
</script>

<template>
  <div class="space-y-4">

    <!-- Welcome row -->
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <h2 class="text-xl font-semibold">
          {{
            new Date().getHours() >= 6 && new Date().getHours() < 12
              ? $t("dashboard.goodMorning")
              : new Date().getHours() >= 12 && new Date().getHours() < 18
                ? $t("dashboard.goodDay")
                : $t("dashboard.goodEvening")
          }}, {{ user?.display_name || user?.name?.split(" ")[0] || $t("common.noData") }}!
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ selectedDate
            ? new Date(selectedDate.toDate(tz)).toLocaleDateString(dateLocale, {
                weekday: "long", year: "numeric", month: "long", day: "numeric",
              })
            : "" }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          v-if="!loading"
          variant="ghost"
          size="icon"
          class="rounded-xl h-9 w-9"
          :class="{ 'opacity-50 pointer-events-none': refreshing }"
          @click="load(true)"
          :title="$t('dashboard.refresh')"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
        </Button>
        <Button class="gap-2 rounded-xl" @click="router.push('/app/diary')">
          <Plus class="h-4 w-4" />
          {{ $t("dashboard.newEntry") }}
        </Button>
      </div>
    </div>

    <!-- Streak rekord banner -->
    <Transition name="streak-banner">
      <div
        v-if="showRecordBanner"
        class="flex items-center gap-3 rounded-2xl px-4 py-3 bg-gradient-to-r from-orange-500 to-amber-400 text-white shadow-lg cursor-pointer animate-pulse"
        @click="showRecordBanner = false"
      >
        <span class="text-2xl">🏆</span>
        <div>
          <div class="text-sm font-black">{{ $t("dashboard.recordStreak") }}</div>
          <div class="text-xs opacity-90">{{ $t("dashboard.recordStreakBanner", { days: streakDays }) }}</div>
        </div>
      </div>
    </Transition>

    <!-- Betöltési hiba banner -->
    <div
      v-if="!loading && loadError"
      class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30 px-4 py-3"
    >
      <AlertTriangle class="h-5 w-5 text-rose-600 dark:text-rose-400 shrink-0" />
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-rose-800 dark:text-rose-300">{{ $t("dashboard.loadFailedTitle") }}</div>
        <div class="text-xs text-rose-600 dark:text-rose-400">{{ $t("dashboard.loadFailedDesc") }}</div>
      </div>
      <Button size="sm" variant="outline" class="rounded-xl shrink-0" @click="load(false)">
        <RefreshCw class="h-3.5 w-3.5" />
        {{ $t("common.retry") }}
      </Button>
    </div>

    <!-- "Ma még nem mértél" banner -->
    <div
      v-if="!loading && notMeasuredToday"
      class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 px-4 py-3 cursor-pointer"
      @click="router.push('/app/diary')"
    >
      <span class="text-lg">🔔</span>
      <div>
        <div class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ $t("dashboard.notMeasuredToday") }}</div>
        <div class="text-xs text-amber-600 dark:text-amber-400">{{ $t("dashboard.diary") }} →</div>
      </div>
    </div>

    <!-- Metrika kártyák – Yasio-stílusú színes kártyák -->
    <div class="grid grid-cols-2 gap-3">

      <!-- Streak + Szívritmus egy sorban (col-span-1 each) -->
      <!-- Streak -->
      <div role="region" :aria-label="$t('dashboard.streak')" class="rounded-2xl p-4 bg-gradient-to-br from-orange-500/15 to-amber-500/5 border border-orange-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-orange-500/80">{{ $t("dashboard.streak") }}</span>
          <Flame class="h-4 w-4 text-orange-500" />
        </div>
        <template v-if="loading">
          <Skeleton class="h-9 w-16 mb-1" /><Skeleton class="h-3 w-20" />
        </template>
        <template v-else>
          <div class="text-4xl font-black tracking-tight">{{ streakDays }}</div>
          <div class="text-xs text-muted-foreground mt-0.5">{{ $t("dashboard.streakDay", streakDays) }}</div>
          <div v-if="isRecordStreak && streakDays > 0" class="text-[10px] font-semibold text-orange-500 mt-1">🏆 {{ $t("dashboard.recordStreak") }}</div>
        </template>
      </div>

      <!-- Szívritmus -->
      <div role="region" :aria-label="$t('dashboard.heartRate')" class="rounded-2xl p-4 bg-gradient-to-br from-red-500/15 to-rose-500/5 border border-red-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-red-500/80">{{ $t("dashboard.heartRate") }}</span>
          <Heart class="h-4 w-4 text-red-500" />
        </div>
        <template v-if="loading">
          <Skeleton class="h-9 w-14 mb-1" /><Skeleton class="h-3 w-20" />
        </template>
        <template v-else-if="latestHR">
          <div class="flex items-start justify-between">
            <div class="text-4xl font-black tracking-tight">{{ latestHR.heart_rate }}</div>
            <span v-if="hrTrend" class="text-sm font-bold" :class="hrTrend.cls">{{ hrTrend.arrow }}</span>
          </div>
          <div class="text-xs mt-0.5 flex items-center gap-1">
            <component v-if="hrStatus(latestHR.heart_rate)?.icon" :is="hrStatus(latestHR.heart_rate).icon" class="h-3 w-3 shrink-0" :class="hrStatus(latestHR.heart_rate)?.color" />
            <span :class="hrStatus(latestHR.heart_rate)?.color || 'text-muted-foreground'">{{ hrStatus(latestHR.heart_rate)?.label }}</span>
            <span class="text-muted-foreground">BPM</span>
          </div>
          <div class="text-[10px] text-muted-foreground/60 mt-1">{{ $t("dashboard.lastMeasured") }} {{ formatDate(latestHR.recorded_at) }}</div>
        </template>
        <template v-else>
          <div class="text-3xl font-black text-muted-foreground/40">—</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.noData") }}</div>
        </template>
      </div>

      <!-- Vérnyomás -->
      <div role="region" :aria-label="$t('dashboard.bloodPressure')" class="rounded-2xl p-4 bg-gradient-to-br from-blue-500/15 to-indigo-500/5 border border-blue-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-blue-500/80">{{ $t("dashboard.bloodPressure") }}</span>
          <Activity class="h-4 w-4 text-blue-500" />
        </div>
        <template v-if="loading">
          <Skeleton class="h-9 w-20 mb-1" /><Skeleton class="h-3 w-16" />
        </template>
        <template v-else-if="latestBP">
          <div class="flex items-start justify-between">
            <div class="text-3xl font-black tracking-tight">
              {{ latestBP.systolic }}<span class="text-lg font-semibold text-muted-foreground">/{{ latestBP.diastolic }}</span>
            </div>
            <span v-if="bpTrend" class="text-sm font-bold" :class="bpTrend.cls">{{ bpTrend.arrow }}</span>
          </div>
          <div class="text-xs mt-0.5 flex items-center gap-1">
            <component v-if="bpStatus(latestBP.systolic, latestBP.diastolic)?.icon" :is="bpStatus(latestBP.systolic, latestBP.diastolic).icon" class="h-3 w-3 shrink-0" :class="bpStatus(latestBP.systolic, latestBP.diastolic)?.color" />
            <span :class="bpStatus(latestBP.systolic, latestBP.diastolic)?.color || 'text-muted-foreground'">
              {{ bpStatus(latestBP.systolic, latestBP.diastolic)?.label }}
            </span>
            <span class="text-muted-foreground">mmHg</span>
          </div>
          <div class="text-[10px] text-muted-foreground/60 mt-1">{{ $t("dashboard.lastMeasured") }} {{ formatDate(latestBP.recorded_at) }}</div>
        </template>
        <template v-else>
          <div class="text-3xl font-black text-muted-foreground/40">—</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.noData") }}</div>
        </template>
      </div>

      <!-- Testsúly -->
      <div role="region" :aria-label="$t('dashboard.weight')" class="rounded-2xl p-4 bg-gradient-to-br from-violet-500/15 to-purple-500/5 border border-violet-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-violet-500/80">{{ $t("dashboard.weight") }}</span>
          <Weight class="h-4 w-4 text-violet-500" />
        </div>
        <template v-if="loading">
          <Skeleton class="h-9 w-14 mb-1" /><Skeleton class="h-3 w-16" />
        </template>
        <template v-else-if="latestWeight">
          <div class="flex items-start justify-between">
            <div class="text-4xl font-black tracking-tight">{{ latestWeight.weight }}</div>
            <span v-if="weightTrend" class="text-sm font-bold" :class="weightTrend.cls">{{ weightTrend.arrow }}</span>
          </div>
          <div class="text-xs text-muted-foreground mt-0.5">kg</div>
          <div class="text-[10px] text-muted-foreground/60 mt-1">{{ $t("dashboard.lastMeasured") }} {{ formatDate(latestWeight.recorded_at) }}</div>
        </template>
        <template v-else>
          <div class="text-3xl font-black text-muted-foreground/40">—</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.noData") }}</div>
        </template>
      </div>

      <!-- Lépések – teljes széles kártya, activity ring -->
      <div role="region" :aria-label="$t('dashboard.stepsToday')" class="col-span-2 rounded-2xl p-4 bg-gradient-to-br from-emerald-500/15 to-teal-500/5 border border-emerald-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-emerald-500/80">{{ $t("dashboard.stepsToday") }}</span>
          <Footprints class="h-4 w-4 text-emerald-500" />
        </div>
        <template v-if="loading">
          <div class="flex items-center gap-4"><Skeleton class="h-20 w-20 rounded-full" /><div class="flex-1 space-y-2"><Skeleton class="h-7 w-32" /><Skeleton class="h-3 w-20" /></div></div>
        </template>
        <template v-else-if="todaySteps?.steps != null">
          <div class="flex items-center gap-4">
            <div class="relative shrink-0">
              <svg class="h-20 w-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15.915" fill="none" class="stroke-muted/40" stroke-width="3" />
                <circle
                  cx="18" cy="18" r="15.915"
                  fill="none"
                  class="stroke-emerald-500 transition-[stroke-dashoffset] duration-[900ms] ease-out"
                  stroke-width="3"
                  stroke-linecap="round"
                  stroke-dasharray="100"
                  :stroke-dashoffset="100 - Math.min(stepProgress, 100)"
                />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-sm font-bold tabular-nums" :class="stepsColor(todaySteps.steps)">{{ stepProgress }}%</span>
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-3xl font-black tracking-tight leading-none" :class="stepsColor(todaySteps.steps)">
                {{ todaySteps.steps.toLocaleString(dateLocale) }}
              </div>
              <div class="text-xs text-muted-foreground mt-1.5">/ {{ STEP_GOAL.toLocaleString(dateLocale) }} · {{ $t("dashboard.stepGoalProgress") }}</div>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="text-3xl font-black text-muted-foreground/40">—</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.noData") }}</div>
        </template>
      </div>

      <!-- Víz ma – teljes széles kártya, progress ring -->
      <div role="region" :aria-label="$t('dashboard.waterToday')" class="col-span-2 rounded-2xl p-4 bg-gradient-to-br from-blue-500/15 to-sky-500/5 border border-blue-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-blue-500/80">{{ $t("dashboard.waterToday") }}</span>
          <Droplet class="h-4 w-4 text-blue-500" />
        </div>
        <template v-if="loading">
          <div class="flex items-center gap-4"><Skeleton class="h-20 w-20 rounded-full" /><div class="flex-1 space-y-2"><Skeleton class="h-7 w-32" /><Skeleton class="h-3 w-20" /></div></div>
        </template>
        <template v-else>
          <div class="flex items-center gap-4">
            <div class="relative shrink-0">
              <svg class="h-20 w-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15.915" fill="none" class="stroke-muted/40" stroke-width="3" />
                <circle
                  cx="18" cy="18" r="15.915"
                  fill="none"
                  class="stroke-blue-500 transition-[stroke-dashoffset] duration-[900ms] ease-out"
                  stroke-width="3"
                  stroke-linecap="round"
                  stroke-dasharray="100"
                  :stroke-dashoffset="100 - Math.min(waterProgress, 100)"
                />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-sm font-bold tabular-nums text-blue-600">{{ waterProgress }}%</span>
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-3xl font-black tracking-tight leading-none text-blue-600">
                {{ (todayWater?.total_ml || 0).toLocaleString(dateLocale) }} ml
              </div>
              <div class="text-xs text-muted-foreground mt-1.5">/ {{ WATER_GOAL.toLocaleString(dateLocale) }} ml · {{ $t("dashboard.waterGoalProgress") }}</div>
            </div>
          </div>
        </template>
      </div>

      <!-- Alvás tegnap éjjel – teljes széles kártya -->
      <div role="region" :aria-label="$t('dashboard.sleepLastNight')" class="col-span-2 rounded-2xl p-4 bg-gradient-to-br from-indigo-500/15 to-purple-500/5 border border-indigo-500/20 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-indigo-500/80">{{ $t("dashboard.sleepLastNight") }}</span>
          <Moon class="h-4 w-4 text-indigo-500" />
        </div>
        <template v-if="loading">
          <div class="flex items-center gap-4"><Skeleton class="h-16 w-16 rounded-full" /><div class="flex-1 space-y-2"><Skeleton class="h-7 w-32" /><Skeleton class="h-3 w-20" /></div></div>
        </template>
        <template v-else-if="lastNightSleep?.hours != null">
          <div class="flex items-center gap-4">
            <div class="h-16 w-16 rounded-2xl bg-indigo-500/10 flex items-center justify-center shrink-0">
              <Moon class="h-7 w-7 text-indigo-500" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-3xl font-black tracking-tight leading-none text-indigo-600">
                {{ Number(lastNightSleep.hours).toLocaleString(dateLocale, { maximumFractionDigits: 1 }) }} <span class="text-base font-semibold">{{ $t("dashboard.sleepHoursShort") }}</span>
              </div>
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-1.5">
                <template v-if="lastNightSleep.quality">
                  <Star class="h-3 w-3 text-amber-500 fill-amber-500" />
                  <span class="tabular-nums">{{ lastNightSleep.quality }}/5</span>
                  <span class="text-muted-foreground/60">·</span>
                </template>
                <span>{{ formatSleepDate(lastNightSleep.recorded_at) }}</span>
              </div>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="text-3xl font-black text-muted-foreground/40">—</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.noData") }}</div>
        </template>
      </div>
    </div>

    <!-- HR chart -->
    <div class="rounded-2xl border bg-card p-4">
      <div class="flex items-center justify-between mb-4">
        <div>
          <div class="font-semibold text-sm flex items-center gap-2">
            <Heart class="h-4 w-4 text-red-500" />
            {{ $t("dashboard.hrChart") }}
          </div>
          <div class="text-xs text-muted-foreground mt-0.5">{{ $t("dashboard.hrChartDesc") }}</div>
        </div>
      </div>
      <template v-if="loading">
        <div class="flex items-end gap-2 h-24">
          <Skeleton v-for="i in 7" :key="i" class="flex-1 rounded-md" style="height: 40px" />
        </div>
      </template>
      <template v-else-if="barData.some(d => d.avg)">
        <div class="flex items-end gap-1.5 h-28">
          <div v-for="day in barData" :key="day.date" class="flex-1 flex flex-col items-center gap-1">
            <span class="text-[10px] text-muted-foreground font-medium">{{ day.avg || '' }}</span>
            <div
              class="w-full rounded-t-lg transition-all duration-300"
              :class="day.avg ? 'bg-red-500/70' : 'bg-muted'"
              :style="{ height: day.avg ? (day.avg / barMax * 72) + 'px' : '4px' }"
            />
            <span class="text-[10px] text-muted-foreground">{{ day.label }}</span>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="flex items-center justify-center h-24 text-sm text-muted-foreground">
          {{ $t("dashboard.noHrData") }}
        </div>
      </template>
    </div>

    <!-- Gyors műveletek -->
    <div class="grid grid-cols-2 gap-3">
      <button
        class="rounded-2xl p-4 border bg-card flex items-center gap-3 hover:bg-accent/50 active:scale-[0.97] transition-all text-left"
        @click="router.push('/app/diary')"
      >
        <div class="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
          <NotebookPen class="h-5 w-5 text-primary" />
        </div>
        <div>
          <div class="font-semibold text-sm">{{ $t("dashboard.diary") }}</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.diaryDesc") }}</div>
        </div>
      </button>
      <button
        class="rounded-2xl p-4 border bg-card flex items-center gap-3 hover:bg-accent/50 active:scale-[0.97] transition-all text-left"
        @click="router.push('/app/stats')"
      >
        <div class="h-10 w-10 rounded-xl bg-blue-500/10 flex items-center justify-center shrink-0">
          <BarChart3 class="h-5 w-5 text-blue-500" />
        </div>
        <div>
          <div class="font-semibold text-sm">{{ $t("dashboard.statistics") }}</div>
          <div class="text-xs text-muted-foreground">{{ $t("dashboard.statisticsDesc") }}</div>
        </div>
      </button>
    </div>

  </div>
</template>


<style scoped>
.streak-banner-enter-active, .streak-banner-leave-active { transition: all 0.4s ease; }
.streak-banner-enter-from, .streak-banner-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
