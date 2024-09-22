<?php

namespace wpforo\widgets;

use WP_Widget;

class Tags extends WP_Widget {
	/**
	 * @var array
	 */
	private $default_instance;

	function __construct() {
		parent::__construct( 'wpforo_tags', 'wpForo Topic Tags', [ 'description' => 'List of most popular tags' ] );
		$this->init_local_vars();
		add_action( 'wp_ajax_wpforo_load_ajax_widget_Tags',        [ $this, 'load_ajax_widget' ] );
		add_action( 'wp_ajax_nopriv_wpforo_load_ajax_widget_Tags', [ $this, 'load_ajax_widget' ] );
	}

	private function init_local_vars() {
		$this->default_instance = [
			'title'   => __( 'Topic Tags', 'wpforo' ),
			'boardid' => 0,
			'topics'  => true,
			'count'   => 20,
		];
	}

	public function get_widget( $instance ) {
		$tag_args = [ 'row_count' => (int) wpfval( $instance, 'count' ) ];
		$tags     = WPF()->topic->get_tags( $tag_args, $items_count );
		ob_start();

		if( ! empty( $tags ) ) {
			echo '<ul class="wpf-widget-tags">';
			foreach( $tags as $tag ) {
				$topic_count = ( wpfval( $instance, 'topics' ) ) ? '<span>' . $tag['count'] . '</span>' : '';
				echo '<li><a href="' . esc_url( wpforo_home_url() . '?wpfin=tag&wpfs=' . $tag['tag'] ) . '" title="' . esc_attr( $tag['tag'] ) . '">' . wpforo_text( $tag['tag'], 25, false ) . '</a>' . $topic_count . '</li>';
			}
			echo '</ul>';
			if( wpfval($instance, 'count' ) < $items_count ) {
				echo '<div class="wpf-all-tags"><a href="' . esc_url( wpforo_home_url( wpforo_settings_get_slug( 'tags' ) ) ) . '">' . sprintf( wpforo_phrase( 'View all tags (%d)', false ), $items_count ) . '</a></div>';
			}
		} else {
			echo '<p style="text-align:center">' . wpforo_phrase( 'No tags found', false ) . '</p>';
		}

        return ob_get_clean();
    }

	public function load_ajax_widget() {
		$_POST    = wp_unslash( $_POST );
		$instance = json_decode( (string) wpfval( $_POST, 'instance' ), true );
		wp_send_json_success( [ 'html' => $this->get_widget( $instance ) ] );
	}

	public function widget( $args, $instance ) {
		wp_enqueue_script( 'wpforo-widgets-js' );
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$data = [
			'boardid'  => $instance['boardid'],
			'action'   => 'wpforo_load_ajax_widget_Tags',
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

		echo $args['before_widget'] . '<div id="wpf-widget-tags" class="wpforo-widget-wrap">';
		if( $instance['title'] ) echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		echo '<div class="wpforo-widget-content wpforo-ajax-widget ' . ( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ) . '" data-json="' . esc_attr( $json ) . '">' . $html . '</div></div>' . $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$boardid  = (int) $instance['boardid'];
		$title    = (string) $instance['title'];
		$topics   = (bool) $instance['topics'];
		$count    = (int) $instance['count'];
		WPF()->change_board( $boardid );
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
        <p>
            <span><?php _e( 'Topic Counts', 'wpforo' ); ?> : </span>

            <label for="<?php echo $this->get_field_id( 'topics' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
            <input id="<?php echo $this->get_field_id( 'topics' ) ?>_1" value="1" <?php checked( $topics ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'topics' ) ); ?>">

            <label for="<?php echo $this->get_field_id( 'topics' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
            <input id="<?php echo $this->get_field_id( 'topics' ) ?>_0" value="0" <?php checked( $topics, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'topics' ) ); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'count' ) ?>"><?php _e( 'Number of Items', 'wpforo' ); ?>:</label>&nbsp;
            <input id="<?php echo $this->get_field_id( 'count' ) ?>" type="number" min="1" style="width: 53px;" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" value="<?php echo esc_attr( $count ); ?>">
        </p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance        = wpforo_parse_args( $new_instance, $this->default_instance );
		$instance            = [];
		$instance['boardid'] = (int) $new_instance['boardid'];
		$instance['title']   = strip_tags( (string) $new_instance['title'] );
		$instance['topics']  = (bool) (int) $new_instance['topics'];
		$instance['count']   = (int) $new_instance['count'];

		return $instance;
	}
}
