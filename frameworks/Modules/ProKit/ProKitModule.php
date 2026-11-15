<?php

declare(strict_types=1);

namespace WPEssential\Modules\ProKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPEssential\Contracts\ModuleInterface;
use WPEssential\Contracts\ServiceRegistryInterface;
use WPEssential\Platform\Modules\ModuleManifest;

/**
 * Pro elements of the WPEssential kit (ADR-0238 in Free): countdown, flip, modal, pricing table,
 * chart, forms, products and the rest. Free ships the kit engine and the basic elements; this
 * module adds Pro's kit/ folder to it through `wpessential_kit_roots`, so each element becomes
 * a Gutenberg block and an Elementor widget, switchable on the Elements screen. Being a Pro
 * module, it only loads with an active Pro licence or trial.
 */
final class ProKitModule implements ModuleInterface {

	public const ID = 'pro-elements';

	public function manifest(): ModuleManifest {
		return new ModuleManifest( id: self::ID, name: 'Pro elements', version: '0.1.0', edition: 'pro' );
	}

	public function register( ServiceRegistryInterface $services ): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'wpessential_kit_roots', [ self::class, 'roots' ] );
	}

	public function boot( ServiceRegistryInterface $services ): void {}

	/**
	 * @param mixed $roots Kit folders added so far.
	 * @return array<int,mixed>
	 */
	public static function roots( mixed $roots ): array {
		$roots   = is_array( $roots ) ? array_values( $roots ) : [];
		$root    = dirname( __DIR__, 3 );
		$roots[] = [
			'dir'       => $root . '/kit',
			'url'       => function_exists( 'plugins_url' ) ? plugins_url( 'kit', $root . '/wpessential-pro.php' ) : '',
			'edition'   => 'pro',
			'namespace' => __NAMESPACE__ . '\\Elements',
		];

		return $roots;
	}
}
