<#
.SYNOPSIS
    Build a deploy-ready ZIP for ListingHub.
.DESCRIPTION
    Creates a self-contained ZIP with vendor (no-dev) and pre-built Vite assets.
    The ZIP is ready to extract on a host and run the /install wizard.
    No .env, database.sqlite, node_modules, or dev files are included.
#>

param(
    [string]$OutputDir = (Join-Path $PSScriptRoot '..' 'dist')
)

$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
Push-Location $root

try {
    # Determine version from composer.json
    $composerJson = Get-Content 'composer.json' -Raw | ConvertFrom-Json
    $version = if ($composerJson.version) { $composerJson.version } else { 'dev' }
    $zipName = "listinghub-$version.zip"

    Write-Host "Building ListingHub $version deploy package..." -ForegroundColor Cyan

    # Step 1: Install production dependencies
    Write-Host '[1/4] Installing production Composer dependencies...'
    & php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>$null
    if (-not $?) {
        # Try scoop composer
        & composer install --no-dev --optimize-autoloader --no-interaction
    }

    # Step 2: Build frontend assets
    Write-Host '[2/4] Building Vite assets...'
    & npm ci --ignore-scripts 2>$null
    & npm run build

    # Step 3: Create output directory
    Write-Host '[3/4] Packaging...'
    if (-not (Test-Path $OutputDir)) {
        New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
    }
    $zipPath = Join-Path $OutputDir $zipName

    # Step 4: Create ZIP excluding dev/secret files
    Write-Host '[4/4] Creating ZIP...'

    # Use git archive as base (respects .gitignore), then add vendor/ and public/build/
    $tempDir = Join-Path $env:TEMP "listinghub-build-$(Get-Random)"
    New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

    # Export tracked files
    & git archive HEAD --format=tar | tar -xf - -C $tempDir

    # Copy vendor (no-dev)
    Copy-Item -Path 'vendor' -Destination (Join-Path $tempDir 'vendor') -Recurse -Force

    # Copy built assets
    if (Test-Path 'public/build') {
        Copy-Item -Path 'public/build' -Destination (Join-Path $tempDir 'public/build') -Recurse -Force
    }

    # Ensure writable dirs exist with .gitkeep
    @('storage/app', 'storage/framework/cache', 'storage/framework/sessions',
      'storage/framework/views', 'storage/logs', 'bootstrap/cache') | ForEach-Object {
        $dir = Join-Path $tempDir $_
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        if (-not (Test-Path (Join-Path $dir '.gitkeep'))) {
            New-Item -ItemType File -Path (Join-Path $dir '.gitkeep') -Force | Out-Null
        }
    }

    # Remove files that must NOT ship
    @('.env', 'database/database.sqlite', '.git', 'node_modules', 'tests',
      'storage/app/installed.lock', 'storage/app/installation.running',
      'storage/app/installation.pending', 'storage/app/purifier',
      '.github', 'phpstan.neon', 'deptrac.yaml', 'pint.json',
      'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
      'package.json', 'package-lock.json', 'phpunit.xml') | ForEach-Object {
        $target = Join-Path $tempDir $_
        if (Test-Path $target) {
            Remove-Item -Path $target -Recurse -Force
        }
    }

    # Create ZIP
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    Compress-Archive -Path (Join-Path $tempDir '*') -DestinationPath $zipPath -Force

    # Cleanup
    Remove-Item -Path $tempDir -Recurse -Force

    $sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
    Write-Host ""
    Write-Host "Deploy package ready: $zipPath ($sizeMB MB)" -ForegroundColor Green
    Write-Host "Upload, extract, point document root to public/, visit /install" -ForegroundColor Yellow

} finally {
    Pop-Location
    # Restore dev dependencies for local development
    Write-Host 'Restoring dev dependencies...'
    Push-Location $root
    & php composer.phar install --no-interaction 2>$null
    if (-not $?) { & composer install --no-interaction 2>$null }
    Pop-Location
}
