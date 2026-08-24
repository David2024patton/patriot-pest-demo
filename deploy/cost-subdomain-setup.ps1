# Cost Dashboard Subdomain Deployment Script
# Doctrine: SCRIPT THE REPETITION - Deployment automation
# Usage: .\deploy\cost-subdomain-setup.ps1

param(
    [string]$Subdomain = "cost.patriotpest.pro",
    [switch]$SkipDNS = $false,
    [switch]$SkipSSL = $false
)

Write-Host "=== Cost Dashboard Subdomain Setup ===" -ForegroundColor Cyan
Write-Host "Target: $Subdomain" -ForegroundColor Yellow
Write-Host ""

# Function to test HTTP endpoint
function Test-HttpEndpoint {
    param([string]$Url)
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10
        return @{
            Success = $true
            StatusCode = $response.StatusCode
            Status = $response.StatusDescription
        }
    }
    catch {
        return @{
            Success = $false
            Error = $_.Exception.Message
        }
    }
}

# Step 1: Validate file structure
Write-Host "[1/5] Validating file structure..." -ForegroundColor Cyan
$requiredFiles = @(
    "public\cost\index.php",
    "public\cost\assets\css\cost.css",
    "public\cost\assets\js\cost.js",
    "public\cost\data\pricing.json",
    "public\cost\health.php"
)

$missingFiles = @()
foreach ($file in $requiredFiles) {
    $fullPath = Join-Path $PSScriptRoot ".." $file
    if (-not (Test-Path $fullPath)) {
        $missingFiles += $file
    }
}

if ($missingFiles.Count -gt 0) {
    Write-Host "ERROR: Missing required files:" -ForegroundColor Red
    $missingFiles | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    exit 1
}
Write-Host "✓ All required files present" -ForegroundColor Green

# Step 2: Validate .htaccess configuration
Write-Host "[2/5] Validating .htaccess configuration..." -ForegroundColor Cyan
$htaccessPath = Join-Path $PSScriptRoot "..\public\.htaccess"
$htaccessContent = Get-Content $htaccessPath -Raw

if ($htaccessContent -notmatch "cost\.patriotpest\.pro") {
    Write-Host "ERROR: Subdomain routing not found in .htaccess" -ForegroundColor Red
    exit 1
}
Write-Host "✓ .htaccess contains subdomain routing" -ForegroundColor Green

# Step 3: Validate router.php configuration
Write-Host "[3/5] Validating router.php configuration..." -ForegroundColor Cyan
$routerPath = Join-Path $PSScriptRoot "..\public\router.php"
$routerContent = Get-Content $routerPath -Raw

if ($routerContent -notmatch "cost\.patriotpest\.pro") {
    Write-Host "ERROR: Subdomain routing not found in router.php" -ForegroundColor Red
    exit 1
}
Write-Host "✓ router.php contains subdomain routing" -ForegroundColor Green

# Step 4: Validate feature toggle
Write-Host "[4/5] Validating feature toggle..." -ForegroundColor Cyan
$envPath = Join-Path $PSScriptRoot "..\.env"
$envContent = Get-Content $envPath -Raw

if ($envContent -notmatch "COST_DASHBOARD_ENABLED") {
    Write-Host "WARNING: COST_DASHBOARD_ENABLED not found in .env" -ForegroundColor Yellow
    Write-Host "Adding default value..." -ForegroundColor Yellow
    Add-Content $envPath "`nCOST_DASHBOARD_ENABLED=true"
}
else {
    if ($envContent -match "COST_DASHBOARD_ENABLED=(false|0)") {
        Write-Host "WARNING: Cost dashboard is disabled in .env" -ForegroundColor Yellow
    }
    else {
        Write-Host "✓ Cost dashboard is enabled" -ForegroundColor Green
    }
}

# Step 5: DNS validation (if not skipped)
if (-not $SkipDNS) {
    Write-Host "[5/5] Validating DNS configuration..." -ForegroundColor Cyan
    Write-Host "Checking DNS for $Subdomain..." -ForegroundColor Yellow
    
    try {
        $dnsResult = Resolve-DnsName -Name $Subdomain -ErrorAction SilentlyContinue
        if ($dnsResult) {
            Write-Host "✓ DNS record found" -ForegroundColor Green
            $dnsResult | ForEach-Object {
                Write-Host "  Type: $($_.Type), Address: $($_.IPAddress)" -ForegroundColor Gray
            }
        }
        else {
            Write-Host "WARNING: DNS record not found for $Subdomain" -ForegroundColor Yellow
            Write-Host ""
            Write-Host "MANUAL DNS CONFIGURATION REQUIRED:" -ForegroundColor Yellow
            Write-Host "1. Log into Hostinger panel: https://hpanel.hostinger.com" -ForegroundColor White
            Write-Host "2. Navigate to Domains > patriotpest.pro > DNS" -ForegroundColor White
            Write-Host "3. Add new A record:" -ForegroundColor White
            Write-Host "   - Type: A" -ForegroundColor Gray
            Write-Host "   - Name: cost" -ForegroundColor Gray
            Write-Host "   - TTL: 3600 (1 hour)" -ForegroundColor Gray
            Write-Host "   - Points to: 212.1.212.162" -ForegroundColor Gray
            Write-Host "4. Save changes" -ForegroundColor White
            Write-Host "5. Allow up to 24 hours for DNS propagation" -ForegroundColor Yellow
            Write-Host ""
            Write-Host "Note: Hostinger API is currently returning 530 errors (Cloudflare origin issues)." -ForegroundColor Gray
            Write-Host "      Manual configuration via panel is required until API is fixed." -ForegroundColor Gray
        }
    }
    catch {
        Write-Host "WARNING: DNS lookup failed: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}
else {
    Write-Host "[5/5] DNS validation skipped" -ForegroundColor Gray
}

# SSL validation (if not skipped)
if (-not $SkipSSL) {
    Write-Host ""
    Write-Host "SSL Certificate Configuration..." -ForegroundColor Cyan
    Write-Host "SSL configuration via Hostinger panel:" -ForegroundColor Yellow
    Write-Host "1. Access Hostinger panel: https://hpanel.hostinger.com" -ForegroundColor White
    Write-Host "2. Navigate to Domains > patriotpest.pro > SSL" -ForegroundColor White
    Write-Host "3. Request SSL certificate for cost.patriotpest.pro" -ForegroundColor White
    Write-Host "4. Use Let's Encrypt (free) or purchase Hostinger SSL" -ForegroundColor White
    Write-Host "5. Wait for certificate issuance (usually instant for Let's Encrypt)" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Note: patriotpest.pro is hosted on Hostinger shared hosting." -ForegroundColor Gray
    Write-Host "      SSL must be configured via Hostinger panel, not Dokploy VPS." -ForegroundColor Gray
}
else {
    Write-Host ""
    Write-Host "SSL validation skipped" -ForegroundColor Gray
}

# Health check test
Write-Host ""
Write-Host "Testing health endpoint..." -ForegroundColor Cyan
$healthTest = Test-HttpEndpoint -Url "http://localhost:8080/cost/health"
if ($healthTest.Success) {
    Write-Host "✓ Health endpoint responding" -ForegroundColor Green
}
else {
    Write-Host "WARNING: Health endpoint not responding" -ForegroundColor Yellow
    Write-Host "Error: $($healthTest.Error)" -ForegroundColor Yellow
}

# Summary
Write-Host ""
Write-Host "=== Deployment Validation Complete ===" -ForegroundColor Cyan
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Configure DNS A record for $Subdomain (manual via Hostinger panel)" -ForegroundColor White
Write-Host "2. Upload cost dashboard files to Hostinger public/cost/ directory" -ForegroundColor White
Write-Host "3. Verify DNS propagation: nslookup $Subdomain" -ForegroundColor White
Write-Host "4. Configure SSL certificate via Hostinger panel" -ForegroundColor White
Write-Host "5. Test subdomain access in browser: https://$Subdomain" -ForegroundColor White
Write-Host "6. Verify health endpoint: https://$Subdomain/health" -ForegroundColor White
Write-Host ""
Write-Host "File upload methods:" -ForegroundColor Yellow
Write-Host "- Hostinger file manager (hpanel.hostinger.com > Files)" -ForegroundColor White
Write-Host "- SFTP/FTP to Hostinger account" -ForegroundColor White
Write-Host "- Upload public/cost/ directory contents to public_html/cost/" -ForegroundColor Gray
Write-Host ""
Write-Host "Post-deployment verification:" -ForegroundColor Yellow
Write-Host "- HTTP status 200 on https://$Subdomain" -ForegroundColor White
Write-Host "- Valid SSL certificate (no browser warnings)" -ForegroundColor White
Write-Host "- Health endpoint returns JSON status" -ForegroundColor White
Write-Host "- Dashboard loads with tactical theme" -ForegroundColor White
Write-Host ""
Write-Host "To disable dashboard: Set COST_DASHBOARD_ENABLED=false in .env" -ForegroundColor Gray
