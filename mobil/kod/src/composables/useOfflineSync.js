import { ref } from "vue"
import { Network } from "@capacitor/network"
import api from "@/lib/api"
import { getQueue, setQueue } from "@/lib/offlineQueue"

const isOnline = ref(true)

export function useOfflineSync() {
  async function processQueue() {
    const queue = getQueue()
    if (!queue.length) return

    const remaining = []
    for (const item of queue) {
      try {
        // _skipQueue: jelzi az api interceptornak hogy NE queue-olja újra ha megint offline
        await api({ method: item.method, url: item.url, data: item.payload, _skipQueue: true })
      } catch (e) {
        const status = e?.response?.status
        if (!status || status >= 500) remaining.push(item)
      }
    }
    setQueue(remaining)
  }

  async function initNetworkListener() {
    try {
      const status = await Network.getStatus()
      isOnline.value = status.connected

      Network.addListener("networkStatusChange", async (status) => {
        isOnline.value = status.connected
        if (status.connected) {
          await processQueue()
        }
      })
    } catch {
      // @capacitor/network nem elérhető (pl. web dev build) – maradjon true
      isOnline.value = true
    }
  }

  return { isOnline, processQueue, initNetworkListener }
}
