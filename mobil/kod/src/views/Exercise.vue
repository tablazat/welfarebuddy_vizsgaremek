<script setup>
import { ref, inject, computed, onMounted, watch } from "vue"
import { useI18n } from "vue-i18n"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { hapticMedium, hapticHeavy } from "@/lib/haptics"
import { storageGet } from "@/lib/storage"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Skeleton } from "@/components/ui/skeleton"
import { Separator } from "@/components/ui/separator"
import {
  Dumbbell, Plus, Loader2, Clock, Timer, ListChecks,
  Footprints, Flame, TrendingUp, ChevronDown, ChevronUp, Trash2,
  Target, Waves, Snowflake, Swords, Flower, Music, Shapes,
} from "lucide-vue-next"
import { localizedActivityName } from "@/lib/utils"

const { t, locale } = useI18n()
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")
const globalQuery    = inject("globalQuery")
const wsEvent        = inject("wsEvent", ref(null))
const refreshTrigger = inject("refreshTrigger", ref(0))

const activeTab = ref("overview")
const loading = ref(false)
const saving = ref(false)
const savingSteps = ref(false)
const deletingId = ref(null)
const error = ref("")
const successMsg = ref("")

// Data
const activities = ref([])
const exercises = ref([])
const todaySteps = ref(null)
const stepsHistory = ref([])

// Category mapping - keys are i18n category key suffixes
const categoryTypeMap = {
  cardio: [
    "running", "running.treadmill", "biking", "biking.stationary", "walking",
    "hiking", "elliptical", "stair_climbing", "stair_climbing.machine",
    "jump_rope", "mixed_metabolic_cardio",
  ],
  strength: [
    "strength_training", "strength_training.functional", "calisthenics",
    "weightlifting", "core_training", "crossfit", "bootcamp",
  ],
  ballSports: [
    "basketball", "football.soccer", "football.american", "football.australian",
    "volleyball", "handball", "tennis", "table_tennis", "badminton", "baseball",
    "softball", "cricket", "rugby", "hockey", "hockey.roller", "lacrosse",
    "squash", "racquetball", "pickleball", "water_polo",
  ],
  waterSports: [
    "swimming", "swimming.pool", "swimming.open_water", "surfing", "sailing",
    "scuba_diving", "water_fitness", "water_sports", "paddle_sports",
  ],
  winterSports: [
    "skiing", "skiing.cross_country", "skiing.downhill", "snowboarding",
    "snowshoeing", "snow_sports", "ice_skating", "curling",
  ],
  martialArts: [
    "boxing", "kickboxing", "martial_arts", "fencing", "wrestling",
  ],
  mindBody: [
    "yoga", "pilates", "meditation", "tai_chi", "guided_breathing",
    "stretching", "flexibility", "barre", "cooldown", "preparation_and_recovery",
  ],
  dance: [
    "dancing", "dancing.social", "dancing.cardio",
  ],
  other: [
    "other", "play", "fishing", "hunting", "horseback_riding", "golf", "archery",
    "bowling", "gymnastics", "track_and_field", "paragliding", "rock_climbing",
    "fitness_gaming", "disc_sports", "frisbee_disc", "exercise_class",
    "rowing", "rowing.machine", "wheelchair", "wheelchair.walkpace",
    "wheelchair.runpace",
  ],
}

const categoryIconMap = {
  cardio: Footprints,
  strength: Dumbbell,
  ballSports: Target,
  waterSports: Waves,
  winterSports: Snowflake,
  martialArts: Swords,
  mindBody: Flower,
  dance: Music,
  other: Shapes,
}

const _unknownTypesSeen = new Set()
function getCategoryKey(type) {
  for (const [catKey, types] of Object.entries(categoryTypeMap)) {
    if (types.includes(type)) return catKey
  }
  if (import.meta.env.DEV && type && !_unknownTypesSeen.has(type)) {
    _unknownTypesSeen.add(type)
    console.warn(`[Exercise] Unknown activity type "${type}" → bucketed to "other". Add to categoryTypeMap.`)
  }
  return "other"
}

const groupedActivities = computed(() => {
  const groups = {}
  for (const act of activities.value) {
    const catKey = getCategoryKey(act.type)
    const catLabel = t(`exercise.categories.${catKey}`)
    if (!groups[catKey]) groups[catKey] = { label: catLabel, acts: [] }
    groups[catKey].acts.push(act)
  }
  for (const catKey of Object.keys(groups)) {
    groups[catKey].acts.sort((a, b) =>
      localizedActivityName(a, locale.value).localeCompare(localizedActivityName(b, locale.value), locale.value)
    )
  }
  return groups
})

// Exercise form — külön dátum + idő, HealthKit-kompatibilis
function todayDate() {
  return new Date().toISOString().slice(0, 10)
}
function nowTime() {
  const d = new Date()
  return String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0")
}

const form = ref({
  activity_id: "",
  date: todayDate(),
  beginTime: nowTime(),
  endTime: nowTime(),
  source: "manual", // "manual" | "healthkit" | "google_fit" | stb.
})
const stepsForm = ref({ steps: "", date: todayDate(), mode: "add" })

// Összerakja a dátum + idő-t "YYYY-MM-DD HH:mm:ss" formátumba
function combineDatetime(date, time) {
  if (!date || !time) return null
  return `${date} ${time}:00`
}

// Computed begin/end a form-ból (duration preview-hoz)
const formBeginDt = computed(() => form.value.date && form.value.beginTime ? new Date(`${form.value.date}T${form.value.beginTime}`) : null)
const formEndDt = computed(() => {
  if (!form.value.date || !form.value.endTime) return null
  let end = new Date(`${form.value.date}T${form.value.endTime}`)
  if (formBeginDt.value && end <= formBeginDt.value) end = new Date(end.getTime() + 86400000)
  return end
})

// Steps goal (user configurable, localStorage-backed)
const STEP_GOAL = parseInt(storageGet("step_goal") || "10000")

const stepsPercent = computed(() => {
  if (!todaySteps.value?.steps) return 0
  return Math.min(100, Math.round((todaySteps.value.steps / STEP_GOAL) * 100))
})

const todayCalEstimate = computed(() => {
  // Durva becslés: ~0.04 kcal/lépés
  if (!todaySteps.value?.steps) return 0
  return Math.round(todaySteps.value.steps * 0.04)
})

const todayExerciseMinutes = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  let total = 0
  for (const ex of exercises.value) {
    const exDate = (ex.begin || "").slice(0, 10)
    if (exDate === today) {
      const diff = new Date(ex.end) - new Date(ex.begin)
      if (diff > 0) total += diff / 60000
    }
  }
  return Math.round(total)
})

function formatDateTime(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleString(dateLocale.value, {
    month: "short", day: "numeric", hour: "2-digit", minute: "2-digit",
  })
}

function formatDate(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleDateString(dateLocale.value, {
    month: "short", day: "numeric",
  })
}

function durationMinutes(begin, end) {
  if (!begin || !end) return null
  const diff = new Date(end) - new Date(begin)
  if (diff <= 0) return null
  const mins = Math.round(diff / 60000)
  if (mins < 60) return `${mins} ${t('exercise.minutes')}`
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}

function getActivityName(activityId) {
  const act = activities.value.find((a) => a.id === activityId)
  return localizedActivityName(act, locale.value) || t("exercise.unknownActivity")
}

const filteredExercises = computed(() => {
  const q = (globalQuery?.value || "").toLowerCase().trim()
  if (!q) return exercises.value
  return exercises.value.filter((ex) => {
    const name = getActivityName(ex.activity_id).toLowerCase()
    return name.includes(q) || (ex.begin || "").includes(q)
  })
})

// Expanded category tracking
const expandedCat = ref(null)
function toggleCat(cat) {
  expandedCat.value = expandedCat.value === cat ? null : cat
}

// Duration slider (perc) – beginTime-ból számolja az endTime-ot
const DURATION_PRESETS = [15, 30, 45, 60, 90]
const durationSlider = ref(30)
function applyDurationPreset(mins) {
  if (!form.value.beginTime) return
  durationSlider.value = mins
  const [h, m] = form.value.beginTime.split(":").map(Number)
  const start = new Date()
  start.setHours(h, m, 0, 0)
  const end = new Date(start.getTime() + mins * 60000)
  form.value.endTime = String(end.getHours()).padStart(2, "0") + ":" + String(end.getMinutes()).padStart(2, "0")
}
function formatDurationLabel(mins) {
  if (mins < 60) return `${mins} ${t('exercise.minutes')}`
  if (mins === 60) return `1 ${t('exercise.hour')}`
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m === 0 ? `${h} ${t('exercise.hour')}` : `${h}${t('exercise.hourShort')} ${m}${t('exercise.minuteShort')}`
}

async function loadAll() {
  loading.value = true
  try {
    const [actRes, exRes, stepsRes, stepsHistRes] = await Promise.allSettled([
      api.get("/activities"),
      api.get("/exercises"),
      api.get("/steps/today"),
      api.get("/steps", { params: { start_date: getWeekAgo(), end_date: getTomorrow() } }),
    ])
    if (actRes.status === "fulfilled") activities.value = actRes.value.data
    if (exRes.status === "fulfilled") exercises.value = exRes.value.data.slice().reverse()
    if (stepsRes.status === "fulfilled") todaySteps.value = stepsRes.value.data
    if (stepsHistRes.status === "fulfilled") stepsHistory.value = stepsHistRes.value.data
  } catch {}
  loading.value = false
}

function getWeekAgo() {
  const d = new Date()
  d.setDate(d.getDate() - 6)
  return d.toISOString().slice(0, 10)
}

function getTomorrow() {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().slice(0, 10)
}

onMounted(loadAll)
watch(refreshTrigger, (v, old) => { if (v !== old) loadAll() })

function clearMessages() {
  error.value = ""
  successMsg.value = ""
}

async function saveExercise() {
  clearMessages()
  if (!form.value.activity_id) { error.value = t("exercise.validation.activity"); return }
  if (!form.value.date) { error.value = t("exercise.validation.date"); return }
  if (!form.value.beginTime || !form.value.endTime) { error.value = t("exercise.validation.time"); return }

  const begin = combineDatetime(form.value.date, form.value.beginTime)
  const beginDt = new Date(`${form.value.date}T${form.value.beginTime}`)
  let endDt = new Date(`${form.value.date}T${form.value.endTime}`)
  if (endDt <= beginDt) endDt = new Date(endDt.getTime() + 86400000)
  const endDate = [endDt.getFullYear(), String(endDt.getMonth() + 1).padStart(2, "0"), String(endDt.getDate()).padStart(2, "0")].join("-")
  const end = `${endDate} ${form.value.endTime}:00`

  saving.value = true
  try {
    await api.post("/new-exercise", {
      activity_id: Number(form.value.activity_id),
      begin,
      end,
      // source: form.value.source, // HealthKit-hez a jövőben
    })
    form.value = { activity_id: "", date: todayDate(), beginTime: nowTime(), endTime: nowTime(), source: "manual" }
    successMsg.value = t("exercise.saved.exercise")
    expandedCat.value = null
    hapticMedium()
    await loadAll()
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}

async function saveSteps() {
  clearMessages()
  if (!stepsForm.value.steps || Number(stepsForm.value.steps) < 0) { error.value = t("exercise.validation.steps"); return }

  savingSteps.value = true
  try {
    await api.post("/new-steps", {
      steps: Number(stepsForm.value.steps),
      recorded_at: stepsForm.value.date || todayDate(),
      mode: stepsForm.value.mode,
    })
    stepsForm.value = { steps: "", date: todayDate(), mode: "add" }
    successMsg.value = t("exercise.saved.steps")
    hapticMedium()
    await loadAll()
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    savingSteps.value = false
  }
}

// Helpers for overview
const todayExercises = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return exercises.value.filter((ex) => (ex.begin || "").slice(0, 10) === today)
})

function getCategoryIcon(activityId) {
  const act = activities.value.find((a) => a.id === activityId)
  if (!act) return Shapes
  const catKey = getCategoryKey(act.type)
  return categoryIconMap[catKey] || Shapes
}

// Steps bar chart helpers
const stepsBarMax = computed(() => {
  const vals = []
  for (let i = 0; i < 7; i++) {
    const d = getStepDay(i)
    if (d?.steps) vals.push(d.steps)
  }
  return vals.length ? Math.max(...vals) : STEP_GOAL
})

function getStepDay(daysAgo) {
  const d = new Date()
  d.setDate(d.getDate() - (6 - daysAgo))
  const key = d.toISOString().slice(0, 10)
  return stepsHistory.value.find((s) => s.recorded_at === key) || null
}

function getDayLabel(daysAgo) {
  const d = new Date()
  d.setDate(d.getDate() - (6 - daysAgo))
  return d.toLocaleDateString(dateLocale.value, { weekday: "short" })
}

async function deleteExercise(id) {
  if (!confirm(t("exercise.confirmDelete"))) return
  deletingId.value = id
  try {
    hapticHeavy()
    await api.delete(`/exercises/${id}`)
    successMsg.value = t("exercise.deleted")
    await loadAll()
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    deletingId.value = null
  }
}

// WebSocket
watch(wsEvent, (e) => {
  if (!e) return
  if (["activity", "steps"].includes(e.type)) loadAll()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold flex items-center gap-2">
        <Dumbbell class="h-5 w-5" />
        {{ $t("exercise.title") }}
      </h2>
      <p class="text-sm text-muted-foreground">{{ $t("exercise.subtitle") }}</p>
    </div>

    <Alert v-if="error" variant="destructive" class="rounded-xl">
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>
    <Alert v-if="successMsg" class="rounded-xl border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
      <AlertDescription>{{ successMsg }}</AlertDescription>
    </Alert>

    <Tabs v-model="activeTab" class="w-full">
      <TabsList class="grid grid-cols-3 w-full rounded-xl">
        <TabsTrigger value="overview" class="gap-1.5">
          <TrendingUp class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">{{ $t("exercise.overview") }}</span>
          <span class="sm:hidden">{{ $t("exercise.overviewShort") }}</span>
        </TabsTrigger>
        <TabsTrigger value="new" class="gap-1.5">
          <Plus class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">{{ $t("exercise.newEntry") }}</span>
          <span class="sm:hidden">{{ $t("exercise.newEntryShort") }}</span>
        </TabsTrigger>
        <TabsTrigger value="history" class="gap-1.5">
          <ListChecks class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">{{ $t("exercise.history") }}</span>
          <span class="sm:hidden">{{ $t("exercise.historyShort") }}</span>
        </TabsTrigger>
      </TabsList>

      <!-- ==================== OVERVIEW TAB ==================== -->
      <TabsContent value="overview" class="mt-4 space-y-4">

        <!-- Summary cards -->
        <div class="grid grid-cols-3 gap-3">
          <!-- Steps -->
          <Card class="rounded-2xl">
            <CardContent class="p-4 text-center">
              <div class="text-2xl mb-1">👟</div>
              <template v-if="loading">
                <Skeleton class="h-7 w-16 mx-auto mb-1" />
              </template>
              <template v-else>
                <div class="text-2xl font-bold">{{ todaySteps?.steps?.toLocaleString(dateLocale.value) || "0" }}</div>
              </template>
              <div class="text-xs text-muted-foreground">{{ $t("exercise.steps") }}</div>
              <!-- Mini progress bar -->
              <div class="mt-2 h-1.5 bg-muted rounded-full overflow-hidden">
                <div class="h-full bg-green-500 rounded-full transition-all" :style="{ width: stepsPercent + '%' }" />
              </div>
              <div class="text-[10px] text-muted-foreground mt-0.5">{{ stepsPercent }}% / {{ STEP_GOAL.toLocaleString(dateLocale.value) }}</div>
            </CardContent>
          </Card>

          <!-- Exercise minutes -->
          <Card class="rounded-2xl">
            <CardContent class="p-4 text-center">
              <div class="text-2xl mb-1">⏱️</div>
              <template v-if="loading">
                <Skeleton class="h-7 w-12 mx-auto mb-1" />
              </template>
              <template v-else>
                <div class="text-2xl font-bold">{{ todayExerciseMinutes }}</div>
              </template>
              <div class="text-xs text-muted-foreground">{{ $t("exercise.exerciseMinutes") }}</div>
            </CardContent>
          </Card>

          <!-- Calorie estimate -->
          <Card class="rounded-2xl">
            <CardContent class="p-4 text-center">
              <div class="text-2xl mb-1">🔥</div>
              <template v-if="loading">
                <Skeleton class="h-7 w-12 mx-auto mb-1" />
              </template>
              <template v-else>
                <div class="text-2xl font-bold">{{ todayCalEstimate }}</div>
              </template>
              <div class="text-xs text-muted-foreground">{{ $t("exercise.caloriesEstimated") }}</div>
            </CardContent>
          </Card>
        </div>

        <!-- Steps quick entry -->
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base flex items-center gap-2">
              <Footprints class="h-4 w-4 text-green-500" />
              {{ $t("exercise.todaySteps") }}
            </CardTitle>
            <CardDescription>{{ $t("exercise.todayStepsDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="flex gap-2 flex-wrap items-end" @submit.prevent="saveSteps">
              <Input
                v-model="stepsForm.steps"
                type="number"
                min="0"
                max="200000"
                placeholder="pl. 8500"
                class="flex-1 min-w-[120px]"
              />
              <Input
                v-model="stepsForm.date"
                type="date"
                class="w-40"
                :max="todayDate()"
              />
              <div class="flex rounded-xl border overflow-hidden w-fit">
                <button
                  type="button"
                  class="px-3 py-1.5 text-sm font-medium transition"
                  :class="stepsForm.mode === 'add' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                  @click="stepsForm.mode = 'add'"
                >{{ $t("exercise.modeAdd") }}</button>
                <button
                  type="button"
                  class="px-3 py-1.5 text-sm font-medium transition border-l"
                  :class="stepsForm.mode === 'overwrite' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                  @click="stepsForm.mode = 'overwrite'"
                >{{ $t("exercise.modeOverwrite") }}</button>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="savingSteps">
                <Loader2 v-if="savingSteps" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("exercise.saveExercise") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <!-- Steps 7-day bar chart -->
        <Card class="rounded-2xl">
          <CardHeader class="pb-2">
            <CardTitle class="text-base flex items-center gap-2">
              <Footprints class="h-4 w-4 text-green-500" />
              {{ $t("exercise.stepsLast7") }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <template v-if="loading">
              <div class="flex items-end gap-2 h-24">
                <Skeleton v-for="(h, i) in [40, 70, 55, 90, 65, 80, 50]" :key="i" class="flex-1 rounded" :style="{ height: h + 'px' }" />
              </div>
            </template>
            <template v-else>
              <div class="flex items-end gap-1.5 h-28">
                <div v-for="i in 7" :key="i" class="flex-1 flex flex-col items-center gap-1">
                  <span class="text-[10px] text-muted-foreground font-medium">
                    {{ getStepDay(i - 1)?.steps ? getStepDay(i - 1).steps.toLocaleString(dateLocale.value) : '' }}
                  </span>
                  <div
                    class="w-full rounded-t-md transition-all"
                    :class="getStepDay(i - 1)?.steps ? 'bg-green-400' : 'bg-muted'"
                    :style="{ height: getStepDay(i - 1)?.steps ? Math.max(4, (getStepDay(i - 1).steps / stepsBarMax * 72)) + 'px' : '4px' }"
                  />
                  <span class="text-[10px] text-muted-foreground">{{ getDayLabel(i - 1) }}</span>
                </div>
              </div>
            </template>
          </CardContent>
        </Card>

        <!-- Today's exercises -->
        <Card v-if="todayExercises.length" class="rounded-2xl">
          <CardHeader class="pb-2">
            <CardTitle class="text-base flex items-center gap-2">
              <Dumbbell class="h-4 w-4 text-orange-500" />
              {{ $t("exercise.todayExercises") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <div
              v-for="ex in todayExercises"
              :key="ex.id"
              class="flex items-center justify-between rounded-xl border bg-card px-3 py-2"
            >
              <div class="flex items-center gap-2.5">
                <component :is="getCategoryIcon(ex.activity_id)" class="h-5 w-5 text-orange-600 dark:text-orange-400 shrink-0" />
                <div>
                  <div class="font-medium text-sm">{{ getActivityName(ex.activity_id) }}</div>
                  <div class="text-xs text-muted-foreground">{{ formatDateTime(ex.begin) }} – {{ formatDateTime(ex.end) }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <div v-if="durationMinutes(ex.begin, ex.end)" class="text-xs font-medium px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                  {{ durationMinutes(ex.begin, ex.end) }}
                </div>
                <button
                  class="p-1.5 rounded-lg text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition"
                  :disabled="deletingId === ex.id"
                  @click="deleteExercise(ex.id)"
                >
                  <Loader2 v-if="deletingId === ex.id" class="h-4 w-4 animate-spin" />
                  <Trash2 v-else class="h-4 w-4" />
                </button>
              </div>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- ==================== NEW ENTRY TAB ==================== -->
      <TabsContent value="new" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("exercise.newExercise") }}</CardTitle>
            <CardDescription>{{ $t("exercise.newExerciseDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="loading" class="space-y-3">
              <Skeleton class="h-10 w-full rounded-xl" />
              <Skeleton class="h-10 w-full rounded-xl" />
            </div>
            <form v-else class="space-y-3" @submit.prevent="saveExercise">
              <!-- Grouped activity selector -->
              <div class="space-y-1.5">
                <Label>{{ $t("exercise.activity") }}</Label>
                <div class="space-y-1">
                  <div v-for="(group, catKey) in groupedActivities" :key="catKey">
                    <button
                      type="button"
                      class="w-full flex items-center justify-between rounded-xl border px-3 py-2 text-sm hover:bg-accent transition"
                      :class="expandedCat === catKey ? 'bg-accent border-primary/30' : ''"
                      @click="toggleCat(catKey)"
                    >
                      <span class="flex items-center gap-2">
                        <component :is="categoryIconMap[catKey] || Shapes" class="h-4 w-4 text-muted-foreground" />
                        <span class="font-medium">{{ group.label }}</span>
                        <span class="text-xs text-muted-foreground">({{ group.acts.length }})</span>
                      </span>
                      <ChevronUp v-if="expandedCat === catKey" class="h-4 w-4 text-muted-foreground" />
                      <ChevronDown v-else class="h-4 w-4 text-muted-foreground" />
                    </button>
                    <div v-if="expandedCat === catKey" class="grid grid-cols-2 sm:grid-cols-3 gap-1 mt-1 mb-2 pl-2">
                      <button
                        v-for="act in group.acts"
                        :key="act.id"
                        type="button"
                        class="text-left rounded-lg border px-2.5 py-1.5 text-sm transition hover:bg-accent"
                        :class="form.activity_id == act.id ? 'bg-primary text-primary-foreground border-primary hover:bg-primary/90' : ''"
                        @click="form.activity_id = act.id"
                      >
                        {{ localizedActivityName(act, locale) }}
                      </button>
                    </div>
                  </div>
                </div>
                <div v-if="form.activity_id" class="text-sm text-muted-foreground flex items-center gap-1.5 mt-1">
                  {{ $t("exercise.selected") }}: <span class="font-medium text-foreground">{{ getActivityName(form.activity_id) }}</span>
                </div>
              </div>

              <Separator />

              <!-- Dátum -->
              <div class="space-y-1.5">
                <Label>{{ $t("exercise.date") }}</Label>
                <Input v-model="form.date" type="date" :max="todayDate()" />
              </div>

              <!-- Kezdés / Befejezés idő -->
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                  <Label>{{ $t("exercise.beginTime") }}</Label>
                  <Input v-model="form.beginTime" type="time" />
                </div>
                <div class="space-y-1.5">
                  <Label>{{ $t("exercise.endTime") }}</Label>
                  <Input v-model="form.endTime" type="time" />
                </div>
              </div>

              <!-- Duration slider + gyors preset chipek -->
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <Label class="text-xs text-muted-foreground">{{ $t("exercise.durationSlider") }}</Label>
                  <span class="text-xs font-medium tabular-nums">{{ formatDurationLabel(durationSlider) }}</span>
                </div>
                <input
                  type="range"
                  min="5"
                  max="180"
                  step="5"
                  :value="durationSlider"
                  :disabled="!form.beginTime"
                  class="w-full accent-primary"
                  @input="applyDurationPreset(Number($event.target.value))"
                />
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="mins in DURATION_PRESETS"
                    :key="mins"
                    type="button"
                    class="text-xs font-medium px-3 py-1.5 rounded-full border hover:bg-accent transition"
                    :class="{ 'bg-primary text-primary-foreground border-primary': durationSlider === mins }"
                    :disabled="!form.beginTime"
                    @click="applyDurationPreset(mins)"
                  >
                    {{ formatDurationLabel(mins) }}
                  </button>
                </div>
              </div>

              <div
                v-if="formBeginDt && formEndDt && formEndDt > formBeginDt"
                class="text-sm text-muted-foreground flex items-center gap-1.5"
              >
                <Timer class="h-3.5 w-3.5" />
                {{ $t("exercise.duration") }}: {{ durationMinutes(formBeginDt, formEndDt) }}
              </div>

              <Button type="submit" class="gap-2 rounded-xl w-full sm:w-auto" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("exercise.saveExercise") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <!-- Steps entry -->
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base flex items-center gap-2">
              <Footprints class="h-4 w-4 text-green-500" />
              {{ $t("exercise.stepsEntry") }}
            </CardTitle>
            <CardDescription>{{ $t("exercise.stepsEntryDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="saveSteps">
              <div class="flex gap-2">
                <div class="space-y-1.5 flex-1">
                  <Label>{{ $t("exercise.stepsLabel") }}</Label>
                  <Input v-model="stepsForm.steps" type="number" min="0" max="200000" placeholder="pl. 8500" />
                </div>
                <div class="space-y-1.5 w-40">
                  <Label>{{ $t("exercise.date") }}</Label>
                  <Input v-model="stepsForm.date" type="date" :max="todayDate()" />
                </div>
              </div>
              <div class="space-y-1.5">
                <Label>{{ $t("exercise.mode") }}</Label>
                <div class="flex rounded-xl border overflow-hidden w-fit">
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-medium transition"
                    :class="stepsForm.mode === 'add' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                    @click="stepsForm.mode = 'add'"
                  >{{ $t("exercise.modeAdd") }}</button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-medium transition border-l"
                    :class="stepsForm.mode === 'overwrite' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                    @click="stepsForm.mode = 'overwrite'"
                  >{{ $t("exercise.modeOverwrite") }}</button>
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ stepsForm.mode === 'overwrite' ? $t('exercise.modeOverwriteDesc') : $t('exercise.modeAddDesc') }}
                </p>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="savingSteps">
                <Loader2 v-if="savingSteps" class="h-4 w-4 animate-spin" />
                <Footprints v-else class="h-4 w-4" />
                {{ $t("exercise.saveExercise") }}
              </Button>
            </form>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- ==================== HISTORY TAB ==================== -->
      <TabsContent value="history" class="mt-4 space-y-4">
        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 5" :key="i" class="h-16 rounded-xl" />
        </div>
        <div v-else-if="filteredExercises.length" class="space-y-2">
          <div
            v-for="ex in filteredExercises"
            :key="ex.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                <component :is="getCategoryIcon(ex.activity_id)" class="h-5 w-5 text-orange-600 dark:text-orange-400" />
              </div>
              <div>
                <div class="font-semibold text-sm">{{ getActivityName(ex.activity_id) }}</div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateTime(ex.begin) }} – {{ formatDateTime(ex.end) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div v-if="durationMinutes(ex.begin, ex.end)" class="text-xs font-medium px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                {{ durationMinutes(ex.begin, ex.end) }}
              </div>
              <button
                class="p-1.5 rounded-lg text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition"
                :disabled="deletingId === ex.id"
                @click="deleteExercise(ex.id)"
              >
                <Loader2 v-if="deletingId === ex.id" class="h-4 w-4 animate-spin" />
                <Trash2 v-else class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Dumbbell class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("exercise.noExercises") }}</p>
          <button class="text-xs text-primary hover:underline" @click="activeTab = 'new'">
            {{ $t("exercise.newEntry") }} →
          </button>
        </div>

        <!-- Steps history -->
        <Card v-if="stepsHistory.length" class="rounded-2xl">
          <CardHeader class="pb-2">
            <CardTitle class="text-base flex items-center gap-2">
              <Footprints class="h-4 w-4 text-green-500" />
              {{ $t("exercise.stepsHistory") }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-1.5">
            <div
              v-for="s in [...stepsHistory].reverse()"
              :key="s.id"
              class="flex items-center justify-between rounded-lg border px-3 py-2"
            >
              <div class="flex items-center gap-2">
                <Footprints class="h-4 w-4 text-green-500" />
                <span class="text-sm">{{ formatDate(s.recorded_at) }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-sm">{{ s.steps?.toLocaleString(dateLocale.value) }}</span>
                <span class="text-xs text-muted-foreground">{{ $t("exercise.steps") }}</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>

