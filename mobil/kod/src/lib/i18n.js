import { createI18n } from "vue-i18n"
import { storageGet, storageSet } from "./storage"
import hu from "@/locales/hu.js"
import en from "@/locales/en.js"
import de from "@/locales/de.js"

export const supportedLocales = [
  { code: "hu", label: "Magyar", flag: "🇭🇺" },
  { code: "en", label: "English", flag: "🇬🇧" },
  { code: "de", label: "Deutsch", flag: "🇩🇪" },
]

export function detectBrowserLocale() {
  const lang = (navigator.language || "en").slice(0, 2).toLowerCase()
  const supported = supportedLocales.map(l => l.code)
  return supported.includes(lang) ? lang : "en"
}

export function getInitialLocale() {
  const stored = storageGet("app_locale")
  if (stored && supportedLocales.some(l => l.code === stored)) return stored

  try {
    const user = JSON.parse(storageGet("auth_user") || "null")
    if (user?.locale && supportedLocales.some(l => l.code === user.locale)) return user.locale
  } catch {}

  return detectBrowserLocale()
}

export function setLocale(locale) {
  i18n.global.locale.value = locale
  storageSet("app_locale", locale)

  // Ha be van jelentkezve, frissítjük a backend-en is
  const token = storageGet("auth_token")
  if (token) {
    import("./api").then(({ default: api }) => {
      api.post("/locale", { locale }).catch(() => {})
    })
  }
}

const i18n = createI18n({
  legacy: false,
  locale: getInitialLocale(),
  fallbackLocale: "en",
  messages: { hu, en, de },
})

export default i18n
