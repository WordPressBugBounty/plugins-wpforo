<?php

namespace wpforo\widgets;

use WP_Widget;

class Search extends WP_Widget {
	/**
	 * @var array
	 */
	private $default_instance;

	function __construct() {
		parent::__construct( 'wpforo_search', 'wpForo Search', [ 'description' => 'wpForo search form' ] );
		$this->init_local_vars();
		add_action( 'wp_ajax_wpforo_load_ajax_widget_Search',        [ $this, 'load_ajax_widget' ] );
		add_action( 'wp_ajax_nopriv_wpforo_load_ajax_widget_Search', [ $this, 'load_ajax_widget' ] );
	}

	private function init_local_vars() {
		$this->default_instance = [
			'title'   => __( 'Forum Search', 'wpforo' ),
			'boardid' => 0,
		];
	}

	public function get_widget() {
		ob_start(); ?>

        <form action="<?php echo wpforo_home_url() ?>" method="GET" id="wpforo-search-form">
			<?php wpforo_make_hidden_fields_from_url( wpforo_home_url() ) ?>
            <label class="wpf-search-widget-label">
                <input type="text" placeholder="<?php wpforo_phrase( 'Search...' ) ?>" name="wpfs" class="wpfw-100" value="<?php echo isset( $_GET['wpfs'] ) ? esc_attr( sanitize_text_field( $_GET['wpfs'] ) ) : '' ?>">
                <svg onclick="this.closest('form').submit();" viewBox="0 0 16 16" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><g id="Guide"/><g id="Layer_2"><path d="M13.85,13.15l-2.69-2.69c0.74-0.9,1.2-2.03,1.2-3.28C12.37,4.33,10.04,2,7.18,2S2,4.33,2,7.18s2.33,5.18,5.18,5.18   c1.25,0,2.38-0.46,3.28-1.2l2.69,2.69c0.1,0.1,0.23,0.15,0.35,0.15s0.26-0.05,0.35-0.15C14.05,13.66,14.05,13.34,13.85,13.15z    M3,7.18C3,4.88,4.88,3,7.18,3s4.18,1.88,4.18,4.18s-1.88,4.18-4.18,4.18S3,9.49,3,7.18z"/></g></svg>
            </label>
        </form>

		<?php

        return  ob_get_clean();
    }

	public function load_ajax_widget() {
		wp_send_json_success( [ 'html' => $this->get_widget() ] );
	}

	public function widget( $args, $instance ) {
		wp_enqueue_script( 'wpforo-widgets-js' );
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$data = [
			'boardid'  => $instance['boardid'],
			'action'   => 'wpforo_load_ajax_widget_Search',
		];

		if( WPF()->board->get_current( 'boardid' ) === $instance['boardid'] ){
            $html = $this->get_widget();
            $onload = false;
		}else{
			$html = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
            $onload = true;
			$data['referer'] = home_url();
		}
		$json = json_encode( $data );

		echo $args['before_widget'] . '<div id="wpf-widget-search" class="wpforo-widget-wrap">';
		if( $instance['title'] ) echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		echo '<div class="wpforo-widget-content wpforo-ajax-widget ' . ( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ) . '" data-json="' . esc_attr( $json ) . '">' . $html . '</div></div>' . $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$boardid  = (int) $instance['boardid'];
		$title    = (string) $instance['title'];
		?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Title', 'wpforo' ); ?>:</label>
            <input id="<?php echo $this->get_field_id( 'title' ) ?>" class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'boardid' ) ?>"></label>
            <select id="<?php echo $this->get_field_id( 'boardid' ) ?>" name="<?php echo esc_attr( $this->get_field_name( 'boardid' ) ); ?>">
				<?php echo WPF()->board->dropdown( $boardid ) ?>
            </select>
        </p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance        = wpforo_parse_args( $new_instance, $this->default_instance );
		$instance            = [];
		$instance['boardid'] = (int) $new_instance['boardid'];
		$instance['title']   = strip_tags( (string) $new_instance['title'] );

		return $instance;
	}
}
