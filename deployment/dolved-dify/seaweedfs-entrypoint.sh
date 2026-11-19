#!/bin/sh
set -eu

case "${AWS_ACCESS_KEY_ID:-}" in
  ''|*[!A-Za-z0-9_-]*) echo "AWS_ACCESS_KEY_ID must use URL-safe characters." >&2; exit 1 ;;
esac
case "${AWS_SECRET_ACCESS_KEY:-}" in
  ''|*[!A-Za-z0-9_+=./-]*) echo "AWS_SECRET_ACCESS_KEY contains unsupported characters." >&2; exit 1 ;;
esac

umask 077
printf '{"identities":[{"name":"dolved","credentials":[{"accessKey":"%s","secretKey":"%s"}],"actions":["Admin","Read","Write","List","Tagging"]}]}' \
  "$AWS_ACCESS_KEY_ID" "$AWS_SECRET_ACCESS_KEY" > /tmp/s3.json

exec weed mini \
  -dir=/data \
  -ip.bind=0.0.0.0 \
  -s3 \
  -s3.port=8333 \
  -s3.config=/tmp/s3.json
