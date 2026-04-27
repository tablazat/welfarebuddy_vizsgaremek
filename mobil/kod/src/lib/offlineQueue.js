const QUEUE_KEY = "pending_uploads"
const MAX_QUEUE = 200

export function getQueue() {
  try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || "[]") } catch { return [] }
}

export function setQueue(queue) {
  try { localStorage.setItem(QUEUE_KEY, JSON.stringify(queue)) } catch {}
}

export function clearQueue() {
  try { localStorage.removeItem(QUEUE_KEY) } catch {}
}

export function enqueueRequest(url, method, payload) {
  const queue = getQueue()
  if (queue.length >= MAX_QUEUE) return //
  queue.push({ url, method, payload, timestamp: Date.now() })
  setQueue(queue)
}
