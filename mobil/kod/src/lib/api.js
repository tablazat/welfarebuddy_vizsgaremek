import axios from "axios"
import { storageGet, storageRemove } from "@/lib/storage"
import { enqueueRequest } from "@/lib/offlineQueue"
import { cacheResponse, getCachedResponse } from "@/lib/offlineCache"

const api = axios.create({
  baseURL: (import.meta.env.VITE_API_BASE_URL ?? "https://api.welfarebuddy.hu").replace(/\/$/, ""),
  withCredentials: false,
  headers: {
    Accept: "application/json",
  },
})

api.interceptors.request.use((config) => {
  const token = storageGet("auth_token")
  if (token) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (res) => {
    // GET válaszokat eltároljuk cache-be (blob-okat nem)
    if (res.config.method === "get" && res.config.responseType !== "blob") {
      const rawParams = res.config.params || {}
      const sortedParams = Object.fromEntries(Object.keys(rawParams).sort().map(k => [k, rawParams[k]]))
      const cacheKey = res.config.url + JSON.stringify(sortedParams)
      cacheResponse(cacheKey, res.data)
    }
    return res
  },
  (err) => {
    // Hálózati hiba (nincs response, de volt request) = offline állapot
    if (!err.response && err.request) {
      const { method, url, data } = err.config

      // _skipQueue: a processQueue-ból jövő kéréseket NE queue-oljuk újra
      if (["post", "put", "patch"].includes(method) && !err.config._skipQueue) {
        enqueueRequest(url, method, JSON.parse(data || "{}"))
        return Promise.resolve({ data: { queued: true }, status: 202, queued: true })
      }

      if (method === "get") {
        const rawP = err.config.params || {}
        const cacheKey = url + JSON.stringify(Object.fromEntries(Object.keys(rawP).sort().map(k => [k, rawP[k]])))
        const cached = getCachedResponse(cacheKey)
        if (cached !== null) {
          return Promise.resolve({ data: cached, status: 200, fromCache: true })
        }
      }
    }

    if (err?.response?.status === 401) {
      const onSafePage = location.pathname === "/auth" || location.pathname === "/verify-email"
      if (!onSafePage) {
        storageRemove("auth_token")
        storageRemove("auth_user")
        const redirect = location.pathname + location.search + location.hash
        location.href = `/auth?tab=login&redirect=${encodeURIComponent(redirect)}`
      }
    }
    return Promise.reject(err)
  }
)

export default api
