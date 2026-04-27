import { ref, onMounted, onBeforeUnmount } from "vue"

/**
 * usePullToRefresh – natív pull-to-refresh gesture Capacitor apphoz
 *
 * Használat:
 *   const { isPulling, pullProgress } = usePullToRefresh(scrollEl, onRefresh)
 *   - scrollEl: ref<HTMLElement> – a görgethető konténer elem
 *   - onRefresh: async function – a frissítő callback
 *
 * A parent komponensben egy refresh indicator-t kell renderelni:
 *   <div v-if="isPulling" :style="{ opacity: pullProgress }" class="...">↓</div>
 */
export function usePullToRefresh(scrollEl, onRefresh) {
  const isPulling     = ref(false)
  const pullProgress  = ref(0)  // 0–1
  const isRefreshing  = ref(false)

  const THRESHOLD  = 70   // px – ennyit kell húzni a frissítéshez
  const MAX_PULL   = 100  // px – max vizuális húzás

  let startY  = 0
  let currentY = 0
  let active   = false

  function onTouchStart(e) {
    const el = scrollEl.value
    if (!el) return
    if (el.scrollTop > 0) return   // csak ha a tetején van
    if (isRefreshing.value) return
    startY   = e.touches[0].clientY
    active   = true
  }

  function onTouchMove(e) {
    if (!active) return
    const el = scrollEl.value
    if (!el) return
    if (el.scrollTop > 0) { active = false; return }

    currentY = e.touches[0].clientY
    const delta = currentY - startY
    if (delta <= 0) { isPulling.value = false; pullProgress.value = 0; return }

    // Prevent default scroll when pulling down
    e.preventDefault()
    isPulling.value  = true
    pullProgress.value = Math.min(delta / MAX_PULL, 1)
  }

  async function onTouchEnd() {
    if (!active || !isPulling.value) {
      active = false; return
    }
    active = false
    const delta = currentY - startY

    if (delta >= THRESHOLD) {
      isRefreshing.value = true
      pullProgress.value = 1
      try { await onRefresh() } finally {
        isRefreshing.value = false
      }
    }

    isPulling.value    = false
    pullProgress.value = 0
    startY = currentY = 0
  }

  onMounted(() => {
    const el = scrollEl.value
    if (!el) return
    el.addEventListener("touchstart", onTouchStart, { passive: true })
    el.addEventListener("touchmove",  onTouchMove,  { passive: false })
    el.addEventListener("touchend",   onTouchEnd,   { passive: true })
  })

  onBeforeUnmount(() => {
    const el = scrollEl.value
    if (!el) return
    el.removeEventListener("touchstart", onTouchStart)
    el.removeEventListener("touchmove",  onTouchMove)
    el.removeEventListener("touchend",   onTouchEnd)
  })

  return { isPulling, pullProgress, isRefreshing }
}
