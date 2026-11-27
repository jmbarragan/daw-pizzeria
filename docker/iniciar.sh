#!/bin/sh
# Pone en marcha La Focaccia di Trevi con Docker. Lo llaman «make start»,
# «make stop» y «make reset»; en Windows sin make está iniciar.ps1, que hace lo mismo.
#
#   start  instala Docker si falta (pregunta antes), crea el .env con contraseñas
#          aleatorias, construye la aplicación, la arranca y abre el navegador
#   stop   para los contenedores sin borrar nada
#   reset  borra la base de datos y los ficheros de la aplicación, y vuelve a empezar
set -e

cd "$(dirname "$0")/.."

DIRECCION="http://localhost:${APP_PUERTO:-8000}"
DOCKER=docker

# Como root (un servidor recién instalado) no hace falta sudo, y muchas veces ni está instalado
if [ "$(id -u)" = 0 ]; then SUDO=""; else SUDO=sudo; fi

# Título de cada paso, para que se vea por dónde va
aviso() { printf '\n==> %s\n' "$1"; }

# Explica qué ha fallado y corta el script
fallo() {
    printf '\nERROR: %s\n' "$1" >&2
    exit 1
}

# Pide un sí o un no; solo «s» o «sí» cuentan como sí
preguntar() {
    printf '%s [s/N] ' "$1"
    read -r respuesta
    case "$respuesta" in s|S|si|sí|Si|Sí) return 0 ;; *) return 1 ;; esac
}

# linux, macos o windows (Git Bash): cada uno instala y arranca Docker a su manera
sistema() {
    case "$(uname -s)" in
        Linux) echo linux ;;
        Darwin) echo macos ;;
        MINGW*|MSYS*|CYGWIN*) echo windows ;;
        *) echo otro ;;
    esac
}

# Con el instalador de cada sistema y siempre después de preguntar
instalar_docker() {
    case "$(sistema)" in
        linux)
            preguntar "Docker no está instalado. ¿Lo instalo ahora con el script oficial de Docker?" \
                || fallo "Sin Docker no se puede seguir. Instálalo desde https://docs.docker.com/engine/install/"
            command -v curl >/dev/null 2>&1 || fallo "Falta curl para descargar Docker: instálalo (en Debian o Ubuntu, apt install curl) y vuelve a lanzar make start."
            curl -fsSL https://get.docker.com | $SUDO sh
            [ -n "$SUDO" ] && $SUDO usermod -aG docker "$USER" || true
            ;;
        macos)
            command -v brew >/dev/null 2>&1 \
                || fallo "Instala Docker Desktop desde https://www.docker.com/products/docker-desktop/ y vuelve a lanzar make start"
            preguntar "Docker no está instalado. ¿Instalo Docker Desktop con Homebrew?" \
                || fallo "Sin Docker no se puede seguir."
            brew install --cask docker
            ;;
        windows)
            preguntar "Docker no está instalado. ¿Instalo Docker Desktop con winget?" \
                || fallo "Sin Docker no se puede seguir."
            winget install -e --id Docker.DockerDesktop --accept-package-agreements --accept-source-agreements
            echo "Docker Desktop puede pedir permisos de administrador y reiniciar el equipo para activar WSL2."
            echo "Cuando termine, abre Docker Desktop, acepta sus condiciones y vuelve a lanzar make start."
            exit 0
            ;;
        *) fallo "No sé instalar Docker en este sistema. Instálalo desde https://docs.docker.com/get-docker/" ;;
    esac
}

# Docker instalado, pero el servicio parado: se arranca y se espera hasta dos minutos
esperar_docker() {
    $DOCKER info >/dev/null 2>&1 && return 0

    if [ "$(sistema)" = linux ] && [ -n "$SUDO" ] && sudo docker info >/dev/null 2>&1; then
        DOCKER="sudo docker"
        return 0
    fi

    aviso "Arrancando Docker"
    case "$(sistema)" in
        linux) $SUDO systemctl start docker || true ;;
        macos) open -a Docker || true ;;
        windows) "/c/Program Files/Docker/Docker/Docker Desktop.exe" >/dev/null 2>&1 & ;;
    esac

    for _ in $(seq 1 60); do
        if $DOCKER info >/dev/null 2>&1; then return 0; fi
        if [ "$(sistema)" = linux ] && [ -n "$SUDO" ] && sudo docker info >/dev/null 2>&1; then
            DOCKER="sudo docker"
            return 0
        fi
        sleep 2
    done
    fallo "Docker no responde. Ábrelo a mano, espera a que diga que está en marcha y vuelve a lanzar make start."
}

# Contraseña aleatoria de 32 letras y números: sin símbolos, para no romper el .env ni las órdenes de MySQL
contrasena() {
    LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32
}

# La primera vez, el .env sale de .env.example con contraseñas nuevas y la clave de la aplicación
crear_env() {
    [ -f .env ] && return 0
    aviso "Creando el fichero .env con contraseñas nuevas"
    clave="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
    sed -e "s|^APP_KEY=.*|APP_KEY=$clave|" \
        -e "s|^DB_PORT=.*|DB_PORT=3307|" \
        -e "s|^DB_ADMIN_CONTRASENA=.*|DB_ADMIN_CONTRASENA=$(contrasena)|" \
        -e "s|^DB_WEB_CONTRASENA=.*|DB_WEB_CONTRASENA=$(contrasena)|" \
        -e "s|^DB_GESTION_CONTRASENA=.*|DB_GESTION_CONTRASENA=$(contrasena)|" \
        -e "s|^DB_INFORMES_CONTRASENA=.*|DB_INFORMES_CONTRASENA=$(contrasena)|" \
        -e "s|^DB_ROOT_CONTRASENA=.*|DB_ROOT_CONTRASENA=$(contrasena)|" \
        .env.example >.env
}

# Abre la web, salvo con SIN_NAVEGADOR (para probar el script sin ventanas)
abrir_navegador() {
    [ -n "$SIN_NAVEGADOR" ] && return 0
    case "$(sistema)" in
        linux) xdg-open "$DIRECCION" >/dev/null 2>&1 || true ;;
        macos) open "$DIRECCION" || true ;;
        windows) cmd.exe /c start "" "$DIRECCION" || true ;;
    esac
}

# make start: Docker, .env, construcción y arranque, y la dirección para entrar
arrancar() {
    command -v docker >/dev/null 2>&1 || instalar_docker
    esperar_docker
    crear_env

    aviso "Construyendo y arrancando la aplicación (la primera vez tarda unos minutos)"
    $DOCKER compose up -d --build --wait \
        || fallo "La aplicación no ha arrancado. Mira qué ha pasado con: docker compose logs web"

    aviso "La Focaccia di Trevi está en marcha"
    echo "  Web:                $DIRECCION"
    echo "  Gestión:            $DIRECCION/gestion/acceso"
    echo "  Correos (Mailpit):  http://localhost:${MAILPIT_PUERTO_PANEL:-8025}"
    echo "  Cuentas de prueba:  en la guía de instalación"
    echo "  Para pararla:       make stop"
    abrir_navegador
}

case "${1:-start}" in
    start) arrancar ;;
    stop)
        esperar_docker
        $DOCKER compose stop
        ;;
    reset)
        preguntar "Se borrarán la base de datos y los pedidos de prueba, y se empezará de cero. ¿Seguro?" || exit 0
        esperar_docker
        $DOCKER compose down --volumes
        arrancar
        ;;
    *) fallo "Uso: iniciar.sh start|stop|reset" ;;
esac
