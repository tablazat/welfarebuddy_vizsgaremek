<script setup>
import { computed, ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import {
  HeartPulse, RefreshCw, Clock, CheckCircle, AlertCircle,
  ShieldCheck, Loader2, XCircle, Heart, Activity, Weight, Dumbbell, ArrowLeft,
} from "lucide-vue-next"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useHealthSync } from "@/composables/useHealthSync"
import { useOfflineSync } from "@/composables/useOfflineSync"
import { hapticSuccess, hapticHeavy, hapticLight } from "@/lib/haptics"

const { t, locale } = useI18n()
const router = useRouter()
const { isOnline } = useOfflineSync()

const {
  init, syncAll, requestPermissions,
  pluginState, isSyncing, syncError, lastSyncTime, syncedCounts, currentStep,
  debugLog,
} = useHealthSync()

const stepLabels = {
  heart_rate: "healthSync.heartRate",
  blood_pressure: "healthSync.bloodPressure",
  weight: "healthSync.weight",
  steps: "healthSync.steps",
  activity: "healthSync.activities",
}

const dateLocale = computed(() =>
  ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US"
)

function formatDateTime(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleString(dateLocale.value, {
    year: "numeric", month: "short", day: "numeric",
    hour: "2-digit", minute: "2-digit",
  })
}

const totalSynced = computed(() => {
  if (!syncedCounts.value) return 0
  const c = syncedCounts.value
  return c.heartRate + c.bloodPressure + c.weight + c.steps + c.activities
})

const uploadedIdsCount = ref(0)

function refreshIdsCount() {
  try {
    const ids = JSON.parse(localStorage.getItem("health_uploaded_ids") || "[]")
    uploadedIdsCount.value = ids.length
  } catch { uploadedIdsCount.value = 0 }
}

function clearUploaded() {
  localStorage.removeItem("health_uploaded_ids")
  refreshIdsCount()
}

function clearLastSync() {
  localStorage.removeItem("health_last_sync")
  lastSyncTime.value = null
}

async function handleSyncAll() {
  hapticLight()
  try {
    await syncAll()
    if (!syncError.value) hapticSuccess()
    else hapticHeavy()
  } catch {
    hapticHeavy()
  }
}

async function handleRequestPermissions() {
  hapticLight()
  try {
    await requestPermissions()
  } catch {
    hapticHeavy()
  }
}

onMounted(() => {
  refreshIdsCount()
  init()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div>
      <Button variant="ghost" size="sm" class="rounded-xl gap-1.5 text-muted-foreground mb-2" @click="router.push('/app/settings')">
        <ArrowLeft class="h-3.5 w-3.5" />
        {{ $t("upgrade.backToSettings") }}
      </Button>
      <h2 class="text-lg font-semibold flex items-center gap-2">
        <HeartPulse class="w-5 h-5 text-primary" />
        {{ t("healthSync.title") }}
      </h2>
      <p class="text-sm text-muted-foreground">{{ t("healthSync.statusDesc") }}</p>
    </div>

    <!-- 1. Ellenőrzés állapot -->
    <Card v-if="pluginState === 'checking'">
      <CardContent class="flex items-center gap-3 py-6">
        <Loader2 class="w-5 h-5 animate-spin text-muted-foreground" />
        <span class="text-sm text-muted-foreground">{{ t("healthSync.checking") }}</span>
      </CardContent>
    </Card>

    <!-- 2. Nem elérhető -->
    <Card v-else-if="pluginState === 'unavailable'">
      <CardContent class="flex flex-col items-center gap-3 py-8 text-center">
        <XCircle class="w-10 h-10 text-muted-foreground" />
        <p class="text-muted-foreground text-sm">{{ t("healthSync.unavailable") }}</p>
        <Button variant="outline" size="sm" @click="init">
          {{ t("healthSync.retry") }}
        </Button>
      </CardContent>
    </Card>

    <!-- 3. Engedély szükséges -->
    <template v-else-if="pluginState === 'needsPermission'">
      <Card>
        <CardHeader>
          <CardTitle class="text-base">{{ t("healthSync.permTitle") }}</CardTitle>
          <CardDescription>{{ t("healthSync.permDesc") }}</CardDescription>
        </CardHeader>
        <CardContent>
          <ul class="space-y-2 text-sm text-muted-foreground mb-4">
            <li class="flex items-center gap-2"><Heart class="w-4 h-4 text-red-500 shrink-0" /> {{ t("healthSync.heartRate") }}</li>
            <li class="flex items-center gap-2"><Activity class="w-4 h-4 text-blue-500 shrink-0" /> {{ t("healthSync.bloodPressure") }}</li>
            <li class="flex items-center gap-2"><Weight class="w-4 h-4 text-green-500 shrink-0" /> {{ t("healthSync.weight") }}</li>
            <li class="flex items-center gap-2"><Dumbbell class="w-4 h-4 text-orange-500 shrink-0" /> {{ t("healthSync.activities") }}</li>
          </ul>

          <div v-if="syncError" class="flex items-start gap-2 text-sm text-destructive mb-3">
            <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>{{ syncError }}</span>
          </div>

          <Button class="w-full" @click="handleRequestPermissions">
            <ShieldCheck class="w-4 h-4 mr-2" />
            {{ t("healthSync.grantPermission") }}
          </Button>
        </CardContent>
      </Card>
    </template>

    <!-- 4. Kész, szinkronizálás elérhető -->
    <template v-else>
      <!-- Státusz kártya -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base">{{ t("healthSync.statusTitle") }}</CardTitle>
          <CardDescription>{{ t("healthSync.statusDesc") }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <!-- Utolsó szinkronizálás -->
          <div class="flex items-center gap-2 text-sm">
            <Clock class="w-4 h-4 text-muted-foreground shrink-0" />
            <span class="text-muted-foreground">{{ t("healthSync.lastSync") }}:</span>
            <span class="font-medium">{{ formatDateTime(lastSyncTime) }}</span>
          </div>

          <!-- Szinkronizált elemek száma -->
          <div v-if="syncedCounts && !isSyncing" class="space-y-1 text-sm">
            <div class="flex items-center gap-2 text-green-600">
              <CheckCircle class="w-4 h-4 shrink-0" />
              <span>{{ t("healthSync.syncDone", { count: totalSynced }) }}</span>
            </div>
            <div v-if="totalSynced > 0" class="ml-6 text-xs text-muted-foreground space-y-0.5">
              <div v-if="syncedCounts.heartRate">{{ t("healthSync.heartRate") }}: +{{ syncedCounts.heartRate }}</div>
              <div v-if="syncedCounts.bloodPressure">{{ t("healthSync.bloodPressure") }}: +{{ syncedCounts.bloodPressure }}</div>
              <div v-if="syncedCounts.weight">{{ t("healthSync.weight") }}: +{{ syncedCounts.weight }}</div>
              <div v-if="syncedCounts.steps">{{ t("healthSync.steps") }}: +{{ syncedCounts.steps }}</div>
              <div v-if="syncedCounts.activities">{{ t("healthSync.activities") }}: +{{ syncedCounts.activities }}</div>
            </div>
          </div>

          <!-- Hiba -->
          <div v-if="syncError" class="flex items-start gap-2 text-sm text-destructive">
            <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>{{ syncError }}</span>
          </div>
        </CardContent>
      </Card>

      <!-- Offline figyelmezetés -->
      <div v-if="!isOnline" class="flex items-center gap-2 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-700 dark:text-yellow-400">
        <AlertCircle class="w-4 h-4 shrink-0" />
        <span>{{ $t("offline.banner") }}</span>
      </div>

      <!-- Sync gomb + lépés indikátor -->
      <Button class="w-full" :disabled="isSyncing || !isOnline" @click="handleSyncAll">
        <RefreshCw class="w-4 h-4 mr-2" :class="{ 'animate-spin': isSyncing }" />
        {{ isSyncing ? t("healthSync.syncing") : t("healthSync.syncNow") }}
      </Button>
      <div v-if="isSyncing && currentStep" class="flex items-center gap-2 text-xs text-muted-foreground justify-center">
        <Loader2 class="w-3.5 h-3.5 animate-spin" />
        <span>{{ t(stepLabels[currentStep] || currentStep) }}...</span>
      </div>

      <!-- Info kártya -->
      <Card>
        <CardContent class="py-4">
          <p class="text-xs text-muted-foreground">{{ t("healthSync.infoText") }}</p>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
