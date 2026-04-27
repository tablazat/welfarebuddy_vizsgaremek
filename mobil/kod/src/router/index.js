import { createRouter, createWebHistory } from "vue-router"
import { storageGet, storageSet } from "@/lib/storage"
import AppShell from "@/layouts/AppShell.vue"
import api from "@/lib/api"

const routes = [
  {
    path: "/",
    redirect: "/auth",
  },
  {
    path: "/auth",
    name: "auth",
    component: () => import("@/views/Auth.vue"),
    meta: { guestOnly: true },
  },
  {
    path: "/verify-email",
    name: "verify-email",
    component: () => import("@/views/VerifyEmail.vue"),
  },
  {
    path: "/reset-password",
    name: "reset-password",
    component: () => import("@/views/ResetPassword.vue"),
    meta: { guestOnly: true },
  },
  {
    path: "/legal",
    name: "legal",
    component: () => import("@/views/Legal.vue"),
  },
  {
    path: "/app",
    component: AppShell,
    meta: { requiresAuth: true, requiresVerified: true },
    children: [
      { path: "", name: "dashboard", component: () => import("@/views/Dashboard.vue") },
      { path: "diary", name: "diary", component: () => import("@/views/Diary.vue") },
      { path: "stats", name: "stats", component: () => import("@/views/Stats.vue") },
      { path: "exercise", name: "exercise", component: () => import("@/views/Exercise.vue") },
      { path: "summary", name: "summary", component: () => import("@/views/Summary.vue") },
      { path: "profile", name: "profile", component: () => import("@/views/Profile.vue") },
      { path: "settings", name: "settings", component: () => import("@/views/Settings.vue") },
      { path: "upgrade", name: "upgrade", component: () => import("@/views/Upgrade.vue") },
      { path: "skins", name: "skins", component: () => import("@/views/Skins.vue") },
      { path: "admin", name: "admin", component: () => import("@/views/Admin.vue") },
      { path: "health-sync", name: "health-sync", component: () => import("@/views/HealthSync.vue") },
    ],
  },
  {
    path: "/:pathMatch(.*)*",
    redirect: "/auth",
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

let refreshedOnce = false
async function refreshUserOnce() {
  if (refreshedOnce) return null
  refreshedOnce = true
  try {
    const { data } = await api.get("/token-check")
    if (data?.user) {
      storageSet("auth_user", JSON.stringify(data.user))
      return data.user
    }
  } catch {}
  return null
}

router.beforeEach(async (to) => {
  const token = storageGet("auth_token")
  let user = JSON.parse(storageGet("auth_user") || "null")

  if (to.meta.requiresAuth && !token) {
    return { name: "auth", query: { redirect: to.fullPath, tab: "login" } }
  }

  if (to.meta.guestOnly && token) {
    if (user && !user.email_verified_at) {
      const fresh = await refreshUserOnce()
      if (fresh) user = fresh
      if (user && !user.email_verified_at) return { name: "verify-email" }
    }
    return { name: "dashboard" }
  }

  if (to.meta.requiresVerified && token && user && !user.email_verified_at) {
    const fresh = await refreshUserOnce()
    if (fresh?.email_verified_at) return
    return { name: "verify-email" }
  }

  if (to.name === "verify-email" && user?.email_verified_at) {
    return { name: "dashboard" }
  }
})

export default router
