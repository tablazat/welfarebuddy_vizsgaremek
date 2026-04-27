<script setup>
import { computed, nextTick, provide, ref, watch, onMounted, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { DateFormatter, getLocalTimeZone, today } from "@internationalized/date";

import {
  Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupContent,
  SidebarGroupLabel, SidebarHeader, SidebarInset, SidebarMenu, SidebarMenuButton,
  SidebarMenuItem, SidebarProvider, SidebarRail, SidebarTrigger,
} from "@/components/ui/sidebar";

import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

import {
  BarChart3, CalendarIcon, Crown, Dumbbell, Flame, HeartPulse,
  Home, LogOut, NotebookPen, Plus, Paintbrush, Settings, Shield,
  UserRound, WifiOff, X, MoreHorizontal,
} from "lucide-vue-next";

import OnboardingModal from "@/components/OnboardingModal.vue";
import { useAccess }       from "@/composables/useAccess";
import { useSkins }        from "@/composables/useSkins";
import { useOfflineSync }  from "@/composables/useOfflineSync";
import { useHealthSync }   from "@/composables/useHealthSync";
import { initLocalNotifications } from "@/composables/useLocalNotifications";

import api                              from "@/lib/api";
import { storageGet, storageSet, storageRemove } from "@/lib/storage";
import { invalidateCacheForWsEvent } from "@/lib/offlineCache";
import { getQueue } from "@/lib/offlineQueue";
import { useEcho }         from "@/composables/useEcho";
import { hapticMedium } from "@/lib/haptics";

const route  = useRoute();
const router = useRouter();
const { t, tm, locale } = useI18n();
const dateLocale = computed(() => ({ hu: "hu-HU", en: "en-US", de: "de-DE" })[locale.value] || "en-US");

const { subscribe, disconnect, connected: wsConnected } = useEcho();

const tz   = getLocalTimeZone();
const date = ref(today(tz));
const df   = computed(() => new DateFormatter(dateLocale.value, { dateStyle: "long" }));

const query   = ref("");
const wsEvent = ref(null);
provide("globalQuery",   query);
provide("selectedDate",  date);
provide("wsEvent",       wsEvent);


const refreshTrigger = ref(0)  
provide("refreshTrigger", refreshTrigger)
const isPulling    = ref(false)
const pullProgress = ref(0)    
const scrollRef    = ref(null) 
const PULL_THRESHOLD = 70
const PULL_MAX       = 100
let _ptStartY = 0, _ptCurY = 0, _ptActive = false, _ptRefreshing = false

function ptTouchStart(e) {
  if (!scrollRef.value) return
  if (scrollRef.value.scrollTop > 0) return
  if (_ptRefreshing) return
  _ptStartY = e.touches[0].clientY
  _ptActive = true
}
function ptTouchMove(e) {
  if (!_ptActive || !scrollRef.value) return
  if (scrollRef.value.scrollTop > 0) { _ptActive = false; return }
  _ptCurY = e.touches[0].clientY
  const delta = _ptCurY - _ptStartY
  if (delta <= 0) { isPulling.value = false; pullProgress.value = 0; return }
  e.preventDefault()
  isPulling.value   = true
  pullProgress.value = Math.min(delta / PULL_MAX, 1)
}
async function ptTouchEnd() {
  if (!_ptActive || !isPulling.value) { _ptActive = false; return }
  _ptActive = false
  const delta = _ptCurY - _ptStartY
  if (delta >= PULL_THRESHOLD) {
    _ptRefreshing = true
    pullProgress.value = 1
    hapticMedium()
    refreshTrigger.value++
    await new Promise(r => setTimeout(r, 600))  
    _ptRefreshing = false
  }
  isPulling.value    = false
  pullProgress.value = 0
}


const nav = computed(() => [
  { to: "/app",            label: t("nav.dashboard"), icon: Home        },
  { to: "/app/diary",      label: t("nav.diary"),     icon: NotebookPen },
  { to: "/app/stats",      label: t("nav.stats"),     icon: BarChart3   },
  { to: "/app/exercise",   label: t("nav.exercise"),  icon: Dumbbell    },
  { to: "/app/skins",      label: t("nav.skins"),     icon: Paintbrush  },
]);


const bottomNav = computed(() => [
  { to: "/app",           label: t("nav.dashboard"), icon: Home        },
  { to: "/app/diary",     label: t("nav.diary"),     icon: NotebookPen },
  { to: "/app/stats",     label: t("nav.stats"),     icon: BarChart3   },
  { to: "/app/exercise",  label: t("nav.exercise"),  icon: Dumbbell    },
  { to: "/app/settings",  label: t("nav.settings"),  icon: Settings    },
]);

const isActive = (to) => {
  if (to === "/app") return route.path === "/app";
  return route.path === to || route.path.startsWith(to + "/");
};

const pageTitle = computed(() => {
  const found = nav.value.find((x) => isActive(x.to));
  if (found) return found.label;
  if (isActive("/app/profile"))     return t("nav.profile");
  if (isActive("/app/settings"))    return t("nav.settings");
  if (isActive("/app/admin"))       return t("nav.admin");
  if (isActive("/app/upgrade"))     return t("upgrade.title");
  return t("nav.appName");
});


const sidebarOpen  = ref(false); 
const profileOpen  = ref(false);






const showOnboarding = ref(false)


const authUser     = ref(JSON.parse(storageGet("auth_user") || "null"));
const userName     = computed(() => authUser.value?.display_name || authUser.value?.name || "Felhasználó");
const userEmail    = computed(() => authUser.value?.email || "");
const userInitials = computed(() => {
  const name = authUser.value?.name || "?";
  return name.split(" ").map(p => p[0]).join("").toUpperCase().slice(0, 2);
});
const userAvatar   = computed(() => authUser.value?.profile_picture_url || null);
const { isPro, isAdmin } = useAccess(authUser);
const skinSystem         = useSkins(authUser);
const { isOnline, processQueue, initNetworkListener } = useOfflineSync();
const { syncAll }        = useHealthSync();
provide("skinSystem", skinSystem);

const tierBadge = computed(() => {
  if (isAdmin.value) return { label: "Admin", class: "bg-purple-500/20 text-purple-400" };
  if (isPro.value)   return { label: "Pro",   class: "bg-primary/20 text-primary" };
  return null;
});

const closeOverlays = () => { profileOpen.value = false; };


const pendingQueueCount = ref(0)
function refreshQueueCount() {
  try { pendingQueueCount.value = getQueue().length } catch { pendingQueueCount.value = 0 }
}
watch(isOnline, refreshQueueCount)

const onKeydown = (e) => { if (e.key === "Escape") closeOverlays(); };


let _appStateListener = null;

async function setupAppLifecycle() {
  if (window.Capacitor?.isNativePlatform?.()) {
    try {
      const { App } = await import("@capacitor/app");
      _appStateListener = await App.addListener("appStateChange", ({ isActive }) => {
        if (isActive) { processQueue(); syncAll().catch(() => {}); }
      });
    } catch {}
  } else {
    const onVisible = () => { if (document.visibilityState === "visible") { processQueue(); } };
    document.addEventListener("visibilitychange", onVisible);
    _appStateListener = { remove: () => document.removeEventListener("visibilitychange", onVisible) };
  }
}

onMounted(async () => {
  await initNetworkListener();
  refreshQueueCount();
  processQueue();
  syncAll().catch(() => {});
  setupAppLifecycle();
  initLocalNotifications(t, tm, {}).catch(() => {});

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
  window.addEventListener("keydown", onKeydown);

  
  if (scrollRef.value) {
    scrollRef.value.addEventListener("touchstart", ptTouchStart, { passive: true })
    scrollRef.value.addEventListener("touchmove",  ptTouchMove,  { passive: false })
    scrollRef.value.addEventListener("touchend",   ptTouchEnd,   { passive: true })
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  _appStateListener?.remove?.();
  if (scrollRef.value) {
    scrollRef.value.removeEventListener("touchstart", ptTouchStart)
    scrollRef.value.removeEventListener("touchmove",  ptTouchMove)
    scrollRef.value.removeEventListener("touchend",   ptTouchEnd)
  }
});

watch(sidebarOpen, (v) => { if (!v) profileOpen.value = false; });

const logout = async () => {
  disconnect();
  try { await api.post("/logout"); } catch {}
  storageRemove("auth_token");
  storageRemove("auth_user");
  router.replace("/");
};
</script>

<template>
  
  <OnboardingModal v-if="showOnboarding" @done="showOnboarding = false" />

  
  <div class="flex flex-col bg-background" style="height: 100dvh; padding-top: env(safe-area-inset-top, 0px);">

    
    <div
      v-if="!isOnline"
      class="relative z-20 flex items-center gap-2 px-4 py-1.5 text-xs bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border-b border-yellow-500/20"
    >
      <WifiOff class="w-3.5 h-3.5 shrink-0" />
      <span>{{ $t("offline.banner") }}</span>
    </div>

    
    <div
      v-else-if="!wsConnected"
      class="relative z-20 flex items-center gap-2 px-4 py-1 text-xs bg-blue-500/10 text-blue-600 dark:text-blue-400 border-b border-blue-500/20"
    >
      <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse shrink-0" />
      <span>{{ $t("offline.wsDisconnected") }}</span>
    </div>

    
    <div class="relative z-10 flex-1 min-h-0 md:p-3 lg:p-4 md:bg-muted/30">
      <div class="h-full w-full overflow-hidden md:rounded-2xl md:border md:shadow-sm bg-background relative">

        <SidebarProvider v-model:open="sidebarOpen" class="!min-h-0 h-full">

          
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
                <Button
                  class="w-full rounded-xl justify-start gap-2"
                  @click="router.push('/app/diary'); sidebarOpen = false"
                >
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
                          @click="sidebarOpen = false"
                        >
                          <span
                            v-if="isActive(item.to)"
                            class="absolute left-1 top-1/2 -translate-y-1/2 h-6 w-1 rounded-full bg-sidebar-primary"
                          />
                          <component :is="item.icon" />
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
                          @click="sidebarOpen = false"
                        >
                          <span v-if="isActive('/app/admin')"
                            class="absolute left-1 top-1/2 -translate-y-1/2 h-6 w-1 rounded-full bg-purple-500"
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
                          @click="sidebarOpen = false"
                        >
                          <span v-if="isActive('/app/settings')"
                            class="absolute left-1 top-1/2 -translate-y-1/2 h-6 w-1 rounded-full bg-sidebar-primary"
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
                      class="w-full flex items-center gap-3 rounded-xl px-3 py-3 hover:bg-sidebar-accent transition text-left"
                    >
                      <Avatar class="h-9 w-9 shrink-0">
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
                        <UserRound class="h-4 w-4" /> {{ $t("nav.profile") }}
                      </RouterLink>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                      <RouterLink to="/app/settings" class="flex items-center gap-2" @click="closeOverlays">
                        <Settings class="h-4 w-4" /> {{ $t("nav.settings") }}
                      </RouterLink>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="text-destructive flex items-center gap-2" @click="logout">
                      <LogOut class="h-4 w-4" /> {{ $t("auth.logout") }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </SidebarFooter>
            <SidebarRail />
          </Sidebar>

          
          <SidebarInset class="overflow-hidden flex flex-col relative">

            
            <div class="skin-bg pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
              <span v-for="i in 20" :key="i" />
            </div>
            <div class="skin-fg pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
              <span v-for="i in 10" :key="'fg'+i" />
            </div>

            
            <header class="relative z-20 flex h-14 shrink-0 items-center justify-between gap-2 border-b px-3 sm:px-4 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
              <div class="flex items-center gap-2 min-w-0">
                
                <SidebarTrigger class="-ml-1 hidden md:flex" />
                <div class="font-semibold text-base truncate">{{ pageTitle }}</div>
                
                <span
                  class="w-2 h-2 rounded-full shrink-0"
                  :class="!isOnline ? 'bg-red-500' : wsConnected ? 'bg-green-500' : 'bg-yellow-500 animate-pulse'"
                  :title="!isOnline ? $t('offline.banner') : wsConnected ? $t('status.live') : $t('offline.wsDisconnected')"
                  :aria-label="!isOnline ? $t('offline.banner') : wsConnected ? $t('status.live') : $t('offline.wsDisconnected')"
                />
              </div>
              
              <button
                type="button"
                class="md:hidden flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent transition"
                @click="router.push('/app/profile')"
              >
                <Avatar class="h-7 w-7">
                  <AvatarImage v-if="userAvatar" :src="userAvatar" />
                  <AvatarFallback class="text-xs">{{ userInitials }}</AvatarFallback>
                </Avatar>
              </button>
              
              <div class="hidden md:flex items-center gap-2">
                <div class="flex items-center gap-1.5 text-sm text-muted-foreground px-3 py-1.5 rounded-xl border bg-background/60">
                  <CalendarIcon class="h-3.5 w-3.5" />
                  {{ df.format(date.toDate(tz)) }}
                </div>
              </div>
            </header>

            
            <div ref="scrollRef" class="flex-1 min-h-0 overflow-y-auto scroll-smooth-mobile pb-20 md:pb-0 relative z-[1]">
              
              <div
                v-if="isPulling"
                class="flex items-center justify-center pt-3 pb-1 transition-all duration-150"
                :style="{ opacity: pullProgress, transform: `scaleY(${pullProgress})`, transformOrigin: 'top' }"
              >
                <div class="h-7 w-7 rounded-full border-2 border-primary border-t-transparent animate-spin" />
              </div>
              <div class="p-3 sm:p-4 md:p-5">
                <div class="max-w-6xl mx-auto w-full">
                  <RouterView />
                </div>
              </div>
            </div>

          </SidebarInset>
        </SidebarProvider>

      </div>
    </div>

    
    <nav
      class="md:hidden relative z-20 flex items-stretch border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80"
      style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 4px);"
    >
      <RouterLink
        v-for="item in bottomNav"
        :key="item.to"
        :to="item.to"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 min-h-[52px] transition-colors relative"
        :class="isActive(item.to) ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
      >
        
        <span
          v-if="isActive(item.to)"
          class="absolute top-0 left-1/2 -translate-x-1/2 h-0.5 w-6 rounded-full bg-primary"
        />
        <span class="relative">
          <component :is="item.icon" class="h-5 w-5" />
          
          <span
            v-if="item.to === '/app/diary' && pendingQueueCount > 0"
            class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-yellow-500"
          />
        </span>
        <span class="text-[10px] font-medium leading-none">{{ item.label }}</span>
      </RouterLink>
    </nav>

  </div>
</template>
