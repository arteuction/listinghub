<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingHour;
use App\Models\ListingHourException;
use App\Services\Hours\EffectiveHoursResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/*
| 3.5.1 — Public hour exceptions.
|
| Key invariants:
|   • A date exception always overrides the weekly schedule for that date.
|   • Without an exception, the weekly row for the day of week applies.
|   • No weekly row and no exception → null ("no schedule information"),
|     which is NOT the same as closed.
|   • "Today" is decided in Europe/Sofia, not the server (UTC) clock.
|   • An exception for a different date must not leak into today.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function freezeSofia(string $dateTime): CarbonImmutable
{
    $now = CarbonImmutable::parse($dateTime, EffectiveHoursResolver::TIMEZONE);
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    return $now;
}

function hoursListing(): Listing
{
    return Listing::factory()->published()->create();
}

// --------------------------------------------------------------- resolver unit

it('lets a closed exception override the weekly schedule', function () {
    freezeSofia('2026-08-12 10:00'); // Wednesday
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    ListingHourException::factory()->for($listing)->create([
        'date' => '2026-08-12', 'is_closed' => true, 'note' => 'Национален празник',
    ]);

    $listing->load(['hours', 'hourExceptions']);
    $schedule = app(EffectiveHoursResolver::class)->for($listing);

    expect($schedule)->not->toBeNull()
        ->and($schedule->isClosed)->toBeTrue()
        ->and($schedule->isException)->toBeTrue()
        ->and($schedule->note)->toBe('Национален празник');
});

it('lets a special-hours exception override the weekly schedule', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    ListingHourException::factory()->for($listing)->create([
        'date' => '2026-08-12', 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '14:00',
    ]);

    $listing->load(['hours', 'hourExceptions']);
    $schedule = app(EffectiveHoursResolver::class)->for($listing);

    expect($schedule->isClosed)->toBeFalse()
        ->and($schedule->opensAt)->toBe('10:00')
        ->and($schedule->closesAt)->toBe('14:00')
        ->and($schedule->isException)->toBeTrue();
});

it('falls back to the weekly schedule when no exception matches', function () {
    freezeSofia('2026-08-12 10:00'); // Wednesday, day_of_week = 3
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    // Exception for ANOTHER date must not apply today.
    ListingHourException::factory()->for($listing)->create(['date' => '2026-08-13', 'is_closed' => true]);

    $listing->load(['hours', 'hourExceptions']);
    $schedule = app(EffectiveHoursResolver::class)->for($listing);

    expect($schedule->isClosed)->toBeFalse()
        ->and($schedule->opensAt)->toBe('09:00')
        ->and($schedule->closesAt)->toBe('18:00')
        ->and($schedule->isException)->toBeFalse();
});

it('resolves an explicitly requested other date independently of today', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 5, 'opens_at' => '08:00', 'closes_at' => '12:00']);
    ListingHourException::factory()->for($listing)->create(['date' => '2026-08-13', 'is_closed' => true]);

    $listing->load(['hours', 'hourExceptions']);
    $resolver = app(EffectiveHoursResolver::class);

    $thursday = CarbonImmutable::parse('2026-08-13', EffectiveHoursResolver::TIMEZONE);
    expect($resolver->for($listing, $thursday)->isClosed)->toBeTrue()
        ->and($resolver->for($listing, $thursday)->isException)->toBeTrue();

    $friday = CarbonImmutable::parse('2026-08-14', EffectiveHoursResolver::TIMEZONE);
    expect($resolver->for($listing, $friday)->isClosed)->toBeFalse()
        ->and($resolver->for($listing, $friday)->opensAt)->toBe('08:00');
});

it('returns null when the listing has no schedule information at all', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();
    $listing->load(['hours', 'hourExceptions']);

    expect(app(EffectiveHoursResolver::class)->for($listing))->toBeNull();
});

it('treats a weekly closed day as closed without an exception flag', function () {
    freezeSofia('2026-08-16 10:00'); // Sunday, day_of_week = 0
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 0, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null]);

    $listing->load(['hours', 'hourExceptions']);
    $schedule = app(EffectiveHoursResolver::class)->for($listing);

    expect($schedule->isClosed)->toBeTrue()
        ->and($schedule->isException)->toBeFalse();
});

it('decides "today" in Europe/Sofia, not UTC', function () {
    // 23:30 UTC on Tuesday = 02:30 Wednesday in Sofia (EEST, UTC+3).
    $now = CarbonImmutable::parse('2026-08-11 23:30', 'UTC');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    expect(EffectiveHoursResolver::today()->format('Y-m-d'))->toBe('2026-08-12');
});

// ------------------------------------------------------------------ public page

it('shows a closed exception on the public listing page', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    ListingHourException::factory()->for($listing)->create([
        'date' => '2026-08-12', 'is_closed' => true, 'note' => 'Инвентаризация',
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Днес:')
        ->assertSee('затворено')
        ->assertSee('Извънредно работно време')
        ->assertSee('Инвентаризация');
});

it('shows special exception hours on the public listing page', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    ListingHourException::factory()->for($listing)->create([
        'date' => '2026-08-12', 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '14:00',
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('10:00–14:00')
        ->assertSee('Извънредно работно време');
});

it('shows the weekly schedule as today when no exception applies', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();
    ListingHour::factory()->for($listing)->create(['day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '18:00']);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Днес:')
        ->assertSee('09:00–18:00')
        ->assertDontSee('Извънредно работно време');
});

it('omits the today line entirely when there is no schedule information', function () {
    freezeSofia('2026-08-12 10:00');
    $listing = hoursListing();

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertDontSee('Днес:');
});
