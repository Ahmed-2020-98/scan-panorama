#!/bin/bash
# Deploy to clinic-ksa.online (Hostinger shared hosting, PHP 8.4 via .htaccess handler).
# Keeps the server's .env, database and uploaded files untouched.
set -euo pipefail
cd "$(dirname "$0")/.."

HOST="u300114289@82.29.182.140"
PORT=65002
KEY="$HOME/.ssh/id_ed25519_hostinger"
REMOTE="domains/clinic-ksa.online/app"
SSH=(ssh -i "$KEY" -p "$PORT" -o IdentitiesOnly=yes "$HOST")

npm run build
COPYFILE_DISABLE=1 tar -czf /tmp/clinic-deploy.tar.gz --no-xattrs \
  --exclude=./node_modules --exclude=./vendor --exclude=./.env --exclude=./database/database.sqlite \
  --exclude=./storage --exclude=./public/storage --exclude=./public/hot --exclude=./.claude --exclude=./tests \
  --exclude='./bootstrap/cache/*.php' .

scp -i "$KEY" -P "$PORT" -o IdentitiesOnly=yes /tmp/clinic-deploy.tar.gz "$HOST:$REMOTE/../deploy.tar.gz"
rm /tmp/clinic-deploy.tar.gz

"${SSH[@]}" "set -e; cd $REMOTE; PHP=/opt/alt/php84/usr/bin/php
  \$PHP artisan down || true
  tar -xzf ../deploy.tar.gz 2>/dev/null; rm ../deploy.tar.gz
  grep -q 'x-httpd-php84' public/.htaccess || sed -i '1i AddHandler application/x-httpd-php84 .php\n' public/.htaccess
  \$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
  \$PHP artisan migrate --force
  \$PHP artisan optimize
  \$PHP artisan up"

echo "Deployed: https://clinic-ksa.online"
