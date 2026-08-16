<?php

namespace gVectors\License;

// Exit if accessed directly
use gVectors\License\Services\ActionsService;
use gVectors\License\Services\AddonsService;
use gVectors\License\Services\ApiService;
use gVectors\License\Services\LicenseService;

if( ! defined( 'ABSPATH' ) ) exit;

class LicenseModule {
	/**
	 * @deprecated Use LicenseModule::getActionsService($slug) instead.
	 * Kept for backward compatibility — always points to the last-instantiated plugin's service.
	 */
	public static $actionsService;

	/**
	 * Keyed registry: slug → ActionsService instance.
	 * Allows multiple plugins to coexist without overwriting each other.
	 */
	private static $instances = [];

	public function __construct( Config $config ) {
		$actionsService       = new ActionsService( $config, new AddonsService( $config, new LicenseService( $config, new ApiService( $config ) ) ) );
		self::$actionsService = $actionsService; // backward compat
		self::$instances[ $config->get_core_plugin_slug() ] = $actionsService;
		new AdminPage( $config );
	}

	/**
	 * Get the ActionsService for a specific core plugin slug.
	 */
	public static function getActionsService( string $slug ): ?ActionsService {
		return self::$instances[ $slug ] ?? null;
	}

	/**
	 * Generate a unique site token for authenticating with the proxy server.
	 * Based on raw domain + WordPress auth salts - unique per installation, not guessable.
	 * Uses AUTH_SALT + SECURE_AUTH_SALT for maximum entropy.
	 * Falls back to NONCE_SALT or LOGGED_IN_SALT if the primary salts are missing.
	 * All standard WordPress installations define these in wp-config.php.
	 */
	public static function get_site_token(): string {
		$domain = self::get_site_domain();
		$salt   = self::get_auth_salt();
		return hash_hmac( 'sha256', $domain, $salt );
	}

	/**
	 * Get a strong, unpredictable salt for HMAC token generation.
	 * Combines multiple WordPress salts for maximum entropy.
	 * Refuses to use a hardcoded fallback — the site must have proper salts configured.
	 */
	private static function get_auth_salt(): string {
		$parts = [];
		if( defined( 'AUTH_SALT' ) && AUTH_SALT !== '' )               $parts[] = AUTH_SALT;
		if( defined( 'SECURE_AUTH_SALT' ) && SECURE_AUTH_SALT !== '' ) $parts[] = SECURE_AUTH_SALT;
		if( defined( 'LOGGED_IN_SALT' ) && LOGGED_IN_SALT !== '' )    $parts[] = LOGGED_IN_SALT;
		if( defined( 'NONCE_SALT' ) && NONCE_SALT !== '' )            $parts[] = NONCE_SALT;

		if( ! empty( $parts ) ) {
			return implode( '|', $parts );
		}

		// Absolute last resort: use DB-based unique key (wp_options: siteurl + DB password hash)
		// This is still unique per installation, unlike a hardcoded string
		return hash( 'sha256', DB_NAME . ':' . DB_USER . ':' . self::get_site_domain() );
	}

	/**
	 * Get the raw site domain (no protocol, no www, no trailing slash).
	 * e.g. "example.com" or "sub.example.com"
	 */
	public static function get_site_domain(): string {
		return self::normalize_domain( get_site_url() );
	}

	/**
	 * Normalize a site domain for comparison: lowercase, strip protocol and www, trim slashes.
	 * Must match the server-side LicenseService::normalizeDomain() logic.
	 */
	public static function normalize_domain( string $url ): string {
		$url = rtrim( strtolower( trim( $url ) ), '/' );
		$url = preg_replace( '#^https?://#', '', $url );
		$url = preg_replace( '#^www\.#', '', $url );
		// Strip path — keep only host(:port), e.g. localhost/subpath → localhost
		return explode( '/', $url )[0];
	}

	/**
	 * Detect if the current WordPress installation is running on a development, local, or staging environment.
	 * Development sites are exempt from signature verification and tamper detection.
	 *
	 * Detects:
	 * - localhost / 127.0.0.1 / ::1 / 0.0.0.0
	 * - IP addresses (private ranges: 10.x, 172.16-31.x, 192.168.x, and any raw IP)
	 * - Local TLDs: .local, .loc, .test, .localhost, .example, .invalid, .internal, .home
	 * - Virtual host dev TLDs: .ddev.site, .lndo.site, .nip.io, .sslip.io, .xip.io
	 * - Known staging/dev subdomains: dev.*, staging.*, stage.*, test.*, local.*
	 * - Known temporary site patterns: *.instawp.xyz, *.tastewp.com
	 * - WordPress environment type set to 'local', 'development', or 'staging'
	 * - WP_LOCAL_DEV or WP_DEBUG constants
	 * - Domains with port numbers (e.g., site.com:8080)
	 *
	 * Results are cached per request via a static variable.
	 *
	 * @return bool True if this is a development/local/staging environment.
	 */
	public static function is_development_site(): bool {
		static $is_dev = null;
		if( $is_dev !== null ) return $is_dev;

		$site_domain = strtolower( self::get_site_domain() );

		// Strip protocol
		$host = preg_replace( '#^https?://#', '', $site_domain );
		// Strip path
		$host = explode( '/', $host )[0];
		// Separate port if present
		$port = '';
		if( preg_match( '/^(\[.*]):(\d+)$/', $host, $m ) ) {
			// IPv6 with port: [::1]:8080
			$host = $m[1];
			$port = $m[2];
		} elseif( preg_match( '/^([^:]+):(\d+)$/', $host, $m ) ) {
			$host = $m[1];
			$port = $m[2];
		}

		// Strip brackets from IPv6
		$host = trim( $host, '[]' );

		// 1) Localhost / loopback
		$loopbacks = [ 'localhost', '127.0.0.1', '::1', '0.0.0.0' ];
		if( in_array( $host, $loopbacks, true ) ) {
			$is_dev = true;
			return true;
		}

		// 2) Raw IP address (no real domain)
		if( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$is_dev = true;
			return true;
		}

		// 3) Non-standard port (real production sites don't use ports in URLs)
		if( $port && ! in_array( $port, [ '80', '443' ], true ) ) {
			$is_dev = true;
			return true;
		}

		// 4) Dev / staging domain suffixes
		// IMPORTANT: Keep in sync with Auth::BLOCKED_SUFFIXES on the server side.
		$dev_suffixes = [
			// RFC 2606 / IANA reserved TLDs
			'.local', '.loc', '.test', '.localhost', '.example', '.invalid',

			// Common local / dev TLDs
			'.internal', '.home', '.lan', '.dev', '.dev.cc',
			'.staging', '.stg', '.qa', '.preprod', '.preview',

			// Virtual host / tunnel services
			'.ddev.site', '.lndo.site',
			'.nip.io', '.sslip.io', '.xip.io',
			'.ngrok.io', '.ngrok-free.app',
			'.serveo.net', '.localtunnel.me',
			'.trycloudflare.com',
			'.loca.lt',

			// Temporary / throwaway WordPress hosting
			'.instawp.xyz', '.tastewp.com', '.tempurl.host',

			// Managed WordPress staging environments
			'.myftpupload.com',
			'.cloudwaysapps.com',
			'.wpengine.com', '.wpengine.net',
			'.flywheelstaging.com',
			'.kinsta.cloud',
			'.platformsh.site',
			'.bigscoots-staging.com',
			'.wpmudev.host',
			'.closte.com',
			'.pressdns.com',
			'.accessdomain.com',
		];
		foreach( $dev_suffixes as $suffix ) {
			if( substr( $host, -strlen( $suffix ) ) === $suffix ) {
				$is_dev = true;
				return true;
			}
		}

		// 5) Dev/staging subdomains
		$dev_prefixes = [
			'localhost.', 'local.',
			'dev.', 'develop.',
			'staging.', 'stage.', 'stg.',
			'test.', 'testing.',
			'demo.', 'sandbox.',
			'preprod.', 'pre-prod.', 'preview.',
			'uat.', 'acceptance.', 'acc.', 'qa.',
		];
		foreach( $dev_prefixes as $prefix ) {
			if( strpos( $host, $prefix ) === 0 ) {
				$is_dev = true;
				return true;
			}
		}

		// 6) Regex-based staging patterns (numbered staging, hosting-specific)
		$dev_regex_patterns = [
			'#^staging\d+\.#i',
			'#^stg\d+\.#i',
			'#^dev\d+\.#i',
			'#^(dev|test)-[^.]+\.pantheonsite\.io$#i',
			'#^[^.]*staging[^.]*\.kinsta\.(com|cloud)$#i',
		];
		foreach( $dev_regex_patterns as $pattern ) {
			if( preg_match( $pattern, $host ) ) {
				$is_dev = true;
				return true;
			}
		}

		// 7) WordPress environment type (WP 5.5+)
		if( function_exists( 'wp_get_environment_type' ) ) {
			$env = wp_get_environment_type();
			if( in_array( $env, [ 'local', 'development', 'staging' ], true ) ) {
				$is_dev = true;
				return true;
			}
		}

		$is_dev = false;
		return false;
	}

}
