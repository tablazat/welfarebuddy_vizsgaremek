<script setup>
import { ref, computed, onMounted, watch } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import api from "@/lib/api"
import { getAxiosErrorMessage } from "@/lib/httpError"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Separator } from "@/components/ui/separator"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Users, Crown, Shield, UserRound, Search, ChevronLeft, ChevronRight,
  Trash2, Loader2, MailCheck, MailX, CalendarDays, Activity, ArrowLeft,
  Bell, BellRing, RefreshCw, Wifi, WifiOff, Database, Smartphone, FlaskConical,
  Clock, CheckCircle, XCircle, HeartPulse, Trash, Download,
} from "lucide-vue-next"
import { notifyImmediate, scheduleDailyReminder } from "@/composables/useLocalNotifications"
import { useHealthSync } from "@/composables/useHealthSync"
import { useEcho } from "@/composables/useEcho"
import { useOfflineSync } from "@/composables/useOfflineSync"
import { getQueue, clearQueue } from "@/lib/offlineQueue"
import { storageGet } from "@/lib/storage"

const router = useRouter()

const { t, locale } = useI18n()
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US")


const stats = ref(null)
const statsLoading = ref(true)


const users = ref([])
const usersLoading = ref(true)
const search = ref("")
const levelFilter = ref("")
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)


const actionLoading = ref(null)
const success = ref("")
const error = ref("")


const expandedUserId = ref(null)
const expandedUser = ref(null)
const expandedLoading = ref(false)


const deleteConfirmId = ref(null)

function clearMsg() { success.value = ""; error.value = "" }

async function loadStats() {
  try {
    const { data } = await api.get("/admin/stats")
    stats.value = data
  } catch {}
  statsLoading.value = false
}

async function loadUsers() {
  usersLoading.value = true
  try {
    const params = { page: currentPage.value }
    if (search.value) params.search = search.value
    if (levelFilter.value) params.level = levelFilter.value
    const { data } = await api.get("/admin/users", { params })
    users.value = data.data
    currentPage.value = data.current_page
    lastPage.value = data.last_page
    total.value = data.total
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  }
  usersLoading.value = false
}

async function toggleExpand(userId) {
  if (expandedUserId.value === userId) {
    expandedUserId.value = null
    expandedUser.value = null
    return
  }
  expandedUserId.value = userId
  expandedLoading.value = true
  try {
    const { data } = await api.get(`/admin/users/${userId}`)
    expandedUser.value = data.user
  } catch {}
  expandedLoading.value = false
}

async function changeLevel(userId, newLevel) {
  clearMsg()
  actionLoading.value = userId
  try {
    const { data } = await api.put(`/admin/users/${userId}/level`, { level_of_access: newLevel })

    const idx = users.value.findIndex(u => u.id === userId)
    if (idx !== -1) users.value[idx].level_of_access = data.user.level_of_access
    if (expandedUser.value?.id === userId) expandedUser.value.level_of_access = data.user.level_of_access
    success.value = t("admin.levelChanged", { level: newLevel })
    loadStats()
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  }
  actionLoading.value = null
}

async function deleteUser(userId) {
  clearMsg()
  actionLoading.value = userId
  try {
    await api.delete(`/admin/users/${userId}`)
    users.value = users.value.filter(u => u.id !== userId)
    expandedUserId.value = null
    expandedUser.value = null
    deleteConfirmId.value = null
    success.value = t("admin.userDeleted")
    loadStats()
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  }
  actionLoading.value = null
}

const exportUserId = ref(null)
async function exportUser(userId, format) {
  clearMsg()
  exportUserId.value = `${userId}-${format}`
  try {
    const response = await api.get(`/admin/users/${userId}/export/${format}`, { responseType: "blob" })
    const mime = format === "csv" ? "application/zip" : "application/json"
    const ext  = format === "csv" ? "zip" : "json"
    const url  = URL.createObjectURL(new Blob([response.data], { type: mime }))
    const a    = document.createElement("a")
    a.href     = url
    a.download = `welfarebuddy-user-${userId}-${new Date().toISOString().slice(0, 10)}.${ext}`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = getAxiosErrorMessage(e)
  } finally {
    exportUserId.value = null
  }
}

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadUsers()
  }, 400)
})

watch(levelFilter, () => {
  currentPage.value = 1
  loadUsers()
})

function prevPage() { if (currentPage.value > 1) { currentPage.value--; loadUsers() } }
function nextPage() { if (currentPage.value < lastPage.value) { currentPage.value++; loadUsers() } }

function formatDate(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleDateString(dateLocale.value, { year: "numeric", month: "short", day: "numeric" })
}

function levelBadge(level) {
  const map = {
    free: { label: t("upgrade.free"), class: "bg-muted text-muted-foreground" },
    pro: { label: "Pro", class: "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300" },
    admin: { label: "Admin", class: "bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300" },
  }
  return map[level] || map.free
}

onMounted(() => {
  loadStats()
  loadUsers()
  refreshDevTools()
})


const { syncAll, isSyncing, syncedCounts, lastSyncTime, syncError: syncErr } = useHealthSync()
const { connected: wsConnected } = useEcho()
const { isOnline, processQueue } = useOfflineSync()

const notifTitle   = ref("WelfareBuddy teszt")
const notifBody    = ref("Ez egy tesztértesítés")
const notifSent    = ref(false)
const reminderSent = ref(false)

const uploadedIdsCount = ref(0)
const offlineQueueCount = ref(0)
const platform  = ref(window.Capacitor?.getPlatform?.() || "web")
const isNative  = ref(window.Capacitor?.isNativePlatform?.() || false)
const apiBaseUrl = ref(import.meta.env.VITE_API_BASE_URL ?? "https://api.welfarebuddy.hu")


const pingMs      = ref(null)
const pingStatus  = ref(null)
async function pingApi() {
  pingStatus.value = "loading"
  const t0 = Date.now()
  try {
    await api.get("/token-check")
    pingMs.value = Date.now() - t0
    pingStatus.value = "ok"
  } catch {
    pingMs.value = Date.now() - t0
    pingStatus.value = "error"
  }
}


const authToken = computed(() => {
  const t = storageGet("auth_token")
  return t ? t.slice(0, 12) + "…" : "—"
})
const authUser = computed(() => {
  try { return JSON.parse(storageGet("auth_user") || "null") } catch { return null }
})


const lsEntries = computed(() => {
  const entries = []
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i)
    const v = localStorage.getItem(k) || ""
    entries.push({ key: k, size: Math.round(v.length / 1024 * 10) / 10 })
  }
  return entries.sort((a, b) => b.size - a.size)
})
const lsTotalKb = computed(() =>
  Math.round(lsEntries.value.reduce((s, e) => s + e.size, 0) * 10) / 10
)

function refreshDevTools() {
  try {
    const ids = JSON.parse(localStorage.getItem("health_uploaded_ids") || "[]")
    uploadedIdsCount.value = ids.length
  } catch { uploadedIdsCount.value = 0 }
  offlineQueueCount.value = getQueue().length
}

async function testNotif() {
  await notifyImmediate(notifTitle.value, notifBody.value)
  notifSent.value = true
  setTimeout(() => notifSent.value = false, 2000)
}

async function testDailyReminder() {
  const now = new Date()
  await scheduleDailyReminder(notifTitle.value, notifBody.value, now.getHours())
  reminderSent.value = true
  setTimeout(() => reminderSent.value = false, 2000)
}

async function testSync() {
  await syncAll()
  refreshDevTools()
}

function clearUploadedIds() {
  localStorage.removeItem("health_uploaded_ids")
  refreshDevTools()
}

function clearLastSyncTime() {
  localStorage.removeItem("health_last_sync")
  lastSyncTime.value = null
}

function clearOfflineQueue() {
  clearQueue()
  refreshDevTools()
}

async function runProcessQueue() {
  await processQueue()
  refreshDevTools()
}

function clearAllCache() {
  const toRemove = []
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i)
    if (k?.startsWith("cache_")) toRemove.push(k)
  }
  toRemove.forEach(k => localStorage.removeItem(k))
  refreshDevTools()
}

function formatTs(iso) {
  if (!iso) return "—"
  return new Date(iso).toLocaleString("hu-HU", { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" })
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
        <Shield class="h-5 w-5 text-purple-500" />
        {{ $t("admin.title") }}
      </h2>
      <p class="text-sm text-muted-foreground">{{ $t("admin.subtitle") }}</p>
    </div>

    
    <Alert v-if="success" class="rounded-xl border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/40 text-green-700 dark:text-green-300">
      <AlertDescription>{{ success }}</AlertDescription>
    </Alert>
    <Alert v-if="error" variant="destructive" class="rounded-xl">
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <template v-if="statsLoading"><Skeleton class="h-8 w-12 mx-auto" /><Skeleton class="h-3 w-16 mx-auto mt-2" /></template>
          <template v-else>
            <div class="text-2xl font-bold">{{ stats?.total_users ?? 0 }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ $t("admin.totalUsers") }}</div>
          </template>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <template v-if="statsLoading"><Skeleton class="h-8 w-12 mx-auto" /><Skeleton class="h-3 w-16 mx-auto mt-2" /></template>
          <template v-else>
            <div class="text-2xl font-bold text-muted-foreground">{{ stats?.free_users ?? 0 }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ $t("admin.freeUsers") }}</div>
          </template>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <template v-if="statsLoading"><Skeleton class="h-8 w-12 mx-auto" /><Skeleton class="h-3 w-16 mx-auto mt-2" /></template>
          <template v-else>
            <div class="text-2xl font-bold text-blue-500">{{ stats?.pro_users ?? 0 }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ $t("admin.proUsers") }}</div>
          </template>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <template v-if="statsLoading"><Skeleton class="h-8 w-12 mx-auto" /><Skeleton class="h-3 w-16 mx-auto mt-2" /></template>
          <template v-else>
            <div class="text-2xl font-bold text-purple-500">{{ stats?.admin_users ?? 0 }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ $t("admin.adminUsers") }}</div>
          </template>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <template v-if="statsLoading"><Skeleton class="h-8 w-12 mx-auto" /><Skeleton class="h-3 w-16 mx-auto mt-2" /></template>
          <template v-else>
            <div class="text-2xl font-bold text-green-500">{{ stats?.verified_users ?? 0 }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ $t("admin.verifiedUsers") }}</div>
          </template>
        </CardContent>
      </Card>
      <Card class="rounded-2xl">
        <CardContent class="p-4 text-center">
          <template v-if="statsLoading"><Skeleton class="h-8 w-12 mx-auto" /><Skeleton class="h-3 w-16 mx-auto mt-2" /></template>
          <template v-else>
            <div class="text-2xl font-bold text-orange-500">{{ stats?.recent_users ?? 0 }}</div>
            <div class="text-xs text-muted-foreground mt-1">{{ $t("admin.recentUsers") }}</div>
          </template>
        </CardContent>
      </Card>
    </div>

    
    <Card class="rounded-2xl">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <Users class="h-4 w-4" />
          {{ $t("admin.users") }}
          <span class="text-xs font-normal text-muted-foreground">({{ total }})</span>
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        
        <div class="flex flex-col sm:flex-row gap-2">
          <div class="relative flex-1">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input v-model="search" :placeholder="$t('admin.searchPlaceholder')" class="pl-9" />
          </div>
          <div class="flex gap-1.5">
            <Button
              v-for="opt in [
                { value: '', label: $t('admin.all') },
                { value: 'free', label: 'Free' },
                { value: 'pro', label: 'Pro' },
                { value: 'admin', label: 'Admin' },
              ]"
              :key="opt.value"
              size="sm"
              :variant="levelFilter === opt.value ? 'default' : 'outline'"
              class="rounded-xl text-xs"
              @click="levelFilter = opt.value"
            >
              {{ opt.label }}
            </Button>
          </div>
        </div>

        
        <div v-if="usersLoading" class="space-y-2">
          <Skeleton v-for="i in 5" :key="i" class="h-14 w-full rounded-xl" />
        </div>

        <div v-else-if="users.length === 0" class="text-center py-8 text-sm text-muted-foreground">
          {{ $t("admin.noResults") }}
        </div>

        <div v-else class="space-y-1.5">
          <div
            v-for="u in users"
            :key="u.id"
            class="rounded-xl border transition"
          >
            
            <button
              type="button"
              class="w-full flex items-center gap-3 p-3 text-left hover:bg-accent/50 transition rounded-xl"
              @click="toggleExpand(u.id)"
            >
              <div class="h-9 w-9 rounded-full bg-muted flex items-center justify-center shrink-0 text-sm font-medium">
                {{ (u.name || '?').split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-medium text-sm truncate">{{ u.name }}</span>
                  <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full shrink-0" :class="levelBadge(u.level_of_access).class">
                    {{ levelBadge(u.level_of_access).label }}
                  </span>
                  <MailCheck v-if="u.email_verified_at" class="h-3.5 w-3.5 text-green-500 shrink-0" />
                  <MailX v-else class="h-3.5 w-3.5 text-red-400 shrink-0" />
                </div>
                <div class="text-xs text-muted-foreground truncate">{{ u.email }}</div>
              </div>
              <div class="text-xs text-muted-foreground shrink-0 hidden sm:block">
                {{ formatDate(u.created_at) }}
              </div>
            </button>

            
            <div v-if="expandedUserId === u.id" class="border-t px-4 py-3 space-y-3 bg-muted/30">
              <template v-if="expandedLoading">
                <Skeleton class="h-5 w-48" />
                <Skeleton class="h-5 w-32" />
              </template>
              <template v-else-if="expandedUser">
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                  <div>
                    <div class="text-xs text-muted-foreground">{{ $t("admin.entries.heartRate") }}</div>
                    <div class="font-semibold">{{ expandedUser.heart_rates_count ?? 0 }}</div>
                  </div>
                  <div>
                    <div class="text-xs text-muted-foreground">{{ $t("admin.entries.bloodPressure") }}</div>
                    <div class="font-semibold">{{ expandedUser.blood_pressures_count ?? 0 }}</div>
                  </div>
                  <div>
                    <div class="text-xs text-muted-foreground">{{ $t("admin.entries.weight") }}</div>
                    <div class="font-semibold">{{ expandedUser.weights_count ?? 0 }}</div>
                  </div>
                  <div>
                    <div class="text-xs text-muted-foreground">{{ $t("admin.entries.exercises") }}</div>
                    <div class="font-semibold">{{ expandedUser.activity_users_count ?? 0 }}</div>
                  </div>
                </div>

                <div class="text-xs text-muted-foreground">
                  {{ $t("admin.streak") }}: <span class="font-medium text-foreground">{{ expandedUser.streak?.days ?? 0 }} {{ $t("admin.streakDays") }}</span>
                  &nbsp;·&nbsp;
                  {{ $t("admin.registration") }}: <span class="font-medium text-foreground">{{ formatDate(expandedUser.created_at) }}</span>
                </div>

                <Separator />

                
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-xs text-muted-foreground">{{ $t("admin.exportUser") }}:</span>
                  <Button
                    size="sm"
                    variant="outline"
                    class="rounded-xl text-xs h-7 px-3 gap-1"
                    :disabled="exportUserId === `${u.id}-json`"
                    @click.stop="exportUser(u.id, 'json')"
                  >
                    <Loader2 v-if="exportUserId === `${u.id}-json`" class="h-3 w-3 animate-spin" />
                    <Download v-else class="h-3 w-3" />
                    JSON
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    class="rounded-xl text-xs h-7 px-3 gap-1"
                    :disabled="exportUserId === `${u.id}-csv`"
                    @click.stop="exportUser(u.id, 'csv')"
                  >
                    <Loader2 v-if="exportUserId === `${u.id}-csv`" class="h-3 w-3 animate-spin" />
                    <Download v-else class="h-3 w-3" />
                    CSV (ZIP)
                  </Button>
                </div>

                <Separator />

                
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-xs text-muted-foreground">{{ $t("admin.level") }}:</span>
                  <Button
                    v-for="lvl in ['free', 'pro', 'admin']"
                    :key="lvl"
                    size="sm"
                    :variant="u.level_of_access === lvl ? 'default' : 'outline'"
                    class="rounded-xl text-xs h-7 px-3"
                    :class="lvl === 'admin' ? 'border-purple-300 dark:border-purple-700' : lvl === 'pro' ? 'border-blue-300 dark:border-blue-700' : ''"
                    :disabled="actionLoading === u.id || u.level_of_access === lvl"
                    @click="changeLevel(u.id, lvl)"
                  >
                    <Loader2 v-if="actionLoading === u.id" class="h-3 w-3 animate-spin mr-1" />
                    {{ lvl === 'free' ? $t('upgrade.free') : lvl === 'pro' ? 'Pro' : 'Admin' }}
                  </Button>

                  <div class="flex-1" />

                  
                  <template v-if="deleteConfirmId !== u.id">
                    <Button
                      variant="ghost"
                      size="sm"
                      class="text-destructive h-7 px-2 text-xs gap-1"
                      @click.stop="deleteConfirmId = u.id"
                    >
                      <Trash2 class="h-3 w-3" />
                      {{ $t("common.delete") }}
                    </Button>
                  </template>
                  <template v-else>
                    <Button
                      variant="destructive"
                      size="sm"
                      class="h-7 px-3 text-xs gap-1"
                      :disabled="actionLoading === u.id"
                      @click.stop="deleteUser(u.id)"
                    >
                      <Loader2 v-if="actionLoading === u.id" class="h-3 w-3 animate-spin" />
                      {{ $t("admin.confirmDelete") }}
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      class="h-7 px-2 text-xs"
                      @click.stop="deleteConfirmId = null"
                    >
                      {{ $t("common.cancel") }}
                    </Button>
                  </template>
                </div>
              </template>
            </div>
          </div>
        </div>

        
        <div v-if="lastPage > 1" class="flex items-center justify-between pt-2">
          <Button variant="outline" size="sm" class="rounded-xl gap-1" :disabled="currentPage <= 1" @click="prevPage">
            <ChevronLeft class="h-4 w-4" />
            {{ $t("common.previous") }}
          </Button>
          <span class="text-xs text-muted-foreground">{{ currentPage }} / {{ lastPage }}</span>
          <Button variant="outline" size="sm" class="rounded-xl gap-1" :disabled="currentPage >= lastPage" @click="nextPage">
            {{ $t("common.next") }}
            <ChevronRight class="h-4 w-4" />
          </Button>
        </div>
      </CardContent>
    </Card>
    
    <Card class="rounded-2xl border-dashed border-yellow-500/40">
      <CardHeader class="pb-3">
        <CardTitle class="text-base flex items-center gap-2">
          <FlaskConical class="h-4 w-4 text-yellow-500" />
          Dev Tools
        </CardTitle>
        <CardDescription>Funkciók tesztelése fejlesztéshez</CardDescription>
      </CardHeader>
      <CardContent class="space-y-5">

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <Smartphone class="h-3.5 w-3.5" /> Eszköz & Hálózat
          </div>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div class="rounded-xl bg-muted/50 px-3 py-2 flex items-center justify-between">
              <span class="text-muted-foreground text-xs">Platform</span>
              <span class="font-mono font-semibold text-xs">{{ platform }}</span>
            </div>
            <div class="rounded-xl bg-muted/50 px-3 py-2 flex items-center justify-between">
              <span class="text-muted-foreground text-xs">Natív</span>
              <span class="font-semibold text-xs" :class="isNative ? 'text-green-500' : 'text-muted-foreground'">
                {{ isNative ? 'igen' : 'nem' }}
              </span>
            </div>
            <div class="rounded-xl bg-muted/50 px-3 py-2 flex items-center justify-between">
              <span class="text-muted-foreground text-xs">Hálózat</span>
              <span class="flex items-center gap-1 text-xs font-semibold" :class="isOnline ? 'text-green-500' : 'text-red-400'">
                <Wifi v-if="isOnline" class="h-3.5 w-3.5" />
                <WifiOff v-else class="h-3.5 w-3.5" />
                {{ isOnline ? 'Online' : 'Offline' }}
              </span>
            </div>
            <div class="rounded-xl bg-muted/50 px-3 py-2 flex items-center justify-between">
              <span class="text-muted-foreground text-xs">WebSocket</span>
              <span class="flex items-center gap-1 text-xs font-semibold" :class="wsConnected ? 'text-green-500' : 'text-red-400'">
                <Wifi v-if="wsConnected" class="h-3.5 w-3.5" />
                <WifiOff v-else class="h-3.5 w-3.5" />
                {{ wsConnected ? 'OK' : 'Nincs' }}
              </span>
            </div>
          </div>
        </div>

        <Separator />

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <Activity class="h-3.5 w-3.5" /> Backend API
          </div>
          <div class="rounded-xl bg-muted/50 px-3 py-2 text-xs font-mono text-muted-foreground mb-2 truncate">
            {{ apiBaseUrl }}
          </div>
          <div class="flex items-center gap-2">
            <Button size="sm" variant="outline" class="rounded-xl gap-1.5 flex-1" :disabled="pingStatus === 'loading'" @click="pingApi">
              <Loader2 v-if="pingStatus === 'loading'" class="h-3.5 w-3.5 animate-spin" />
              <CheckCircle v-else-if="pingStatus === 'ok'" class="h-3.5 w-3.5 text-green-500" />
              <XCircle v-else-if="pingStatus === 'error'" class="h-3.5 w-3.5 text-red-400" />
              <Activity v-else class="h-3.5 w-3.5" />
              Ping
            </Button>
            <div v-if="pingMs !== null" class="text-xs font-mono px-3 py-2 rounded-xl border"
              :class="pingStatus === 'ok' ? 'text-green-500 border-green-500/30' : 'text-red-400 border-red-400/30'">
              {{ pingStatus === 'ok' ? '✓' : '✗' }} {{ pingMs }}ms
            </div>
          </div>
        </div>

        <Separator />

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <Shield class="h-3.5 w-3.5" /> Auth
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div class="rounded-xl bg-muted/50 px-3 py-2">
              <div class="text-muted-foreground text-[10px] mb-0.5">Token (prefix)</div>
              <div class="font-mono text-xs font-semibold truncate">{{ authToken }}</div>
            </div>
            <div class="rounded-xl bg-muted/50 px-3 py-2">
              <div class="text-muted-foreground text-[10px] mb-0.5">User ID</div>
              <div class="font-mono text-xs font-semibold">{{ authUser?.id ?? '—' }}</div>
            </div>
            <div class="rounded-xl bg-muted/50 px-3 py-2">
              <div class="text-muted-foreground text-[10px] mb-0.5">Level</div>
              <div class="font-mono text-xs font-semibold">{{ authUser?.level_of_access ?? '—' }}</div>
            </div>
            <div class="rounded-xl bg-muted/50 px-3 py-2">
              <div class="text-muted-foreground text-[10px] mb-0.5">Verified</div>
              <div class="font-mono text-xs font-semibold" :class="authUser?.email_verified_at ? 'text-green-500' : 'text-red-400'">
                {{ authUser?.email_verified_at ? 'igen' : 'nem' }}
              </div>
            </div>
          </div>
        </div>

        <Separator />

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <Bell class="h-3.5 w-3.5" /> Push Notification
          </div>
          <div class="space-y-2">
            <Input v-model="notifTitle" placeholder="Értesítés cím" class="text-sm h-9" />
            <Input v-model="notifBody" placeholder="Értesítés szöveg" class="text-sm h-9" />
            <div class="flex gap-2">
              <Button size="sm" class="rounded-xl gap-1.5 flex-1" @click="testNotif">
                <CheckCircle v-if="notifSent" class="h-3.5 w-3.5 text-green-400" />
                <BellRing v-else class="h-3.5 w-3.5" />
                {{ notifSent ? 'Elküldve!' : 'Azonnali notif' }}
              </Button>
              <Button size="sm" variant="outline" class="rounded-xl gap-1.5 flex-1" @click="testDailyReminder">
                <CheckCircle v-if="reminderSent" class="h-3.5 w-3.5 text-green-400" />
                <Clock v-else class="h-3.5 w-3.5" />
                {{ reminderSent ? 'Beállítva!' : 'Napi emlékeztető' }}
              </Button>
            </div>
          </div>
        </div>

        <Separator />

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <HeartPulse class="h-3.5 w-3.5" /> Health Sync
          </div>
          <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div class="rounded-xl bg-muted/50 px-3 py-2">
                <div class="text-muted-foreground mb-0.5">Utolsó sync</div>
                <div class="font-mono font-semibold">{{ formatTs(lastSyncTime) }}</div>
              </div>
              <div class="rounded-xl bg-muted/50 px-3 py-2">
                <div class="text-muted-foreground mb-0.5">Feltöltött</div>
                <div class="font-mono font-semibold">
                  <span v-if="syncedCounts">
                    HR:{{ syncedCounts.heartRate }}
                    BP:{{ syncedCounts.bloodPressure }}
                    W:{{ syncedCounts.weight }}
                    S:{{ syncedCounts.steps }}
                  </span>
                  <span v-else>—</span>
                </div>
              </div>
            </div>
            <div v-if="syncErr" class="text-xs text-destructive flex items-start gap-1.5">
              <XCircle class="h-3.5 w-3.5 shrink-0 mt-0.5" />
              {{ syncErr }}
            </div>
            <div class="flex gap-2">
              <Button size="sm" class="rounded-xl gap-1.5 flex-1" :disabled="isSyncing" @click="testSync">
                <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isSyncing }" />
                {{ isSyncing ? 'Szinkron...' : 'Sync indítása' }}
              </Button>
              <Button size="sm" variant="outline" class="rounded-xl gap-1.5" @click="clearLastSyncTime">
                <Clock class="h-3.5 w-3.5" />
                Reset idő
              </Button>
            </div>
          </div>
        </div>

        <Separator />

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <Database class="h-3.5 w-3.5" /> Offline Queue
          </div>
          <div class="rounded-xl bg-muted/50 px-3 py-2 flex items-center justify-between mb-2">
            <span class="text-xs text-muted-foreground">Várakozó kérések</span>
            <span class="font-mono text-xs font-semibold" :class="offlineQueueCount > 0 ? 'text-yellow-500' : ''">
              {{ offlineQueueCount }}
            </span>
          </div>
          <div class="flex gap-2">
            <Button size="sm" variant="outline" class="rounded-xl gap-1.5 flex-1" :disabled="offlineQueueCount === 0" @click="runProcessQueue">
              <RefreshCw class="h-3.5 w-3.5" />
              Feldolgozás
            </Button>
            <Button size="sm" variant="outline" class="rounded-xl gap-1.5 text-destructive border-destructive/30" :disabled="offlineQueueCount === 0" @click="clearOfflineQueue">
              <Trash class="h-3.5 w-3.5" />
              Törlés
            </Button>
          </div>
        </div>

        <Separator />

        
        <div>
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <Database class="h-3.5 w-3.5" /> LocalStorage
            <span class="ml-auto text-[10px] font-normal normal-case">{{ lsTotalKb }} KB összesen</span>
          </div>
          <div class="rounded-xl border overflow-hidden text-[10px] font-mono max-h-40 overflow-y-auto">
            <div
              v-for="entry in lsEntries"
              :key="entry.key"
              class="flex items-center justify-between px-2.5 py-1 border-b last:border-b-0 hover:bg-muted/50"
            >
              <span class="truncate text-muted-foreground max-w-[70%]">{{ entry.key }}</span>
              <span class="shrink-0 text-right" :class="entry.size > 10 ? 'text-yellow-500' : ''">{{ entry.size }} KB</span>
            </div>
            <div v-if="lsEntries.length === 0" class="px-2.5 py-2 text-muted-foreground">Üres</div>
          </div>
          <div class="flex gap-2 mt-2">
            <Button size="sm" variant="outline" class="rounded-xl gap-1.5 text-destructive border-destructive/30 flex-1" @click="clearUploadedIds">
              <Trash class="h-3.5 w-3.5" />
              Uploaded IDs ({{ uploadedIdsCount }})
            </Button>
            <Button size="sm" variant="outline" class="rounded-xl gap-1.5 text-destructive border-destructive/30 flex-1" @click="clearAllCache">
              <Trash class="h-3.5 w-3.5" />
              Cache törlése
            </Button>
          </div>
        </div>

      </CardContent>
    </Card>

  </div>
</template>
