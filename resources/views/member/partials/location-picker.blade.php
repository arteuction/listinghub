@php
    /**
     * Cascading region -> settlement picker using Tom Select.
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
             settlementId: @js((string) old('settlement_id', $listing?->settlement_id ?? '')),
             regionTs: null,
             settlementTs: null,

             initSelects() {
                 this.regionTs = new TomSelect(this.$refs.region, {
                     onChange: (value) => {
                         this.regionSlug = value;
                         this.loadSettlements();
                     },
                 });

                 this.settlementTs = new TomSelect(this.$refs.settlement, {
                     valueField: 'id',
                     labelField: 'name',
                     searchField: 'name',
                     onChange: (value) => { this.settlementId = value; },
                 });

                 if (this.regionSlug) {
                     this.loadSettlements(true);
                 }
             },

             async loadSettlements(keepSelection = false) {
                 this.settlementTs.clear(true);
                 this.settlementTs.clearOptions();

                 if (! this.regionSlug) return;

                 this.settlementTs.settings.placeholder = 'Зареждане…';
                 this.settlementTs.inputState();

                 try {
                     const response = await fetch(`/regions/${encodeURIComponent(this.regionSlug)}/settlements`, {
                         headers: { 'Accept': 'application/json' },
                     });
                     const data = response.ok ? await response.json() : [];
                     this.settlementTs.addOptions(data);

                     if (keepSelection && this.settlementId) {
                         this.settlementTs.setValue(this.settlementId, true);
                     }
                 } catch (e) {
                     // Network error — options stay empty.
                 } finally {
                     this.settlementTs.settings.placeholder = '—';
                     this.settlementTs.inputState();
                 }
             },
         }"
         x-init="initSelects()">
    <h2 class="mb-4 text-lg font-semibold">Местоположение</h2>
    <p class="mb-4 text-sm text-slate-600">
        Платформата обслужва само България. Изберете област, след това населено място.
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="region" class="block text-sm font-medium">Област</label>
            <select id="region" x-ref="region"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <option value="">—</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->slug }}" @selected(($currentRegion?->slug ?? '') === $region->slug)>
                        {{ $region->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="settlement_id" class="block text-sm font-medium">Населено място</label>
            <select id="settlement_id" name="settlement_id" x-ref="settlement"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <option value="">—</option>
                @if ($currentSettlement)
                    <option value="{{ $currentSettlement->id }}" selected>{{ $currentSettlement->name }}</option>
                @endif
            </select>
            @error('settlement_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
