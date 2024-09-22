<?php

namespace wpforo\widgets;

use WP_Widget;

class Forums extends WP_Widget {
	/**
	 * @var array
	 */
	private $default_instance;

	function __construct() {
		parent::__construct( 'wpforo_forums', 'wpForo Forums', [ 'description' => 'Forum tree.' ] );
		$this->init_local_vars();
		add_action( 'wp_ajax_wpforo_load_ajax_widget_Forums',        [ $this, 'load_ajax_widget' ] );
		add_action( 'wp_ajax_nopriv_wpforo_load_ajax_widget_Forums', [ $this, 'load_ajax_widget' ] );
	}

	private function init_local_vars() {
		$this->default_instance = [
			'boardid'  => 0,
			'title'    => __( 'Forums', 'wpforo' ),
			'dropdown' => false,
		];
	}

	public function get_widget( $instance ) {
		ob_start();

		if ( wpfval( $instance, 'dropdown' ) ) {
			$forum_urls = [];
			$forums     = array_filter( WPF()->forum->get_forums(), function ( $forum ) {
				return WPF()->perm->forum_can( 'vf', $forum['forumid'] );
			} );
			if ( ! empty( $forums ) ) {
				foreach ( $forums as $forum ) {
					$forum_urls[ 'forum_' . $forum['forumid'] ] = wpforo_home_url( $forum['slug'] );
				}
			}
			if ( ! empty( $forum_urls ) ) {
				echo '<select onchange="window.location.href = wpf_forum_urls[\'forum_\' + this.value]">';
				WPF()->forum->tree( 'select_box', true, WPF()->current_object['forumid'] );
				echo '</select>';
				?>
                <script>
                    var wpf_forum_json = '<?php echo json_encode( $forum_urls ) ?>';
                    var wpf_forum_urls = JSON.parse(wpf_forum_json);
                </script>
				<?php
			}
		} else {
			WPF()->forum->tree( 'front_list', true, WPF()->current_object['forumid'], false );
		}

        return ob_get_clean();
    }

	public function load_ajax_widget() {
		$_POST = wp_unslash( $_POST );
		$instance  = json_decode( (string) wpfval( $_POST, 'instance' ), true );
        wp_send_json_success( [ 'html' => $this->get_widget( $instance ) ] );
    }

	public function widget( $args, $instance ) {
		wp_enqueue_script( 'wpforo-widgets-js' );
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$data = [
			'boardid'  => $instance['boardid'],
			'action'   => 'wpforo_load_ajax_widget_Forums',
			'instance' => $instance,
		];
		if( WPF()->board->get_current( 'boardid' ) === $instance['boardid'] ){
            $html = $this->get_widget( $data['instance'] );
            $onload = false;
		}else{
			$html = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
            $onload = true;
			$data['referer'] = home_url();
		}
		$json = json_encode( $data );
		echo $args['before_widget'] . '<div id="wpf-widget-forums" class="wpforo-widget-wrap">';
		if ( $instance['title'] ) echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		echo '<div class="wpforo-widget-content wpforo-ajax-widget ' . ( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ) . '" data-json="' . esc_attr( $json ) . '">' . $html . '</div></div>' . $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$boardid  = (int) $instance['boardid'];
		$title    = (string) $instance['title'];
		$dropdown = (bool) $instance['dropdown'];
		WPF()->change_board( $boardid );
		?>
        <div class="wpf-wdg-wrapper">
            <p>
                <label><?php _e( 'Title', 'wpforo' ); ?>:</label>
                <label>
                    <input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'boardid' ) ?>"></label>
                <select id="<?php echo $this->get_field_id( 'boardid' ) ?>" name="<?php echo esc_attr( $this->get_field_name( 'boardid' ) ); ?>">
			        <?php echo WPF()->board->dropdown( $boardid ) ?>
                </select>
            </p>
            <p>
                <span><?php _e( 'Display as dropdown', 'wpforo' ); ?> : </span>

                <label for="<?php echo $this->get_field_id( 'dropdown' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'dropdown' ) ?>_1" value="1" <?php checked( $dropdown ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'dropdown' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'dropdown' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'dropdown' ) ?>_0" value="0" <?php checked( $dropdown, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'dropdown' ) ); ?>">
            </p>
        </div>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance         = wpforo_parse_args( $new_instance, $this->default_instance );
		$instance             = [];
		$instance['boardid']  = (int) $new_instance['boardid'];
		$instance['title']    = strip_tags( (string) $new_instance['title'] );
		$instance['dropdown'] = (bool) (int) $new_instance['dropdown'];

		return $instance;
	}
}
