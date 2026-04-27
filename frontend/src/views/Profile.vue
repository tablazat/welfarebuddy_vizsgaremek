<script setup>
import { ref, computed, onMounted } from "vue"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { storageGet, storageSet } from "@/lib/storage"
import { invalidateCacheKeys } from "@/lib/offlineCache"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { Separator } from "@/components/ui/separator"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { useAccess } from "@/composables/useAccess"
import { UserRound, Mail, Shield, Flame, Calendar, Camera, Trash2, Loader2, Crown, ArrowRight, Ruler, TrendingDown, TrendingUp, Minus, Sparkles } from "lucide-vue-next"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { pickMotivationalMessage } from "@/lib/utils"

const router = useRouter()
const { t, tm, locale } = useI18n()
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")

const loading = ref(true)
const uploading = ref(false)
const savingHeight = ref(false)
const user = ref(null)
const streak = ref(null)
const currentWeight = ref(null)
const error = ref("")
const success = ref("")
const fileInput = ref(null)
const heightForm = ref("")
const progress = ref(null)
const motivationalMsg = ref("")

const initials = computed(() => {
  if (!user.value?.name) return "?"
  return user.value.name.split(" ").map(p => p[0]).join("").toUpperCase().slice(0, 2)
})

const accessLabel = computed(() => {
  const map = { free: t("profile.accessLabels.free"), pro: t("profile.accessLabels.pro"), admin: t("profile.accessLabels.admin") }
  return map[user.value?.level_of_access] || user.value?.level_of_access || "—"
})

const accessColor = computed(() => {
  const map = {
    free: "bg-muted text-muted-foreground",
    pro: "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
    admin: "bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300",
  }
  return map[user.value?.level_of_access] || "bg-muted text-muted-foreground"
})

const profilePicUrl = computed(() => user.value?.profile_picture_url || null)
const { isFree, isPro, isAdmin } = useAccess(user)

const bmi = computed(() => {
  if (!user.value?.height_cm || !currentWeight.value) return null
  const h = user.value.height_cm / 100
  return Math.round((currentWeight.value / (h * h)) * 10) / 10
})

const bmiCategory = computed(() => {
  if (!bmi.value) return null
  if (bmi.value < 18.5) return { key: "bmiUnderweight", color: "text-blue-600" }
  if (bmi.value < 25) return { key: "bmiNormal", color: "text-green-600" }
  if (bmi.value < 30) return { key: "bmiOverweight", color: "text-yellow-600" }
  return { key: "bmiObese", color: "text-red-600" }
})

function formatDate(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleDateString(dateLocale.value, { year: "numeric", month: "long", day: "numeric" })
}

function clearMsg() { error.value = ""; success.value = "" }

function triggerUpload() {
  fileInput.value?.click()
}

async function handleFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  clearMsg()
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append("photo", file)
    const { data } = await api.post("/profile-picture", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    })
    user.value = data.user
    storageSet("auth_user", JSON.stringify(data.user))
    success.value = t("profile.pictureUploaded")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ""
  }
}

async function removePicture() {
  clearMsg()
  uploading.value = true
  try {
    const { data } = await api.delete("/profile-picture")
    user.value = data.user
    storageSet("auth_user", JSON.stringify(data.user))
    success.value = t("profile.pictureDeleted")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    uploading.value = false
  }
}

async function saveHeight() {
  clearMsg()
  const h = Number(heightForm.value)
  if (!h || h < 50 || h > 300) { error.value = t("profile.heightValidation"); return }
  savingHeight.value = true
  try {
    const { data } = await api.post("/height", { height_cm: h })
    if (data?.user?.height_cm) {
      user.value = data.user
      storageSet("auth_user", JSON.stringify(data.user))
    } else {
      invalidateCacheKeys("/token-check")
      try {
        const check = await api.get("/token-check")
        if (check.data?.user) {
          user.value = check.data.user
          storageSet("auth_user", JSON.stringify(check.data.user))
        }
      } catch {}
    }
    invalidateCacheKeys("/token-check")
    success.value = t("profile.heightSaved")
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    savingHeight.value = false
  }
}

onMounted(async () => {
  try {
    const [checkRes, weightRes, progressRes] = await Promise.allSettled([
      api.get("/token-check"),
      api.get("/current-weight"),
      api.get("/me/progress"),
    ])
    if (checkRes.status === "fulfilled") {
      user.value = checkRes.value.data.user
      streak.value = checkRes.value.data.streak
      storageSet("auth_user", JSON.stringify(checkRes.value.data.user))
      heightForm.value = checkRes.value.data.user?.height_cm || ""
    }
    if (weightRes.status === "fulfilled" && weightRes.value.data) {
      currentWeight.value = weightRes.value.data.weight
    }
    if (progressRes.status === "fulfilled" && progressRes.value.data) {
      progress.value = progressRes.value.data
      motivationalMsg.value = pickMotivationalMessage(progress.value, tm)
    }
  } catch {}
  loading.value = false
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold">{{ $t("profile.title") }}</h2>
      <p class="text-sm text-muted-foreground">{{ $t("profile.subtitle") }}</p>
    </div>

    <Alert v-if="error" variant="destructive" class="rounded-xl">
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>
    <Alert v-if="success" class="rounded-xl border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
      <AlertDescription>{{ success }}</AlertDescription>
    </Alert>

    <!-- Profile card -->
    <Card class="rounded-2xl">
      <CardContent class="p-6">
        <template v-if="loading">
          <div class="flex items-center gap-4">
            <Skeleton class="h-16 w-16 rounded-full" />
            <div class="space-y-2">
              <Skeleton class="h-5 w-40" />
              <Skeleton class="h-4 w-56" />
              <Skeleton class="h-5 w-20 rounded-full" />
            </div>
          </div>
        </template>
        <template v-else>
          <div class="flex items-center gap-4 flex-wrap">
            <div class="relative group">
              <Avatar class="h-16 w-16 text-xl">
                <AvatarImage v-if="profilePicUrl" :src="profilePicUrl" />
                <AvatarFallback class="text-lg">{{ initials }}</AvatarFallback>
              </Avatar>
              <button
                type="button"
                class="absolute inset-0 rounded-full bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer"
                :disabled="uploading"
                @click="triggerUpload"
              >
                <Loader2 v-if="uploading" class="h-5 w-5 text-white animate-spin" />
                <Camera v-else class="h-5 w-5 text-white" />
              </button>
              <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="handleFileChange"
              />
            </div>
            <div>
              <div class="text-xl font-semibold">{{ user?.name }}</div>
              <div class="text-sm text-muted-foreground">{{ user?.email }}</div>
              <div class="mt-1.5 flex items-center gap-2">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="accessColor">
                  {{ accessLabel }}
                </span>
                <Button
                  v-if="profilePicUrl"
                  variant="ghost"
                  size="sm"
                  class="h-6 px-2 text-xs text-muted-foreground gap-1"
                  :disabled="uploading"
                  @click="removePicture"
                >
                  <Trash2 class="h-3 w-3" />
                  {{ $t("profile.deletePicture") }}
                </Button>
              </div>
            </div>
          </div>
        </template>
      </CardContent>
    </Card>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-3">
      <Card class="rounded-2xl">
        <CardContent class="p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center shrink-0">
            <Flame class="h-5 w-5 text-orange-500" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">{{ $t("profile.currentStreak") }}</div>
            <template v-if="loading">
              <Skeleton class="h-6 w-12 mt-1" />
            </template>
            <template v-else>
              <div class="text-xl font-bold">{{ streak?.days || 0 }} <span class="text-sm font-normal text-muted-foreground">{{ $t("dashboard.streakDay") }}</span></div>
            </template>
          </div>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
            <Calendar class="h-5 w-5 text-green-600" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">{{ $t("profile.lastEntry") }}</div>
            <template v-if="loading">
              <Skeleton class="h-5 w-24 mt-1" />
            </template>
            <template v-else>
              <div class="text-sm font-semibold">{{ formatDate(streak?.last_day) }}</div>
            </template>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Account details -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base">{{ $t("profile.accountDetails") }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <template v-if="loading">
          <Skeleton class="h-5 w-full" />
          <Skeleton class="h-5 w-3/4" />
          <Skeleton class="h-5 w-1/2" />
        </template>
        <template v-else>
          <div class="flex items-center gap-3 text-sm">
            <UserRound class="h-4 w-4 text-muted-foreground shrink-0" />
            <span class="text-muted-foreground w-24 shrink-0">{{ $t("profile.name") }}</span>
            <span class="font-medium">{{ user?.name || '—' }}</span>
          </div>
          <Separator />
          <div class="flex items-center gap-3 text-sm">
            <Mail class="h-4 w-4 text-muted-foreground shrink-0" />
            <span class="text-muted-foreground w-24 shrink-0">{{ $t("profile.email") }}</span>
            <span class="font-medium">{{ user?.email || '—' }}</span>
          </div>
          <Separator />
          <div class="flex items-center gap-3 text-sm">
            <Shield class="h-4 w-4 text-muted-foreground shrink-0" />
            <span class="text-muted-foreground w-24 shrink-0">{{ $t("profile.access") }}</span>
            <span class="font-medium px-2 py-0.5 rounded-full text-xs" :class="accessColor">{{ accessLabel }}</span>
          </div>
          <Separator />
          <div class="flex items-center gap-3 text-sm">
            <Calendar class="h-4 w-4 text-muted-foreground shrink-0" />
            <span class="text-muted-foreground w-24 shrink-0">{{ $t("profile.registeredAt") }}</span>
            <span class="font-medium">{{ formatDate(user?.created_at) }}</span>
          </div>
        </template>
      </CardContent>
    </Card>

    <!-- Height & BMI -->
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Ruler class="h-4 w-4 text-muted-foreground" />
          {{ $t("profile.heightCard") }}
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <template v-if="loading">
          <Skeleton class="h-10 w-full" />
        </template>
        <template v-else>
          <form class="flex gap-3 items-end" @submit.prevent="saveHeight">
            <div class="flex-1 space-y-1.5">
              <Label>{{ $t("profile.heightLabel") }}</Label>
              <Input v-model="heightForm" type="number" min="50" max="300" placeholder="175" />
            </div>
            <Button type="submit" class="rounded-xl gap-2" :disabled="savingHeight">
              <Loader2 v-if="savingHeight" class="h-4 w-4 animate-spin" />
              {{ $t("profile.heightSave") }}
            </Button>
          </form>
          <div class="flex items-center gap-3 p-3 rounded-xl bg-muted flex-wrap">
            <div class="text-sm text-muted-foreground">{{ $t("profile.bmiLabel") }}:</div>
            <div v-if="bmi" class="font-bold text-lg" :class="bmiCategory?.color">
              {{ bmi }}
              <span class="text-sm font-normal ml-1">{{ $t(`profile.${bmiCategory?.key}`) }}</span>
            </div>
            <div v-else-if="!user?.height_cm" class="text-sm text-muted-foreground">
              {{ $t("profile.bmiNoHeight") }}
            </div>
            <div v-else class="text-sm text-muted-foreground flex items-center gap-1 flex-wrap">
              <span>{{ $t("profile.bmiNoWeight") }}</span>
              <Button variant="link" size="sm" class="px-1 h-auto" @click="router.push('/app/diary')">
                {{ $t("profile.addWeightLink") }}
              </Button>
            </div>
          </div>
        </template>
      </CardContent>
    </Card>

    <!-- Progress tracker — Az utad eddig -->
    <Card v-if="!loading && progress" class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Sparkles class="h-4 w-4 text-amber-500" />
          {{ $t("progress.cardTitle") }}
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="progress.weight_start !== null" class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">{{ $t("progress.weightLabel") }}</span>
          <div class="flex items-center gap-2 font-medium">
            <span>{{ progress.weight_start }} kg</span>
            <ArrowRight class="h-3 w-3 text-muted-foreground" />
            <span>{{ progress.weight_current }} kg</span>
            <span
              v-if="progress.weight_delta !== null && progress.weight_delta !== 0"
              class="flex items-center gap-0.5 text-xs font-semibold"
              :class="progress.weight_delta < 0 ? 'text-green-600' : 'text-orange-600'"
            >
              <TrendingDown v-if="progress.weight_delta < 0" class="h-3 w-3" />
              <TrendingUp v-else class="h-3 w-3" />
              {{ progress.weight_delta > 0 ? '+' : '' }}{{ progress.weight_delta }} kg
            </span>
            <Minus v-else-if="progress.weight_delta === 0" class="h-3 w-3 text-muted-foreground" />
          </div>
        </div>
        <div v-if="progress.bmi_start !== null && progress.bmi_current !== null" class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">{{ $t("progress.bmiLabel") }}</span>
          <div class="flex items-center gap-2 font-medium">
            <span>{{ progress.bmi_start }}</span>
            <ArrowRight class="h-3 w-3 text-muted-foreground" />
            <span>{{ progress.bmi_current }}</span>
          </div>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">{{ $t("progress.streakLabel") }}</span>
          <div class="flex items-center gap-2 font-medium">
            <span>{{ progress.streak_current }} {{ $t("dashboard.streakDay") }}</span>
            <span v-if="progress.streak_max > 0" class="text-xs text-muted-foreground">
              ({{ $t("progress.streakRecord") }}: {{ progress.streak_max }})
            </span>
          </div>
        </div>
        <div v-if="motivationalMsg" class="mt-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 text-sm text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-900">
          {{ motivationalMsg }}
        </div>
      </CardContent>
    </Card>

    <!-- Előfizetés -->
    <Card v-if="!loading" class="rounded-2xl" :class="isAdmin ? 'border-purple-200 dark:border-purple-800' : isPro ? 'border-blue-200 dark:border-blue-800' : ''">
      <CardContent class="p-5">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
              :class="isAdmin ? 'bg-purple-100 dark:bg-purple-900/30' : isPro ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-muted'"
            >
              <Crown class="h-5 w-5" :class="isAdmin ? 'text-purple-500' : isPro ? 'text-blue-500' : 'text-muted-foreground'" />
            </div>
            <div>
              <div class="text-sm font-semibold">
                {{ isAdmin ? $t("profile.accessLabels.admin") : isPro ? $t("profile.subscription.pro") : $t("profile.subscription.free") }}
              </div>
              <div class="text-xs text-muted-foreground">
                {{ isAdmin ? $t("profile.adminAccess") : isPro ? $t("profile.proAccess") : $t("profile.freeAccess") }}
              </div>
            </div>
          </div>
          <Button
            v-if="isFree"
            size="sm"
            class="rounded-xl gap-1.5 shrink-0"
            @click="router.push('/app/upgrade')"
          >
            <Crown class="h-3.5 w-3.5" />
            Upgrade
            <ArrowRight class="h-3.5 w-3.5" />
          </Button>
          <Button
            v-if="isPro && !isAdmin"
            variant="outline"
            size="sm"
            class="rounded-xl gap-1.5 shrink-0"
            @click="router.push('/app/upgrade')"
          >
            {{ $t("profile.manage") }}
            <ArrowRight class="h-3.5 w-3.5" />
          </Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
