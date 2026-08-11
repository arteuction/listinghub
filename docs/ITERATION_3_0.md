# Iteration 3.0 — UI layer over a protected HTTP base

> **Статус: 3.0A (+ .1 hardening) РЕАЛИЗИРАН и runtime-verified (SQLite + MariaDB).**
> 3.0B–3.0D предстоят. Authorization границата е доказана преди първия admin екран.
>
> Стек, който вече е налице: Laravel 13, `spatie/laravel-permission` (роли
> `admin`/`staff`/`member`, permission `manage settings` и др.), инсталаторът
> създава **verified `active` admin** с роля `admin`. Няма login/logout, admin
> routes, controllers, policies или feature layouts — затова 3.0A е първо.

## Ред на gate-овете

| Gate | Обхват | Зависи от |
|------|--------|-----------|
| **3.0A** | Authentication + authorization shell | — |
| **3.0B** | Admin custom-field definitions | 3.0A |
| **3.0C** | Custom fields в listing create/edit | 3.0A, `SyncListingCustomFields` |
| **3.0D** | Public category browsing + filters/sort | `ListingSearchQuery` |

Всеки gate завършва с **двата runtime gate-а (SQLite + MariaDB)**, както досега.

---

## 3.0A — защитена основа

**Обхват**
- Session **login/logout**; **без public registration** засега.
- Само `status === active` потребители могат да влизат (suspended → отказ).
- Инсталаторският admin е вече verified — без email-verification стъпка тук.
- `/admin/*` зад middleware `['auth', 'active', 'permission:manage settings']`.
- Staff/member (без `manage settings`) → **403**.
- **Login rate limiting** (в `LoginRequest` чрез `RateLimiter`, 5/min per email+IP) + **CSRF** на всички POST.
- Минимален admin **layout + dashboard** (`/admin`).

**Файлове (план)**
- `routes/web.php`: `GET/POST /login`, `POST /logout`, група `/admin` (`auth`+permission).
- `App\Http\Controllers\Auth\LoginController` (или Laravel-native auth actions) — тънък; логиката за „active" в кастъм guard/Form Request.
- `App\Http\Requests\Auth\LoginRequest` — валидация + throttling ключ по email+IP.
- `App\Http\Controllers\Admin\DashboardController@index`.
- `resources/views/auth/login.blade.php`, `resources/views/admin/layout.blade.php`, `admin/dashboard.blade.php`.
- Middleware alias `permission:` вече е регистриран (spatie).

**Правила / инварианти**
- Активност се проверява при login (не само при route) — suspended сесия се прекратява.
- Никакви бизнес правила в контролерите; „can login" е политика/Form Request.

**Acceptance тестове (feature)**
- guest към `/admin` → redirect към `/login`.
- успешен login на admin → 200 на dashboard; logout → сесията изчистена, `/admin` пак redirect-ва.
- **suspended** потребител с верни credentials → отказ (не влиза).
- **permission boundary**: member/staff логнат без `manage settings` → **403** на `/admin`.
- login throttling след N неуспешни опита; CSRF задължителен на POST.

---

## 3.0B — admin custom-field definitions ✅ РЕАЛИЗИРАН

**Обхват (никаква нова схема)**
- `App\Actions\CustomFields\CreateCustomField` — ползва **същия definition validator**
  като `UpdateCustomField` (I1–I11 в domain слоя).
- FormRequests валидират **shape**; domain actions държат инвариантите.
- Маршрут: `/admin/categories/{category}/custom-fields` (index/create/store/edit/update/destroy).
- Редактируеми: `label`, `key`, `type`, `required`, capability flags
  (`searchable/filterable/sortable`), `options`, `sort_order`.
- Контролерът **не** записва моделите директно — само през actions.
- **Delete** разрешен **само ако полето няма стойности**; архивиране на използвани
  полета = отделна бъдеща schema стъпка (извън 3.0B).
- Select option **codes** не се редактират/махат при употреба (I7/I8); **labels** — да.

**Acceptance тестове**
- create/edit минават през validator (I5/I6/I9/I10/I11 отказват невалидното).
- capability flag ↔ type matrix се спазва през UI пътя.
- delete на поле **със** стойности → отказ; **без** стойности → успех.
- контролерът не извиква модели директно (проверка чрез поведение).

---

## 3.0C — custom fields в listing create/edit ✅ РЕАЛИЗИРАН

**Обхват**
- Формата се генерира по **точната категория** и `sort_order` на полетата.
- POST payload → **единствено** `SyncListingCustomFields` (full-replacement).
- Required/type/options грешки се връщат към **съответното поле** (per-field errors).
- Смяна на категорията → изисква **пълен нов sync**; старите стойности се премахват контролирано (през Sync, не ръчно).
- **Никакъв** direct `CustomFieldValue::create()` в контролер.

**Acceptance тестове**
- форма за категория показва точно нейните полета, подредени по `sort_order`.
- валиден submit → стойностите синхронизирани; невалиден → per-field грешки, нула промени.
- смяна на категория → старите стойности изчистени, новите приложени атомарно.

---

## 3.0D — public category browsing + filters/sort

**Обхват**
- GET форма + **стабилен query-string contract** (мап към `ListingSearchQuery` request shape).
- Публичният контролер **задължително** chain-ва visibility:
  ```php
  $search->build($criteria)->published()->paginate();
  ```
- Показват се **само** `filterable`/`sortable` definitions.
- Invalid query (`InvalidSearchQuery`) → нормална **validation грешка**, не 500.
- **Pagination** запазва филтрите и sort параметрите в линковете.
- Empty-results и **reset-filters** състояния.
- **Accessibility**: реални `<label>`, `<fieldset>`/`<legend>` за checkbox/select, error summary.

**Acceptance тестове**
- query-string → правилни резултати (scoped + published).
- невалиден филтър → 422/redirect с грешка, не 500.
- pagination линковете носят филтрите/sort.
- само filterable/sortable полета се предлагат в UI.

---

## Извън 3.0
- Public registration, email verification flow, social login UI.
- Архивиране на използвани custom fields (schema стъпка).
- Member self-service панел извън listing create/edit.
- Плащания/subscriptions UI.

> **Следваща стъпка при одобрение: само 3.0A** (auth + authorization shell + тестове),
> после 3.0B → 3.0C → 3.0D, всеки със свои SQLite+MariaDB gate-ове.

---

## 3.0A — точен contract (заключен преди код)

**Маршрути**
```
GET  /login
POST /login
POST /logout
GET  /admin
```

**Middleware ред за `/admin/*`:** `auth → active → permission:manage settings`.

**Правила**
- Authorization е **permission-based** (`permission:manage settings`), не hardcoded role check.
- `EnsureActiveUser` (`active`) проверява статуса при **всяка** admin заявка; ако вече логнат
  потребител е suspended → сесията се прекратява (не само нов login забранен).
- Login success → `session()->regenerate()`.
- Logout → `Auth::logout()` + `session()->invalidate()` + `regenerateToken()`.
- **Един и същ** error response за грешна парола, неизвестен email и suspended user.
- Rate limit: **5 опита/минута** по нормализиран `email + IP`; успех → изчиства limiter-а.
- **Без** remember-me, registration, password reset, verification UI.
- Logout остава достъпен за authenticated suspended user; `active` пази admin групата, не logout.
- **Email case** портируемо: lookup чрез `lower(email)` (еднакво на SQLite/MariaDB) + mixed-case тест.
- Password **никога** в session/flash/лог.
- Guest `/admin` → login с **intended redirect**; след успех → първоначално поисканата страница.
- Authenticated без permission → **403** (не redirect, не 404).

**Acceptance тестове (12)**
1. успешен login + session regeneration; 2. грешни credentials; 3. suspended login;
4. активна сесия, суспендирана след login; 5. rate-limit + clear след успех;
6. logout invalidation; 7. guest intended redirect; 8. admin success;
9. staff/member → 403; 10. user с директно `manage settings` → достъп;
11. mixed-case email еднакво на SQLite/MariaDB; 12. липса на password в session/logs.

---

## 3.0A.1 — hardening (приложен)

- **`guest` middleware** на `GET/POST /login` (`RedirectIfAuthenticated` → безопасен redirect `/`)
  — вече удостоверен потребител **не може** да смени самоличност чрез `POST /login`; без
  member→/admin→403 цикъл.
- **Constant-time login:** при неизвестен email се изпълнява една `Hash::check()` срещу
  **фиксиран** dummy bcrypt hash (не се генерира per-request) → timing не различава
  неизвестен от съществуващ акаунт.
- **По-строги тестове:** session-id преди/след `regenerate()`; успешният login сам изчиства
  предишни неуспешни опити (без ръчен `clear`); logout ротира CSRF token; отделен unknown-email
  тест; identity-switch тест; log spy за password. **CSRF 419-без-token** се доказва на HTTP ниво
  в gate-а (test harness-ът изключва `ValidateCsrfToken`).

**Известен follow-up (преди registration/member provisioning, не блокер за 3.0B):**
`lower(email)` може да съвпадне с повече от един ред, ако unique индексът е case-sensitive.
Нужно е канонично **lowercase съхранение** на email + защита от case-fold collisions, преди да
се позволи самообслужване. При единствения installer admin днес няма риск.
