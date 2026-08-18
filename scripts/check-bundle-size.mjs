/**
 * Bundle-size budget checker.
 *
 * Reads the Vite manifest, resolves static import trees for each entry,
 * computes raw and gzip sizes, and exits 1 if any budget is exceeded.
 *
 * Usage:  node scripts/check-bundle-size.mjs
 */

import { readFileSync, statSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { gzipSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const buildDir = resolve(root, 'public/build');

const manifest = JSON.parse(readFileSync(resolve(buildDir, 'manifest.json'), 'utf8'));

// --- helpers ----------------------------------------------------------------

function fileSize(relPath) {
    const abs = resolve(buildDir, relPath);
    const buf = readFileSync(abs);
    return { raw: buf.length, gzip: gzipSync(buf, { level: 9 }).length };
}

/** Collect the file for `key` plus all its static (non-dynamic) imports. */
function collectStaticTree(key, visited = new Set()) {
    if (visited.has(key)) return [];
    visited.add(key);
    const entry = manifest[key];
    if (!entry) return [];
    const files = [entry.file];
    if (entry.css) files.push(...entry.css);
    for (const imp of entry.imports ?? []) {
        files.push(...collectStaticTree(imp, visited));
    }
    return files;
}

function sumSizes(files) {
    const unique = [...new Set(files)];
    let raw = 0, gzip = 0;
    for (const f of unique) {
        const s = fileSize(f);
        raw += s.raw;
        gzip += s.gzip;
    }
    return { raw, gzip };
}

function kb(bytes) { return (bytes / 1024).toFixed(2); }

// --- budgets (gzip KiB) ----------------------------------------------------

function findEntryKey(src) {
    for (const [k, v] of Object.entries(manifest)) {
        if (v.src === src && v.isEntry) return k;
    }
    return src;
}

function findDynamicKey(src) {
    for (const [k, v] of Object.entries(manifest)) {
        if (v.src === src && v.isDynamicEntry) return k;
    }
    // Also check by looking for chunk name patterns
    for (const [k, v] of Object.entries(manifest)) {
        if (v.src === src) return k;
    }
    return src;
}

const budgets = [
    { label: 'public initial JS',       src: 'resources/js/app.js',              type: 'js',  maxGzipKiB: 30  },
    { label: 'admin initial JS',        src: 'resources/js/admin.js',            type: 'js',  maxGzipKiB: 30  },
    { label: 'public initial CSS',      src: 'resources/css/app.css',            type: 'css', maxGzipKiB: 15  },
    { label: 'admin initial CSS',       src: 'resources/css/admin.css',          type: 'css', maxGzipKiB: 15  },
    { label: 'RichText/Tiptap lazy',    src: 'resources/js/features/rich-text.js', type: 'all', maxGzipKiB: 110 },
    { label: 'Tom Select lazy',         src: 'resources/js/features/tom-select.js', type: 'all', maxGzipKiB: 25 },
    { label: 'SortableJS lazy',         src: 'resources/js/features/sortable.js',   type: 'all', maxGzipKiB: 20 },
];

// --- run --------------------------------------------------------------------

let failed = false;

console.log('\n  Bundle size report');
console.log('  ' + '─'.repeat(68));
console.log('  ' + 'Group'.padEnd(30) + 'Raw KiB'.padStart(10) + 'Gzip KiB'.padStart(10) + 'Budget'.padStart(10) + '  Status');
console.log('  ' + '─'.repeat(68));

for (const b of budgets) {
    let key;
    if (b.type === 'all') {
        // lazy chunk — find by src in any form
        key = findDynamicKey(b.src);
    } else {
        key = findEntryKey(b.src);
    }

    const entry = manifest[key];
    if (!entry) {
        console.log(`  ${'SKIP'.padEnd(30)} ${b.label} — not found in manifest`);
        continue;
    }

    let files;
    if (b.type === 'js') {
        // Only JS files from the static tree
        files = collectStaticTree(key).filter(f => f.endsWith('.js'));
    } else if (b.type === 'css') {
        // The CSS file for this entry
        files = [entry.file];
        if (entry.css) files.push(...entry.css);
        files = files.filter(f => f.endsWith('.css'));
    } else {
        // All files for a lazy chunk (JS + CSS)
        files = collectStaticTree(key);
    }

    const { raw, gzip } = sumSizes(files);
    const gzipKiB = gzip / 1024;
    const over = gzipKiB > b.maxGzipKiB;
    if (over) failed = true;

    const status = over ? 'OVER' : 'ok';
    console.log(
        '  ' +
        b.label.padEnd(30) +
        kb(raw).padStart(10) +
        kb(gzip).padStart(10) +
        `${b.maxGzipKiB}`.padStart(10) +
        `  ${status}`
    );
}

console.log('  ' + '─'.repeat(68));
console.log('');

if (failed) {
    console.error('  Bundle budget exceeded — see OVER entries above.\n');
    process.exit(1);
}

console.log('  All budgets within limits.\n');
