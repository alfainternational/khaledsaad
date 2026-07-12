param(
    [string] $CredentialPath = (Join-Path $PSScriptRoot 'worker.credentials.dpapi'),
    [string] $PythonExe = 'python',
    [string] $WorkerScript = (Join-Path $PSScriptRoot 'worker.py')
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $CredentialPath) -or -not (Test-Path -LiteralPath $WorkerScript)) {
    throw 'Private worker runtime files are missing.'
}

$encrypted = [IO.File]::ReadAllBytes($CredentialPath)
$plain = [Security.Cryptography.ProtectedData]::Unprotect(
    $encrypted,
    $null,
    [Security.Cryptography.DataProtectionScope]::CurrentUser
)

try {
    $credentials = [Text.Encoding]::UTF8.GetString($plain) | ConvertFrom-Json
    $env:AI_WORKER_SERVER_URL = [string] $credentials.server_url
    $env:AI_WORKER_ID = [string] $credentials.worker_id
    $env:AI_WORKER_SECRET = [string] $credentials.worker_secret
    $env:AI_WORKER_CAPABILITIES = [string] $credentials.capabilities
    $env:AI_WORKER_OLLAMA_URL = [string] $credentials.ollama_url
    $env:AI_WORKER_OLLAMA_MODEL = [string] $credentials.ollama_model
    $env:AI_WORKER_HTTP_TIMEOUT = [string] $credentials.http_timeout

    & $PythonExe $WorkerScript --once
    exit $LASTEXITCODE
} finally {
    [Array]::Clear($plain, 0, $plain.Length)
    Remove-Item Env:AI_WORKER_SECRET -ErrorAction SilentlyContinue
}
