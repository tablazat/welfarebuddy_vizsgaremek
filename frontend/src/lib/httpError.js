export function getAxiosErrorMessage(e, fallback) {
  const err = e
  const data = err?.response?.data
  if (data?.message) return String(data.message)
  if (data?.errors) {
    const k = Object.keys(data.errors)[0]
    const first = data.errors?.[k]?.[0]
    if (first) return String(first)
  }
  if (err?.response?.status) return `Error (${err.response.status})`
  return fallback ?? "Network error."
}
