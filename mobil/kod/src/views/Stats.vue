<script setup>
import { ref, inject, onMounted, onBeforeUnmount, watch, nextTick, computed } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { Chart, registerables } from "chart.js"
import api from "@/lib/api"
import { storageGet } from "@/lib/storage"
import { useAccess } from "@/composables/useAccess"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Heart, Activity, Weight, Crown, Lock, Footprints, Dumbbell, Flame, Droplet, Moon, CheckCircle2, AlertCircle, AlertTriangle } from "lucide-vue-next"

Chart.register(...registerables)

const router = useRouter()
const { t, locale } = useI18n()
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")
const authUser = ref(JSON.parse(storageGet("auth_user") || "null"))
const { isPro } = useAccess(authUser)
const wsEvent        = inject("wsEvent", ref(null))
const refreshTrigger = inject("refreshTrigger", ref(0))


const periods = computed(() => [
  { label: t("stats.period.day1"), days: 1, pro: false },
  { label: t("stats.period.week1"), days: 7, pro: false },
  { label: t("stats.period.month1"), days: 30, pro: false },
  { label: t("stats.period.month3"), days: 90, pro: true },
  { label: t("stats.period.all"), days: 3650, pro: true },
])
const selectedPeriod = ref(30)

const loading = ref(true)
const hrData = ref([])
const bpData = ref([])
const wData = ref([])
const stepsData = ref([])
const exerciseData = ref([])
const calorieData = ref([])
const waterData = ref([])
const sleepData = ref([])
const avgHR = ref(null)
const avgBP = ref({ systolic: null, diastolic: null })


const hrCanvas = ref(null)
const bpCanvas = ref(null)
const wCanvas = ref(null)
const stepsCanvas = ref(null)
const exerciseCanvas = ref(null)
const calorieCanvas = ref(null)
const waterCanvas = ref(null)
const sleepCanvas = ref(null)

let hrChart = null
let bpChart = null
let wChart = null
let stepsChart = null
let exerciseChart = null
let calorieChart = null
let waterChart = null
let sleepChart = null

function toLocalDateStr(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, "0")
  const d = String(date.getDate()).padStart(2, "0")
  return `${y}-${m}-${d}`
}

function getDateRange(days) {
  const start = new Date()
  start.setDate(start.getDate() - days + 1)
  const end = new Date()
  end.setDate(end.getDate() + 1)
  return {
    start: toLocalDateStr(start),
    end: toLocalDateStr(end),
  }
}

function formatLabel(iso) {
  if (selectedPeriod.value === 1) {
    return new Date(iso).toLocaleTimeString(dateLocale.value, { hour: "2-digit", minute: "2-digit" })
  }
  return new Date(iso).toLocaleDateString(dateLocale.value, { month: "short", day: "numeric" })
}

function hrStatus(hr) {
  if (!hr || hr <= 0) return null
  if (hr < 60)   return { label: t("dashboard.low"),    color: "text-blue-600", icon: AlertCircle }
  if (hr <= 100) return { label: t("dashboard.normal"), color: "text-green-600", icon: CheckCircle2 }
  return { label: t("dashboard.high"), color: "text-red-600", icon: AlertTriangle }
}

function bpStatus(sys, dia) {
  if (!sys || !dia) return null
  if (sys < 120 && dia < 80)  return { label: t("dashboard.normal"),   color: "text-green-600", icon: CheckCircle2 }
  if (sys < 130 && dia < 80)  return { label: t("dashboard.elevated"), color: "text-yellow-600", icon: AlertCircle }
  if (sys < 140 || dia < 90)  return { label: t("dashboard.high1"),    color: "text-orange-600", icon: AlertTriangle }
  return { label: t("dashboard.high2"), color: "text-red-600", icon: AlertTriangle }
}

function getChartColors() {
  const style = getComputedStyle(document.documentElement)
  return {
    text: style.getPropertyValue("--foreground").trim() || "#000",
    grid: style.getPropertyValue("--border").trim() || "#e5e7eb",
    muted: style.getPropertyValue("--muted-foreground").trim() || "#6b7280",
  }
}

function baseChartOptions(yLabel) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
      legend: { position: "top", labels: { boxWidth: 12, padding: 16, font: { size: 12 } } },
      tooltip: { cornerRadius: 8 },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { maxTicksLimit: 8, font: { size: 11 } },
      },
      y: {
        grid: { color: "rgba(0,0,0,0.06)" },
        ticks: { font: { size: 11 } },
        title: { display: !!yLabel, text: yLabel, font: { size: 11 } },
      },
    },
  }
}

function destroyCharts() {
  if (hrChart) { hrChart.destroy(); hrChart = null }
  if (bpChart) { bpChart.destroy(); bpChart = null }
  if (wChart) { wChart.destroy(); wChart = null }
  if (stepsChart) { stepsChart.destroy(); stepsChart = null }
  if (exerciseChart) { exerciseChart.destroy(); exerciseChart = null }
  if (calorieChart) { calorieChart.destroy(); calorieChart = null }
  if (waterChart) { waterChart.destroy(); waterChart = null }
  if (sleepChart) { sleepChart.destroy(); sleepChart = null }
}

function buildHRChart() {
  if (!hrCanvas.value || !hrData.value.length) return
  if (hrChart) hrChart.destroy()
  const labels = hrData.value.map(d => formatLabel(d.recorded_at || d.created_at))
  const values = hrData.value.map(d => d.heart_rate)
  hrChart = new Chart(hrCanvas.value, {
    type: "line",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.heartRate"),
        data: values,
        borderColor: "rgb(239, 68, 68)",
        backgroundColor: "rgba(239, 68, 68, 0.08)",
        borderWidth: 2,
        pointRadius: values.length > 30 ? 1 : values.length < 5 ? 6 : 4,
        pointHoverRadius: 8,
        fill: true,
        tension: 0.3,
      }],
    },
    options: baseChartOptions("BPM"),
  })
}

function buildBPChart() {
  if (!bpCanvas.value || !bpData.value.length) return
  if (bpChart) bpChart.destroy()
  const labels = bpData.value.map(d => formatLabel(d.recorded_at || d.created_at))
  bpChart = new Chart(bpCanvas.value, {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          label: t("stats.chartLabels.systolic"),
          data: bpData.value.map(d => d.systolic),
          borderColor: "rgb(59, 130, 246)",
          backgroundColor: "rgba(59, 130, 246, 0.08)",
          borderWidth: 2,
          pointRadius: bpData.value.length > 30 ? 1 : bpData.value.length < 5 ? 6 : 4,
          pointHoverRadius: 8,
          fill: false,
          tension: 0.3,
        },
        {
          label: t("stats.chartLabels.diastolic"),
          data: bpData.value.map(d => d.diastolic),
          borderColor: "rgb(147, 197, 253)",
          backgroundColor: "rgba(147, 197, 253, 0.08)",
          borderWidth: 2,
          pointRadius: bpData.value.length > 30 ? 1 : bpData.value.length < 5 ? 6 : 4,
          pointHoverRadius: 8,
          fill: false,
          tension: 0.3,
        },
      ],
    },
    options: baseChartOptions("mmHg"),
  })
}

function buildWChart() {
  if (!wCanvas.value || !wData.value.length) return
  if (wChart) wChart.destroy()
  const labels = wData.value.map(d => formatLabel(d.recorded_at || d.created_at))
  wChart = new Chart(wCanvas.value, {
    type: "line",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.weight"),
        data: wData.value.map(d => d.weight),
        borderColor: "rgb(168, 85, 247)",
        backgroundColor: "rgba(168, 85, 247, 0.08)",
        borderWidth: 2,
        pointRadius: wData.value.length > 30 ? 1 : wData.value.length < 5 ? 6 : 4,
        pointHoverRadius: 8,
        fill: true,
        tension: 0.3,
      }],
    },
    options: baseChartOptions("kg"),
  })
}

function buildStepsChart() {
  if (!stepsCanvas.value || !stepsData.value.length) return
  if (stepsChart) stepsChart.destroy()
  const labels = stepsData.value.map(d => formatLabel(d.recorded_at || d.created_at))
  stepsChart = new Chart(stepsCanvas.value, {
    type: "bar",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.steps"),
        data: stepsData.value.map(d => d.steps),
        backgroundColor: "rgba(34, 197, 94, 0.7)",
        borderColor: "rgb(34, 197, 94)",
        borderWidth: 1,
        borderRadius: 4,
      }],
    },
    options: baseChartOptions(""),
  })
}

function buildExerciseChart() {
  if (!exerciseCanvas.value || !exerciseData.value.length) return
  if (exerciseChart) exerciseChart.destroy()
  const labels = exerciseData.value.map(d => formatLabel(d.begin || d.created_at))
  const minutes = exerciseData.value.map(d => {
    if (!d.begin || !d.end) return 0
    return Math.round((new Date(d.end) - new Date(d.begin)) / 60000)
  })
  exerciseChart = new Chart(exerciseCanvas.value, {
    type: "bar",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.exerciseMinutes"),
        data: minutes,
        backgroundColor: "rgba(249, 115, 22, 0.7)",
        borderColor: "rgb(249, 115, 22)",
        borderWidth: 1,
        borderRadius: 4,
      }],
    },
    options: baseChartOptions("min"),
  })
}

function buildWaterChart() {
  if (!waterCanvas.value || !waterData.value.length) return
  if (waterChart) waterChart.destroy()
  
  const byDay = new Map()
  waterData.value.forEach(d => {
    const iso = d.recorded_at || d.created_at
    if (!iso) return
    const key = String(iso).includes("T") ? String(iso).split("T")[0] : String(iso).split(" ")[0]
    byDay.set(key, (byDay.get(key) || 0) + Number(d.amount_ml || 0))
  })
  const sortedDays = Array.from(byDay.keys()).sort()
  const labels = sortedDays.map(d => new Date(d).toLocaleDateString(dateLocale.value, { month: "short", day: "numeric" }))
  const values = sortedDays.map(d => byDay.get(d))
  waterChart = new Chart(waterCanvas.value, {
    type: "bar",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.water"),
        data: values,
        backgroundColor: "rgba(59, 130, 246, 0.6)",
        borderColor: "rgb(59, 130, 246)",
        borderWidth: 1,
        borderRadius: 4,
      }],
    },
    options: baseChartOptions("ml"),
  })
}

function buildSleepChart() {
  if (!sleepCanvas.value || !sleepData.value.length) return
  if (sleepChart) sleepChart.destroy()
  const sorted = sleepData.value.slice().sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at))
  const labels = sorted.map(d => new Date(d.recorded_at).toLocaleDateString(dateLocale.value, { month: "short", day: "numeric" }))
  const values = sorted.map(d => Number(d.hours || 0))
  sleepChart = new Chart(sleepCanvas.value, {
    type: "bar",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.sleep"),
        data: values,
        backgroundColor: "rgba(99, 102, 241, 0.6)",
        borderColor: "rgb(99, 102, 241)",
        borderWidth: 1,
        borderRadius: 4,
      }],
    },
    options: baseChartOptions("h"),
  })
}

function buildCalorieChart() {
  if (!calorieCanvas.value || !calorieData.value.length) return
  if (calorieChart) calorieChart.destroy()
  const labels = calorieData.value.map(d => formatLabel(d.recorded_at || d.created_at))
  const values = calorieData.value.map(d => d.data)
  calorieChart = new Chart(calorieCanvas.value, {
    type: "bar",
    data: {
      labels,
      datasets: [{
        label: t("stats.chartLabels.calories"),
        data: values,
        backgroundColor: "rgba(239, 68, 68, 0.6)",
        borderColor: "rgb(239, 68, 68)",
        borderWidth: 1,
        borderRadius: 4,
      }],
    },
    options: baseChartOptions("kcal"),
  })
}

const heatmapCells = computed(() => {
  const days = Math.min(selectedPeriod.value, 90)
  if (days < 7) return []

  const activityMap = new Map()
  const addActivity = (items, dateKey) => {
    items.forEach(item => {
      const raw = item[dateKey]
      if (!raw) return
      const dateStr = String(raw).includes("T")
        ? String(raw).split("T")[0]
        : String(raw).split(" ")[0]
      activityMap.set(dateStr, (activityMap.get(dateStr) || 0) + 1)
    })
  }
  addActivity(hrData.value, "recorded_at")
  addActivity(bpData.value, "recorded_at")
  addActivity(wData.value, "recorded_at")
  addActivity(stepsData.value, "recorded_at")
  addActivity(calorieData.value, "recorded_at")
  addActivity(exerciseData.value, "begin")
  addActivity(waterData.value, "recorded_at")
  addActivity(sleepData.value, "recorded_at")

  const result = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  for (let i = days - 1; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(d.getDate() - i)
    const dateStr = toLocalDateStr(d)
    const count = activityMap.get(dateStr) || 0
    let level = 0
    if (count >= 1) level = 1
    if (count >= 3) level = 2
    if (count >= 5) level = 3
    if (count >= 7) level = 4
    result.push({ date: dateStr, count, level, dayOfWeek: d.getDay() })
  }

  if (result.length > 0) {
    const firstDow = result[0].dayOfWeek
    for (let i = 0; i < firstDow; i++) result.unshift({ empty: true })
  }
  return result
})

async function load() {
  loading.value = true
  destroyCharts()
  const { start, end } = getDateRange(selectedPeriod.value)
  const params = { start_date: start, end_date: end }
  try {
    const [hrRes, bpRes, wRes, avgHRRes, avgBPRes, stepsRes, exerciseRes, calRes, waterRes, sleepRes] = await Promise.allSettled([
      api.get("/heart-rates", { params }),
      api.get("/blood-pressures", { params }),
      api.get("/weights", { params }),
      api.get("/average/heart-rates", { params }),
      api.get("/average/blood-pressures", { params }),
      api.get("/steps", { params }),
      api.get("/exercises"),
      api.get("/calories", { params }),
      api.get("/waters", { params }),
      api.get("/sleeps", { params }),
    ])
    if (hrRes.status === "fulfilled") hrData.value = hrRes.value.data
    if (bpRes.status === "fulfilled") bpData.value = bpRes.value.data
    if (wRes.status === "fulfilled") wData.value = wRes.value.data
    if (avgHRRes.status === "fulfilled") avgHR.value = avgHRRes.value.data
    if (avgBPRes.status === "fulfilled") avgBP.value = avgBPRes.value.data
    if (stepsRes.status === "fulfilled") stepsData.value = stepsRes.value.data
    if (exerciseRes.status === "fulfilled") {
      
      const startDate = new Date(start)
      exerciseData.value = exerciseRes.value.data.filter(e => new Date(e.begin) >= startDate)
    }
    if (calRes.status === "fulfilled") calorieData.value = calRes.value.data
    if (waterRes.status === "fulfilled") waterData.value = waterRes.value.data
    if (sleepRes.status === "fulfilled") sleepData.value = sleepRes.value.data
  } catch {}
  loading.value = false
  await nextTick()
  buildHRChart()
  buildBPChart()
  buildWChart()
  buildStepsChart()
  buildExerciseChart()
  buildCalorieChart()
  buildWaterChart()
  buildSleepChart()
}

onMounted(load)
onBeforeUnmount(destroyCharts)
watch(selectedPeriod, load)
watch(refreshTrigger, (v, old) => { if (v !== old) load() })


watch(wsEvent, (e) => {
  if (!e) return
  const entryType = e.type || e.data?.type
  if (["heart_rate", "blood_pressure", "weight", "steps", "exercise", "calorie", "water", "sleep"].includes(entryType)) {
    load()
  }
})
</script>

<template>
  <div class="space-y-4">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h2 class="text-lg font-semibold">{{ $t("stats.title") }}</h2>
        <p class="text-sm text-muted-foreground">{{ $t("stats.subtitle") }}</p>
      </div>
      <div class="flex gap-1.5 bg-muted rounded-xl p-1">
        <Button
          v-for="p in periods"
          :key="p.days"
          :variant="selectedPeriod === p.days ? 'default' : 'ghost'"
          size="sm"
          class="rounded-lg h-8 px-3 gap-1"
          :class="p.pro && !isPro ? 'opacity-60' : ''"
          @click="p.pro && !isPro ? router.push('/app/upgrade') : (selectedPeriod = p.days)"
        >
          <Lock v-if="p.pro && !isPro" class="h-3 w-3" />
          {{ p.label }}
        </Button>
      </div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <Card class="rounded-2xl">
        <CardContent class="p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
            <Heart class="h-5 w-5 text-red-500" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">{{ $t("stats.avgHeartRate") }}</div>
            <div class="text-xl font-bold">
              <template v-if="loading"><Skeleton class="h-6 w-12" /></template>
              <template v-else>{{typeof avgHR === 'number' && avgHR > 0 ? avgHR : '—' }} <span class="text-sm font-normal text-muted-foreground">BPM</span></template>
            </div>
            <div v-if="!loading && hrStatus(avgHR)" class="text-xs mt-0.5 flex items-center gap-1" :class="hrStatus(avgHR).color">
              <component :is="hrStatus(avgHR).icon" class="h-3 w-3 shrink-0" />
              {{ hrStatus(avgHR).label }}
            </div>
          </div>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
            <Activity class="h-5 w-5 text-blue-500" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">{{ $t("stats.avgBloodPressure") }}</div>
            <div class="text-xl font-bold">
              <template v-if="loading"><Skeleton class="h-6 w-20" /></template>
              <template v-else>
                {{ avgBP.systolic != null ? avgBP.systolic : '—' }}
                <span class="text-muted-foreground font-normal">/{{ avgBP.diastolic != null ? avgBP.diastolic : '—' }}</span>
                <span class="text-sm font-normal text-muted-foreground ml-1">mmHg</span>
              </template>
            </div>
            <div v-if="!loading && bpStatus(avgBP.systolic, avgBP.diastolic)" class="text-xs mt-0.5 flex items-center gap-1" :class="bpStatus(avgBP.systolic, avgBP.diastolic).color">
              <component :is="bpStatus(avgBP.systolic, avgBP.diastolic).icon" class="h-3 w-3 shrink-0" />
              {{ bpStatus(avgBP.systolic, avgBP.diastolic).label }}
            </div>
          </div>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
            <Weight class="h-5 w-5 text-purple-500" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">{{ $t("stats.totalEntries") }}</div>
            <div class="text-xl font-bold">
              <template v-if="loading"><Skeleton class="h-6 w-8" /></template>
              <template v-else>{{ hrData.length + bpData.length + wData.length }}</template>
            </div>
            <div class="text-xs text-muted-foreground">{{ $t("stats.totalEntriesDesc") }}</div>
          </div>
        </CardContent>
      </Card>
    </div>

    
    <Card v-if="!loading && heatmapCells.length >= 7" class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Flame class="h-4 w-4 text-orange-500" />
          {{ $t("stats.heatmap") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.heatmapDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto pb-2">
          <div
            class="grid gap-0.5"
            style="grid-template-rows: repeat(7, 12px); grid-auto-flow: column; grid-auto-columns: 12px;"
            role="img"
            :aria-label="$t('stats.heatmapAria')"
          >
            <template v-for="(cell, idx) in heatmapCells" :key="cell.empty ? `e-${idx}` : cell.date">
              <div v-if="cell.empty" class="w-3 h-3" />
              <div
                v-else
                class="w-3 h-3 rounded-sm transition-colors"
                :class="{
                  'bg-muted': cell.level === 0,
                  'bg-emerald-200 dark:bg-emerald-900': cell.level === 1,
                  'bg-emerald-400 dark:bg-emerald-700': cell.level === 2,
                  'bg-emerald-500': cell.level === 3,
                  'bg-emerald-600 dark:bg-emerald-400': cell.level === 4,
                }"
                :title="`${cell.date}: ${cell.count}`"
              />
            </template>
          </div>
        </div>
        <div class="mt-3 flex items-center justify-end gap-1.5 text-xs text-muted-foreground">
          <span>{{ $t("stats.heatmapLess") }}</span>
          <div class="w-3 h-3 rounded-sm bg-muted"></div>
          <div class="w-3 h-3 rounded-sm bg-emerald-200 dark:bg-emerald-900"></div>
          <div class="w-3 h-3 rounded-sm bg-emerald-400 dark:bg-emerald-700"></div>
          <div class="w-3 h-3 rounded-sm bg-emerald-500"></div>
          <div class="w-3 h-3 rounded-sm bg-emerald-600 dark:bg-emerald-400"></div>
          <span>{{ $t("stats.heatmapMore") }}</span>
        </div>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Heart class="h-4 w-4 text-red-500" />
          {{ $t("stats.heartRate") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.heartRateDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="hrData.length">
          <div class="relative h-48">
            <canvas ref="hrCanvas" role="img" :aria-label="$t('stats.heartRateChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Activity class="h-4 w-4 text-blue-500" />
          {{ $t("stats.bloodPressure") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.bloodPressureDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="bpData.length">
          <div class="relative h-48">
            <canvas ref="bpCanvas" role="img" :aria-label="$t('stats.bloodPressureChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Weight class="h-4 w-4 text-purple-500" />
          {{ $t("stats.weight") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.weightDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="wData.length">
          <div class="relative h-48">
            <canvas ref="wCanvas" role="img" :aria-label="$t('stats.weightChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Footprints class="h-4 w-4 text-green-500" />
          {{ $t("stats.steps") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.stepsDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="stepsData.length">
          <div class="relative h-48">
            <canvas ref="stepsCanvas" role="img" :aria-label="$t('stats.stepsChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Dumbbell class="h-4 w-4 text-orange-500" />
          {{ $t("stats.exercise") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.exerciseDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="exerciseData.length">
          <div class="relative h-48">
            <canvas ref="exerciseCanvas" role="img" :aria-label="$t('stats.exerciseChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Flame class="h-4 w-4 text-red-500" />
          {{ $t("stats.calorie") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.calorieDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="calorieData.length">
          <div class="relative h-48">
            <canvas ref="calorieCanvas" role="img" :aria-label="$t('stats.calorieChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Droplet class="h-4 w-4 text-blue-500" />
          {{ $t("stats.water") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.waterDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="waterData.length">
          <div class="relative h-48">
            <canvas ref="waterCanvas" role="img" :aria-label="$t('stats.waterChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-2">
        <CardTitle class="text-base flex items-center gap-2">
          <Moon class="h-4 w-4 text-indigo-500" />
          {{ $t("stats.sleep") }}
        </CardTitle>
        <CardDescription>{{ $t("stats.sleepDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-xl" />
        </template>
        <template v-else-if="sleepData.length">
          <div class="relative h-48">
            <canvas ref="sleepCanvas" role="img" :aria-label="$t('stats.sleepChartAria')" />
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-center h-48 text-sm text-muted-foreground border rounded-xl border-dashed">
            {{ $t("stats.noData") }}
          </div>
        </template>
      </CardContent>
    </Card>

  </div>
</template>
