const CACHE_PREFIX = "cache_"
const DEFAULT_MAX_AGE_MS = 24 * 60 * 60 * 1000 // 24 óra
const HIGH_WATER_KEY = "cache_high_water_ts"
const CLOCK_SKEW_TOLERANCE_MS = 60 * 1000 // 1 perc a kis órákülönbségre

// Monotonic guard: eltároljuk a legnagyobb látott időbélyeget. Ha Date.now()
// kisebb ennél (visszaállított óra), minden cache-bejegyzést eldobunk.
function advanceHighWater() {
  const now = Date.now()
  try {
    const prev = Number(localStorage.getItem(HIGH_WATER_KEY)) || 0
    if (now > prev) localStorage.setItem(HIGH_WATER_KEY, String(now))
    else if (now < prev - CLOCK_SKEW_TOLERANCE_MS) {
      // óra visszaállt → minden cache kulcs törlése
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const k = localStorage.key(i)
        if (k && k.startsWith(CACHE_PREFIX)) localStorage.removeItem(k)
      }
      localStorage.setItem(HIGH_WATER_KEY, String(now))
    }
  } catch {}
  return now
}

export function cacheResponse(cacheKey, data) {
  const timestamp = advanceHighWater()
  try {
    localStorage.setItem(
      CACHE_PREFIX + cacheKey,
      JSON.stringify({ data, timestamp })
    )
  } catch {}
}

export function getCachedResponse(cacheKey, maxAgeMs = DEFAULT_MAX_AGE_MS) {
  const raw = localStorage.getItem(CACHE_PREFIX + cacheKey)
  if (!raw) return null
  let parsed
  try { parsed = JSON.parse(raw) } catch { return null }
  const { data, timestamp } = parsed
  const now = advanceHighWater()
  // Jövőbeli timestamp (óra visszaállítva az íráskor) → érvénytelen
  if (timestamp > now + CLOCK_SKEW_TOLERANCE_MS) return null
  if (now - timestamp > maxAgeMs) return null
  return data
}

export function invalidateCacheKeys(...keys) {
  for (const key of keys) {
    localStorage.removeItem(CACHE_PREFIX + key)
  }
}

// Map WebSocket event types to the cache keys they invalidate
const WS_CACHE_MAP = {
  heart_rate:       ["/heart-rates", "/average/heart-rates"],
  blood_pressure:   ["/blood-pressures", "/average/blood-pressures"],
  weight:           ["/weights", "/current-weight"],
  steps:            ["/steps", "/steps/today"],
  exercise:         ["/exercises"],
  water:            ["/waters", "/waters/today"],
  sleep:            ["/sleeps", "/sleeps/last-night"],
  health_sync_complete: [
    "/heart-rates", "/average/heart-rates",
    "/blood-pressures", "/average/blood-pressures",
    "/weights", "/current-weight",
    "/steps", "/steps/today",
    "/exercises",
    "/waters", "/waters/today",
    "/sleeps", "/sleeps/last-night",
  ],
}

export function invalidateCacheForWsEvent(eventType) {
  const keys = WS_CACHE_MAP[eventType] ?? []
  invalidateCacheKeys(...keys)
}
