<?php

namespace wpforo\classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait AIAjaxTrait
 *
 * Provides common AJAX handling functionality for AI classes.
 * Reduces code duplication for nonce verification, permission checks,
 * board context switching, and JSON responses.
 *
 * Usage:
 * class MyAIClass {
 *     use AIAjaxTrait;
 *
 *     public function ajax_my_action() {
 *         $this->verify_ajax_admin_request( 'my_nonce_action', 'nonce' );
 *         $this->switch_board_context();
 *
 *         // Your logic here...
 *
 *         $this->send_success( [ 'data' => $result ] );
 *     }
 * }
 */
trait AIAjaxTrait {

	/**
	 * Verify AJAX request with nonce and admin permission check.
	 * Sends error response and dies if verification fails.
	 *
	 * @param string $nonce_action The nonce action name.
	 * @param string $nonce_param  The nonce parameter name in $_REQUEST. Default 'nonce'.
	 *
	 * @return void
	 */
	protected function verify_ajax_admin_request( $nonce_action, $nonce_param = 'nonce' ) {
		check_ajax_referer( $nonce_action, $nonce_param );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'Permission denied', 'wpforo' ), 403 );
		}
	}

	/**
	 * Verify AJAX request with nonce only (no permission check).
	 * Useful for frontend AJAX handlers that have custom permission logic.
	 * Sends error response and dies if verification fails.
	 *
	 * @param string $nonce_action The nonce action name.
	 * @param string $nonce_param  The nonce parameter name in $_REQUEST. Default 'nonce'.
	 *
	 * @return void
	 */
	protected function verify_ajax_nonce( $nonce_action, $nonce_param = 'nonce' ) {
		if ( ! check_ajax_referer( $nonce_action, $nonce_param, false ) ) {
			$this->send_error( __( 'Security check failed', 'wpforo' ), 403 );
		}
	}

	/**
	 * Verify AJAX request with nonce and custom capability check.
	 * Sends error response and dies if verification fails.
	 *
	 * @param string $nonce_action The nonce action name.
	 * @param string $nonce_param  The nonce parameter name in $_REQUEST. Default 'nonce'.
	 * @param string $capability   The capability to check. Default 'manage_options'.
	 *
	 * @return void
	 */
	protected function verify_ajax_request( $nonce_action, $nonce_param = 'nonce', $capability = 'manage_options' ) {
		check_ajax_referer( $nonce_action, $nonce_param );

		if ( ! current_user_can( $capability ) ) {
			$this->send_error( __( 'Permission denied', 'wpforo' ), 403 );
		}
	}

	/**
	 * Switch to the correct board context based on POST parameter.
	 * This is needed for multi-board setups where each board has separate data.
	 *
	 * @param string $param_name The POST parameter name containing board ID. Default 'boardid'.
	 *
	 * @return int The board ID that was switched to (0 if default board).
	 */
	protected function switch_board_context( $param_name = 'boardid' ) {
		$boardid = isset( $_POST[ $param_name ] ) ? intval( $_POST[ $param_name ] ) : 0;
		if ( $boardid > 0 ) {
			WPF()->change_board( $boardid );
		}
		return $boardid;
	}

	/**
	 * Send JSON error response and die.
	 *
	 * @param string   $message    Error message.
	 * @param int|null $status_code HTTP status code. Default null (WordPress default).
	 *
	 * @return void
	 */
	protected function send_error( $message, $status_code = null ) {
		wp_send_json_error( [ 'message' => $message ], $status_code );
	}

	/**
	 * Send JSON success response and die.
	 *
	 * @param array $data Response data array.
	 *
	 * @return void
	 */
	protected function send_success( $data ) {
		wp_send_json_success( $data );
	}

	/**
	 * Get sanitized POST parameter with default value.
	 *
	 * @param string $key      Parameter name.
	 * @param mixed  $default  Default value if parameter is not set.
	 * @param string $sanitize Sanitization type: 'text', 'int', 'bool', 'email', 'url', 'array_int'.
	 *                         Default 'text'.
	 *
	 * @return mixed Sanitized value.
	 */
	protected function get_post_param( $key, $default = '', $sanitize = 'text' ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		$value = $_POST[ $key ];

		switch ( $sanitize ) {
			case 'int':
				return intval( $value );

			case 'bool':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );

			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'array_int':
				return is_array( $value ) ? array_map( 'intval', $value ) : [];

			case 'array_text':
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Get multiple POST parameters at once with sanitization.
	 *
	 * @param array $params Array of parameter definitions:
	 *                      [ 'key' => [ 'default' => '', 'sanitize' => 'text' ] ]
	 *                      or simple: [ 'key' => 'default_value' ] (uses 'text' sanitization)
	 *
	 * @return array Associative array of sanitized values.
	 */
	protected function get_post_params( $params ) {
		$result = [];

		foreach ( $params as $key => $config ) {
			if ( is_array( $config ) ) {
				$default  = $config['default'] ?? '';
				$sanitize = $config['sanitize'] ?? 'text';
			} else {
				$default  = $config;
				$sanitize = 'text';
			}

			$result[ $key ] = $this->get_post_param( $key, $default, $sanitize );
		}

		return $result;
	}

	/**
	 * Validate required POST parameters exist.
	 * Sends error response and dies if any required parameter is missing.
	 *
	 * @param array $required Array of required parameter names.
	 *
	 * @return void
	 */
	protected function require_post_params( $required ) {
		$missing = [];
		foreach ( $required as $param ) {
			if ( ! isset( $_POST[ $param ] ) || $_POST[ $param ] === '' ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			$this->send_error(
				sprintf( __( 'Missing required parameters: %s', 'wpforo' ), implode( ', ', $missing ) ),
				400
			);
		}
	}

	/**
	 * Check if user has exceeded rate limit for an AI feature.
	 * Sends error response and dies if rate limited.
	 *
	 * Rate limits are stored in WordPress transients with daily reset.
	 * Moderators (usergroup[cans][ms]) are exempt from rate limits.
	 *
	 * @param string $feature Feature key: search, translation, summarization, suggestions, chatbot
	 *
	 * @return void
	 */
	protected function check_rate_limit( $feature ) {
		$user_id = get_current_user_id();

		// Check if user is moderator (has 'ms' permission) - unlimited access
		if ( $user_id && WPF()->usergroup->can( 'ms' ) ) {
			return; // Moderators have unlimited access
		}

		// Get rate limit settings for this feature
		$rate_limits = wpforo_setting( 'ai', 'rate_limits' );
		if ( empty( $rate_limits ) || empty( $rate_limits[ $feature ] ) ) {
			return; // No limits configured for this feature
		}

		$feature_limits = $rate_limits[ $feature ];

		// Determine limit based on user type (guest vs logged-in)
		if ( $user_id ) {
			$limit         = (int) ( $feature_limits['user'] ?? 0 );
			$transient_key = "wpforo_ai_{$feature}_user_{$user_id}";
		} else {
			$limit         = (int) ( $feature_limits['guest'] ?? 0 );
			$ip            = $this->get_client_ip();
			$transient_key = "wpforo_ai_{$feature}_guest_" . md5( $ip );
		}

		// If limit is 0, feature is disabled for this user type
		if ( $limit === 0 ) {
			$this->send_error(
				__( 'This feature is not available.', 'wpforo' ),
				403
			);
		}

		// Check current usage count
		$current_count = (int) get_transient( $transient_key );

		if ( $current_count >= $limit ) {

			// Show different message for guests vs logged-in users
			if ( ! $user_id ) {
				$message = __( 'Daily limit reached. Please login to remove this limitation.', 'wpforo' );
			} else {
				$message = __( 'Daily limit reached. Please try again tomorrow.', 'wpforo' );
			}
			$this->send_error( $message, 429 );
		}

		// Increment counter (expires at midnight local time)
		$seconds_until_midnight = strtotime( 'tomorrow' ) - time();
		set_transient( $transient_key, $current_count + 1, $seconds_until_midnight );
	}

	/**
	 * Get the client IP address, considering proxy headers.
	 *
	 * @return string Client IP address
	 */
	protected function get_client_ip() {
		$ip_keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0];
				return trim( $ip );
			}
		}
		return '0.0.0.0';
	}
}
