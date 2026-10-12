#!/bin/bash
# Move the live site from one domain to another on the same Hostinger account.
# Usage: bash scripts/move-domain.sh clinic-ksa.online panoramascann.com
#
# Before running: the new domain is bought and added as a website in hPanel
# (so domains/<new>/public_html exists) and its SSL is issued.
# The old app folder stays as a backup; the old domain 301-redirects every path
# to the new one, so WhatsApp links already sent keep working.
set -euo pipefail

OLD="${1:?old domain}"
NEW="${2:?new domain}"
HOST="u300114289@82.29.182.140"
SSH=(ssh -i "$HOME/.ssh/id_ed25519_hostinger" -p 65002 -o IdentitiesOnly=yes "$HOST")

"${SSH[@]}" "set -euo pipefail
  PHP=/opt/alt/php84/usr/bin/php
  OLD_DIR=\$HOME/domains/$OLD; NEW_DIR=\$HOME/domains/$NEW
  [ -d \"\$OLD_DIR/app\" ] || { echo 'Old app not found'; exit 1; }
  [ -d \"\$NEW_DIR/public_html\" ] || { echo 'Add $NEW as a website in hPanel first'; exit 1; }
  [ -e \"\$NEW_DIR/app\" ] && { echo 'New domain already has an app folder'; exit 1; }

  cd \"\$OLD_DIR/app\" && \$PHP artisan down
  cp database/database.sqlite database/database.before-move-\$(date +%Y%m%d%H%M).sqlite

  # Copy everything (code, .env, database, uploaded files) to the new domain.
  cp -a \"\$OLD_DIR/app\" \"\$NEW_DIR/app\"
  mv \"\$NEW_DIR/public_html\" \"\$NEW_DIR/public_html_default_backup\"
  ln -s app/public \"\$NEW_DIR/public_html\"
  cd \"\$NEW_DIR/app\"
  rm -f public/storage && ln -s \"\$NEW_DIR/app/storage/app/public\" public/storage
  if grep -q '^APP_URL=' .env; then sed -i 's#^APP_URL=.*#APP_URL=https://$NEW#' .env; else echo 'APP_URL=https://$NEW' >> .env; fi
  \$PHP artisan optimize:clear >/dev/null
  \$PHP artisan optimize
  \$PHP artisan up

  # Old domain: permanent redirect for every path, old app kept untouched as a backup.
  rm \"\$OLD_DIR/public_html\"
  mkdir \"\$OLD_DIR/public_html\"
  printf '%s\n' 'RewriteEngine On' 'RewriteRule ^(.*)\$ https://$NEW/\$1 [R=301,L]' > \"\$OLD_DIR/public_html/.htaccess\"
  echo 'Moved.'"

curl -s -o /dev/null -w "https://$NEW/login → %{http_code}\n" "https://$NEW/login"
curl -s -o /dev/null -w "https://$OLD/login → %{http_code} %{redirect_url}\n" "https://$OLD/login"
