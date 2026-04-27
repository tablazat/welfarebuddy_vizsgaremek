import i18n from "@/lib/i18n"

export function getAxiosErrorMessage(e, fallback) {
  const t = (key) => i18n.global.t(key)

  if (!e?.response) {
    if (e?.code === "ECONNABORTED" || /timeout/i.test(e?.message ?? "")) {
      return t("errors.timeout")
    }
    return t("errors.network")
  }

  const { status, data } = e.response

  if (status === 422 && data?.errors && typeof data.errors === "object") {
    const firstKey = Object.keys(data.errors)[0]
    const firstMsg = data.errors?.[firstKey]?.[0]
    if (firstMsg) return String(firstMsg)
  }

  if (typeof data?.message === "string" && data.message.length > 0) {
    return data.message
  }

  switch (status) {
    case 401: return t("errors.unauthorized")
    case 403: return t("errors.forbidden")
    case 404: return t("errors.notFound")
    case 429: return t("errors.rateLimit")
    case 500: return t("errors.server")
    case 502:
    case 503:
    case 504: return t("errors.serverDown")
    default:  return fallback ?? t("errors.unknown")
  }
}
