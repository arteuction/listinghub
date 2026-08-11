# Declarative custom fields — карта (2.9)

> **Статус: 2.9A (+ .1/.2/.3) И 2.9B РЕАЛИЗИРАНИ и runtime-verified (SQLite + MariaDB).
> Разделено на два runtime gate-а: **2.9A** (schema + typed normalization +
> `SyncListingCustomFields`) и **2.9B** (`ListingSearchQuery` compiler +
> portability/performance). Започваме само с 2.9A след одобрение.

## 0. Заключени решения

| Решение | Заключена посока |
|---|---|
| Обхват | Полетата важат само за точната `category_id`; **без inheritance** засега |
| Собственост на стойността | Реален `listing_id` FK; **премахваме** polymorphic `valuable_*` |
| Миграция | Нова **forward** миграция; каноничните стари миграции не се пипат |
| Една стойност | `unique(custom_field_id, listing_id)` |
| Празна стойност | Изтриване на реда; **никога** ред с всички `NULL` |
| Select options | Стабилен `value` + променяем `label`; използвана опция не се маха |
| Field `key` | Стабилен, **неизменяем** след като има стойности |
| Type change | **Забранен**, ако вече има стойности |
| Query scope | Custom filters/sort изискват конкретна `category_id` |
| UI | Извън първия подетап; първо domain/schema/query contract |

## 1. Typed матрица (единственият източник за колона/операции)

| Тип | Колона | Search | Filter | Sort |
|---|---|--:|---|--:|
| `text` | `value_text` | да | `contains` | не |
| `number` | `value_decimal` | не | `eq`, `lt/lte`, `gt/gte`, `between` | да |
| `select` | `value_string` | не | `eq`, `in` | да |
| `checkbox` | `value_boolean` | не | `eq` | не |
| `url` | `value_string` | не | `eq` | не |
| `email` | `value_string` | не | `eq` | не |

`App\Enums\CustomFieldType` се подравнява точно към тези 6 case-а. Матрицата
живее на едно място (enum метод/конфиг), от което се извеждат: колоната за
запис, разрешените оператори и дали полето е search/filter/sort-able.

---

## 2A. Схема — forward миграция (2.9A)

Каноничната `custom_field_values` (polymorphic `valuable_*`, единичен `value`)
се **заменя** с typed таблица с реален `listing_id` FK. Правим го в **нова**
миграция `2026_01_02_000100_reshape_custom_field_values_typed.php` — старите
миграции остават непроменени.

**`custom_fields` (добавяме колони):**
- `searchable` boolean default `false`
- `filterable` boolean default `false`
- `sortable` boolean default `false`

(`type`, `key`, `options`, `is_required`, `sort_order`, `category_id` остават.)

**`custom_field_values` (нова форма):**
```
id
custom_field_id  FK -> custom_fields  cascadeOnDelete
listing_id       FK -> listings       cascadeOnDelete
value_text       text            null
value_string     varchar(255)    null
value_decimal    decimal(20,4)   null
value_boolean    boolean         null
timestamps
unique (custom_field_id, listing_id)                 -- една стойност
index (custom_field_id, value_string)                -- select/url/email filter/sort
index (custom_field_id, value_decimal)               -- number filter/sort
index (custom_field_id, value_boolean)               -- checkbox filter
-- value_text не се индексира (само `contains`, виж §2B бележка за LIKE)
```

**Миграционен план (up) — fail-safe swap (`App\Support\ReshapeCustomFieldValues`):**
MySQL/MariaDB **autocommit-ват DDL**, затова многостъпкова миграция **не** може да
rollback-не. Живата таблица остава **canonical и непокътната**, докато напълно
изграден и валидиран `_next` я замести с **един атомарен `RENAME TABLE`**:
1. cleanup на остатъци от предишен опит (`_next`/`_old`) — retry-safe.
2. добавя 3-те boolean флага (идемпотентно, `hasColumn` guard).
3. `create custom_field_values_next` (typed) — живата таблица не се пипа.
4. **валидиращ backfill** live → `_next` (`CustomFieldBackfill`); при лош ред → abort, живата таблица е intact.
5. атомарен swap: MySQL `RENAME TABLE cfv→_old, _next→cfv`; SQLite/PG — rename в транзакция.
6. `drop _old`.

**down:** **reverse migration не се поддържа по policy** — хвърля exception
(възстановяване от backup вместо автоматичен rollback).
Пре-релийз няма данни за миграция; след релийз таблицата е immutable.

**Портируемост:** само стандартни типове (`decimal`, `varchar`, `boolean`,
`text`) + composite unique/index — валидни и на SQLite, и на MariaDB (вече
доказано за целия проект).

## 2A. Normalization — `App\Actions\Listings\SyncListingCustomFields`

Единствената точка за запис на стойности. Вход: `Listing` + масив
`{field_key => raw_value}`.

Алгоритъм (в транзакция):
1. Заявката е валидна **само** за полета с `custom_field.category_id ==
   listing.category_id` (§0 scope). Поле от друга категория → отхвърля се.
2. За всяко поле: нормализира `raw_value` според `type` → пише **само**
   съответната typed колона (матрица §1), останалите остават `NULL`.
3. **Празна стойност** (`null`/`''`/празен `Stringable`) → `delete` на реда
   `(custom_field_id, listing_id)`; никога all-NULL ред. **Невалиден cast** (напр.
   нечислов вход за `number`, non-scalar text) → **reject** (`CustomFieldConflict`),
   не delete.
4. Непразна → `updateOrCreate` по `(custom_field_id, listing_id)` (uniqueness).
5. `select` → стойността трябва да е сред активните `options[].value`; иначе
   reject.

## 2A. Invariant matrix (enforced в service/FormRequest, не в БД)

| # | Инвариант | Enforce |
|---|---|---|
| I1 | Поле важи само за точната категория; без inheritance | `SyncListingCustomFields` + validation |
| I2 | Една стойност на `(field, listing)` | DB unique + `updateOrCreate` |
| I3 | Празна стойност → изтрит ред (никога all-NULL) | `SyncListingCustomFields` |
| I4 | Колоната се определя единствено от `type` | матрица §1 |
| I5 | `key` неизменяем след като има стойности | `UpdateCustomField` guard: ако `values()->exists()` и `key` се мени → reject |
| I6 | `type` не се сменя, ако има стойности | същият guard |
| I7 | Select `value` стабилен, `label` променяем | diff на `options`: разрешени промени само на `label`/добавяне; премахване на `value`, който има стойност → reject |
| I8 | Използвана опция не се маха | `values()->where('value_string', $optValue)->exists()` → reject |

Всеки нарушен инвариант хвърля domain exception (напр.
`App\Exceptions\CustomFieldConflict`), **без** промяна в БД.

## 2A. Acceptance тестове (2.9A)

- **Schema:** typed колони съществуват с правилни типове; `unique(custom_field_id,
  listing_id)`; FK към `custom_fields` и `listings`; `custom_fields` има
  `searchable/filterable/sortable`.
- **Normalization per type:** всеки от 6-те типа пише правилната колона и
  нулира останалите.
- **Empty → delete:** задаване на празно трие реда; няма all-NULL редове.
- **One value:** повторно задаване update-ва същия ред (не дублира).
- **Cross-category reject:** поле от друга категория → отказ, без запис.
- **I5/I6:** смяна на `key`/`type` при налични стойности → отказ.
- **I7/I8:** премахване на използвана select опция → отказ; преименуване на
  `label` → ok.
- **Portability:** целият блок минава на **SQLite и MariaDB**.

---

## 2B. `App\Services\Search\ListingSearchQuery` (2.9B)

Компилатор, който взима заявка и връща `Builder<Listing>`.

**Вход (request contract):**
```
category_id : int            (задължителен — иначе никакви custom filters/sort)
filters     : [ { key, operator, value|values } ]   (0..MAX_FILTERS)
sort        : { key, direction } | null
```

**Правила (заключени):**
1. Изисква конкретна `category_id`; без нея custom filters/sort не се приемат.
2. **Allowlist:** `key` трябва да принадлежи на тази категория И да е
   `filterable` (за filter) / `sortable` (за sort). Непознато/забранено поле →
   **reject** (exception), не тихо игнориране.
3. **Operator allowlist** според типа (матрица §1). Непознат оператор → reject.
4. Всеки filter → `whereExists` подзаявка към `custom_field_values` с
   `listings.id = custom_field_values.listing_id AND custom_field_id = ?` и
   условие върху typed колоната. `whereExists` → **без дублиране** на листинги.
5. **Sort:** correlated subselect към typed колоната; `NULL` винаги последни
   (`ORDER BY (value IS NULL), value <dir>`), плюс `listings.id` като
   детерминистичен tie-breaker.
6. `MAX_FILTERS` (заключено: **8**); над него → reject.
7. **Никога** колона или `direction` от request-а не влизат директно в SQL:
   колоната се извежда от типа (матрица), `direction` се map-ва към фиксирано
   `'asc'`/`'desc'`.
8. `text` search (`contains`) → `LIKE '%'||?||'%'`; escape-ва `%`/`_`; параметрът
   винаги е bound (никаква конкатенация в SQL).

**Стойностна нормализация на входа:** същият typed cast като §2A преди
сравнение (число → decimal, checkbox → boolean, select/url/email → string).
Невалиден cast за оператора → reject.

## 2B. Acceptance тестове (2.9B)

- **Оператори:** `eq`/`in` (select), `contains` (text), `eq/lt/lte/gt/gte/between`
  (number), `eq` (checkbox/url/email) връщат правилните листинги.
- **Без дублиране:** листинг с няколко стойности не се появява два пъти.
- **Allowlist reject:** непознат `key`, non-filterable поле, поле от друга
  категория, непознат оператор → exception (не игнориране).
- **Sort:** `NULL` последни и при двете посоки; `listings.id` tie-breaker дава
  стабилен ред.
- **MAX_FILTERS:** 9 филтъра → reject.
- **Injection:** опит за подаване на колона/`direction`/SQL в `key`/`operator`/
  `direction` → reject; нищо не се интерполира.
- **Portability:** SQLite и MariaDB дават еднакви резултати.
- **Performance smoke:** filter/sort по индексирана typed колона използва
  индекса (EXPLAIN проверка на MariaDB; на SQLite — само коректност).

---

## 3. Извън обхвата на 2.9

- UI (admin форми за дефиниране на полета; public форми/филтри) — след contract-а.
- Inheritance на полета между категории.
- Полиморфни стойности за други entity-та (само `listing_id` засега).
- Full-text/Elasticsearch — първо SQL индекси + `ListingSearchQuery`.

## 4. Ред на изпълнение

1. **2.9A** — миграция + typed нормализация + `SyncListingCustomFields` +
   invariant guards → runtime gate (SQLite + MariaDB).
2. **2.9B** — `ListingSearchQuery` compiler + query contract → runtime gate
   (SQLite + MariaDB, вкл. injection и NULL-ordering тестове).

---

## 5. Задължителни уточнения (одобрени преди код)

### 5.1 Legacy backfill без загуба (2.9A миграция)

Живата таблица остава canonical; backfill-ът пише във **`_next`** (не в живата),
после атомарен swap я заменя. **Не се разчита на автоматичен DDL rollback**
(MySQL autocommit-ва DDL) — безопасността идва от „old-stays-canonical + atomic
swap + retry cleanup".

Backfill-ът обхожда всеки legacy ред (join към `custom_fields`/`listings`) и
**прекъсва безопасно** (throw преди swap-а; живата таблица остава непокътната)
при:
- `valuable_type` различен от `App\Models\Listing`;
- липсващ `listing_id` или `custom_field_id` (осиротял ред);
- **cross-category** ред (`custom_fields.category_id != listings.category_id`);
- стойност, невалидна за typed колоната на дадения тип;
- дубликат `(custom_field_id, listing_id)`;
- `select` стойност извън активните `options[].value`.

**Запазват се** legacy `id`, `created_at`, `updated_at` (идентичност и история).
Backfill-ът минава през същата typed нормализация като §2A (една истина).

**Migration тестове:** сийднати legacy редове за **всичките 6 типа** →
успешен backfill с правилни typed колони; плюс по един тест за всяка от
петте abort-условия (грешен `valuable_type`, осиротял ред, невалидна стойност,
дубликат, select извън options) → миграцията се проваля и legacy остава.

### 5.2 Точна `SyncListingCustomFields` семантика — **full replacement**, не patch

Вход: `Listing` + пълен payload `{field_key => raw_value}` за категорията.
- **Пропуснато optional поле** → съществуващата стойност се **изтрива**.
- **Пропуснато required поле** → целият sync се **отказва** (нула промени).
- `null`/празно → delete, но **само** при optional поле; при required → отказ.
- unknown key или cross-category key → отказ.
- Всичко в **една транзакция** с `lockForUpdate` на listing-а.
- Една грешка (валидация/инвариант) → **нула промени** (rollback).
- **Идемпотентност:** същият payload втори път → същото крайно състояние.

### 5.3 Decimal и string граници

`value_decimal` = `decimal(20,4)`:
- входът се обработва **като string**, никога `float`;
- максимум **16 цели + 4 дробни** цифри;
- excess precision → **отказ**, без тихо rounding/truncation;
- Laravel cast: `decimal:4` връща **string** (не float);
- паричните стойности **не** минават оттук — те си остават `price_minor` (int).

`value_string` = **`VARCHAR(255)`** (портируем индекс) → максимум 255 символа,
**вкл. за `url`**; по-дълъг вход → отказ.

### 5.4 Query детайли (заключени за 2.9B)

- `MAX_FILTERS = 8` брои **нормализираните filter обекти** (след парсване).
- **Един оператор на field key**; диапазон = `between` (не два отделни filter-а).
- **Дублиран key** → отказ.
- `contains`: escape-ват се `%`, `_` **и самият escape символ** (`ESCAPE '\'`);
  binding-ът сам не решава wildcard семантиката.
- **EXPLAIN тестове** не сравняват точен optimizer изход между SQLite и MariaDB;
  проверяват само: наличие на очаквания индекс, успешно планиране, и **липса на
  Cartesian join**.

---

> **2.9A е одобрена за започване** (само migration + backfill, typed
> normalization, `SyncListingCustomFields`, invariant guards, двата runtime
> gate-а). `ListingSearchQuery` — изцяло 2.9B.

### 5.5 Допълнителни инварианти и заключвания (2.9A.1 hardening)

- **I9:** `custom_fields.category_id` е неизменяема при налични стойности.
- **I10:** флаговете съответстват на capability matrix (`searchable` само за `text`;
  `sortable` само за `number`/`select`; `filterable` за всички).
- **I11:** `select` има ≥1 опция с уникален, непразен `value` ≤255; **non-select** има `options=null`.
- **Concurrency (структурно):** `SyncListingCustomFields` заключва `lockForUpdate` **и**
  listing-а, **и** definition редовете; `UpdateCustomField` заключва definition-а. Това
  е коректната serialization граница, но **още няма реален interleaving тест** — гаранцията
  е структурна, не доказана емпирично (за отбелязване, не блокер).
- **Text:** приема само scalar/`Stringable`; масив/обект → `CustomFieldConflict`.
- **URL:** само `http`/`https` схеми.

### 5.6 Query hardening (2.9B.1)

- **Category scope (P0):** при подадена `category_id` заявката добавя
  `where('listings.category_id', ?)`. filters/sort → задължителна **положителна** категория
  (0/отрицателни → reject); само `category_id` → връща листингите на тази категория; без
  нищо → базова unscoped заявка. (Sort-only вече **не** връща чужди категории като NULL-опашка.)
- **Empty value:** `castForFilter()` отказва липсваща/празна стойност за всички типове;
  `false` и `'0'` остават валидни за checkbox.
- **URL/email:** filter стойностите минават през пълна `url()`/`email()` валидация (не само дължина).
- **Строг shape:** `filters` = `array_is_list`; всеки filter е масив; value-операторите изискват
  `value`; `in` изисква `values`; `between` = list с точно 2; `sort` = `null` или масив;
  `direction` първо string. Всяко нарушение → `InvalidSearchQuery`.
- **`MAX_IN_VALUES = 50`** — за да не се заобикаля `MAX_FILTERS=8` с гигантски `IN`.
- **Escape:** `%`, `_` и `!` третирани литерално в `contains`.
- **Visibility НЕ е работа на compiler-а:** той никога не добавя статус scope. Публичният
  контролер трябва да chain-ва `->published()`; admin може всички статуси.
