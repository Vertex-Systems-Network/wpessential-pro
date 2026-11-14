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

# Tries a real Pro write: the Site Kit "update" ability sets a colour field.
# Prints "written:<stored value>" or "denied:<reason>".
pro_write() {
	export WPE_VALUE="$1"
	wp eval --user=admin '
		$value = (string) getenv("WPE_VALUE");
		$abilities = WPEssential\Bootstrap\Plugin::kernel()->services()->get("platform.abilities");
		$key = null;
		foreach (WPEssential\Modules\SiteKit\SiteKitSchema::fields() as $field => $definition) {
			if (($definition["type"] ?? "") === "color") { $key = $field; break; }
		}
		$context = new WPEssential\Platform\Auth\ExecutionContext(new WPEssential\Platform\Auth\Principal(get_current_user_id()), get_current_blog_id());
		$input = ["values" => [$key => $value]];
		$decision = $abilities->authorize("wpessential/site-kit/update", $context, $input);
		if (!$decision->allowed) { echo "denied:" . $decision->reason; return; }
		$abilities->execute("wpessential/site-kit/update", $input, $context);
		$stored = get_option("wpessential_site_kit", []);
		echo "written:" . ($stored[$key] ?? "none");
	'
}

# Pro data as stored: the Site Kit option.
pro_data() { wp eval '$v = get_option("wpessential_site_kit", []); echo is_array($v) ? implode(",", $v) : "none";'; }

# Fires admin_init with a Pro admin form action. Prints "blocked" or "allowed".
pro_admin_post() {
	wp eval '
		$_REQUEST["action"] = "wpessential_site_kit_save";
		add_filter("wp_die_handler", static fn () => static function (): void { echo "blocked"; exit(0); });
		do_action("admin_init");
		echo "allowed";
	'
}

# "<table>:<rows>" for every WPEssential table.
row_counts() {
	wp eval '
		global $wpdb;
		$out = [];
		foreach ($wpdb->get_col("SHOW TABLES LIKE \"%wpe%\"") as $table) {
			$out[] = $table . ":" . (int) $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table) . "`");
		}
		sort($out);
		echo implode(" ", $out);
	'
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
[[ "$(pro_write '#123456')" == "written:#123456" ]] || fail "Pro write refused with an active licence: $(pro_write '#123456')"
pass "Active licence: Pro write saved"
[[ "$(pro_admin_post)" == "allowed" ]] || fail "Pro admin form blocked with an active licence"
pass "Active licence: Pro admin form allowed"
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"active\",\"pro\":true,\"plan\":\"Pro Trial\",\"expires\":\"${future}\",\"checked\":${now},\"verified\":${now}}"
expect "Trial licence" "compatible\|trial_active\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"active\",\"pro\":true,\"plan\":\"Pro\",\"expires\":\"${past}\",\"checked\":${now},\"verified\":${now}}"
expect "Grace period" "compatible\|grace\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
set_license "{\"key\":\"WPE-LIFECYCLE-0001\",\"status\":\"invalid\",\"error\":\"wpe_license_expired\",\"pro\":true,\"plan\":\"Pro\",\"expires\":\"${past}\",\"checked\":${now}}"
expect "Expired licence is read-only" "compatible\|expired\|true\|false\|[1-9][0-9]*\|[1-9][0-9]*"
got="$(pro_write '#654321')"
[[ "${got}" == "denied:premium_read_only" ]] || fail "Expired licence: Pro write not blocked (${got})"
pass "Expired licence: Pro write blocked (${got})"
[[ "$(pro_data)" == *"#123456"* ]] || fail "Expired licence: Pro data changed or unreadable ($(pro_data))"
pass "Expired licence: Pro data kept and readable"
[[ "$(pro_admin_post)" == "blocked" ]] || fail "Expired licence: Pro admin form not blocked"
pass "Expired licence: Pro admin form blocked"
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
rows_before="$(row_counts)"
wp plugin activate wpessential >/dev/null 2>&1 || true
wp plugin deactivate wpessential-pro
expect "Free only" "none\|none\|none\|none\|0\|[1-9][0-9]*"
wp option get wpessential_license --format=json | grep -q WPE-LIFECYCLE-0001 || fail "Licence lost after Pro deactivation"
pass "Licence kept after Pro deactivation"

# 6. Deleting Pro (files) never deletes data; reinstalling restores it.
wp plugin delete wpessential-pro
rows_after="$(row_counts)"
[[ -n "${rows_before}" && "${rows_before}" == "${rows_after}" ]] || fail "Data changed after deleting Pro: '${rows_before}' -> '${rows_after}'"
pass "Table rows kept after deleting Pro (${rows_after})"
[[ "$(pro_data)" == *"#123456"* ]] || fail "Pro data lost after deleting Pro ($(pro_data))"
pass "Pro data kept after deleting Pro"
wp plugin install "${PRO_ZIP}" --activate >/dev/null
expect "Pro reinstalled" "compatible\|pro_active\|true\|true\|[1-9][0-9]*\|[1-9][0-9]*"
[[ "$(pro_write '#abcdef')" == "written:#abcdef" ]] || fail "Pro write refused after reinstall"
pass "Pro reinstalled: writes work again"
check_log "Full lifecycle"

echo "Free + Pro lifecycle: all scenarios passed."
