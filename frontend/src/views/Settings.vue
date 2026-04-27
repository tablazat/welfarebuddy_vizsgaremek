<script setup>
import { ref, computed, onMounted } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { invalidateCacheKeys } from "@/lib/offlineCache"
import { storageGet, storageSet, storageRemove } from "@/lib/storage"
import { useTheme } from "@/composables/useTheme"
import { useAccess } from "@/composables/useAccess"
import { supportedLocales, setLocale } from "@/lib/i18n"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Separator } from "@/components/ui/separator"
import { LogOut, AlertTriangle, Info, Sun, Moon, Monitor, Crown, Check, ArrowRight, Lock, Loader2, Globe, Heart, RefreshCw, Download, Trash2, Snowflake, Droplet, FileText, ChevronRight } from "lucide-vue-next"
import { useHealthSync } from "@/composables/useHealthSync"

const router = useRouter()
const { t, tm, locale } = useI18n()
const loading = ref(false)
const profileLoading = ref(true)
const profileError = ref(false)

const { init: healthInit, syncAll, requestPermissions, forceSync, pluginState, isSyncing, syncError: healthSyncError, lastSyncTime, syncedCounts } = useHealthSync()
const nukeLoading = ref(false)
const deleteAccountLoading = ref(false)
const showDeleteAccountConfirm = ref(false)
const exportLoading = ref("")
const error = ref("")
const success = ref("")
const showNukeConfirm = ref(false)

const { theme } = useTheme()

const authUser = ref(JSON.parse(storageGet("auth_user") || "null"))
const { isFree, isPro, isAdmin, level } = useAccess(authUser)

async function loadProfile() {
  profileLoading.value = true
  profileError.value = false
  try {
    const { data } = await api.get("/token-check")
    authUser.value = data.user
    storageSet("auth_user", JSON.stringify(data.user))
    if (data.user?.step_goal_daily) {
      stepGoalInput.value = data.user.step_goal_daily
      storageSet("step_goal", String(data.user.step_goal_daily))
    }
    if (data.user?.water_goal_ml) {
      waterGoalInput.value = data.user.water_goal_ml
      storageSet("water_goal_ml", String(data.user.water_goal_ml))
    }
  } catch {
    profileError.value = true
  } finally {
    profileLoading.value = false
  }
}
onMounted(async () => {
  await loadProfile()
  healthInit()
})

const tierLabel = computed(() => {
  const map = { free: t("upgrade.free"), pro: "Pro", admin: "Admin" }
  return map[level.value] || t("upgrade.free")
})

const tierColor = computed(() => {
  const map = { free: "bg-muted text-muted-foreground", pro: "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300", admin: "bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300" }
  return map[level.value] || "bg-muted text-muted-foreground"
})

const themeOptions = computed(() => [
  { value: "light", label: t("settings.themeLight"), icon: Sun },
  { value: "dark", label: t("settings.themeDark"), icon: Moon },
  { value: "system", label: t("settings.themeSystem"), icon: Monitor },
])

function switchLocale(code) {
  setLocale(code)
}

// Password change
const pwForm = ref({ current_password: "", password: "", password_confirmation: "" })
const pwSaving = ref(false)

function clearMsg() { error.value = ""; success.value = "" }

async function changePassword() {
  clearMsg()
  if (!pwForm.value.current_password || !pwForm.value.password) {
    error.value = t("settings.fillAllFields")
    return
  }
  if (pwForm.value.password !== pwForm.value.password_confirmation) {
    error.value = t("auth.passwordMismatch")
    return
  }
  pwSaving.value = true
  try {
    await api.post("/password-change", {
      current_password: pwForm.value.current_password,
      password: pwForm.value.password,
    })
    // Sikeres jelszóváltoztatás → kijelentkeztetés
    storageRemove("auth_token")
    storageRemove("auth_user")
    router.replace({ name: "auth", query: { tab: "login", pw_changed: "1" } })
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
    pwSaving.value = false
  }
}

async function logout() {
  clearMsg()
  loading.value = true
  try {
    await api.post("/logout")
  } catch {}
  storageRemove("auth_token")
  storageRemove("auth_user")
  router.replace("/")
}

async function nuke() {
  clearMsg()
  nukeLoading.value = true
  try {
    await api.post("/nuke")
    storageRemove("auth_token")
    storageRemove("auth_user")
    router.replace("/")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
    nukeLoading.value = false
  }
}

async function exportData(format) {
  clearMsg()
  exportLoading.value = format
  try {
    const response = await api.get(`/export/${format}`, { responseType: "blob" })
    const mime = format === "csv" ? "application/zip" : "application/json"
    const ext  = format === "csv" ? "zip" : "json"
    const blob = new Blob([response.data], { type: mime })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement("a")
    a.href     = url
    a.download = `welfarebuddy-export-${new Date().toISOString().slice(0, 10)}.${ext}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 5000)
    success.value = t("settings.exportSuccess")
  } catch (e) {
    if (e?.response?.data instanceof Blob) {
      try {
        const txt = await e.response.data.text()
        const parsed = JSON.parse(txt)
        error.value = parsed?.message || t("settings.exportError")
      } catch {
        error.value = t("settings.exportError")
      }
    } else {
      error.value = getAxiosErrorMessage(e)
    }
  } finally {
    exportLoading.value = ""
  }
}

// Display name (becenév)
const initialUser = (() => { try { return JSON.parse(storageGet("auth_user") || "null") } catch { return null } })()
const displayNameInput = ref(initialUser?.display_name || "")
const displayNameSaving = ref(false)

async function saveDisplayName() {
  error.value = ""
  success.value = ""
  displayNameSaving.value = true
  try {
    const name = displayNameInput.value.trim()
    if (name.length < 1 || name.length > 64) {
      error.value = t("settings.displayNameError")
      return
    }
    const { data } = await api.put("/me/profile", { display_name: name })
    if (data?.user) storageSet("auth_user", JSON.stringify(data.user))
    invalidateCacheKeys("/token-check")
    success.value = t("settings.displayNameSaved")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    displayNameSaving.value = false
  }
}

// Daily step goal (localStorage-backed)
const stepGoalInput = ref(parseInt(storageGet("step_goal") || "10000"))
const stepGoalSaving = ref(false)

async function saveStepGoal() {
  error.value = ""
  success.value = ""
  stepGoalSaving.value = true
  try {
    const raw = parseInt(stepGoalInput.value) || 10000
    const clamped = Math.max(1000, Math.min(100000, raw))
    const { data } = await api.put("/me/goals", { step_goal_daily: clamped })
    if (data?.user) storageSet("auth_user", JSON.stringify(data.user))
    invalidateCacheKeys("/token-check")
    storageSet("step_goal", String(clamped))
    stepGoalInput.value = clamped
    success.value = t("settings.stepGoalSaved")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    stepGoalSaving.value = false
  }
}

// Daily water goal (localStorage-backed)
const waterGoalInput = ref(parseInt(storageGet("water_goal_ml") || "2500"))
const waterGoalSaving = ref(false)

async function saveWaterGoal() {
  error.value = ""
  success.value = ""
  waterGoalSaving.value = true
  try {
    const raw = parseInt(waterGoalInput.value) || 2500
    const clamped = Math.max(500, Math.min(10000, raw))
    const { data } = await api.put("/me/goals", { water_goal_ml: clamped })
    if (data?.user) storageSet("auth_user", JSON.stringify(data.user))
    invalidateCacheKeys("/token-check")
    storageSet("water_goal_ml", String(clamped))
    waterGoalInput.value = clamped
    success.value = t("settings.waterGoalSaved")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    waterGoalSaving.value = false
  }
}

// Streak freeze (Pro)
const freezeStatus = ref(null)
const freezeLoading = ref(false)
const freezeSaving = ref(false)

async function loadFreezeStatus() {
  freezeLoading.value = true
  try {
    const { data } = await api.get("/streak/status")
    freezeStatus.value = data
  } catch {
    freezeStatus.value = null
  } finally {
    freezeLoading.value = false
  }
}

async function useFreeze() {
  error.value = ""
  success.value = ""
  freezeSaving.value = true
  try {
    await api.post("/streak/freeze")
    success.value = t("settings.streakFreezeUsed")
    await loadFreezeStatus()
  } catch (e) {
    const code = e?.response?.data?.code
    const map = {
      not_pro:    t("settings.streakFreezeNotPro"),
      no_streak:  t("settings.streakFreezeNoStreak"),
      not_needed: t("settings.streakFreezeNotNeeded"),
      too_late:   t("settings.streakFreezeTooLate"),
      cooldown:   t("settings.streakFreezeCooldown"),
    }
    error.value = map[code] || getAxiosErrorMessage(e)
  } finally {
    freezeSaving.value = false
  }
}

onMounted(loadFreezeStatus)

async function deleteAccount() {
  clearMsg()
  deleteAccountLoading.value = true
  try {
    await api.delete("/account")
    storageRemove("auth_token")
    storageRemove("auth_user")
    router.replace("/")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
    deleteAccountLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold">{{ $t("settings.title") }}</h2>
      <p class="text-sm text-muted-foreground">{{ $t("settings.subtitle") }}</p>
    </div>

    <Alert v-if="error" variant="destructive" class="rounded-xl">
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>
    <Alert v-if="success" class="rounded-xl border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
      <AlertDescription>{{ success }}</AlertDescription>
    </Alert>

    <div
      v-if="!profileLoading && profileError"
      class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30 px-4 py-3"
    >
      <AlertTriangle class="h-5 w-5 text-rose-600 dark:text-rose-400 shrink-0" />
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-rose-800 dark:text-rose-300">{{ $t("settings.profileLoadFailed") }}</div>
        <div class="text-xs text-rose-600 dark:text-rose-400">{{ $t("settings.profileLoadFailedDesc") }}</div>
      </div>
      <Button size="sm" variant="outline" class="rounded-xl shrink-0" @click="loadProfile">
        <RefreshCw class="h-3.5 w-3.5" />
        {{ $t("common.retry") }}
      </Button>
    </div>

    <!-- Theme -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Sun class="h-4 w-4" />
          {{ $t("settings.appearance") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.appearanceDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="flex gap-2">
          <button
            v-for="opt in themeOptions"
            :key="opt.value"
            type="button"
            class="flex-1 flex flex-col items-center gap-2 rounded-xl border p-3 transition hover:bg-accent"
            :class="theme === opt.value ? 'border-primary bg-accent ring-2 ring-primary/20' : 'border-border'"
            @click="theme = opt.value"
          >
            <component :is="opt.icon" class="h-5 w-5" />
            <span class="text-sm font-medium">{{ opt.label }}</span>
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- Language -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Globe class="h-4 w-4" />
          {{ $t("settings.language") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.languageDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="flex gap-2">
          <button
            v-for="loc in supportedLocales"
            :key="loc.code"
            type="button"
            class="flex-1 flex flex-col items-center gap-2 rounded-xl border p-3 transition hover:bg-accent"
            :class="locale === loc.code ? 'border-primary bg-accent ring-2 ring-primary/20' : 'border-border'"
            @click="switchLocale(loc.code)"
          >
            <span class="text-xl">{{ loc.flag }}</span>
            <span class="text-sm font-medium">{{ loc.label }}</span>
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- Password change -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Lock class="h-4 w-4" />
          {{ $t("settings.passwordChange") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.passwordChangeDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-3" @submit.prevent="changePassword">
          <div class="space-y-1.5">
            <Label>{{ $t("auth.currentPassword") }}</Label>
            <Input v-model="pwForm.current_password" type="password" placeholder="••••••••" />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label>{{ $t("auth.newPassword") }}</Label>
              <Input v-model="pwForm.password" type="password" placeholder="••••••••" />
            </div>
            <div class="space-y-1.5">
              <Label>{{ $t("auth.confirmPassword") }}</Label>
              <Input v-model="pwForm.password_confirmation" type="password" placeholder="••••••••" />
            </div>
          </div>
          <Button type="submit" class="gap-2 rounded-xl" :disabled="pwSaving">
            <Loader2 v-if="pwSaving" class="h-4 w-4 animate-spin" />
            <Lock v-else class="h-4 w-4" />
            {{ $t("settings.passwordChangeBtn") }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <!-- Tier / Subscription -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Crown class="h-4 w-4" />
          {{ $t("settings.subscription") }}
        </CardTitle>
        <CardDescription>
          {{ $t("settings.currentPlan") }}
          <span class="font-medium px-1.5 py-0.5 rounded-full text-xs ml-1" :class="tierColor">{{ tierLabel }}</span>
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Free tier -->
          <div class="rounded-xl border p-4 space-y-3" :class="isFree ? 'border-primary ring-2 ring-primary/20' : ''">
            <div class="flex items-center justify-between">
              <span class="font-semibold">{{ $t("upgrade.free") }}</span>
              <span v-if="isFree" class="text-xs bg-primary text-primary-foreground px-2 py-0.5 rounded-full">{{ $t("settings.active") }}</span>
            </div>
            <ul class="space-y-1.5">
              <li v-for="(f, i) in $tm('settings.freeFeatures')" :key="i" class="text-sm flex items-start gap-2" :class="f.included === false ? 'text-muted-foreground/50' : 'text-muted-foreground'">
                <Check class="h-4 w-4 shrink-0 mt-0.5" :class="f.included === false ? 'text-muted-foreground/30' : 'text-green-500'" />
                {{ f.text }}
              </li>
            </ul>
          </div>
          <!-- Pro tier -->
          <div class="rounded-xl border p-4 space-y-3" :class="isAdmin ? 'border-purple-300 ring-2 ring-purple-300/20 dark:border-purple-700' : isPro ? 'border-primary ring-2 ring-primary/20' : 'border-blue-200 dark:border-blue-800'">
            <div class="flex items-center justify-between">
              <span class="font-semibold flex items-center gap-1.5">
                <Crown class="h-4 w-4" :class="isAdmin ? 'text-purple-500' : 'text-blue-500'" />
                {{ isAdmin ? 'Admin' : 'Pro' }}
              </span>
              <span v-if="isAdmin" class="text-xs bg-purple-500 text-white px-2 py-0.5 rounded-full">{{ $t("settings.active") }}</span>
              <span v-else-if="isPro" class="text-xs bg-blue-500 text-white px-2 py-0.5 rounded-full">{{ $t("settings.active") }}</span>
            </div>
            <ul class="space-y-1.5">
              <li v-for="(f, i) in $tm('settings.freeFeatures')" :key="'free-'+i" class="text-sm text-muted-foreground flex items-start gap-2">
                <Check class="h-4 w-4 text-green-500 shrink-0 mt-0.5" />
                {{ f.text }}
              </li>
              <li v-for="(f, i) in $tm('settings.proFeatures')" :key="'pro-'+i" class="text-sm flex items-start gap-2">
                <Check class="h-4 w-4 text-blue-500 shrink-0 mt-0.5" />
                {{ f.text }}
              </li>
            </ul>
            <Button
              v-if="isFree"
              class="w-full gap-2 rounded-xl mt-2"
              @click="router.push('/app/upgrade')"
            >
              <Crown class="h-4 w-4" />
              {{ $t("settings.upgradeBtn") }}
              <ArrowRight class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Display name (becenév) -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">{{ $t("settings.displayNameTitle") }}</CardTitle>
        <CardDescription>{{ $t("settings.displayNameDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="flex gap-2" @submit.prevent="saveDisplayName">
          <Input
            v-model="displayNameInput"
            type="text"
            maxlength="64"
            :placeholder="$t('onboarding.displayNamePlaceholder')"
            class="max-w-[260px]"
          />
          <Button type="submit" class="gap-2 rounded-xl" :disabled="displayNameSaving">
            <Loader2 v-if="displayNameSaving" class="h-4 w-4 animate-spin" />
            {{ $t("common.save") }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <!-- Daily step goal -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Heart class="h-4 w-4" />
          {{ $t("settings.stepGoalTitle") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.stepGoalDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="flex gap-2" @submit.prevent="saveStepGoal">
          <Input
            v-model="stepGoalInput"
            type="number"
            min="1000"
            max="100000"
            step="500"
            class="max-w-[180px]"
          />
          <Button type="submit" class="gap-2 rounded-xl" :disabled="stepGoalSaving">
            <Loader2 v-if="stepGoalSaving" class="h-4 w-4 animate-spin" />
            {{ $t("common.save") }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <!-- Daily water goal -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Droplet class="h-4 w-4" />
          {{ $t("settings.waterGoalTitle") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.waterGoalDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="flex gap-2" @submit.prevent="saveWaterGoal">
          <Input
            v-model="waterGoalInput"
            type="number"
            min="500"
            max="10000"
            step="100"
            class="max-w-[180px]"
          />
          <Button type="submit" class="gap-2 rounded-xl" :disabled="waterGoalSaving">
            <Loader2 v-if="waterGoalSaving" class="h-4 w-4 animate-spin" />
            {{ $t("common.save") }}
          </Button>
        </form>
      </CardContent>
    </Card>

    <!-- Data Export -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Download class="h-4 w-4" />
          {{ $t("settings.exportTitle") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.exportDesc") }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="flex gap-2 flex-wrap">
          <Button
            variant="outline"
            class="gap-2 rounded-xl"
            :disabled="!!exportLoading"
            @click="exportData('json')"
          >
            <Loader2 v-if="exportLoading === 'json'" class="h-4 w-4 animate-spin" />
            <Download v-else class="h-4 w-4" />
            JSON
          </Button>
          <Button
            variant="outline"
            class="gap-2 rounded-xl"
            :disabled="!!exportLoading"
            @click="exportData('csv')"
          >
            <Loader2 v-if="exportLoading === 'csv'" class="h-4 w-4 animate-spin" />
            <Download v-else class="h-4 w-4" />
            CSV (ZIP)
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Session -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">{{ $t("settings.session") }}</CardTitle>
        <CardDescription>{{ $t("settings.sessionDesc") }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <p class="text-sm text-muted-foreground">
          {{ $t("settings.sessionInfo") }}
        </p>
        <Button
          variant="outline"
          class="gap-2 rounded-xl"
          :disabled="loading"
          @click="logout"
        >
          <LogOut class="h-4 w-4" />
          {{ $t("auth.logout") }}
        </Button>
      </CardContent>
    </Card>

    <!-- HealthKit / Health Sync -->
    <Card v-if="pluginState === 'ready' || pluginState === 'needsPermission'" class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Heart class="h-4 w-4 text-red-500" />
          {{ $t("healthSync.title") }}
        </CardTitle>
        <CardDescription>{{ $t("healthSync.subtitle") }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="text-sm text-muted-foreground">
          <span v-if="pluginState === 'checking'">{{ $t("common.loading") }}</span>
          <span v-else-if="pluginState === 'ready'" class="text-green-600 dark:text-green-400 font-medium">{{ $t("healthSync.statusReady") }}</span>
          <span v-else-if="pluginState === 'needsPermission'">{{ $t("healthSync.statusNeedsPermission") }}</span>
        </div>

        <div v-if="lastSyncTime" class="text-xs text-muted-foreground">
          {{ $t("healthSync.lastSync") }}: {{ new Date(lastSyncTime).toLocaleString() }}
        </div>
        <div v-else class="text-xs text-muted-foreground">{{ $t("healthSync.neverSynced") }}</div>

        <Alert v-if="healthSyncError" variant="destructive" class="rounded-xl">
          <AlertDescription>{{ healthSyncError }}</AlertDescription>
        </Alert>

        <div v-if="syncedCounts" class="text-xs text-muted-foreground space-y-0.5">
          <p>{{ $t("healthSync.syncSuccess") }}</p>
          <p>{{ syncedCounts.heartRate }} HR, {{ syncedCounts.bloodPressure }} BP, {{ syncedCounts.weight }} WT, {{ syncedCounts.steps }} Steps, {{ syncedCounts.activities }} ACT</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <Button
            v-if="pluginState === 'needsPermission'"
            class="gap-2 rounded-xl"
            :disabled="isSyncing"
            @click="requestPermissions"
          >
            <Heart class="h-4 w-4" />
            {{ $t("healthSync.permissionBtn") }}
          </Button>
          <Button
            v-if="pluginState === 'ready'"
            variant="outline"
            class="gap-2 rounded-xl"
            :disabled="isSyncing"
            @click="syncAll"
          >
            <Loader2 v-if="isSyncing" class="h-4 w-4 animate-spin" />
            <RefreshCw v-else class="h-4 w-4" />
            {{ isSyncing ? $t("healthSync.syncing") : $t("healthSync.syncBtn") }}
          </Button>
          <Button
            v-if="pluginState === 'ready'"
            variant="outline"
            class="gap-2 rounded-xl"
            :disabled="isSyncing"
            @click="forceSync"
          >
            <Loader2 v-if="isSyncing" class="h-4 w-4 animate-spin" />
            <RefreshCw v-else class="h-4 w-4" />
            {{ $t("healthSync.forceSync") }}
          </Button>
        </div>
      </CardContent>
    </Card>
    <!-- Legal link -->
    <Card class="rounded-2xl">
      <button
        class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-accent/50 active:bg-accent transition text-left rounded-2xl"
        @click="router.push('/legal')"
      >
        <div class="h-8 w-8 rounded-lg bg-slate-500/10 flex items-center justify-center shrink-0">
          <FileText class="h-4 w-4 text-slate-500" />
        </div>
        <span class="text-sm font-medium flex-1">{{ $t("legal.link") }}</span>
        <ChevronRight class="h-4 w-4 text-muted-foreground" />
      </button>
    </Card>
    <!-- Danger zone -->
    <Card class="rounded-2xl border-destructive/30">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2 text-destructive">
          <AlertTriangle class="h-4 w-4" />
          {{ $t("settings.dangerZone") }}
        </CardTitle>
        <CardDescription>{{ $t("settings.dangerZoneDesc") }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <p class="text-sm text-muted-foreground">
          {{ $t("settings.dangerZoneInfo") }}
        </p>
        <div v-if="!showNukeConfirm">
          <Button
            variant="destructive"
            class="gap-2 rounded-xl"
            @click="showNukeConfirm = true"
          >
            <AlertTriangle class="h-4 w-4" />
            {{ $t("settings.logoutAll") }}
          </Button>
        </div>
        <div v-else class="space-y-2">
          <p class="text-sm font-medium text-destructive">{{ $t("settings.logoutAllConfirm") }}</p>
          <div class="flex gap-2">
            <Button
              variant="destructive"
              class="gap-2 rounded-xl"
              :disabled="nukeLoading"
              @click="nuke"
            >
              <LogOut class="h-4 w-4" />
              {{ $t("settings.logoutAllYes") }}
            </Button>
            <Button
              variant="outline"
              class="rounded-xl"
              :disabled="nukeLoading"
              @click="showNukeConfirm = false"
            >
              {{ $t("common.cancel") }}
            </Button>
          </div>
        </div>

        <div class="border-t pt-3">
          <div v-if="!showDeleteAccountConfirm">
            <Button
              variant="outline"
              class="gap-2 rounded-xl border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground"
              @click="showDeleteAccountConfirm = true"
            >
              <Trash2 class="h-4 w-4" />
              {{ $t("settings.deleteAccount") }}
            </Button>
          </div>
          <div v-else class="space-y-2">
            <p class="text-sm font-medium text-destructive">{{ $t("settings.deleteAccountConfirm") }}</p>
            <p class="text-xs text-muted-foreground">{{ $t("settings.deleteAccountInfo") }}</p>
            <div class="flex gap-2">
              <Button
                variant="destructive"
                class="gap-2 rounded-xl"
                :disabled="deleteAccountLoading"
                @click="deleteAccount"
              >
                <Trash2 class="h-4 w-4" />
                {{ $t("settings.deleteAccountYes") }}
              </Button>
              <Button
                variant="outline"
                class="rounded-xl"
                :disabled="deleteAccountLoading"
                @click="showDeleteAccountConfirm = false"
              >
                {{ $t("common.cancel") }}
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>


  </div>
</template>
