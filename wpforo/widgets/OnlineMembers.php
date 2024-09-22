<?php

namespace wpforo\widgets;

use WP_Widget;

class OnlineMembers extends WP_Widget {
	/**
	 * @var array
	 */
	private $default_instance;

	function __construct() {
		parent::__construct( 'wpforo_online_members', 'wpForo Online Members', [ 'description' => 'Online members.' ] );
		$this->init_local_vars();
		add_action( 'wp_ajax_wpforo_load_ajax_widget_OnlineMembers',        [ $this, 'load_ajax_widget' ] );
		add_action( 'wp_ajax_nopriv_wpforo_load_ajax_widget_OnlineMembers', [ $this, 'load_ajax_widget' ] );
	}

	private function init_local_vars() {
		$this->default_instance = [
			'title'            => __( 'Online Members', 'wpforo' ),
			'count'            => 15,
			'display_avatar'   => true,
			'groupids'         => WPF()->usergroup->get_visible_usergroup_ids(),
			'refresh_interval' => 0,
		];
	}

	public function get_widget( $instance ) {
		$online_members = WPF()->member->get_online_members( $instance['count'], $instance['groupids'] );
		ob_start();
		if( ! empty( $online_members ) ) {
			echo '<ul>
					 <li>
						<div class="wpforo-list-item">';
                            foreach( $online_members as $member ) {
                                if( $instance['display_avatar'] ): ?>
	                                <?php wpforo_member_link( $member, '', 96, 'onlineavatar', true, 'avatar', 'style="width:95%;" class="avatar"' ) ?>
                                <?php else: ?>
                                    <?php wpforo_member_link( $member, '', 30, 'onlineuser' ) ?>
                                <?php endif; ?>
                                <?php
                            }
			                echo '<div class="wpf-clear"></div>
                        </div>
                    </li>
                </ul>';
		} else {
			echo '<p class="wpf-widget-note">&nbsp;' . wpforo_phrase( 'No online members at the moment', false ) . '</p>';
		}

        return ob_get_clean();
    }

	public function load_ajax_widget() {
		$_POST = wp_unslash( $_POST );
		$instance  = json_decode( (string) wpfval( $_POST, 'instance' ), true );
        wp_send_json_success( ['html' => $this->get_widget( $instance )] );
	}

	public function widget( $args, $instance ) {
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		$data = [
			'boardid'  => 0,
			'action'   => 'wpforo_load_ajax_widget_OnlineMembers',
			'instance' => $instance,
		];
		if( WPF()->board->get_current( 'boardid' ) !== 0 ) $data['referer'] = home_url();
		$html = $this->get_widget( $data['instance'] );
        $json = json_encode( $data );
		wp_enqueue_script( 'wpforo-widgets-js' );
		echo $args['before_widget'] . '<div id="wpf-widget-online-users" class="wpforo-widget-wrap">';
		if( $instance['title'] ) echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		echo '<div class="wpforo-widget-content wpforo-ajax-widget wpforo-ajax-widget-onload-false" data-json="' . esc_attr( $json ) . '">' . $html . '</div></div>' . $args['after_widget'];
	}

	public function form( $instance ) {
		$instance         = wpforo_parse_args( $instance, $this->default_instance );
		$title            = (string) $instance['title'];
		$count            = (int) $instance['count'];
		$display_avatar   = (bool) $instance['display_avatar'];
		$groupids         = array_filter( wpforo_parse_args( ( wpforo_is_json( $instance['groupids'] ) ? json_decode( $instance['groupids'], true ) : $instance['groupids'] ) ) );
		$refresh_interval = (int) $instance['refresh_interval'];
		?>
        <div class="wpf-wdg-wrapper">
            <p>
                <label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Title', 'wpforo' ); ?>:</label>
                <input id="<?php echo $this->get_field_id( 'title' ) ?>" class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'groupids' ) ?>"><?php _e( 'User Groups', 'wpforo' ); ?></label>&nbsp;
                <select id="<?php echo $this->get_field_id( 'groupids' ) ?>" name="<?php echo esc_attr( $this->get_field_name( 'groupids' ) ); ?>[]" multiple required>
		            <?php WPF()->usergroup->show_selectbox( $groupids, 4 ) ?>
                </select>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'count' ) ?>"><?php _e( 'Number of Items', 'wpforo' ); ?></label>&nbsp;
                <input id="<?php echo $this->get_field_id( 'count' ) ?>" type="number" min="1" style="width: 53px;" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" value="<?php echo esc_attr( $count ); ?>">
            </p>
            <p>
                <span><?php _e( 'Display with avatars', 'wpforo' ); ?> : </span>

                <label for="<?php echo $this->get_field_id( 'display_avatar' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_avatar' ) ?>_1" value="1" <?php checked( $display_avatar ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_avatar' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'display_avatar' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_avatar' ) ?>_0" value="0" <?php checked( $display_avatar, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_avatar' ) ); ?>">
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'refresh_interval' ) ?>"><?php _e( 'Auto Refresh Interval Seconds', 'wpforo' ); ?></label>&nbsp;
                <input id="<?php echo $this->get_field_id( 'refresh_interval' ) ?>" type="number" min="0" style="display: inline-block; width: 53px;" name="<?php echo esc_attr( $this->get_field_name( 'refresh_interval' ) ); ?>" value="<?php echo esc_attr( $refresh_interval ); ?>">
                <span style="color: #ccc"><?php _e( 'Set 0 to disable autorefresh', 'wpforo' ) ?></span>
            </p>
        </div>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance                 = wpforo_parse_args( $new_instance, $this->default_instance );
		$instance                     = [];
		$instance['title']            = strip_tags( (string) $new_instance['title'] );
		$instance['count']            = (int) $new_instance['count'];
		$instance['display_avatar']   = (bool) (int) $new_instance['display_avatar'];
		$instance['groupids']         = array_filter( wpforo_parse_args( $new_instance['groupids'] ) );
		$instance['refresh_interval'] = (int) $new_instance['refresh_interval'];

		return $instance;
	}
}
