<script setup>
import { ref, computed, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter, useRoute } from "vue-router"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { storageSet, storageGet } from "@/lib/storage"
import { hapticSuccess, hapticHeavy } from "@/lib/haptics"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Loader2, ArrowLeft, MailCheck } from "lucide-vue-next"
import { supportedLocales, setLocale } from "@/lib/i18n"

const { t, locale } = useI18n()
const langOpen = ref(false)

function switchLocale(code) {
  setLocale(code)
  langOpen.value = false
}
const showVerifyNotice = ref(false)

onMounted(() => {
  if (route.query.token && route.query.email) {
    showReset.value = true
    resetForm.value.token = route.query.token
    resetForm.value.email = route.query.email
  }
  if (route.query.pw_changed === "1") {
    success.value = t("auth.pwChanged")
  }
})

const router = useRouter()
const route = useRoute()

const activeTab = ref(route.query.tab === "register" ? "register" : "login")

const loading = ref(false)
const error = ref("")
const success = ref("")

const loginForm = ref({ email: "", password: "" })
const registerForm = ref({ name: "", email: "", password: "", password_confirmation: "" })
const termsAccepted = ref(false)
const forgotForm = ref({ email: "" })
const resetForm = ref({ email: "", password: "", password_confirmation: "", token: "" })
const showForgot = ref(false)
const showReset = ref(false)

const redirect = computed(() => route.query.redirect || "/app")

function setError(msg) {
  error.value = msg
  success.value = ""
}

function setSuccess(msg) {
  success.value = msg
  error.value = ""
}

function clearMessages() {
  error.value = ""
  success.value = ""
}

function saveAuth(token, user) {
  storageSet("auth_token", token)
  if (user) storageSet("auth_user", JSON.stringify(user))
}

async function login() {
  clearMessages()
  loading.value = true
  try {
    const { data } = await api.post("/login", loginForm.value)
    saveAuth(data.token, data.user)
    setSuccess(t("common.success"))
    hapticSuccess()
    router.replace(redirect.value)
  } catch (e) {
    setError(getAxiosErrorMessage(e))
    hapticHeavy()
  } finally {
    loading.value = false
  }
}

async function forgotPassword() {
  clearMessages()
  if (!forgotForm.value.email) { setError(t("auth.emailRequired")); return }
  loading.value = true
  try {
    await api.post("/forgot-password", { ...forgotForm.value, locale: locale.value })
    setSuccess(t("auth.resetPasswordDone"))
    hapticSuccess()
  } catch (e) {
    setError(getAxiosErrorMessage(e))
    hapticHeavy()
  } finally {
    loading.value = false
  }
}

async function resetPassword() {
  clearMessages()
  if (resetForm.value.password.length < 8) { setError(t("auth.passwordRequired")); return }
  if (resetForm.value.password !== resetForm.value.password_confirmation) { setError(t("auth.passwordMismatch")); return }
  loading.value = true
  try {
    await api.post("/reset-password", resetForm.value)
    setSuccess(t("auth.resetPasswordDoneDesc"))
    hapticSuccess()
    showReset.value = false
  } catch (e) {
    setError(getAxiosErrorMessage(e))
    hapticHeavy()
  } finally {
    loading.value = false
  }
}

async function register() {
  clearMessages()
  if (registerForm.value.password.length < 8) {
    setError(t("auth.passwordRequired"))
    return
  }
  if (registerForm.value.password !== registerForm.value.password_confirmation) {
    setError(t("auth.passwordMismatch"))
    return
  }
  if (!termsAccepted.value) {
    setError(t("auth.termsRequired"))
    return
  }
  loading.value = true
  try {
    const { data } = await api.post("/register", {
      ...registerForm.value,
      locale: locale.value,
      terms_accepted: true,
    })
    saveAuth(data.token, data.user)
    hapticSuccess()
    router.replace("/verify-email")
  } catch (e) {
    setError(getAxiosErrorMessage(e))
    hapticHeavy()
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-svh flex items-center justify-center bg-muted/40 p-4">
    <div class="w-full max-w-md">
      <div class="mb-4 flex items-center justify-end">
        <div class="relative">
          <button
            type="button"
            class="text-xl leading-none px-1.5 py-1 rounded-lg hover:bg-accent transition"
            @click="langOpen = !langOpen"
          >
            {{ supportedLocales.find(l => l.code === locale)?.flag }}
          </button>
          <div
            v-if="langOpen"
            class="absolute right-0 mt-1 bg-popover border rounded-xl shadow-lg py-1 z-50 min-w-[140px]"
          >
            <button
              v-for="loc in supportedLocales"
              :key="loc.code"
              type="button"
              class="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-accent transition"
              :class="locale === loc.code ? 'font-semibold' : ''"
              @click="switchLocale(loc.code)"
            >
              <span class="text-base">{{ loc.flag }}</span>
              {{ loc.label }}
            </button>
          </div>
        </div>
      </div>
      <div class="text-center mb-6">
        <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-14 mx-auto mb-2" />
        <h1 class="text-2xl font-bold">WelfareBuddy</h1>
        <p class="text-muted-foreground text-sm mt-1">{{ $t("auth.welcomeSubtitle") }}</p>
      </div>

      
      <Card v-if="showVerifyNotice" class="rounded-2xl border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/40 mb-4">
        <CardContent class="p-5">
          <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center shrink-0">
              <MailCheck class="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <div>
              <h3 class="font-semibold text-green-800 dark:text-green-300">{{ $t("auth.registerSuccess") }}</h3>
              <p class="text-sm text-green-700 dark:text-green-400 mt-1">
                {{ $t("auth.registerVerifyMsg") }}
              </p>
              <Button
                size="sm"
                class="mt-3 rounded-xl gap-1.5"
                @click="showVerifyNotice = false; router.replace(redirect)"
              >
                {{ $t("auth.goToApp") }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      
      <Card v-else-if="showForgot" class="rounded-2xl">
        <CardHeader class="pb-2">
          <CardTitle class="text-lg">{{ $t("auth.forgotPasswordTitle") }}</CardTitle>
          <CardDescription>{{ $t("auth.forgotPasswordDesc") }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="error" class="mb-4">
            <Alert variant="destructive"><AlertDescription>{{ error }}</AlertDescription></Alert>
          </div>
          <div v-if="success" class="mb-4">
            <Alert><AlertDescription>{{ success }}</AlertDescription></Alert>
          </div>
          <form class="space-y-4" @submit.prevent="forgotPassword">
            <div class="space-y-2">
              <Label>{{ $t("auth.email") }}</Label>
              <Input v-model="forgotForm.email" type="email" placeholder="neved@email.com" />
            </div>
            <Button class="w-full" type="submit" :disabled="loading">
              <Loader2 v-if="loading" class="h-4 w-4 mr-2 animate-spin" />
              {{ $t("auth.sendResetLink") }}
            </Button>
            <div class="text-xs text-muted-foreground text-center">
              <button type="button" class="underline text-foreground" @click="showForgot = false; clearMessages()">
                {{ $t("auth.backToLogin") }}
              </button>
            </div>
          </form>
        </CardContent>
      </Card>

      
      <Card v-else-if="showReset" class="rounded-2xl">
        <CardHeader class="pb-2">
          <CardTitle class="text-lg">{{ $t("auth.resetPasswordTitle") }}</CardTitle>
          <CardDescription>{{ $t("auth.resetPasswordNewDesc") }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="error" class="mb-4">
            <Alert variant="destructive"><AlertDescription>{{ error }}</AlertDescription></Alert>
          </div>
          <div v-if="success" class="mb-4">
            <Alert><AlertDescription>{{ success }}</AlertDescription></Alert>
          </div>
          <form class="space-y-4" @submit.prevent="resetPassword">
            <div class="space-y-2">
              <Label>{{ $t("auth.email") }}</Label>
              <Input v-model="resetForm.email" type="email" disabled />
            </div>
            <div class="space-y-2">
              <Label>{{ $t("auth.newPassword") }}</Label>
              <Input v-model="resetForm.password" type="password" :placeholder="$t('auth.passwordMinLength')" />
            </div>
            <div class="space-y-2">
              <Label>{{ $t("auth.confirmPassword") }}</Label>
              <Input v-model="resetForm.password_confirmation" type="password" placeholder="••••••" />
            </div>
            <Button class="w-full" type="submit" :disabled="loading">
              <Loader2 v-if="loading" class="h-4 w-4 mr-2 animate-spin" />
              {{ $t("auth.resetPasswordBtn") }}
            </Button>
            <div class="text-xs text-muted-foreground text-center">
              <button type="button" class="underline text-foreground" @click="showReset = false; clearMessages()">
                {{ $t("auth.backToLogin") }}
              </button>
            </div>
          </form>
        </CardContent>
      </Card>

      
      <Card v-else class="rounded-2xl">
        <CardHeader class="pb-2">
          <CardTitle class="text-lg">{{ $t("auth.welcome") }}</CardTitle>
          <CardDescription>{{ $t("auth.welcomeSubtitle") }}</CardDescription>
        </CardHeader>

        <CardContent>
          <div v-if="error" class="mb-4">
            <Alert variant="destructive">
              <AlertTitle>{{ $t("common.error") }}</AlertTitle>
              <AlertDescription>{{ error }}</AlertDescription>
            </Alert>
          </div>

          <div v-if="success" class="mb-4">
            <Alert>
              <AlertTitle>{{ $t("common.success") }}</AlertTitle>
              <AlertDescription>{{ success }}</AlertDescription>
            </Alert>
          </div>

          <Tabs v-model="activeTab">
            <TabsList class="grid grid-cols-2 w-full">
              <TabsTrigger value="login">{{ $t("auth.login") }}</TabsTrigger>
              <TabsTrigger value="register">{{ $t("auth.register") }}</TabsTrigger>
            </TabsList>

            <TabsContent value="login" class="mt-4">
              <form class="space-y-4" @submit.prevent="login">
                <div class="space-y-2">
                  <Label>{{ $t("auth.email") }}</Label>
                  <Input v-model="loginForm.email" type="email" autocomplete="email" placeholder="neved@email.com" />
                </div>
                <div class="space-y-2">
                  <Label>{{ $t("auth.password") }}</Label>
                  <Input v-model="loginForm.password" type="password" autocomplete="current-password" placeholder="••••••" />
                </div>
                <Button class="w-full" type="submit" :disabled="loading">
                  <Loader2 v-if="loading" class="h-4 w-4 mr-2 animate-spin" />
                  {{ $t("auth.login") }}
                </Button>
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                  <button type="button" class="underline hover:text-foreground" @click="showForgot = true; clearMessages()">
                    {{ $t("auth.forgotPassword") }}
                  </button>
                  <span>
                    {{ $t("auth.noAccount") }}
                    <button type="button" class="underline ml-1 text-foreground" @click="activeTab = 'register'">
                      {{ $t("auth.register") }}
                    </button>
                  </span>
                </div>
              </form>
            </TabsContent>

            <TabsContent value="register" class="mt-4">
              <form class="space-y-4" @submit.prevent="register">
                <div class="space-y-2">
                  <Label>{{ $t("auth.name") }}</Label>
                  <Input v-model="registerForm.name" type="text" autocomplete="name" placeholder="Papp Péter" />
                </div>
                <div class="space-y-2">
                  <Label>{{ $t("auth.email") }}</Label>
                  <Input v-model="registerForm.email" type="email" autocomplete="email" placeholder="neved@email.com" />
                </div>
                <div class="space-y-2">
                  <Label>{{ $t("auth.password") }}</Label>
                  <Input v-model="registerForm.password" type="password" autocomplete="new-password" :placeholder="$t('auth.passwordMinLength')" />
                </div>
                <div class="space-y-2">
                  <Label>{{ $t("auth.confirmPassword") }}</Label>
                  <Input v-model="registerForm.password_confirmation" type="password" autocomplete="new-password" placeholder="••••••" />
                </div>
                <label class="flex items-start gap-2 text-xs text-muted-foreground cursor-pointer">
                  <input
                    v-model="termsAccepted"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border border-input accent-primary cursor-pointer"
                  />
                  <span>
                    {{ $t("auth.termsPrefix") }}
                    <router-link to="/legal?tab=tos" class="underline text-foreground">{{ $t("auth.termsLinkTos") }}</router-link>{{ $t("auth.termsSep1") }}
                    <router-link to="/legal?tab=gdpr" class="underline text-foreground">{{ $t("auth.termsLinkGdpr") }}</router-link>{{ $t("auth.termsSep2") }}
                    <router-link to="/legal?tab=eula" class="underline text-foreground">{{ $t("auth.termsLinkEula") }}</router-link>{{ $t("auth.termsSuffix") }}
                  </span>
                </label>
                <Button class="w-full" type="submit" :disabled="loading || !termsAccepted">
                  <Loader2 v-if="loading" class="h-4 w-4 mr-2 animate-spin" />
                  {{ $t("auth.register") }}
                </Button>
                <div class="text-xs text-muted-foreground text-center">
                  {{ $t("auth.haveAccount") }}
                  <button type="button" class="underline ml-1 text-foreground" @click="activeTab = 'login'">
                    {{ $t("auth.login") }}
                  </button>
                </div>
              </form>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
