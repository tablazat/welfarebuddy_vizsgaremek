<script setup>
import { ref, computed, inject, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import { storageGet, storageSet } from "@/lib/storage"
import { useAccess } from "@/composables/useAccess"
import api from "@/lib/api"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Lock, Check, Crown, Flame, Trophy, Calendar, ArrowLeft, Palette } from "lucide-vue-next"
import { hapticLight } from "@/lib/haptics"

const { t } = useI18n()
const router = useRouter()
const authUser = ref(JSON.parse(storageGet("auth_user") || "null"))
const { isPro, isAdmin } = useAccess(authUser)
const { skins, activeSkinId, isUnlocked, applySkin } = inject("skinSystem") ?? { skins: ref([]), activeSkinId: ref(null), isUnlocked: () => false, applySkin: () => {} }

// A streak nincs az `auth_user` localStorage-ban (csak `user`), de kell az
// unlock check-hez. Friss /token-check ad mind user-t (max_days), mind streak-et.
const userWithStreak = computed(() => ({
  ...authUser.value,
  streak: streakData.value,
}))
const streakData = ref(null)
onMounted(async () => {
  try {
    const { data } = await api.get("/token-check")
    if (data?.user) {
      authUser.value = data.user
      storageSet("auth_user", JSON.stringify(data.user))
    }
    if (data?.streak) streakData.value = data.streak
  } catch {}
})

// Csoportositas kategoria szerint
const groupedSkins = computed(() => {
  const groups = [
    { key: "free", label: t("skins.categoryFree"), icon: Palette },
    { key: "streak", label: t("skins.categoryStreak"), icon: Flame },
    { key: "pro", label: t("skins.categoryPro"), icon: Crown },
    { key: "milestone", label: t("skins.categoryMilestone"), icon: Trophy },
  ]
  return groups
    .map((g) => ({
      ...g,
      skins: skins.filter((s) => s.category === g.key),
    }))
    .filter((g) => g.skins.length > 0)
})

// Kategoria badge szin
function categoryClass(cat) {
  const map = {
    free: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300",
    pro: "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
    streak: "bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300",
    milestone: "bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300",
  }
  return map[cat] || ""
}

function categoryIcon(cat) {
  const map = { free: Palette, pro: Crown, streak: Flame, milestone: Trophy }
  return map[cat] || Palette
}

function getUnlockText(skin) {
  const u = skin.unlock
  if (u.type === "free") return t("skins.unlockFree")
  if (u.type === "pro") return t("skins.unlockPro")
  if (u.type === "streak") return t("skins.unlockStreak", { days: u.days })
  if (u.type === "entries") return t("skins.unlockEntries", { count: u.count })
  if (u.type === "accountAge") return t("skins.unlockAccountAge", { days: u.days })
  if (u.type === "proStreak") return t("skins.unlockProStreak", { days: u.days })
  return ""
}

function selectSkin(skin) {
  if (isUnlocked(skin, userWithStreak.value)) {
    applySkin(skin.id)
    hapticLight()
  }
}

// Szinek a previewhoz (light mode szinek)
function getPreviewColors(skin) {
  const l = skin.light
  return [l.primary, l.accent, l.ring, l.secondary].filter(Boolean)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Fejlec -->
    <div>
      <Button
        variant="ghost"
        size="sm"
        class="rounded-xl gap-1.5 text-muted-foreground mb-2"
        @click="router.push('/app/settings')"
      >
        <ArrowLeft class="h-3.5 w-3.5" />
        {{ $t("upgrade.backToSettings") }}
      </Button>
      <h2 class="text-lg font-semibold flex items-center gap-2">
        <Palette class="h-5 w-5 text-primary" />
        {{ $t("skins.title") }}
      </h2>
      <p class="text-sm text-muted-foreground">{{ $t("skins.subtitle") }}</p>
    </div>

    <!-- Aktiv skin jelzes -->
    <Alert
      v-if="activeSkinId"
      variant="default"
      class="rounded-xl border-primary/20 bg-primary/5"
    >
      <AlertDescription class="flex items-center gap-2 text-sm">
        <Check class="h-4 w-4 text-primary shrink-0" />
        <span>
          {{ $t("skins.activeSkin") }}:
          <strong>{{ $t("skins." + activeSkinId) }}</strong>
        </span>
      </AlertDescription>
    </Alert>

    <!-- Csoportok -->
    <div v-for="group in groupedSkins" :key="group.key" class="space-y-3">
      <!-- Csoport fejlec -->
      <div class="flex items-center gap-2">
        <component :is="group.icon" class="h-4 w-4 text-muted-foreground" />
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
          {{ group.label }}
        </h3>
        <div class="flex-1 h-px bg-border" />
      </div>

      <!-- Skin kartyak grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <Card
          v-for="skin in group.skins"
          :key="skin.id"
          class="skin-preview-card rounded-2xl cursor-pointer transition-all duration-200 hover:scale-[1.02] hover:shadow-md relative overflow-hidden group"
          :class="{
            'ring-2 ring-primary shadow-lg': activeSkinId === skin.id,
            'opacity-50 grayscale-[30%] hover:opacity-70 hover:grayscale-0': !isUnlocked(skin, userWithStreak),
          }"
          @click="selectSkin(skin)"
        >
          <!-- Szin preview sav a tetején -->
          <div class="h-1.5 flex">
            <div
              v-for="(color, ci) in getPreviewColors(skin)"
              :key="ci"
              class="flex-1"
              :style="{ background: color }"
            />
          </div>

          <CardContent class="p-4 text-center space-y-2">
            <!-- Emoji -->
            <div class="text-3xl select-none transition-transform duration-200 group-hover:scale-110">
              {{ skin.icon }}
            </div>

            <!-- Nev -->
            <div class="font-semibold text-sm leading-tight">
              {{ $t("skins." + skin.id) }}
            </div>

            <!-- Kategoria badge -->
            <div class="flex justify-center">
              <span
                class="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full"
                :class="categoryClass(skin.category)"
              >
                <component :is="categoryIcon(skin.category)" class="h-2.5 w-2.5" />
                {{ group.label }}
              </span>
            </div>

            <!-- Aktiv badge VAGY lock info -->
            <div
              v-if="activeSkinId === skin.id"
              class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary bg-primary/10 px-2.5 py-1 rounded-full"
            >
              <Check class="h-3 w-3" />
              {{ $t("skins.active") }}
            </div>
            <div
              v-else-if="!isUnlocked(skin, userWithStreak)"
              class="inline-flex items-center gap-1 text-[11px] text-muted-foreground bg-muted/60 px-2.5 py-1 rounded-full"
            >
              <Lock class="h-3 w-3" />
              <span class="truncate max-w-[120px]">{{ getUnlockText(skin) }}</span>
            </div>
            <div v-else class="h-6" />

            <!-- Szin korok -->
            <div class="flex justify-center gap-1.5 pt-1">
              <div
                v-for="(color, ci) in getPreviewColors(skin).slice(0, 4)"
                :key="ci"
                class="w-4 h-4 rounded-full border border-black/10 dark:border-white/10 shadow-sm"
                :style="{ background: color }"
              />
            </div>
          </CardContent>

          <!-- Locked overlay -->
          <div
            v-if="!isUnlocked(skin, userWithStreak)"
            class="absolute inset-0 bg-background/10 pointer-events-none"
          />
        </Card>
      </div>
    </div>

    <!-- Ures allapot -->
    <div
      v-if="groupedSkins.length === 0"
      class="text-center py-12 text-muted-foreground text-sm"
    >
      {{ $t("skins.noSkins") }}
    </div>
  </div>
</template>
