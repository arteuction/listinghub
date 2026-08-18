{{--
    Interactive Bulgaria map (MapLibre GL JS, 3.4.1).

    USAGE: @include('site.partials.map', ['mapParams' => $mapParams])
    where $mapParams is a JSON-safe array of current catalog filters that will
    be forwarded to the /api/catalog/map endpoint to guarantee filter parity.

    Renders:
      • A MapLibre GL map locked to Bulgaria (maxBounds).
      • GeoJSON clustering (client-side, no extra tile server required).
      • Popup on individual marker click → listing title + link.
      • Zoom-in on cluster click.
      • Accessible: keyboard users can toggle to the list view to avoid the
        canvas; ARIA roles mark the canvas region and the fallback list.
--}}
@once
    {{-- MapLibre GL JS 4.7.1 — pinned version + SRI; update both when bumping --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css" integrity="sha384-MinO0mNliZ3vwppuPOUnGa+iq619pfMhLVUXfC4LHwSCvF9H+6P/KO4Q7qBOYV5V" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js" integrity="sha384-SYKAG6cglRMN0RVvhNeBY0r3FYKNOJtznwA0v7B5Vp9tr31xAHsZC0DqkQ/pZDmj" crossorigin="anonymous" defer></script>
@endonce

<div
    id="lh-map"
    role="region"
    aria-label="Карта на обявите"
    class="relative rounded-lg border border-slate-200 overflow-hidden"
    style="height:560px;"
    data-tile-url="{{ config('listinghub.map.tile_url') }}"
    data-center="{{ json_encode(config('listinghub.map.center')) }}"
    data-zoom="{{ config('listinghub.map.default_zoom') }}"
    data-min-zoom="{{ config('listinghub.map.min_zoom') }}"
    data-bounds="{{ json_encode(config('listinghub.map.bounds')) }}"
    data-api="{{ route('api.catalog.map') }}"
    data-params="{{ json_encode($mapParams ?? []) }}"
>
    <p class="absolute inset-0 flex items-center justify-center text-slate-400 text-sm select-none" id="lh-map-loading">
        Зарежда картата…
    </p>
</div>

<script>
(function () {
    'use strict';

    // Wait for MapLibre script (loaded defer) and DOM.
    function boot() {
        const container = document.getElementById('lh-map');
        if (!container || typeof maplibregl === 'undefined') {
            requestAnimationFrame(boot);
            return;
        }

        const tileUrl  = container.dataset.tileUrl;
        const center   = JSON.parse(container.dataset.center);   // [lng, lat]
        const zoom     = parseFloat(container.dataset.zoom);
        const minZoom  = parseFloat(container.dataset.minZoom);
        const bounds   = JSON.parse(container.dataset.bounds);   // [[w,s],[e,n]]
        const apiBase  = container.dataset.api;
        const apiExtra = JSON.parse(container.dataset.params);   // {category, region, …}

        // Build the MapLibre style inline so we work with any raster tile URL
        // (OSM in dev, a hosted provider in production via MAP_TILE_URL).
        const style = {
            version: 8,
            sources: {
                osm: {
                    type: 'raster',
                    tiles: [tileUrl],
                    tileSize: 256,
                    attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
                },
            },
            layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
        };

        const map = new maplibregl.Map({
            container: 'lh-map',
            style,
            center,
            zoom,
            minZoom,
            maxBounds: bounds,
            attributionControl: true,
        });

        map.addControl(new maplibregl.NavigationControl(), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');

        // Remove placeholder text once the map canvas is ready.
        map.on('load', function () {
            const loading = document.getElementById('lh-map-loading');
            if (loading) loading.remove();

            // GeoJSON source with client-side clustering.
            map.addSource('listings', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
                cluster: true,
                clusterRadius: 50,
                clusterMaxZoom: 14,
            });

            // Cluster circles — colour deepens with count.
            map.addLayer({
                id: 'clusters',
                type: 'circle',
                source: 'listings',
                filter: ['has', 'point_count'],
                paint: {
                    'circle-color': [
                        'step', ['get', 'point_count'],
                        '#60a5fa',   // 1–9
                        10, '#2563eb', // 10–29
                        30, '#1e40af', // 30+
                    ],
                    'circle-radius': [
                        'step', ['get', 'point_count'],
                        20,
                        10, 28,
                        30, 36,
                    ],
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff',
                },
            });

            // Count label on each cluster.
            map.addLayer({
                id: 'cluster-count',
                type: 'symbol',
                source: 'listings',
                filter: ['has', 'point_count'],
                layout: {
                    'text-field': '{point_count_abbreviated}',
                    'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-size': 13,
                },
                paint: { 'text-color': '#ffffff' },
            });

            // Individual marker (unclustered).
            map.addLayer({
                id: 'unclustered-point',
                type: 'circle',
                source: 'listings',
                filter: ['!', ['has', 'point_count']],
                paint: {
                    'circle-color': '#2563eb',
                    'circle-radius': 8,
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff',
                },
            });

            // Cluster click → zoom in.
            map.on('click', 'clusters', function (e) {
                const features = map.queryRenderedFeatures(e.point, { layers: ['clusters'] });
                const clusterId = features[0].properties.cluster_id;
                map.getSource('listings').getClusterExpansionZoom(clusterId, function (err, zoom) {
                    if (err) return;
                    map.easeTo({ center: features[0].geometry.coordinates, zoom });
                });
            });

            // Individual marker click → popup.
            map.on('click', 'unclustered-point', function (e) {
                const p = e.features[0].properties;
                const coords = e.features[0].geometry.coordinates.slice();
                const html = '<strong><a href="' + p.url + '">' + escHtml(p.title) + '</a></strong>'
                    + (p.settlement ? '<br><span class="text-slate-500 text-xs">' + escHtml(p.settlement) + '</span>' : '');

                new maplibregl.Popup({ closeButton: true, maxWidth: '240px' })
                    .setLngLat(coords)
                    .setHTML(html)
                    .addTo(map);
            });

            // Pointer cursor over interactive layers.
            ['clusters', 'unclustered-point'].forEach(function (layer) {
                map.on('mouseenter', layer, function () { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', layer, function () { map.getCanvas().style.cursor = ''; });
            });

            // Load listings for the initial viewport, then refresh on move.
            loadListings();
            map.on('moveend', loadListings);
        });

        let abortCtrl = null;

        function loadListings() {
            const b = map.getBounds();
            const bbox = [
                b.getWest().toFixed(5),
                b.getSouth().toFixed(5),
                b.getEast().toFixed(5),
                b.getNorth().toFixed(5),
            ].join(',');

            const url = new URL(apiBase);
            url.searchParams.set('bbox', bbox);
            Object.entries(apiExtra).forEach(([k, v]) => {
                if (v) url.searchParams.set(k, v);
            });

            // Persist bbox in the browser URL so the link stays shareable.
            const pageUrl = new URL(window.location.href);
            pageUrl.searchParams.set('bbox', bbox);
            history.replaceState(null, '', pageUrl.toString());

            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            fetch(url.toString(), { signal: abortCtrl.signal })
                .then(function (r) { return r.json(); })
                .then(function (geojson) {
                    const src = map.getSource('listings');
                    if (src) src.setData(geojson);
                })
                .catch(function () { /* aborted or network error — silently ignore */ });
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
</script>
