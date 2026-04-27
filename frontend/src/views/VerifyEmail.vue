<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from "vue"
import { useRouter, useRoute } from "vue-router"
import { useI18n } from "vue-i18n"
import api from "@/lib/api"
import { storageGet, storageSet, storageRemove } from "@/lib/storage"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { MailCheck, RefreshCw, LogOut, Loader2, CheckCircle2, LogIn } from "lucide-vue-next"

const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const sending = ref(false)
const checking = ref(false)
const verifying = ref(false)
const verified = ref(false)
const success = ref("")
const error = ref("")
const hasToken = ref(!!storageGet("auth_token"))
let pollInterval = null

async function verifyFromLink() {
  const { id, hash, expires, signature } = route.query
  if (!id || !hash || !expires || !signature) return false

  verifying.value = true
  try {
    const baseUrl = (import.meta.env.VITE_API_BASE_URL ?? "https://api.welfarebuddy.hu").replace(/\/$/, "")
    const response = await fetch(
      `${baseUrl}/email/verify/${id}/${hash}?expires=${encodeURIComponent(expires)}&signature=${encodeURIComponent(signature)}`,
      {
        method: "GET",
        headers: { "Accept": "application/json" },
      }
    )
    const data = await response.json()

    if (response.ok) {
      verified.value = true
      success.value = data.code === "already_verified" ? t("verify.alreadyVerifiedMsg") : t("verify.verifiedSuccess")

      if (hasToken.value) {
        try {
          const { data: userData } = await api.get("/token-check")
          if (userData?.user) storageSet("auth_user", JSON.stringify(userData.user))
        } catch {}
        setTimeout(() => router.replace("/app"), 2000)
      } else {
        setTimeout(() => router.replace("/auth?tab=login"), 3000)
      }
    } else {
      if (response.status === 403) {
        error.value = t("verify.invalidLink")
      } else if (response.status === 404) {
        error.value = t("verify.userNotFound")
      } else {
        error.value = data.message || t("verify.verifyError")
      }
    }
  } catch (e) {
    error.value = t("verify.verifyErrorRetry")
  } finally {
    verifying.value = false
  }
  return true
}

async function resendEmail() {
  success.value = ""
  error.value = ""
  sending.value = true
  try {
    await api.post("/email/verification-notification")
    success.value = t("verify.resendSuccess")
  } catch (e) {
    if (e?.response?.status === 401) {
      error.value = t("verify.resendNoAuth")
    } else {
      error.value = t("verify.resendError")
    }
  } finally {
    sending.value = false
  }
}

async function checkVerified() {
  checking.value = true
  try {
    const { data } = await api.get("/token-check")
    storageSet("auth_user", JSON.stringify(data.user))
    if (data.user.email_verified_at) {
      clearInterval(pollInterval)
      router.replace("/app")
    } else {
      error.value = t("verify.notVerifiedYet")
    }
  } catch (e) {
    if (e?.response?.status === 401) {
      error.value = t("verify.noAuth")
    }
  }
  checking.value = false
}

function logout() {
  api.post("/logout").catch(() => {})
  storageRemove("auth_token")
  storageRemove("auth_user")
  router.replace("/")
}

function goLogin() {
  router.push("/auth?tab=login")
}

async function handleRoute() {
  const hadLink = await verifyFromLink()
  if (!hadLink && hasToken.value) {
    pollInterval = setInterval(checkVerified, 10000)
    checkVerified()
  } else if (!hadLink && !hasToken.value) {
    router.replace("/auth?tab=login")
  }
}

onMounted(handleRoute)

// Ha a user már az oldalon van és a query params változik (pl. emailből kattint)
watch(() => route.query, (newQuery, oldQuery) => {
  if (newQuery.id && newQuery.hash && newQuery.id !== oldQuery?.id) {
    error.value = ""
    success.value = ""
    verified.value = false
    verifying.value = false
    handleRoute()
  }
})

onBeforeUnmount(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<template>
  <div class="min-h-svh flex items-center justify-center bg-muted/40 p-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-6">
        <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-14 mx-auto mb-2" />
        <h1 class="text-2xl font-bold">WelfareBuddy</h1>
      </div>

      <Card class="rounded-2xl">
        <CardContent class="p-6">
          <!-- Megerősítés folyamatban -->
          <div v-if="verifying" class="flex flex-col items-center text-center space-y-4">
            <div class="h-16 w-16 rounded-2xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <Loader2 class="h-8 w-8 text-blue-500 animate-spin" />
            </div>
            <div>
              <h2 class="text-xl font-bold">{{ $t("verify.verifying") }}</h2>
              <p class="text-sm text-muted-foreground mt-2">{{ $t("verify.verifyingDesc") }}</p>
            </div>
          </div>

          <!-- Sikeres megerősítés -->
          <div v-else-if="verified" class="flex flex-col items-center text-center space-y-4">
            <div class="h-16 w-16 rounded-2xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <CheckCircle2 class="h-8 w-8 text-green-500" />
            </div>
            <div>
              <h2 class="text-xl font-bold">{{ $t("verify.verified") }}</h2>
              <p v-if="hasToken" class="text-sm text-muted-foreground mt-2">{{ $t("verify.redirectingApp") }}</p>
              <p v-else class="text-sm text-muted-foreground mt-2">{{ $t("verify.redirectingLogin") }}</p>
            </div>
          </div>

          <!-- Várakozás / hiba -->
          <div v-else class="flex flex-col items-center text-center space-y-4">
            <div class="h-16 w-16 rounded-2xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <MailCheck class="h-8 w-8 text-blue-500" />
            </div>

            <div>
              <h2 class="text-xl font-bold">{{ $t("verify.waitTitle") }}</h2>
              <p class="text-sm text-muted-foreground mt-2 leading-relaxed">
                {{ $t("verify.waitDesc") }}
              </p>
              <p class="text-xs text-muted-foreground mt-2">
                {{ $t("verify.waitNotice") }}
              </p>
            </div>

            <Alert v-if="success" class="rounded-xl border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300 text-left">
              <AlertDescription>{{ success }}</AlertDescription>
            </Alert>
            <Alert v-if="error" variant="destructive" class="rounded-xl text-left">
              <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Ha be van jelentkezve -->
            <div v-if="hasToken" class="flex flex-col gap-2 w-full pt-2">
              <Button class="w-full rounded-xl gap-2" :disabled="sending" @click="resendEmail">
                <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                <RefreshCw v-else class="h-4 w-4" />
                {{ $t("verify.resend") }}
              </Button>
              <Button variant="outline" class="w-full rounded-xl gap-2" :disabled="checking" @click="checkVerified">
                <Loader2 v-if="checking" class="h-4 w-4 animate-spin" />
                {{ $t("verify.alreadyVerified") }}
              </Button>
              <Button variant="ghost" class="w-full rounded-xl gap-2 text-muted-foreground" @click="logout">
                <LogOut class="h-4 w-4" />
                {{ $t("auth.logout") }}
              </Button>
            </div>

            <!-- Ha nincs bejelentkezve -->
            <div v-else class="flex flex-col gap-2 w-full pt-2">
              <Button class="w-full rounded-xl gap-2" @click="goLogin">
                <LogIn class="h-4 w-4" />
                {{ $t("auth.login") }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
