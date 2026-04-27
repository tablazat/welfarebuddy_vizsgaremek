const UPLOADED_IDS_KEY = "health_uploaded_ids"

export function getUploadedIds() {
  return new Set(JSON.parse(localStorage.getItem(UPLOADED_IDS_KEY) || "[]"))
}

export function markAsUploaded(ids) {
  const existing = getUploadedIds()
  for (const id of ids) existing.add(id)
  localStorage.setItem(UPLOADED_IDS_KEY, JSON.stringify([...existing]))
}

export function makeRecordId(dataType, startDate) {
  const d = startDate instanceof Date ? startDate : new Date(startDate)
  return `${dataType}_${d.toISOString()}`
}
