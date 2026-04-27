/**
 * Storage wrapper – cookie consent alapján dönt:
 *  - "accepted" → localStorage (megmarad)
 *  - bármi más  → sessionStorage (böngésző bezáráskor törlődik)
 *
 * A cookie_consent értéket MINDIG localStorage-ban tároljuk,
 * hogy ne kérdezze újra minden alkalommal.
 */

function accepted() {
  return localStorage.getItem("cookie_consent") === "accepted"
}

function store() {
  return accepted() ? localStorage : sessionStorage
}

export function storageSet(key, value) {
  store().setItem(key, value)
}

export function storageGet(key) {
  // Először a localStorage-ban nézünk (ha régebben accepted-del mentette),
  // utána sessionStorage-ban
  return localStorage.getItem(key) ?? sessionStorage.getItem(key) ?? null
}

export function storageRemove(key) {
  localStorage.removeItem(key)
  sessionStorage.removeItem(key)
}

/**
 * Hívandó, ha a user elfogadja a cookie-kat:
 * átemeli a sessionStorage-ból localStorage-ba.
 */
export function migrateToLocalStorage() {
  const token = sessionStorage.getItem("auth_token")
  const user = sessionStorage.getItem("auth_user")
  if (token) {
    localStorage.setItem("auth_token", token)
    sessionStorage.removeItem("auth_token")
  }
  if (user) {
    localStorage.setItem("auth_user", user)
    sessionStorage.removeItem("auth_user")
  }
}

/**
 * Hívandó, ha a user elutasítja:
 * localStorage-ból átemeli sessionStorage-ba.
 */
export function migrateToSessionStorage() {
  const token = localStorage.getItem("auth_token")
  const user = localStorage.getItem("auth_user")
  if (token) {
    sessionStorage.setItem("auth_token", token)
    localStorage.removeItem("auth_token")
  }
  if (user) {
    sessionStorage.setItem("auth_user", user)
    localStorage.removeItem("auth_user")
  }
}
