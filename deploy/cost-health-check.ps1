# Cost Dashboard Health Check Verification Script
# Doctrine: DEBUGGING IS A FEATURE - Health endpoint verification
# Usage: .\deploy\cost-health-check.ps1

param(
    [string]$Subdomain = "cost.patriotpest.pro"
)

Write-Host "=== Cost Dashboard Health Check ===" -ForegroundColor Cyan
Write-Host "Target: https://$Subdomain/health" -ForegroundColor Yellow
Write-Host ""

# Test health endpoint
Write-Host "[1/3] Testing health endpoint..." -ForegroundColor Cyan
try {
    $healthResponse = Invoke-WebRequest -Uri "https://$Subdomain/health" -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
    Write-Host "✓ Health endpoint responding" -ForegroundColor Green
    Write-Host "  Status: $($healthResponse.StatusCode)" -ForegroundColor Gray
    Write-Host "  Content: $($healthResponse.Content)" -ForegroundColor Gray
}
catch {
    Write-Host "ERROR: Health endpoint failed" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Troubleshooting:" -ForegroundColor Yellow
    Write-Host "- Verify DNS: nslookup $Subdomain" -ForegroundColor Gray
    Write-Host "- Verify SSL: Test https://$Subdomain in browser" -ForegroundColor Gray
    Write-Host "- Check .htaccess routing configuration" -ForegroundColor Gray
    Write-Host "- Verify COST_DASHBOARD_ENABLED=true in .env" -ForegroundColor Gray
    exit 1
}

# Test main dashboard page
Write-Host ""
Write-Host "[2/3] Testing main dashboard page..." -ForegroundColor Cyan
try {
    $dashboardResponse = Invoke-WebRequest -Uri "https://$Subdomain" -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
    Write-Host "✓ Dashboard page responding" -ForegroundColor Green
    Write-Host "  Status: $($dashboardResponse.StatusCode)" -ForegroundColor Gray
    
    if ($dashboardResponse.Content -match "cost|pricing|dashboard") {
        Write-Host "  Content: Dashboard HTML detected" -ForegroundColor Gray
    }
    else {
        Write-Host "  WARNING: Dashboard content not detected" -ForegroundColor Yellow
    }
}
catch {
    Write-Host "ERROR: Dashboard page failed" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Test static assets
Write-Host ""
Write-Host "[3/3] Testing static assets..." -ForegroundColor Cyan
$assets = @(
    "https://$Subdomain/cost/assets/css/cost.css",
    "https://$Subdomain/cost/assets/js/cost.js"
)

$failedAssets = @()
foreach ($asset in $assets) {
    try {
        $assetResponse = Invoke-WebRequest -Uri $asset -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
        Write-Host "✓ $asset" -ForegroundColor Green
    }
    catch {
        Write-Host "✗ $asset" -ForegroundColor Red
        $failedAssets += $asset
    }
}

if ($failedAssets.Count -gt 0) {
    Write-Host ""
    Write-Host "WARNING: Some assets failed to load" -ForegroundColor Yellow
    Write-Host "Failed:" -ForegroundColor Red
    $failedAssets | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
}

# Summary
Write-Host ""
Write-Host "=== Health Check Complete ===" -ForegroundColor Cyan
if ($failedAssets.Count -eq 0) {
    Write-Host "Status: ALL CHECKS PASSED" -ForegroundColor Green
    Write-Host "Deployment: https://$Subdomain is live and healthy" -ForegroundColor Green
}
else {
    Write-Host "Status: PARTIAL FAILURE" -ForegroundColor Yellow
    Write-Host "Core functionality: OK" -ForegroundColor Green
    Write-Host "Static assets: FAILED" -ForegroundColor Red
}

Write-Host ""
Write-Host "Manual verification checklist:" -ForegroundColor Yellow
Write-Host "- Open https://$Subdomain in browser" -ForegroundColor White
Write-Host "- Verify tactical theme loads correctly" -ForegroundColor White
Write-Host "- Check charts render with pricing data" -ForegroundColor White
Write-Host "- Test mobile responsiveness" -ForegroundColor White
