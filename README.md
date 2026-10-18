# NajmujMieszkanie — Aplikacja do wynajmu mieszkań i pokoi

Webowa aplikacja do wynajmu mieszkań i pokoi w Polsce. Projekt zaliczeniowy.

**Demo online:** https://krzysiekdobosz.github.io/NajmujMieszkanie/

Wersja demo działa w całości w przeglądarce, na danych testowych (bez backendu). Odpowiedzi API są
symulowane w tym samym formacie co Laravel (`frontend/src/demo`), a zmiany zapisują się w `localStorage`.
Na stronie logowania można jednym kliknięciem wejść jako najemca, właściciel lub administrator.

---

## Stack technologiczny

- **Frontend:** Vue 3 (Composition API, Vite, Pinia, Vue Router 4, Tailwind CSS, Axios, VeeValidate)
- **Backend:** Laravel 11 (PHP 8.3+, MySQL 8.0, Sanctum, spatie/laravel-permission, dompdf)
- **Komunikacja:** REST API (`/api/v1/`) z Bearer Token (Sanctum)
- **Baza danych:** MySQL 8.0

---

## Funkcjonalności

- Przeglądanie i filtrowanie ofert mieszkań i pokoi
- Rejestracja, logowanie, zarządzanie profilem
- Role: gość, użytkownik, właściciel, administrator
- Panel użytkownika: ulubione, rezerwacje oglądania, wiadomości
- Panel właściciela: zarządzanie ofertami, zdjęciami, wiadomościami, umowami, płatnościami
- Panel administratora: moderacja ofert, zarządzanie użytkownikami, blog, strony statyczne
- Blog z kategoriami i tagami
- Strony statyczne: O nas, Jak to działa, Kontakt, FAQ, Regulamin, Polityka prywatności
- Generowanie umów PDF
- Demonstracyjne płatności (bez integracji z operatorem)
- System powiadomień w aplikacji
- Podstawowe SEO (meta tagi, sitemap, Open Graph)

---

## Struktura projektu

```
ProjektZaliczeniowy/
├── backend/          # Laravel 11 API
├── frontend/         # Vue 3 SPA
├── docs/             # Dokumentacja (baza danych, enumy)
├── ARCHITECTURE.md   # Dokumentacja architektury technicznej
└── README.md
```

---

## Uruchomienie projektu

> Wymagania: PHP 8.3+, Composer, Node.js 18+ (np. `nvm use 22`), MySQL 8.0

### Backend

```bash
cd backend
composer install
cp .env.example .env
# Skonfiguruj DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD w .env
# Ustaw APP_URL=http://localhost:8000 oraz FRONTEND_URL=http://localhost:5173
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

Backend dostępny pod: `http://localhost:8000`

### Frontend

```bash
cd frontend
npm install
# Utwórz plik .env z zawartością:
# VITE_API_URL=http://localhost:8000/api/v1
# VITE_APP_NAME=NajmujMieszkanie
# VITE_APP_URL=http://localhost:5173
npm run dev
```

Frontend dostępny pod: `http://localhost:5173`

---

## Zmienne środowiskowe

### Backend (`backend/.env`)
```env
APP_NAME=NajmujMieszkanie
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=najmuj_mieszkanie
DB_USERNAME=root
DB_PASSWORD=        # ← uzupełnij lokalnie
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

### Frontend (`frontend/.env`)
```env
VITE_API_URL=http://localhost:8000/api/v1
VITE_APP_NAME=NajmujMieszkanie
VITE_APP_URL=http://localhost:5173
```

---

## Konta domyślne (po seedowaniu)

| Rola | Nazwa | Email | Hasło | Panel |
|---|---|---|---|---|
| Administrator | Administrator | `admin@example.com` | `password` | `/admin/dashboard` |
| Właściciel | Jan Właściciel | `owner@example.com` | `password` | `/wlasciciel/dashboard` |
| Użytkownik | Anna Kowalska | `user@example.com` | `password` | `/panel/dashboard` |

> Po zalogowaniu aplikacja automatycznie przekierowuje do odpowiedniego panelu na podstawie roli.

---

## Status realizacji

### ✅ Zrealizowane (wszystkie kroki)

| Krok | Nazwa | Co zawiera |
|---|---|---|
| 1 | Architektura techniczna | Stack, konwencje nazw, layouty, struktura katalogów, ARCHITECTURE.md |
| 2 | Projekt bazy danych | 20 tabel, 11 enumów PHP, relacje, indeksy, docs/DATABASE.md |
| 3 | Inicjalizacja backendu Laravel | Laravel 11 + Sanctum + spatie/permission, 20 migracji, 17 modeli Eloquent, 65 tras REST API, seedery ról i użytkowników |
| 4 | Inicjalizacja frontendu Vue | Vue 3 + Vite + Tailwind CSS, router z guardami, Pinia stores, Axios wrapper, 4 layouty, 10 komponentów bazowych |
| 5 | Autoryzacja | Backend (AuthController, ProfileController, Form Requests, UserResource) + frontend (LoginView, RegisterView, ForgotPasswordView, ProfileView) |
| 6 | Role i uprawnienia | Middleware `role:owner\|admin` i `role:admin` na trasach API, guardy Vue Router per rola, przekierowanie po logowaniu |
| 7 | Model i API ofert | Pełny CRUD Owner + publiczne endpointy z filtrowaniem, sortowaniem, paginacją |
| 8 | Formularz dodawania i edycji oferty | `PropertyFormView` - tryb create/edit, walidacja, wszystkie pola oferty |
| 9 | Upload i galeria zdjęć | `PropertyImageController` - upload, ustawienie zdjęcia głównego, zmiana kolejności, usuwanie |
| 10 | Publiczna lista ofert | `PropertyListView` z filtrowaniem po typie, mieście, województwie, cenie, metrażu, umeblowaniu |
| 11 | Zaawansowana wyszukiwarka i filtrowanie | Sidebar z filtrami, sortowanie, synchronizacja z URL query params |
| 12 | Szczegóły oferty | `PropertyDetailView` - galeria, dane, kontakt z właścicielem, rezerwacja oglądania |
| 13 | Ulubione | `FavoriteController`, `FavoritesView`, toggle z poziomu listy ofert |
| 14 | Wiadomości użytkownik - właściciel | `ConversationController`, `MessageController`, `MessagesView` (user i owner) |
| 15 | Kontakt do administratora | `ContactMessageController` (public POST + admin panel), `ContactMessagesView` |
| 16 | Rezerwacja oglądania nieruchomości | `PropertyViewingController` (user) + `ViewingController` (owner), `ViewingsView` |
| 17 | Panel użytkownika | Dashboard, ulubione, rezerwacje, wiadomości, profil |
| 18 | Panel właściciela | Dashboard, oferty, zdjęcia, oglądania, wiadomości, umowy, płatności |
| 19 | Panel administratora | Dashboard, użytkownicy, oferty, wiadomości kontaktowe, blog, strony, ustawienia, logi |
| 20 | Blog | `BlogPostController` (public + admin), `BlogListView`, `BlogPostView`, kategorie i tagi |
| 21 | Strony statyczne i prosty CMS | `PageController`, `PageView`, `PageSeeder` (O nas, Kontakt, FAQ, Regulamin itd.), edycja w panelu admina |
| 22 | Generowanie umów PDF | `PdfService`, `ContractController` z endpointem `/download`, `ContractsView` |
| 23 | Demonstracyjne płatności | `PaymentController`, `PaymentsView` właściciela |
| 24 | Powiadomienia | `NotificationService`, `NotificationController`, `NotificationsStore`, `NotificationsView` |
| 25 | SEO | Meta tagi w `PublicLayout`, Open Graph w widokach szczegółów |
| 26 | Bezpieczeństwo | Sanctum Bearer Token, throttle na auth endpointach, `LogActivity` middleware, `ActivityLogController` |

---

## Testy

```bash
cd backend
php artisan test
```

Testy funkcjonalne obejmują m.in. generowanie umów PDF, płatności, powiadomienia i uprawnienia panelu właściciela.

## Wersja demo

```bash
cd frontend
npm run build:demo   # build z symulowanym API, base: /NajmujMieszkanie/
npx vite preview --mode demo
```

Publikacja na GitHub Pages: `npm run deploy:demo` (gałąź `gh-pages`).

