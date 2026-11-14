#!/usr/bin/env bash
# Free + Pro plugin lifecycle on a real WordPress install, using the packaged ZIPs.
#
# Usage: free-pro-lifecycle.sh <wordpress-dir> <free-zip> <pro-zip>
# Needs: wp (WP-CLI) on PATH, a reachable empty MySQL database (WPE_DB_* env).
set -euo pipefail

WP_DIR="$1"
FREE_ZIP="$2"
PRO_ZIP="$3"
DB_NAME="${WPE_DB_NAME:-wpessential_lifecycle}"
DB_USER="${WPE_DB_USER:-root}"
DB_PASS="${WPE_DB_PASSWORD:-root}"
DB_HOST="${WPE_DB_HOST:-127.0.0.1:3306}"
LOG="${WP_DIR}/wp-content/debug.log"

wp() { command wp --path="${WP_DIR}" --allow-root "$@"; }
fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "PASS: $*"; }

# Prints "<compatibility>|<entitlement>|<reads>|<writes>|<premium modules>|<free modules>".
probe() {
	wp eval '
		$c = defined("WPE_PRO_COMPATIBILITY_STATE") ? WPE_PRO_COMPATIBILITY_STATE : "none";
		$e = defined("WPE_PRO_ENTITLEMENT_STATE") ? WPE_PRO_ENTITLEMENT_STATE : "none";
		$r = defined("WPE_PRO_PREMIUM_READS_ALLOWED") ? var_export(WPE_PRO_PREMIUM_READS_ALLOWED, true) : "none";
		$w = defined("WPE_PRO_PREMIUM_MUTATIONS_ALLOWED") ? var_export(WPE_PRO_PREMIUM_MUTATIONS_ALLOWED, true) : "none";
		$premium = 0; $free = 0;
		$k = class_exists("WPEssential\\Bootstrap\\Plugin") ? WPEssential\Bootstrap\Plugin::kernel() : null;
		if ($k) {
			foreach ($k->modules()->all() as $m) {
				$m = is_object($m) && method_exists($m, "manifest") ? $m : ($m->module ?? null);
				if ($m === null) { continue; }
				$m->manifest()->edition === "free" ? $free++ : $premium++;
			}
		}
		echo "$c|$e|$r|$w|$premium|$free";
	'
}

expect() {
	local label="$1" want="$2" got
	got="$(probe)"
	[[ "${got}" =~ ^${want}$ ]] || fail "${label}: expected ${want}, got ${got}"
	pass "${label} (${got})"
}

set_license() { wp option update wpessential_license "$1" --format=json >/dev/null; }

check_log() {
	if [[ -f "${LOG}" ]] && grep -E "PHP (Fatal|Parse|Warning|Deprecated)|Uncaught" "${LOG}" | grep -i wpessential; then
		fail "$1: PHP errors from WPEssential in debug.log"
	fi
	pass "$1: no WPEssential PHP errors"
}

now="$(date +%s)"
future="$(date -u -d '+20 days' +%Y-%m-%d)"
past="$(date -u -d '-20 days' +%Y-%m-%d)"

# --- Fresh site --------------------------------------------------------------
wp config create --dbname="${DB_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASS}" --dbhost="${DB_HOST}" --skip-check --force \
	--extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
PHP
wp db reset --yes >/dev/null 2>&1 || wp db create
wp core install --url=lifecycle.test --title=Lifecycle --admin_user=admin --admin_password=admin --admin_email=admin@lifecycle.test --skip-email
wp plugin install "${FREE_ZIP}" >/dev/null
wp plugin install "${PRO_ZIP}" >/dev/null
rm -f "${LOG}"

# 1. Pro alone: WordPress refuses it (Requires Plugins: wpessential); the site stays fine.
if wp plugin activate wpessential-pro >/tmp/wpe-pro-alone.log 2>&1; then
	fail "Pro activated without Free"
fi
grep -q "requires" /tmp/wpe-pro-alone.log || fail "Unexpected activation error: $(cat /tmp/wpe-pro-alone.log)"
pass "WordPress refuses Pro without Free"
expect "Site without plugins" "none\|none\|none\|none\|0\|0"
check_log "Pro without Free"

# 2. Free, then Pro, no licence: premium visible, read-only.
wp plugin activate wpessential
wp plugin activate wpessential-pro
expect "Free+Pro, no licence" "compatible\|verification_unavailable\|true\|false\|[0-9]+\|[1-9][0-9]*"

# 3. Licence states written by Free's LicenseService.
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"active\",\"pro\":true,\"plan\":\"Pro\",\"expires\":\"${future}\",\"checked\":${now},\"verified\":${now}}"
expect "Active Pro licence" "compatible\|pro_active\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"active\",\"pro\":true,\"plan\":\"Pro Trial\",\"expires\":\"${future}\",\"checked\":${now},\"verified\":${now}}"
expect "Trial licence" "compatible\|trial_active\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"active\",\"pro\":true,\"plan\":\"Pro\",\"expires\":\"${past}\",\"checked\":${now},\"verified\":${now}}"
expect "Grace period" "compatible\|grace\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"invalid\",\"error\":\"wpe_license_expired\",\"pro\":true,\"plan\":\"Pro\",\"expires\":\"${past}\",\"checked\":${now}}"
expect "Expired licence is read-only" "compatible\|expired\|true\|false\|[1-9][0-9]*\|[1-9][0-9]*"
check_log "Licence states"

# 4. Deactivating Free under an active Pro must not break the site.
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"active\",\"pro\":true,\"plan\":\"Pro\",\"expires\":\"${future}\",\"checked\":${now},\"verified\":${now}}"
if wp plugin deactivate wpessential >/tmp/wpe-free-off.log 2>&1; then
	expect "Free deactivated, Pro active" "[a-z_]+\|none\|none\|none\|0\|0"
else
	pass "WordPress keeps Free active while Pro depends on it"
fi
wp eval 'echo get_bloginfo("name");' | grep -q Lifecycle || fail "Site broken after Free deactivation"
wp plugin activate wpessential >/dev/null 2>&1 || true

# 4b. Free files vanish while Pro stays in active_plugins (manual delete, failed update).
mv "${WP_DIR}/wp-content/plugins/wpessential" /tmp/wpessential-moved
expect "Free files missing, Pro loaded" "[a-z_]+\|none\|none\|none\|0\|0"
[[ "$(probe | cut -d'|' -f1)" != "compatible" ]] || fail "Pro reported compatible without Free files"
wp eval 'echo get_bloginfo("name");' | grep -q Lifecycle || fail "Site broken with Free files missing"
mv /tmp/wpessential-moved "${WP_DIR}/wp-content/plugins/wpessential"
check_log "Free missing under Pro"

# 5. Free back on, Pro off: Free keeps working; licence and data stay.
tables_before="$(wp db query "SHOW TABLES LIKE '%wpe%'" --skip-column-names | sort | tr '\n' ' ')"
wp plugin activate wpessential >/dev/null 2>&1 || true
wp plugin deactivate wpessential-pro
expect "Free only" "none\|none\|none\|none\|0\|[1-9][0-9]*"
wp option get wpessential_license --format=json | grep -q WPE-LIFECYCLE-0001 || fail "Licence lost after Pro deactivation"
pass "Licence kept after Pro deactivation"

# 6. Deleting Pro (files) never deletes data; reinstalling restores it.
wp plugin delete wpessential-pro
tables_after="$(wp db query "SHOW TABLES LIKE '%wpe%'" --skip-column-names | sort | tr '\n' ' ')"
[[ "${tables_before}" == "${tables_after}" ]] || fail "Tables changed after deleting Pro: '${tables_before}' -> '${tables_after}'"
pass "Data tables kept after deleting Pro (${tables_after:-none})"
wp plugin install "${PRO_ZIP}" --activate >/dev/null
expect "Pro reinstalled" "compatible\|pro_active\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
check_log "Full lifecycle"

echo "Free + Pro lifecycle: all scenarios passed."
