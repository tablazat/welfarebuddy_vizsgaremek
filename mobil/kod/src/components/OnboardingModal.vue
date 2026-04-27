<script setup>
import { ref, inject, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { storageSet } from "@/lib/storage"
import { scheduleDailyReminder, setNotifPref } from "@/composables/useLocalNotifications"
import api from "@/lib/api"
import {
  Smile, Ruler, UserCircle, Footprints, Bell, CheckCircle2, ChevronRight,
} from "lucide-vue-next"
import { hapticLight, hapticSuccess } from "@/lib/haptics"

const emit = defineEmits(["done"])
const { t, tm } = useI18n()
const user = inject("user", ref(null))

const step = ref(1)
const TOTAL_STEPS = 5

// Step 2 – height
const heightInput = ref("")
const heightSaving = ref(false)

// Step 3 – display name (becenév)
const displayNameInput = ref("")
const displayNameSaving = ref(false)

// Step 4 – step goal
const stepGoalInput = ref("10000")

// Step 5 – reminder
const reminderEnabled = ref(false)
const reminderHour = ref(9)
const reminderSaving = ref(false)

onMounted(() => {
  // Default display_name = teljes név első szava (pl. „Simon Balázs" → „Simon")
  const fullName = user.value?.name || ""
  displayNameInput.value = user.value?.display_name || fullName.split(" ")[0] || ""
})

async function saveHeight() {
  const h = parseInt(heightInput.value)
  if (h >= 50 && h <= 300) {
    heightSaving.value = true
    try {
      await api.post("/height", { height_cm: h })
    } catch {}
    heightSaving.value = false
  }
  hapticLight()
  step.value = 3
}

async function saveDisplayName() {
  const name = displayNameInput.value.trim()
  if (name && name.length >= 1 && name.length <= 64) {
    displayNameSaving.value = true
    try {
      await api.put("/me/profile", { display_name: name })
    } catch {}
    displayNameSaving.value = false
  }
  hapticLight()
  step.value = 4
}

async function saveStepGoal() {
  const g = parseInt(stepGoalInput.value) || 10000
  const clamped = Math.max(1000, Math.min(100000, g))
  storageSet("step_goal", String(clamped))
  try {
    await api.put("/me/goals", { step_goal_daily: clamped })
  } catch {}
  hapticLight()
  step.value = 5
}

async function saveReminder() {
  if (reminderEnabled.value) {
    reminderSaving.value = true
    try {
      setNotifPref("enabled", true)
      setNotifPref("daily", true)
      setNotifPref("daily_hour", reminderHour.value)
      await scheduleDailyReminder(
        t("notifications.dailyReminder"),
        tm("notifications.dailyReminderMessages"),
        reminderHour.value,
      )
    } catch {}
    reminderSaving.value = false
  } else {
    setNotifPref("daily", false)
  }
  hapticLight()
  step.value = 6
}

function finish() {
  storageSet("onboarding_done", "1")
  hapticSuccess()
  emit("done")
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-sm bg-card rounded-3xl shadow-2xl border p-6 space-y-5">

      <!-- Progress dots -->
      <div class="flex items-center justify-center gap-1.5">
        <div
          v-for="i in TOTAL_STEPS"
          :key="i"
          class="h-1.5 rounded-full transition-all duration-300"
          :class="i <= step - 1 ? 'bg-primary w-6' : i === step ? 'bg-primary w-4' : 'bg-muted w-4'"
        />
      </div>

      <!-- Step 1: Welcome -->
      <template v-if="step === 1">
        <div class="text-center space-y-3">
          <div class="flex justify-center">
            <div class="h-16 w-16 rounded-2xl bg-primary/10 flex items-center justify-center">
              <Smile class="h-8 w-8 text-primary" />
            </div>
          </div>
          <h2 class="text-xl font-bold">{{ $t("onboarding.welcomeTitle") }}</h2>
          <p class="text-sm text-muted-foreground">{{ $t("onboarding.welcomeDesc") }}</p>
        </div>
        <Button class="w-full rounded-xl gap-2" @click="step = 2">
          {{ $t("onboarding.start") }}
          <ChevronRight class="h-4 w-4" />
        </Button>
      </template>

      <!-- Step 2: Height -->
      <template v-else-if="step === 2">
        <div class="space-y-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
              <Ruler class="h-5 w-5 text-violet-600" />
            </div>
            <div>
              <h3 class="font-semibold">{{ $t("onboarding.heightTitle") }}</h3>
              <p class="text-xs text-muted-foreground">{{ $t("onboarding.heightDesc") }}</p>
            </div>
          </div>
          <div class="space-y-1.5">
            <Label>{{ $t("profile.heightLabel") }}</Label>
            <div class="flex gap-2">
              <Input
                v-model="heightInput"
                type="number"
                min="50"
                max="300"
                placeholder="170"
                class="rounded-xl"
              />
              <span class="flex items-center text-sm text-muted-foreground font-medium pr-1">cm</span>
            </div>
          </div>
          <div class="flex gap-2">
            <Button class="flex-1 rounded-xl" :disabled="heightSaving" @click="saveHeight">
              {{ $t("common.next") }}
            </Button>
            <Button variant="ghost" class="rounded-xl" @click="step = 3">
              {{ $t("common.skip") }}
            </Button>
          </div>
        </div>
      </template>

      <!-- Step 3: Display name (becenév) -->
      <template v-else-if="step === 3">
        <div class="space-y-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
              <UserCircle class="h-5 w-5 text-blue-600" />
            </div>
            <div>
              <h3 class="font-semibold">{{ $t("onboarding.displayNameTitle") }}</h3>
              <p class="text-xs text-muted-foreground">{{ $t("onboarding.displayNameDesc") }}</p>
            </div>
          </div>
          <div class="space-y-1.5">
            <Label>{{ $t("onboarding.displayNameLabel") }}</Label>
            <Input
              v-model="displayNameInput"
              type="text"
              maxlength="64"
              :placeholder="$t('onboarding.displayNamePlaceholder')"
              class="rounded-xl"
            />
          </div>
          <div class="flex gap-2">
            <Button class="flex-1 rounded-xl" :disabled="displayNameSaving" @click="saveDisplayName">
              {{ $t("common.next") }}
            </Button>
            <Button variant="ghost" class="rounded-xl" @click="step = 4">
              {{ $t("common.skip") }}
            </Button>
          </div>
        </div>
      </template>

      <!-- Step 4: Step goal -->
      <template v-else-if="step === 4">
        <div class="space-y-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
              <Footprints class="h-5 w-5 text-emerald-600" />
            </div>
            <div>
              <h3 class="font-semibold">{{ $t("onboarding.stepGoalTitle") }}</h3>
              <p class="text-xs text-muted-foreground">{{ $t("onboarding.stepGoalDesc") }}</p>
            </div>
          </div>
          <div class="space-y-1.5">
            <Label>{{ $t("onboarding.stepGoalLabel") }}</Label>
            <Input
              v-model="stepGoalInput"
              type="number"
              min="1000"
              max="100000"
              step="500"
              class="rounded-xl"
            />
          </div>
          <div class="flex gap-2">
            <Button class="flex-1 rounded-xl" @click="saveStepGoal">
              {{ $t("common.next") }}
            </Button>
            <Button variant="ghost" class="rounded-xl" @click="step = 5">
              {{ $t("common.skip") }}
            </Button>
          </div>
        </div>
      </template>

      <!-- Step 5: Reminder -->
      <template v-else-if="step === 5">
        <div class="space-y-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
              <Bell class="h-5 w-5 text-amber-600" />
            </div>
            <div>
              <h3 class="font-semibold">{{ $t("onboarding.reminderTitle") }}</h3>
              <p class="text-xs text-muted-foreground">{{ $t("onboarding.reminderDesc") }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3 rounded-xl border p-3">
            <input
              id="ob-reminder"
              v-model="reminderEnabled"
              type="checkbox"
              class="h-4 w-4 rounded accent-primary"
            />
            <label for="ob-reminder" class="text-sm cursor-pointer flex-1">
              {{ $t("settings.dailyReminderLabel") }}
            </label>
            <div v-if="reminderEnabled" class="flex items-center gap-0.5">
              <Input
                v-model.number="reminderHour"
                type="number"
                min="0"
                max="23"
                class="w-16 rounded-xl text-center"
              />
              <span class="text-sm text-muted-foreground font-medium">:00</span>
            </div>
          </div>
          <div class="flex gap-2">
            <Button class="flex-1 rounded-xl" :disabled="reminderSaving" @click="saveReminder">
              {{ $t("common.next") }}
            </Button>
            <Button variant="ghost" class="rounded-xl" @click="step = 6">
              {{ $t("common.skip") }}
            </Button>
          </div>
        </div>
      </template>

      <!-- Step 5: Done -->
      <template v-else>
        <div class="text-center space-y-3">
          <div class="flex justify-center">
            <div class="h-16 w-16 rounded-2xl bg-green-100 flex items-center justify-center">
              <CheckCircle2 class="h-8 w-8 text-green-600" />
            </div>
          </div>
          <h2 class="text-xl font-bold">{{ $t("onboarding.doneTitle") }}</h2>
          <p class="text-sm text-muted-foreground">{{ $t("onboarding.doneDesc") }}</p>
        </div>
        <Button class="w-full rounded-xl" @click="finish">
          {{ $t("onboarding.doneBtn") }}
        </Button>
      </template>

    </div>
  </div>
</template>
