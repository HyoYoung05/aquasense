param(
    [ValidateSet('chrome', 'web-server')]
    [string]$Device = 'chrome',
    [ValidateRange(1, 65535)]
    [int]$Port = 58047,
    [string]$ApiConfig = 'config/api.local.json'
)

$ErrorActionPreference = 'Stop'
$mobileRoot = $PSScriptRoot
$configPath = if ([System.IO.Path]::IsPathRooted($ApiConfig)) {
    $ApiConfig
} else {
    Join-Path $mobileRoot $ApiConfig
}

if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
    throw "Missing $configPath. Copy config/api.development.example.json to config/api.local.json and set API_BASE_URL."
}

$settings = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
if ([string]::IsNullOrWhiteSpace([string]$settings.API_BASE_URL)) {
    throw 'API_BASE_URL is missing from the local Flutter configuration.'
}

Push-Location $mobileRoot
try {
    & flutter run `
        -d $Device `
        --web-hostname localhost `
        --web-port $Port `
        "--dart-define-from-file=$configPath"
} finally {
    Pop-Location
}
