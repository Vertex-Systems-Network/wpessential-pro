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
	 * Everything a post edit screen can be matched on (post type, status, ID, template, format,
	 * parent, terms as "taxonomy:id", and the editing user).
	 *
	 * @return array<string,list<string>>
	 */
	public static function postContext( int $postId, string $postType, string $status ): array {
		$context = [
			'post_type'   => [ $postType ],
			'post_status' => [ $status ],
			'entity_id'   => [ (string) $postId ],
		] + self::currentUser();
		if ( $postId < 1 || ! function_exists( 'get_post' ) ) {
			return $context;
		}
		$post                      = get_post( $postId );
		$template                  = get_page_template_slug( $postId );
		$format                    = get_post_format( $postId );
		$context['page_template']  = [ is_string( $template ) && $template !== '' ? $template : 'default' ];
		$context['post_format']    = [ is_string( $format ) ? $format : 'standard' ];
		$context['post_parent']    = [ (string) ( $post instanceof \WP_Post ? $post->post_parent : 0 ) ];
		$terms                     = [];
		foreach ( get_object_taxonomies( $postType ) as $taxonomy ) {
			$ids = wp_get_object_terms( $postId, $taxonomy, [ 'fields' => 'ids' ] );
			foreach ( is_array( $ids ) ? $ids : [] as $id ) {
				$terms[] = $taxonomy . ':' . $id;
			}
		}
		$context['post_term'] = $terms;

		return $context;
	}

	/** @return array<string,list<string>> Roles and ID of the user doing the editing. */
	public static function currentUser(): array {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return [];
		}
		$user = wp_get_current_user();

		return [
			'current_user_role' => array_values( array_map( 'strval', (array) $user->roles ) ),
			'current_user'      => [ (string) $user->ID ],
		];
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
