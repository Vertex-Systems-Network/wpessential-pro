<?php

declare(strict_types=1);

namespace WPEssential\Modules\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;

/**
 * Show a field only when other fields in the same group match.
 *
 * Shape (ACF style): a list of OR groups, each a list of AND rules
 * {field: <field key>, operator: one of OPERATORS, value?: string}. "in" / "not_in" take a comma-separated list;
 * the number comparisons need a numeric value.
 */
final class FieldConditionalLogic {

	public const OPERATORS = [ '==', '!=', 'empty', 'not_empty', 'contains', 'not_contains', 'starts_with', 'ends_with', '>', '>=', '<', '<=', 'in', 'not_in' ];

	/**
	 * @return list<list<array{field:string,operator:string,value:string}>>
	 */
	public static function normalize( mixed $groups ): array {
		if ( $groups === null || $groups === [] ) {
			return [];
		}
		if ( ! is_array( $groups ) || ! array_is_list( $groups ) || count( $groups ) > 10 ) {
			throw new InvalidArgumentException( 'Conditional logic must be a list of up to 10 OR groups.' );
		}
		$out = [];
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || ! array_is_list( $group ) || $group === [] || count( $group ) > 10 ) {
				throw new InvalidArgumentException( 'Each conditional logic group must be a list of 1 to 10 rules.' );
			}
			$rules = [];
			foreach ( $group as $rule ) {
				$field    = is_array( $rule ) ? ( $rule['field'] ?? null ) : null;
				$operator = is_array( $rule ) ? ( $rule['operator'] ?? null ) : null;
				$value    = is_array( $rule ) ? ( $rule['value'] ?? '' ) : '';
				if ( ! is_string( $field ) || preg_match( '/^[a-z0-9_-]{1,64}$/', $field ) !== 1 ) {
					throw new InvalidArgumentException( 'A conditional logic rule must name a field key.' );
				}
				if ( ! in_array( $operator, self::OPERATORS, true ) ) {
					throw new InvalidArgumentException( 'Unknown conditional logic operator.' );
				}
				if ( ! is_scalar( $value ) ) {
					throw new InvalidArgumentException( 'A conditional logic value must be text.' );
				}
				if ( in_array( $operator, [ '>', '>=', '<', '<=' ], true ) && ! is_numeric( $value ) ) {
					throw new InvalidArgumentException( 'Greater / less than conditions need a number.' );
				}
				$rules[] = [
					'field'    => $field,
					'operator' => (string) $operator,
					'value'    => (string) $value,
				];
			}
			$out[] = $rules;
		}

		return $out;
	}

	/**
	 * True when no logic is set or any OR group has all its rules matching.
	 *
	 * @param list<list<array{field:string,operator:string,value:string}>> $groups
	 * @param array<string,mixed> $values Field key => current value (scalars or lists).
	 */
	public static function visible( array $groups, array $values ): bool {
		if ( $groups === [] ) {
			return true;
		}
		foreach ( $groups as $group ) {
			$all = true;
			foreach ( $group as $rule ) {
				if ( ! self::matches( $rule, $values[ $rule['field'] ] ?? null ) ) {
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

	/** @param list<string> $list @param callable(string):bool $test */
	private static function any( array $list, callable $test ): bool {
		return array_filter( $list, $test ) !== [];
	}

	/** @return list<string> */
	private static function split( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), static fn ( string $item ): bool => $item !== '' ) );
	}

	/** @param array{field:string,operator:string,value:string} $rule */
	private static function matches( array $rule, mixed $value ): bool {
		$list  = array_map( static fn ( mixed $item ): string => is_bool( $item ) ? ( $item ? '1' : '0' ) : ( is_scalar( $item ) ? (string) $item : '' ), is_array( $value ) ? $value : ( $value === null ? [] : [ $value ] ) );
		$empty = array_filter( $list, static fn ( string $item ): bool => $item !== '' && $item !== '0' ) === [];

		return match ( $rule['operator'] ) {
			'=='        => in_array( $rule['value'], $list, true ),
			'!='        => ! in_array( $rule['value'], $list, true ),
			'empty'     => $empty,
			'not_empty' => ! $empty,
			'contains'     => self::any( $list, static fn ( string $item ): bool => $rule['value'] !== '' && str_contains( $item, $rule['value'] ) ),
			'not_contains' => ! self::any( $list, static fn ( string $item ): bool => $rule['value'] !== '' && str_contains( $item, $rule['value'] ) ),
			'starts_with'  => self::any( $list, static fn ( string $item ): bool => $rule['value'] !== '' && str_starts_with( $item, $rule['value'] ) ),
			'ends_with'    => self::any( $list, static fn ( string $item ): bool => $rule['value'] !== '' && str_ends_with( $item, $rule['value'] ) ),
			'>'            => self::any( $list, static fn ( string $item ): bool => is_numeric( $item ) && (float) $item > (float) $rule['value'] ),
			'>='           => self::any( $list, static fn ( string $item ): bool => is_numeric( $item ) && (float) $item >= (float) $rule['value'] ),
			'<'            => self::any( $list, static fn ( string $item ): bool => is_numeric( $item ) && (float) $item < (float) $rule['value'] ),
			'<='           => self::any( $list, static fn ( string $item ): bool => is_numeric( $item ) && (float) $item <= (float) $rule['value'] ),
			'in'           => self::any( $list, static fn ( string $item ): bool => in_array( $item, self::split( $rule['value'] ), true ) ),
			'not_in'       => ! self::any( $list, static fn ( string $item ): bool => in_array( $item, self::split( $rule['value'] ), true ) ),
			default        => false,
		};
	}
}
