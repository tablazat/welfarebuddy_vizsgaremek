<script setup>
import { ref, inject, computed, onMounted, watch } from "vue"
import { useI18n } from "vue-i18n"
import api from "@/lib/api"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Separator } from "@/components/ui/separator"
import {
  Flame, Dumbbell, Footprints, Timer, TrendingUp, TrendingDown, Minus, Calendar,
  Heart, Activity, Weight,
} from "lucide-vue-next"
import { localizedActivityName } from "@/lib/utils"

const { t, locale } = useI18n()
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")
const wsEvent = inject("wsEvent", ref(null))

const loading = ref(false)
const activities = ref([])
const exercises = ref([])
const steps = ref([])
const userWeight = ref(70) // default kg, felülírjuk ha van adat

// Health vitals trend
const hrCurrent = ref(null)
const hrPrev = ref(null)
const bpCurrentSys = ref(null)
const bpPrevSys = ref(null)
const wCurrent = ref(null)
const wPrev = ref(null)

// Időszak
const allPeriods = computed(() => [
  { label: t("summary.period.today"), days: 1 },
  { label: t("summary.period.week1"), days: 7 },
  { label: t("summary.period.month1"), days: 30 },
  { label: t("summary.period.all"), days: 3650 },
])
const selectedPeriod = ref(7)

// MET értékek tevékenység type alapján
// https://sites.google.com/site/compendiumofphysicalactivities/
const metValues = {
  // Kardió
  running: 9.8, "running.treadmill": 9.0, biking: 7.5, "biking.stationary": 6.8,
  walking: 3.5, hiking: 6.0, elliptical: 5.0, stair_climbing: 9.0,
  "stair_climbing.machine": 9.0, jump_rope: 12.3, mixed_metabolic_cardio: 6.0,
  // Erősítés
  strength_training: 6.0, "strength_training.functional": 5.0, calisthenics: 5.0,
  weightlifting: 6.0, core_training: 4.0, crossfit: 8.0, bootcamp: 7.0,
  // Labdasportok
  basketball: 6.5, "football.soccer": 7.0, "football.american": 8.0,
  "football.australian": 7.0, volleyball: 4.0, handball: 8.0, tennis: 7.3,
  table_tennis: 4.0, badminton: 5.5, baseball: 5.0, softball: 5.0,
  cricket: 5.0, rugby: 8.3, hockey: 8.0, "hockey.roller": 7.0,
  lacrosse: 8.0, squash: 7.3, racquetball: 7.0, pickleball: 6.0, water_polo: 10.0,
  // Vízi sportok
  swimming: 6.0, "swimming.pool": 5.8, "swimming.open_water": 7.0,
  surfing: 3.0, sailing: 3.0, scuba_diving: 7.0, water_fitness: 5.3,
  water_sports: 5.0, paddle_sports: 5.0,
  // Téli sportok
  skiing: 7.0, "skiing.cross_country": 9.0, "skiing.downhill": 5.3,
  snowboarding: 5.3, snowshoeing: 8.0, snow_sports: 5.0, ice_skating: 7.0, curling: 4.0,
  // Harcművészet
  boxing: 7.8, kickboxing: 7.0, martial_arts: 7.0, fencing: 6.0, wrestling: 6.0,
  // Elme & Test
  yoga: 3.0, pilates: 3.0, meditation: 1.0, tai_chi: 3.0,
  guided_breathing: 1.0, stretching: 2.3, flexibility: 2.5, barre: 3.5,
  cooldown: 2.0, preparation_and_recovery: 2.0,
  // Tánc
  dancing: 5.0, "dancing.social": 4.5, "dancing.cardio": 7.0,
  // Egyéb
  other: 4.0, play: 4.0, fishing: 2.5, hunting: 5.0, horseback_riding: 4.0,
  golf: 3.5, archery: 3.5, bowling: 3.0, gymnastics: 5.5,
  track_and_field: 6.0, paragliding: 3.5, rock_climbing: 8.0,
  fitness_gaming: 3.5, disc_sports: 4.0, frisbee_disc: 3.0,
  exercise_class: 5.0, rowing: 7.0, "rowing.machine": 7.0,
  wheelchair: 3.0, "wheelchair.walkpace": 3.5, "wheelchair.runpace": 6.0,
}

// Lépés MET: ~3.5 (walking pace) - becslés: 0.04 kcal/lépés
const STEP_KCAL = 0.04

function getActivityType(activityId) {
  const act = activities.value.find(a => a.id === activityId)
  return act?.type || "other"
}

function getActivityName(activityId) {
  const act = activities.value.find(a => a.id === activityId)
  return localizedActivityName(act, locale.value) || t("exercise.unknownActivity")
}

function calcExerciseKcal(ex) {
  const type = getActivityType(ex.activity_id)
  const met = metValues[type] || 4.0
  const durationHours = (new Date(ex.end) - new Date(ex.begin)) / 3600000
  if (durationHours <= 0) return 0
  // kcal = MET × testsúly (kg) × idő (óra)
  return met * userWeight.value * durationHours
}

function durationMinutes(begin, end) {
  const diff = new Date(end) - new Date(begin)
  if (diff <= 0) return 0
  return Math.round(diff / 60000)
}

function formatDuration(mins) {
  if (mins < 60) return `${mins} min`
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}

// Szűrt adatok az időszak alapján
const filteredExercises = computed(() => {
  const cutoff = new Date()
  cutoff.setDate(cutoff.getDate() - selectedPeriod.value + 1)
  cutoff.setHours(0, 0, 0, 0)
  return exercises.value.filter(ex => new Date(ex.begin) >= cutoff)
})

const filteredSteps = computed(() => {
  const cutoff = new Date()
  cutoff.setDate(cutoff.getDate() - selectedPeriod.value + 1)
  const cutoffStr = cutoff.toISOString().slice(0, 10)
  return steps.value.filter(s => s.recorded_at >= cutoffStr)
})

// Összesítések
const totalExerciseKcal = computed(() => {
  return Math.round(filteredExercises.value.reduce((sum, ex) => sum + calcExerciseKcal(ex), 0))
})

const totalStepsKcal = computed(() => {
  return Math.round(filteredSteps.value.reduce((sum, s) => sum + (s.steps * STEP_KCAL), 0))
})

const totalKcal = computed(() => totalExerciseKcal.value + totalStepsKcal.value)

const totalExerciseMinutes = computed(() => {
  return filteredExercises.value.reduce((sum, ex) => sum + durationMinutes(ex.begin, ex.end), 0)
})

const totalSteps = computed(() => {
  return filteredSteps.value.reduce((sum, s) => sum + (s.steps || 0), 0)
})

const exerciseCount = computed(() => filteredExercises.value.length)

// Tevékenység szerinti bontás
const breakdownByActivity = computed(() => {
  const map = {}
  for (const ex of filteredExercises.value) {
    const name = getActivityName(ex.activity_id)
    const type = getActivityType(ex.activity_id)
    if (!map[name]) map[name] = { name, type, kcal: 0, minutes: 0, count: 0 }
    map[name].kcal += calcExerciseKcal(ex)
    map[name].minutes += durationMinutes(ex.begin, ex.end)
    map[name].count++
  }
  return Object.values(map).sort((a, b) => b.kcal - a.kcal)
})

// Napi bontás (utolsó 7 nap max)
const dailyBreakdown = computed(() => {
  const days = Math.min(selectedPeriod.value, 30)
  const result = []
  for (let i = days - 1; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    const dateStr = d.toISOString().slice(0, 10)
    const dayLabel = d.toLocaleDateString(dateLocale.value, { month: "short", day: "numeric" })
    const dayExercises = filteredExercises.value.filter(ex => (ex.begin || "").slice(0, 10) === dateStr)
    const daySteps = filteredSteps.value.find(s => s.recorded_at === dateStr)
    const exKcal = dayExercises.reduce((sum, ex) => sum + calcExerciseKcal(ex), 0)
    const stKcal = (daySteps?.steps || 0) * STEP_KCAL
    result.push({
      date: dateStr,
      label: dayLabel,
      exerciseKcal: Math.round(exKcal),
      stepsKcal: Math.round(stKcal),
      totalKcal: Math.round(exKcal + stKcal),
      steps: daySteps?.steps || 0,
      exerciseMinutes: dayExercises.reduce((sum, ex) => sum + durationMinutes(ex.begin, ex.end), 0),
    })
  }
  return result
})

const barMax = computed(() => {
  const vals = dailyBreakdown.value.map(d => d.totalKcal)
  return vals.length ? Math.max(...vals, 1) : 1
})

function avg(arr) {
  if (!arr.length) return null
  return arr.reduce((s, v) => s + v, 0) / arr.length
}

const hrTrend = computed(() => {
  if (hrCurrent.value == null || hrPrev.value == null) return null
  const diff = hrCurrent.value - hrPrev.value
  return { diff: Math.round(diff * 10) / 10, up: diff > 0.5, down: diff < -0.5 }
})

const bpTrend = computed(() => {
  if (bpCurrentSys.value == null || bpPrevSys.value == null) return null
  const diff = bpCurrentSys.value - bpPrevSys.value
  return { diff: Math.round(diff * 10) / 10, up: diff > 0.5, down: diff < -0.5 }
})

const wTrend = computed(() => {
  if (wCurrent.value == null || wPrev.value == null) return null
  const diff = wCurrent.value - wPrev.value
  return { diff: Math.round(diff * 10) / 10, up: diff > 0.05, down: diff < -0.05 }
})

async function loadAll() {
  loading.value = true
  try {
    const endDate = new Date()
    endDate.setDate(endDate.getDate() + 1)
    const startDate = new Date()
    startDate.setDate(startDate.getDate() - selectedPeriod.value + 1)
    const prevEndDate = new Date(startDate)
    const prevStartDate = new Date(startDate)
    prevStartDate.setDate(prevStartDate.getDate() - selectedPeriod.value)

    function fmt(d) { return d.toISOString().slice(0, 10) }
    const curParams = { start_date: fmt(startDate), end_date: fmt(endDate) }
    const prevParams = { start_date: fmt(prevStartDate), end_date: fmt(prevEndDate) }

    const [actRes, exRes, stRes, wRes, hrCurRes, hrPrevRes, bpCurRes, bpPrevRes, wCurRes, wPrevRes] = await Promise.allSettled([
      api.get("/activities"),
      api.get("/exercises"),
      api.get("/steps"),
      api.get("/current-weight"),
      api.get("/average/heart-rates", { params: curParams }),
      api.get("/average/heart-rates", { params: prevParams }),
      api.get("/average/blood-pressures", { params: curParams }),
      api.get("/average/blood-pressures", { params: prevParams }),
      api.get("/weights", { params: curParams }),
      api.get("/weights", { params: prevParams }),
    ])
    if (actRes.status === "fulfilled") activities.value = actRes.value.data
    if (exRes.status === "fulfilled") exercises.value = exRes.value.data
    if (stRes.status === "fulfilled") steps.value = stRes.value.data
    if (wRes.status === "fulfilled" && wRes.value.data?.weight) {
      userWeight.value = wRes.value.data.weight
    }
    if (hrCurRes.status === "fulfilled") hrCurrent.value = hrCurRes.value.data
    if (hrPrevRes.status === "fulfilled") hrPrev.value = hrPrevRes.value.data
    if (bpCurRes.status === "fulfilled") bpCurrentSys.value = bpCurRes.value.data?.systolic ?? null
    if (bpPrevRes.status === "fulfilled") bpPrevSys.value = bpPrevRes.value.data?.systolic ?? null
    if (wCurRes.status === "fulfilled") {
      const ws = wCurRes.value.data
      wCurrent.value = ws.length ? avg(ws.map(w => w.weight)) : null
    }
    if (wPrevRes.status === "fulfilled") {
      const ws = wPrevRes.value.data
      wPrev.value = ws.length ? avg(ws.map(w => w.weight)) : null
    }
  } catch {}
  loading.value = false
}

onMounted(loadAll)
watch(selectedPeriod, loadAll)

watch(wsEvent, (e) => {
  if (!e) return
  if (["activity", "steps", "weight", "heart_rate", "blood_pressure"].includes(e.type)) loadAll()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h2 class="text-lg font-semibold flex items-center gap-2">
          <Flame class="h-5 w-5 text-orange-500" />
          {{ $t("summary.title") }}
        </h2>
        <p class="text-sm text-muted-foreground">{{ $t("summary.subtitle") }}</p>
      </div>
      <div class="flex gap-1.5 bg-muted rounded-xl p-1">
        <Button
          v-for="p in allPeriods"
          :key="p.days"
          :variant="selectedPeriod === p.days ? 'default' : 'ghost'"
          size="sm"
          class="rounded-lg h-8 px-3"
          @click="selectedPeriod = p.days"
        >
          {{ p.label }}
        </Button>
      </div>
    </div>

    <!-- Big stat cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <Card class="rounded-2xl col-span-2 sm:col-span-1">
        <CardContent class="p-4 text-center">
          <Flame class="h-6 w-6 text-orange-500 mx-auto mb-1" />
          <template v-if="loading"><Skeleton class="h-8 w-20 mx-auto" /></template>
          <template v-else>
            <div class="text-2xl font-bold">{{ totalKcal.toLocaleString(dateLocale.value) }}</div>
          </template>
          <div class="text-xs text-muted-foreground">{{ $t("summary.totalKcal") }}</div>
        </CardContent>
      </Card>

      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <Timer class="h-6 w-6 text-blue-500 mx-auto mb-1" />
          <template v-if="loading"><Skeleton class="h-8 w-16 mx-auto" /></template>
          <template v-else>
            <div class="text-2xl font-bold">{{ formatDuration(totalExerciseMinutes) }}</div>
          </template>
          <div class="text-xs text-muted-foreground">{{ $t("summary.exerciseTime") }}</div>
        </CardContent>
      </Card>

      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <Footprints class="h-6 w-6 text-green-500 mx-auto mb-1" />
          <template v-if="loading"><Skeleton class="h-8 w-20 mx-auto" /></template>
          <template v-else>
            <div class="text-2xl font-bold">{{ totalSteps.toLocaleString(dateLocale.value) }}</div>
          </template>
          <div class="text-xs text-muted-foreground">{{ $t("summary.totalSteps") }}</div>
        </CardContent>
      </Card>

      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <Dumbbell class="h-6 w-6 text-purple-500 mx-auto mb-1" />
          <template v-if="loading"><Skeleton class="h-8 w-8 mx-auto" /></template>
          <template v-else>
            <div class="text-2xl font-bold">{{ exerciseCount }}</div>
          </template>
          <div class="text-xs text-muted-foreground">{{ $t("summary.exerciseCount") }}</div>
        </CardContent>
      </Card>
    </div>

    <!-- Kalória összetétel -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Flame class="h-4 w-4 text-orange-500" />
          {{ $t("summary.calorieBreakdown") }}
        </CardTitle>
        <CardDescription>{{ $t("summary.calorieBreakdownDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-20 w-full rounded-xl" />
        </template>
        <template v-else>
          <!-- Stacked bar -->
          <div class="h-6 rounded-full overflow-hidden flex bg-muted mb-3" v-if="totalKcal > 0">
            <div
              class="h-full bg-orange-400 transition-all"
              :style="{ width: (totalExerciseKcal / totalKcal * 100) + '%' }"
            />
            <div
              class="h-full bg-green-400 transition-all"
              :style="{ width: (totalStepsKcal / totalKcal * 100) + '%' }"
            />
          </div>
          <div class="flex gap-6 text-sm">
            <div class="flex items-center gap-2">
              <div class="h-3 w-3 rounded-full bg-orange-400" />
              <span class="text-muted-foreground">{{ $t("summary.exercise") }}:</span>
              <span class="font-semibold">{{ totalExerciseKcal.toLocaleString(dateLocale.value) }} kcal</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="h-3 w-3 rounded-full bg-green-400" />
              <span class="text-muted-foreground">{{ $t("summary.stepsLabel") }}:</span>
              <span class="font-semibold">{{ totalStepsKcal.toLocaleString(dateLocale.value) }} kcal</span>
            </div>
          </div>
        </template>
      </CardContent>
    </Card>

    <!-- Napi bontás bar chart -->
    <Card class="rounded-2xl" v-if="selectedPeriod <= 30">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Calendar class="h-4 w-4" />
          {{ $t("summary.dailyBreakdown") }}
        </CardTitle>
        <CardDescription>{{ $t("summary.dailyBreakdownDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <div class="flex items-end gap-2 h-32">
            <Skeleton v-for="i in 7" :key="i" class="flex-1 rounded" :style="{ height: [40, 70, 55, 90, 65, 80, 50][i - 1] + 'px' }" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-end gap-1 h-36 overflow-x-auto">
            <div
              v-for="day in dailyBreakdown"
              :key="day.date"
              class="flex-1 min-w-[28px] flex flex-col items-center gap-0.5"
            >
              <span class="text-[9px] text-muted-foreground font-medium">
                {{ day.totalKcal || '' }}
              </span>
              <div class="w-full flex flex-col rounded-t-md overflow-hidden" :style="{ height: Math.max(4, day.totalKcal / barMax * 80) + 'px' }">
                <div
                  class="w-full bg-orange-400"
                  :style="{ flex: day.exerciseKcal }"
                />
                <div
                  class="w-full bg-green-400"
                  :style="{ flex: day.stepsKcal }"
                />
              </div>
              <span class="text-[9px] text-muted-foreground">{{ day.label }}</span>
            </div>
          </div>
        </template>
      </CardContent>
    </Card>

    <!-- Tevékenység szerinti bontás -->
    <Card class="rounded-2xl" v-if="breakdownByActivity.length">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Dumbbell class="h-4 w-4 text-purple-500" />
          {{ $t("summary.activityBreakdown") }}
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-2">
        <div
          v-for="item in breakdownByActivity"
          :key="item.name"
          class="flex items-center justify-between rounded-xl border px-3 py-2.5"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="font-medium text-sm truncate">{{ item.name }}</div>
            <span class="text-xs text-muted-foreground shrink-0">{{ item.count }}×</span>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <span class="text-xs text-muted-foreground">{{ formatDuration(item.minutes) }}</span>
            <span class="text-sm font-semibold text-orange-600 dark:text-orange-400">{{ Math.round(item.kcal).toLocaleString(dateLocale.value) }} kcal</span>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Health vitals trend -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <TrendingUp class="h-4 w-4 text-blue-500" />
          {{ $t("summary.vitalsTrend") }}
        </CardTitle>
        <CardDescription>{{ $t("summary.vitalsTrendDesc") }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <template v-if="loading">
          <Skeleton class="h-10 w-full rounded-xl" />
          <Skeleton class="h-10 w-full rounded-xl" />
          <Skeleton class="h-10 w-full rounded-xl" />
        </template>
        <template v-else>
          <!-- HR trend -->
          <div class="flex items-center justify-between rounded-xl border px-3 py-2.5">
            <div class="flex items-center gap-2.5">
              <div class="h-8 w-8 rounded-lg bg-red-100 flex items-center justify-center">
                <Heart class="h-4 w-4 text-red-500" />
              </div>
              <div>
                <div class="text-sm font-medium">{{ $t("dashboard.heartRate") }}</div>
                <div class="text-xs text-muted-foreground">{{ hrCurrent != null ? Math.round(hrCurrent * 10) / 10 + ' BPM' : $t("summary.trendNoData") }}</div>
              </div>
            </div>
            <div v-if="hrTrend" class="flex items-center gap-1 text-sm font-semibold" :class="hrTrend.up ? 'text-red-500' : hrTrend.down ? 'text-green-600' : 'text-muted-foreground'">
              <component :is="hrTrend.up ? TrendingUp : hrTrend.down ? TrendingDown : Minus" class="h-4 w-4" />
              {{ hrTrend.diff > 0 ? '+' : '' }}{{ hrTrend.diff }}
            </div>
            <div v-else class="text-xs text-muted-foreground">{{ $t("summary.trendNoData") }}</div>
          </div>
          <!-- BP trend -->
          <div class="flex items-center justify-between rounded-xl border px-3 py-2.5">
            <div class="flex items-center gap-2.5">
              <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center">
                <Activity class="h-4 w-4 text-blue-500" />
              </div>
              <div>
                <div class="text-sm font-medium">{{ $t("dashboard.bloodPressure") }}</div>
                <div class="text-xs text-muted-foreground">{{ bpCurrentSys != null ? bpCurrentSys + ' mmHg sys' : $t("summary.trendNoData") }}</div>
              </div>
            </div>
            <div v-if="bpTrend" class="flex items-center gap-1 text-sm font-semibold" :class="bpTrend.up ? 'text-red-500' : bpTrend.down ? 'text-green-600' : 'text-muted-foreground'">
              <component :is="bpTrend.up ? TrendingUp : bpTrend.down ? TrendingDown : Minus" class="h-4 w-4" />
              {{ bpTrend.diff > 0 ? '+' : '' }}{{ bpTrend.diff }}
            </div>
            <div v-else class="text-xs text-muted-foreground">{{ $t("summary.trendNoData") }}</div>
          </div>
          <!-- Weight trend -->
          <div class="flex items-center justify-between rounded-xl border px-3 py-2.5">
            <div class="flex items-center gap-2.5">
              <div class="h-8 w-8 rounded-lg bg-purple-100 flex items-center justify-center">
                <Weight class="h-4 w-4 text-purple-500" />
              </div>
              <div>
                <div class="text-sm font-medium">{{ $t("dashboard.weight") }}</div>
                <div class="text-xs text-muted-foreground">{{ wCurrent != null ? (Math.round(wCurrent * 10) / 10) + ' kg' : $t("summary.trendNoData") }}</div>
              </div>
            </div>
            <div v-if="wTrend" class="flex items-center gap-1 text-sm font-semibold text-muted-foreground">
              <component :is="wTrend.up ? TrendingUp : wTrend.down ? TrendingDown : Minus" class="h-4 w-4" />
              {{ wTrend.diff > 0 ? '+' : '' }}{{ wTrend.diff }} kg
            </div>
            <div v-else class="text-xs text-muted-foreground">{{ $t("summary.trendNoData") }}</div>
          </div>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
