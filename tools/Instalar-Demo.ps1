[CmdletBinding(DefaultParameterSetName='Comprobar')]
param(
    [Parameter(ParameterSetName='Comprobar')][switch]$Comprobar,
    [Parameter(Mandatory,ParameterSetName='Instalar')][switch]$Instalar,
    [Parameter(Mandatory,ParameterSetName='Instalar')][ValidateSet('cafeteria_defensa_demo')][string]$AceptoBase
)
$ErrorActionPreference = 'Stop'
$phpDemo = 'C:\php83\php.exe'
$scriptDemo = Join-Path $PSScriptRoot 'instalar-demo.php'
if (!(Test-Path -LiteralPath $phpDemo) -or !(Test-Path -LiteralPath $scriptDemo)) { throw 'Falta PHP 8.3 o el ejecutor local.' }
# Solo permitir mensajes literales controlados del ejecutor y sus guardas.
# Nunca reenviar stderr arbitrario de PHP (avisos de arranque, PDO o trazas).
$mensajesDemo = @()
$fuentesDemo = @($scriptDemo,
    (Join-Path $PSScriptRoot '../app/Services/Demostracion/GuardiaInstalacionDemo.php'),
    (Join-Path $PSScriptRoot '../app/Services/Demostracion/InstaladorEsquemaDemo.php'))
foreach ($fuenteDemo in $fuentesDemo) {
    $textoFuenteDemo = [IO.File]::ReadAllText($fuenteDemo, [Text.Encoding]::UTF8)
    foreach ($mensajeDemo in [regex]::Matches($textoFuenteDemo, "throw new RuntimeException\('([^'\r\n]*)'\);")) {
        $mensajesDemo += $mensajeDemo.Groups[1].Value
    }
    foreach ($mensajeDemo in [regex]::Matches($textoFuenteDemo, '(?:echo |fwrite\(STDERR,)"([^"$\r\n]*)"')) {
        $mensajesDemo += $mensajeDemo.Groups[1].Value.Replace('\n', "`n").TrimEnd()
    }
}
$migracionesDemo = @(Get-ChildItem -LiteralPath (Join-Path $PSScriptRoot '../database/migrations') -Filter '*.php' -File | Select-Object -ExpandProperty Name)
function Mostrar-DiagnosticoDemo {
    param([string]$Texto, [switch]$EsError)
    $omitidaDemo = $false
    foreach ($lineaDemo in ($Texto -split '\r?\n')) {
        if ([string]::IsNullOrWhiteSpace($lineaDemo)) { continue }
        $permitidaDemo = ($mensajesDemo -ccontains $lineaDemo) -or ($migracionesDemo -ccontains $lineaDemo) -or
            ($lineaDemo -cmatch '^Migraciones locales a revisar: [0-9]+\. Sin seeders ni fixtures\.$') -or
            ($lineaDemo -cmatch '^Falta un privilegio necesario para instalar o inspeccionar: (SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|INDEX|REFERENCES|DROP|SHOW VIEW|TRIGGER|EVENT|ALTER ROUTINE)\.$')
        if ($permitidaDemo) {
            if ($EsError) { Write-Host $lineaDemo -ForegroundColor Red } else { Write-Host $lineaDemo }
        } else { $omitidaDemo = $true }
    }
    if ($omitidaDemo) { Write-Host 'Salida no reconocida omitida por seguridad.' -ForegroundColor Yellow }
}
$claveDemo = Read-Host 'Contraseña de karma_demo_instalador (oculta; no se guarda)' -AsSecureString
$punteroDemo = [IntPtr]::Zero
try {
    $punteroDemo = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($claveDemo)
    $entradaDemo = @{password=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($punteroDemo)} | ConvertTo-Json -Compress
    $inicioDemo = New-Object System.Diagnostics.ProcessStartInfo
    $inicioDemo.FileName = $phpDemo
    $inicioDemo.Arguments = '"' + $scriptDemo + '" ' + $(if ($Instalar) {'--instalar --acepto-base=cafeteria_defensa_demo'} else {'--comprobar'})
    $inicioDemo.UseShellExecute = $false
    $inicioDemo.CreateNoWindow = $true
    $inicioDemo.RedirectStandardInput = $true
    $inicioDemo.RedirectStandardOutput = $true
    $inicioDemo.RedirectStandardError = $true
    $inicioDemo.StandardOutputEncoding = New-Object System.Text.UTF8Encoding($false)
    $inicioDemo.StandardErrorEncoding = New-Object System.Text.UTF8Encoding($false)
    $procesoDemo = New-Object System.Diagnostics.Process
    $procesoDemo.StartInfo = $inicioDemo
    [void]$procesoDemo.Start()
    # Drenar ambos flujos simultáneamente antes de escribir stdin o esperar al hijo.
    $lecturaSalidaDemo = $procesoDemo.StandardOutput.ReadToEndAsync()
    $lecturaErrorDemo = $procesoDemo.StandardError.ReadToEndAsync()
    # .NET Framework (PowerShell 5.1) no expone ProcessStartInfo.StandardInputEncoding.
    $escritorDemo = New-Object System.IO.StreamWriter($procesoDemo.StandardInput.BaseStream, (New-Object System.Text.UTF8Encoding($false)))
    $falloEntradaDemo = $false
    try {
        $escritorDemo.Write($entradaDemo)
        $escritorDemo.Close()
    } catch [System.IO.IOException] {
        # PHP puede rechazar la configuración antes de leer stdin. Conservar su diagnóstico.
        $falloEntradaDemo = $true
        $procesoDemo.StandardInput.BaseStream.Close()
    }
    $entradaDemo = $null
    $procesoDemo.WaitForExit()
    $salidaDemo = $lecturaSalidaDemo.GetAwaiter().GetResult()
    $errorDemo = $lecturaErrorDemo.GetAwaiter().GetResult()
    Mostrar-DiagnosticoDemo -Texto $salidaDemo
    Mostrar-DiagnosticoDemo -Texto $errorDemo -EsError
    if ($procesoDemo.ExitCode -ne 0) { throw ('El verificador/instalador se detuvo (codigo {0}). No se reejecuta automáticamente.' -f $procesoDemo.ExitCode) }
    if ($falloEntradaDemo) { throw 'No se confirmó la entrega por stdin. No se reejecuta automáticamente.' }
} finally {
    if ($escritorDemo) {
        try { $escritorDemo.Dispose() } catch [System.IO.IOException] { }
    }
    $entradaDemo = $null
    $salidaDemo = $null
    $errorDemo = $null
    $lecturaSalidaDemo = $null
    $lecturaErrorDemo = $null
    if ($punteroDemo -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($punteroDemo) }
    if ($claveDemo) { $claveDemo.Dispose() }
    if ($procesoDemo) { $procesoDemo.Dispose() }
}
