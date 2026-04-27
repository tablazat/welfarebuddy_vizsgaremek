<script setup>
import { computed, nextTick, provide, ref, watch, onMounted, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { DateFormatter, getLocalTimeZone, today } from "@internationalized/date";

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarRail,
  SidebarTrigger,
} from "@/components/ui/sidebar";

import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

import {
  BarChart3,
  CalendarIcon,
  Crown,
  Dumbbell,
  Flame,
  Home,
  LogOut,
  NotebookPen,
  Plus,
  Search,
  Paintbrush,
  Settings,
  Shield,
  UserRound,
  X,
  Wifi,
  WifiOff,
} from "lucide-vue-next";

import { useAccess } from "@/composables/useAccess";
import { useSkins } from "@/composables/useSkins";
import { useHealthSync } from "@/composables/useHealthSync";

import { isOnline, initNetworkListener } from "@/composables/useOfflineSync";
import { getQueue } from "@/lib/offlineQueue";

import api from "@/lib/api";
import { storageGet, storageSet, storageRemove } from "@/lib/storage";
import { useEcho } from "@/composables/useEcho";
import { invalidateCacheForWsEvent } from "@/lib/offlineCache";
import OnboardingModal from "@/components/OnboardingModal.vue";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US");


const { subscribe, disconnect, connected: wsConnected } = useEcho();

const tz = getLocalTimeZone();
const date = ref(today(tz));
const df = computed(() => new DateFormatter(dateLocale.value, { dateStyle: "long" }));

const query = ref("");
const wsEvent = ref(null);
provide("globalQuery", query);
provide("selectedDate", date);
provide("wsEvent", wsEvent);

const searchOpen = ref(false);
const searchInputEl = ref(null);

const openSearch = async () => {
  searchOpen.value = true;
  await nextTick();
  searchInputEl.value?.focus();
};

const closeSearch = () => {
  searchOpen.value = false;
};

const nav = computed(() => [
  { to: "/app", label: t("nav.dashboard"), icon: Home },
  { to: "/app/diary", label: t("nav.diary"), icon: NotebookPen },
  { to: "/app/stats", label: t("nav.stats"), icon: BarChart3 },
  { to: "/app/exercise", label: t("nav.exercise"), icon: Dumbbell },
  { to: "/app/summary", label: t("nav.summary"), icon: Flame },
  { to: "/app/skins", label: t("nav.skins"), icon: Paintbrush },
]);

const isActive = (to) => {
  if (to === "/app") return route.path === "/app";
  return route.path === to || route.path.startsWith(to + "/");
};

const pageTitle = computed(() => {
  const found = nav.value.find((x) => isActive(x.to));
  if (found) return found.label;
  if (isActive("/app/profile")) return t("nav.profile");
  if (isActive("/app/settings")) return t("nav.settings");
  if (isActive("/app/exercise")) return t("nav.exercise");
  if (isActive("/app/admin")) return t("nav.admin");
  if (isActive("/app/upgrade")) return t("upgrade.title");
  if (isActive("/app/skins")) return t("skins.title");
  return t("nav.appName");
});

const goNewEntry = () => {
  router.push("/app/diary");
};

const sidebarOpen = ref(true);
const profileOpen = ref(false);


const authUser = ref(JSON.parse(storageGet("auth_user") || "null"));
const userName = computed(() => authUser.value?.display_name || authUser.value?.name || "Felhasználó");
const userEmail = computed(() => authUser.value?.email || "");
const userInitials = computed(() => {
  const name = authUser.value?.name || "?";
  return name.split(" ").map(p => p[0]).join("").toUpperCase().slice(0, 2);
});
const userAvatar = computed(() => authUser.value?.profile_picture_url || null);
const { isPro, isAdmin, level } = useAccess(authUser);
const skinSystem = useSkins(authUser);
provide("skinSystem", skinSystem);
const tierBadge = computed(() => {
  if (isAdmin.value) return { label: "Admin", class: "bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300" };
  if (isPro.value) return { label: "Pro", class: "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300" };
  return null;
});


const showOnboarding = ref(false)


const { init: healthInit, syncAll, forceSync, lastSyncTime } = useHealthSync();


let cleanupNetwork = null;

onMounted(async () => {
  try {
    const { data } = await api.get("/token-check");
    authUser.value = data.user;
    storageSet("auth_user", JSON.stringify(data.user));

    
    if (data.user?.height_cm) {
      storageSet("onboarding_done", "1");
      showOnboarding.value = false;
    } else if (!storageGet("onboarding_done")) {
      showOnboarding.value = true;
    }
    if (data.user?.step_goal_daily) storageSet("step_goal", String(data.user.step_goal_daily));
    if (data.user?.water_goal_ml) storageSet("water_goal_ml", String(data.user.water_goal_ml));

    if (data.user?.id) {
      subscribe(data.user.id, (type, eventData) => {
        invalidateCacheForWsEvent(type);
        wsEvent.value = { type, data: eventData, _ts: Date.now() };
      });
    }
  } catch {}

  
  cleanupNetwork = initNetworkListener();
  refreshQueueCount();

  
  healthInit().then(() => {
    if (!lastSyncTime.value) {
      forceSync().catch(() => {});
    } else {
      syncAll().catch(() => {});
    }
  });

  window.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  if (cleanupNetwork) cleanupNetwork();
});

watch(sidebarOpen, (v) => {
  if (!v) profileOpen.value = false;
});

const closeOverlays = () => {
  profileOpen.value = false;
  searchOpen.value = false;
};


const pendingQueueCount = ref(0)
function refreshQueueCount() {
  try { pendingQueueCount.value = getQueue().length } catch { pendingQueueCount.value = 0 }
}
watch(isOnline, refreshQueueCount)

const onKeydown = (e) => {
  if (e.key === "Escape") closeOverlays();
};

const logout = async () => {
  disconnect();
  try {
    await api.post("/logout");
  } catch {}
  storageRemove("auth_token");
  storageRemove("auth_user");
  router.replace("/");
};
</script>

<template>
  
  <OnboardingModal v-if="showOnboarding" @done="showOnboarding = false" />

  
  <div class="h-screen w-screen bg-muted/40 p-2 sm:p-3 md:p-4">
    <div class="h-full w-full overflow-hidden rounded-2xl border bg-background shadow-sm relative">
      <!-- Skin animated background layer -->
      <div class="skin-bg" aria-hidden="true">
        <span v-for="i in 20" :key="i" />
      </div>
      <div class="skin-fg" aria-hidden="true">
        <span v-for="i in 10" :key="'fg'+i" />
      </div>
      <SidebarProvider v-model:open="sidebarOpen" class="!min-h-0 h-full relative z-[1]">
        <Sidebar>
          <SidebarHeader>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton size="xl">
                  <div class="grid flex-1 text-left text-sm leading-tight">
                    <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-7 shrink-0" />
                    <span class="truncate font-semibold text-sidebar-primary">{{ $t("nav.appName") }}</span>
                  </div>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarHeader>

          <SidebarContent>
            <div class="px-2 pt-2">
              <Button class="w-full rounded-xl justify-start gap-2" @click="goNewEntry">
                <Plus class="h-4 w-4" />
                {{ $t("nav.newEntry") }}
              </Button>
            </div>

            <SidebarGroup>
              <SidebarGroupLabel>{{ $t("nav.navigation") }}</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  <SidebarMenuItem v-for="item in nav" :key="item.to">
                    <SidebarMenuButton as-child>
                      <RouterLink
                        :to="item.to"
                        class="relative rounded-xl px-3 flex items-center gap-3"
                        :class="isActive(item.to) ? 'bg-sidebar-accent' : ''"
                        @click="closeOverlays"
                      >
                        <span
                          class="absolute left-1 top-1/2 -translate-y-1/2 h-6 w-1 rounded-full bg-sidebar-primary"
                          v-if="isActive(item.to)"
                        />
                        <span class="relative">
                          <component :is="item.icon" />
                          <span
                            v-if="item.to === '/app/diary' && pendingQueueCount > 0"
                            class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-yellow-500"
                          />
                        </span>
                        <span>{{ item.label }}</span>
                      </RouterLink>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup v-if="isAdmin">
              <SidebarGroupLabel>{{ $t("nav.administration") }}</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                      <RouterLink
                        to="/app/admin"
                        class="relative rounded-xl px-3 flex items-center gap-3"
                        :class="isActive('/app/admin') ? 'bg-sidebar-accent' : ''"
                        @click="closeOverlays"
                      >
                        <span
                          class="absolute left-1 top-1/2 -translate-y-1/2 h-6 w-1 rounded-full bg-purple-500"
                          v-if="isActive('/app/admin')"
                        />
                        <Shield class="h-4 w-4 text-purple-500" />
                        <span>{{ $t("nav.admin") }}</span>
                      </RouterLink>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
              <SidebarGroupLabel>{{ $t("nav.account") }}</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                      <RouterLink
                        to="/app/settings"
                        class="relative rounded-xl px-3 flex items-center gap-3"
                        :class="isActive('/app/settings') ? 'bg-sidebar-accent' : ''"
                        @click="closeOverlays"
                      >
                        <span
                          class="absolute left-1 top-1/2 -translate-y-1/2 h-6 w-1 rounded-full bg-sidebar-primary"
                          v-if="isActive('/app/settings')"
                        />
                        <Settings />
                        <span>{{ $t("nav.settings") }}</span>
                      </RouterLink>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>

          <SidebarFooter>
            <div class="p-2">
              <DropdownMenu v-model:open="profileOpen">
                <DropdownMenuTrigger as-child>
                  <button
                    type="button"
                    class="w-full flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-sidebar-accent transition text-left"
                  >
                    <Avatar class="h-9 w-9">
                      <AvatarImage v-if="userAvatar" :src="userAvatar" />
                      <AvatarFallback>{{ userInitials }}</AvatarFallback>
                    </Avatar>
                    <div class="flex flex-col text-sm leading-tight min-w-0">
                      <div class="flex items-center gap-1.5">
                        <span class="font-medium truncate">{{ userName }}</span>
                        <span v-if="tierBadge" class="text-[10px] font-medium px-1.5 py-0.5 rounded-full shrink-0" :class="tierBadge.class">
                          {{ tierBadge.label }}
                        </span>
                      </div>
                      <span class="text-xs text-muted-foreground truncate">{{ userEmail }}</span>
                    </div>
                  </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" class="w-56">
                  <DropdownMenuLabel>{{ $t("nav.account") }}</DropdownMenuLabel>
                  <DropdownMenuSeparator />

                  <DropdownMenuItem as-child>
                    <RouterLink to="/app/profile" class="flex items-center gap-2" @click="closeOverlays">
                      <UserRound class="h-4 w-4" />
                      {{ $t("nav.profile") }}
                    </RouterLink>
                  </DropdownMenuItem>

                  <DropdownMenuItem as-child>
                    <RouterLink to="/app/settings" class="flex items-center gap-2" @click="closeOverlays">
                      <Settings class="h-4 w-4" />
                      {{ $t("nav.settings") }}
                    </RouterLink>
                  </DropdownMenuItem>

                  <DropdownMenuSeparator />

                  <DropdownMenuItem class="text-destructive flex items-center gap-2" @click="logout">
                    <LogOut class="h-4 w-4" />
                    {{ $t("auth.logout") }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </SidebarFooter>

          <SidebarRail />
        </Sidebar>

        <SidebarInset class="overflow-hidden flex flex-col">
          
          <div
            v-if="!isOnline"
            class="flex items-center gap-2 px-4 py-1.5 text-xs bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border-b border-yellow-500/20"
          >
            <WifiOff class="w-3.5 h-3.5 shrink-0" />
            <span>{{ $t("offline.banner") }}</span>
          </div>
          <div
            v-else-if="!wsConnected"
            class="flex items-center gap-2 px-4 py-1 text-xs bg-blue-500/10 text-blue-600 dark:text-blue-400 border-b border-blue-500/20"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse shrink-0" />
            <span>{{ $t("offline.wsDisconnected") }}</span>
          </div>

          <header
            class="flex h-14 shrink-0 items-center justify-between gap-2 border-b px-3 sm:px-4 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60"
          >
            <div class="flex items-center gap-2 min-w-0">
              <SidebarTrigger class="-ml-1" />
              <div class="font-medium truncate">{{ pageTitle }}</div>
              
              <span
                class="w-2 h-2 rounded-full shrink-0"
                :class="!isOnline ? 'bg-red-500' : wsConnected ? 'bg-green-500' : 'bg-yellow-500 animate-pulse'"
                :title="!isOnline ? $t('offline.banner') : wsConnected ? $t('status.live') : $t('offline.wsDisconnected')"
                :aria-label="!isOnline ? $t('offline.banner') : wsConnected ? $t('status.live') : $t('offline.wsDisconnected')"
              />
            </div>

            <div class="flex items-center gap-2 min-w-0">
              <Button
                variant="outline"
                disabled
                :class="cn('h-10 rounded-xl justify-start text-left font-normal gap-2 px-3 cursor-default', 'w-[170px] sm:w-[220px] md:w-[240px]')"
              >
                <CalendarIcon class="h-4 w-4" />
                {{ df.format(date.toDate(tz)) }}
              </Button>
            </div>
          </header>

          <div class="flex-1 min-h-0 overflow-y-auto">
            <div class="p-3 sm:p-4 md:p-5">
              <div class="max-w-6xl mx-auto w-full">
                <div class="rounded-2xl border bg-background/60 p-3 sm:p-4">
                  <RouterView />
                </div>
              </div>
            </div>
          </div>

          
          <div v-if="searchOpen" class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40" @click="closeSearch" />
            <div
              class="absolute left-1/2 top-4 w-[calc(100%-1rem)] -translate-x-1/2 max-w-lg rounded-2xl border bg-background shadow-lg"
            >
              <div class="flex items-center gap-2 p-3 border-b">
                <div class="relative flex-1">
                  <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <input
                    ref="searchInputEl"
                    v-model="query"
                    type="text"
                    :placeholder="$t('common.search')"
                    class="h-10 w-full rounded-xl border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                  />
                </div>
                <Button variant="ghost" class="h-10 w-10 rounded-xl p-0" @click="closeSearch">
                  <X class="h-5 w-5" />
                </Button>
              </div>
              <div class="p-3 text-sm text-muted-foreground">
                {{ $t("common.searchHint") }}
              </div>
            </div>
          </div>
        </SidebarInset>
      </SidebarProvider>
    </div>
  </div>
</template>
