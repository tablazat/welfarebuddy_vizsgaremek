

let _Haptics = null

async function getHaptics() {
  if (_Haptics !== null) return _Haptics
  try {
    const mod = await import("@capacitor/haptics")
    _Haptics = mod.Haptics
  } catch {
    _Haptics = false  
  }
  return _Haptics
}


export async function hapticLight() {
  const H = await getHaptics()
  if (H) {
    try { await H.impact({ style: "light" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate(30)
  }
}


export async function hapticMedium() {
  const H = await getHaptics()
  if (H) {
    try { await H.impact({ style: "medium" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate(50)
  }
}


export async function hapticHeavy() {
  const H = await getHaptics()
  if (H) {
    try { await H.impact({ style: "heavy" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate([40, 30, 40])
  }
}


export async function hapticSuccess() {
  const H = await getHaptics()
  if (H) {
    try { await H.notification({ type: "SUCCESS" }) } catch {}
  } else if (navigator.vibrate) {
    navigator.vibrate([30, 20, 60])
  }
}
