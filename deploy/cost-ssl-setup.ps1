# Cost Dashboard SSL Configuration Script
# Doctrine: SCRIPT THE REPETITION - SSL automation
# Usage: .\deploy\cost-ssl-setup.ps1

param(
    [string]$Subdomain = "cost.patriotpest.pro"
)

Write-Host "=== Cost Dashboard SSL Configuration ===" -ForegroundColor Cyan
Write-Host "Target: $Subdomain" -ForegroundColor Yellow
Write-Host "Host: Hostinger Shared Hosting" -ForegroundColor Gray
Write-Host ""

# Hostinger SSL configuration
Write-Host "[1/2] Hostinger SSL Configuration" -ForegroundColor Cyan
Write-Host "Access Hostinger panel: https://hpanel.hostinger.com" -ForegroundColor Yellow
Write-Host ""
Write-Host "Steps:" -ForegroundColor White
Write-Host "1. Log in to Hostinger panel" -ForegroundColor Gray
Write-Host "2. Navigate to Domains > patriotpest.pro > SSL" -ForegroundColor Gray
Write-Host "3. Select cost.patriotpest.pro subdomain" -ForegroundColor Gray
Write-Host "4. Choose Let's Encrypt (free) or purchase Hostinger SSL" -ForegroundColor Gray
Write-Host "5. Click Install/Request SSL certificate" -ForegroundColor Gray
Write-Host "6. Wait for certificate issuance (usually instant for Let's Encrypt)" -ForegroundColor Gray
Write-Host ""

$ready = Read-Host "Have you completed the Hostinger SSL configuration? (y/n)"
if ($ready -ne "y") {
    Write-Host "SSL configuration aborted. Complete Hostinger steps and re-run." -ForegroundColor Red
    exit 1
}
Write-Host "✓ Hostinger SSL configuration marked complete" -ForegroundColor Green

# SSL verification
Write-Host ""
Write-Host "[2/2] Verifying SSL Certificate" -ForegroundColor Cyan
try {
    $cert = Invoke-WebRequest -Uri "https://$Subdomain" -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
    Write-Host "✓ SSL certificate valid" -ForegroundColor Green
    Write-Host "  Status: $($cert.StatusCode)" -ForegroundColor Gray
    Write-Host "  Protocol: HTTPS" -ForegroundColor Gray
}
catch {
    Write-Host "ERROR: SSL verification failed" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Troubleshooting:" -ForegroundColor Yellow
    Write-Host "- Check DNS propagation: nslookup $Subdomain" -ForegroundColor Gray
    Write-Host "- Verify Hostinger SSL certificate status in panel" -ForegroundColor Gray
    Write-Host "- Ensure DNS A record points to correct Hostinger IP" -ForegroundColor Gray
    Write-Host "- Wait for SSL certificate propagation (up to 1 hour)" -ForegroundColor Gray
    exit 1
}

Write-Host ""
Write-Host "=== SSL Configuration Complete ===" -ForegroundColor Cyan
Write-Host "Next step: Run health check verification" -ForegroundColor Yellow
Write-Host "Command: .\deploy\cost-health-check.ps1" -ForegroundColor Gray
