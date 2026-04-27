import { ref, computed, watch } from "vue"
import { storageGet, storageSet } from "@/lib/storage"

/**
 * Skin/téma rendszer – 20 egyedi skin oklch CSS változó felülírásokkal.
 * Minden skin light és dark módot is támogat.
 */

const SKIN_CSS_KEYS = [
  "primary",
  "primary-foreground",
  "accent",
  "accent-foreground",
  "sidebar",
  "sidebar-foreground",
  "sidebar-primary",
  "sidebar-primary-foreground",
  "sidebar-accent",
  "sidebar-accent-foreground",
  "ring",
  "chart-1",
  "chart-2",
  "chart-3",
]

const skins = [
  // ═══════════════════════════════════════════
  //  FREE SKINS (4)
  // ═══════════════════════════════════════════
  {
    id: "default",
    name: "Default",
    icon: "🎨",
    category: "free",
    unlock: { type: "free" },
    light: {},
    dark: {},
  },
  {
    id: "ocean",
    name: "Ocean",
    icon: "🌊",
    category: "free",
    unlock: { type: "free" },
    light: {
      "primary": "oklch(0.45 0.18 240)",
      "primary-foreground": "oklch(0.98 0.005 240)",
      "accent": "oklch(0.92 0.04 230)",
      "accent-foreground": "oklch(0.25 0.08 240)",
      "sidebar": "oklch(0.97 0.02 230)",
      "sidebar-primary": "oklch(0.50 0.20 240)",
      "sidebar-primary-foreground": "oklch(0.98 0.005 240)",
      "sidebar-accent": "oklch(0.93 0.04 230)",
      "sidebar-accent-foreground": "oklch(0.25 0.08 240)",
      "ring": "oklch(0.55 0.16 240)",
      "chart-1": "oklch(0.55 0.20 240)",
      "chart-2": "oklch(0.65 0.15 200)",
      "chart-3": "oklch(0.50 0.12 260)",
    },
    dark: {
      "primary": "oklch(0.72 0.16 235)",
      "primary-foreground": "oklch(0.18 0.04 240)",
      "accent": "oklch(0.28 0.06 235)",
      "accent-foreground": "oklch(0.90 0.04 230)",
      "sidebar": "oklch(0.18 0.04 235)",
      "sidebar-primary": "oklch(0.65 0.18 240)",
      "sidebar-primary-foreground": "oklch(0.98 0.005 240)",
      "sidebar-accent": "oklch(0.26 0.06 235)",
      "sidebar-accent-foreground": "oklch(0.90 0.04 230)",
      "ring": "oklch(0.55 0.14 240)",
      "chart-1": "oklch(0.65 0.18 240)",
      "chart-2": "oklch(0.58 0.14 200)",
      "chart-3": "oklch(0.55 0.12 270)",
    },
  },
  {
    id: "forest",
    name: "Forest",
    icon: "🌲",
    category: "free",
    unlock: { type: "free" },
    light: {
      "primary": "oklch(0.48 0.16 155)",
      "primary-foreground": "oklch(0.98 0.01 155)",
      "accent": "oklch(0.93 0.04 150)",
      "accent-foreground": "oklch(0.25 0.06 155)",
      "sidebar": "oklch(0.97 0.02 150)",
      "sidebar-primary": "oklch(0.45 0.15 155)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 155)",
      "sidebar-accent": "oklch(0.93 0.04 150)",
      "sidebar-accent-foreground": "oklch(0.25 0.06 155)",
      "ring": "oklch(0.55 0.14 155)",
      "chart-1": "oklch(0.50 0.16 155)",
      "chart-2": "oklch(0.60 0.12 130)",
      "chart-3": "oklch(0.45 0.10 175)",
    },
    dark: {
      "primary": "oklch(0.70 0.15 155)",
      "primary-foreground": "oklch(0.18 0.04 155)",
      "accent": "oklch(0.28 0.06 150)",
      "accent-foreground": "oklch(0.90 0.03 150)",
      "sidebar": "oklch(0.18 0.04 150)",
      "sidebar-primary": "oklch(0.62 0.16 155)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 155)",
      "sidebar-accent": "oklch(0.26 0.06 150)",
      "sidebar-accent-foreground": "oklch(0.90 0.03 150)",
      "ring": "oklch(0.55 0.12 155)",
      "chart-1": "oklch(0.62 0.16 155)",
      "chart-2": "oklch(0.55 0.12 130)",
      "chart-3": "oklch(0.50 0.10 180)",
    },
  },
  {
    id: "sunset",
    name: "Sunset",
    icon: "🌅",
    category: "free",
    unlock: { type: "free" },
    light: {
      "primary": "oklch(0.58 0.20 30)",
      "primary-foreground": "oklch(0.98 0.01 30)",
      "accent": "oklch(0.93 0.05 25)",
      "accent-foreground": "oklch(0.30 0.08 30)",
      "sidebar": "oklch(0.97 0.03 25)",
      "sidebar-primary": "oklch(0.55 0.22 25)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 30)",
      "sidebar-accent": "oklch(0.93 0.05 25)",
      "sidebar-accent-foreground": "oklch(0.30 0.08 30)",
      "ring": "oklch(0.60 0.18 30)",
      "chart-1": "oklch(0.60 0.22 30)",
      "chart-2": "oklch(0.70 0.18 50)",
      "chart-3": "oklch(0.55 0.20 10)",
    },
    dark: {
      "primary": "oklch(0.72 0.18 28)",
      "primary-foreground": "oklch(0.20 0.04 30)",
      "accent": "oklch(0.28 0.06 25)",
      "accent-foreground": "oklch(0.90 0.04 25)",
      "sidebar": "oklch(0.18 0.04 20)",
      "sidebar-primary": "oklch(0.65 0.20 25)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 25)",
      "sidebar-accent": "oklch(0.26 0.06 25)",
      "sidebar-accent-foreground": "oklch(0.90 0.04 25)",
      "ring": "oklch(0.58 0.16 28)",
      "chart-1": "oklch(0.65 0.20 30)",
      "chart-2": "oklch(0.60 0.16 50)",
      "chart-3": "oklch(0.58 0.18 10)",
    },
  },

  // ═══════════════════════════════════════════
  //  STREAK-BASED SKINS (5)
  // ═══════════════════════════════════════════
  {
    id: "ember",
    name: "Ember",
    icon: "🔥",
    category: "streak",
    unlock: { type: "streak", days: 7 },
    light: {
      "primary": "oklch(0.55 0.24 22)",
      "primary-foreground": "oklch(0.98 0.01 22)",
      "accent": "oklch(0.92 0.06 18)",
      "accent-foreground": "oklch(0.30 0.10 22)",
      "sidebar": "oklch(0.96 0.03 18)",
      "sidebar-primary": "oklch(0.52 0.25 18)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 22)",
      "sidebar-accent": "oklch(0.92 0.06 18)",
      "sidebar-accent-foreground": "oklch(0.30 0.10 22)",
      "ring": "oklch(0.58 0.22 22)",
      "chart-1": "oklch(0.58 0.24 22)",
      "chart-2": "oklch(0.68 0.20 40)",
      "chart-3": "oklch(0.50 0.22 5)",
    },
    dark: {
      "primary": "oklch(0.70 0.20 20)",
      "primary-foreground": "oklch(0.18 0.05 20)",
      "accent": "oklch(0.26 0.07 18)",
      "accent-foreground": "oklch(0.90 0.05 18)",
      "sidebar": "oklch(0.17 0.05 15)",
      "sidebar-primary": "oklch(0.62 0.22 18)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 18)",
      "sidebar-accent": "oklch(0.25 0.07 18)",
      "sidebar-accent-foreground": "oklch(0.90 0.05 18)",
      "ring": "oklch(0.55 0.18 20)",
      "chart-1": "oklch(0.62 0.22 22)",
      "chart-2": "oklch(0.58 0.18 42)",
      "chart-3": "oklch(0.55 0.20 5)",
    },
  },
  {
    id: "amethyst",
    name: "Amethyst",
    icon: "💎",
    category: "streak",
    unlock: { type: "streak", days: 14 },
    light: {
      "primary": "oklch(0.45 0.22 300)",
      "primary-foreground": "oklch(0.98 0.01 300)",
      "accent": "oklch(0.93 0.05 295)",
      "accent-foreground": "oklch(0.28 0.10 300)",
      "sidebar": "oklch(0.97 0.03 295)",
      "sidebar-primary": "oklch(0.42 0.24 305)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 300)",
      "sidebar-accent": "oklch(0.93 0.05 295)",
      "sidebar-accent-foreground": "oklch(0.28 0.10 300)",
      "ring": "oklch(0.50 0.20 300)",
      "chart-1": "oklch(0.50 0.22 300)",
      "chart-2": "oklch(0.60 0.18 280)",
      "chart-3": "oklch(0.45 0.18 320)",
    },
    dark: {
      "primary": "oklch(0.70 0.19 298)",
      "primary-foreground": "oklch(0.18 0.06 300)",
      "accent": "oklch(0.27 0.07 295)",
      "accent-foreground": "oklch(0.90 0.04 295)",
      "sidebar": "oklch(0.17 0.06 300)",
      "sidebar-primary": "oklch(0.60 0.22 305)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 300)",
      "sidebar-accent": "oklch(0.25 0.07 295)",
      "sidebar-accent-foreground": "oklch(0.90 0.04 295)",
      "ring": "oklch(0.52 0.18 300)",
      "chart-1": "oklch(0.60 0.20 300)",
      "chart-2": "oklch(0.55 0.16 280)",
      "chart-3": "oklch(0.52 0.18 320)",
    },
  },
  {
    id: "golden",
    name: "Golden",
    icon: "⭐",
    category: "streak",
    unlock: { type: "streak", days: 30 },
    light: {
      "primary": "oklch(0.60 0.18 80)",
      "primary-foreground": "oklch(0.98 0.01 80)",
      "accent": "oklch(0.94 0.05 75)",
      "accent-foreground": "oklch(0.30 0.08 80)",
      "sidebar": "oklch(0.97 0.03 75)",
      "sidebar-primary": "oklch(0.58 0.20 75)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 80)",
      "sidebar-accent": "oklch(0.94 0.05 75)",
      "sidebar-accent-foreground": "oklch(0.30 0.08 80)",
      "ring": "oklch(0.62 0.17 80)",
      "chart-1": "oklch(0.62 0.18 80)",
      "chart-2": "oklch(0.70 0.15 60)",
      "chart-3": "oklch(0.55 0.16 95)",
    },
    dark: {
      "primary": "oklch(0.78 0.16 78)",
      "primary-foreground": "oklch(0.20 0.04 80)",
      "accent": "oklch(0.28 0.06 75)",
      "accent-foreground": "oklch(0.92 0.04 75)",
      "sidebar": "oklch(0.18 0.04 70)",
      "sidebar-primary": "oklch(0.72 0.18 75)",
      "sidebar-primary-foreground": "oklch(0.15 0.04 80)",
      "sidebar-accent": "oklch(0.26 0.06 75)",
      "sidebar-accent-foreground": "oklch(0.92 0.04 75)",
      "ring": "oklch(0.65 0.15 78)",
      "chart-1": "oklch(0.72 0.18 80)",
      "chart-2": "oklch(0.65 0.14 60)",
      "chart-3": "oklch(0.60 0.14 95)",
    },
  },
  {
    id: "platinum",
    name: "Platinum",
    icon: "🏆",
    category: "streak",
    unlock: { type: "streak", days: 60 },
    light: {
      "primary": "oklch(0.55 0.03 260)",
      "primary-foreground": "oklch(0.98 0.005 260)",
      "accent": "oklch(0.94 0.01 260)",
      "accent-foreground": "oklch(0.30 0.02 260)",
      "sidebar": "oklch(0.96 0.01 260)",
      "sidebar-primary": "oklch(0.50 0.04 260)",
      "sidebar-primary-foreground": "oklch(0.98 0.005 260)",
      "sidebar-accent": "oklch(0.94 0.01 260)",
      "sidebar-accent-foreground": "oklch(0.30 0.02 260)",
      "ring": "oklch(0.60 0.03 260)",
      "chart-1": "oklch(0.55 0.04 260)",
      "chart-2": "oklch(0.65 0.03 240)",
      "chart-3": "oklch(0.50 0.04 280)",
    },
    dark: {
      "primary": "oklch(0.82 0.03 255)",
      "primary-foreground": "oklch(0.20 0.02 260)",
      "accent": "oklch(0.28 0.02 260)",
      "accent-foreground": "oklch(0.90 0.01 255)",
      "sidebar": "oklch(0.18 0.02 260)",
      "sidebar-primary": "oklch(0.75 0.04 258)",
      "sidebar-primary-foreground": "oklch(0.15 0.02 260)",
      "sidebar-accent": "oklch(0.26 0.02 260)",
      "sidebar-accent-foreground": "oklch(0.90 0.01 255)",
      "ring": "oklch(0.62 0.03 258)",
      "chart-1": "oklch(0.75 0.04 260)",
      "chart-2": "oklch(0.68 0.03 240)",
      "chart-3": "oklch(0.60 0.04 280)",
    },
  },
  {
    id: "aurora",
    name: "Aurora",
    icon: "🌌",
    category: "streak",
    unlock: { type: "streak", days: 100 },
    light: {
      "primary": "oklch(0.52 0.18 180)",
      "primary-foreground": "oklch(0.98 0.01 180)",
      "accent": "oklch(0.93 0.05 175)",
      "accent-foreground": "oklch(0.25 0.08 180)",
      "sidebar": "oklch(0.96 0.03 175)",
      "sidebar-primary": "oklch(0.48 0.20 185)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 180)",
      "sidebar-accent": "oklch(0.93 0.05 175)",
      "sidebar-accent-foreground": "oklch(0.25 0.08 180)",
      "ring": "oklch(0.55 0.16 180)",
      "chart-1": "oklch(0.55 0.18 180)",
      "chart-2": "oklch(0.50 0.16 280)",
      "chart-3": "oklch(0.60 0.14 155)",
    },
    dark: {
      "primary": "oklch(0.72 0.16 178)",
      "primary-foreground": "oklch(0.16 0.05 180)",
      "accent": "oklch(0.25 0.07 175)",
      "accent-foreground": "oklch(0.90 0.04 175)",
      "sidebar": "oklch(0.15 0.05 178)",
      "sidebar-primary": "oklch(0.65 0.18 182)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 180)",
      "sidebar-accent": "oklch(0.24 0.07 175)",
      "sidebar-accent-foreground": "oklch(0.90 0.04 175)",
      "ring": "oklch(0.52 0.14 180)",
      "chart-1": "oklch(0.65 0.18 180)",
      "chart-2": "oklch(0.58 0.16 280)",
      "chart-3": "oklch(0.55 0.14 155)",
    },
  },

  // ═══════════════════════════════════════════
  //  PRO SKINS (8)
  // ═══════════════════════════════════════════
  {
    id: "neon",
    name: "Neon Nights",
    icon: "💜",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.55 0.28 325)",
      "primary-foreground": "oklch(0.98 0.01 325)",
      "accent": "oklch(0.92 0.07 320)",
      "accent-foreground": "oklch(0.30 0.12 325)",
      "sidebar": "oklch(0.96 0.04 320)",
      "sidebar-primary": "oklch(0.50 0.30 330)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 325)",
      "sidebar-accent": "oklch(0.92 0.07 320)",
      "sidebar-accent-foreground": "oklch(0.30 0.12 325)",
      "ring": "oklch(0.58 0.26 325)",
      "chart-1": "oklch(0.55 0.28 325)",
      "chart-2": "oklch(0.50 0.24 290)",
      "chart-3": "oklch(0.60 0.22 350)",
    },
    dark: {
      "primary": "oklch(0.72 0.25 322)",
      "primary-foreground": "oklch(0.15 0.06 325)",
      "accent": "oklch(0.24 0.10 320)",
      "accent-foreground": "oklch(0.90 0.06 320)",
      "sidebar": "oklch(0.14 0.06 325)",
      "sidebar-primary": "oklch(0.65 0.28 328)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 325)",
      "sidebar-accent": "oklch(0.22 0.10 320)",
      "sidebar-accent-foreground": "oklch(0.90 0.06 320)",
      "ring": "oklch(0.55 0.22 322)",
      "chart-1": "oklch(0.65 0.26 325)",
      "chart-2": "oklch(0.58 0.22 290)",
      "chart-3": "oklch(0.60 0.20 350)",
    },
  },
  {
    id: "sakura",
    name: "Sakura",
    icon: "🌸",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.65 0.14 350)",
      "primary-foreground": "oklch(0.98 0.01 350)",
      "accent": "oklch(0.95 0.04 345)",
      "accent-foreground": "oklch(0.35 0.08 350)",
      "sidebar": "oklch(0.97 0.03 345)",
      "sidebar-primary": "oklch(0.60 0.16 348)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 350)",
      "sidebar-accent": "oklch(0.95 0.04 345)",
      "sidebar-accent-foreground": "oklch(0.35 0.08 350)",
      "ring": "oklch(0.68 0.12 350)",
      "chart-1": "oklch(0.65 0.14 350)",
      "chart-2": "oklch(0.72 0.10 330)",
      "chart-3": "oklch(0.60 0.12 10)",
    },
    dark: {
      "primary": "oklch(0.76 0.12 348)",
      "primary-foreground": "oklch(0.20 0.04 350)",
      "accent": "oklch(0.26 0.06 345)",
      "accent-foreground": "oklch(0.92 0.04 345)",
      "sidebar": "oklch(0.17 0.04 348)",
      "sidebar-primary": "oklch(0.68 0.14 348)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 350)",
      "sidebar-accent": "oklch(0.24 0.06 345)",
      "sidebar-accent-foreground": "oklch(0.92 0.04 345)",
      "ring": "oklch(0.60 0.11 348)",
      "chart-1": "oklch(0.68 0.14 350)",
      "chart-2": "oklch(0.62 0.10 330)",
      "chart-3": "oklch(0.58 0.12 10)",
    },
  },
  {
    id: "arctic",
    name: "Arctic",
    icon: "❄️",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.58 0.10 220)",
      "primary-foreground": "oklch(0.98 0.005 220)",
      "accent": "oklch(0.95 0.03 215)",
      "accent-foreground": "oklch(0.30 0.05 220)",
      "sidebar": "oklch(0.97 0.02 215)",
      "sidebar-primary": "oklch(0.55 0.12 225)",
      "sidebar-primary-foreground": "oklch(0.98 0.005 220)",
      "sidebar-accent": "oklch(0.95 0.03 215)",
      "sidebar-accent-foreground": "oklch(0.30 0.05 220)",
      "ring": "oklch(0.62 0.08 220)",
      "chart-1": "oklch(0.58 0.10 220)",
      "chart-2": "oklch(0.65 0.08 200)",
      "chart-3": "oklch(0.52 0.10 245)",
    },
    dark: {
      "primary": "oklch(0.78 0.08 218)",
      "primary-foreground": "oklch(0.18 0.03 220)",
      "accent": "oklch(0.26 0.04 215)",
      "accent-foreground": "oklch(0.92 0.02 215)",
      "sidebar": "oklch(0.16 0.03 220)",
      "sidebar-primary": "oklch(0.70 0.10 222)",
      "sidebar-primary-foreground": "oklch(0.98 0.005 220)",
      "sidebar-accent": "oklch(0.24 0.04 215)",
      "sidebar-accent-foreground": "oklch(0.92 0.02 215)",
      "ring": "oklch(0.58 0.07 218)",
      "chart-1": "oklch(0.70 0.10 220)",
      "chart-2": "oklch(0.62 0.08 200)",
      "chart-3": "oklch(0.56 0.09 245)",
    },
  },
  {
    id: "lavender",
    name: "Lavender",
    icon: "💐",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.58 0.14 285)",
      "primary-foreground": "oklch(0.98 0.01 285)",
      "accent": "oklch(0.94 0.04 280)",
      "accent-foreground": "oklch(0.30 0.06 285)",
      "sidebar": "oklch(0.97 0.03 280)",
      "sidebar-primary": "oklch(0.55 0.16 288)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 285)",
      "sidebar-accent": "oklch(0.94 0.04 280)",
      "sidebar-accent-foreground": "oklch(0.30 0.06 285)",
      "ring": "oklch(0.62 0.12 285)",
      "chart-1": "oklch(0.58 0.14 285)",
      "chart-2": "oklch(0.65 0.10 265)",
      "chart-3": "oklch(0.52 0.12 305)",
    },
    dark: {
      "primary": "oklch(0.75 0.12 283)",
      "primary-foreground": "oklch(0.20 0.04 285)",
      "accent": "oklch(0.27 0.05 280)",
      "accent-foreground": "oklch(0.92 0.03 280)",
      "sidebar": "oklch(0.17 0.04 282)",
      "sidebar-primary": "oklch(0.65 0.14 288)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 285)",
      "sidebar-accent": "oklch(0.25 0.05 280)",
      "sidebar-accent-foreground": "oklch(0.92 0.03 280)",
      "ring": "oklch(0.58 0.10 283)",
      "chart-1": "oklch(0.65 0.14 285)",
      "chart-2": "oklch(0.58 0.10 265)",
      "chart-3": "oklch(0.55 0.12 305)",
    },
  },
  {
    id: "midnight",
    name: "Midnight Gold",
    icon: "✨",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.40 0.08 265)",
      "primary-foreground": "oklch(0.95 0.04 80)",
      "accent": "oklch(0.95 0.04 75)",
      "accent-foreground": "oklch(0.28 0.06 265)",
      "sidebar": "oklch(0.20 0.05 265)",
      "sidebar-foreground": "oklch(0.88 0.04 265)",
      "sidebar-primary": "oklch(0.78 0.14 78)",
      "sidebar-primary-foreground": "oklch(0.18 0.04 265)",
      "sidebar-accent": "oklch(0.28 0.04 265)",
      "sidebar-accent-foreground": "oklch(0.90 0.06 78)",
      "ring": "oklch(0.68 0.12 78)",
      "chart-1": "oklch(0.72 0.14 78)",
      "chart-2": "oklch(0.42 0.08 265)",
      "chart-3": "oklch(0.62 0.10 58)",
    },
    dark: {
      "primary": "oklch(0.80 0.14 78)",
      "primary-foreground": "oklch(0.15 0.04 260)",
      "accent": "oklch(0.22 0.04 258)",
      "accent-foreground": "oklch(0.85 0.10 78)",
      "sidebar": "oklch(0.13 0.03 260)",
      "sidebar-primary": "oklch(0.78 0.16 78)",
      "sidebar-primary-foreground": "oklch(0.15 0.04 260)",
      "sidebar-accent": "oklch(0.20 0.04 258)",
      "sidebar-accent-foreground": "oklch(0.85 0.10 78)",
      "ring": "oklch(0.65 0.12 78)",
      "chart-1": "oklch(0.78 0.16 80)",
      "chart-2": "oklch(0.50 0.06 260)",
      "chart-3": "oklch(0.65 0.12 60)",
    },
  },
  {
    id: "rosegold",
    name: "Rose Gold",
    icon: "🌹",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.62 0.12 25)",
      "primary-foreground": "oklch(0.98 0.01 25)",
      "accent": "oklch(0.94 0.04 20)",
      "accent-foreground": "oklch(0.35 0.06 25)",
      "sidebar": "oklch(0.97 0.03 20)",
      "sidebar-primary": "oklch(0.58 0.14 22)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 25)",
      "sidebar-accent": "oklch(0.94 0.04 20)",
      "sidebar-accent-foreground": "oklch(0.35 0.06 25)",
      "ring": "oklch(0.65 0.10 25)",
      "chart-1": "oklch(0.62 0.12 25)",
      "chart-2": "oklch(0.70 0.10 45)",
      "chart-3": "oklch(0.58 0.14 5)",
    },
    dark: {
      "primary": "oklch(0.76 0.10 23)",
      "primary-foreground": "oklch(0.20 0.04 25)",
      "accent": "oklch(0.26 0.05 20)",
      "accent-foreground": "oklch(0.90 0.04 20)",
      "sidebar": "oklch(0.17 0.04 22)",
      "sidebar-primary": "oklch(0.68 0.12 22)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 25)",
      "sidebar-accent": "oklch(0.24 0.05 20)",
      "sidebar-accent-foreground": "oklch(0.90 0.04 20)",
      "ring": "oklch(0.60 0.09 23)",
      "chart-1": "oklch(0.68 0.12 25)",
      "chart-2": "oklch(0.62 0.10 45)",
      "chart-3": "oklch(0.58 0.12 5)",
    },
  },
  {
    id: "coral",
    name: "Coral Reef",
    icon: "🐠",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.62 0.18 15)",
      "primary-foreground": "oklch(0.98 0.01 15)",
      "accent": "oklch(0.92 0.06 175)",
      "accent-foreground": "oklch(0.30 0.08 175)",
      "sidebar": "oklch(0.96 0.03 175)",
      "sidebar-primary": "oklch(0.58 0.20 12)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 15)",
      "sidebar-accent": "oklch(0.92 0.06 175)",
      "sidebar-accent-foreground": "oklch(0.30 0.08 175)",
      "ring": "oklch(0.60 0.16 15)",
      "chart-1": "oklch(0.62 0.18 15)",
      "chart-2": "oklch(0.55 0.14 175)",
      "chart-3": "oklch(0.68 0.16 35)",
    },
    dark: {
      "primary": "oklch(0.74 0.16 13)",
      "primary-foreground": "oklch(0.18 0.04 15)",
      "accent": "oklch(0.25 0.06 175)",
      "accent-foreground": "oklch(0.88 0.05 175)",
      "sidebar": "oklch(0.16 0.04 178)",
      "sidebar-primary": "oklch(0.65 0.18 12)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 15)",
      "sidebar-accent": "oklch(0.23 0.06 175)",
      "sidebar-accent-foreground": "oklch(0.88 0.05 175)",
      "ring": "oklch(0.55 0.14 13)",
      "chart-1": "oklch(0.65 0.18 15)",
      "chart-2": "oklch(0.52 0.14 175)",
      "chart-3": "oklch(0.60 0.16 35)",
    },
  },
  {
    id: "mocha",
    name: "Mocha",
    icon: "☕",
    category: "pro",
    unlock: { type: "pro" },
    light: {
      "primary": "oklch(0.42 0.10 45)",
      "primary-foreground": "oklch(0.98 0.01 45)",
      "accent": "oklch(0.95 0.03 40)",
      "accent-foreground": "oklch(0.28 0.06 45)",
      "sidebar": "oklch(0.97 0.02 40)",
      "sidebar-primary": "oklch(0.40 0.12 42)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 45)",
      "sidebar-accent": "oklch(0.94 0.03 40)",
      "sidebar-accent-foreground": "oklch(0.28 0.06 45)",
      "ring": "oklch(0.48 0.08 45)",
      "chart-1": "oklch(0.45 0.10 45)",
      "chart-2": "oklch(0.55 0.10 30)",
      "chart-3": "oklch(0.40 0.08 65)",
    },
    dark: {
      "primary": "oklch(0.72 0.08 53)",
      "primary-foreground": "oklch(0.20 0.04 55)",
      "accent": "oklch(0.27 0.05 50)",
      "accent-foreground": "oklch(0.90 0.03 50)",
      "sidebar": "oklch(0.17 0.04 52)",
      "sidebar-primary": "oklch(0.65 0.10 52)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 55)",
      "sidebar-accent": "oklch(0.25 0.05 50)",
      "sidebar-accent-foreground": "oklch(0.90 0.03 50)",
      "ring": "oklch(0.55 0.07 53)",
      "chart-1": "oklch(0.65 0.10 55)",
      "chart-2": "oklch(0.58 0.08 35)",
      "chart-3": "oklch(0.50 0.08 75)",
    },
  },

  // ═══════════════════════════════════════════
  //  MILESTONE SKINS (3)
  // ═══════════════════════════════════════════
  {
    id: "og",
    name: "OG",
    icon: "🏅",
    category: "milestone",
    unlock: { type: "accountAge", days: 90 },
    light: {
      "primary": "oklch(0.52 0.14 55)",
      "primary-foreground": "oklch(0.98 0.01 55)",
      "accent": "oklch(0.95 0.04 50)",
      "accent-foreground": "oklch(0.30 0.08 55)",
      "sidebar": "oklch(0.96 0.03 50)",
      "sidebar-primary": "oklch(0.50 0.16 52)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 55)",
      "sidebar-accent": "oklch(0.94 0.04 50)",
      "sidebar-accent-foreground": "oklch(0.30 0.08 55)",
      "ring": "oklch(0.56 0.12 55)",
      "chart-1": "oklch(0.52 0.14 55)",
      "chart-2": "oklch(0.60 0.12 35)",
      "chart-3": "oklch(0.48 0.10 75)",
    },
    dark: {
      "primary": "oklch(0.74 0.12 63)",
      "primary-foreground": "oklch(0.20 0.04 65)",
      "accent": "oklch(0.27 0.05 60)",
      "accent-foreground": "oklch(0.90 0.04 60)",
      "sidebar": "oklch(0.18 0.04 62)",
      "sidebar-primary": "oklch(0.68 0.14 62)",
      "sidebar-primary-foreground": "oklch(0.98 0.01 65)",
      "sidebar-accent": "oklch(0.25 0.05 60)",
      "sidebar-accent-foreground": "oklch(0.90 0.04 60)",
      "ring": "oklch(0.58 0.10 63)",
      "chart-1": "oklch(0.68 0.14 65)",
      "chart-2": "oklch(0.60 0.10 45)",
      "chart-3": "oklch(0.55 0.12 85)",
    },
  },
  {
    id: "veteran",
    name: "Veteran",
    icon: "🎖️",
    category: "milestone",
    unlock: { type: "accountAge", days: 180 },
    light: {
      "primary": "oklch(0.45 0.12 150)",
      "primary-foreground": "oklch(0.98 0.01 150)",
      "accent": "oklch(0.94 0.03 145)",
      "accent-foreground": "oklch(0.28 0.08 150)",
      "sidebar": "oklch(0.25 0.06 150)",
      "sidebar-foreground": "oklch(0.88 0.04 150)",
      "sidebar-primary": "oklch(0.75 0.12 80)",
      "sidebar-primary-foreground": "oklch(0.20 0.04 150)",
      "sidebar-accent": "oklch(0.30 0.05 150)",
      "sidebar-accent-foreground": "oklch(0.90 0.05 80)",
      "ring": "oklch(0.55 0.10 150)",
      "chart-1": "oklch(0.50 0.12 150)",
      "chart-2": "oklch(0.65 0.12 80)",
      "chart-3": "oklch(0.42 0.10 170)",
    },
    dark: {
      "primary": "oklch(0.70 0.10 123)",
      "primary-foreground": "oklch(0.20 0.04 125)",
      "accent": "oklch(0.25 0.05 80)",
      "accent-foreground": "oklch(0.85 0.06 80)",
      "sidebar": "oklch(0.16 0.04 125)",
      "sidebar-primary": "oklch(0.75 0.14 80)",
      "sidebar-primary-foreground": "oklch(0.18 0.04 125)",
      "sidebar-accent": "oklch(0.22 0.05 125)",
      "sidebar-accent-foreground": "oklch(0.85 0.06 80)",
      "ring": "oklch(0.60 0.10 80)",
      "chart-1": "oklch(0.60 0.10 125)",
      "chart-2": "oklch(0.72 0.14 80)",
      "chart-3": "oklch(0.52 0.08 145)",
    },
  },
  {
    id: "founder",
    name: "Founder",
    icon: "👑",
    category: "milestone",
    unlock: { type: "accountAge", days: 365 },
    light: {
      "primary": "oklch(0.42 0.14 280)",
      "primary-foreground": "oklch(0.98 0.02 80)",
      "accent": "oklch(0.94 0.04 75)",
      "accent-foreground": "oklch(0.30 0.10 280)",
      "sidebar": "oklch(0.22 0.08 280)",
      "sidebar-foreground": "oklch(0.88 0.04 280)",
      "sidebar-primary": "oklch(0.80 0.14 75)",
      "sidebar-primary-foreground": "oklch(0.18 0.06 280)",
      "sidebar-accent": "oklch(0.28 0.07 280)",
      "sidebar-accent-foreground": "oklch(0.92 0.06 75)",
      "ring": "oklch(0.72 0.12 75)",
      "chart-1": "oklch(0.72 0.14 75)",
      "chart-2": "oklch(0.42 0.14 280)",
      "chart-3": "oklch(0.68 0.10 55)",
    },
    dark: {
      "primary": "oklch(0.82 0.15 78)",
      "primary-foreground": "oklch(0.15 0.06 275)",
      "accent": "oklch(0.22 0.08 272)",
      "accent-foreground": "oklch(0.88 0.10 78)",
      "sidebar": "oklch(0.13 0.06 275)",
      "sidebar-primary": "oklch(0.80 0.16 78)",
      "sidebar-primary-foreground": "oklch(0.15 0.06 275)",
      "sidebar-accent": "oklch(0.20 0.08 272)",
      "sidebar-accent-foreground": "oklch(0.88 0.10 78)",
      "ring": "oklch(0.68 0.14 78)",
      "chart-1": "oklch(0.80 0.16 80)",
      "chart-2": "oklch(0.48 0.14 275)",
      "chart-3": "oklch(0.68 0.12 60)",
    },
  },
]

/**
 * Skin rendszer composable.
 * @param {import('vue').Ref|import('vue').ComputedRef} authUser - reaktív user objektum
 */
export function useSkins(authUser) {
  const activeSkinId = ref(storageGet("app_skin") || "default")

  const activeSkin = computed(() => {
    return skins.find((s) => s.id === activeSkinId.value) || skins[0]
  })

  /**
   * Ellenőrzi, hogy a felhasználó feloldotta-e a skint.
   */
  function isUnlocked(skin, user) {
    if (!skin?.unlock) return false

    const u = user || authUser?.value

    // Admin-oknak minden skin elérhető
    if (u?.level_of_access === "admin") return true

    const unlock = skin.unlock

    switch (unlock.type) {
      case "free":
        return true

      case "pro":
        return u?.level_of_access === "pro" || u?.level_of_access === "admin"

      case "streak": {
        // Egyszer megszerzett rekord megmarad — `max_days` (user.max_days) ÉS a
        // jelenlegi `streak.days` közül a nagyobb. Régen csak `streak.current_streak`-et
        // nézte, ami nem létezik a streak modellen (a mező neve `days`).
        const cur = Number(u?.streak?.days ?? 0)
        const max = Number(u?.max_days ?? 0)
        return Math.max(cur, max) >= (unlock.days || 0)
      }

      case "entries": {
        // Placeholder – ha nincs entries szám a user-en, false
        const total = u?.total_entries ?? 0
        return total >= (unlock.count || 0)
      }

      case "accountAge": {
        if (!u?.created_at) return false
        const created = new Date(u.created_at)
        const now = new Date()
        const diffDays = Math.floor((now - created) / (1000 * 60 * 60 * 24))
        return diffDays >= (unlock.days || 0)
      }

      case "proStreak": {
        const isPro =
          u?.level_of_access === "pro" || u?.level_of_access === "admin"
        const cur = Number(u?.streak?.days ?? 0)
        const max = Number(u?.max_days ?? 0)
        return isPro && Math.max(cur, max) >= (unlock.days || 0)
      }

      default:
        return false
    }
  }

  /**
   * Eltávolítja az összes skin CSS változót a <html> elemről.
   */
  function removeSkinStyles() {
    const el = document.documentElement
    for (const key of SKIN_CSS_KEYS) {
      el.style.removeProperty(`--${key}`)
    }
    // Remove any skin-* CSS class from <html>
    const toRemove = [...el.classList].filter(c => c.startsWith("skin-"))
    toRemove.forEach(c => el.classList.remove(c))
  }

  /**
   * Alkalmazza a kiválasztott skint – CSS változókat ír a <html>-re.
   */
  function applySkin(skinId) {
    applyingSkin = true
    activeSkinId.value = skinId
    storageSet("app_skin", skinId)

    const skin = skins.find((s) => s.id === skinId)
    const el = document.documentElement

    // Előző skin törlése
    removeSkinStyles()

    // Default skin = nincs felülírás
    if (!skin || skin.id === "default") {
      applyingSkin = false
      return
    }

    // Aktuális mód (dark/light)
    const isDark = el.classList.contains("dark")
    const vars = isDark ? skin.dark : skin.light

    if (!vars) {
      applyingSkin = false
      return
    }

    // CSS változók alkalmazása
    for (const [key, value] of Object.entries(vars)) {
      el.style.setProperty(`--${key}`, value)
    }

    // Skin CSS class hozzáadása az effektekhez
    el.classList.add(`skin-${skinId}`)
    applyingSkin = false
  }

  /**
   * Dark/light mód váltáskor újra kell alkalmazni a skint,
   * mert a szín paletta módtól függ.
   */
  let applyingSkin = false
  function reapplySkin() {
    applySkin(activeSkinId.value)
  }

  // Induláskor alkalmazzuk a mentett skint
  applySkin(activeSkinId.value)

  // Dark mód változás figyelése (csak dark class változásra reagáljon, ne a skin class-ra)
  let lastDarkState = document.documentElement.classList.contains("dark")
  const observer = new MutationObserver((mutations) => {
    if (applyingSkin) return
    for (const m of mutations) {
      if (m.attributeName === "class") {
        const nowDark = document.documentElement.classList.contains("dark")
        if (nowDark !== lastDarkState) {
          lastDarkState = nowDark
          reapplySkin()
        }
      }
    }
  })
  observer.observe(document.documentElement, { attributes: true })

  return {
    skins,
    activeSkinId,
    activeSkin,
    isUnlocked,
    applySkin,
    removeSkinStyles,
    reapplySkin,
  }
}
