import axios from "axios"
import { storageGet, storageRemove } from "@/lib/storage"
import { enqueueRequest } from "@/lib/offlineQueue"
import { cacheResponse, getCachedResponse } from "@/lib/offlineCache"
import i18n from "@/lib/i18n"

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
  config.headers = config.headers ?? {}
  config.headers["Accept-Language"] = i18n.global.locale.value || "en"
  return config
})

api.interceptors.response.use(
  (res) => {
    
    if (res.config.method === "get" && res.config.responseType !== "blob") {
      const rawParams = res.config.params || {}
      const sortedParams = Object.fromEntries(Object.keys(rawParams).sort().map(k => [k, rawParams[k]]))
      const cacheKey = res.config.url + JSON.stringify(sortedParams)
      cacheResponse(cacheKey, res.data)
    }
    return res
  },
  (err) => {
    
    if (!err.response && err.request) {
      const { method, url, data } = err.config

      
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
