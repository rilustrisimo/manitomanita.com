#!/bin/bash
# Smoke test: run before every push (local) and after every deploy (live).
#   tools/smoke.sh local   PHP lint, JS syntax, Theme.js ASCII + CRLF
#   tools/smoke.sh live    key pages load (200, no PHP error), endpoints answer with their safe errors
#   tools/smoke.sh         both
# Exit code 0 = all passed. Read-only: it never logs in or changes anything on the site.

cd "$(dirname "$0")/.." || exit 1
SITE="${SMOKE_SITE:-https://manitomanita.com}"
MODE="${1:-all}"
FAIL=0
ok()   { printf '  \033[32mok\033[0m   %s\n' "$1"; }
bad()  { printf '  \033[31mFAIL\033[0m %s\n' "$1"; FAIL=1; }

if [ "$MODE" = "local" ] || [ "$MODE" = "all" ]; then
  echo "Local checks"
  n=0
  while IFS= read -r f; do
    out=$(php -l "$f" 2>&1) || bad "php -l $f: $(echo "$out" | head -1)"
    n=$((n+1))
  done < <(find . -name '*.php' -not -path './lazada/*' -not -path './node_modules/*' -not -path './vendor/*')
  ok "PHP lint ($n files)"
  for f in assets/js/Theme.js assets/js/mm-*.js; do
    [ -f "$f" ] || continue
    node --check "$f" 2>/dev/null || bad "JS syntax: $f"
  done
  ok "JS syntax"
  if LC_ALL=C grep -q $'[\x80-\xff]' assets/js/Theme.js; then bad "Theme.js has non-ASCII characters"; else ok "Theme.js is ASCII"; fi
  if file assets/js/Theme.js | grep -q CRLF; then ok "Theme.js has CRLF line endings"; else bad "Theme.js lost its CRLF line endings"; fi
  for f in includes/acf-json/*.json; do python3 -c "import json,sys;json.load(open(sys.argv[1]))" "$f" 2>/dev/null || bad "Invalid JSON: $f"; done
  ok "ACF JSON valid"
fi

if [ "$MODE" = "live" ] || [ "$MODE" = "all" ]; then
  echo "Live checks ($SITE)"
  N=$RANDOM
  TMP=$(mktemp)
  for p in "" my-wishlist/ advertise/ advertiser/ ad-guidelines/ online-bunutan/ monito-monita-generator/ exchange-gift-ideas-500-pesos/ monito-monita-themes/ wedding-gift-registry-philippines/ debut-wishlist/ baptism-gift-registry/ privacy-policy/ terms/; do
    code=$(curl -s -o "$TMP" -w '%{http_code}' --max-time 30 "$SITE/$p?smoke=$N")
    if [ "$code" != "200" ]; then bad "/$p returned $code"
    elif grep -qiE 'critical error|Fatal error|Parse error|Warning:</b>' "$TMP"; then bad "/$p shows a PHP error"
    else ok "/$p"; fi
  done
  code=$(curl -s -o "$TMP" -w '%{http_code}' "$SITE/wishlist/no-such-list-$N/"); [ "$code" = "404" ] && ok "missing wishlist gives 404" || bad "missing wishlist gave $code"
  code=$(curl -s -o "$TMP" -w '%{http_code}' "$SITE/sitemap_index.xml"); [ "$code" = "200" ] && ok "sitemap" || bad "sitemap gave $code"
  # Endpoints must refuse bad input politely (JSON success:false), never crash.
  r=$(curl -s -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=mm_wl_reserve&id=1&key=abc")
  echo "$r" | grep -q '"success":false' && ok "wishlist reserve refuses a bad item" || bad "wishlist reserve: $r"
  r=$(curl -s -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=mm_wl_remind&id=1&email=x")
  echo "$r" | grep -q '"success":false' && ok "registry reminder refuses bad input" || bad "registry reminder: $r"
  r=$(curl -s -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=user_leave_group&uid=1")
  echo "$r" | grep -q '"success":false' && ok "leave group needs the member password" || bad "leave group: $r"
  r=$(curl -s -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=user_card_generate&groupid=171650")
  echo "$r" | grep -q '"success":false' && ok "member cards need the group page key" || bad "member cards without key: $(echo "$r" | head -c 80)"
  r=$(curl -s -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=user_card_generate&groupid=171650&key=0123456789abcdef0123456789abcdef")
  echo "$r" | grep -q '"success":false' && ok "member cards refuse a wrong key" || bad "member cards with a wrong key: $(echo "$r" | head -c 80)"
  r=$(curl -s -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=mm_comment_remove&cid=1&gid=1&nonce=x")
  echo "$r" | grep -q '"success":false' && ok "comment removal needs a PRO moderator" || bad "comment removal: $r"
  code=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$SITE/wp-admin/admin-ajax.php" -d "action=mm_pay_step&nonce=x&step=sheet"); [ "$code" = "403" ] && ok "PRO funnel log refuses a forged step" || bad "PRO funnel log gave $code"
  code=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$SITE/wp-json/mm/v1/paypal-webhook" -d '{}'); [ "$code" = "400" ] && ok "PayPal webhook rejects an empty event" || bad "PayPal webhook gave $code"
  code=$(curl -s -o "$TMP" -w '%{http_code}' "$SITE/wp-json/mm/v1/ad-slot?s=guide&t=none"); [ "$code" = "200" ] && ok "ad slot endpoint" || bad "ad slot gave $code"
  code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/?mm_sp=999999999&s=guide"); case "$code" in 30*) ok "sponsor click redirect is safe";; *) bad "sponsor click gave $code";; esac
  code=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$SITE/wp-json/mm/v1/ses-events" -d '{"Type":"Notification","Message":"{}","SigningCertURL":"https://evil.example/x.pem","Signature":"x"}'); [ "$code" = "403" ] && ok "SES feedback refuses a forged message" || bad "SES feedback gave $code"
  code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/?mm_promo_unsub=eEB4LmNvbQ&s=bad"); [ "$code" = "400" ] && ok "promo unsubscribe refuses a bad link" || bad "promo unsubscribe gave $code"
  ctype=$(curl -s -o /dev/null -w '%{content_type}' "$SITE/wp-json/mm/v1/em-open?q=1&s=bad"); case "$ctype" in image/gif*) ok "email open pixel";; *) bad "open pixel gave $ctype";; esac
  code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/?mm_em=1&u=aHR0cHM6Ly9ldmlsLmV4YW1wbGU&s=bad"); case "$code" in 30*) loc=$(curl -s -o /dev/null -w '%{redirect_url}' "$SITE/?mm_em=1&u=aHR0cHM6Ly9ldmlsLmV4YW1wbGU&s=bad"); case "$loc" in "$SITE"*) ok "email click link is not an open redirect";; *) bad "email click redirected to $loc";; esac;; *) bad "email click gave $code";; esac
  rm -f "$TMP"
fi

echo
if [ $FAIL -eq 0 ]; then echo "All checks passed."; else echo "Some checks FAILED."; fi
exit $FAIL
