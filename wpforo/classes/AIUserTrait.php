<?php

namespace wpforo\classes;

/**
 * wpForo AI User Enrichment Trait
 *
 * Provides unified user data enrichment methods for AI classes.
 * Handles batch fetching of user display names with proper fallback chains.
 *
 * @since 3.0.0
 */
trait AIUserTrait {

	/**
	 * User type constants (if not defined in using class)
	 */
	private static $USER_TYPE_USER   = 'user';
	private static $USER_TYPE_GUEST  = 'guest';
	private static $USER_TYPE_CRON   = 'cron';
	private static $USER_TYPE_SYSTEM = 'system';

	/**
	 * Enrich items with user display names
	 *
	 * Batch fetches user data and adds 'user_display' field to each item.
	 * Uses proper fallback chain: display_name → user_nicename → user_login → Guest
	 *
	 * @param array  $items        Array of items with user IDs
	 * @param string $userid_field Field name containing user ID (default: 'userid')
	 * @param array  $options      Options:
	 *                             - 'user_type_field' => field for user type (default: 'user_type')
	 *                             - 'output_field' => field name for display name (default: 'user_display')
	 *                             - 'use_type_labels' => use type labels for non-users (default: true)
	 *
	 * @return array Enriched items with user_display field
	 */
	protected function enrich_items_with_user_data( array $items, string $userid_field = 'userid', array $options = [] ): array {
		if ( empty( $items ) ) {
			return $items;
		}

		$defaults = [
			'user_type_field' => 'user_type',
			'output_field'    => 'user_display',
			'use_type_labels' => true,
		];
		$options  = array_merge( $defaults, $options );

		// Collect unique user IDs (excluding 0 and empty)
		$user_ids = [];
		foreach ( $items as $item ) {
			$userid = isset( $item[ $userid_field ] ) ? (int) $item[ $userid_field ] : 0;
			if ( $userid > 0 ) {
				$user_ids[ $userid ] = $userid;
			}
		}

		// Batch fetch users using wpForo's cached method (single query)
		$user_names = [];
		if ( ! empty( $user_ids ) ) {
			$members = WPF()->member->get_members( [ 'include' => array_values( $user_ids ) ] );
			foreach ( $members as $member ) {
				$user_names[ $member['userid'] ] = $this->get_display_name_with_fallback( $member );
			}
		}

		// Enrich items
		foreach ( $items as &$item ) {
			$userid    = isset( $item[ $userid_field ] ) ? (int) $item[ $userid_field ] : 0;
			$user_type = $item[ $options['user_type_field'] ] ?? self::$USER_TYPE_USER;

			if ( $userid > 0 && isset( $user_names[ $userid ] ) ) {
				$item[ $options['output_field'] ] = $user_names[ $userid ];
				// Ensure user_type is set for registered users
				if ( ! isset( $item[ $options['user_type_field'] ] ) ) {
					$item[ $options['user_type_field'] ] = self::$USER_TYPE_USER;
				}
			} else {
				// No valid user ID or user not found
				if ( $options['use_type_labels'] && ! empty( $user_type ) && $user_type !== self::$USER_TYPE_USER ) {
					$item[ $options['output_field'] ] = $this->get_user_type_display_label( $user_type );
				} else {
					$item[ $options['output_field'] ] = wpforo_phrase( 'Guest', false );
				}
				// Set guest type if not already set
				if ( ! isset( $item[ $options['user_type_field'] ] ) || $item[ $options['user_type_field'] ] === self::$USER_TYPE_USER ) {
					$item[ $options['user_type_field'] ] = self::$USER_TYPE_GUEST;
				}
			}
		}

		return $items;
	}

	/**
	 * Get display name for a single user ID
	 *
	 * Uses wpforo_member() for cached single-user lookup with fallback chain.
	 *
	 * @param int $userid User ID (0 for guests)
	 *
	 * @return string User display name
	 */
	protected function get_single_user_display_name( int $userid ): string {
		$userid = (int) $userid;

		// For guests (userid = 0), return translated Guest
		if ( $userid === 0 ) {
			return wpforo_phrase( 'Guest', false );
		}

		// Get member data using wpforo_member (uses caching)
		$member = wpforo_member( $userid );

		if ( empty( $member ) || empty( $member['userid'] ) ) {
			return wpforo_phrase( 'Guest', false );
		}

		return $this->get_display_name_with_fallback( $member );
	}

	/**
	 * Get display name with fallback chain
	 *
	 * Tries: display_name → user_nicename → user_login → Guest
	 *
	 * @param array $member Member data array
	 *
	 * @return string Display name
	 */
	protected function get_display_name_with_fallback( array $member ): string {
		// Try display_name first
		if ( ! empty( $member['display_name'] ) && ! $this->is_anonymous_name( $member['display_name'] ) ) {
			return $member['display_name'];
		}

		// Try user_nicename
		if ( ! empty( $member['user_nicename'] ) && ! $this->is_anonymous_name( $member['user_nicename'] ) ) {
			return $member['user_nicename'];
		}

		// Try user_login
		if ( ! empty( $member['user_login'] ) && ! $this->is_anonymous_name( $member['user_login'] ) ) {
			return $member['user_login'];
		}

		// Final fallback
		return wpforo_phrase( 'Guest', false );
	}

	/**
	 * Check if name is a placeholder/anonymous name
	 *
	 * @param string $name Name to check
	 *
	 * @return bool True if name appears to be anonymous/placeholder
	 */
	private function is_anonymous_name( string $name ): bool {
		$lower = strtolower( trim( $name ) );

		return in_array( $lower, [ 'anonymous', 'guest', '' ], true );
	}

	/**
	 * Get user type display label
	 *
	 * Returns translated labels for special user types (cron, system, guest).
	 *
	 * @param string $user_type User type constant
	 *
	 * @return string Display label
	 */
	protected function get_user_type_display_label( string $user_type ): string {
		$labels = [
			self::$USER_TYPE_USER   => wpforo_phrase( 'User', false ),
			self::$USER_TYPE_GUEST  => wpforo_phrase( 'Guest', false ),
			self::$USER_TYPE_CRON   => wpforo_phrase( 'Cron Job', false ),
			self::$USER_TYPE_SYSTEM => wpforo_phrase( 'System', false ),
		];

		// Check if using class has its own constants
		if ( defined( 'static::USER_TYPE_USER' ) ) {
			$labels = [
				static::USER_TYPE_USER   => wpforo_phrase( 'User', false ),
				static::USER_TYPE_GUEST  => wpforo_phrase( 'Guest', false ),
				static::USER_TYPE_CRON   => wpforo_phrase( 'Cron Job', false ),
				static::USER_TYPE_SYSTEM => wpforo_phrase( 'System', false ),
			];
		}

		return $labels[ $user_type ] ?? $user_type;
	}

	/**
	 * Batch get display names for multiple user IDs
	 *
	 * Returns an associative array mapping user IDs to display names.
	 *
	 * @param array $user_ids Array of user IDs
	 *
	 * @return array Associative array [userid => display_name]
	 */
	protected function batch_get_user_display_names( array $user_ids ): array {
		$user_ids = array_unique( array_filter( array_map( 'intval', $user_ids ) ) );

		if ( empty( $user_ids ) ) {
			return [];
		}

		$user_names = [];
		$members    = WPF()->member->get_members( [ 'include' => array_values( $user_ids ) ] );

		foreach ( $members as $member ) {
			$user_names[ $member['userid'] ] = $this->get_display_name_with_fallback( $member );
		}

		return $user_names;
	}
}
