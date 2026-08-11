<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use App\Support\SearchTerm;

/*
| Free-text search over Bulgarian content.
|
| The invariant under test is that case is ignored for Cyrillic, on whichever
| driver the suite is running. MySQL's default utf8mb4 collation gave this for
| free, so the catalog search passed CI while matching nothing on SQLite for
| any term whose case differed from the stored text — the platform's normal
| case, since titles are capitalised and people type in lower case.
|
| These run on both drivers deliberately: a fold that works on only one driver
| is the defect, so a test pinned to one driver cannot detect it.
|
| Listings that a search must NOT return carry an explicit description: the
| factory fills that column with random text, and an assertion about what is
| absent should not depend on what the generator happened to produce.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

// ─── catalog keyword: case folding ──────────────────────────────────────────

it('matches a capitalised Cyrillic title from a lower-case term', function () {
    Listing::factory()->published()->create(['title' => 'Пекарна Слънце']);

    $this->get(route('listings.index', ['q' => 'пекарн']))
        ->assertOk()
        ->assertSee('Пекарна Слънце');
});

it('matches a lower-case Cyrillic title from an upper-case term', function () {
    Listing::factory()->published()->create(['title' => 'пекарна слънце']);

    $this->get(route('listings.index', ['q' => 'ПЕКАРНА']))
        ->assertOk()
        ->assertSee('пекарна слънце');
});

it('ignores case inside a mixed-case Cyrillic title', function () {
    Listing::factory()->published()->create(['title' => 'АвтоСервиз Център']);

    $this->get(route('listings.index', ['q' => 'автосервиз']))
        ->assertOk()
        ->assertSee('АвтоСервиз Център');
});

it('still ignores case for Latin text', function () {
    Listing::factory()->published()->create(['title' => 'Coffee Roasters']);

    $this->get(route('listings.index', ['q' => 'COFFEE ROAST']))
        ->assertOk()
        ->assertSee('Coffee Roasters');
});

it('folds the description as well as the title', function () {
    Listing::factory()->published()->create([
        'title' => 'Автосервиз',
        'description' => 'Ремонт на ПЕКАРНИ машини',
    ]);

    $this->get(route('listings.index', ['q' => 'пекарни']))
        ->assertOk()
        ->assertSee('Автосервиз');
});

it('does not return an unrelated listing', function () {
    Listing::factory()->published()->create([
        'title' => 'Книжарница',
        'description' => 'Учебници и канцеларски материали',
    ]);

    $this->get(route('listings.index', ['q' => 'пекарн']))
        ->assertOk()
        ->assertDontSee('Книжарница');
});

// ─── wildcard and escape-character literalness ──────────────────────────────

it('treats a bare percent as a literal character, not a wildcard', function () {
    Listing::factory()->published()->create([
        'title' => 'Нормална обява',
        'description' => 'Без специални знаци',
    ]);

    $this->get(route('listings.index', ['q' => '%']))
        ->assertOk()
        ->assertDontSee('Нормална обява');
});

it('treats an underscore as a literal character', function () {
    Listing::factory()->published()->create([
        'title' => 'Обява АБВ',
        'description' => 'Без специални знаци',
    ]);

    // Unescaped, '_' matches any single character and this would match.
    $this->get(route('listings.index', ['q' => 'обява_абв']))
        ->assertOk()
        ->assertDontSee('Обява АБВ');
});

it('finds text that genuinely contains a percent sign', function () {
    Listing::factory()->published()->create(['title' => 'Отстъпка 50% за нови клиенти']);

    $this->get(route('listings.index', ['q' => '50%']))
        ->assertOk()
        ->assertSee('Отстъпка 50% за нови клиенти');
});

it('treats the escape character itself as a literal', function () {
    Listing::factory()->published()->create(['title' => 'Спешно!!! обаждане']);
    Listing::factory()->published()->create([
        'title' => 'Спокойно обаждане',
        'description' => 'Без удивителни знаци',
    ]);

    // An escaper that consumed '!' without doubling it would match both rows.
    $this->get(route('listings.index', ['q' => '!!!']))
        ->assertOk()
        ->assertSee('Спешно!!! обаждане')
        ->assertDontSee('Спокойно обаждане');
});

it('treats a backslash as a literal', function () {
    // The previous escaper relied on '\' being the LIKE escape character,
    // which is MySQL's default and not SQLite's. Under ESCAPE '!' it is
    // neither, on either driver.
    Listing::factory()->published()->create(['title' => 'Път А\\Б магистрала']);

    $this->get(route('listings.index', ['q' => 'а\\б']))
        ->assertOk()
        ->assertSee('Път А\\Б магистрала');
});

// ─── settlement autocomplete (same helper, different endpoint) ──────────────

it('matches an EKATTE settlement name regardless of case', function () {
    $region = Region::factory()->create();
    $muni = Municipality::factory()->create(['region_id' => $region->id]);
    Settlement::factory()->create([
        'municipality_id' => $muni->id,
        'name' => 'Благоевград',
        'latitude' => 42.02,
        'longitude' => 23.09,
    ]);

    $res = $this->getJson('/api/catalog/settlements?q=благоевград')->assertOk();

    expect(collect($res->json())->pluck('name'))->toContain('Благоевград');
});

// ─── the helper's own contract ──────────────────────────────────────────────

it('folds and escapes a term into a bound contains pattern', function () {
    expect(SearchTerm::containsPattern('Пекарна'))->toBe('%пекарна%')
        ->and(SearchTerm::containsPattern('50%'))->toBe('%50!%%')
        ->and(SearchTerm::containsPattern('a_b'))->toBe('%a!_b%')
        // The escape character is doubled first, so the escapes introduced
        // for % and _ are not themselves escaped.
        ->and(SearchTerm::containsPattern('!%'))->toBe('%!!!%%');
});

it('builds a LIKE expression that declares the escape character it uses', function () {
    expect(SearchTerm::likeExpression('listings.title'))
        ->toBe("LOWER(listings.title) LIKE ? ESCAPE '!'");
});
