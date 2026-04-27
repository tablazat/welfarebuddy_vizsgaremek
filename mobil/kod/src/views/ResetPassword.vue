<script setup>
import { ref, onMounted } from "vue"
import { useRouter, useRoute } from "vue-router"
import { useI18n } from "vue-i18n"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Loader2, ArrowLeft, KeyRound, CheckCircle2 } from "lucide-vue-next"

const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const loading = ref(false)
const error = ref("")
const done = ref(false)

const form = ref({
  token: "",
  email: "",
  password: "",
  password_confirmation: "",
})

onMounted(() => {
  form.value.token = route.query.token || ""
  form.value.email = route.query.email || ""

  if (!form.value.token || !form.value.email) {
    error.value = t("auth.resetPasswordInvalidLink")
  }
})

async function resetPassword() {
  error.value = ""
  if (form.value.password.length < 8) {
    error.value = t("auth.passwordMinLength")
    return
  }
  if (form.value.password !== form.value.password_confirmation) {
    error.value = t("auth.passwordMismatch")
    return
  }

  loading.value = true
  try {
    await api.post("/reset-password", form.value)
    done.value = true
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    loading.value = false
  }
}

function goToLogin() {
  router.push({ name: "auth", query: { tab: "login" } })
}
</script>

<template>
  <div class="min-h-svh flex items-center justify-center bg-muted/40 p-4">
    <div class="w-full max-w-md">
      <div class="mb-4">
        <Button variant="ghost" size="sm" class="rounded-xl gap-1.5 text-muted-foreground" @click="router.push('/auth')">
          <ArrowLeft class="h-3.5 w-3.5" />
          {{ $t("auth.backToLogin") }}
        </Button>
      </div>

      <div class="text-center mb-6">
        <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-14 mx-auto mb-2" />
        <h1 class="text-2xl font-bold">WelfareBuddy</h1>
        <p class="text-muted-foreground text-sm mt-1">{{ $t("auth.resetPassword") }}</p>
      </div>

      
      <Card v-if="done" class="rounded-2xl border-green-200 dark:border-green-800">
        <CardContent class="p-6 text-center space-y-4">
          <div class="h-14 w-14 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center mx-auto">
            <CheckCircle2 class="h-7 w-7 text-green-600 dark:text-green-400" />
          </div>
          <div>
            <h2 class="text-lg font-semibold">{{ $t("auth.resetPasswordDone") }}</h2>
            <p class="text-sm text-muted-foreground mt-1">
              {{ $t("auth.resetPasswordDoneDesc") }}
            </p>
          </div>
          <Button class="w-full rounded-xl gap-2" @click="goToLogin">
            <ArrowLeft class="h-4 w-4" />
            {{ $t("auth.login") }}
          </Button>
        </CardContent>
      </Card>

      
      <Card v-else class="rounded-2xl">
        <CardHeader class="pb-2">
          <CardTitle class="text-lg flex items-center gap-2">
            <KeyRound class="h-5 w-5" />
            {{ $t("auth.resetPasswordTitle") }}
          </CardTitle>
          <CardDescription>{{ $t("auth.resetPasswordNewDesc") }}</CardDescription>
        </CardHeader>
        <CardContent>
          <Alert v-if="error" variant="destructive" class="mb-4 rounded-xl">
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>

          <form class="space-y-4" @submit.prevent="resetPassword">
            <div class="space-y-2">
              <Label>{{ $t("auth.email") }}</Label>
              <Input v-model="form.email" type="email" disabled class="bg-muted/50" />
            </div>
            <div class="space-y-2">
              <Label>{{ $t("auth.newPassword") }}</Label>
              <Input
                v-model="form.password"
                type="password"
                :placeholder="$t('auth.passwordMinLength')"
                autocomplete="new-password"
              />
            </div>
            <div class="space-y-2">
              <Label>{{ $t("auth.confirmPassword") }}</Label>
              <Input
                v-model="form.password_confirmation"
                type="password"
                :placeholder="$t('auth.confirmPassword')"
                autocomplete="new-password"
              />
            </div>
            <Button
              class="w-full rounded-xl gap-2"
              type="submit"
              :disabled="loading || !form.token"
            >
              <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
              <KeyRound v-else class="h-4 w-4" />
              {{ $t("auth.resetPasswordBtn") }}
            </Button>
            <div class="text-xs text-muted-foreground text-center">
              <button type="button" class="underline hover:text-foreground" @click="goToLogin">
                {{ $t("auth.backToLogin") }}
              </button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
