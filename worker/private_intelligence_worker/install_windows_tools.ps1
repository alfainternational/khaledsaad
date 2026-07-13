$ErrorActionPreference = 'Stop'

$download = 'D:\TesseractInstaller'
$tesseractDir = 'D:\Tesseract-OCR'
New-Item -ItemType Directory -Force -Path $download | Out-Null

$artifacts = @(
    @('https://www.7-zip.org/a/7zr.exe', "$download\7zr.exe", '56B8CC9F4971CEF253644FAFE54063ED7FDCA551D4DEE0F8C6BAA81B855ACD72'),
    @('https://github.com/ip7z/7zip/releases/download/26.02/7z2602-x64.exe', "$download\7zip-x64.exe", '6745FA76DC2EA031596D8678F6F6B99C3C1B435B4164A63485ADBBC7B8D82EF0'),
    @('https://github.com/tesseract-ocr/tesseract/releases/download/5.5.0/tesseract-ocr-w64-setup-5.5.0.20241111.exe', "$download\tesseract-5.5.0.exe", 'F3FC4236425B690C8BE756F35793F77394EE004BE0A6460A440C754D892F68BC')
)

foreach ($artifact in $artifacts) {
    if (-not (Test-Path $artifact[1])) {
        Invoke-WebRequest -Uri $artifact[0] -OutFile $artifact[1] -UseBasicParsing
    }
    $actual = (Get-FileHash $artifact[1] -Algorithm SHA256).Hash
    if ($actual -ne $artifact[2]) {
        throw "Checksum mismatch for $($artifact[1])."
    }
}

& "$download\7zr.exe" x "$download\7zip-x64.exe" "-o$download\7zfull" -y | Out-Null
& "$download\7zfull\7z.exe" x "$download\tesseract-5.5.0.exe" "-o$tesseractDir" -y | Out-Null
if (-not (Test-Path "$tesseractDir\tesseract.exe")) {
    throw 'Portable Tesseract extraction failed.'
}

$languages = @(
    @('ara', 'E3206D3DC87FD50C24A0FB9F01838615911D25168F4E64415244B67D2BB3E729'),
    @('eng', '7D4322BD2A7749724879683FC3912CB542F19906C83BCC1A52132556427170B2'),
    @('osd', '9CF5D576FCC47564F11265841E5CA839001E7E6F38FF7F7AACF46D15A96B00FF')
)
foreach ($language in $languages) {
    $path = "$tesseractDir\tessdata\$($language[0]).traineddata"
    if (-not (Test-Path $path)) {
        Invoke-WebRequest -Uri "https://github.com/tesseract-ocr/tessdata_fast/raw/refs/heads/main/$($language[0]).traineddata" -OutFile $path -UseBasicParsing
    }
    if ((Get-FileHash $path -Algorithm SHA256).Hash -ne $language[1]) {
        throw "Checksum mismatch for OCR language $($language[0])."
    }
}

winget install --id oschwartz10612.Poppler --exact --silent --accept-package-agreements --accept-source-agreements --disable-interactivity
$popplerDir = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter pdftotext.exe -Recurse |
    Select-Object -First 1 -ExpandProperty DirectoryName
if (-not $popplerDir) {
    throw 'Poppler executables were not found after installation.'
}

$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
foreach ($directory in @($tesseractDir, $popplerDir)) {
    if (($userPath -split ';') -notcontains $directory) {
        $userPath = ($userPath.TrimEnd(';') + ';' + $directory).Trim(';')
    }
}
[Environment]::SetEnvironmentVariable('Path', $userPath, 'User')
$env:Path = "$tesseractDir;$popplerDir;$env:Path"

Write-Output (& "$tesseractDir\tesseract.exe" --version 2>&1 | Select-Object -First 1)
Write-Output (& "$popplerDir\pdftotext.exe" -v 2>&1 | Select-Object -First 1)
Write-Output (& "$tesseractDir\tesseract.exe" --list-langs 2>&1)
