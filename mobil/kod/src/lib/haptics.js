/**
 * haptics.js – Vibráció / tapintásos visszajelzés
 *
 * Capacitor native: @capacitor/haptics (ha telepítve van)
 * Fallback: navigator.vibrate() – Android Chrome-on működik, iOS Safarion nem
 */

let _Haptics = null

async function getHaptics() {
  if (_Haptics !== null) return _Haptics
  try {
    const mod = await import("@capacitor/haptics")
    _Haptics = mod.Haptics
  } catch {
    _Haptics = false  // not available
  }
  return _Haptics
}

/**
 * Könnyű tapintás – gombnyomás, elem kijelölés
 */
export async function hapticLight() {
  const H = await getHaptics()
  if (H) {
    try { await H.impact({ style: "light" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate(30)
  }
}

/**
 * Közepesebb tapintás – sikeres mentés, confirm
 */
export async function hapticMedium() {
  const H = await getHaptics()
  if (H) {
    try { await H.impact({ style: "medium" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate(50)
  }
}

/**
 * Erős tapintás – törlés, hiba, figyelmeztetés
 */
export async function hapticHeavy() {
  const H = await getHaptics()
  if (H) {
    try { await H.impact({ style: "heavy" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate([40, 30, 40])
  }
}

/**
 * Siker – streak mérföldkő, rekord
 */
export async function hapticSuccess() {
  const H = await getHaptics()
  if (H) {
    try { await H.notification({ type: "SUCCESS" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate([30, 20, 60])
  }
}
