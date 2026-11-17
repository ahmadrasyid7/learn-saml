#!/bin/sh
# Generates a self-signed key pair for the IdP and the SP (learning only).
set -e
cd "$(dirname "$0")/.."

for side in idp sp; do
  if [ -f "certs/$side/$side.key" ]; then
    echo "certs/$side already exists, skipping"
    continue
  fi
  mkdir -p "certs/$side"
  docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/certs/$side:/out" php:8.4-cli \
    openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 3650 \
      -subj "/CN=$side.localhost" \
      -keyout "/out/$side.key" -out "/out/$side.crt"
  echo "created certs/$side/$side.key and $side.crt"
done
