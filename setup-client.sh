#!/usr/bin/env sh
set -eu

MODE="${1:-${MPGRAM_CONNECTION:-public}}"
ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
DOCKER_DIR="$ROOT_DIR/docker"

case "$MODE" in
  public|mytelegram|dual) ;;
  *)
    echo "Usage: sh setup-client.sh [public|mytelegram|dual]"
    exit 2
    ;;
esac

ask() {
  name="$1"
  prompt="$2"
  default="$3"
  eval "current=\${$name:-}"
  if [ -n "$current" ]; then
    printf '%s' "$current"
    return
  fi
  if [ -t 0 ]; then
    if [ -n "$default" ]; then
      printf '%s [%s]: ' "$prompt" "$default" >&2
    else
      printf '%s: ' "$prompt" >&2
    fi
    IFS= read -r answer || answer=''
    if [ -n "$answer" ]; then
      printf '%s' "$answer"
      return
    fi
  fi
  printf '%s' "$default"
}

write_api_values() {
  api_id_value="$(ask API_ID 'Telegram API id from https://my.telegram.org/apps' '')"
  api_hash_value="$(ask API_HASH 'Telegram API hash from https://my.telegram.org/apps' '')"
  if [ -z "$api_id_value" ] || [ -z "$api_hash_value" ]; then
    echo "Missing API_ID or API_HASH. Create a Telegram app at https://my.telegram.org/apps first."
    exit 1
  fi
  cat > "$ROOT_DIR/api_values.php" <<PHP
<?php
define('api_id', $api_id_value);
define('api_hash', '$api_hash_value');
PHP
}

write_env_public() {
  uid_value="$(id -u 2>/dev/null || printf '1000')"
  sed "s/^UID=.*/UID=$uid_value/" "$DOCKER_DIR/.env.public.example" > "$DOCKER_DIR/.env"
}

write_env_mytelegram() {
  uid_value="$(id -u 2>/dev/null || printf '1000')"
  host_value="$(ask MYTELEGRAM_HOST 'MyTelegram/MTG host reachable from Docker' '10.0.0.10')"
  port_value="$(ask MYTELEGRAM_PORT 'MyTelegram/MTG WebSocket port' '30444')"
  use_wss_value="$(ask MYTELEGRAM_USE_WSS 'Use WSS for MyTelegram/MTG' 'false')"
  sed \
    -e "s/^UID=.*/UID=$uid_value/" \
    -e "s/^PRIVATE_SERVER_HOST=.*/PRIVATE_SERVER_HOST=$host_value/" \
    -e "s/^PRIVATE_SERVER_PORT=.*/PRIVATE_SERVER_PORT=$port_value/" \
    -e "s/^PRIVATE_SERVER_USE_WSS=.*/PRIVATE_SERVER_USE_WSS=$use_wss_value/" \
    "$DOCKER_DIR/.env.mytelegram.example" > "$DOCKER_DIR/.env"
}

write_api_values

if [ "$MODE" = "public" ]; then
  cp "$ROOT_DIR/config.public.php.example" "$ROOT_DIR/config.php"
  write_env_public
  echo "Configured MPGram S Web for public Telegram MTProto mode."
  echo "Run: cd docker && docker compose up --build -d"
elif [ "$MODE" = "mytelegram" ]; then
  cp "$ROOT_DIR/config.mytelegram.php.example" "$ROOT_DIR/config.php"
  write_env_mytelegram
  echo "Configured MPGram S Web for MyTelegram/MTG-server mode."
  echo "Run: cd docker && docker compose up --build -d"
else
  cp "$ROOT_DIR/config.php.example" "$ROOT_DIR/config.php"
  uid_value="$(id -u 2>/dev/null || printf '1000')"
  cat > "$DOCKER_DIR/.env.dual" <<ENV
UID=$uid_value
INTERFACE=0.0.0.0
PUBLIC_PORT_HTTP=8081
MYTELEGRAM_PORT_HTTP=8082
MYTELEGRAM_HOST=${MYTELEGRAM_HOST:-10.0.0.10}
MYTELEGRAM_PORT=${MYTELEGRAM_PORT:-30444}
MYTELEGRAM_USE_WSS=${MYTELEGRAM_USE_WSS:-false}
ENV
  echo "Configured MPGram S Web for dual public/MyTelegram Docker mode."
  echo "Run: cd docker && docker compose --env-file .env.dual -f docker-compose.dual.yml up --build -d"
fi
