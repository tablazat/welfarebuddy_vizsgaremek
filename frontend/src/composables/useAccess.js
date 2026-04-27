import { computed } from "vue"

export function useAccess(user) {
  const level = computed(() => user.value?.level_of_access || "free")
  const isFree = computed(() => level.value === "free")
  const isPro = computed(() => level.value === "pro" || level.value === "admin")
  const isAdmin = computed(() => level.value === "admin")

  return { level, isFree, isPro, isAdmin }
}
