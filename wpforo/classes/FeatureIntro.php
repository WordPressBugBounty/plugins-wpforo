<?php

namespace wpforo\classes;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

class FeatureIntro {

	/**
	 * Current intro version - bump this when adding new feature intro content.
	 * Must match a key in get_intros().
     *
     * Add a new entry in get_intros():
     *
     * '3.0.0' => [ 'slides' => [ ... ] ],  // existing
     * '3.1.0' => [ 'slides' => [ ... ] ],  // new
     * Update the constant:
     *
     * const CURRENT_INTRO = '3.1.0';
	 */
	const CURRENT_INTRO = '3.0.0';

	const META_KEY       = 'wpforo_feature_intro_dismissed';
	const OPT_OUT_KEY    = 'wpforo_feature_intro_opt_out';
	const VERSION_OPTION = 'wpforo_feature_intro_version';

	private $intros = [];

	public function __construct() {
		$this->intros = $this->get_intros();

		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'maybe_render_modal' ] );
	}

	/**
	 * Define all feature introductions.
	 * To add a new intro for a future version, add a new key here.
	 */
	private function get_intros() {
		return [
			'3.0.0' => [
				'slides' => [
					[
						'title'       => __( "What's New in wpForo 3.0 - AI Edition", 'wpforo' ),
						'description' => __( 'A major update with a reimagined modern AI-driven discussion system.', 'wpforo' ),
						'image'       => 'whats-new-page.png',
						'features'    => [
							__( '"What\' New" page. Timeline-based activity feed showing all forum actions: new topics, replies, likes, dislikes and more...', 'wpforo' ),
							__( 'Refreshed new theme with the brand new Boxed Layout for a clean, card-based forum experience.', 'wpforo' ),
							__( 'Using Forum as Support Tickets Board. Turns any forum into a private support ticket system.', 'wpforo' ),
							__( '99.9% Spam Protection with New user control, AI antispam, flood detection and reCAPTCHA v3.', 'wpforo' ),
							__( 'Block Widgets replacing the legacy widgets.', 'wpforo' ),
							__( '360° powered AI features shown next &#8594;', 'wpforo' ),
						],
					],
					[
						'title'       => __( 'New Boxed Forum Layout', 'wpforo' ),
						'description' => __( 'The fifth forum layout with a modern boxed card design. All five layouts have been refreshed with a clean, contemporary look.', 'wpforo' ),
						'image'       => 'boxed-forum-layout.png',
						'tip'         => __( 'Go to <strong>wpForo &rarr; Forums</strong> to change the layout of any forum.', 'wpforo' ),
					],
					[
						'title'       => __( '360° AI-Powered Features', 'wpforo' ),
						'description' => __( '20+ AI Features are added! wpForo 3.0 introduces revolutionary AI features that make your forum smarter, safer, and more engaging — while keeping you in full control of every feature.', 'wpforo' ),
						'image'       => 'ai-search-widget.png',
						'features'    => [
							__( 'AI Semantic Search, finds topics by meaning', 'wpforo' ),
							__( 'AI Translation, 100+ languages, real-time', 'wpforo' ),
							__( 'AI Topic Summary, instant discussion overviews', 'wpforo' ),
							__( 'AI Chat Assistant, 24/7 forum chatbot', 'wpforo' ),
							__( 'AI Content Moderation, spam and toxicity detection.', 'wpforo' ),
							__( 'AI Topic Suggestions, smart recommendations', 'wpforo' ),
						],
					],
					[
						'title'       => __( 'Connect to AI Service', 'wpforo' ),
						'description' => __( 'Get started with one click &mdash; no configuration needed.', 'wpforo' ),
						'image'       => 'connect-to-service.png',
						'steps'       => [
							__( 'Go to <strong>wpForo &rarr; AI Features</strong>', 'wpforo' ),
							__( 'Check the Terms of Service checkbox', 'wpforo' ),
							__( 'Click <strong>"Generate API Key & Connect"</strong>', 'wpforo' ),
						],
						'highlight'   => __( 'Get started in one click for FREE!', 'wpforo' ),
					],
					[
						'title'       => __( 'You\'re in Control', 'wpforo' ),
						'description' => __( 'Every AI feature can be independently enabled or disabled. Only spend credits on what you use. Start with just search and add more features anytime.', 'wpforo' ),
						'image'       => 'ai-settings.png',
						'tip'         => __( 'Go to <strong>wpForo &rarr; Settings &rarr; AI Features</strong> to toggle each feature on or off.', 'wpforo' ),
					],
				],
			],
		];
	}

	/**
	 * Should the intro modal be shown to the current user?
	 */
	public function should_show() {
		if( ! current_user_can( 'manage_options' ) ) return false;
		if( ! $this->is_wpforo_admin_page() ) return false;
		if( $this->is_user_opted_out() ) return false;
		if( $this->is_dismissed( self::CURRENT_INTRO ) ) return false;
		if( ! isset( $this->intros[ self::CURRENT_INTRO ] ) ) return false;

		return true;
	}

	/**
	 * Check if we're on a wpForo admin page.
	 */
	private function is_wpforo_admin_page() {
		if( ! function_exists( 'get_current_screen' ) ) return false;

		$screen = get_current_screen();
		if( ! $screen ) return false;

		return strpos( $screen->id, 'wpforo' ) !== false;
	}

	/**
	 * Check if user permanently opted out of all intros.
	 */
	private function is_user_opted_out() {
		return (bool) get_user_meta( get_current_user_id(), self::OPT_OUT_KEY, true );
	}

	/**
	 * Check if user dismissed a specific intro version.
	 */
	private function is_dismissed( $version ) {
		$dismissed = get_user_meta( get_current_user_id(), self::META_KEY, true );
		if( ! is_array( $dismissed ) ) $dismissed = [];

		return isset( $dismissed[ $version ] );
	}

	/**
	 * Enqueue CSS and JS for the intro modal.
	 */
	public function maybe_enqueue_assets() {
		if( ! $this->should_show() ) return;

		wp_enqueue_style(
			'wpforo-feature-intro',
			WPFORO_URL . '/admin/assets/css/feature-intro.css',
			[],
			WPFORO_VERSION
		);

		wp_enqueue_script(
			'wpforo-feature-intro',
			WPFORO_URL . '/admin/assets/js/feature-intro.js',
			[ 'jquery' ],
			WPFORO_VERSION,
			true
		);

		wp_localize_script( 'wpforo-feature-intro', 'wpforoFeatureIntro', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'wpforo_feature_intro' ),
			'version'       => self::CURRENT_INTRO,
			'connectUrl'    => admin_url( 'admin.php?page=wpforo-ai' ),
			'totalSlides'   => count( $this->intros[ self::CURRENT_INTRO ]['slides'] ),
		] );
	}

	/**
	 * Render the modal HTML in admin footer.
	 */
	public function maybe_render_modal() {
		if( ! $this->should_show() ) return;

		$intro  = $this->intros[ self::CURRENT_INTRO ];
		$slides = $intro['slides'];
		$img_url = WPFORO_URL . '/assets/images/intro/';
		?>
		<div id="wpforo-feature-intro-overlay" class="wpf-fi-overlay">
			<div class="wpf-fi-modal">
				<button type="button" class="wpf-fi-close" title="<?php esc_attr_e( 'Close', 'wpforo' ); ?>">&times;</button>

				<div class="wpf-fi-slides-wrap">
					<?php foreach( $slides as $i => $slide ) : ?>
						<div class="wpf-fi-slide<?php echo $i === 0 ? ' wpf-fi-active' : ''; ?>" data-slide="<?php echo (int) $i; ?>">

							<?php if( ! empty( $slide['image'] ) ) : ?>
								<div class="wpf-fi-image-wrap">
									<img src="<?php echo esc_url( $img_url . $slide['image'] ); ?>"
									     alt="<?php echo esc_attr( wp_strip_all_tags( $slide['title'] ) ); ?>" />
								</div>
							<?php endif; ?>

							<div class="wpf-fi-content">
								<h2 class="wpf-fi-title"><?php echo esc_html( $slide['title'] ); ?></h2>
								<p class="wpf-fi-desc"><?php echo wp_kses_post( $slide['description'] ); ?></p>

								<?php if( ! empty( $slide['features'] ) ) : ?>
									<ul class="wpf-fi-features">
										<?php foreach( $slide['features'] as $feature ) : ?>
											<li><?php echo wp_kses_post( $feature ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>

								<?php if( ! empty( $slide['steps'] ) ) : ?>
									<ol class="wpf-fi-steps">
										<?php foreach( $slide['steps'] as $step ) : ?>
											<li><?php echo wp_kses_post( $step ); ?></li>
										<?php endforeach; ?>
									</ol>
								<?php endif; ?>

								<?php if( ! empty( $slide['highlight'] ) ) : ?>
									<div class="wpf-fi-highlight"><?php echo wp_kses_post( $slide['highlight'] ); ?></div>
								<?php endif; ?>

								<?php if( ! empty( $slide['tip'] ) ) : ?>
									<div class="wpf-fi-tip"><?php echo wp_kses_post( $slide['tip'] ); ?></div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="wpf-fi-nav">
					<button type="button" class="wpf-fi-arrow wpf-fi-prev" title="<?php esc_attr_e( 'Previous', 'wpforo' ); ?>">&lsaquo;</button>
					<div class="wpf-fi-dots">
						<?php for( $i = 0; $i < count( $slides ); $i++ ) : ?>
							<span class="wpf-fi-dot<?php echo $i === 0 ? ' wpf-fi-active' : ''; ?>" data-slide="<?php echo (int) $i; ?>"></span>
						<?php endfor; ?>
					</div>
					<button type="button" class="wpf-fi-arrow wpf-fi-next" title="<?php esc_attr_e( 'Next', 'wpforo' ); ?>">&rsaquo;</button>
				</div>

				<div class="wpf-fi-actions">
					<div class="wpf-fi-actions-left">
						<label class="wpf-fi-opt-out">
							<input type="checkbox" id="wpf-fi-opt-out" />
							<?php _e( "Don't show introductions for new features", 'wpforo' ); ?>
						</label>
					</div>
					<div class="wpf-fi-actions-right">
						<button type="button" class="wpf-fi-btn wpf-fi-btn-secondary wpf-fi-dismiss">
							<?php _e( 'Maybe Later', 'wpforo' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforo-ai' ) ); ?>" class="wpf-fi-btn wpf-fi-btn-primary wpf-fi-connect">
							<?php _e( 'Activate AI Features', 'wpforo' ); ?> &rarr;
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: dismiss the intro for the current user.
	 * Instance method (called when class is instantiated in admin context).
	 */
	public function ajax_dismiss() {
		self::ajax_dismiss_static();
	}

	/**
	 * Static AJAX handler: dismiss the intro for the current user.
	 * Called from includes/hooks.php during AJAX requests (admin/index.php is not loaded for AJAX).
	 */
	public static function ajax_dismiss_static() {
		check_ajax_referer( 'wpforo_feature_intro', '_wpnonce' );

		if( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : self::CURRENT_INTRO;
		$opt_out = isset( $_POST['opt_out'] ) && $_POST['opt_out'] === '1';

		// Mark this version as dismissed
		$dismissed = get_user_meta( get_current_user_id(), self::META_KEY, true );
		if( ! is_array( $dismissed ) ) $dismissed = [];
		$dismissed[ $version ] = time();
		update_user_meta( get_current_user_id(), self::META_KEY, $dismissed );

		// Handle permanent opt-out
		if( $opt_out ) {
			update_user_meta( get_current_user_id(), self::OPT_OUT_KEY, true );
		}

		wp_send_json_success();
	}
}
