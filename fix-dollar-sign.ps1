# Fix escaped dollar sign bug in class-alloia-core.php
$ErrorActionPreference = "Stop"

$file = "includes/class-alloia-core.php"

Write-Host "Fixing escaped dollar sign in $file..." -ForegroundColor Yellow

$lines = Get-Content $file
$lineNumber = 200  # 0-indexed (line 201)

Write-Host "Line $($lineNumber + 1) before: $($lines[$lineNumber])" -ForegroundColor Gray

# Fix the escaped dollar sign
$lines[$lineNumber] = $lines[$lineNumber] -replace '\\', ''

Write-Host "Line $($lineNumber + 1) after:  $($lines[$lineNumber])" -ForegroundColor Gray

$lines | Set-Content $file

# Verify
$check = (Get-Content $file)[200]
if ($check -match '\\$') {
    Write-Host "✗ Still has escaped dollar sign!" -ForegroundColor Red
} else {
    Write-Host "✓ Fixed successfully!" -ForegroundColor Green
}

Write-Host "`nVerifying: $check"

