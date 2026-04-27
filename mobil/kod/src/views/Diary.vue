<script setup>
import { ref, inject, computed, onMounted, watch } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { getLocalTimeZone, today } from "@internationalized/date"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { storageGet } from "@/lib/storage"
import { hapticMedium, hapticHeavy, hapticLight } from "@/lib/haptics"
import { notifyFirstEntry } from "@/composables/useLocalNotifications"
import { useAccess } from "@/composables/useAccess"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Skeleton } from "@/components/ui/skeleton"
import { Separator } from "@/components/ui/separator"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { Calendar } from "@/components/ui/calendar"
import { Heart, Activity, Weight, Plus, Loader2, Clock, Crown, Lock, Trash2, Flame, Droplet, Moon, Star, Pencil, X, CalendarIcon, ChevronLeft, ChevronRight } from "lucide-vue-next"

const { t, locale } = useI18n()
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")
const router = useRouter()
const authUser = ref(JSON.parse(storageGet("auth_user") || "null"))
const { isPro } = useAccess(authUser)

const selectedDate   = inject("selectedDate")
const globalQuery    = inject("globalQuery")
const wsEvent        = inject("wsEvent", ref(null))
const refreshTrigger = inject("refreshTrigger", ref(0))
const tz = getLocalTimeZone()

const activeTab = ref("heart-rate")
const loading = ref(false)
const saving = ref(false)
const error = ref("")
const successMsg = ref("")


const heartRates = ref([])
const bloodPressures = ref([])
const weights = ref([])
const calories = ref([])
const waters = ref([])
const sleeps = ref([])


function nowLocal() {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().slice(0, 16)
}

const hrForm = ref({ heart_rate: "", recorded_at: nowLocal(), context: "" })
const bpForm = ref({ systolic: "", diastolic: "", recorded_at: nowLocal(), period: "" })
const wForm = ref({ weight: "", recorded_at: nowLocal() })
const calForm = ref({ data: "", recorded_at: nowLocal() })
const waterForm = ref({ amount_ml: "", recorded_at: nowLocal() })
const sleepForm = ref({ hours: "", quality: "", recorded_at: nowLocal() })

function toLocalDateStr(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, "0")
  const d = String(date.getDate()).padStart(2, "0")
  return `${y}-${m}-${d}`
}

const todayStr = computed(() => {
  return toLocalDateStr(selectedDate.value.toDate(tz))
})

const tomorrowStr = computed(() => {
  const d = selectedDate.value.toDate(tz)
  d.setDate(d.getDate() + 1)
  return toLocalDateStr(d)
})

function formatTime(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleTimeString(dateLocale.value, { hour: "2-digit", minute: "2-digit" })
}

function formatDateFull(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleDateString(dateLocale.value, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" })
}

function filterByQuery(items, fields) {
  const q = (globalQuery?.value || "").toLowerCase().trim()
  if (!q) return items
  return items.filter(item =>
    fields.some(f => String(item[f] ?? "").toLowerCase().includes(q))
  )
}

const filteredHR = computed(() => filterByQuery(heartRates.value, ["heart_rate", "recorded_at"]))
const filteredBP = computed(() => filterByQuery(bloodPressures.value, ["systolic", "diastolic", "recorded_at"]))
const filteredW = computed(() => filterByQuery(weights.value, ["weight", "recorded_at"]))
const filteredCal = computed(() => filterByQuery(calories.value, ["data", "recorded_at"]))
const filteredWater = computed(() => filterByQuery(waters.value, ["amount_ml", "recorded_at"]))
const filteredSleep = computed(() => filterByQuery(sleeps.value, ["hours", "recorded_at"]))

async function loadAll() {
  loading.value = true
  try {
    const params = { start_date: todayStr.value, end_date: tomorrowStr.value }
    const [hrRes, bpRes, wRes, calRes, waterRes, sleepRes] = await Promise.allSettled([
      api.get("/heart-rates", { params }),
      api.get("/blood-pressures", { params }),
      api.get("/weights", { params }),
      api.get("/calories", { params }),
      api.get("/waters", { params }),
      api.get("/sleeps", { params }),
    ])
    if (hrRes.status === "fulfilled") heartRates.value = hrRes.value.data.slice().reverse()
    if (bpRes.status === "fulfilled") bloodPressures.value = bpRes.value.data.slice().reverse()
    if (wRes.status === "fulfilled") weights.value = wRes.value.data.slice().reverse()
    if (calRes.status === "fulfilled") calories.value = calRes.value.data.slice().reverse()
    if (waterRes.status === "fulfilled") waters.value = waterRes.value.data.slice().reverse()
    if (sleepRes.status === "fulfilled") sleeps.value = sleepRes.value.data.slice().reverse()
    const results = [hrRes, bpRes, wRes, calRes, waterRes, sleepRes]
    if (results.every(r => r.status === "rejected")) {
      error.value = getAxiosErrorMessage(results[0].reason)
    }
  } catch {}
  loading.value = false
}

onMounted(loadAll)
watch(selectedDate, loadAll)
watch(refreshTrigger, (v, old) => { if (v !== old) loadAll() })



watch(wsEvent, (e) => {
  if (!e) return
  const entry = e.data
  switch (e.type) {
    case "heart_rate":
      if (entry?.heart_rate) heartRates.value.unshift(entry)
      break
    case "blood_pressure":
      if (entry?.systolic) bloodPressures.value.unshift(entry)
      break
    case "weight":
      if (entry?.weight) weights.value.unshift(entry)
      break
    case "calorie":
      if (entry?.data) calories.value.unshift(entry)
      break
    case "water":
      if (entry?.amount_ml) waters.value.unshift(entry)
      break
    case "sleep":
      if (entry?.hours) sleeps.value.unshift(entry)
      break
    default:
      loadAll()
  }
})

function clearMessages() {
  error.value = ""
  successMsg.value = ""
}

async function saveHR() {
  clearMessages()
  if (!hrForm.value.heart_rate) { error.value = t("diary.validation.heartRate"); return }
  saving.value = true
  try {
    const payload = { heart_rate: Number(hrForm.value.heart_rate) }
    if (isPro.value && hrForm.value.recorded_at) payload.recorded_at = hrForm.value.recorded_at
    if (hrForm.value.context) payload.context = hrForm.value.context
    await api.post("/new-heart-rate", payload)
    hrForm.value = { heart_rate: "", recorded_at: nowLocal(), context: "" }
    successMsg.value = t("diary.saved.heartRate")
    hapticMedium()
    await loadAll()
    notifyFirstEntry(t("notifications.firstEntryTitle"), t("notifications.firstEntryBody")).catch(() => {})
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}

async function saveBP() {
  clearMessages()
  if (!bpForm.value.systolic || !bpForm.value.diastolic) { error.value = t("diary.validation.bloodPressure"); return }
  saving.value = true
  try {
    const payload = { systolic: Number(bpForm.value.systolic), diastolic: Number(bpForm.value.diastolic) }
    if (isPro.value && bpForm.value.recorded_at) payload.recorded_at = bpForm.value.recorded_at
    if (bpForm.value.period) payload.period = bpForm.value.period
    await api.post("/new-blood-pressure", payload)
    bpForm.value = { systolic: "", diastolic: "", recorded_at: nowLocal(), period: "" }
    successMsg.value = t("diary.saved.bloodPressure")
    hapticMedium()
    await loadAll()
    notifyFirstEntry(t("notifications.firstEntryTitle"), t("notifications.firstEntryBody")).catch(() => {})
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}

async function saveW() {
  clearMessages()
  if (!wForm.value.weight) { error.value = t("diary.validation.weight"); return }
  saving.value = true
  try {
    const payload = { weight: Number(wForm.value.weight) }
    if (isPro.value && wForm.value.recorded_at) payload.recorded_at = wForm.value.recorded_at
    await api.post("/new-weight", payload)
    wForm.value = { weight: "", recorded_at: nowLocal() }
    successMsg.value = t("diary.saved.weight")
    hapticMedium()
    await loadAll()
    notifyFirstEntry(t("notifications.firstEntryTitle"), t("notifications.firstEntryBody")).catch(() => {})
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}

async function saveCalorie() {
  clearMessages()
  if (!calForm.value.data) { error.value = t("diary.validation.calorie"); return }
  saving.value = true
  try {
    const payload = { data: Number(calForm.value.data) }
    if (isPro.value && calForm.value.recorded_at) payload.recorded_at = calForm.value.recorded_at
    await api.post("/new-calorie", payload)
    calForm.value = { data: "", recorded_at: nowLocal() }
    successMsg.value = t("diary.saved.calorie")
    hapticMedium()
    await loadAll()
    notifyFirstEntry(t("notifications.firstEntryTitle"), t("notifications.firstEntryBody")).catch(() => {})
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}

async function saveWater() {
  clearMessages()
  if (!waterForm.value.amount_ml) { error.value = t("diary.validation.water"); return }
  saving.value = true
  try {
    const payload = { amount_ml: Number(waterForm.value.amount_ml) }
    if (isPro.value && waterForm.value.recorded_at) payload.recorded_at = waterForm.value.recorded_at
    await api.post("/new-water", payload)
    waterForm.value = { amount_ml: "", recorded_at: nowLocal() }
    successMsg.value = t("diary.saved.water")
    hapticMedium()
    await loadAll()
    notifyFirstEntry(t("notifications.firstEntryTitle"), t("notifications.firstEntryBody")).catch(() => {})
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}

function addWaterQuick(ml) {
  waterForm.value.amount_ml = ml
  saveWater()
}

async function saveSleep() {
  clearMessages()
  if (!sleepForm.value.hours) { error.value = t("diary.validation.sleep"); return }
  saving.value = true
  try {
    const payload = { hours: Number(sleepForm.value.hours) }
    if (sleepForm.value.quality) payload.quality = Number(sleepForm.value.quality)
    if (isPro.value && sleepForm.value.recorded_at) payload.recorded_at = sleepForm.value.recorded_at
    await api.post("/new-sleep", payload)
    sleepForm.value = { hours: "", quality: "", recorded_at: nowLocal() }
    successMsg.value = t("diary.saved.sleep")
    hapticMedium()
    await loadAll()
    notifyFirstEntry(t("notifications.firstEntryTitle"), t("notifications.firstEntryBody")).catch(() => {})
  } catch (e) {
    hapticHeavy()
    error.value = getAxiosErrorMessage(e)
  } finally {
    saving.value = false
  }
}


const copyingYesterday = ref("")

async function copyYesterday(type) {
  copyingYesterday.value = type
  const d = selectedDate.value.toDate(tz)
  d.setDate(d.getDate() - 1)
  const yd = toLocalDateStr(d)
  const d2 = new Date(d)
  d2.setDate(d2.getDate() + 1)
  const yd2 = toLocalDateStr(d2)
  const params = { start_date: yd, end_date: yd2 }
  try {
    if (type === "heartRate") {
      const res = await api.get("/heart-rates", { params })
      const items = res.data
      if (!items.length) { error.value = t("diary.copyYesterdayNone"); return }
      hrForm.value.heart_rate = items[items.length - 1].heart_rate
    } else if (type === "bloodPressure") {
      const res = await api.get("/blood-pressures", { params })
      const items = res.data
      if (!items.length) { error.value = t("diary.copyYesterdayNone"); return }
      bpForm.value.systolic = items[items.length - 1].systolic
      bpForm.value.diastolic = items[items.length - 1].diastolic
    } else if (type === "weight") {
      const res = await api.get("/weights", { params })
      const items = res.data
      if (!items.length) { error.value = t("diary.copyYesterdayNone"); return }
      wForm.value.weight = items[items.length - 1].weight
    }
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    copyingYesterday.value = ""
  }
}


const editItem = ref(null)
const editType = ref("")
const editForm = ref({})
const editSaving = ref(false)

function openEdit(type, item) {
  editType.value = type
  editItem.value = item
  if (type === "heartRate") {
    editForm.value = { heart_rate: item.heart_rate, context: item.context || "", recorded_at: item.recorded_at?.slice(0, 16) || nowLocal() }
  } else if (type === "bloodPressure") {
    editForm.value = { systolic: item.systolic, diastolic: item.diastolic, period: item.period || "", recorded_at: item.recorded_at?.slice(0, 16) || nowLocal() }
  } else if (type === "weight") {
    editForm.value = { weight: item.weight, recorded_at: item.recorded_at?.slice(0, 16) || nowLocal() }
  } else if (type === "calorie") {
    editForm.value = { data: item.data, recorded_at: item.recorded_at?.slice(0, 16) || nowLocal() }
  } else if (type === "water") {
    editForm.value = { amount_ml: item.amount_ml, recorded_at: item.recorded_at?.slice(0, 16) || nowLocal() }
  } else if (type === "sleep") {
    editForm.value = { hours: item.hours, quality: item.quality || "", recorded_at: item.recorded_at?.slice(0, 16) || nowLocal() }
  }
}

async function saveEdit() {
  if (!editItem.value) return
  editSaving.value = true
  const endpoints = {
    heartRate:     `/heart-rates/${editItem.value.id}`,
    bloodPressure: `/blood-pressures/${editItem.value.id}`,
    weight:        `/weights/${editItem.value.id}`,
    calorie:       `/calories/${editItem.value.id}`,
    water:         `/waters/${editItem.value.id}`,
    sleep:         `/sleeps/${editItem.value.id}`,
  }
  try {
    const payload = { ...editForm.value }
    if (editType.value === "heartRate") {
      payload.heart_rate = Number(payload.heart_rate)
      if (payload.context === "") payload.context = null
    }
    if (editType.value === "bloodPressure") {
      payload.systolic = Number(payload.systolic)
      payload.diastolic = Number(payload.diastolic)
      if (payload.period === "") payload.period = null
    }
    if (editType.value === "weight") payload.weight = Number(payload.weight)
    if (editType.value === "calorie") payload.data = Number(payload.data)
    if (editType.value === "water") payload.amount_ml = Number(payload.amount_ml)
    if (editType.value === "sleep") {
      payload.hours = Number(payload.hours)
      if (payload.quality === "") payload.quality = null
      else payload.quality = Number(payload.quality)
    }
    await api.put(endpoints[editType.value], payload)
    editItem.value = null
    await loadAll()
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    editSaving.value = false
  }
}


const datePickerOpen = ref(false)
const todayDate = computed(() => today(tz))
const isToday = computed(() => selectedDate.value.compare(todayDate.value) === 0)

function setDate(v) {
  if (!v) return
  selectedDate.value = v
  datePickerOpen.value = false
  hapticLight()
}
function prevDay() {
  selectedDate.value = selectedDate.value.add({ days: -1 })
  hapticLight()
}
function nextDay() {
  if (isToday.value) return
  selectedDate.value = selectedDate.value.add({ days: 1 })
  hapticLight()
}
function goToday() {
  if (isToday.value) return
  selectedDate.value = todayDate.value
  hapticLight()
}

async function deleteEntry(type, id) {
  if (!window.confirm(t("diary.deleteConfirm"))) return
  const endpoints = {
    heartRate: `/heart-rates/${id}`,
    bloodPressure: `/blood-pressures/${id}`,
    weight: `/weights/${id}`,
    calorie: `/calories/${id}`,
    water: `/waters/${id}`,
    sleep: `/sleeps/${id}`,
  }
  try {
    hapticHeavy()
    await api.delete(endpoints[type])
    await loadAll()
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  }
}
</script>

<template>
  <div class="space-y-4">

    <div class="space-y-2">
      <h2 class="text-lg font-semibold">{{ $t("diary.title") }}</h2>
      <div class="flex items-center gap-1.5">
        <Button type="button" variant="outline" size="icon" class="h-9 w-9 shrink-0 rounded-xl" :aria-label="$t('diary.prevDay')" @click="prevDay">
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <Popover v-model:open="datePickerOpen">
          <PopoverTrigger as-child>
            <Button type="button" variant="outline" class="h-9 flex-1 justify-start gap-2 rounded-xl font-normal">
              <CalendarIcon class="h-4 w-4 text-muted-foreground" />
              <span class="text-sm">{{ selectedDate ? new Date(selectedDate.toDate(tz)).toLocaleDateString(dateLocale, { year: "numeric", month: "long", day: "numeric" }) : "" }}</span>
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-auto p-0" align="start">
            <Calendar :model-value="selectedDate" :max-value="todayDate" @update:model-value="setDate" />
          </PopoverContent>
        </Popover>
        <Button type="button" variant="outline" size="icon" class="h-9 w-9 shrink-0 rounded-xl" :aria-label="$t('diary.nextDay')" :disabled="isToday" @click="nextDay">
          <ChevronRight class="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" class="h-9 rounded-xl" :disabled="isToday" @click="goToday">
          {{ $t("diary.today") }}
        </Button>
      </div>
    </div>

    
    <Alert v-if="error" variant="destructive" class="rounded-xl">
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>
    <Alert v-if="successMsg" class="rounded-xl border-green-200 bg-green-50 text-green-800">
      <AlertDescription>{{ successMsg }}</AlertDescription>
    </Alert>

    <Tabs v-model="activeTab" class="w-full">
      <TabsList class="grid grid-cols-6 w-full rounded-xl">
        <TabsTrigger value="heart-rate" class="gap-1.5">
          <Heart class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">{{ $t("diary.heartRate") }}</span>
          <span class="sm:hidden">{{ $t("diary.heartRateShort") }}</span>
        </TabsTrigger>
        <TabsTrigger value="blood-pressure" class="gap-1.5">
          <Activity class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">{{ $t("diary.bloodPressure") }}</span>
          <span class="sm:hidden">{{ $t("diary.bloodPressureShort") }}</span>
        </TabsTrigger>
        <TabsTrigger value="weight" class="gap-1.5">
          <Weight class="h-3.5 w-3.5" />
          {{ $t("diary.weight") }}
        </TabsTrigger>
        <TabsTrigger value="calorie" class="gap-1.5">
          <Flame class="h-3.5 w-3.5" />
          {{ $t("diary.calorieShort") }}
        </TabsTrigger>
        <TabsTrigger value="water" class="gap-1.5">
          <Droplet class="h-3.5 w-3.5" />
          {{ $t("diary.waterShort") }}
        </TabsTrigger>
        <TabsTrigger value="sleep" class="gap-1.5">
          <Moon class="h-3.5 w-3.5" />
          {{ $t("diary.sleepShort") }}
        </TabsTrigger>
      </TabsList>

      
      <TabsContent value="heart-rate" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("diary.newHeartRate") }}</CardTitle>
            <CardDescription>{{ $t("diary.hrDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="saveHR">
              <div class="flex gap-3 flex-wrap">
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label class="flex items-center justify-between">
                    {{ $t("diary.hrLabel") }}
                    <button type="button" class="text-[11px] text-primary hover:underline disabled:opacity-50" :disabled="!!copyingYesterday" @click="copyYesterday('heartRate')">
                      {{ copyingYesterday === 'heartRate' ? '...' : $t("diary.copyYesterday") }}
                    </button>
                  </Label>
                  <Input v-model="hrForm.heart_rate" type="number" min="1" max="300" placeholder="72" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[140px]">
                  <Label>{{ $t("diary.hrContext") }}</Label>
                  <select v-model="hrForm.context" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">{{ $t("diary.hrContextOptions.none") }}</option>
                    <option value="resting">{{ $t("diary.hrContextOptions.resting") }}</option>
                    <option value="exercise">{{ $t("diary.hrContextOptions.exercise") }}</option>
                    <option value="waking">{{ $t("diary.hrContextOptions.waking") }}</option>
                    <option value="other">{{ $t("diary.hrContextOptions.other") }}</option>
                  </select>
                </div>
                <div class="space-y-1.5 flex-1 min-w-[180px]">
                  <Label class="flex items-center gap-1.5">
                    {{ $t("diary.dateTimeOptional") }}
                    <span v-if="!isPro" class="text-xs text-muted-foreground flex items-center gap-0.5">
                      <Lock class="h-3 w-3" /> Pro
                    </span>
                  </Label>
                  <div v-if="!isPro" class="relative">
                    <Input type="datetime-local" disabled class="opacity-50 cursor-not-allowed" />
                    <button type="button" class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground hover:text-foreground gap-1 rounded-md" @click="router.push('/app/upgrade')">
                      <Crown class="h-3.5 w-3.5 text-blue-500" />
                      {{ $t("diary.proFeature") }}
                    </button>
                  </div>
                  <Input v-else v-model="hrForm.recorded_at" type="datetime-local" :max="nowLocal()" />
                </div>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("common.save") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        
        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 3" :key="i" class="h-14 rounded-xl" />
        </div>
        <div v-else-if="filteredHR.length" class="space-y-2">
          <div
            v-for="item in filteredHR"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-red-100 flex items-center justify-center">
                <Heart class="h-4 w-4 text-red-500" />
              </div>
              <div>
                <div class="font-semibold flex items-center gap-1.5 flex-wrap">
                  <span>{{ item.heart_rate }} <span class="text-sm font-normal text-muted-foreground">BPM</span></span>
                  <span v-if="item.context" class="text-[10px] uppercase tracking-wide font-medium px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground">
                    {{ $t(`diary.hrContextOptions.${item.context}`) }}
                  </span>
                </div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateFull(item.recorded_at) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div
                class="text-xs font-medium px-2 py-0.5 rounded-full"
                :class="item.heart_rate < 60 ? 'bg-blue-100 text-blue-700' : item.heart_rate <= 100 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
              >
                {{ item.heart_rate < 60 ? $t('dashboard.low') : item.heart_rate <= 100 ? $t('dashboard.normal') : $t('dashboard.high') }}
              </div>
              <button type="button" class="text-muted-foreground hover:text-primary transition-colors p-1" @click="openEdit('heartRate', item)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button type="button" class="text-muted-foreground hover:text-red-500 transition-colors p-1" @click="deleteEntry('heartRate', item.id)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Heart class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("diary.noDataForDay.heartRate") }}</p>
        </div>
      </TabsContent>

      
      <TabsContent value="blood-pressure" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("diary.newBP") }}</CardTitle>
            <CardDescription>{{ $t("diary.bpDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="saveBP">
              <div class="flex gap-3 flex-wrap">
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label class="flex items-center justify-between">
                    {{ $t("diary.systolic") }}
                    <button type="button" class="text-[11px] text-primary hover:underline disabled:opacity-50" :disabled="!!copyingYesterday" @click="copyYesterday('bloodPressure')">
                      {{ copyingYesterday === 'bloodPressure' ? '...' : $t("diary.copyYesterday") }}
                    </button>
                  </Label>
                  <Input v-model="bpForm.systolic" type="number" min="1" max="300" placeholder="120" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label>{{ $t("diary.diastolic") }}</Label>
                  <Input v-model="bpForm.diastolic" type="number" min="1" max="200" placeholder="80" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[140px]">
                  <Label>{{ $t("diary.bpPeriod") }}</Label>
                  <select v-model="bpForm.period" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">{{ $t("diary.bpPeriodOptions.none") }}</option>
                    <option value="morning">{{ $t("diary.bpPeriodOptions.morning") }}</option>
                    <option value="evening">{{ $t("diary.bpPeriodOptions.evening") }}</option>
                    <option value="other">{{ $t("diary.bpPeriodOptions.other") }}</option>
                  </select>
                </div>
                <div class="space-y-1.5 flex-1 min-w-[180px]">
                  <Label class="flex items-center gap-1.5">
                    {{ $t("diary.dateTimeOptional") }}
                    <span v-if="!isPro" class="text-xs text-muted-foreground flex items-center gap-0.5">
                      <Lock class="h-3 w-3" /> Pro
                    </span>
                  </Label>
                  <div v-if="!isPro" class="relative">
                    <Input type="datetime-local" disabled class="opacity-50 cursor-not-allowed" />
                    <button type="button" class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground hover:text-foreground gap-1 rounded-md" @click="router.push('/app/upgrade')">
                      <Crown class="h-3.5 w-3.5 text-blue-500" />
                      {{ $t("diary.proFeature") }}
                    </button>
                  </div>
                  <Input v-else v-model="bpForm.recorded_at" type="datetime-local" :max="nowLocal()" />
                </div>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("common.save") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 3" :key="i" class="h-14 rounded-xl" />
        </div>
        <div v-else-if="filteredBP.length" class="space-y-2">
          <div
            v-for="item in filteredBP"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-blue-100 flex items-center justify-center">
                <Activity class="h-4 w-4 text-blue-500" />
              </div>
              <div>
                <div class="font-semibold flex items-center gap-1.5 flex-wrap">
                  <span>{{ item.systolic }}<span class="text-muted-foreground font-normal">/{{ item.diastolic }}</span>
                  <span class="text-sm font-normal text-muted-foreground ml-1">mmHg</span></span>
                  <span v-if="item.period" class="text-[10px] uppercase tracking-wide font-medium px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground">
                    {{ $t(`diary.bpPeriodOptions.${item.period}`) }}
                  </span>
                </div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateFull(item.recorded_at) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div
                class="text-xs font-medium px-2 py-0.5 rounded-full"
                :class="item.systolic < 120 && item.diastolic < 80 ? 'bg-green-100 text-green-700' : item.systolic < 130 ? 'bg-yellow-100 text-yellow-700' : item.systolic < 140 ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'"
              >
                {{ item.systolic < 120 && item.diastolic < 80 ? $t('dashboard.normal') : item.systolic < 130 ? $t('dashboard.elevated') : item.systolic < 140 ? $t('dashboard.high1') : $t('dashboard.high2') }}
              </div>
              <button type="button" class="text-muted-foreground hover:text-primary transition-colors p-1" @click="openEdit('bloodPressure', item)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button type="button" class="text-muted-foreground hover:text-red-500 transition-colors p-1" @click="deleteEntry('bloodPressure', item.id)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Activity class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("diary.noDataForDay.bloodPressure") }}</p>
        </div>
      </TabsContent>

      
      <TabsContent value="weight" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("diary.newWeight") }}</CardTitle>
            <CardDescription>{{ $t("diary.weightDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="saveW">
              <div class="flex gap-3 flex-wrap">
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label class="flex items-center justify-between">
                    {{ $t("diary.weightLabel") }}
                    <button type="button" class="text-[11px] text-primary hover:underline disabled:opacity-50" :disabled="!!copyingYesterday" @click="copyYesterday('weight')">
                      {{ copyingYesterday === 'weight' ? '...' : $t("diary.copyYesterday") }}
                    </button>
                  </Label>
                  <Input v-model="wForm.weight" type="number" min="1" max="500" step="0.1" placeholder="70.5" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[180px]">
                  <Label class="flex items-center gap-1.5">
                    {{ $t("diary.dateTimeOptional") }}
                    <span v-if="!isPro" class="text-xs text-muted-foreground flex items-center gap-0.5">
                      <Lock class="h-3 w-3" /> Pro
                    </span>
                  </Label>
                  <div v-if="!isPro" class="relative">
                    <Input type="datetime-local" disabled class="opacity-50 cursor-not-allowed" />
                    <button type="button" class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground hover:text-foreground gap-1 rounded-md" @click="router.push('/app/upgrade')">
                      <Crown class="h-3.5 w-3.5 text-blue-500" />
                      {{ $t("diary.proFeature") }}
                    </button>
                  </div>
                  <Input v-else v-model="wForm.recorded_at" type="datetime-local" :max="nowLocal()" />
                </div>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("common.save") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 3" :key="i" class="h-14 rounded-xl" />
        </div>
        <div v-else-if="filteredW.length" class="space-y-2">
          <div
            v-for="item in filteredW"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-purple-100 flex items-center justify-center">
                <Weight class="h-4 w-4 text-purple-500" />
              </div>
              <div>
                <div class="font-semibold">{{ item.weight }} <span class="text-sm font-normal text-muted-foreground">kg</span></div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateFull(item.recorded_at) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="text-muted-foreground hover:text-primary transition-colors p-1" @click="openEdit('weight', item)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button type="button" class="text-muted-foreground hover:text-red-500 transition-colors p-1" @click="deleteEntry('weight', item.id)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Weight class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("diary.noDataForDay.weight") }}</p>
        </div>
      </TabsContent>

      
      <TabsContent value="calorie" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("diary.newCalorie") }}</CardTitle>
            <CardDescription>{{ $t("diary.calorieDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="saveCalorie">
              <div class="flex gap-3 flex-wrap">
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label>{{ $t("diary.calorieLabel") }}</Label>
                  <Input v-model="calForm.data" type="number" min="1" max="10000" placeholder="500" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[180px]">
                  <Label class="flex items-center gap-1.5">
                    {{ $t("diary.dateTimeOptional") }}
                    <span v-if="!isPro" class="text-xs text-muted-foreground flex items-center gap-0.5">
                      <Lock class="h-3 w-3" /> Pro
                    </span>
                  </Label>
                  <div v-if="!isPro" class="relative">
                    <Input type="datetime-local" disabled class="opacity-50 cursor-not-allowed" />
                    <button type="button" class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground hover:text-foreground gap-1 rounded-md" @click="router.push('/app/upgrade')">
                      <Crown class="h-3.5 w-3.5 text-blue-500" />
                      {{ $t("diary.proFeature") }}
                    </button>
                  </div>
                  <Input v-else v-model="calForm.recorded_at" type="datetime-local" :max="nowLocal()" />
                </div>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("common.save") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 3" :key="i" class="h-14 rounded-xl" />
        </div>
        <div v-else-if="filteredCal.length" class="space-y-2">
          <div
            v-for="item in filteredCal"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-orange-100 flex items-center justify-center">
                <Flame class="h-4 w-4 text-orange-500" />
              </div>
              <div>
                <div class="font-semibold">{{ item.data }} <span class="text-sm font-normal text-muted-foreground">kcal</span></div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateFull(item.recorded_at) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="text-muted-foreground hover:text-primary transition-colors p-1" @click="openEdit('calorie', item)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button type="button" class="text-muted-foreground hover:text-red-500 transition-colors p-1" @click="deleteEntry('calorie', item.id)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Flame class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("diary.noDataForDay.calorie") }}</p>
        </div>
      </TabsContent>

      
      <TabsContent value="water" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("diary.newWater") }}</CardTitle>
            <CardDescription>{{ $t("diary.waterDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            
            <div class="flex flex-wrap gap-2 mb-3">
              <Button type="button" variant="outline" size="sm" class="rounded-xl gap-1.5" :disabled="saving" @click="addWaterQuick(250)">
                <Droplet class="h-3.5 w-3.5" /> 250 ml
              </Button>
              <Button type="button" variant="outline" size="sm" class="rounded-xl gap-1.5" :disabled="saving" @click="addWaterQuick(500)">
                <Droplet class="h-3.5 w-3.5" /> 500 ml
              </Button>
              <Button type="button" variant="outline" size="sm" class="rounded-xl gap-1.5" :disabled="saving" @click="addWaterQuick(750)">
                <Droplet class="h-3.5 w-3.5" /> 750 ml
              </Button>
            </div>
            <form class="space-y-3" @submit.prevent="saveWater">
              <div class="flex gap-3 flex-wrap">
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label>{{ $t("diary.waterLabel") }}</Label>
                  <Input v-model="waterForm.amount_ml" type="number" min="1" max="5000" placeholder="250" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[180px]">
                  <Label class="flex items-center gap-1.5">
                    {{ $t("diary.dateTimeOptional") }}
                    <span v-if="!isPro" class="text-xs text-muted-foreground flex items-center gap-0.5">
                      <Lock class="h-3 w-3" /> Pro
                    </span>
                  </Label>
                  <div v-if="!isPro" class="relative">
                    <Input type="datetime-local" disabled class="opacity-50 cursor-not-allowed" />
                    <button type="button" class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground hover:text-foreground gap-1 rounded-md" @click="router.push('/app/upgrade')">
                      <Crown class="h-3.5 w-3.5 text-blue-500" />
                      {{ $t("diary.proFeature") }}
                    </button>
                  </div>
                  <Input v-else v-model="waterForm.recorded_at" type="datetime-local" :max="nowLocal()" />
                </div>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("common.save") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 3" :key="i" class="h-14 rounded-xl" />
        </div>
        <div v-else-if="filteredWater.length" class="space-y-2">
          <div
            v-for="item in filteredWater"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-blue-100 flex items-center justify-center">
                <Droplet class="h-4 w-4 text-blue-500" />
              </div>
              <div>
                <div class="font-semibold">{{ item.amount_ml }} <span class="text-sm font-normal text-muted-foreground">ml</span></div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateFull(item.recorded_at) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="text-muted-foreground hover:text-primary transition-colors p-1" @click="openEdit('water', item)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button type="button" class="text-muted-foreground hover:text-red-500 transition-colors p-1" @click="deleteEntry('water', item.id)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Droplet class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("diary.noDataForDay.water") }}</p>
        </div>
      </TabsContent>

      
      <TabsContent value="sleep" class="mt-4 space-y-4">
        <Card class="rounded-2xl">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ $t("diary.newSleep") }}</CardTitle>
            <CardDescription>{{ $t("diary.sleepDesc") }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-3" @submit.prevent="saveSleep">
              <div class="flex gap-3 flex-wrap">
                <div class="space-y-1.5 flex-1 min-w-[120px]">
                  <Label>{{ $t("diary.sleepLabel") }}</Label>
                  <Input v-model="sleepForm.hours" type="number" min="0.25" max="24" step="0.25" placeholder="7.5" />
                </div>
                <div class="space-y-1.5 flex-1 min-w-[140px]">
                  <Label>{{ $t("diary.qualityLabel") }}</Label>
                  <select v-model="sleepForm.quality" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">{{ $t("diary.qualityOptions.none") }}</option>
                    <option value="1">{{ $t("diary.qualityOptions.1") }}</option>
                    <option value="2">{{ $t("diary.qualityOptions.2") }}</option>
                    <option value="3">{{ $t("diary.qualityOptions.3") }}</option>
                    <option value="4">{{ $t("diary.qualityOptions.4") }}</option>
                    <option value="5">{{ $t("diary.qualityOptions.5") }}</option>
                  </select>
                </div>
                <div class="space-y-1.5 flex-1 min-w-[180px]">
                  <Label class="flex items-center gap-1.5">
                    {{ $t("diary.dateTimeOptional") }}
                    <span v-if="!isPro" class="text-xs text-muted-foreground flex items-center gap-0.5">
                      <Lock class="h-3 w-3" /> Pro
                    </span>
                  </Label>
                  <div v-if="!isPro" class="relative">
                    <Input type="datetime-local" disabled class="opacity-50 cursor-not-allowed" />
                    <button type="button" class="absolute inset-0 flex items-center justify-center text-xs text-muted-foreground hover:text-foreground gap-1 rounded-md" @click="router.push('/app/upgrade')">
                      <Crown class="h-3.5 w-3.5 text-blue-500" />
                      {{ $t("diary.proFeature") }}
                    </button>
                  </div>
                  <Input v-else v-model="sleepForm.recorded_at" type="datetime-local" :max="nowLocal()" />
                </div>
              </div>
              <Button type="submit" class="gap-2 rounded-xl" :disabled="saving">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
                {{ $t("common.save") }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <div v-if="loading" class="space-y-2">
          <Skeleton v-for="i in 3" :key="i" class="h-14 rounded-xl" />
        </div>
        <div v-else-if="filteredSleep.length" class="space-y-2">
          <div
            v-for="item in filteredSleep"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center">
                <Moon class="h-4 w-4 text-indigo-500" />
              </div>
              <div>
                <div class="font-semibold flex items-center gap-1.5 flex-wrap">
                  <span>{{ item.hours }} <span class="text-sm font-normal text-muted-foreground">h</span></span>
                  <span v-if="item.quality" class="text-[10px] uppercase tracking-wide font-medium px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground flex items-center gap-0.5">
                    <Star class="h-2.5 w-2.5" /> {{ item.quality }}/5
                  </span>
                </div>
                <div class="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDateFull(item.recorded_at) }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="text-muted-foreground hover:text-primary transition-colors p-1" @click="openEdit('sleep', item)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button type="button" class="text-muted-foreground hover:text-red-500 transition-colors p-1" @click="deleteEntry('sleep', item.id)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-10 border rounded-xl border-dashed text-muted-foreground gap-2">
          <Moon class="h-8 w-8 opacity-30" />
          <p class="text-sm">{{ $t("diary.noDataForDay.sleep") }}</p>
        </div>
      </TabsContent>
    </Tabs>

    
    <Teleport to="body">
      <div v-if="editItem" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="editItem = null">
        <div class="w-full max-w-sm bg-card rounded-2xl shadow-2xl border p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">{{ $t("diary.editTitle") }}</h3>
            <button type="button" class="text-muted-foreground hover:text-foreground" @click="editItem = null">
              <X class="h-5 w-5" />
            </button>
          </div>

          
          <template v-if="editType === 'heartRate'">
            <div class="space-y-1.5">
              <Label>{{ $t("diary.heartRate") }}</Label>
              <Input v-model="editForm.heart_rate" type="number" min="1" max="300" class="rounded-xl" />
            </div>
            <div class="space-y-1.5">
              <Label>{{ $t("diary.hrContext") }}</Label>
              <select v-model="editForm.context" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm">
                <option value="">{{ $t("diary.hrContextOptions.none") }}</option>
                <option value="resting">{{ $t("diary.hrContextOptions.resting") }}</option>
                <option value="exercise">{{ $t("diary.hrContextOptions.exercise") }}</option>
                <option value="waking">{{ $t("diary.hrContextOptions.waking") }}</option>
                <option value="other">{{ $t("diary.hrContextOptions.other") }}</option>
              </select>
            </div>
          </template>

          
          <template v-else-if="editType === 'bloodPressure'">
            <div class="flex gap-3">
              <div class="space-y-1.5 flex-1">
                <Label>{{ $t("diary.systolic") }}</Label>
                <Input v-model="editForm.systolic" type="number" min="1" max="300" class="rounded-xl" />
              </div>
              <div class="space-y-1.5 flex-1">
                <Label>{{ $t("diary.diastolic") }}</Label>
                <Input v-model="editForm.diastolic" type="number" min="1" max="200" class="rounded-xl" />
              </div>
            </div>
            <div class="space-y-1.5">
              <Label>{{ $t("diary.bpPeriod") }}</Label>
              <select v-model="editForm.period" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm">
                <option value="">{{ $t("diary.bpPeriodOptions.none") }}</option>
                <option value="morning">{{ $t("diary.bpPeriodOptions.morning") }}</option>
                <option value="evening">{{ $t("diary.bpPeriodOptions.evening") }}</option>
                <option value="other">{{ $t("diary.bpPeriodOptions.other") }}</option>
              </select>
            </div>
          </template>

          
          <template v-else-if="editType === 'weight'">
            <div class="space-y-1.5">
              <Label>{{ $t("diary.weight") }}</Label>
              <Input v-model="editForm.weight" type="number" step="0.1" min="1" max="600" class="rounded-xl" />
            </div>
          </template>

          
          <template v-else-if="editType === 'calorie'">
            <div class="space-y-1.5">
              <Label>{{ $t("diary.calorieLabel") }}</Label>
              <Input v-model="editForm.data" type="number" min="1" max="10000" class="rounded-xl" />
            </div>
          </template>

          
          <template v-else-if="editType === 'water'">
            <div class="space-y-1.5">
              <Label>{{ $t("diary.waterLabel") }}</Label>
              <Input v-model="editForm.amount_ml" type="number" min="1" max="5000" class="rounded-xl" />
            </div>
          </template>

          
          <template v-else-if="editType === 'sleep'">
            <div class="space-y-1.5">
              <Label>{{ $t("diary.sleepLabel") }}</Label>
              <Input v-model="editForm.hours" type="number" min="0.25" max="24" step="0.25" class="rounded-xl" />
            </div>
            <div class="space-y-1.5">
              <Label>{{ $t("diary.qualityLabel") }}</Label>
              <select v-model="editForm.quality" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm">
                <option value="">{{ $t("diary.qualityOptions.none") }}</option>
                <option value="1">{{ $t("diary.qualityOptions.1") }}</option>
                <option value="2">{{ $t("diary.qualityOptions.2") }}</option>
                <option value="3">{{ $t("diary.qualityOptions.3") }}</option>
                <option value="4">{{ $t("diary.qualityOptions.4") }}</option>
                <option value="5">{{ $t("diary.qualityOptions.5") }}</option>
              </select>
            </div>
          </template>

          
          <div v-if="isPro" class="space-y-1.5">
            <Label>{{ $t("diary.recordedAt") }}</Label>
            <Input v-model="editForm.recorded_at" type="datetime-local" class="rounded-xl" :max="nowLocal()" />
          </div>

          <div class="flex gap-2">
            <Button class="flex-1 rounded-xl gap-2" :disabled="editSaving" @click="saveEdit">
              <Loader2 v-if="editSaving" class="h-4 w-4 animate-spin" />
              {{ $t("common.save") }}
            </Button>
            <Button variant="outline" class="rounded-xl" @click="editItem = null">
              {{ $t("common.cancel") }}
            </Button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
