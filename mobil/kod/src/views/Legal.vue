<script setup>
import { ref, onMounted, computed } from "vue"
import { useRouter, useRoute } from "vue-router"
import { useI18n } from "vue-i18n"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs"
import { ArrowLeft, FileText, Shield, Scale } from "lucide-vue-next"

const router = useRouter()
const route  = useRoute()
const { t, tm } = useI18n()

const tab = ref(route.query.tab || "gdpr")

function setTab(v) {
  tab.value = v
  router.replace({ query: { ...route.query, tab: v } })
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push({ name: "dashboard" })
}

const gdprParagraphs = computed(() => tm("legal.gdpr.body") || [])
const tosParagraphs  = computed(() => tm("legal.tos.body")  || [])
const eulaParagraphs = computed(() => tm("legal.eula.body") || [])
</script>

<template>
  <div class="max-w-3xl mx-auto p-4 space-y-4">
    <div class="flex items-center gap-3">
      <Button variant="ghost" size="icon" class="rounded-full" @click="goBack">
        <ArrowLeft class="h-4 w-4" />
      </Button>
      <div>
        <h1 class="text-lg font-semibold">{{ $t("legal.title") }}</h1>
        <p class="text-sm text-muted-foreground">{{ $t("legal.subtitle") }}</p>
      </div>
    </div>

    <Tabs :model-value="tab" @update:model-value="setTab" class="space-y-4">
      <TabsList class="grid grid-cols-3 w-full">
        <TabsTrigger value="gdpr" class="gap-2">
          <Shield class="h-4 w-4" />
          <span class="hidden sm:inline">{{ $t("legal.gdpr.title") }}</span>
          <span class="sm:hidden">GDPR</span>
        </TabsTrigger>
        <TabsTrigger value="tos" class="gap-2">
          <FileText class="h-4 w-4" />
          <span class="hidden sm:inline">{{ $t("legal.tos.title") }}</span>
          <span class="sm:hidden">ÁSZF</span>
        </TabsTrigger>
        <TabsTrigger value="eula" class="gap-2">
          <Scale class="h-4 w-4" />
          <span class="hidden sm:inline">{{ $t("legal.eula.title") }}</span>
          <span class="sm:hidden">EULA</span>
        </TabsTrigger>
      </TabsList>

      <TabsContent value="gdpr">
        <Card class="rounded-2xl">
          <CardHeader>
            <CardTitle class="text-base flex items-center gap-2">
              <Shield class="h-4 w-4 text-blue-500" />
              {{ $t("legal.gdpr.title") }}
            </CardTitle>
            <p class="text-xs text-muted-foreground">{{ $t("legal.lastUpdated") }}: 2026-04-23</p>
          </CardHeader>
          <CardContent class="prose prose-sm max-w-none dark:prose-invert">
            <p v-for="(p, i) in gdprParagraphs" :key="i" class="text-sm leading-relaxed mb-3" v-html="p" />
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="tos">
        <Card class="rounded-2xl">
          <CardHeader>
            <CardTitle class="text-base flex items-center gap-2">
              <FileText class="h-4 w-4 text-green-500" />
              {{ $t("legal.tos.title") }}
            </CardTitle>
            <p class="text-xs text-muted-foreground">{{ $t("legal.lastUpdated") }}: 2026-04-23</p>
          </CardHeader>
          <CardContent class="prose prose-sm max-w-none dark:prose-invert">
            <p v-for="(p, i) in tosParagraphs" :key="i" class="text-sm leading-relaxed mb-3" v-html="p" />
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="eula">
        <Card class="rounded-2xl">
          <CardHeader>
            <CardTitle class="text-base flex items-center gap-2">
              <Scale class="h-4 w-4 text-purple-500" />
              {{ $t("legal.eula.title") }}
            </CardTitle>
            <p class="text-xs text-muted-foreground">{{ $t("legal.lastUpdated") }}: 2026-04-23</p>
          </CardHeader>
          <CardContent class="prose prose-sm max-w-none dark:prose-invert">
            <p v-for="(p, i) in eulaParagraphs" :key="i" class="text-sm leading-relaxed mb-3" v-html="p" />
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>
