# Pone en marcha La Focaccia di Trevi con Docker en Windows, que no trae make.
# Hace lo mismo que «make start», «make stop» y «make reset» (docker/iniciar.sh).
#
#   powershell -ExecutionPolicy Bypass -File iniciar.ps1          (arrancar)
#   powershell -ExecutionPolicy Bypass -File iniciar.ps1 stop     (parar sin borrar nada)
#   powershell -ExecutionPolicy Bypass -File iniciar.ps1 reset    (borrar la base y empezar de cero)

param(
    [ValidateSet('start', 'stop', 'reset')]
    [string]$Accion = 'start'
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$puertoWeb = if ($env:APP_PUERTO) { $env:APP_PUERTO } else { '8000' }
$puertoCorreo = if ($env:MAILPIT_PUERTO_PANEL) { $env:MAILPIT_PUERTO_PANEL } else { '8025' }
$direccion = "http://localhost:$puertoWeb"

# Título de cada paso, para que se vea por dónde va
function Escribir-Aviso([string]$texto) { Write-Host "`n==> $texto" -ForegroundColor Green }

# Explica qué ha fallado y corta el script
function Salir-ConError([string]$texto) {
    Write-Host "`nERROR: $texto" -ForegroundColor Red
    exit 1
}

# Pide un sí o un no; solo «s» o «sí» cuentan como sí
function Pedir-Confirmacion([string]$texto) {
    $respuesta = Read-Host "$texto [s/N]"
    return $respuesta -match '^(s|si|sí)$'
}

# Con cmd, para que los mensajes de error de docker no paren el script
function Comprobar-Docker {
    cmd /c 'docker info >nul 2>&1'
    return $LASTEXITCODE -eq 0
}

# Con winget y siempre después de preguntar
function Instalar-Docker {
    if (-not (Pedir-Confirmacion 'Docker no está instalado. ¿Instalo Docker Desktop con winget?')) {
        Salir-ConError 'Sin Docker no se puede seguir. Se descarga en https://www.docker.com/products/docker-desktop/'
    }
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        Salir-ConError 'Este Windows no tiene winget. Instala Docker Desktop desde https://www.docker.com/products/docker-desktop/'
    }
    winget install -e --id Docker.DockerDesktop --accept-package-agreements --accept-source-agreements
    Write-Host 'Docker Desktop puede pedir permisos de administrador y reiniciar el equipo para activar WSL2.'
    Write-Host 'Cuando termine, abre Docker Desktop, acepta sus condiciones y vuelve a lanzar iniciar.ps1.'
    exit 0
}

# Docker instalado, pero parado: se abre Docker Desktop y se espera hasta dos minutos
function Esperar-Docker {
    if (Comprobar-Docker) { return }
    Escribir-Aviso 'Arrancando Docker Desktop'
    $programa = Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'
    if (Test-Path $programa) { Start-Process $programa }
    for ($i = 0; $i -lt 60; $i++) {
        if (Comprobar-Docker) { return }
        Start-Sleep -Seconds 2
    }
    Salir-ConError 'Docker no responde. Ábrelo a mano, espera a que diga que está en marcha y vuelve a lanzar iniciar.ps1.'
}

# Contraseña aleatoria de 32 letras y números: sin símbolos, para no romper el .env ni las órdenes de MySQL
function Nueva-Contrasena {
    $letras = [char[]]'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    $azar = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $bytes = New-Object byte[] 32
    $azar.GetBytes($bytes)
    return -join ($bytes | ForEach-Object { $letras[$_ % $letras.Length] })
}

# La primera vez, el .env sale de .env.example con contraseñas nuevas y la clave de la aplicación
function Crear-Env {
    if (Test-Path '.env') { return }
    Escribir-Aviso 'Creando el fichero .env con contraseñas nuevas'
    $azar = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $clave = New-Object byte[] 32
    $azar.GetBytes($clave)
    $valores = @{
        'APP_KEY' = 'base64:' + [Convert]::ToBase64String($clave)
        'DB_PORT' = '3307'
        'DB_ADMIN_CONTRASENA' = Nueva-Contrasena
        'DB_WEB_CONTRASENA' = Nueva-Contrasena
        'DB_GESTION_CONTRASENA' = Nueva-Contrasena
        'DB_INFORMES_CONTRASENA' = Nueva-Contrasena
        'DB_ROOT_CONTRASENA' = Nueva-Contrasena
    }
    $lineas = Get-Content '.env.example' -Encoding UTF8 | ForEach-Object {
        $nombre = ($_ -split '=', 2)[0]
        if ($valores.ContainsKey($nombre)) { "$nombre=$($valores[$nombre])" } else { $_ }
    }
    # UTF-8 sin BOM y con saltos de línea de Unix, como espera Docker
    [System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env'), ($lineas -join "`n") + "`n", (New-Object System.Text.UTF8Encoding $false))
}

# iniciar.ps1 sin nada más: Docker, .env, construcción y arranque, y la dirección para entrar
function Arrancar-Aplicacion {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { Instalar-Docker }
    Esperar-Docker
    Crear-Env

    Escribir-Aviso 'Construyendo y arrancando la aplicación (la primera vez tarda unos minutos)'
    docker compose up -d --build --wait
    if ($LASTEXITCODE -ne 0) {
        Salir-ConError 'La aplicación no ha arrancado. Mira qué ha pasado con: docker compose logs web'
    }

    Escribir-Aviso 'La Focaccia di Trevi está en marcha'
    Write-Host "  Web:                $direccion"
    Write-Host "  Gestión:            $direccion/gestion/acceso"
    Write-Host "  Correos (Mailpit):  http://localhost:$puertoCorreo"
    Write-Host '  Cuentas de prueba:  en la guía de instalación'
    Write-Host '  Para pararla:       iniciar.ps1 stop'
    if (-not $env:SIN_NAVEGADOR) { Start-Process $direccion }
}

switch ($Accion) {
    'start' { Arrancar-Aplicacion }
    'stop' {
        Esperar-Docker
        docker compose stop
    }
    'reset' {
        if (-not (Pedir-Confirmacion 'Se borrarán la base de datos y los pedidos de prueba, y se empezará de cero. ¿Seguro?')) { exit 0 }
        Esperar-Docker
        docker compose down --volumes
        Arrancar-Aplicacion
    }
}
