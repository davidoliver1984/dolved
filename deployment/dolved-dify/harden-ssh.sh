#!/bin/sh
set -eu

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root only after key login as 'dolved' has been verified." >&2
  exit 1
fi

key_file=/home/dolved/.ssh/authorized_keys
if [ ! -s "$key_file" ] || ! grep -q '^ssh-ed25519 ' "$key_file"; then
  echo "Verified dolved Ed25519 access is required before SSH hardening." >&2
  exit 1
fi

install -d -m 0755 /etc/ssh/sshd_config.d
# OpenSSH uses the first value it reads. This file must sort before the
# provider's 50-cloud-init.conf, which may explicitly enable passwords.
install -m 0600 /dev/null /etc/ssh/sshd_config.d/40-dolved-pilot.conf
printf '%s\n' \
  'PasswordAuthentication no' \
  'KbdInteractiveAuthentication no' \
  'PermitRootLogin prohibit-password' \
  > /etc/ssh/sshd_config.d/40-dolved-pilot.conf

rm -f /etc/ssh/sshd_config.d/90-dolved-pilot.conf

sshd -t
systemctl reload ssh
echo "SSH password authentication disabled; key-based root recovery remains permitted."
