#!/usr/bin/env pwsh
# Build AlloIA WordPress Plugin for deployment

$ErrorActionPreference = "Stop"

# Plugin details
$PLUGIN_NAME = "alloia-woocommerce-plugin"
$PLUGIN_DIR = $PSScriptRoot
$VERSION = "1.8.0"

Write-Host "Building AlloIA WordPress Plugin v${VERSION}..." -ForegroundColor Cyan

# Create temporary build directory
$BUILD_DIR = Join-Path $env:TEMP "alloia-build-$(Get-Date -Format 'yyyyMMddHHmmss')"
$PLUGIN_BUILD_DIR = Join-Path $BUILD_DIR $PLUGIN_NAME
New-Item -ItemType Directory -Path $PLUGIN_BUILD_DIR -Force | Out-Null

Write-Host "Build directory: $BUILD_DIR" -ForegroundColor Gray

# Files and directories to include
$INCLUDE_ITEMS = @(
    "alloia-woocommerce.php",
    "includes",
    "admin",
    "assets",
    "uninstall.php",
    "README.md",
    "CHANGELOG.md",
    "readme.txt"
)

# Copy files to build directory
Write-Host "Copying plugin files..." -ForegroundColor Yellow
foreach ($item in $INCLUDE_ITEMS) {
    $source = Join-Path $PLUGIN_DIR $item
    if (Test-Path $source) {
        $dest = Join-Path $PLUGIN_BUILD_DIR $item
        if (Test-Path $source -PathType Container) {
            Copy-Item -Path $source -Destination $dest -Recurse -Force
            Write-Host "  ✓ Copied directory: $item" -ForegroundColor Green
        } else {
            Copy-Item -Path $source -Destination $dest -Force
            Write-Host "  ✓ Copied file: $item" -ForegroundColor Green
        }
    } else {
        Write-Host "  ⚠ Skipped (not found): $item" -ForegroundColor DarkYellow
    }
}

# Create zip file
$ZIP_NAME = "${PLUGIN_NAME}.zip"
$ZIP_PATH = Join-Path $PLUGIN_DIR $ZIP_NAME

Write-Host "Creating plugin archive..." -ForegroundColor Yellow

# Remove old zip if exists
if (Test-Path $ZIP_PATH) {
    Remove-Item $ZIP_PATH -Force
    Write-Host "  ✓ Removed old archive" -ForegroundColor Gray
}

# Create new zip
Compress-Archive -Path "$PLUGIN_BUILD_DIR\*" -DestinationPath $ZIP_PATH -Force
Write-Host "  ✓ Created: $ZIP_NAME" -ForegroundColor Green

# Cleanup
Remove-Item -Path $BUILD_DIR -Recurse -Force
Write-Host "  ✓ Cleaned up build directory" -ForegroundColor Gray

# Copy to dev environment
$DEV_DIR = Join-Path (Split-Path $PLUGIN_DIR -Parent) "dev wordpress"
$DEV_ZIP_PATH = Join-Path $DEV_DIR $ZIP_NAME

if (Test-Path $DEV_DIR) {
    Copy-Item -Path $ZIP_PATH -Destination $DEV_ZIP_PATH -Force
    Write-Host "`n✓ Plugin copied to dev environment: $DEV_ZIP_PATH" -ForegroundColor Green
} else {
    Write-Host "`n⚠ Dev environment not found: $DEV_DIR" -ForegroundColor Yellow
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Build Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Plugin: $PLUGIN_NAME" -ForegroundColor White
Write-Host "Version: $VERSION" -ForegroundColor White
Write-Host "Archive: $ZIP_PATH" -ForegroundColor White
Write-Host "Size: $([math]::Round((Get-Item $ZIP_PATH).Length / 1KB, 2)) KB" -ForegroundColor White
Write-Host "========================================`n" -ForegroundColor Cyan

