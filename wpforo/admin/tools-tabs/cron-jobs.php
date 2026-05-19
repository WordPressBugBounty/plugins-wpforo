<?php
/**
 * wpForo Tools — Cron Jobs tab
 *
 * Lists every WP-Cron event registered in this WordPress install with
 * wpForo events ordered first. Each row exposes Run Now and Delete
 * buttons. Helps diagnose `wp_options.cron` bloat and verify whether
 * AI / Email Queue / other wpForo crons are scheduled correctly.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'administrator' ) ) exit;

$wpf_cron_nonce_action = 'wpforo_cron_jobs';

// -----------------------------------------------------------------------
// POST handler — run / delete / run-all-wpforo
// Tools page emits HTML before including this tab file, so a redirect
// here would land after "headers already sent". We process inline,
// add notices via WPF()->notice, and fall through to render — matching
// the existing Email Queue tab pattern. Actions are idempotent
// enough (re-running a cron just fires it again; re-deleting is a
// silent no-op) that a browser refresh re-submitting is safe.
// -----------------------------------------------------------------------
if (
	'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' )
	&& isset( $_POST['wpforo_cron_action'] )
	&& wp_verify_nonce( wpfval( $_POST, '_wpnonce' ), $wpf_cron_nonce_action )
) {
	$action    = sanitize_text_field( wp_unslash( $_POST['wpforo_cron_action'] ) );
	$timestamp = absint( wpfval( $_POST, 'timestamp' ) );
	$hook      = sanitize_text_field( wpfval( $_POST, 'hook' ) );
	$md5       = sanitize_text_field( wpfval( $_POST, 'md5' ) );

	$events = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
	if ( ! is_array( $events ) ) $events = [];

	switch ( $action ) {

		case 'run':
			if ( $timestamp && $hook && $md5 && isset( $events[ $timestamp ][ $hook ][ $md5 ] ) ) {
				$entry    = $events[ $timestamp ][ $hook ][ $md5 ];
				$args     = isset( $entry['args'] ) ? (array) $entry['args'] : [];
				$schedule = $entry['schedule'] ?? false;

				// For single events: unschedule before firing so the queued one
				// doesn't run a second time. For recurring events: leave the
				// schedule alone — the regular cadence resumes after we fire.
				if ( false === $schedule ) {
					wp_unschedule_event( $timestamp, $hook, $args );
				}

				try {
					do_action_ref_array( $hook, $args );
					WPF()->notice->add(
						sprintf(
							/* translators: %s = cron hook name */
							__( 'Cron event "%s" executed.', 'wpforo' ),
							$hook
						),
						'success'
					);
				} catch ( \Throwable $e ) {
					WPF()->notice->add(
						sprintf(
							/* translators: 1: hook name, 2: error message */
							__( 'Cron event "%1$s" raised an exception: %2$s', 'wpforo' ),
							$hook,
							$e->getMessage()
						),
						'error'
					);
				}
			} else {
				WPF()->notice->add(
					__( 'Cron event no longer exists (likely already executed or rescheduled).', 'wpforo' ),
					'warning'
				);
			}
			break;

		case 'delete':
			if ( $timestamp && $hook && $md5 && isset( $events[ $timestamp ][ $hook ][ $md5 ] ) ) {
				$args     = (array) ( $events[ $timestamp ][ $hook ][ $md5 ]['args'] ?? [] );
				$schedule = $events[ $timestamp ][ $hook ][ $md5 ]['schedule'] ?? false;

				wp_unschedule_event( $timestamp, $hook, $args );

				// Recurring event: also clear any future occurrences with the
				// same args so the row truly goes away from the listing.
				if ( false !== $schedule ) {
					wp_clear_scheduled_hook( $hook, $args );
				}

				WPF()->notice->add(
					sprintf(
						/* translators: %s = cron hook name */
						__( 'Cron event "%s" deleted.', 'wpforo' ),
						$hook
					),
					'success'
				);
			}
			break;

		case 'run_all_wpforo':
			$ran = 0;
			foreach ( $events as $ts => $hooks ) {
				if ( ! is_array( $hooks ) ) continue;
				foreach ( $hooks as $h => $items ) {
					if ( strpos( $h, 'wpforo_' ) !== 0 ) continue;
					if ( ! is_array( $items ) ) continue;
					foreach ( $items as $item ) {
						$args     = isset( $item['args'] ) ? (array) $item['args'] : [];
						$schedule = $item['schedule'] ?? false;
						if ( false === $schedule ) {
							wp_unschedule_event( $ts, $h, $args );
						}
						try {
							do_action_ref_array( $h, $args );
							$ran++;
						} catch ( \Throwable $e ) {
							WPF()->notice->add(
								sprintf(
									/* translators: 1: hook name, 2: error message */
									__( 'Cron event "%1$s" raised an exception: %2$s', 'wpforo' ),
									$h,
									$e->getMessage()
								),
								'error'
							);
						}
					}
				}
			}
			WPF()->notice->add(
				sprintf(
					/* translators: %d = number of cron events executed */
					_n( '%d wpForo cron event executed.', '%d wpForo cron events executed.', $ran, 'wpforo' ),
					$ran
				),
				'success'
			);
			break;
	}
}

// -----------------------------------------------------------------------
// Load + normalize + sort cron events
// -----------------------------------------------------------------------
$cron_array = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
if ( ! is_array( $cron_array ) ) $cron_array = [];

$rows         = [];
$wpforo_count = 0;

foreach ( $cron_array as $timestamp => $hooks ) {
	if ( ! is_array( $hooks ) ) continue;
	foreach ( $hooks as $hook => $items ) {
		if ( ! is_array( $items ) ) continue;
		foreach ( $items as $md5 => $entry ) {
			$is_wpforo = ( strpos( $hook, 'wpforo_' ) === 0 );
			if ( $is_wpforo ) $wpforo_count++;
			$rows[] = [
				'timestamp' => (int) $timestamp,
				'hook'      => (string) $hook,
				'md5'       => (string) $md5,
				'args'      => isset( $entry['args'] ) ? (array) $entry['args'] : [],
				'schedule'  => $entry['schedule'] ?? false,
				'interval'  => isset( $entry['interval'] ) ? (int) $entry['interval'] : 0,
				'is_wpforo' => $is_wpforo,
			];
		}
	}
}

// Sort: wpForo first (alphabetical by hook then by timestamp), then everything
// else by next-run time.
usort( $rows, function ( $a, $b ) {
	if ( $a['is_wpforo'] !== $b['is_wpforo'] ) return $a['is_wpforo'] ? -1 : 1;
	if ( $a['is_wpforo'] ) {
		$h = strcmp( $a['hook'], $b['hook'] );
		if ( $h !== 0 ) return $h;
		return $a['timestamp'] <=> $b['timestamp'];
	}
	return $a['timestamp'] <=> $b['timestamp'];
} );

$now           = time();
$total_count   = count( $rows );
$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
$cron_alt      = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;
$schedules     = function_exists( 'wp_get_schedules' ) ? wp_get_schedules() : [];

// Pretty-print interval helper
$wpf_fmt_interval = function ( $seconds ) {
	$seconds = (int) $seconds;
	if ( $seconds <= 0 ) return '—';
	if ( $seconds < HOUR_IN_SECONDS ) {
		$n = max( 1, (int) round( $seconds / MINUTE_IN_SECONDS ) );
		/* translators: %d is the number of minutes */
		return sprintf( _n( 'every %d minute', 'every %d minutes', $n, 'wpforo' ), $n );
	}
	if ( $seconds < DAY_IN_SECONDS ) {
		$n = max( 1, (int) round( $seconds / HOUR_IN_SECONDS ) );
		/* translators: %d is the number of hours */
		return sprintf( _n( 'every %d hour', 'every %d hours', $n, 'wpforo' ), $n );
	}
	if ( $seconds < WEEK_IN_SECONDS ) {
		$n = max( 1, (int) round( $seconds / DAY_IN_SECONDS ) );
		/* translators: %d is the number of days */
		return sprintf( _n( 'every %d day', 'every %d days', $n, 'wpforo' ), $n );
	}
	$n = max( 1, (int) round( $seconds / WEEK_IN_SECONDS ) );
	/* translators: %d is the number of weeks */
	return sprintf( _n( 'every %d week', 'every %d weeks', $n, 'wpforo' ), $n );
};

// Pretty-print relative time helper
$wpf_fmt_when = function ( $ts ) use ( $now ) {
	$diff = $ts - $now;
	if ( $diff < 0 )                 return '<span style="color:#dc3232;font-weight:600;">' . esc_html__( 'past due', 'wpforo' ) . '</span>';
	if ( $diff < MINUTE_IN_SECONDS ) return esc_html__( 'in <1 min', 'wpforo' );
	if ( $diff < HOUR_IN_SECONDS ) {
		$n = (int) round( $diff / MINUTE_IN_SECONDS );
		/* translators: %d is the number of minutes — matches wpforo_ai_format_next_run_time() in ai-features-helpers.php */
		return esc_html( sprintf( _n( 'in %d min', 'in %d mins', $n, 'wpforo' ), $n ) );
	}
	if ( $diff < DAY_IN_SECONDS ) {
		$n = (int) round( $diff / HOUR_IN_SECONDS );
		/* translators: %d is the number of hours */
		return esc_html( sprintf( _n( 'in %d hour', 'in %d hours', $n, 'wpforo' ), $n ) );
	}
	$n = (int) round( $diff / DAY_IN_SECONDS );
	/* translators: %d is the number of days */
	return esc_html( sprintf( _n( 'in %d day', 'in %d days', $n, 'wpforo' ), $n ) );
};
?>

<style>
.wpf-cron-summary {
	display: flex;
	gap: 15px;
	margin: 15px 0;
	flex-wrap: wrap;
	align-items: center;
}
.wpf-cron-summary .wpf-cron-counter {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 8px 18px;
	font-size: 13px;
}
.wpf-cron-summary .wpf-cron-counter strong { font-size: 18px; color: #1d2327; }
.wpf-cron-summary .wpf-cron-counter.wpforo strong { color: #0073aa; }

.wpf-cron-banner {
	padding: 10px 15px;
	border-radius: 4px;
	margin: 10px 0;
}
.wpf-cron-banner.warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
.wpf-cron-banner.ok      { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }

.wpf-cron-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #ccd0d4; }
.wpf-cron-table th, .wpf-cron-table td { padding: 9px 12px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; font-size: 13px; }
.wpf-cron-table th { background: #f6f7f7; font-weight: 600; }
.wpf-cron-table tr.wpforo-row td { background: #f6fbff; }
.wpf-cron-table tr:hover td { background: #f0f6fc; }

.wpf-cron-hook { font-family: Consolas, Monaco, monospace; word-break: break-all; }
.wpf-cron-hook .wpf-cron-badge { background: #0073aa; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-right: 6px; font-family: -apple-system, sans-serif; font-weight: 600; vertical-align: middle; }

.wpf-cron-row-actions { display: inline-flex; gap: 6px; flex-wrap: wrap; }
.wpf-cron-row-actions form { margin: 0; display: inline-block; }
.wpf-cron-row-actions .button { font-size: 11px; height: auto; padding: 3px 9px; line-height: 1.4; }
.wpf-cron-row-actions .button.delete { color: #b32d2e; border-color: #b32d2e; }
.wpf-cron-row-actions .button.delete:hover { background: #b32d2e; color: #fff; }
.wpf-cron-row-actions .button.details { color: #2271b1; border-color: #2271b1; }
.wpf-cron-row-actions .button.details:hover { background: #2271b1; color: #fff; }

/* Details modal */
.wpf-cron-modal-overlay {
	display: none;
	position: fixed;
	inset: 0;
	background: rgba(0,0,0,0.55);
	z-index: 99999;
	align-items: flex-start;
	justify-content: center;
	overflow-y: auto;
	padding: 60px 20px;
}
.wpf-cron-modal-overlay.is-open { display: flex; }
.wpf-cron-modal {
	background: #fff;
	border-radius: 6px;
	max-width: 720px;
	width: 100%;
	box-shadow: 0 12px 32px rgba(0,0,0,0.25);
	max-height: calc(100vh - 120px);
	display: flex;
	flex-direction: column;
}
.wpf-cron-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 18px;
	border-bottom: 1px solid #e5e5e5;
}
.wpf-cron-modal-header h3 { margin: 0; font-size: 16px; }
.wpf-cron-modal-close {
	background: transparent;
	border: 0;
	font-size: 22px;
	line-height: 1;
	cursor: pointer;
	color: #555;
	padding: 4px 8px;
}
.wpf-cron-modal-close:hover { color: #000; }
.wpf-cron-modal-body { padding: 16px 18px; overflow-y: auto; }
.wpf-cron-modal-body dl { margin: 0; }
.wpf-cron-modal-body dt {
	font-weight: 600;
	color: #1d2327;
	margin-top: 12px;
	margin-bottom: 4px;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.wpf-cron-modal-body dt:first-child { margin-top: 0; }
.wpf-cron-modal-body dd { margin: 0; font-size: 13px; word-break: break-all; }
.wpf-cron-modal-body dd code,
.wpf-cron-modal-body dd pre {
	font-family: Consolas, Monaco, monospace;
	font-size: 12px;
}
.wpf-cron-modal-body pre {
	background: #f6f7f7;
	border: 1px solid #e5e5e5;
	border-radius: 3px;
	padding: 10px;
	max-height: 260px;
	overflow: auto;
	margin: 0;
	white-space: pre-wrap;
	word-break: break-word;
}

.wpf-cron-toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
.wpf-cron-empty { padding: 30px; text-align: center; color: #666; background: #fff; border: 1px dashed #ccd0d4; border-radius: 4px; }
</style>

<div style="padding: 1%;">
	<?php
	// Render any notices added by the POST handler above. The tools.php
	// wrapper already called WPF()->notice->show() before this tab was
	// included, so anything we added since needs an explicit second flush
	// here to surface on the current request.
	WPF()->notice->show();
	?>
	<h3 style="margin-top: 0;"><?php _e( 'WP-Cron Jobs', 'wpforo' ); ?></h3>
	<p class="wpf-info">
		<?php _e( 'All scheduled events in this WordPress install. wpForo events are listed first. Use Run Now to fire a single event immediately, or Delete to remove it from the schedule.', 'wpforo' ); ?>
	</p>

	<?php if ( $cron_disabled ) : ?>
		<div class="wpf-cron-banner warning">
			<strong><?php _e( 'WP-Cron is disabled.', 'wpforo' ); ?></strong>
			<?php _e( '<code>DISABLE_WP_CRON</code> is set. Events stay scheduled but will not fire automatically — you need to trigger wp-cron.php via a system cron or call it manually.', 'wpforo' ); ?>
		</div>
	<?php elseif ( $cron_alt ) : ?>
		<div class="wpf-cron-banner warning">
			<strong><?php _e( 'Alternate WP-Cron in use.', 'wpforo' ); ?></strong>
			<?php _e( '<code>ALTERNATE_WP_CRON</code> is set; events fire via redirect rather than WordPress\'s normal background spawn.', 'wpforo' ); ?>
		</div>
	<?php else : ?>
		<div class="wpf-cron-banner ok">
			<span class="dashicons dashicons-yes-alt" style="vertical-align: text-bottom;"></span>
			<?php _e( 'WP-Cron is active. Events fire on the next page load past their scheduled time.', 'wpforo' ); ?>
		</div>
	<?php endif; ?>

	<div class="wpf-cron-summary">
		<div class="wpf-cron-counter">
			<?php printf( __( '<strong>%d</strong> total scheduled events', 'wpforo' ), $total_count ); ?>
		</div>
		<div class="wpf-cron-counter wpforo">
			<?php printf( __( '<strong>%d</strong> from wpForo', 'wpforo' ), $wpforo_count ); ?>
		</div>
	</div>

	<?php if ( $wpforo_count > 0 ) : ?>
		<div class="wpf-cron-toolbar">
			<form method="post" style="margin: 0;">
				<?php wp_nonce_field( $wpf_cron_nonce_action ); ?>
				<input type="hidden" name="wpforo_cron_action" value="run_all_wpforo">
				<button type="submit" class="button button-secondary"
				        onclick="return confirm('<?php echo esc_js( __( 'Run every scheduled wpForo cron event now? Long-running events may delay this admin page.', 'wpforo' ) ); ?>');">
					<span class="dashicons dashicons-controls-play" style="vertical-align: text-bottom;"></span>
					<?php _e( 'Run All wpForo Crons Now', 'wpforo' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( empty( $rows ) ) : ?>
		<div class="wpf-cron-empty">
			<?php _e( 'No scheduled events.', 'wpforo' ); ?>
		</div>
	<?php else : ?>
		<table class="wpf-cron-table widefat striped">
			<thead>
				<tr>
					<th><?php _e( 'Hook', 'wpforo' ); ?></th>
					<th style="width: 18%;"><?php _e( 'Next run', 'wpforo' ); ?></th>
					<th style="width: 14%;"><?php _e( 'Schedule', 'wpforo' ); ?></th>
					<th style="width: 13%;"><?php _e( 'Interval', 'wpforo' ); ?></th>
					<th style="width: 240px;"><?php _e( 'Actions', 'wpforo' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) :
				// Schedule label: prefer the human-readable `display` field from
				// wp_get_schedules() over the raw key. Some plugins (e.g. wpDiscuz
				// uses `wpdiscuz_generate_thumbnails_every_3h`) namespace their
				// schedule keys, so the display field is much more useful in the
				// table cell.
				$has_display  = $row['schedule']
					&& isset( $schedules[ $row['schedule'] ]['display'] )
					&& $schedules[ $row['schedule'] ]['display'] !== '';
				$schedule_lbl = $has_display
					? $schedules[ $row['schedule'] ]['display']
					: ( $row['schedule'] ? $row['schedule'] : __( 'single event', 'wpforo' ) );
				// Tooltip carries the raw key when a display was used, so it's
				// still discoverable on hover.
				$schedule_h   = $has_display ? $row['schedule'] : $schedule_lbl;
				$interval_lbl = $row['interval'] > 0 ? $wpf_fmt_interval( $row['interval'] ) : '—';
				$next_abs     = wp_date( 'Y-m-d H:i:s', $row['timestamp'] );
				$row_class    = $row['is_wpforo'] ? 'wpforo-row' : '';
				?>
				<tr class="<?php echo esc_attr( $row_class ); ?>">
					<td class="wpf-cron-hook">
						<?php if ( $row['is_wpforo'] ) : ?>
							<span class="wpf-cron-badge">wpForo</span>
						<?php endif; ?>
						<strong><?php echo esc_html( $row['hook'] ); ?></strong>
					</td>
					<td>
						<div><?php echo $wpf_fmt_when( $row['timestamp'] ); ?></div>
						<div style="color:#888;font-size:11px;"><?php echo esc_html( $next_abs ); ?></div>
					</td>
					<td>
						<span title="<?php echo esc_attr( $schedule_h ); ?>">
							<?php echo esc_html( $schedule_lbl ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $interval_lbl ); ?></td>
					<td>
						<div class="wpf-cron-row-actions">
							<?php
							// Build the full attribute payload for the Details modal.
							// Pretty-print args JSON server-side so we don't depend on
							// JSON.stringify formatting in JS.
							$details_args_pretty = ( ! empty( $row['args'] ) )
								? wp_json_encode( $row['args'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
								: __( '(no arguments)', 'wpforo' );
							$details_payload = [
								'hook'         => $row['hook'],
								'source'       => $row['is_wpforo'] ? 'wpForo' : __( 'Other / WordPress core', 'wpforo' ),
								'timestamp'    => (int) $row['timestamp'],
								'next_local'   => wp_date( 'Y-m-d H:i:s T', $row['timestamp'] ),
								'next_gmt'     => gmdate( 'Y-m-d H:i:s', $row['timestamp'] ) . ' GMT',
								'next_rel'     => wp_strip_all_tags( $wpf_fmt_when( $row['timestamp'] ) ),
								// Schedule: raw key + human-readable display (when registered)
								'schedule'     => $row['schedule'] ? $row['schedule'] : 'single_event',
								'schedule_lbl' => $has_display ? $schedules[ $row['schedule'] ]['display'] : '',
								'interval'     => $row['interval'],
								'interval_lbl' => $interval_lbl,
								'args'         => $details_args_pretty,
								'md5'          => $row['md5'],
							];
							?>
							<button type="button" class="button details wpf-cron-details-btn"
							        data-payload="<?php echo esc_attr( wp_json_encode( $details_payload ) ); ?>"
							        title="<?php esc_attr_e( 'View full cron event attributes', 'wpforo' ); ?>">
								<?php _e( 'Details', 'wpforo' ); ?>
							</button>
							<form method="post">
								<?php wp_nonce_field( $wpf_cron_nonce_action ); ?>
								<input type="hidden" name="wpforo_cron_action" value="run">
								<input type="hidden" name="timestamp" value="<?php echo (int) $row['timestamp']; ?>">
								<input type="hidden" name="hook"      value="<?php echo esc_attr( $row['hook'] ); ?>">
								<input type="hidden" name="md5"       value="<?php echo esc_attr( $row['md5'] ); ?>">
								<button type="submit" class="button" title="<?php esc_attr_e( 'Fire this event now', 'wpforo' ); ?>">
									<?php _e( 'Run Now', 'wpforo' ); ?>
								</button>
							</form>
							<form method="post"
							      onsubmit="return confirm('<?php echo esc_js( sprintf( __( 'Delete the scheduled event \"%s\"? This removes it from WP-Cron immediately.', 'wpforo' ), $row['hook'] ) ); ?>');">
								<?php wp_nonce_field( $wpf_cron_nonce_action ); ?>
								<input type="hidden" name="wpforo_cron_action" value="delete">
								<input type="hidden" name="timestamp" value="<?php echo (int) $row['timestamp']; ?>">
								<input type="hidden" name="hook"      value="<?php echo esc_attr( $row['hook'] ); ?>">
								<input type="hidden" name="md5"       value="<?php echo esc_attr( $row['md5'] ); ?>">
								<button type="submit" class="button delete" title="<?php esc_attr_e( 'Remove this event from the schedule', 'wpforo' ); ?>">
									<?php _e( 'Delete', 'wpforo' ); ?>
								</button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<!-- Details modal (shared, populated on click) -->
	<div id="wpf-cron-modal-overlay" class="wpf-cron-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="wpf-cron-modal-title" aria-hidden="true">
		<div class="wpf-cron-modal">
			<div class="wpf-cron-modal-header">
				<h3 id="wpf-cron-modal-title"><?php _e( 'Cron event details', 'wpforo' ); ?></h3>
				<button type="button" class="wpf-cron-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wpforo' ); ?>">&times;</button>
			</div>
			<div class="wpf-cron-modal-body">
				<dl>
					<dt><?php _e( 'Hook', 'wpforo' ); ?></dt>
					<dd><code data-field="hook"></code></dd>

					<dt><?php _e( 'Source', 'wpforo' ); ?></dt>
					<dd data-field="source"></dd>

					<dt><?php _e( 'Next run (site time)', 'wpforo' ); ?></dt>
					<dd data-field="next_local"></dd>

					<dt><?php _e( 'Next run (GMT)', 'wpforo' ); ?></dt>
					<dd data-field="next_gmt"></dd>

					<dt><?php _e( 'Relative', 'wpforo' ); ?></dt>
					<dd data-field="next_rel"></dd>

					<dt><?php _e( 'Schedule', 'wpforo' ); ?></dt>
					<dd>
						<code data-field="schedule"></code>
						<span data-field="schedule_lbl" style="color:#666;margin-left:6px;"></span>
					</dd>

					<dt><?php _e( 'Interval', 'wpforo' ); ?></dt>
					<dd>
						<span data-field="interval_lbl"></span>
						<span data-field="interval" style="color:#666;"></span>
					</dd>

					<dt><?php _e( 'Arguments', 'wpforo' ); ?></dt>
					<dd><pre data-field="args"></pre></dd>

					<dt><?php _e( 'Signature (md5)', 'wpforo' ); ?></dt>
					<dd><code data-field="md5"></code></dd>

					<dt><?php _e( 'Unix timestamp', 'wpforo' ); ?></dt>
					<dd><code data-field="timestamp"></code></dd>
				</dl>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	var overlay = document.getElementById( 'wpf-cron-modal-overlay' );
	if ( ! overlay ) return;

	function setField( name, value ) {
		var els = overlay.querySelectorAll( '[data-field="' + name + '"]' );
		for ( var i = 0; i < els.length; i++ ) {
			els[ i ].textContent = ( value === null || typeof value === 'undefined' ) ? '' : String( value );
		}
	}

	function openModal( payload ) {
		var formattedInterval = '';
		if ( payload.interval && payload.interval > 0 ) {
			formattedInterval = ' (' + payload.interval + 's)';
		}
		setField( 'hook',         payload.hook );
		setField( 'source',       payload.source );
		setField( 'next_local',   payload.next_local );
		setField( 'next_gmt',     payload.next_gmt );
		setField( 'next_rel',     payload.next_rel );
		setField( 'schedule',     payload.schedule );
		setField( 'schedule_lbl', payload.schedule_lbl );
		setField( 'interval_lbl', payload.interval_lbl );
		setField( 'interval',     formattedInterval );
		setField( 'args',         payload.args );
		setField( 'md5',          payload.md5 );
		setField( 'timestamp',    payload.timestamp );
		overlay.classList.add( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'false' );
	}

	function closeModal() {
		overlay.classList.remove( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'true' );
	}

	// Open on Details click (event delegation works even after future renders)
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.wpf-cron-details-btn' ) : null;
		if ( ! btn ) return;
		e.preventDefault();
		var raw = btn.getAttribute( 'data-payload' );
		if ( ! raw ) return;
		try {
			openModal( JSON.parse( raw ) );
		} catch ( err ) {
			console.error( 'wpForo cron details: invalid payload', err );
		}
	} );

	// Close on overlay click (outside the white card), the X button, or Escape
	overlay.addEventListener( 'click', function ( e ) {
		if ( e.target === overlay ) closeModal();
	} );
	var closeBtn = overlay.querySelector( '.wpf-cron-modal-close' );
	if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && overlay.classList.contains( 'is-open' ) ) closeModal();
	} );
})();
</script>
