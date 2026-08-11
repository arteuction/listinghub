@php
    /**
     * Cascading region -> settlement picker.
     *
     * The settlements table holds 5,256 rows, so the full list must never be
     * rendered into the form. Picking a region fetches only that region's
     * settlements from the public lookup endpoint.
     */
    $currentSettlement = $listing?->settlement;
    $currentRegion = $currentSettlement?->municipality?->region;
@endphp

<section class="rounded-lg border border-slate-200 bg-white p-6"
         x-data="{
             regionSlug: @js($currentRegion?->slug ?? ''),
             settlements: [],
             settlementId: @js((string) old('settlement_id', $listing?->settlement_id ?? '')),
             loading: false,
             async loadSettlements(keepSelection = false) {
                 if (! this.regionSlug) { this.settlements = []; this.settlementId = ''; return; }
                 this.loading = true;
                 if (! keepSelection) { this.settlementId = ''; }
                 try {
                     const response = await fetch(`/regions/${encodeURIComponent(this.regionSlug)}/settlements`, {
                         headers: { 'Accept': 'application/json' },
                     });
                     this.settlements = response.ok ? await response.json() : [];
                 } catch (e) {
                     this.settlements = [];
                 } finally {
                     this.loading = false;
                 }
             },
         }"
         x-init="if (regionSlug) { loadSettlements(true); }">
    <h2 class="mb-4 text-lg font-semibold">Местоположение</h2>
    <p class="mb-4 text-sm text-slate-600">
        Платформата обслужва само България. Изберете област, след това населено място.
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="region" class="block text-sm font-medium">Област</label>
            <select id="region" x-model="regionSlug" @change="loadSettlements()"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <option value="">—</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->slug }}">{{ $region->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="settlement_id" class="block text-sm font-medium">Населено място</label>
            <select id="settlement_id" name="settlement_id" x-model="settlementId"
                    :disabled="loading || ! regionSlug"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 disabled:bg-slate-100">
                <option value="">—</option>
                {{-- Rendered server-side so the current value survives a
                     validation redirect even before Alpine has fetched. --}}
                @if ($currentSettlement)
                    <option value="{{ $currentSettlement->id }}" selected>{{ $currentSettlement->name }}</option>
                @endif
                <template x-for="settlement in settlements" :key="settlement.id">
                    <option :value="settlement.id" x-text="settlement.name"></option>
                </template>
            </select>
            <p class="mt-1 text-xs text-slate-500" x-show="loading">Зареждане…</p>
            @error('settlement_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
