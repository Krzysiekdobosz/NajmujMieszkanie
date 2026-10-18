// Odpowiednik REST API z Laravela działający w przeglądarce. Zwraca odpowiedzi
// w tym samym formacie co backend, dzięki czemu widoki nie wiedzą, że to demo.
import { db, save, nextId } from './db'
import { buildContractPdf } from './contractPdf'

const BASE = import.meta.env.BASE_URL

const LABELS = {
  property: { draft: 'Szkic', pending: 'Oczekuje', published: 'Opublikowana', rejected: 'Odrzucona', archived: 'Zarchiwizowana' },
  viewing: { pending: 'Oczekujące', accepted: 'Zaakceptowane', rejected: 'Odrzucone', cancelled: 'Anulowane', completed: 'Zakończone' },
  contract: { draft: 'Szkic', generated: 'Wygenerowana', signed: 'Podpisana' },
  payment: { pending: 'Oczekuje', paid: 'Opłacone', failed: 'Nieudane', cancelled: 'Anulowane' },
  paymentType: { rent: 'Czynsz', deposit: 'Kaucja', extra_fee: 'Opłata dodatkowa' },
  contact: { new: 'Nowe', in_progress: 'W trakcie', closed: 'Zamknięte' },
  furnishing: { furnished: 'Umeblowane', partially_furnished: 'Częściowo umeblowane', unfurnished: 'Nieumeblowane' },
  rentalPeriod: {
    minimum_1_month: 'Minimum 1 miesiąc',
    minimum_3_months: 'Minimum 3 miesiące',
    minimum_6_months: 'Minimum 6 miesięcy',
    minimum_12_months: 'Minimum 12 miesięcy',
    indefinite: 'Bezterminowo',
  },
}

export class HttpError extends Error {
  constructor(status, message, errors) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

const ok = (data = null, message = 'OK', status = 200) => ({ status, data: { success: true, message, data } })
const created = (data, message = 'Zasób został utworzony.') => ok(data, message, 201)
const noContent = () => ({ status: 204, data: null })
const invalid = (errors) => {
  throw new HttpError(422, 'Dane są nieprawidłowe.', errors)
}
const notFound = (message = 'Zasób nie został znaleziony.') => {
  throw new HttpError(404, message)
}

const now = () => new Date().toISOString()
const toInt = (v) => Number.parseInt(v, 10)

function paginate(items, query, perPage = 15) {
  const page = Math.max(1, toInt(query.page) || 1)
  const lastPage = Math.max(1, Math.ceil(items.length / perPage))
  const start = (page - 1) * perPage
  const slice = items.slice(start, start + perPage)
  return {
    status: 200,
    data: {
      success: true,
      data: slice,
      meta: {
        current_page: page,
        last_page: lastPage,
        per_page: perPage,
        total: items.length,
        from: slice.length ? start + 1 : null,
        to: slice.length ? start + slice.length : null,
      },
    },
  }
}

function slugify(text) {
  const map = { ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z' }
  return text
    .toLowerCase()
    .replace(/[ąćęłńóśźż]/g, (c) => map[c])
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function uniqueSlug(list, title, ignoreId) {
  const base = slugify(title) || 'oferta'
  let slug = base
  let i = 1
  while (list.some((item) => item.slug === slug && item.id !== ignoreId)) slug = `${base}-${i++}`
  return slug
}

// ─── Użytkownicy i autoryzacja ─────────────────────────────────────────────

const publicUser = ({ password, ...user }) => user

function currentUser(headers) {
  const token = (headers.Authorization || headers.authorization || '').replace('Bearer ', '')
  if (!token.startsWith('demo|')) return null
  return db.users.find((u) => u.id === toInt(token.slice(5))) || null
}

function requireUser(ctx) {
  if (!ctx.user) throw new HttpError(401, 'Unauthenticated.')
  return ctx.user
}

function requireRole(ctx, ...roles) {
  const user = requireUser(ctx)
  if (!roles.includes(user.role)) throw new HttpError(403, 'Nie masz uprawnień do tej akcji.')
  return user
}

function logActivity(user, action, description) {
  db.activityLogs.unshift({
    id: nextId(),
    action,
    description,
    subject_type: null,
    subject_id: null,
    ip_address: '127.0.0.1',
    user: user ? { id: user.id, name: user.name } : null,
    created_at: now(),
  })
  db.activityLogs = db.activityLogs.slice(0, 200)
}

function notify(userId, type, title, body, data = {}) {
  db.notifications.unshift({ id: nextId(), user_id: userId, type, title, body, data, is_read: false, read_at: null, created_at: now() })
}

// ─── Oferty ────────────────────────────────────────────────────────────────

const assetUrl = (url) => (!url || /^(https?:|blob:|data:)/.test(url) ? url : `${BASE}${url.replace(/^\//, '')}`)

function propertyResource(p, user) {
  const images = (p.images || []).map((img) => ({ ...img, url: assetUrl(img.url) }))
  const main = images.find((img) => img.is_main) || images[0]
  const resource = {
    ...p,
    images,
    main_image: main?.url ?? null,
    main_image_url: main?.url ?? null,
    favorites_count: db.favorites.filter((f) => f.property_id === p.id).length,
  }
  if (user) resource.is_favorited = db.favorites.some((f) => f.user_id === user.id && f.property_id === p.id)
  return resource
}

const findProperty = (id) => db.properties.find((p) => p.id === toInt(id)) || notFound()

function ownedProperty(ctx, id) {
  const property = findProperty(id)
  if (property.owner?.id !== ctx.user.id && ctx.user.role !== 'admin') notFound()
  return property
}

const bool = (v) => v === true || v === 'true' || v === '1' || v === 1

function filterProperties(query) {
  const has = (key) => query[key] !== undefined && query[key] !== null && query[key] !== ''
  let list = db.properties.filter((p) => p.status === 'published')

  if (has('type')) list = list.filter((p) => p.property_type === query.type)
  if (has('city')) list = list.filter((p) => p.city.toLowerCase().includes(String(query.city).toLowerCase()))
  if (has('voivodeship')) list = list.filter((p) => p.voivodeship === query.voivodeship)
  if (has('district')) list = list.filter((p) => (p.district || '').toLowerCase().includes(String(query.district).toLowerCase()))
  if (has('price_min')) list = list.filter((p) => Number(p.price) >= Number(query.price_min))
  if (has('price_max')) list = list.filter((p) => Number(p.price) <= Number(query.price_max))
  if (has('area_min')) list = list.filter((p) => Number(p.area) >= Number(query.area_min))
  if (has('area_max')) list = list.filter((p) => Number(p.area) <= Number(query.area_max))
  if (has('rooms_min')) list = list.filter((p) => (p.rooms_count ?? 0) >= Number(query.rooms_min))
  if (has('rooms_max')) list = list.filter((p) => (p.rooms_count ?? 0) <= Number(query.rooms_max))
  if (has('furnishing')) list = list.filter((p) => p.furnishing === query.furnishing)
  if (has('pets')) list = list.filter((p) => p.pets_allowed === bool(query.pets))
  if (has('smoking')) list = list.filter((p) => p.smoking_allowed === bool(query.smoking))
  if (has('students')) list = list.filter((p) => p.students_allowed === bool(query.students))
  if (has('parking')) list = list.filter((p) => p.parking === bool(query.parking))
  if (has('available_from')) list = list.filter((p) => !p.available_from || p.available_from <= query.available_from)

  const sorters = {
    price_asc: (a, b) => a.price - b.price,
    price_desc: (a, b) => b.price - a.price,
    area_desc: (a, b) => (b.area ?? 0) - (a.area ?? 0),
  }
  const sorter = sorters[query.sort] || ((a, b) => (b.published_at || '').localeCompare(a.published_at || ''))
  return [...list].sort(sorter)
}

const PROPERTY_FIELDS = [
  'property_type', 'title', 'short_description', 'description', 'voivodeship', 'city', 'district', 'street',
  'postal_code', 'area', 'rooms_count', 'bathrooms_count', 'floor', 'total_floors', 'furnishing', 'year_built',
  'parking', 'elevator', 'balcony', 'room_area', 'apartment_area', 'roommates_count', 'total_rooms_count',
  'price', 'admin_fee', 'deposit', 'additional_costs', 'rental_period', 'available_from', 'min_rental_months',
  'pets_allowed', 'smoking_allowed', 'students_allowed', 'only_women', 'only_men', 'rules', 'notes', 'status',
]

function applyPropertyData(property, body, isNew) {
  const errors = {}
  if (isNew || 'title' in body) {
    if (!body.title || String(body.title).trim().length < 10) errors.title = ['Tytuł musi mieć co najmniej 10 znaków.']
  }
  if (isNew || 'city' in body) {
    if (!body.city) errors.city = ['Pole miasto jest wymagane.']
  }
  if (isNew || 'price' in body) {
    if (!(Number(body.price) > 0)) errors.price = ['Podaj cenę najmu.']
  }
  if (isNew && !['apartment', 'room'].includes(body.property_type)) errors.property_type = ['Wybierz typ oferty.']
  if (Object.keys(errors).length) invalid(errors)

  PROPERTY_FIELDS.forEach((key) => {
    if (key in body) property[key] = body[key] === '' ? null : body[key]
  })
  property.property_type_label = property.property_type === 'apartment' ? 'Mieszkanie' : 'Pokój'
  property.furnishing_label = LABELS.furnishing[property.furnishing] ?? null
  property.rental_period_label = LABELS.rentalPeriod[property.rental_period] ?? null
  property.status_label = LABELS.property[property.status]
  property.updated_at = now()
}

// ─── Formatowanie pozostałych zasobów ─────────────────────────────────────

const propertyRef = (p) => (p ? { id: p.id, title: p.title, slug: p.slug, city: p.city } : null)
const userRef = (u, extra = []) => (u ? Object.fromEntries([['id', u.id], ['name', u.name], ...extra.map((k) => [k, u[k]])]) : null)
const findUser = (id) => db.users.find((u) => u.id === id)

function conversationSummary(c, user) {
  const otherId = c.user_id === user.id ? c.owner_id : c.user_id
  const last = c.messages[c.messages.length - 1]
  return {
    id: c.id,
    property: c.property,
    other_user: userRef(findUser(otherId)),
    last_message: last ? { body: last.body, created_at: last.created_at } : null,
    unread_count: c.messages.filter((m) => !m.is_read && m.sender.id !== user.id).length,
    last_message_at: c.last_message_at,
  }
}

function paymentResource(p) {
  const overdue = p.status === 'pending' && p.due_date && p.due_date < new Date().toISOString().slice(0, 10)
  return { ...p, type_label: LABELS.paymentType[p.type], status_label: LABELS.payment[p.status], is_overdue: overdue }
}

function blogListItem(p) {
  const { content, meta_title, meta_description, status, created_at, ...rest } = p
  return { ...rest, author: { name: p.author?.name }, tags: p.tags.map(({ name, slug }) => ({ name, slug })) }
}

function blogCategoryRef(id) {
  const c = db.blogCategories.find((cat) => cat.id === toInt(id))
  return c ? { id: c.id, name: c.name, slug: c.slug } : null
}

// ─── Trasy ─────────────────────────────────────────────────────────────────

const routes = []
const route = (method, path, handler) => {
  const keys = []
  const pattern = new RegExp('^' + path.replace(/:(\w+)/g, (_, key) => (keys.push(key), '([^/]+)')) + '$')
  routes.push({ method, pattern, keys, handler })
}

// Autoryzacja
route('post', '/auth/login', ({ body }) => {
  const user = db.users.find((u) => u.email === String(body.email || '').toLowerCase())
  if (!user || user.password !== body.password) invalid({ email: ['Nieprawidłowy email lub hasło.'] })
  if (!user.is_active) throw new HttpError(403, 'Twoje konto zostało zablokowane.')
  logActivity(user, 'user.login', `Logowanie: ${user.email}`)
  save()
  return ok({ token: `demo|${user.id}`, user: publicUser(user) }, 'Zalogowano pomyślnie.')
})

route('post', '/auth/register', ({ body }) => {
  const errors = {}
  if (!body.name) errors.name = ['Pole imię jest wymagane.']
  if (!/^\S+@\S+\.\S+$/.test(body.email || '')) errors.email = ['Podaj poprawny adres e-mail.']
  else if (db.users.some((u) => u.email === body.email.toLowerCase())) errors.email = ['Ten adres e-mail jest już zajęty.']
  if (!body.password || body.password.length < 8) errors.password = ['Hasło musi mieć co najmniej 8 znaków.']
  else if (body.password_confirmation !== undefined && body.password !== body.password_confirmation) errors.password = ['Hasła nie są identyczne.']
  if (Object.keys(errors).length) invalid(errors)

  const user = {
    id: nextId(),
    name: body.name,
    email: body.email.toLowerCase(),
    phone: body.phone || null,
    is_active: true,
    role: body.role === 'owner' ? 'owner' : 'user',
    email_verified_at: null,
    created_at: now(),
    profile: { avatar: null, bio: null, city: null, voivodeship: null, date_of_birth: null },
    password: body.password,
  }
  db.users.push(user)
  logActivity(user, 'user.register', `Rejestracja: ${user.email}`)
  save()
  return created({ token: `demo|${user.id}`, user: publicUser(user) }, 'Konto zostało utworzone.')
})

route('post', '/auth/logout', () => ok(null, 'Wylogowano pomyślnie.'))
route('get', '/auth/me', (ctx) => ok(publicUser(requireUser(ctx))))
route('post', '/auth/forgot-password', () => ok(null, 'Jeśli konto istnieje, wyślemy link do resetowania hasła.'))
route('post', '/auth/reset-password', () => ok(null, 'Hasło zostało zmienione. Możesz się zalogować.'))

route('put', '/profile', (ctx) => {
  const user = requireUser(ctx)
  const { body } = ctx
  if (body.email && db.users.some((u) => u.email === body.email && u.id !== user.id)) {
    invalid({ email: ['Ten adres e-mail jest już zajęty.'] })
  }
  Object.assign(user, { name: body.name ?? user.name, email: body.email ?? user.email, phone: body.phone ?? null })
  user.profile = { ...user.profile, bio: body.bio ?? null, city: body.city ?? null, voivodeship: body.voivodeship ?? null, date_of_birth: body.date_of_birth ?? null }
  if (body.password) user.password = body.password
  save()
  return ok(publicUser(user), 'Profil zaktualizowany.')
})

// Oferty publiczne
route('get', '/properties', ({ query, user }) => {
  const result = paginate(filterProperties(query), query, 12)
  result.data.data = result.data.data.map((p) => propertyResource(p, user))
  return result
})

route('get', '/properties/:slug', ({ params, user }) => {
  const property = db.properties.find((p) => p.slug === params.slug && p.status === 'published') || notFound()
  property.views_count = (property.views_count || 0) + 1
  save()
  return ok(propertyResource(property, user))
})

// Blog i strony
route('get', '/blog-posts', ({ query }) => {
  let posts = db.blogPosts.filter((p) => p.status === 'published')
  if (query.category) posts = posts.filter((p) => p.category?.slug === query.category)
  if (query.tag) posts = posts.filter((p) => p.tags.some((t) => t.slug === query.tag))
  if (query.search) posts = posts.filter((p) => p.title.toLowerCase().includes(query.search.toLowerCase()))
  posts = [...posts].sort((a, b) => (b.published_at || '').localeCompare(a.published_at || ''))
  const result = paginate(posts, query, 10)
  result.data.data = result.data.data.map(blogListItem)
  return result
})

route('get', '/blog-posts/:slug', ({ params }) => {
  const post = db.blogPosts.find((p) => p.slug === params.slug && p.status === 'published') || notFound()
  const { status, created_at, ...rest } = post
  return ok({ ...rest, meta_title: post.meta_title || post.title, meta_description: post.meta_description || post.excerpt })
})

route('get', '/blog-categories', () =>
  ok(db.blogCategories.map((c) => ({
    ...c,
    posts_count: db.blogPosts.filter((p) => p.status === 'published' && p.category?.id === c.id).length,
  })))
)

route('get', '/pages/:slug', ({ params }) => {
  const page = db.pages.find((p) => p.slug === params.slug && p.status === 'published') || notFound()
  return ok({ id: page.id, title: page.title, slug: page.slug, content: page.content, meta_title: page.meta_title || page.title, meta_description: page.meta_description })
})

route('post', '/contact-messages', ({ body, user }) => {
  const errors = {}
  for (const key of ['name', 'email', 'subject', 'body']) if (!body[key]) errors[key] = ['To pole jest wymagane.']
  if (Object.keys(errors).length) invalid(errors)
  db.contactMessages.unshift({
    id: nextId(),
    name: body.name,
    email: body.email,
    subject: body.subject,
    body: body.body,
    status: 'new',
    status_label: LABELS.contact.new,
    admin_note: null,
    user: user ? userRef(user) : null,
    created_at: now(),
  })
  save()
  return created(null, 'Wiadomość została wysłana. Odpiszemy wkrótce.')
})

// Ulubione
route('get', '/favorites', (ctx) => {
  const user = requireUser(ctx)
  const ids = db.favorites.filter((f) => f.user_id === user.id).map((f) => f.property_id).reverse()
  const result = paginate(ids.map((id) => db.properties.find((p) => p.id === id)).filter(Boolean), ctx.query, 12)
  result.data.data = result.data.data.map((p) => propertyResource(p, user))
  return result
})

route('post', '/favorites', (ctx) => {
  const user = requireUser(ctx)
  const propertyId = toInt(ctx.body.property_id)
  findProperty(propertyId)
  if (db.favorites.some((f) => f.user_id === user.id && f.property_id === propertyId)) {
    return ok({ favorited: true }, 'Już jest w ulubionych.')
  }
  db.favorites.push({ user_id: user.id, property_id: propertyId })
  save()
  return created({ favorited: true }, 'Dodano do ulubionych.')
})

route('delete', '/favorites/:id', (ctx) => {
  const user = requireUser(ctx)
  db.favorites = db.favorites.filter((f) => !(f.user_id === user.id && f.property_id === toInt(ctx.params.id)))
  save()
  return noContent()
})

// Rozmowy
const findConversation = (ctx) => {
  const user = requireUser(ctx)
  const c = db.conversations.find((conv) => conv.id === toInt(ctx.params.id))
  if (!c || (c.user_id !== user.id && c.owner_id !== user.id)) notFound()
  return c
}

route('get', '/conversations', (ctx) => {
  const user = requireUser(ctx)
  const list = db.conversations
    .filter((c) => c.user_id === user.id || c.owner_id === user.id)
    .sort((a, b) => b.last_message_at.localeCompare(a.last_message_at))
    .map((c) => conversationSummary(c, user))
  return paginate(list, ctx.query, 20)
})

route('get', '/conversations/:id', (ctx) => {
  const c = findConversation(ctx)
  return ok({
    ...conversationSummary(c, ctx.user),
    messages: c.messages.map((m) => ({ ...m, is_mine: m.sender.id === ctx.user.id })),
  })
})

route('post', '/conversations', (ctx) => {
  const user = requireUser(ctx)
  const property = findProperty(ctx.body.property_id)
  if (!ctx.body.message) invalid({ message: ['Wpisz treść wiadomości.'] })
  if (property.owner?.id === user.id) throw new HttpError(422, 'Nie możesz pisać do siebie.')

  let conversation = db.conversations.find((c) => c.property.id === property.id && c.user_id === user.id)
  if (!conversation) {
    conversation = {
      id: nextId(),
      property: { id: property.id, title: property.title, slug: property.slug },
      user_id: user.id,
      owner_id: property.owner.id,
      messages: [],
      last_message_at: now(),
    }
    db.conversations.push(conversation)
  }
  conversation.messages.push({ id: nextId(), body: ctx.body.message, sender: userRef(user), is_read: false, created_at: now() })
  conversation.last_message_at = now()
  notify(conversation.owner_id, 'new_message', 'Nowa wiadomość', `Masz nową wiadomość od ${user.name}.`, { conversation_id: conversation.id })
  save()
  return created({ conversation_id: conversation.id }, 'Wiadomość wysłana.')
})

route('post', '/conversations/:id/messages', (ctx) => {
  const c = findConversation(ctx)
  if (!ctx.body.body) invalid({ body: ['Wpisz treść wiadomości.'] })
  const message = { id: nextId(), body: ctx.body.body, sender: userRef(ctx.user), is_read: false, created_at: now() }
  c.messages.push(message)
  c.last_message_at = message.created_at
  const recipient = c.user_id === ctx.user.id ? c.owner_id : c.user_id
  notify(recipient, 'new_message', 'Nowa wiadomość', `Masz nową wiadomość od ${ctx.user.name}.`, { conversation_id: c.id })
  save()
  return created({ ...message, is_mine: true }, 'Wiadomość wysłana.')
})

route('patch', '/conversations/:id/messages/read', (ctx) => {
  const c = findConversation(ctx)
  c.messages.forEach((m) => {
    if (m.sender.id !== ctx.user.id) m.is_read = true
  })
  save()
  return ok(null)
})

// Oglądania – użytkownik
const viewingForUser = (v) => ({
  id: v.id,
  property: { id: v.property.id, title: v.property.title, slug: v.property.slug, city: v.property.city },
  owner: v.owner,
  proposed_at: v.proposed_at,
  status: v.status,
  status_label: LABELS.viewing[v.status],
  note: v.note,
  owner_note: v.owner_note,
})

route('get', '/property-viewings', (ctx) => {
  const user = requireUser(ctx)
  const list = db.viewings
    .filter((v) => v.user_id === user.id && (!ctx.query.status || v.status === ctx.query.status))
    .sort((a, b) => b.proposed_at.localeCompare(a.proposed_at))
    .map(viewingForUser)
  return paginate(list, ctx.query, 20)
})

route('post', '/property-viewings', (ctx) => {
  const user = requireUser(ctx)
  const property = findProperty(ctx.body.property_id)
  const date = new Date(ctx.body.proposed_at)
  if (Number.isNaN(date.getTime()) || date <= new Date()) invalid({ proposed_at: ['Wybierz termin w przyszłości.'] })

  const conflict = db.viewings.some((v) => v.property.id === property.id && v.status === 'accepted'
    && Math.abs(new Date(v.proposed_at) - date) < 60 * 60 * 1000)
  if (conflict) throw new HttpError(422, 'W tym terminie jest już zarezerwowane oglądanie.')

  const owner = findUser(property.owner.id)
  const viewing = {
    id: nextId(),
    property: propertyRef(property),
    user: userRef(user, ['phone', 'email']),
    owner: userRef(owner, ['phone']),
    user_id: user.id,
    owner_id: owner.id,
    proposed_at: date.toISOString(),
    status: 'pending',
    status_label: LABELS.viewing.pending,
    note: ctx.body.note || null,
    owner_note: null,
  }
  db.viewings.push(viewing)
  notify(owner.id, 'new_viewing', 'Nowa rezerwacja oglądania', `${user.name} chce zobaczyć "${property.title}".`, { viewing_id: viewing.id })
  save()
  return created(viewingForUser(viewing), 'Prośba o oglądanie wysłana.')
})

route('delete', '/property-viewings/:id', (ctx) => {
  const user = requireUser(ctx)
  const viewing = db.viewings.find((v) => v.id === toInt(ctx.params.id) && v.user_id === user.id) || notFound()
  if (!['pending', 'accepted'].includes(viewing.status)) throw new HttpError(422, 'Nie można anulować tej rezerwacji.')
  viewing.status = 'cancelled'
  save()
  return ok(null, 'Rezerwacja anulowana.')
})

// Powiadomienia
route('get', '/notifications', (ctx) => {
  const user = requireUser(ctx)
  return ok(db.notifications.filter((n) => n.user_id === user.id).slice(0, 50).map(({ user_id, ...n }) => n))
})

route('post', '/notifications/read-all', (ctx) => {
  const user = requireUser(ctx)
  db.notifications.forEach((n) => {
    if (n.user_id === user.id && !n.is_read) Object.assign(n, { is_read: true, read_at: now() })
  })
  save()
  return ok(null, 'Wszystkie powiadomienia oznaczone jako przeczytane.')
})

route('patch', '/notifications/:id/read', (ctx) => {
  const user = requireUser(ctx)
  const n = db.notifications.find((item) => item.id === toInt(ctx.params.id) && item.user_id === user.id) || notFound()
  Object.assign(n, { is_read: true, read_at: n.read_at || now() })
  save()
  return ok(null)
})

// ─── Panel właściciela ─────────────────────────────────────────────────────

route('get', '/owner/properties', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const list = db.properties
    .filter((p) => p.owner?.id === user.id && (!ctx.query.status || p.status === ctx.query.status))
    .sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '') || b.id - a.id)
  const result = paginate(list, ctx.query, Math.min(toInt(ctx.query.per_page) || 15, 100))
  result.data.data = result.data.data.map((p) => propertyResource(p))
  return result
})

route('get', '/owner/properties/:id', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  return ok(propertyResource(ownedProperty(ctx, ctx.params.id)))
})

route('post', '/owner/properties', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const property = {
    id: nextId(),
    status: 'draft',
    owner: { id: user.id, name: user.name, phone: user.phone, email: user.email },
    images: [],
    views_count: 0,
    published_at: null,
    rejected_reason: null,
    created_at: now(),
  }
  applyPropertyData(property, { ...ctx.body, status: 'draft' }, true)
  property.slug = uniqueSlug(db.properties, property.title)
  db.properties.push(property)
  logActivity(user, 'property.create', `Utworzono ofertę: ${property.title}`)
  save()
  return created(propertyResource(property), 'Oferta została utworzona.')
})

route('put', '/owner/properties/:id', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  const property = ownedProperty(ctx, ctx.params.id)
  const body = { ...ctx.body }
  if (body.status && !['draft', 'pending', 'archived'].includes(body.status)) delete body.status
  applyPropertyData(property, body, false)
  if (body.status === 'pending') Object.assign(property, { published_at: null, rejected_reason: null })
  logActivity(ctx.user, 'property.update', `Zaktualizowano ofertę: ${property.title}`)
  save()
  return ok(propertyResource(property), 'Oferta została zaktualizowana.')
})

route('delete', '/owner/properties/:id', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  const property = ownedProperty(ctx, ctx.params.id)
  db.properties = db.properties.filter((p) => p.id !== property.id)
  db.favorites = db.favorites.filter((f) => f.property_id !== property.id)
  logActivity(ctx.user, 'property.delete', `Usunięto ofertę: ${property.title}`)
  save()
  return noContent()
})

route('post', '/owner/properties/:id/publish', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  const property = ownedProperty(ctx, ctx.params.id)
  if (!['draft', 'rejected'].includes(property.status)) throw new HttpError(422, 'Nie można opublikować oferty o tym statusie.')
  Object.assign(property, { status: 'pending', status_label: LABELS.property.pending })
  save()
  return ok(propertyResource(property), 'Oferta wysłana do weryfikacji.')
})

route('post', '/owner/properties/:id/images', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  const property = ownedProperty(ctx, ctx.params.id)
  const files = ctx.formData ? [...ctx.formData.values()].filter((v) => v instanceof Blob) : []
  if (!files.length) invalid({ images: ['Wybierz co najmniej jedno zdjęcie.'] })
  files.forEach((file) => {
    property.images.push({ id: nextId(), url: URL.createObjectURL(file), is_main: !property.images.length, order: property.images.length })
  })
  save()
  return created(propertyResource(property).images, 'Zdjęcia zostały dodane.')
})

route('patch', '/owner/properties/:id/images/:imageId/main', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  const property = ownedProperty(ctx, ctx.params.id)
  property.images.forEach((img) => (img.is_main = img.id === toInt(ctx.params.imageId)))
  save()
  return ok(null, 'Zdjęcie główne ustawione.')
})

route('delete', '/owner/properties/:id/images/:imageId', (ctx) => {
  requireRole(ctx, 'owner', 'admin')
  const property = ownedProperty(ctx, ctx.params.id)
  property.images = property.images.filter((img) => img.id !== toInt(ctx.params.imageId))
  if (property.images.length && !property.images.some((img) => img.is_main)) property.images[0].is_main = true
  save()
  return noContent()
})

route('get', '/owner/viewings', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const list = db.viewings
    .filter((v) => v.owner_id === user.id && (!ctx.query.status || v.status === ctx.query.status))
    .sort((a, b) => b.proposed_at.localeCompare(a.proposed_at))
    .map((v) => ({ ...viewingForUser(v), owner: undefined, user: v.user }))
  return paginate(list, ctx.query, 20)
})

route('patch', '/owner/viewings/:id/status', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const viewing = db.viewings.find((v) => v.id === toInt(ctx.params.id) && v.owner_id === user.id) || notFound()
  if (!LABELS.viewing[ctx.body.status]) invalid({ status: ['Nieprawidłowy status.'] })
  Object.assign(viewing, { status: ctx.body.status, owner_note: ctx.body.owner_note || null })
  if (['accepted', 'rejected'].includes(viewing.status)) {
    const accepted = viewing.status === 'accepted'
    notify(
      viewing.user_id,
      accepted ? 'viewing_accepted' : 'viewing_rejected',
      accepted ? 'Rezerwacja zaakceptowana' : 'Rezerwacja odrzucona',
      `Twoja rezerwacja oglądania "${viewing.property.title}" została ${accepted ? 'zaakceptowana' : 'odrzucona'}.`,
      { viewing_id: viewing.id }
    )
  }
  save()
  return ok(null, 'Status rezerwacji zaktualizowany.')
})

// Umowy
const contractResource = (c) => ({ ...c, status_label: LABELS.contract[c.status] })
const ownedContract = (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  return db.contracts.find((c) => c.id === toInt(ctx.params.id) && c.owner_id === user.id) || notFound()
}

route('get', '/owner/contracts', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const list = db.contracts
    .filter((c) => c.owner_id === user.id && (!ctx.query.status || c.status === ctx.query.status))
    .sort((a, b) => b.created_at.localeCompare(a.created_at))
    .map(contractResource)
  return paginate(list, ctx.query, 20)
})

route('get', '/owner/contracts/:id', (ctx) => ok(contractResource(ownedContract(ctx))))

route('post', '/owner/contracts', (ctx) => {
  const owner = requireRole(ctx, 'owner', 'admin')
  const { body } = ctx
  const errors = {}
  const property = db.properties.find((p) => p.id === toInt(body.property_id) && p.owner?.id === owner.id)
  const tenant = db.users.find((u) => u.email === String(body.tenant_email || '').toLowerCase())
  if (!property) errors.property_id = ['Wybierz ofertę.']
  if (!tenant) errors.tenant_email = ['Najemca musi mieć konto w serwisie.']
  else if (tenant.id === owner.id) errors.tenant_email = ['Nie możesz zawrzeć umowy sam ze sobą.']
  if (!body.start_date) errors.start_date = ['Podaj datę rozpoczęcia.']
  if (!body.owner_name) errors.owner_name = ['Podaj imię i nazwisko wynajmującego.']
  if (Object.keys(errors).length) invalid(errors)

  const months = toInt(body.rental_months) || null
  let endDate = null
  if (months) {
    const end = new Date(body.start_date)
    end.setMonth(end.getMonth() + months)
    end.setDate(end.getDate() - 1)
    endDate = end.toISOString().slice(0, 10)
  }

  const contract = {
    id: nextId(),
    owner_id: owner.id,
    status: 'generated',
    property: propertyRef(property),
    tenant: userRef(tenant, ['email']),
    rental_price: property.price,
    deposit_amount: property.deposit,
    admin_fee: property.admin_fee,
    start_date: body.start_date,
    end_date: endDate,
    rental_months: months,
    generated_at: now(),
    created_at: now(),
    owner_name: body.owner_name,
    owner_address: body.owner_address || null,
    owner_id_number: body.owner_id_number || null,
    tenant_name: body.tenant_name || tenant.name,
    tenant_address: body.tenant_address || null,
    tenant_id_number: body.tenant_id_number || null,
    property_address: [property.street, `${property.postal_code ?? ''} ${property.city}`.trim()].filter(Boolean).join(', '),
    property_type: property.property_type,
    conditions: body.conditions || null,
  }
  db.contracts.push(contract)
  notify(tenant.id, 'contract_generated', 'Nowa umowa najmu', `Wynajmujący przygotował umowę najmu dla "${property.title}".`, { contract_id: contract.id })
  save()
  return created(contractResource(contract), 'Umowa została wygenerowana.')
})

route('patch', '/owner/contracts/:id/status', (ctx) => {
  const contract = ownedContract(ctx)
  if (!LABELS.contract[ctx.body.status]) invalid({ status: ['Nieprawidłowy status.'] })
  contract.status = ctx.body.status
  save()
  return ok(contractResource(contract), 'Status umowy zaktualizowany.')
})

route('get', '/owner/contracts/:id/download', (ctx) => ({ status: 200, data: buildContractPdf(ownedContract(ctx)), raw: true }))

// Płatności
route('get', '/owner/payments', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const mine = db.payments.filter((p) => p.owner_id === user.id).map(paymentResource)
  const list = mine
    .filter((p) => (!ctx.query.status || p.status === ctx.query.status) && (!ctx.query.type || p.type === ctx.query.type))
    .sort((a, b) => (b.due_date || '').localeCompare(a.due_date || ''))
  const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString()
  const result = paginate(list, ctx.query, 20)
  result.data.summary = {
    pending: mine.filter((p) => p.status === 'pending').reduce((sum, p) => sum + Number(p.amount), 0),
    paid_this_month: mine.filter((p) => p.status === 'paid' && p.paid_at >= monthStart).reduce((sum, p) => sum + Number(p.amount), 0),
    overdue_count: mine.filter((p) => p.is_overdue).length,
  }
  return result
})

route('post', '/owner/payments', (ctx) => {
  const owner = requireRole(ctx, 'owner', 'admin')
  const { body } = ctx
  const errors = {}
  const property = db.properties.find((p) => p.id === toInt(body.property_id) && p.owner?.id === owner.id)
  const tenant = db.users.find((u) => u.email === String(body.tenant_email || '').toLowerCase())
  if (!property) errors.property_id = ['Wybierz ofertę.']
  if (!tenant) errors.tenant_email = ['Najemca musi mieć konto w serwisie.']
  if (!LABELS.paymentType[body.type]) errors.type = ['Wybierz typ płatności.']
  if (!(Number(body.amount) > 0)) errors.amount = ['Podaj kwotę większą od zera.']
  if (!body.due_date) errors.due_date = ['Podaj termin płatności.']
  if (Object.keys(errors).length) invalid(errors)

  const payment = {
    id: nextId(),
    owner_id: owner.id,
    type: body.type,
    status: 'pending',
    amount: Number(body.amount).toFixed(2),
    description: body.description || null,
    due_date: body.due_date,
    paid_at: null,
    contract_id: toInt(body.contract_id) || null,
    property: propertyRef(property),
    tenant: userRef(tenant, ['email']),
  }
  db.payments.push(payment)
  notify(tenant.id, 'payment_requested', 'Nowa płatność do opłacenia', `Wynajmujący dodał płatność ${payment.amount} zł za "${property.title}".`, { payment_id: payment.id })
  save()
  return created(paymentResource(payment), 'Płatność została dodana.')
})

route('patch', '/owner/payments/:id/status', (ctx) => {
  const user = requireRole(ctx, 'owner', 'admin')
  const payment = db.payments.find((p) => p.id === toInt(ctx.params.id) && p.owner_id === user.id) || notFound()
  if (!LABELS.payment[ctx.body.status]) invalid({ status: ['Nieprawidłowy status.'] })
  payment.status = ctx.body.status
  payment.paid_at = payment.status === 'paid' ? payment.paid_at || now() : null
  save()
  return ok(paymentResource(payment), 'Status płatności zaktualizowany.')
})

// ─── Panel administratora ─────────────────────────────────────────────────

route('get', '/admin/users', (ctx) => {
  requireRole(ctx, 'admin')
  const { role, search, is_active } = ctx.query
  const list = db.users
    .filter((u) => !role || u.role === role)
    .filter((u) => !search || `${u.name} ${u.email}`.toLowerCase().includes(search.toLowerCase()))
    .filter((u) => is_active === undefined || is_active === '' || u.is_active === bool(is_active))
    .map(publicUser)
  return paginate(list, ctx.query, 20)
})

route('get', '/admin/users/:id', (ctx) => {
  requireRole(ctx, 'admin')
  return ok(publicUser(findUser(toInt(ctx.params.id)) || notFound()))
})

route('patch', '/admin/users/:id/status', (ctx) => {
  requireRole(ctx, 'admin')
  const user = findUser(toInt(ctx.params.id)) || notFound()
  if (user.role === 'admin') throw new HttpError(403, 'Nie można blokować admina.')
  user.is_active = bool(ctx.body.is_active)
  save()
  return ok(null, user.is_active ? 'Konto aktywowane.' : 'Konto zablokowane.')
})

route('patch', '/admin/users/:id/role', (ctx) => {
  requireRole(ctx, 'admin')
  const user = findUser(toInt(ctx.params.id)) || notFound()
  if (!['user', 'owner', 'admin'].includes(ctx.body.role)) invalid({ role: ['Nieprawidłowa rola.'] })
  user.role = ctx.body.role
  save()
  return ok(null, 'Rola zaktualizowana.')
})

route('get', '/admin/properties', (ctx) => {
  requireRole(ctx, 'admin')
  const { status, search, type } = ctx.query
  const list = db.properties
    .filter((p) => !status || p.status === status)
    .filter((p) => !type || p.property_type === type)
    .filter((p) => !search || `${p.title} ${p.city}`.toLowerCase().includes(search.toLowerCase()))
    .sort((a, b) => b.id - a.id)
  const result = paginate(list, ctx.query, 20)
  result.data.data = result.data.data.map((p) => propertyResource(p))
  return result
})

route('patch', '/admin/properties/:id/status', (ctx) => {
  const admin = requireRole(ctx, 'admin')
  const property = findProperty(ctx.params.id)
  const status = ctx.body.status
  if (!LABELS.property[status]) invalid({ status: ['Nieprawidłowy status.'] })
  property.status = status
  property.status_label = LABELS.property[status]
  if (status === 'published') Object.assign(property, { published_at: now(), rejected_reason: null })
  if (status === 'rejected') Object.assign(property, { published_at: null, rejected_reason: ctx.body.rejected_reason || null })
  logActivity(admin, 'admin.property.status', `Zmieniono status oferty #${property.id} na ${status}`)
  save()
  return ok(null, 'Status oferty zaktualizowany.')
})

route('get', '/admin/contact-messages', (ctx) => {
  requireRole(ctx, 'admin')
  const list = db.contactMessages.filter((m) => !ctx.query.status || m.status === ctx.query.status)
  return paginate(list, ctx.query, 20)
})

route('get', '/admin/contact-messages/:id', (ctx) => {
  requireRole(ctx, 'admin')
  return ok(db.contactMessages.find((m) => m.id === toInt(ctx.params.id)) || notFound())
})

route('patch', '/admin/contact-messages/:id/status', (ctx) => {
  requireRole(ctx, 'admin')
  const message = db.contactMessages.find((m) => m.id === toInt(ctx.params.id)) || notFound()
  if (!LABELS.contact[ctx.body.status]) invalid({ status: ['Nieprawidłowy status.'] })
  Object.assign(message, { status: ctx.body.status, status_label: LABELS.contact[ctx.body.status], admin_note: ctx.body.admin_note ?? message.admin_note })
  save()
  return ok(null, 'Status zaktualizowany.')
})

route('get', '/admin/blog-posts', (ctx) => {
  requireRole(ctx, 'admin')
  const list = [...db.blogPosts]
    .filter((p) => !ctx.query.status || p.status === ctx.query.status)
    .sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '') || b.id - a.id)
  return paginate(list, ctx.query, 20)
})

route('get', '/admin/blog-posts/:id', (ctx) => {
  requireRole(ctx, 'admin')
  return ok(db.blogPosts.find((p) => p.id === toInt(ctx.params.id)) || notFound())
})

function applyBlogData(post, body, isNew) {
  const errors = {}
  if ((isNew || 'title' in body) && !body.title) errors.title = ['Podaj tytuł.']
  if ((isNew || 'content' in body) && !body.content) errors.content = ['Podaj treść.']
  if (Object.keys(errors).length) invalid(errors)
  for (const key of ['title', 'excerpt', 'content', 'status', 'meta_title', 'meta_description']) {
    if (key in body) post[key] = body[key] || null
  }
  if ('category_id' in body) post.category = blogCategoryRef(body.category_id)
  if (Array.isArray(body.tags)) post.tags = body.tags.map((name) => ({ id: nextId(), name, slug: slugify(name) }))
  if (post.status === 'published' && !post.published_at) post.published_at = now()
}

route('post', '/admin/blog-posts', (ctx) => {
  const admin = requireRole(ctx, 'admin')
  const post = { id: nextId(), tags: [], category: null, author: userRef(admin), published_at: null, created_at: now(), status: 'draft' }
  applyBlogData(post, ctx.body, true)
  post.slug = uniqueSlug(db.blogPosts, post.title)
  db.blogPosts.push(post)
  save()
  return created(post, 'Wpis został utworzony.')
})

route('put', '/admin/blog-posts/:id', (ctx) => {
  requireRole(ctx, 'admin')
  const post = db.blogPosts.find((p) => p.id === toInt(ctx.params.id)) || notFound()
  applyBlogData(post, ctx.body, false)
  save()
  return ok(post, 'Wpis został zaktualizowany.')
})

route('delete', '/admin/blog-posts/:id', (ctx) => {
  requireRole(ctx, 'admin')
  db.blogPosts = db.blogPosts.filter((p) => p.id !== toInt(ctx.params.id))
  save()
  return noContent()
})

route('get', '/admin/pages', (ctx) => {
  requireRole(ctx, 'admin')
  return ok([...db.pages].sort((a, b) => a.title.localeCompare(b.title)).map(({ id, title, slug, status }) => ({ id, title, slug, status })))
})

route('get', '/admin/pages/:id', (ctx) => {
  requireRole(ctx, 'admin')
  return ok(db.pages.find((p) => p.id === toInt(ctx.params.id)) || notFound())
})

route('put', '/admin/pages/:id', (ctx) => {
  requireRole(ctx, 'admin')
  const page = db.pages.find((p) => p.id === toInt(ctx.params.id)) || notFound()
  for (const key of ['title', 'content', 'meta_title', 'meta_description', 'status']) {
    if (key in ctx.body) page[key] = ctx.body[key]
  }
  page.updated_at = now()
  save()
  return ok({ id: page.id, title: page.title, slug: page.slug, content: page.content }, 'Strona zaktualizowana.')
})

route('get', '/admin/settings', (ctx) => {
  requireRole(ctx, 'admin')
  return ok(db.settings)
})

route('put', '/admin/settings', (ctx) => {
  requireRole(ctx, 'admin')
  Object.assign(db.settings, ctx.body.settings || {})
  save()
  return ok(null, 'Ustawienia zapisane.')
})

route('get', '/admin/activity-logs', (ctx) => {
  requireRole(ctx, 'admin')
  const list = db.activityLogs.filter((l) => !ctx.query.action || l.action.includes(ctx.query.action))
  return paginate(list, ctx.query, 50)
})

// ─── Obsługa żądania ──────────────────────────────────────────────────────

export function handle({ method, path, query, body, formData, headers }) {
  for (const r of routes) {
    if (r.method !== method) continue
    const match = path.match(r.pattern)
    if (!match) continue
    const params = Object.fromEntries(r.keys.map((key, i) => [key, decodeURIComponent(match[i + 1])]))
    return r.handler({ params, query, body: body || {}, formData, headers, user: currentUser(headers) })
  }
  throw new HttpError(404, `Brak trasy ${method.toUpperCase()} ${path} w wersji demo.`)
}
