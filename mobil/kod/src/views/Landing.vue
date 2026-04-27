<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Heart, Activity, Weight, BarChart3, ArrowRight,
  UserPlus, ClipboardEdit, TrendingUp, Shield, Zap, Smartphone
} from "lucide-vue-next"
import { supportedLocales, setLocale } from "@/lib/i18n"

const router = useRouter()
const { t, locale } = useI18n()
const langOpen = ref(false)

function switchLocale(code) {
  setLocale(code)
  langOpen.value = false
}

function goAuth(tab = "register") {
  router.push({ name: "auth", query: { tab } })
}
</script>

<template>
  <div class="min-h-svh bg-background text-foreground">

    
    <nav class="sticky top-0 z-50 backdrop-blur-lg bg-background/80 border-b">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-8 shrink-0" />
          <span class="text-lg font-bold tracking-tight">WelfareBuddy</span>
        </div>
        <div class="flex items-center gap-2">
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
          <Button variant="ghost" size="sm" class="rounded-xl" @click="goAuth('login')">
            {{ $t("landing.login") }}
          </Button>
          <Button size="sm" class="rounded-xl gap-1.5" @click="goAuth('register')">
            {{ $t("landing.getStartedFree") }}
            <ArrowRight class="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>
    </nav>

    
    <section class="relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-red-50 via-blue-50 to-purple-50 dark:from-red-950/20 dark:via-blue-950/20 dark:to-purple-950/20" />
      <div class="absolute inset-0">
        <div class="absolute top-20 left-10 w-72 h-72 bg-red-200/30 dark:bg-red-800/10 rounded-full blur-3xl" />
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-200/30 dark:bg-blue-800/10 rounded-full blur-3xl" />
        <div class="absolute top-40 right-1/4 w-64 h-64 bg-purple-200/20 dark:bg-purple-800/10 rounded-full blur-3xl" />
      </div>

      <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-20 pb-24 sm:pt-28 sm:pb-32">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
          
          <div class="text-center lg:text-left">
            <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary mb-6">
              <Zap class="h-3 w-3" />
              {{ $t("landing.heroTagline") }}
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] mb-6">
              {{ $t("landing.heroTitle") }}
              <span class="bg-gradient-to-r from-red-500 via-blue-500 to-purple-500 bg-clip-text text-transparent">{{ $t("landing.heroTitleHighlight") }}</span>
            </h1>
            <p class="text-lg sm:text-xl text-muted-foreground max-w-lg mx-auto lg:mx-0 mb-8 leading-relaxed">
              {{ $t("landing.heroSubtitle") }}
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
              <Button size="lg" class="rounded-xl text-base gap-2 h-12 px-6" @click="goAuth('register')">
                {{ $t("landing.getStartedFree") }}
                <ArrowRight class="h-4 w-4" />
              </Button>
              <Button variant="outline" size="lg" class="rounded-xl text-base h-12 px-6" @click="goAuth('login')">
                {{ $t("landing.login") }}
              </Button>
            </div>
          </div>

          
          <div class="hidden lg:block relative">
            <div class="relative w-full max-w-md mx-auto">
              
              <div class="bg-card border rounded-2xl shadow-2xl shadow-black/5 p-6 space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-muted-foreground">{{ $t("landing.mockupSummary") }}</span>
                  <span class="text-xs text-muted-foreground">{{ $t("landing.mockupDate") }}</span>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div class="bg-red-50 dark:bg-red-950/30 rounded-xl p-3 text-center">
                    <Heart class="h-5 w-5 text-red-500 mx-auto mb-1" />
                    <div class="text-2xl font-bold">72</div>
                    <div class="text-[10px] text-muted-foreground">BPM</div>
                  </div>
                  <div class="bg-blue-50 dark:bg-blue-950/30 rounded-xl p-3 text-center">
                    <Activity class="h-5 w-5 text-blue-500 mx-auto mb-1" />
                    <div class="text-2xl font-bold">120<span class="text-sm font-normal text-muted-foreground">/80</span></div>
                    <div class="text-[10px] text-muted-foreground">mmHg</div>
                  </div>
                  <div class="bg-purple-50 dark:bg-purple-950/30 rounded-xl p-3 text-center">
                    <Weight class="h-5 w-5 text-purple-500 mx-auto mb-1" />
                    <div class="text-2xl font-bold">74</div>
                    <div class="text-[10px] text-muted-foreground">kg</div>
                  </div>
                </div>
                
                <div class="pt-2">
                  <div class="text-xs text-muted-foreground mb-2">{{ $t("landing.mockupChart") }}</div>
                  <div class="flex items-end gap-1.5 h-16">
                    <div class="flex-1 bg-red-200 dark:bg-red-800/40 rounded-t" style="height: 60%" />
                    <div class="flex-1 bg-red-200 dark:bg-red-800/40 rounded-t" style="height: 75%" />
                    <div class="flex-1 bg-red-200 dark:bg-red-800/40 rounded-t" style="height: 55%" />
                    <div class="flex-1 bg-red-300 dark:bg-red-700/50 rounded-t" style="height: 80%" />
                    <div class="flex-1 bg-red-300 dark:bg-red-700/50 rounded-t" style="height: 70%" />
                    <div class="flex-1 bg-red-400 dark:bg-red-600/50 rounded-t" style="height: 90%" />
                    <div class="flex-1 bg-red-400 dark:bg-red-600/50 rounded-t" style="height: 85%" />
                  </div>
                </div>
              </div>

              
              <div class="absolute -top-4 -right-4 bg-card border rounded-xl shadow-lg px-3 py-2 flex items-center gap-2 animate-bounce" style="animation-duration: 3s">
                <div class="h-8 w-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                  <TrendingUp class="h-4 w-4 text-green-600" />
                </div>
                <div>
                  <div class="text-xs font-semibold">{{ $t("landing.mockupTrend") }}</div>
                  <div class="text-[10px] text-muted-foreground">{{ $t("landing.mockupTrendDesc") }}</div>
                </div>
              </div>

              
              <div class="absolute -bottom-3 -left-6 bg-card border rounded-xl shadow-lg px-3 py-2 flex items-center gap-2">
                <span class="text-xl">🔥</span>
                <div>
                  <div class="text-xs font-semibold">{{ $t("landing.mockupStreak") }}</div>
                  <div class="text-[10px] text-muted-foreground">{{ $t("landing.mockupStreakDesc") }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    
    <section class="py-20 sm:py-28">
      <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-14">
          <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">
            {{ $t("landing.featuresTitle") }}
          </h2>
          <p class="text-muted-foreground text-lg max-w-2xl mx-auto">
            {{ $t("landing.featuresSubtitle") }}
          </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <Card class="rounded-2xl border-0 shadow-sm bg-card hover:shadow-md transition-shadow">
            <CardContent class="p-6">
              <div class="h-12 w-12 rounded-2xl bg-red-100 dark:bg-red-950/40 flex items-center justify-center mb-4">
                <Heart class="h-6 w-6 text-red-500" />
              </div>
              <h3 class="font-semibold text-lg mb-2">{{ $t("landing.featureHR") }}</h3>
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ $t("landing.featureHRDesc") }}
              </p>
            </CardContent>
          </Card>

          <Card class="rounded-2xl border-0 shadow-sm bg-card hover:shadow-md transition-shadow">
            <CardContent class="p-6">
              <div class="h-12 w-12 rounded-2xl bg-blue-100 dark:bg-blue-950/40 flex items-center justify-center mb-4">
                <Activity class="h-6 w-6 text-blue-500" />
              </div>
              <h3 class="font-semibold text-lg mb-2">{{ $t("landing.featureBP") }}</h3>
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ $t("landing.featureBPDesc") }}
              </p>
            </CardContent>
          </Card>

          <Card class="rounded-2xl border-0 shadow-sm bg-card hover:shadow-md transition-shadow">
            <CardContent class="p-6">
              <div class="h-12 w-12 rounded-2xl bg-purple-100 dark:bg-purple-950/40 flex items-center justify-center mb-4">
                <Weight class="h-6 w-6 text-purple-500" />
              </div>
              <h3 class="font-semibold text-lg mb-2">{{ $t("landing.featureWeight") }}</h3>
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ $t("landing.featureWeightDesc") }}
              </p>
            </CardContent>
          </Card>

          <Card class="rounded-2xl border-0 shadow-sm bg-card hover:shadow-md transition-shadow">
            <CardContent class="p-6">
              <div class="h-12 w-12 rounded-2xl bg-amber-100 dark:bg-amber-950/40 flex items-center justify-center mb-4">
                <BarChart3 class="h-6 w-6 text-amber-500" />
              </div>
              <h3 class="font-semibold text-lg mb-2">{{ $t("landing.featureStats") }}</h3>
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ $t("landing.featureStatsDesc") }}
              </p>
            </CardContent>
          </Card>

          <Card class="rounded-2xl border-0 shadow-sm bg-card hover:shadow-md transition-shadow">
            <CardContent class="p-6">
              <div class="h-12 w-12 rounded-2xl bg-green-100 dark:bg-green-950/40 flex items-center justify-center mb-4">
                <Shield class="h-6 w-6 text-green-500" />
              </div>
              <h3 class="font-semibold text-lg mb-2">{{ $t("landing.featureSecurity") }}</h3>
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ $t("landing.featureSecurityDesc") }}
              </p>
            </CardContent>
          </Card>

          <Card class="rounded-2xl border-0 shadow-sm bg-card hover:shadow-md transition-shadow">
            <CardContent class="p-6">
              <div class="h-12 w-12 rounded-2xl bg-cyan-100 dark:bg-cyan-950/40 flex items-center justify-center mb-4">
                <Smartphone class="h-6 w-6 text-cyan-500" />
              </div>
              <h3 class="font-semibold text-lg mb-2">{{ $t("landing.featureResponsive") }}</h3>
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ $t("landing.featureResponsiveDesc") }}
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </section>

    
    <section class="py-20 sm:py-28 bg-muted/40">
      <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-14">
          <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">
            {{ $t("landing.howTitle") }}
          </h2>
          <p class="text-muted-foreground text-lg">
            {{ $t("landing.howSubtitle") }}
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          <div class="text-center">
            <div class="h-16 w-16 rounded-2xl bg-primary text-primary-foreground flex items-center justify-center mx-auto mb-5 text-2xl font-bold shadow-lg">
              1
            </div>
            <div class="h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
              <UserPlus class="h-7 w-7 text-primary" />
            </div>
            <h3 class="font-semibold text-lg mb-2">{{ $t("landing.step1Title") }}</h3>
            <p class="text-sm text-muted-foreground leading-relaxed max-w-xs mx-auto">
              {{ $t("landing.step1Desc") }}
            </p>
          </div>

          <div class="text-center">
            <div class="h-16 w-16 rounded-2xl bg-primary text-primary-foreground flex items-center justify-center mx-auto mb-5 text-2xl font-bold shadow-lg">
              2
            </div>
            <div class="h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
              <ClipboardEdit class="h-7 w-7 text-primary" />
            </div>
            <h3 class="font-semibold text-lg mb-2">{{ $t("landing.step2Title") }}</h3>
            <p class="text-sm text-muted-foreground leading-relaxed max-w-xs mx-auto">
              {{ $t("landing.step2Desc") }}
            </p>
          </div>

          <div class="text-center">
            <div class="h-16 w-16 rounded-2xl bg-primary text-primary-foreground flex items-center justify-center mx-auto mb-5 text-2xl font-bold shadow-lg">
              3
            </div>
            <div class="h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
              <TrendingUp class="h-7 w-7 text-primary" />
            </div>
            <h3 class="font-semibold text-lg mb-2">{{ $t("landing.step3Title") }}</h3>
            <p class="text-sm text-muted-foreground leading-relaxed max-w-xs mx-auto">
              {{ $t("landing.step3Desc") }}
            </p>
          </div>
        </div>
      </div>
    </section>

    
    <section class="py-20 sm:py-28">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 text-center">
        <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-16 mx-auto mb-6" />
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">
          {{ $t("landing.ctaTitle") }}
        </h2>
        <p class="text-muted-foreground text-lg mb-8 max-w-xl mx-auto">
          {{ $t("landing.ctaSubtitle") }}
        </p>
        <Button size="lg" class="rounded-xl text-base gap-2 h-12 px-8" @click="goAuth('register')">
          {{ $t("landing.ctaButton") }}
          <ArrowRight class="h-4 w-4" />
        </Button>
      </div>
    </section>

    
    <footer class="border-t py-8">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <img src="@/assets/welfarebuddy_logo.svg" alt="WelfareBuddy" class="h-6 shrink-0" />
          <span class="font-semibold">WelfareBuddy</span>
        </div>
        <p class="text-sm text-muted-foreground">
          &copy; 2026 WelfareBuddy. {{ $t("landing.footerRights") }}
        </p>
      </div>
    </footer>

  </div>
</template>
