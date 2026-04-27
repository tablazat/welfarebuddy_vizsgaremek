import { ref } from "vue"
import { getQueue, clearQueue } from "@/lib/offlineQueue"
import api from "@/lib/api"

export const isOnline = ref(navigator.onLine)

export async function processQueue() {
  if (!isOnline.value) return
  const queue = getQueue()
  if (!queue.length) return

  const failed = []
  for (const item of queue) {
    try {
      await api({ method: item.method, url: item.url, data: item.payload })
    } catch (e) {
      const status = e?.response?.status
      if (!status || status >= 500) failed.push(item)
    }
  }

  if (failed.length) {
    const { setQueue } = await import("@/lib/offlineQueue")
    setQueue(failed)
  } else {
    clearQueue()
  }
}

export function initNetworkListener() {
  // Use Capacitor Network plugin if available (injected globally by Capacitor runtime)
  const CapNet = window.Capacitor?.Plugins?.Network

  if (CapNet) {
    let handle = null
    CapNet.getStatus().then((s) => {
      isOnline.value = s.connected
    }).catch(() => {})

    CapNet.addListener("networkStatusChange", (s) => {
      isOnline.value = s.connected
      if (s.connected) processQueue()
    }).then((h) => { handle = h }).catch(() => {})

    return () => { handle?.remove() }
  }

  // Browser fallback
  const onOnline = () => { isOnline.value = true; processQueue() }
  const onOffline = () => { isOnline.value = false }
  const onVisible = () => { if (document.visibilityState === "visible" && isOnline.value) processQueue() }
  window.addEventListener("online", onOnline)
  window.addEventListener("offline", onOffline)
  document.addEventListener("visibilitychange", onVisible)
  return () => {
    window.removeEventListener("online", onOnline)
    window.removeEventListener("offline", onOffline)
    document.removeEventListener("visibilitychange", onVisible)
  }
}
