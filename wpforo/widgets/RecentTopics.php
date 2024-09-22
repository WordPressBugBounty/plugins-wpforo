<?php

namespace wpforo\widgets;

use WP_Widget;

class RecentTopics extends WP_Widget {
	private $default_instance = [];
	private $orderby_fields   = [];
	private $order_fields     = [];

	function __construct() {
		parent::__construct( 'wpforo_recent_topics', 'wpForo Recent Topics', [ 'description' => 'Your forum\'s recent topics.' ] );
		$this->init_local_vars();
        add_action( 'wp_ajax_wpforo_load_ajax_widget_RecentTopics',        [ $this, 'load_ajax_widget' ] );
		add_action( 'wp_ajax_nopriv_wpforo_load_ajax_widget_RecentTopics', [ $this, 'load_ajax_widget' ] );
		if( is_admin() ) {
			add_action( 'wp_ajax_wpforo_get_forum_tree', [ $this, 'get_forum_tree' ] );
		}
	}

	private function init_local_vars() {
		$this->default_instance = [
			'boardid'                => 0,
			'title'                  => 'Recent Topics',
			'forumids'               => [],
			'orderby'                => 'created',
			'order'                  => 'DESC',
			'count'                  => 9,
			'display_avatar'         => true,
			'forumids_filter'        => false,
			'current_forumid_filter' => false,
			'goto_unread'            => false,
            'refresh_interval'       => 0,
		];
		$this->orderby_fields   = [
			'created'  => __( 'Created Date', 'wpforo' ),
			'modified' => __( 'Modified Date', 'wpforo' ),
			'posts'    => __( 'Posts Count', 'wpforo' ),
			'views'    => __( 'Views Count', 'wpforo' ),
		];
		$this->order_fields     = [
			'DESC' => __( 'DESC', 'wpforo' ),
			'ASC'  => __( 'ASC', 'wpforo' ),
		];
	}

	public function get_widget( $instance, $topic_args ) {
		$is_user_logged_in = (bool) WPF()->current_userid;
		$topic_args['private'] = ( ! $is_user_logged_in || ! WPF()->usergroup->can( 'aum' ) ) ? 0 : null;;
		$topic_args['status']  = ( ! $is_user_logged_in || ! WPF()->usergroup->can( 'aum' ) ) ? 0 : null;

		$row_count = (int) wpfval( $topic_args, 'row_count' );
		$topics = [];
		$topic_args['offset'] = 0;
		while( $row_count && count( $topics ) < $row_count ){
			if( ! ( $_topics = WPF()->topic->get_topics( $topic_args ) ) ) break;

			$topics = array_merge( $topics, $_topics );
			$topic_args['offset'] += $row_count;
		}
		array_splice( $topics, $row_count );

		$print_avatar = $instance['display_avatar'] && wpforo_setting( 'profiles', 'avatars' ) && WPF()->usergroup->can( 'va' );

		$lis = '';
		foreach( $topics as $topic ) {
			$topic_url = wpforo_topic( $topic['topicid'], 'url' );
			$member    = wpforo_member( $topic );

			$lis .= sprintf(
				'<li>
                    <div class="wpforo-list-item">
                        %1$s
                        <div class="wpforo-list-item-right" %2$s>
                            <p class="posttitle">%3$s</p>
                            <p class="postuser">
                                %4$s %5$s <span style="white-space: nowrap;">%6$s</span>
                            </p>
                        </div>
                        <div class="wpf-clear"></div>
                    </div>
                </li>',
				( $print_avatar ? sprintf( '<div class="wpforo-list-item-left">%1$s</div>', wpforo_user_avatar( $member ) ) : '' ),
				( !$print_avatar ? 'style="width: 100%"' : '' ),
				( wpfval( $instance, 'goto_unread' ) ?
					wpforo_topic_title( $topic, $topic_url, '{p}{au}{t}{/a}', false ) .
					( $topic['topicid'] != wpfval( WPF()->current_object, 'topicid' ) ? wpforo_unread_button( $topic['topicid'], $topic_url, false ) : '' )
					:
					wpforo_topic_title( $topic, $topic_url, '{p}{a}{t}{/a}', false )
				),
				wpforo_phrase( 'by', false ),
				wpforo_member_link( $member, '', 30, '', false ),
				esc_html( wpforo_date( $topic['created'], 'ago', false ) )
			);
		}

		return sprintf( '<ul>%1$s</ul>', $lis );
    }

	public function load_ajax_widget() {
        $_POST = wp_unslash( $_POST );
        $instance   = json_decode( (string) wpfval( $_POST, 'instance' ), true );
        $topic_args = json_decode( (string) wpfval( $_POST, 'topic_args' ), true );
        wp_send_json_success( [ 'html' => $this->get_widget( $instance, $topic_args ) ] );
	}

    public function widget( $args, $instance ) {
	    wp_enqueue_script( 'wpforo-widgets-js' );
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		if( $instance['current_forumid_filter'] && $instance['boardid'] === WPF()->board->get_current( 'boardid' ) && $current_forumid = wpfval( WPF()->current_object, 'forumid' ) ) $instance['forumids'] = (array) $current_forumid;
        $data = [
	        'boardid'    => $instance['boardid'],
	        'action'     => 'wpforo_load_ajax_widget_RecentTopics',
	        'instance'   => $instance,
	        'topic_args' => [
		        'forumids'  => ( $instance['forumids'] ?: $this->default_instance['forumids'] ),
		        'orderby'   => ( key_exists( $instance['orderby'], $this->orderby_fields ) ? $instance['orderby'] : $this->default_instance['orderby'] ),
		        'order'     => ( key_exists( $instance['order'], $this->order_fields ) ? $instance['order'] : $this->default_instance['order'] ),
		        'row_count' => ( ( $count = intval( $instance['count'] ) ) ? $count : $this->default_instance['count'] ),
	        ],
        ];
	    if( WPF()->board->get_current( 'boardid' ) === $instance['boardid'] ) {
            $html = $this->get_widget( $data['instance'], $data['topic_args'] );
		    $onload = false;
	    }else{
		    $html = '<div style="text-align: center; font-size: 20px;"><i class="fas fa-spinner fa-spin"></i></div>';
		    $onload = true;
		    $data['referer'] = home_url();
	    }
	    $json = json_encode( $data );
		echo $args['before_widget'] . '<div id="wpf-widget-recent-replies" class="wpforo-widget-wrap">';
		if( ! empty( $instance['title'] ) ) echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        echo '<div class="wpforo-widget-content wpforo-ajax-widget ' . ( ! $onload ? 'wpforo-ajax-widget-onload-false' : '' ) . '" data-json="' . esc_attr( $json ) . '">' . $html . '</div></div>' . $args['after_widget'];
	}

	public function form( $instance ) {
		$instance               = wpforo_parse_args( $instance, $this->default_instance );
		$title                  = (string) $instance['title'];
        $boardid                = (int) $instance['boardid'];
		$selected               = array_unique( array_filter( array_map( 'intval', (array) $instance['forumids'] ) ) );
		$orderby                = (string) $instance['orderby'];
		$order                  = (string) $instance['order'];
		$count                  = (int) $instance['count'];
		$display_avatar         = (bool) $instance['display_avatar'];
		$forumids_filter        = (bool) $instance['forumids_filter'];
		$current_forumid_filter = (bool) $instance['current_forumid_filter'];
		$goto_unread            = (bool) $instance['goto_unread'];
		$refresh_interval       = (int) $instance['refresh_interval'];
        WPF()->change_board( $boardid );
		?>
        <style>
            .wpf-wdg-wrapper .wpf_wdg_forumids_wrap {
                display: none !important;
            }

            .wpf-wdg-wrapper input.wpf_wdg_forumids_filter_1:checked ~ .wpf_wdg_forumids_wrap {
                display: block !important;
            }
        </style>
        <div class="wpf-wdg-wrapper">
            <p>
                <label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Title', 'wpforo' ); ?>:</label>
                <input id="<?php echo $this->get_field_id( 'title' ) ?>" class="widefat"
                       name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
                       value="<?php echo esc_attr( $title ); ?>">
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'boardid' ) ?>"></label>
                <select id="<?php echo $this->get_field_id( 'boardid' ) ?>" class="wpf_wdg_boardid" name="<?php echo esc_attr( $this->get_field_name( 'boardid' ) ); ?>">
		            <?php echo WPF()->board->dropdown( $boardid ) ?>
                </select>
            </p>
            <div>
                <span><?php _e( 'Filter by forums', 'wpforo' ); ?> :</span>

                <label for="<?php echo $this->get_field_id( 'forumids_filter' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'forumids_filter' ) ?>_1" value="1" class="wpf_wdg_forumids_filter_1" name="<?php echo esc_attr( $this->get_field_name( 'forumids_filter' ) ); ?>" <?php checked( $forumids_filter ); ?> type="radio">

                <label for="<?php echo $this->get_field_id( 'forumids_filter' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'forumids_filter' ) ?>_0" value="0" class="wpf_wdg_forumids_filter_0" name="<?php echo esc_attr( $this->get_field_name( 'forumids_filter' ) ); ?>" <?php checked( $forumids_filter, false ); ?> type="radio">

                <div class="wpf_wdg_forumids_wrap">
                    <label for="<?php echo $this->get_field_id( 'forumids' ) ?>"></label>
                    <select id="<?php echo $this->get_field_id( 'forumids' ) ?>" class="wpf_wdg_forumids" name="<?php echo esc_attr( $this->get_field_name( 'forumids' ) ); ?>[]" multiple>
                        <?php WPF()->forum->tree( 'select_box', false, $selected ) ?>
                    </select>
                </div>
            </div>
            <p>
                <span><?php _e( 'Autofilter by current forum', 'wpforo' ); ?> : </span>

                <label for="<?php echo $this->get_field_id( 'current_forumid_filter' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'current_forumid_filter' ) ?>_1" name="<?php echo esc_attr( $this->get_field_name( 'current_forumid_filter' ) ); ?>" value="1" <?php checked( $current_forumid_filter ); ?> type="radio">

                <label for="<?php echo $this->get_field_id( 'current_forumid_filter' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'current_forumid_filter' ) ?>_0" name="<?php echo esc_attr( $this->get_field_name( 'current_forumid_filter' ) ); ?>" value="0" <?php checked( $current_forumid_filter, false ); ?> type="radio">
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'orderby' ) ?>"><?php _e( 'Order by', 'wpforo' ); ?>:</label>
                <select name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>"
                        id="<?php echo $this->get_field_id( 'orderby' ) ?>">
			        <?php foreach( $this->orderby_fields as $orderby_key => $orderby_field ) : ?>
                        <option value="<?php echo $orderby_key; ?>"<?php echo( $orderby_key == $orderby ? ' selected' : '' ); ?>><?php echo $orderby_field; ?></option>
			        <?php endforeach; ?>
                </select>
                <label>
                    <select name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>">
				        <?php foreach( $this->order_fields as $order_key => $order_field ) : ?>
                            <option value="<?php echo $order_key; ?>"<?php echo( $order_key == $order ? ' selected' : '' ); ?>><?php echo $order_field; ?></option>
				        <?php endforeach; ?>
                    </select>
                </label>
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
                <span><?php _e( 'Refer topics to first unread post', 'wpforo' ); ?> : </span>

                <label for="<?php echo $this->get_field_id( 'goto_unread' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'goto_unread' ) ?>_1" <?php checked( $goto_unread ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'goto_unread' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'goto_unread' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'goto_unread' ) ?>_0" <?php checked( $goto_unread, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'goto_unread' ) ); ?>">
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
		$new_instance                       = wpforo_parse_args( $new_instance, $this->default_instance );
		$instance                           = [];
		$instance['title']                  = strip_tags( (string) $new_instance['title'] );
        $instance['boardid']                = (int) $new_instance['boardid'];
		$instance['forumids_filter']        = (bool) (int) $new_instance['forumids_filter'];
		$instance['forumids']               = array_unique( array_filter( array_map( 'intval', (array) $new_instance['forumids'] ) ) );
		$instance['orderby']                = ( ! empty( $new_instance['orderby'] ) && key_exists( $new_instance['orderby'], $this->orderby_fields ) ) ? $new_instance['orderby'] : $this->default_instance['orderby'];
		$instance['order']                  = ( ! empty( $new_instance['order'] ) && key_exists( $new_instance['order'], $this->order_fields ) ) ? $new_instance['order'] : $this->default_instance['order'];
		$instance['count']                  = (int) $new_instance['count'];
		$instance['display_avatar']         = (bool) (int) $new_instance['display_avatar'];
		$instance['current_forumid_filter'] = (bool) (int) $new_instance['current_forumid_filter'];
		$instance['goto_unread']            = (bool) (int) $new_instance['goto_unread'];
		$instance['refresh_interval']       = (int) $new_instance['refresh_interval'];

		return $instance;
	}

	public function get_forum_tree(  ) {
        ob_start();
		WPF()->forum->tree( 'select_box', false, [] );
        wp_send_json_success( [ 'html' => ob_get_clean() ] );
    }
}
