
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
  
  return localStorage.getItem(key) ?? sessionStorage.getItem(key) ?? null
}

export function storageRemove(key) {
  localStorage.removeItem(key)
  sessionStorage.removeItem(key)
}


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
