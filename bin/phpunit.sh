#!/bin/bash
HOST_DIR="/home/jontasr/repositorios/jsr-pix-withdrawal"
CONTAINER_DIR="/opt/www"

# Replace host paths with container paths in all arguments
ARGS=()
for arg in "$@"; do
    ARGS+=("${arg//$HOST_DIR/$CONTAINER_DIR}")
done

# Get host IP (Docker gateway) for Xdebug to connect back
HOST_IP=$(/usr/bin/docker exec jsr-pix-withdrawal ip route | grep default | awk '{print $3}')

# Xdebug PHP flags (loaded as zend_extension, off by default)
XDEBUG_FLAGS=(
    -d "zend_extension=xdebug.so"
    -d "xdebug.mode=${XDEBUG_MODE:-off}"
    -d "xdebug.client_host=${HOST_IP}"
    -d "xdebug.client_port=9003"
    -d "xdebug.start_with_request=yes"
    -d "xdebug.idekey=VSCODE"
)

/usr/bin/docker compose exec -T app php "${XDEBUG_FLAGS[@]}" vendor/bin/phpunit -c phpunit.unit.xml "${ARGS[@]}"
