// Dane wersji demonstracyjnej (GitHub Pages). Stan trzymamy w localStorage,
// więc każdy odwiedzający pracuje na własnej kopii danych.
import seed from './seed.json'

const STORAGE_KEY = 'najmuj:demo-db'
const DEMO_STATUSES = { 46: 'pending', 47: 'pending', 48: 'draft', 49: 'rejected' }

function createDb() {
  const db = structuredClone(seed)

  // Kilka ofert w innych statusach, żeby panel właściciela i moderacja nie były puste.
  db.properties.forEach((p, index) => {
    const status = DEMO_STATUSES[index]
    if (!status) return
    p.status = status
    p.status_label = { pending: 'Oczekuje', draft: 'Szkic', rejected: 'Odrzucona' }[status]
    p.published_at = null
    if (status === 'rejected') p.rejected_reason = 'Dodaj więcej zdjęć łazienki i kuchni.'
  })

  db.nextId = 10000
  return db
}

function load() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY))
    if (saved?.properties) {
      // Zdjęcia dodane w poprzedniej sesji (blob:) nie przetrwają przeładowania strony.
      saved.properties.forEach(p => {
        p.images = (p.images || []).filter(img => !img.url?.startsWith('blob:'))
      })
      return saved
    }
  } catch {
    // Uszkodzone dane – zaczynamy od nowa.
  }
  return createDb()
}

export const db = load()

export function save() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(db))
  } catch {
    // Brak miejsca lub zablokowany storage – demo działa dalej w pamięci.
  }
}

export function resetDb() {
  localStorage.removeItem(STORAGE_KEY)
  localStorage.removeItem('token')
}

export const nextId = () => ++db.nextId
