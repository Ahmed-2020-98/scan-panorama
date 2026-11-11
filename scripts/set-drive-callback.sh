#!/bin/bash
# Move the Google Drive sign-in return address from the old clinic-ksa.online to panoramascann.com,
# so the old domain can be removed later. FIRST add the new address in Google Cloud Console
# (APIs & Services → Credentials → the OAuth client → Authorized redirect URIs):
#   https://panoramascann.com/drive/callback
# Then run: bash scripts/set-drive-callback.sh
# It also reports which email address the site sends from, and any setting still on the old domain
# (setting names only, values are never printed).
set -euo pipefail

ssh -i "$HOME/.ssh/id_ed25519_hostinger" -p 65002 -o IdentitiesOnly=yes u300114289@82.29.182.140 'bash -s' <<'REMOTE'
set -e
cd ~/domains/panoramascann.com/app
PHP=/opt/alt/php84/usr/bin/php
NEW="https://panoramascann.com/drive/callback"
cp -p .env ".env.before-drive-$(date +%Y%m%d%H%M)"
if grep -q '^GOOGLE_DRIVE_REDIRECT_URI=' .env; then
  sed -i "s#^GOOGLE_DRIVE_REDIRECT_URI=.*#GOOGLE_DRIVE_REDIRECT_URI=$NEW#" .env
else
  printf '\nGOOGLE_DRIVE_REDIRECT_URI=%s\n' "$NEW" >> .env
fi
$PHP artisan config:clear >/dev/null
$PHP artisan optimize >/dev/null
echo "عنوان رجوع Drive بقى: $($PHP artisan tinker --execute='echo config("drive.redirect_uri");' 2>/dev/null | tail -1)"
echo "الإيميل بيتبعت من: $(grep '^MAIL_FROM_ADDRESS=' .env | cut -d= -f2-) عن طريق $(grep '^MAIL_HOST=' .env | cut -d= -f2-)"
LEFT=$(grep -i 'clinic-ksa' .env | cut -d= -f1 | tr '\n' ' ')
if [ -n "$LEFT" ]; then echo "إعدادات لسه على الدومين القديم: $LEFT"; else echo "مفيش أي إعداد لسه على clinic-ksa.online."; fi
echo "صفحة الموقع: $(curl -s -o /dev/null -w '%{http_code}' https://panoramascann.com/login)"
REMOTE
echo "جرّب دلوقتي: ادخل بحساب المدير → Drive → اربط الحساب. لو جوجل قال redirect_uri_mismatch يبقى العنوان لسه مش متضاف في Google Cloud."
