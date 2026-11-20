<?php

declare(strict_types=1);

namespace WPEssential\Modules\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime check of a group's location rules against the object being edited.
 *
 * A group shows when any OR group has every rule matching. Sources the context does not know
 * (page template, media, …) are left to their own placement code and count as matching here.
 */
final class FieldLocationMatcher {

	/**
	 * @param array<mixed> $locations Normalized location groups.
	 * @param array<string,list<string>> $context Source => current values, e.g. post_type => [page], entity_id => [12].
	 */
	public static function matches( array $locations, array $context ): bool {
		if ( $locations === [] ) {
			return true;
		}
		foreach ( $locations as $group ) {
			$all = true;
			foreach ( is_array( $group ) ? $group : [] as $rule ) {
				if ( is_array( $rule ) && ! self::rule( $rule, $context ) ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<mixed> $rule
	 * @param array<string,list<string>> $context
	 */
	private static function rule( array $rule, array $context ): bool {
		$source = (string) ( $rule['source'] ?? '' );
		if ( ! array_key_exists( $source, $context ) ) {
			return true;
		}
		$wanted   = array_map( 'strval', is_array( $rule['value'] ?? null ) ? $rule['value'] : [ $rule['value'] ?? '' ] );
		$hit      = array_intersect( $wanted, $context[ $source ] ) !== [];
		$positive = in_array( $rule['operator'] ?? '', [ 'equals', 'in' ], true );
		$result   = $positive ? $hit : ! $hit;

		return ( $rule['negate'] ?? false ) === true ? ! $result : $result;
	}
}
