param(
    [string] $CredentialPath = (Join-Path $PSScriptRoot 'worker.credentials.dpapi'),
    [string] $PythonExe = 'python',
    [string] $WorkerScript = (Join-Path $PSScriptRoot 'worker.py'),
    [switch] $Continuous
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $CredentialPath) -or -not (Test-Path -LiteralPath $WorkerScript)) {
    throw 'Private worker runtime files are missing.'
}

try {
    $securePayload = Get-Content -LiteralPath $CredentialPath -Raw | ConvertTo-SecureString
    $plainPayload = [Net.NetworkCredential]::new('', $securePayload).Password
    $credentials = $plainPayload | ConvertFrom-Json
    $env:AI_WORKER_SERVER_URL = [string] $credentials.server_url
    $env:AI_WORKER_ID = [string] $credentials.worker_id
    $env:AI_WORKER_SECRET = [string] $credentials.worker_secret
    $env:AI_WORKER_CAPABILITIES = [string] $credentials.capabilities
    $env:AI_WORKER_OLLAMA_URL = [string] $credentials.ollama_url
    $env:AI_WORKER_OLLAMA_MODEL = [string] $credentials.ollama_model
    $env:AI_WORKER_OLLAMA_MODELS = [string] $credentials.ollama_models
    $env:AI_WORKER_HTTP_TIMEOUT = [string] $credentials.http_timeout
    $env:AI_WORKER_HTTP_HOST = [string] $credentials.http_host
    $env:AI_WORKER_TLS_CHECK_HOSTNAME = [string] $credentials.tls_check_hostname
    $env:AI_WORKER_TLS_SERVER_NAME = [string] $credentials.tls_server_name

    $tunnel = $null
    if ($credentials.ssh_target -and $credentials.ssh_key_path -and $credentials.tunnel_port) {
        $forwardHost = if ($credentials.ssh_forward_host) { [string] $credentials.ssh_forward_host } else { '127.0.0.1' }
        $forward = "127.0.0.1:$($credentials.tunnel_port):${forwardHost}:443"
        $arguments = @(
            '-N', '-L', $forward,
            '-i', [string] $credentials.ssh_key_path,
            '-p', [string] $credentials.ssh_port,
            '-o', 'BatchMode=yes',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ConnectTimeout=20',
            [string] $credentials.ssh_target
        )
        $tunnel = Start-Process -FilePath 'ssh.exe' -ArgumentList $arguments -WindowStyle Hidden -PassThru
        $ready = $false
        foreach ($attempt in 1..20) {
            Start-Sleep -Milliseconds 250
            try {
                $client = [Net.Sockets.TcpClient]::new('127.0.0.1', [int] $credentials.tunnel_port)
                $client.Dispose()
                $ready = $true
                break
            } catch {
                if ($tunnel.HasExited) {
                    break
                }
            }
        }
        if (-not $ready) {
            throw 'The private worker SSH tunnel did not start.'
        }
    }

    $workerArguments = if ($Continuous) { @() } else { @('--once') }
    & $PythonExe $WorkerScript @workerArguments
    exit $LASTEXITCODE
} finally {
    if ($tunnel -and -not $tunnel.HasExited) {
        Stop-Process -Id $tunnel.Id -Force -ErrorAction SilentlyContinue
    }
    $plainPayload = $null
    $securePayload = $null
    Remove-Item Env:AI_WORKER_SECRET -ErrorAction SilentlyContinue
}
