#!/bin/sh
set -eu

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root on the new pilot VPS." >&2
  exit 1
fi

if [ "$#" -ne 1 ] || [ ! -f "$1" ]; then
  echo "Usage: $0 /path/to/dolved-deploy.pub" >&2
  exit 1
fi

. /etc/os-release
if [ "${ID:-}" != "debian" ] || [ "${VERSION_ID:-}" != "13" ]; then
  echo "Expected Debian 13; found ${PRETTY_NAME:-unknown}." >&2
  exit 1
fi

deploy_key=$(tr -d '\r\n' < "$1")
case "$deploy_key" in
  ssh-ed25519\ *) ;;
  *) echo "Deployment key must be one complete ssh-ed25519 public key." >&2; exit 1 ;;
esac

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl fail2ban git gnupg jq openssh-server openssl rsync ufw unattended-upgrades
install -m 0755 -d /etc/apt/keyrings
if [ ! -f /etc/apt/keyrings/docker.asc ]; then
  curl --fail --show-error --silent --location https://download.docker.com/linux/debian/gpg \
    --output /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
fi

architecture=$(dpkg --print-architecture)
printf '%s\n' \
  "deb [arch=${architecture} signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian ${VERSION_CODENAME} stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

if ! id dolved >/dev/null 2>&1; then
  useradd --create-home --shell /bin/bash dolved
fi
usermod -aG docker dolved
install -d -m 0700 -o dolved -g dolved /home/dolved/.ssh
printf '%s\n' "$deploy_key" > /home/dolved/.ssh/authorized_keys
chown dolved:dolved /home/dolved/.ssh/authorized_keys
chmod 0600 /home/dolved/.ssh/authorized_keys

install -d -m 0750 -o dolved -g dolved /srv/dolved-pilot /srv/dify

if ! swapon --show=NAME --noheadings | grep -q .; then
  if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 0600 /swapfile
    mkswap /swapfile
  fi
  swapon /swapfile
fi
if ! grep -q '^/swapfile ' /etc/fstab; then
  printf '%s\n' '/swapfile none swap sw 0 0' >> /etc/fstab
fi

ufw default deny incoming
ufw default allow outgoing
ssh_port=$(sshd -T | awk '$1 == "port" { print $2; exit }')
case "$ssh_port" in
  ''|*[!0-9]*) echo "Could not determine the active SSH port." >&2; exit 1 ;;
esac
ufw limit "${ssh_port}/tcp"
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

systemctl enable --now docker fail2ban unattended-upgrades
docker network inspect dolved-dify-backplane >/dev/null 2>&1 || \
  docker network create --internal dolved-dify-backplane >/dev/null

echo "Bootstrap complete. Verify a second SSH session as 'dolved' before hardening SSH."
if [ -f /var/run/reboot-required ]; then
  echo "A reboot is required after the verified SSH handoff."
fi
