<script setup>
import { ref, computed, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import api from "@/lib/api"
import { storageGet, storageSet } from "@/lib/storage"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { useAccess } from "@/composables/useAccess"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Check, Crown, ArrowLeft, Sparkles, Loader2, ChevronDown } from "lucide-vue-next"
import { hapticSuccess, hapticHeavy } from "@/lib/haptics"

const { t, tm } = useI18n()
const router = useRouter()
const authUser = ref(JSON.parse(storageGet("auth_user") || "null"))
const { isPro, isAdmin } = useAccess(authUser)

const loading = ref(false)
const success = ref("")
const error = ref("")

const freeFeatures = computed(() =>
  (tm("settings.freeFeatures") || []).map(f => ({
    text: typeof f.text === "function" ? f.text() : f.text,
    included: f.included,
  }))
)
const proFeatures = computed(() =>
  (tm("settings.proFeatures") || []).map(f => ({
    text: typeof f.text === "function" ? f.text() : f.text,
  }))
)

onMounted(async () => {
  try {
    const { data } = await api.get("/token-check")
    authUser.value = data.user
    storageSet("auth_user", JSON.stringify(data.user))
  } catch {}
})

async function upgradeToPro() {
  loading.value = true
  success.value = ""
  error.value = ""
  try {
    const { data } = await api.put("/change-plan", { level_of_access: "pro" })
    authUser.value = data.user
    storageSet("auth_user", JSON.stringify(data.user))
    success.value = t("upgrade.proActivated")
    hapticSuccess()
  } catch (e) {
    error.value = getAxiosErrorMessage(e, t("upgrade.proActivateError"))
    hapticHeavy()
  } finally {
    loading.value = false
  }
}

async function downgradeToFree() {
  loading.value = true
  success.value = ""
  error.value = ""
  try {
    const { data } = await api.put("/change-plan", { level_of_access: "free" })
    authUser.value = data.user
    storageSet("auth_user", JSON.stringify(data.user))
    success.value = t("upgrade.downgradeDone")
    hapticHeavy()
  } catch (e) {
    error.value = getAxiosErrorMessage(e, t("upgrade.downgradeError"))
    hapticHeavy()
  } finally {
    loading.value = false
  }
}

</script>

<template>
  <div class="space-y-4">
    <div>
      <Button variant="ghost" size="sm" class="rounded-xl gap-1.5 text-muted-foreground mb-2" @click="router.push('/app/settings')">
        <ArrowLeft class="h-3.5 w-3.5" />
        {{ $t("upgrade.backToSettings") }}
      </Button>
      <h2 class="text-lg font-semibold flex items-center gap-2">
        <Sparkles class="h-5 w-5 text-blue-500" />
        {{ $t("upgrade.title") }}
      </h2>
      <p class="text-sm text-muted-foreground">{{ $t("upgrade.subtitle") }}</p>
    </div>

    <!-- Visszajelzések -->
    <Alert v-if="success" variant="default" class="rounded-xl border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/40 text-green-700 dark:text-green-300">
      <AlertDescription>{{ success }}</AlertDescription>
    </Alert>
    <Alert v-if="error" variant="destructive" class="rounded-xl">
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <!-- Már Pro -->
    <div v-if="isPro && !isAdmin" class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/40 p-4 text-sm text-blue-700 dark:text-blue-300 flex items-center gap-2">
      <Crown class="h-4 w-4 shrink-0" />
      {{ $t("upgrade.alreadyPro") }}
    </div>

    <!-- Admin -->
    <div v-if="isAdmin" class="rounded-xl border border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-950/40 p-4 text-sm text-purple-700 dark:text-purple-300 flex items-center gap-2">
      <Crown class="h-4 w-4 shrink-0" />
      {{ $t("upgrade.alreadyAdmin") }}
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- Free -->
      <Card class="rounded-2xl relative overflow-hidden" :class="!isPro ? 'border-green-200 dark:border-green-800' : ''">
        <div v-if="!isPro" class="absolute top-0 right-0 bg-green-500 text-white text-xs font-medium px-3 py-1 rounded-bl-xl">
          {{ $t("upgrade.current") }}
        </div>
        <CardHeader>
          <CardTitle class="text-lg">{{ $t("upgrade.free") }}</CardTitle>
          <CardDescription>{{ $t("upgrade.freeDesc") }}</CardDescription>
          <div class="mt-2">
            <span class="text-3xl font-bold">0 Ft</span>
            <span class="text-sm text-muted-foreground"> {{ $t("upgrade.perMonth") }}</span>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <ul class="space-y-2">
            <li v-for="(f, i) in freeFeatures" :key="i" class="flex items-start gap-2 text-sm">
              <Check v-if="f.included" class="h-4 w-4 text-green-500 shrink-0 mt-0.5" />
              <span v-else class="h-4 w-4 text-muted-foreground/40 shrink-0 mt-0.5 text-center">—</span>
              <span :class="f.included ? '' : 'text-muted-foreground/60'">{{ f.text }}</span>
            </li>
          </ul>
          <Button
            v-if="isPro && !isAdmin"
            variant="outline"
            class="w-full gap-2 rounded-xl"
            :disabled="loading"
            @click="downgradeToFree"
          >
            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
            <ChevronDown v-else class="h-4 w-4" />
            {{ $t("upgrade.downgrade") }}
          </Button>
        </CardContent>
      </Card>

      <!-- Pro -->
      <Card class="rounded-2xl relative overflow-hidden" :class="isPro ? 'border-blue-200 dark:border-blue-800' : ''">
        <div v-if="isPro" class="absolute top-0 right-0 bg-blue-500 text-white text-xs font-medium px-3 py-1 rounded-bl-xl">
          {{ $t("upgrade.current") }}
        </div>
        <div v-else class="absolute top-0 right-0 bg-blue-500 text-white text-xs font-medium px-3 py-1 rounded-bl-xl">
          {{ $t("upgrade.recommended") }}
        </div>
        <CardHeader>
          <CardTitle class="text-lg flex items-center gap-1.5">
            <Crown class="h-5 w-5 text-blue-500" />
            Pro
          </CardTitle>
          <CardDescription>{{ $t("upgrade.proDesc") }}</CardDescription>
          <div class="mt-2">
            <span class="text-3xl font-bold">990 Ft</span>
            <span class="text-sm text-muted-foreground"> {{ $t("upgrade.perMonth") }}</span>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <ul class="space-y-2">
            <li v-for="(f, i) in proFeatures" :key="i" class="flex items-start gap-2 text-sm">
              <Check class="h-4 w-4 text-blue-500 shrink-0 mt-0.5" />
              <span>{{ f.text }}</span>
            </li>
          </ul>
          <Button
            v-if="!isPro"
            class="w-full gap-2 rounded-xl"
            :disabled="loading"
            @click="upgradeToPro"
          >
            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
            <Crown v-else class="h-4 w-4" />
            {{ $t("upgrade.activate") }}
          </Button>
        </CardContent>
      </Card>
    </div>

    <p class="text-xs text-muted-foreground text-center">
      {{ $t("upgrade.demoNote") }}
    </p>
  </div>
</template>
