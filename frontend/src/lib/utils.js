import { clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

// Activity név locale szerint, fallback chain.
// Az `activities` táblában `name_hu`, `name_en`, `name_de` oszlopok – egyik
// nyelven sem feltétlenül van meg minden sport. Ha a kért locale fordítása
// hiányzik, előbb angolra, majd magyarra esünk vissza.
export function localizedActivityName(act, locale = "en") {
  if (!act) return ""
  return act[`name_${locale}`] || act.name_en || act.name_hu || ""
}

// Progress tracker motivációs üzenet választó.
// `{abs}` és `{days}` placeholdert manuálisan helyettesíti — vue-i18n `tm()` nyers
// stringet ad, nem interpolál (ahhoz `t(key, params)` kéne, de tömbre nem megy).
export function pickMotivationalMessage(progress, tm) {
  if (!progress) return ""
  const pick = (key) => {
    const arr = tm(`progress.${key}`)
    if (!Array.isArray(arr) || arr.length === 0) return ""
    return arr[Math.floor(Math.random() * arr.length)]
  }
  const fill = (s) => String(s)
    .replace("{abs}", Math.abs(progress.weight_delta ?? 0).toFixed(1))
    .replace("{days}", String(progress.streak_current ?? 0))
  if ((progress.entries_total ?? 0) < 5) return fill(pick("messagesNewbie"))
  const delta = progress.weight_delta
  if (typeof delta === "number") {
    if (delta < -0.5) return fill(pick("messagesWeightLoss"))
    if (delta > 0.5) return fill(pick("messagesWeightGain"))
  }
  const cur = progress.streak_current ?? 0
  const max = progress.streak_max ?? 0
  if (max > 0 && cur >= max * 0.8) return fill(pick("messagesStreakHigh"))
  return fill(pick("messagesSteady"))
}
