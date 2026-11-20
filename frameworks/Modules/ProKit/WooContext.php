<?php

declare(strict_types=1);

namespace WPEssential\Modules\ProKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers for the WooCommerce kit elements: which product an element shows (the current
 * product, or in the editor the preview product, else the newest one), the "WooCommerce off"
 * notice, and the product card used by grids.
 */
final class WooContext {

	public static function active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/** Editor-only notice when WooCommerce is not active; nothing on the front end. */
	public static function missing(): string {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return '<p class="wpe-woo__missing">' . esc_html__( 'Activate WooCommerce to use this element.', 'wpessential-pro' ) . '</p>';
		}

		return '';
	}

	/** True while rendering for the block editor or Elementor preview. */
	public static function preview(): bool {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() || ( isset( $_GET['elementor-preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag.
	}

	/** @param array<string,mixed> $a Element values (previewProduct optional). */
	public static function product( array $a = [] ): ?\WC_Product {
		if ( ! self::active() ) {
			return null;
		}
		global $product;
		if ( $product instanceof \WC_Product && ! self::preview() ) {
			return $product;
		}
		$queried = get_queried_object_id();
		if ( $queried && 'product' === get_post_type( $queried ) && ! self::preview() ) {
			$found = wc_get_product( $queried );
			return $found instanceof \WC_Product ? $found : null;
		}
		$id = absint( $a['previewProduct'] ?? 0 );
		if ( $id ) {
			$found = wc_get_product( $id );
			if ( $found instanceof \WC_Product ) {
				return $found;
			}
		}
		if ( ! self::preview() ) {
			return null;
		}
		$latest = wc_get_products(
			[
				'status' => 'publish',
				'limit'  => 1,
			]
		);

		return isset( $latest[0] ) && $latest[0] instanceof \WC_Product ? $latest[0] : null;
	}

	/**
	 * Runs $callback with the WooCommerce globals set to $product (Woo template functions read them).
	 *
	 * @param callable():void $callback
	 */
	public static function with( \WC_Product $item, callable $callback ): string {
		global $product, $post;
		$old_product = $product;
		$old_post    = $post;
		$product     = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		$post        = get_post( $item->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		setup_postdata( $post );
		ob_start();
		$callback();
		$html    = (string) ob_get_clean();
		$product = $old_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $old_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $old_post ) {
			setup_postdata( $old_post );
		} else {
			wp_reset_postdata();
		}

		return $html;
	}

	/**
	 * One product card (used by archive and related grids).
	 *
	 * @param array<string,mixed> $a Display toggles: showImage, showRating, showPrice, showButton.
	 */
	public static function card( \WC_Product $item, array $a ): string {
		$url  = get_permalink( $item->get_id() );
		$html = '<article class="wpe-woo-card">';
		if ( ! empty( $a['showImage'] ) ) {
			$html .= '<a class="wpe-woo-card__image" href="' . esc_url( (string) $url ) . '" tabindex="-1" aria-hidden="true">' . wp_kses_post( $item->get_image( 'woocommerce_thumbnail', [ 'loading' => 'lazy' ] ) ) . '</a>';
		}
		if ( $item->is_on_sale() ) {
			$html .= '<span class="wpe-woo-card__badge">' . esc_html__( 'Sale', 'wpessential-pro' ) . '</span>';
		}
		$html .= '<h3 class="wpe-woo-card__title"><a href="' . esc_url( (string) $url ) . '">' . esc_html( $item->get_name() ) . '</a></h3>';
		if ( ! empty( $a['showRating'] ) && (float) $item->get_average_rating() > 0 ) {
			$html .= wp_kses_post( wc_get_rating_html( (float) $item->get_average_rating(), $item->get_rating_count() ) );
		}
		if ( ! empty( $a['showPrice'] ) ) {
			$html .= '<div class="wpe-woo-card__price">' . wp_kses_post( $item->get_price_html() ) . '</div>';
		}
		if ( ! empty( $a['showButton'] ) ) {
			$html .= self::with(
				$item,
				static function (): void {
					woocommerce_template_loop_add_to_cart();
				}
			);
		}

		return $html . '</article>';
	}
}
