param(
    [ValidateSet('apk', 'appbundle', 'web')]
    [string]$Target = 'apk',
    [ValidateSet('debug', 'profile', 'release')]
    [string]$Mode = 'release'
)

$ErrorActionPreference = 'Stop'
$mobileRoot = $PSScriptRoot
$configPath = Join-Path $mobileRoot 'config/api.production.json'

if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
    throw "Missing production API configuration: $configPath"
}

$settings = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
$apiUrl = [string]$settings.API_BASE_URL
$parsedUrl = $null
if (
    [string]::IsNullOrWhiteSpace($apiUrl) -or
    -not [Uri]::TryCreate($apiUrl, [UriKind]::Absolute, [ref]$parsedUrl) -or
    $parsedUrl.Scheme -ne 'https' -or
    [string]::IsNullOrWhiteSpace($parsedUrl.Host) -or
    -not [string]::IsNullOrWhiteSpace($parsedUrl.UserInfo) -or
    -not [string]::IsNullOrWhiteSpace($parsedUrl.Query) -or
    -not [string]::IsNullOrWhiteSpace($parsedUrl.Fragment) -or
    -not $parsedUrl.AbsolutePath.TrimEnd('/').EndsWith('/api/mobile')
) {
    throw 'config/api.production.json must contain a valid HTTPS API_BASE_URL.'
}

if ($Target -in @('apk', 'appbundle') -and $Mode -eq 'release') {
    $keyProperties = Join-Path $mobileRoot 'android/key.properties'
    $debugReleaseAllowed =
        $env:AQUASENSE_ALLOW_DEBUG_RELEASE_SIGNING -eq 'true'
    if (-not (Test-Path -LiteralPath $keyProperties) -and -not $debugReleaseAllowed) {
        throw @'
Release APK signing is not configured. Add the ignored android/key.properties
file for production, or build an installable test APK with:
  .\build-production.ps1 -Target apk -Mode debug
'@
    }
}

Push-Location $mobileRoot
try {
    & flutter build $Target "--$Mode" "--dart-define-from-file=$configPath"
    if ($LASTEXITCODE -ne 0) {
        throw "Flutter production build failed with exit code $LASTEXITCODE."
    }
} finally {
    Pop-Location
}
