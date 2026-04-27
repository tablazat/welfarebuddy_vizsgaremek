<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import { Button } from "@/components/ui/button"
import { Cookie, X } from "lucide-vue-next"
import { migrateToLocalStorage, migrateToSessionStorage } from "@/lib/storage"

const { t } = useI18n()

const visible = ref(false)

onMounted(() => {
  if (!localStorage.getItem("cookie_consent")) {
    visible.value = true
  }
})

function accept() {
  localStorage.setItem("cookie_consent", "accepted")
  migrateToLocalStorage()
  visible.value = false
}

function decline() {
  localStorage.setItem("cookie_consent", "declined")
  migrateToSessionStorage()
  visible.value = false
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="translate-y-full opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="translate-y-full opacity-0"
  >
    <div
      v-if="visible"
      class="fixed bottom-0 inset-x-0 z-[100] p-3 sm:p-4"
    >
      <div class="max-w-xl mx-auto rounded-2xl border bg-background/95 backdrop-blur shadow-lg p-4 sm:p-5">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center shrink-0">
            <Cookie class="h-5 w-5 text-orange-500" />
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-sm">{{ $t("cookie.title") }}</h3>
            <p class="text-xs text-muted-foreground mt-1 leading-relaxed">
              {{ $t("cookie.message") }}
            </p>
            <div class="flex gap-2 mt-3">
              <Button size="sm" class="rounded-xl gap-1.5 h-8 px-4" @click="accept">
                {{ $t("cookie.accept") }}
              </Button>
              <Button variant="ghost" size="sm" class="rounded-xl h-8 px-3 text-muted-foreground" @click="decline">
                {{ $t("cookie.decline") }}
              </Button>
            </div>
          </div>
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground transition shrink-0"
            @click="decline"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
