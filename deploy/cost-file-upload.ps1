# Cost Dashboard File Upload Script
# Doctrine: SCRIPT THE REPETITION - File upload automation
# Usage: .\deploy\cost-file-upload.ps1

param(
    [string]$SourcePath = "G:\Mojo\patriot-pest-app\public\cost",
    [switch]$SkipValidation = $false
)

Write-Host "=== Cost Dashboard File Upload ===" -ForegroundColor Cyan
Write-Host "Source: $SourcePath" -ForegroundColor Yellow
Write-Host "Destination: Hostinger public_html/cost/" -ForegroundColor Yellow
Write-Host ""

# Validate source files
if (-not $SkipValidation) {
    Write-Host "[1/3] Validating source files..." -ForegroundColor Cyan
    $requiredFiles = @(
        "index.php",
        "assets\css\cost.css",
        "assets\js\cost.js",
        "data\pricing.json",
        "health.php"
    )

    $missingFiles = @()
    foreach ($file in $requiredFiles) {
        $fullPath = Join-Path $SourcePath $file
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
}
else {
    Write-Host "[1/3] File validation skipped" -ForegroundColor Gray
}

# Upload instructions
Write-Host ""
Write-Host "[2/3] File Upload Instructions" -ForegroundColor Cyan
Write-Host "Method 1: Hostinger File Manager (Recommended)" -ForegroundColor Yellow
Write-Host "1. Access Hostinger panel: https://hpanel.hostinger.com" -ForegroundColor White
Write-Host "2. Navigate to Hosting > Files > File Manager" -ForegroundColor White
Write-Host "3. Go to public_html directory" -ForegroundColor White
Write-Host "4. Create 'cost' directory if it doesn't exist" -ForegroundColor White
Write-Host "5. Upload files from $SourcePath to public_html/cost/" -ForegroundColor White
Write-Host "6. Maintain directory structure (assets/css, assets/js, data)" -ForegroundColor Gray
Write-Host ""
Write-Host "Method 2: SFTP/FTP Upload" -ForegroundColor Yellow
Write-Host "1. Use FileZilla or WinSCP with Hostinger SFTP credentials" -ForegroundColor White
Write-Host "2. Connect to Hostinger SFTP server" -ForegroundColor White
Write-Host "3. Navigate to public_html/" -ForegroundColor White
Write-Host "4. Upload cost directory contents to public_html/cost/" -ForegroundColor White
Write-Host ""
Write-Host "SFTP credentials (from Hostinger panel > Hosting > FTP/SFTP):" -ForegroundColor Gray
Write-Host "- Host: sftp.hostinger.com (or from Hostinger panel)" -ForegroundColor Gray
Write-Host "- Port: 21 (FTP) or 22 (SFTP)" -ForegroundColor Gray
Write-Host "- Username: (from Hostinger panel)" -ForegroundColor Gray
Write-Host "- Password: (from Hostinger panel)" -ForegroundColor Gray

$ready = Read-Host "Have you completed the file upload? (y/n)"
if ($ready -ne "y") {
    Write-Host "File upload aborted. Complete upload and re-run." -ForegroundColor Red
    exit 1
}
Write-Host "✓ File upload marked complete" -ForegroundColor Green

# Validate uploaded files
Write-Host ""
Write-Host "[3/3] Validating uploaded files..." -ForegroundColor Cyan
Write-Host "Manual verification required:" -ForegroundColor Yellow
Write-Host "1. Access Hostinger file manager" -ForegroundColor White
Write-Host "2. Verify public_html/cost/index.php exists" -ForegroundColor White
Write-Host "3. Verify public_html/cost/assets/css/cost.css exists" -ForegroundColor White
Write-Host "4. Verify public_html/cost/assets/js/cost.js exists" -ForegroundColor White
Write-Host "5. Verify public_html/cost/data/pricing.json exists" -ForegroundColor White
Write-Host "6. Verify public_html/cost/health.php exists" -ForegroundColor White
Write-Host ""

$validated = Read-Host "Have you verified all files uploaded correctly? (y/n)"
if ($validated -ne "y") {
    Write-Host "File validation failed. Fix upload issues and re-run." -ForegroundColor Red
    exit 1
}
Write-Host "✓ File validation complete" -ForegroundColor Green

Write-Host ""
Write-Host "=== File Upload Complete ===" -ForegroundColor Cyan
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Verify DNS propagation: nslookup cost.patriotpest.pro" -ForegroundColor White
WriteHost "2. Configure SSL: .\deploy\cost-ssl-setup.ps1" -ForegroundColor White
Write-Host "3. Health check: .\deploy\cost-health-check.ps1" -ForegroundColor White
