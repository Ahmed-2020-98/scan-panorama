#!/bin/bash
# Turn on real email (password reset links) on the live site, sending from no-reply@panoramascann.com.
# First create that mailbox in hPanel → Emails. Then run: bash scripts/set-mail.sh
# The mailbox password is typed hidden, sent to the server over SSH stdin, and never printed or saved here.
set -euo pipefail
FROM="no-reply@panoramascann.com"

# Without a terminal (e.g. run from Claude Code's `!`), ask in macOS dialogs; the password one is hidden.
ask() { osascript -e "text returned of (display dialog \"$1\" default answer \"\" $2 with title \"Scan Panorama\")" 2>/dev/null; }
if [ -t 0 ]; then
  read -rsp "باسورد الإيميل $FROM: " PASS; echo
  read -rp "ابعت رسالة التجربة على أنهي إيميل؟ " TO
else
  PASS=$(ask "باسورد الإيميل $FROM" "with hidden answer") || { echo "اتلغى."; exit 1; }
  TO=$(ask "ابعت رسالة التجربة على أنهي إيميل؟" "") || { echo "اتلغى."; exit 1; }
fi
[ -n "$PASS" ] || { echo "مفيش باسورد."; exit 1; }
[ -n "$TO" ] || { echo "مفيش إيميل للتجربة."; exit 1; }

{ printf '%s\n%s\n' "$PASS" "$TO"; cat <<'REMOTE'
set -e
cd ~/domains/panoramascann.com/app
PHP=/opt/alt/php84/usr/bin/php
cp -p .env ".env.before-mail-$(date +%Y%m%d%H%M)"
export CENTER=$($PHP artisan tinker --execute='echo App\Models\Setting::get("center_name");' 2>/dev/null | tail -1)
$PHP <<'PHPCODE'
<?php
// Double-quoted .env value with \\, \" and \$ escaped: any password survives, ${...} is not expanded.
$quote = fn (string $v): string => '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $v).'"';
$from = 'no-reply@panoramascann.com';
$set = [
    'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtps', 'MAIL_HOST' => 'smtp.hostinger.com', 'MAIL_PORT' => '465',
    'MAIL_USERNAME' => $from, 'MAIL_PASSWORD' => $quote((string) getenv('PASS')),
    'MAIL_FROM_ADDRESS' => $from, 'MAIL_FROM_NAME' => $quote((string) (getenv('CENTER') ?: 'Panorama Scan')),
];
$env = file_get_contents('.env');
foreach ($set as $key => $value) {
    $line = $key.'='.$value;
    $env = preg_match("/^{$key}=.*$/m", $env)
        ? preg_replace_callback("/^{$key}=.*$/m", fn () => $line, $env)
        : rtrim($env)."\n".$line."\n";
}
file_put_contents('.env', $env);
echo "اتحفظت إعدادات الإيميل.\n";
PHPCODE
unset PASS
$PHP artisan config:clear >/dev/null
$PHP artisan optimize >/dev/null
$PHP artisan mail:test "$TO"
REMOTE
} | ssh -i "$HOME/.ssh/id_ed25519_hostinger" -p 65002 -o IdentitiesOnly=yes u300114289@82.29.182.140 'IFS= read -r PASS; IFS= read -r TO; export PASS TO; bash -s'
unset PASS
echo "لو رسالة التجربة وصلت (بص في السبام كمان)، «نسيت كلمة المرور» بقى شغال."
