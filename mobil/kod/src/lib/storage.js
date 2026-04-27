export function storageGet(key) {
  try { return localStorage.getItem(key) } catch { return null }
}

export function storageSet(key, value) {
  try { localStorage.setItem(key, value) } catch {}
}

export function storageRemove(key) {
  try { localStorage.removeItem(key) } catch {}
}
