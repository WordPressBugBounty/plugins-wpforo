<?php

namespace wpforo\classes;

if( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PluginsOrdering {
	/**
	 * wpForo plugin basename.
	 *
	 * @var string
	 */
	private $wpforo;
	
	/**
	 * Query Monitor plugin basename.
	 *
	 * @var string
	 */
	private $query_monitor = 'query-monitor/query-monitor.php';
	
	/**
	 * Constructor.
	 */
	public function __construct( string $wpforo = 'wpforo/wpforo.php' ) {
		$this->wpforo = $wpforo;
		
		// Ensure correct ordering when plugins are updated.
		add_filter(
			'pre_update_option_active_plugins',
			[ $this, 'filter_active_plugins' ],
			9999
		);
		
		// Ensure correct ordering for network-activated plugins.
		add_filter(
			'pre_update_site_option_active_sitewide_plugins',
			[ $this, 'filter_active_sitewide_plugins' ],
			9999
		);
		
		// Reorder plugins after updates.
		add_action(
			'upgrader_process_complete',
			[ $this, 'reorder_after_update' ],
			9999,
			2
		);
	}
	
	public function filter_active_plugins( $plugins ) {
		if( empty( $plugins ) || ! is_array( $plugins ) ) {
			return $plugins;
		}
		
		return $this->reorder_plugins( $plugins );
	}
	
	/**
	 * Reorder plugins ensuring Query Monitor is first and wpForo second.
	 */
	private function reorder_plugins( $plugins ): array {
		$priority = [];
		
		if( in_array( $this->query_monitor, $plugins, true ) ) {
			$priority[] = $this->query_monitor;
		}
		
		if( in_array( $this->wpforo, $plugins, true ) ) {
			$priority[] = $this->wpforo;
		}
		
		$remaining = array_diff( $plugins, $priority );
		
		return array_values( array_merge( $priority, $remaining ) );
	}
	
	/**
	 * Reorder plugins after wpForo is updated.
	 */
	public function reorder_after_update( $upgrader, $options ) {
		if( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		
		if( empty( $options['action'] ) || 'update' !== $options['action'] ) {
			return;
		}
		
		if( empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}
		
		if( ! in_array( $this->wpforo, $options['plugins'], true ) ) {
			return;
		}
		
		// Reorder single-site plugins.
		$active_plugins = get_option( 'active_plugins', [] );
		update_option(
			'active_plugins',
			$this->reorder_plugins( $active_plugins )
		);
		
		// Reorder multisite plugins.
		if( is_multisite() ) {
			$network_plugins = get_site_option(
				'active_sitewide_plugins',
				[]
			);
			
			update_site_option(
				'active_sitewide_plugins',
				$this->filter_active_sitewide_plugins( $network_plugins )
			);
		}
	}
	
	/**
	 * Reorder network active plugins for multisite installations.
	 */
	public function filter_active_sitewide_plugins( $plugins ) {
		if( empty( $plugins ) || ! is_array( $plugins ) ) {
			return $plugins;
		}
		
		$priority = [];
		
		if( isset( $plugins[ $this->query_monitor ] ) ) {
			$priority[ $this->query_monitor ] = $plugins[ $this->query_monitor ];
			unset( $plugins[ $this->query_monitor ] );
		}
		
		if( isset( $plugins[ $this->wpforo ] ) ) {
			$priority[ $this->wpforo ] = $plugins[ $this->wpforo ];
			unset( $plugins[ $this->wpforo ] );
		}
		
		return $priority + $plugins;
	}
}
