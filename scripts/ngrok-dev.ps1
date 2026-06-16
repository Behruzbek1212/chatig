# ChatiG local dev: expose Sail (port 8080) via ngrok for Instagram OAuth + webhooks.
# Usage: .\scripts\ngrok-dev.ps1

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $repoRoot '.env'
$port = 8080

function Get-EnvValue([string]$key) {
    if (-not (Test-Path $envFile)) {
        return $null
    }

    foreach ($line in Get-Content $envFile) {
        if ($line -match "^\s*$([regex]::Escape($key))\s*=\s*(.*)\s*$") {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }

    return $null
}

function Set-EnvValue([string]$key, [string]$value) {
    $content = Get-Content $envFile -Raw
    $pattern = "(?m)^\s*$([regex]::Escape($key))\s*=.*$"

    if ($content -match $pattern) {
        $content = [regex]::Replace($content, $pattern, "$key=$value")
    } else {
        $content = $content.TrimEnd() + "`r`n$key=$value`r`n"
    }

    Set-Content -Path $envFile -Value $content -NoNewline
}

$ngrokCmd = Get-Command ngrok -ErrorAction SilentlyContinue
$ngrok = if ($ngrokCmd) { $ngrokCmd.Source } else { $null }
if (-not $ngrok) {
    $ngrok = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Recurse -Filter ngrok.exe -ErrorAction SilentlyContinue |
        Select-Object -First 1 -ExpandProperty FullName
}

if (-not $ngrok) {
    Write-Host 'ngrok topilmadi. O''rnating: winget install ngrok.ngrok' -ForegroundColor Red
    exit 1
}

$authtoken = Get-EnvValue 'NGROK_AUTHTOKEN'
if ([string]::IsNullOrWhiteSpace($authtoken)) {
    Write-Host ''
    Write-Host 'NGROK_AUTHTOKEN .env faylida yo''q.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host '1) https://dashboard.ngrok.com/signup — bepul ro''yxatdan o''ting'
    Write-Host '2) https://dashboard.ngrok.com/get-started/your-authtoken — token nusxalang'
    Write-Host '3) .env ga qo''shing: NGROK_AUTHTOKEN=your_token_here'
    Write-Host '4) Bu skriptni qayta ishga tushiring'
    Write-Host ''
    exit 1
}

& $ngrok config add-authtoken $authtoken | Out-Null

$existing = Get-Process -Name ngrok -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host 'Eski ngrok jarayoni to''xtatilmoqda...' -ForegroundColor Yellow
    $existing | Stop-Process -Force
    Start-Sleep -Seconds 1
}

Write-Host "ngrok tunnel ochilmoqda (localhost:$port)..." -ForegroundColor Cyan
Start-Process -FilePath $ngrok -ArgumentList @('http', "$port", '--log=stdout') -WindowStyle Minimized | Out-Null

$publicUrl = $null
for ($i = 0; $i -lt 20; $i++) {
    Start-Sleep -Seconds 1
    try {
        $tunnels = Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 2
        $publicUrl = ($tunnels.tunnels | Where-Object { $_.proto -eq 'https' } | Select-Object -First 1).public_url
        if ($publicUrl) { break }
    } catch {
        # ngrok API hali tayyor emas
    }
}

if (-not $publicUrl) {
    Write-Host 'ngrok URL olinmadi. http://127.0.0.1:4040 ni tekshiring.' -ForegroundColor Red
    exit 1
}

$redirectUri = "$publicUrl/api/v1/integrations/instagram/callback"
$webhookUrl = "$publicUrl/api/v1/webhooks/instagram"

Set-EnvValue 'INSTAGRAM_REDIRECT_URI' $redirectUri

Write-Host ''
Write-Host 'ngrok ishga tushdi!' -ForegroundColor Green
Write-Host "  Public URL:    $publicUrl"
Write-Host "  OAuth callback: $redirectUri"
Write-Host "  Webhook URL:    $webhookUrl"
Write-Host "  Dashboard:      http://127.0.0.1:4040"
Write-Host ''
Write-Host '.env dagi INSTAGRAM_REDIRECT_URI yangilandi.' -ForegroundColor Green
Write-Host 'Meta Developer App da ham shu URL larni qo''shing:' -ForegroundColor Yellow
Write-Host "  - OAuth redirect: $redirectUri"
Write-Host "  - Webhook callback: $webhookUrl"
Write-Host ''
Write-Host 'Laravel config cache tozalash:' -ForegroundColor Cyan
Write-Host '  docker compose exec laravel.test php artisan config:clear'
