# ListingHub — Архитектурна карта

> Концептуален наследник на прегледаната Directory Hub платформа.
> **Оригинален код по мотиви на архитектурата** — не копие на чужд source.
> Стек: **Laravel 13 · PHP 8.3+ · MySQL 8 / MariaDB · Blade + Tailwind · Alpine.js**
> (Laravel 11 е извън security support от 12.03.2026; Laravel 13 се поддържа до март 2028.)
>
> **Модел на платформата: Вариант 3 (подготвен хибрид).** Общ каталог без tenant scopes и
> без промяна в UI/заявките сега; nullable `organization_id` на ключовите таблици служи като
> бъдеща миграционна граница към реален multi-tenant. Виж §2.8.
>
> Статус: **ОДОБРЕНА ПОСОКА** — итерации 1–2 + консолидираща стъпка 2.5 (виж §14).

---

## 0. Цел и обхват

ListingHub е мулти-тенант-готова платформа за **директории / обяви / класифайд**: бизнес листинги с локация, работно време, галерии и отзиви; продукти към листинг; платени абонаментни планове; блог; многоезичие; администраторски и потребителски панели.

Функционалният паритет се извежда от прегледания оригинал, но с **модерна архитектура** и **поправени слабости** (виж §11).

---

## 1. Водещи архитектурни принципи

| Принцип | Решение |
|---------|---------|
| Разделяне на слоеве | Controllers → **Form Requests** (валидация) → **Actions/Services** (бизнес логика) → **Models/Repositories** |
| Тънки контролери | Логиката живее в `App\Actions\*` (single-purpose invokable класове) |
| Плащания абстрахирани | Единен `PaymentGateway` интерфейс + драйвери (Stripe, PayPal, …) — §7 |
| Авторизация | Laravel **Policies** + роли/права, не ad-hoc проверки в middleware |
| Конфигурация | Всичко през `.env` + `config/*`; **нула хардкоднати тайни** |
| i18n | `lang/` JSON + DB-базирани преводи за динамично съдържание |
| Инсталатор | Изолиран пакет-модул, самоизключващ се след завършване — §6 |
| Тестируемост | Feature + Unit тестове от старта (Pest) |

---

## 2. Домейн модел (核心 entities)

Групирани по ограничен контекст (bounded context). Всеки модел → миграция + фабрика + policy (където има достъп).

### 2.1 Каталог / Листинги
- **Listing** — централен обект (име, slug, описание, статус: draft/pending/published/suspended, owner_id, category_id, plan_id, geo lat/lng)
- **MediaAsset** — единна полиморфна галерия (`mediable`) за листинги, продукти и отзиви (виж §14)
- **ListingHour** / **ListingHourException** — работно време + изключения (празници)
- **ListingClaim** — заявка „това е моят бизнес" + документ за верификация
- **ListingLead** — контакт/запитване от посетител
- **ListingSection** / **ListingSectionItem** — гъвкави съдържателни блокове (менюта, услуги)

### 2.2 Продукти
- **Product** — продукт към листинг (име, цена в minor units, статус); галерия през **MediaAsset**
- **Attribute** / **AttributeValue** — динамични характеристики

### 2.3 Таксономия и локация
- **Category** (nested/parent_id) — с **CustomField** дефиниции per категория
- **CustomField** / **CustomFieldValue** — динамични полета per категория (полиморфни стойности)
- **Country → State → City** — географска йерархия (seed от SQL данни)

### 2.4 Потребители и достъп
- **User** (name, email, password, status, locale, country_id)
- **Role** / **Permission** (spatie/laravel-permission) — роли: `admin`, `staff`, `member`
- **SocialAccount** — OAuth връзки (Google, Facebook, GitHub, …)

### 2.5 Монетизация
- **Plan** — абонаментен план (цена, интервал, лимити: брой листинги, featured, галерия)
- **Subscription** — активен абонамент (user, plan, status, renews_at)
- **Invoice** — фактура/разписка (сума, валута, gateway, status)
- **Payment** — единичен платежен запис + **PaymentEvent** (webhook лог, замества per-gateway log таблиците)

### 2.6 Съдържание и маркетинг
- **Post** (блог), **PostTopic**, **PostTag**
- **Review** — рейтинг/отзиви за листинг; изображения през **MediaAsset**
- **Faq**, **Testimonial**, **Advertisement**, **SocialMedia**
- **Message** / **Thread** — вътрешни съобщения (член ↔ администратор / член ↔ листинг)

### 2.7 Платформа / настройки
- **Setting** (key/value, групирани) — заменя монолитната settings таблица
- **Theme** / **Customization** — цветове, header, активна тема
- **Language** — активни езици
- **Import** — bulk CSV импорт (job-базиран, §9)

### 2.8 Организации (граница за бъдещ multi-tenant — Вариант 3)
- **Organization** — минимална таблица (id, name, slug, timestamps). Не се използва активно в UI/логиката сега.
- Ключовите таблици получават **nullable `organization_id`** (FK, `nullOnDelete`): `users`, `listings`, `products`, `plans`, `subscriptions`. Стойността е `NULL` за целия общ каталог.
- **Няма** global scopes, **няма** филтриране по organization, **няма** промяна в заявките или изгледите. Колоната е чисто структурна граница, за да се избегне болезнена миграция, ако някога се въведе изолация.

> **~30 основни таблици** срещу 60 в оригинала — консолидация чрез полиморфизъм (галерии, custom field стойности, payment събития).

---

## 3. Роли и права

| Роля | Достъп |
|------|--------|
| **admin** | Пълен: настройки, плащания, потребители, модерация, импорт, теми, езици |
| **staff** | Делегирана модерация (одобряване листинги/отзиви/claim-и) — конфигурируемо чрез permissions |
| **member** | Собствени листинги/продукти, чекаут, профил, съобщения |
| **guest** | Разглеждане, търсене, регистрация, изпращане на lead |

Реализация: **`spatie/laravel-permission`** + **Policies** за всеки ресурс (`ListingPolicy@update` проверява owner_id или permission). Никаква логика за собственост в маршрутите.

---

## 4. Структура на директориите (Laravel 13)

```
listinghub-platform/
├─ app/
│  ├─ Actions/            # бизнес операции (CreateListing, ApprovePayment, …)
│  ├─ Http/
│  │  ├─ Controllers/{Public,Member,Admin}/
│  │  ├─ Requests/        # Form Request валидация
│  │  └─ Middleware/
│  ├─ Models/
│  ├─ Policies/
│  ├─ Services/
│  │  ├─ Payments/        # PaymentManager + drivers/
│  │  ├─ Install/         # инсталатор логика
│  │  └─ Geo/ Import/ Locale/
│  └─ Support/            # helpers, enums
├─ config/
├─ database/
│  ├─ migrations/
│  ├─ factories/
│  └─ seeders/            # RoleSeeder, PlanSeeder, DemoSeeder, GeoSeeder
├─ resources/
│  ├─ views/{public,member,admin,install}/
│  ├─ js/ css/            # Tailwind + Alpine, Vite
│  └─ lang/
├─ routes/{web,api,console}.php
├─ modules/install/       # изолиран инсталатор (виж §6)
├─ tests/{Feature,Unit}/
├─ docs/
├─ .env.example           # БЕЗ реален APP_KEY
└─ composer.json
```

---

## 5. Заявкови потоци (примери)

**Публично разглеждане на категория:**
`GET /category/{slug}` → `CategoryController` → `ListingQuery` service (филтри: локация, план, рейтинг) → кеширан изглед → Blade.

**Създаване на листинг (член):**
`POST /member/listings` → `StoreListingRequest` (валидация + custom fields per категория) → `CreateListing` action (проверява plan лимити) → събитие `ListingCreated` → нотификация до админ за модерация.

**Модерация:**
`PUT /admin/listings/{listing}/approve` → `ListingPolicy@moderate` → `ApproveListing` action → статус `published` + `ListingApproved` notification към owner.

---

## 6. Инсталатор (преработен)

Изолиран модул `modules/install/`, достъпен на `/install` докато не съществува `storage/app/installed.lock`.

**Стъпки:** Welcome → **Requirements** (PHP 8.3+, ext: pdo, mbstring, openssl, tokenizer, ctype, json, curl, fileinfo) → **Permissions** (`storage/`, `bootstrap/cache/`) → **Environment** (DB, app URL, mail) → **Admin акаунт** (въвежда се от инсталатора!) → **Migrate + seed** → Finished.

**Поправки спрямо оригинала:**
- ❌ Няма default `admin@mail.com / 12345678` — админът се създава в стъпка „Admin акаунт" с потребителски избрана парола.
- ❌ Няма показване на `.env` на екрана в края.
- ✅ `php artisan key:generate` се изпълнява автоматично; `.env.example` е с **празен** `APP_KEY`.
- ✅ Не разчита на symlink hack към document root; използва стандартния `php artisan storage:link`.
- ✅ Лицензна проверка (по избор) — **HTTPS**, без хардкоднати тайни; ключът идва от `.env`.
- ✅ След завършване инсталаторът се самозаключва (lock файл) и рутите връщат 404.

---

## 7. Платежна абстракция

```
App\Services\Payments\
├─ PaymentManager.php          # избира драйвер по конфиг
├─ Contracts/PaymentGateway.php# checkout(), verifyWebhook(), refund(), cancel()
└─ Drivers/
   ├─ StripeDriver.php
   ├─ PayPalDriver.php
   ├─ RazorpayDriver.php
   └─ BankTransferDriver.php
```

Единна `Payment` + `PaymentEvent` таблица (замества `paypal_ipn_logs`, `stripe_webhook_logs`, `razorpay_webhook_logs`). Всеки webhook: `POST /payments/{gateway}/webhook` → `verifyWebhook()` (подпис!) → идемпотентен запис → активиране на `Subscription`.

---

## 8. Многоезичие

- **Статичен UI:** `lang/{locale}.json`
- **Динамично съдържание** (категории, страници): DB преводи (`translatable` колони или таблица `translations`)
- Middleware `SetLocale` от: URL сегмент / user preference / сесия / `Accept-Language`
- Админ панел за редакция на преводи + опционален machine-translate helper (през конфигуриран API, не хардкоднат)

---

## 9. Импорт (bulk CSV)

Оригиналът парсва синхронно през AJAX. Тук: **queued jobs** — `ImportUploaded` → `ParseCsvJob` (chunk) → `ImportRowJob` per ред → прогрес през broadcast/poll. По-устойчиво на големи файлове и timeout.

---

## 10. Ключови външни пакети

| Нужда | Пакет |
|-------|-------|
| Роли/права | `spatie/laravel-permission` |
| Настройки | `spatie/laravel-settings` |
| Media/галерии | `spatie/laravel-medialibrary` |
| Преводими модели | `spatie/laravel-translatable` |
| Sitemap | `spatie/laravel-sitemap` |
| Social login | `laravel/socialite` |
| Плащания | `stripe/stripe-php`, `srmklive/paypal`, `razorpay/razorpay` |
| Sanitize HTML | `mews/purifier` |
| Тестове | `pestphp/pest` |

---

## 11. Поправени слабости спрямо оригинала

| Оригинал | ListingHub |
|----------|-----------|
| 🔴 Default admin `admin@mail.com/12345678` | Админ се създава при инсталация с избрана парола |
| 🔴 Споделен реален `APP_KEY` в пакета | `.env.example` с празен ключ + auto `key:generate` |
| 🔴 Отворени `/utils/link`, `/utils/cache` | Премахнати; само artisan/деплой команди |
| 🔴 `.env` показван на екрана след install | Никога не се извежда |
| ⚠ Лиценз по HTTP + хардкоднат secret | По избор, HTTPS, ключ от `.env` |
| 🔴 Laravel 6 (EOL) | Laravel 13 (поддържан до 2028) |
| Синхронен CSV импорт | Queued jobs |
| 3 отделни webhook лог таблици | Единна `payment_events` |
| Логика в маршрути/middleware | Policies + Actions |

---

## 12. Съдържание на инсталационния пакет (краен deliverable)

```
listinghub-platform/            ← коренна папка (този проект)
├─ (пълен Laravel 13 app)
├─ database/seeders/GeoSeeder   ← опционален импорт на градове
├─ modules/install/             ← уеб инсталатор
├─ .env.example                 ← празни тайни
├─ docs/ARCHITECTURE.md         ← този документ
├─ docs/INSTALL.md              ← ръководство
└─ INSTALL-PACKAGE.md           ← как се сглобява дистрибуционният ZIP
```

---

## 13. План на итерациите (след одобрение на картата)

1. **Скелет** — `composer create-project laravel/laravel "^13.0"`, базов конфиг, Tailwind/Vite, `.env.example` (празни тайни)
2. **Схема на БД** — миграции + модели + фабрики за §2 (core: User/Role, Category/CustomField, Listing+галерия+часове, Plan/Subscription/Invoice/Payment, Geo)
3. **Инсталатор** — модул §6
4. **Auth + роли + policies**
5. **Публичен слой** — начало/търсене/категория/листинг
6. **Member панел** — CRUD листинги/продукти
7. **Admin панел** — модерация, настройки, потребители
8. **Плащания** — абстракция §7 + Stripe драйвер първо
9. **i18n, блог, отзиви, импорт**
10. **Тестове + документация + сглобяване на ZIP**

> **Следваща стъпка при одобрение:** итерация 1 (скелет) + итерация 2 (схема на БД).

---

## 14. Консолидираща стъпка 2.5 (олекотяване + припокриване)

Приложена върху итерации 1–2, за да е по-лека обработката и по-широко припокриването с оригинала. Запазено е широкото покритие (reviews, claims, leads, settings, social accounts, attributes), добавени са по-здрави инженерни решения:

| Промяна | Ефект |
|---------|-------|
| **Пари като integer minor units** (`*_minor` bigint + `char(3) currency`) в plans/products/invoices/payments/subscriptions | целочислена аритметика, без закръгляне, коректно multi-currency; `App\Support\Money` за визуализация |
| **Единна `media_assets`** полиморфна таблица | заменя `listing_images` + `review_images` — една таблица, един код път, един индекс (`media_collection_order`) |
| **Идемпотентни плащания** | `payments.idempotency_key` (uuid, auto) + unique `(gateway, gateway_payment_id)`; `payment_events` unique `(gateway, external_event_id)` + `attempts`/`last_error`/`status` |
| **Snapshot в Subscription** | замразени plan slug/name/price/currency/interval при покупка (`Subscription::snapshotFromPlan()`) |
| **10 status enum-а** | ListingStatus, ProductStatus, SubscriptionStatus, InvoiceStatus, PaymentStatus, PaymentEventStatus, BillingInterval, UserStatus, ModerationStatus, CustomFieldType — евтина валидация чрез каст |
| **Композитни индекси** | `listings`: (status,published_at), (category_id,status), (city_id,status), (is_featured,status); `products`/`reviews`/`invoices`/`payments`: status индекси |
| **12 фабрики + Pest тестове** | hybrid-boundary, payment idempotency, config — база за runtime проверка |

Резултат: **37 таблици / 25 модела / 84 PHP файла**, по-леки от двете предходни версии, без загуба на функционален обхват.

---

## 15. Hardening стъпка 2.6

Приложена след ревю на 2.5. Не сменя домейна — втвърдява коректността:

| # | Проблем | Решение |
|---|---------|---------|
| P0 | `tinker ^2.9` несъвместим с Laravel 13 | `tinker ^3.0`; `spatie/laravel-permission ^8.0`; `pest ^4.0`; `collision ^9.0` |
| P0 | липсва `strict_types` | `declare(strict_types=1);` във **96/96** PHP файла |
| P0 | само `users.organization_id` е реален FK | `constrained()->nullOnDelete()` и на listings/products/plans/subscriptions |
| P0 | бизнес логика в моделите | изведена: `App\Support\PlanSnapshot`, `App\Services\Settings\SettingsRepository`, `App\Actions\Billing\RecordPayment`; моделите са чисти data mappers |
| P1 | `Money` с float + фиксирани 2 знака | currency-aware експоненти (JPY=0, BHD/KWD=3) + integer-safe форматиране |
| P1 | двойно JSON кодиране в `PaymentEventFactory` | payload подава масив (cast `array`) |
| P1 | слаб boundary тест | проверява колона **+ nullable + реален FK** за 5-те таблици + FK-rejection |
| P1 | инсталаторът пуска API преди install | `EnsureInstalled` и на `api` групата → 503 JSON |
| P1 | липсват config/front-end файлове | добавени `cache/session/queue/filesystems/mail/logging/auth/services`, `package.json`, `vite.config.js`, Tailwind 4 + Alpine |
| P1 | липсва `GeoSeeder` | добавен — импортира от `database/data/geo.json`, безопасен no-op ако липсва (без частични данни) |

> Забележка за модулността: моделите остават в `App\Models` (стандартна Laravel конвенция), но **поведението** живее в Actions/Services/Support слоя — това изпълнява заключеното правило „логиката не е в моделите". Пълно разделяне по bounded-context папки е съзнателно отложено.

**Runtime проверка (задължителна преди канонизиране):** `composer install` → `php -l` на всички → `migrate:fresh --seed` на **MySQL и SQLite** → `pest`. Едва след зелено — итерация 3.

---

## 16. Итерация 2.7 — verification contract + transition map + sparse ordering

Тясна итерация; **не** отваря нов архитектурен фронт (без EAV, content_blocks, Cashier, domain-module рефактор, listing sections, admin UI, importer, нови gateways).

**0. Verification contract**
- `composer.json` вече има `"test": ["@php artisan test"]`.
- `verify.sh` (self-contained): `set -Eeuo pipefail`, PHP≥8.3 guard, временна SQLite през `trap`, не пипа реален `.env`/БД; стъпки: validate → install → `migrate:fresh --seed` → `composer test` → `php artisan about`. MySQL портируемостта е отделна ръчна стъпка (виж `docs/VERIFICATION.md`).

**1. Listing transition map** (`App\Enums\ListingTransition`, `App\Actions\Listings\TransitionListing`, `App\Exceptions\InvalidListingTransition`)
- Затворен речник от 6 прехода: Submit(Draft→Pending), AutoPublish(Draft→Published), Approve(Pending→Published), Disapprove(Published→Pending), Suspend(Published→Suspended), Restore(Suspended→Published).
- **Без** Rejected/Archived — статусите остават Draft/Pending/Published/Suspended (`Pending` = оригиналния `Submitted`).
- Action: guard срещу картата → DB transaction → `published_at` само при първо публикуване (не се трие при suspend/disapprove) → невалиден преход хвърля exception и не пипа БД. Нула status-логика в модела.

**2. Sparse ordering** (`App\Support\SparseOrder`, `App\Actions\Media\RepositionMediaAsset`)
- Стъпка 1000; insert = midpoint; rebalance само при липса на цепнатина. Чистият helper е DB-agnostic и преизползваем (категории, custom fields, планове, продукти, sections по-късно).
- Прилага се **само** върху наличния `media_assets` (scope: `mediable_type + mediable_id + collection`), `lockForUpdate` в транзакция — пренареждане на една галерия не докосва друга. Default `sort_order` сменен `0 → 1000` в базовата миграция (позволено пре-релийз).

**Acceptance:** 6-те легални прехода работят, всички други са отказани без промяна в БД, `published_at` стабилен при suspend/restore, midpoint insertion + rebalance коректни, изолация между галерии. **26 тест-случая**, всички статични проверки зелени. Runtime: чака `verify.sh` на PHP 8.3+ хост.

### 2.7.1 — pre-gate корекции (след ревю)

| Приоритет | Поправка |
|-----------|----------|
| P0 | `TransitionListing` зарежда листинга с `lockForUpdate()` **вътре** в транзакцията и проверява заключения актуален статус → конкурентни преходи се сериализират; остарял instance не може да заобиколи картата (нов stale-model тест). |
| P0 | `RepositionMediaAsset` определя съседа по **индекс в заключената, подредена (`sort_order, id`) колекция**, не чрез `sort_order >` → равни позиции (default 1000) са детерминистични. |
| P1 | ZIP се пакетира с Unix `create_system` и `verify.sh` с mode **0755** (executable). Работи и `./verify.sh`, и `bash verify.sh`. |
| доп. | `verify.sh` първо проверява `php`/`composer`/PHP≥8.3/`pdo_sqlite`, чак после генерира ефемерен `APP_KEY`; пази `.env` checksum преди/след. |
| доп. | `App\Actions\Media\AttachMediaAsset` дава append позиция на нови media записи (1000, 2000, …), а не всички на 1000. |
| доп. | Нови тестове: stale-model, равни `sort_order`, target от друг scope, изтрит target, `$target === $asset` no-op, append. |

> Все още **не runtime-ready**: `composer.lock` се генерира при първия `composer install`; каноничният пакет ще го включи след зелен gate.

---

## 17. Итерация 2.7.2 — RUNTIME-VERIFIED ✅

Изпълнен реален gate на портативен PHP 8.3.33 + Composer (Laravel **13.24.0**, Spatie Permission **8.3.0**, Pest **4**). Резултат: `composer validate` ✅ · `composer install` от lock ✅ (103 пакета — L13/Pest4/Spatie8 се разрешават) · `migrate:fresh --seed` на SQLite ✅ · **`artisan test`: 46 passed, 0 failed, 0 warnings (85 assertions)** ✅ · `artisan about` ✅. `verify.sh` минава end-to-end (exit 0).

**Реални дефекти, хванати само от runtime gate-а (не от статичния преглед) и поправени:** `collision ^9.0` не съществува → `^8.8`; backed-enum `from()` е резервиран → `fromStatus()/toStatus()`; deferred FK не работи на SQLite → миграциите пренаредени (organizations+geo преди users, inline FK); boundary FK тест сменен на поведенчески; липсваща `SubscriptionFactory` добавена; framework чете `.env` → `verify.sh` прави throwaway `.env`; `composer.lock` генериран и включен. Пълен запис: `docs/RUNTIME_VERIFICATION_LOG.md`.

**Остава само** MySQL/MariaDB grammar gate (отделна ръчна стъпка). След зелен MySQL — 2.7.2 е канонична.

> **2.7.2 CANONICAL** — both gates green: SQLite (46 tests) and MariaDB 11.4.5 (46 tests, 38 FK, uuid/char(3)/json verified). See `docs/RUNTIME_VERIFICATION_LOG.md`.

---

## 18. Итерация 3 — production инсталатор (RUNTIME-VERIFIED ✅)

9-стъпков backend-first installer: Welcome → Requirements → Environment (реален DB connection test) → Admin → Review → Migrate+Seed → Admin+роля → атомарен `installed.lock` → Finished (inline; refresh → 404). Всички критични правила спазени: `migrate --force` (никога `migrate:fresh`); празна база задължителна (непразна → безопасен отказ); `installed.lock` е единственият маркер (`INSTALLED` махнат); атомарен `.env` запис с allowlist; `APP_KEY` в PHP; DB парола никога в логове/exception/hidden fields; admin парола хешира веднага; admin с `organization_id = null`. P0 решен: file sessions пре-install. Recovery: `installation.pending` (без тайни) + идемпотентен retry; `installation.running` мутекс (атомарен `fopen('x')`) → само един POST финализира.

**Runtime gate:** SQLite 51/51 · MariaDB 51/51 · **E2E clean-install на MariaDB 16/16** (HTTP през artisan serve + curl). Пълен запис: `docs/RUNTIME_VERIFICATION_LOG.md`.

> **2.8.0 CANONICAL** — след итерация 3: bulk moderation (върху `TransitionListing`), после declarative fields.

---

## 19. Bulk moderation (върху `TransitionListing`)

`App\Actions\Listings\BulkTransitionListings` прилага един легален преход към много листинги, преизползвайки `TransitionListing` — всеки елемент минава през **своя** guard + `lockForUpdate` + транзакция. Провалите са изолирани: невалиден преход или липсващ id се записва в `failed[]`, а батчът продължава (един лош ред не rollback-ва останалите). Дедупликация + пропуск на невалидни id-та. Връща `{transitioned: [...], failed: [{id, reason}]}`. Тясна стъпка, нула нова схема. Runtime: SQLite 56/56 · MariaDB 56/56.

---

## 20. Итерация 2.9A — declarative fields (schema + typed normalization) ✅ RUNTIME-VERIFIED

Реализирано по `docs/DECLARATIVE_FIELDS.md` (§0–§5). Typed `custom_field_values` (`listing_id` FK, `value_text/value_string(255)/value_decimal(20,4)/value_boolean`, unique `(custom_field_id, listing_id)`, typed индекси) + флагове `searchable/filterable/sortable` на `custom_fields`. Forward миграция с **lossless validating backfill** (`App\Support\CustomFieldBackfill`), `App\Support\CustomFieldValueNormalizer` (string-based decimal 16+4, varchar 255, select/url/email validity), `SyncListingCustomFields` (full-replacement, две фази, `lockForUpdate`), `UpdateCustomField` (I5–I8 guards).

**Реален бъг, хванат само от MariaDB gate-а:** FK имената в MySQL са database-global — rename на старата cfv → legacy пренася `custom_field_values_custom_field_id_foreign`, а новата cfv с auto-name същото → errno 121. Дадени явни уникални FK имена (`cfv_typed_field_fk`/`cfv_typed_listing_fk`). SQLite не показа проблема. Друг тест-урок: DDL в тест под RefreshDatabase комитва транзакцията → backfill тестът се пренесе на изолирана `cfb` sqlite връзка.

Runtime: **SQLite 82/82 · MariaDB 11.4.5 82/82** (184 assertions). `ListingSearchQuery` — 2.9B.
