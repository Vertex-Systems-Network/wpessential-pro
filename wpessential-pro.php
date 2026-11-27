<?php
/**
 * Plugin Name: WPEssential Pro
 * Plugin URI: https://wpessential.org
 * Description: Premium module add-on for the WPEssential WordPress application platform.
 * Version: 3.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Requires Plugins: wpessential
 * Author: WPEssential
 * Author URI: https://wpessential.org
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wpessential-pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPE_PRO_VERSION')) {
    define('WPE_PRO_VERSION', '3.0.0');
}
if (!defined('WPE_PRO_MIN_FREE_VERSION')) {
    define('WPE_PRO_MIN_FREE_VERSION', '3.0.0');
}
if (!defined('WPE_PRO_MAX_FREE_VERSION')) {
    define('WPE_PRO_MAX_FREE_VERSION', '3.99.99');
}
if (!defined('WPE_PRO_MIN_PLATFORM_API_VERSION')) {
    define('WPE_PRO_MIN_PLATFORM_API_VERSION', '0.1.0');
}
if (!defined('WPE_PRO_MAX_PLATFORM_API_VERSION')) {
    define('WPE_PRO_MAX_PLATFORM_API_VERSION', '0.1.0');
}
if (!defined('WPE_PRO_MIN_PLATFORM_SCHEMA_GENERATION')) {
    define('WPE_PRO_MIN_PLATFORM_SCHEMA_GENERATION', 1);
}
if (!defined('WPE_PRO_MAX_PLATFORM_SCHEMA_GENERATION')) {
    define('WPE_PRO_MAX_PLATFORM_SCHEMA_GENERATION', 1);
}
if (!defined('WPE_PRO_SCHEMA_GENERATION')) {
    define('WPE_PRO_SCHEMA_GENERATION', 1);
}

if (!defined('WPE_PRO_FILE')) {
    define('WPE_PRO_FILE', __FILE__);
}
if (!defined('WPE_PRO_PACKAGE_ACTIVE')) {
    define('WPE_PRO_PACKAGE_ACTIVE', true);
}

$proNotice = static function (string $message): void {
    if (!function_exists('add_action')) {
        return;
    }

    add_action('admin_notices', static function () use ($message): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html($message)
        );
    });
};

if (PHP_VERSION_ID < 80200) {
    $proNotice('WPEssential Pro requires PHP 8.2 or newer.');
    return;
}

/**
 * The Pro package owns only WPEssential\Modules\* implementation source.
 * Compatibility code is the only Pro module namespace allowed to autoload before
 * the local Free/Pro preflight passes. All other Pro implementation remains inert.
 */
spl_autoload_register(
    static function (string $class): void {
        $prefix = 'WPEssential\\Modules\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $compatibilityPrefix = 'WPEssential\\Modules\\Compatibility\\';
        $compatibility = $GLOBALS['wpe_pro_compatibility_result'] ?? null;
        if (
            !str_starts_with($class, $compatibilityPrefix)
            && (!is_array($compatibility) || ($compatibility['state'] ?? '') !== 'compatible')
        ) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        if ($relative === '' || str_contains($relative, '..')) {
            return;
        }

        $file = __DIR__ . '/frameworks/Modules/' . str_replace('\\', '/', $relative) . '.php';
        if (is_readable($file)) {
            require_once $file;
        }
    },
    true,
    true,
);

if (!function_exists('add_action')) {
    return;
}

$moduleClasses = [
    \WPEssential\Modules\Roles\RolesModule::class,
    \WPEssential\Modules\AdminMenu\AdminMenuModule::class,
    \WPEssential\Modules\Settings\SettingsModule::class,
    \WPEssential\Modules\Dashboard\DashboardModule::class,
    \WPEssential\Modules\Query\QueryModule::class,
    \WPEssential\Modules\Fields\FieldsModule::class,
    \WPEssential\Modules\Relations\RelationsModule::class,
    \WPEssential\Modules\Status\StatusModule::class,
    \WPEssential\Modules\AdminColumns\AdminColumnsModule::class,
    \WPEssential\Modules\Listings\ListingsModule::class,
    \WPEssential\Modules\DashboardWidgets\DashboardWidgetsModule::class,
    \WPEssential\Modules\Profiles\ProfilesModule::class,
    \WPEssential\Modules\Membership\MembershipModule::class,
    \WPEssential\Modules\BuilderWidgets\BuilderWidgetsModule::class,
    \WPEssential\Modules\FormsWorkflows\FormsWorkflowsModule::class,
    \WPEssential\Modules\Cron\CronModule::class,
    \WPEssential\Modules\Notifications\NotificationsModule::class,
    \WPEssential\Modules\Emails\EmailsModule::class,
    \WPEssential\Modules\Chat\ChatModule::class,
    \WPEssential\Modules\ProKit\ProKitModule::class,
];

$moduleFileForClass = static function (string $class): ?string {
    $prefix = 'WPEssential\\Modules\\';
    if (!str_starts_with($class, $prefix)) {
        return null;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === '' || str_contains($relative, '..')) {
        return null;
    }

    return __DIR__ . '/frameworks/Modules/' . str_replace('\\', '/', $relative) . '.php';
};

$publishCompatibility = static function (array $result): void {
    // Canonical request-local authority. Compatibility mirror constants below are observability only.
    $GLOBALS['wpe_pro_compatibility_result'] = $result;

    $constants = [
        'WPE_PRO_COMPATIBILITY_STATE' => (string) ($result['state'] ?? 'pro_package_incomplete'),
        'WPE_PRO_COMPATIBILITY_DIMENSION' => (string) ($result['dimension'] ?? 'package'),
        'WPE_PRO_COMPATIBILITY_REASON' => (string) ($result['reason'] ?? 'compatibility_result_unavailable'),
        'WPE_PRO_COMPATIBILITY_REMEDIATION' => (string) ($result['remediation'] ?? 'reinstall_pro'),
        'WPE_PRO_COMPATIBILITY_FREE_VERSION' => (string) ($result['free_version'] ?? 'unknown'),
        'WPE_PRO_COMPATIBILITY_PLATFORM_API' => (string) ($result['platform_api'] ?? 'unknown'),
        'WPE_PRO_COMPATIBILITY_PLATFORM_SCHEMA' => $result['platform_schema'] ?? 'unknown',
        'WPE_PRO_COMPATIBILITY_PRO_SCHEMA' => $result['pro_schema'] ?? 'unknown',
        'WPE_PRO_COMPATIBILITY_BOOT_ALLOWED' => ($result['premium_boot_allowed'] ?? false) === true,
        'WPE_PRO_COMPATIBILITY_MIGRATIONS_ALLOWED' => ($result['premium_migrations_allowed'] ?? false) === true,
    ];

    foreach ($constants as $name => $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }
};

add_action(
    'plugins_loaded',
    static function () use ($proNotice, $moduleClasses, $moduleFileForClass, $publishCompatibility): void {
        $preflightClass = \WPEssential\Modules\Compatibility\LocalCompatibilityPreflight::class;
        if (!class_exists($preflightClass)) {
            $publishCompatibility([
                'state' => 'pro_package_incomplete',
                'dimension' => 'package',
                'reason' => 'compatibility_preflight_missing',
                'remediation' => 'reinstall_pro',
                'free_version' => defined('WPE_VERSION') ? (string) WPE_VERSION : 'unknown',
                'platform_api' => defined('WPE_PLATFORM_API_VERSION') ? (string) WPE_PLATFORM_API_VERSION : 'unknown',
                'platform_schema' => defined('WPE_PLATFORM_SCHEMA_GENERATION')
                    ? WPE_PLATFORM_SCHEMA_GENERATION
                    : 'unknown',
                'pro_schema' => WPE_PRO_SCHEMA_GENERATION,
                'premium_boot_allowed' => false,
                'premium_migrations_allowed' => false,
            ]);
            $proNotice('WPEssential Pro package is incomplete: compatibility preflight is missing.');
            return;
        }

        $packageComplete = true;
        foreach ($moduleClasses as $moduleClass) {
            $moduleFile = $moduleFileForClass($moduleClass);
            if ($moduleFile === null || !is_readable($moduleFile)) {
                $packageComplete = false;
                break;
            }
        }

        $compatibility = $preflightClass::evaluateRuntime($packageComplete);
        if (($compatibility['state'] ?? '') !== 'compatible') {
            $publishCompatibility($compatibility);
            $proNotice(sprintf(
                'WPEssential Pro is inactive because local Free/Pro compatibility failed (%s). Recovery: %s.',
                (string) ($compatibility['reason'] ?? 'unknown'),
                (string) ($compatibility['remediation'] ?? 'repair_packages'),
            ));
            return;
        }

        $requiredFreeRuntimeClasses = [
            \WPEssential\Bootstrap\Plugin::class,
            \WPEssential\Platform\Entitlements\ProductEntitlementState::class,
            \WPEssential\Platform\Entitlements\LocalProductEntitlementProvider::class,
            \WPEssential\Platform\Entitlements\ProductEntitlementSnapshot::class,
            \WPEssential\Platform\Entitlements\PremiumOperationPolicy::class,
            \WPEssential\Platform\Entitlements\EntitlementAwareModuleActivationPolicy::class,
        ];
        foreach ($requiredFreeRuntimeClasses as $requiredFreeRuntimeClass) {
            if (!class_exists($requiredFreeRuntimeClass)) {
                $compatibility['state'] = 'free_bootstrap_incomplete';
                $compatibility['dimension'] = 'package';
                $compatibility['reason'] = 'free_platform_api_profile_incomplete';
                $compatibility['remediation'] = 'repair_free';
                $compatibility['premium_boot_allowed'] = false;
                $compatibility['premium_migrations_allowed'] = false;
                $publishCompatibility($compatibility);
                $proNotice('WPEssential Pro found compatible metadata but the Free Platform API is incomplete.');
                return;
            }
        }

        // Publish compatible only after the declared Free API profile actually resolves.
        $publishCompatibility($compatibility);

        // Entitlement source, in order: an explicit local override (development and CI),
        // the licence stored by Free, else verification unavailable (premium read-only).
        $resolverClass = \WPEssential\Platform\Entitlements\LicenseEntitlementResolver::class;
        if (defined('WPE_PRO_LOCAL_ENTITLEMENT_STATE')) {
            $configuredState = (string) WPE_PRO_LOCAL_ENTITLEMENT_STATE;
            $state = \WPEssential\Platform\Entitlements\ProductEntitlementState::tryFrom($configuredState)
                ?? \WPEssential\Platform\Entitlements\ProductEntitlementState::VerificationUnavailable;
            $snapshot = new \WPEssential\Platform\Entitlements\ProductEntitlementSnapshot(
                $state,
                $state->value === $configuredState ? null : 'invalid_local_entitlement_state',
            );
        } elseif (class_exists($resolverClass)) {
            $snapshot = $resolverClass::current();
        } else {
            $snapshot = new \WPEssential\Platform\Entitlements\ProductEntitlementSnapshot(
                \WPEssential\Platform\Entitlements\ProductEntitlementState::VerificationUnavailable,
                'free_license_bridge_missing',
            );
        }
        $entitlements = new \WPEssential\Platform\Entitlements\LocalProductEntitlementProvider($snapshot);
        $operations = new \WPEssential\Platform\Entitlements\PremiumOperationPolicy($entitlements);

        \WPEssential\Bootstrap\Plugin::setModuleActivationPolicy(
            new \WPEssential\Platform\Entitlements\EntitlementAwareModuleActivationPolicy($entitlements),
        );

        if (!defined('WPE_PRO_ENTITLEMENT_STATE')) {
            define('WPE_PRO_ENTITLEMENT_STATE', $entitlements->snapshot()->state->value);
        }
        if (!defined('WPE_PRO_PREMIUM_READS_ALLOWED')) {
            define('WPE_PRO_PREMIUM_READS_ALLOWED', $operations->allowsRead());
        }
        if (!defined('WPE_PRO_PREMIUM_MUTATIONS_ALLOWED')) {
            define('WPE_PRO_PREMIUM_MUTATIONS_ALLOWED', $operations->allowsMutation());
        }

        foreach ($moduleClasses as $moduleClass) {
            if (!class_exists($moduleClass)) {
                $proNotice(sprintf('WPEssential Pro package is incomplete: missing %s.', $moduleClass));
                return;
            }
        }

        foreach ($moduleClasses as $moduleClass) {
            \WPEssential\Bootstrap\Plugin::registerModule(new $moduleClass());
        }

        // ADR-0239: Free owns the registry; premium Elementor synchronization is opt-in.
        // Older Free versions ignore this filter. Do not contribute it for read-only licences.
        if (function_exists('add_filter')) {
            add_filter(
                'wpessential_site_kit_elementor_sync_enabled',
                static fn (): bool => $operations->allowsMutation(),
            );
        }

        // Read-only enforcement: once Free has booted (plugins_loaded -100), deny mutating
        // pro abilities while the entitlement does not allow changes.
        add_action(
            'plugins_loaded',
            static function () use ($operations): void {
                $kernel = \WPEssential\Bootstrap\Plugin::kernel();
                $abilities = $kernel !== null && $kernel->services()->has('platform.abilities')
                    ? $kernel->services()->get('platform.abilities')
                    : null;
                if (is_object($abilities) && method_exists($abilities, 'setPremiumMutationGate')) {
                    $abilities->setPremiumMutationGate(static fn (): bool => $operations->allowsMutation());
                }
            },
            -99,
        );

        // Pro admin form handlers that write directly (not through abilities).
        if (!$operations->allowsMutation()) {
            add_action(
                'admin_init',
                static function (): void {
                    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : '';
                    $proWriteActions = [
                        'wpessential_relations_save',
                        'wpessential_relations_status',
                        'wpessential_relations_edge',
                    ];
                    if (in_array($action, $proWriteActions, true)) {
                        wp_die(
                            esc_html__('WPEssential Pro is read-only: your licence is not active. Your data is safe; renew or activate your licence to make changes again.', 'wpessential'),
                            esc_html__('WPEssential Pro is read-only', 'wpessential'),
                            ['response' => 403, 'back_link' => true],
                        );
                    }
                },
                0,
            );
        }
    },
    -200,
);
