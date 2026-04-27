import { ref, watch } from "vue"

const theme = ref(localStorage.getItem("theme") || "system")

function applyTheme(value) {
  const root = document.documentElement
  if (value === "dark" || (value === "system" && window.matchMedia("(prefers-color-scheme: dark)").matches)) {
    root.classList.add("dark")
  } else {
    root.classList.remove("dark")
  }
}


if (typeof window !== "undefined") {
  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
    if (theme.value === "system") applyTheme("system")
  })
}

watch(theme, (v) => {
  localStorage.setItem("theme", v)
  applyTheme(v)
}, { immediate: true })

export function useTheme() {
  return { theme }
}


export function initTheme() {
  applyTheme(theme.value)
}
