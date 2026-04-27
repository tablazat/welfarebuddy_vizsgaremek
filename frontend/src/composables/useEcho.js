import { ref, onBeforeUnmount } from "vue"
import Pusher from "pusher-js"
import Echo from "laravel-echo"
import api from "@/lib/api"
import { storageGet } from "@/lib/storage"

let echoInstance = null
let echoInitPromise = null


const dbg = (...args) => { if (import.meta.env.DEV) console.log(...args) }


export function useEcho() {
  const connected = ref(false)
  const lastEvent = ref(null)
  const listeners = ref([])

  async function getEcho() {
    if (echoInstance) return echoInstance
    if (echoInitPromise) return echoInitPromise

    const token = storageGet("auth_token")
    if (!token) return null

    echoInitPromise = api.get("/config")
      .then(({ data }) => {
        const cfg = data?.reverb
        if (!cfg?.key || !cfg?.host) throw new Error("Invalid /config response")
        window.Pusher = Pusher
        echoInstance = new Echo({
          broadcaster: "pusher",
          key: cfg.key,
          cluster: "mt1",
          wsHost: cfg.host,
          wsPort: cfg.port || 443,
          wssPort: cfg.port || 443,
          forceTLS: cfg.forceTLS !== false,
          enabledTransports: ["ws", "wss"],
          disableStats: true,
          authorizer: (channel) => ({
            authorize: (socketId, callback) => {
              api
                .post("/broadcasting/auth", {
                  socket_id: socketId,
                  channel_name: channel.name,
                })
                .then((response) => callback(false, response.data))
                .catch((error) => {
                  console.error("WS auth failed:", channel.name, error)
                  callback(true, error)
                })
            },
          }),
        })
        connected.value = true
        return echoInstance
      })
      .catch((err) => {
        console.error("Echo init failed:", err)
        echoInitPromise = null
        return null
      })

    return echoInitPromise
  }

  
  async function subscribe(userId, onEvent) {
    const echo = await getEcho()
    if (!echo) return

    const channelName = `user.${userId}`

    
    try { echo.leave(channelName) } catch {}

    dbg(`WS: Subscribing to ${channelName}...`)

    echo
      .private(channelName)
      .listen(".upload.created", (e) => {
        dbg("WS event received:", e)
        lastEvent.value = e
        const payload = e.data ?? e
        const entryType = payload.type ?? "unknown"
        const entryData = payload.data ?? payload
        if (onEvent) onEvent(entryType, entryData)
      })
      .subscribed(() => {
        dbg(`WS: Subscribed to ${channelName}`)
        connected.value = true
      })
      .error((error) => {
        console.error("WS subscription error:", error)
        connected.value = false
      })

    listeners.value.push(channelName)
  }

  async function listen(userId, eventName, callback) {
    const echo = await getEcho()
    if (!echo) return

    const channelName = `user.${userId}`
    echo.private(channelName).listen(eventName, (e) => {
      dbg(`WS [${eventName}]:`, e)
      lastEvent.value = e
      if (callback) callback(e)
    })
  }

  function disconnect() {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
      echoInitPromise = null
      connected.value = false
      listeners.value = []
    }
  }

  onBeforeUnmount(() => {
    //
  })

  return {
    connected,
    lastEvent,
    subscribe,
    listen,
    disconnect,
    getEcho,
  }
}
