<?php

namespace wpforo\widgets;

use WP_Widget;

class RecentPosts extends WP_Widget {
	private $default_instance = [];
	private $orderby_fields   = [];
	private $order_fields     = [];

	function __construct() {
		parent::__construct( 'wpforo_recent_posts', 'wpForo Recent Posts', [ 'description' => 'Your forum\'s recent posts.' ] );
		$this->init_local_vars();
		add_action( 'wp_ajax_wpforo_load_ajax_widget_RecentPosts',        [ $this, 'load_ajax_widget' ] );
		add_action( 'wp_ajax_nopriv_wpforo_load_ajax_widget_RecentPosts', [ $this, 'load_ajax_widget' ] );
		if( is_admin() ) {
			add_action( 'wp_ajax_wpforo_get_forum_tree', [ $this, 'get_forum_tree' ] );
		}
	}

	private function init_local_vars() {
		$this->default_instance = [
			'boardid'                => 0,
			'title'                  => 'Recent Posts',
			'forumids'               => [],
			'orderby'                => 'created',
			'order'                  => 'DESC',
			'count'                  => 9,
			'limit_per_topic'        => 0,
			'display_avatar'         => true,
			'forumids_filter'        => false,
			'current_forumid_filter' => false,
			'exclude_firstposts'     => false,
			'display_only_unread'    => false,
			'display_new_indicator'  => false,
			'refresh_interval'       => 0,
            'excerpt_length'         => 55,
		];
		$this->orderby_fields   = [
			'created'  => __( 'Created Date', 'wpforo' ),
			'modified' => __( 'Modified Date', 'wpforo' ),
		];
		$this->order_fields     = [
			'DESC' => __( 'DESC', 'wpforo' ),
			'ASC'  => __( 'ASC', 'wpforo' ),
		];
	}

	public function get_widget( $instance, $post_args ) {
		$is_user_logged_in = (bool) WPF()->current_userid;
		$post_args['private'] = ( ! $is_user_logged_in || ! WPF()->usergroup->can( 'aum' ) ) ? 0 : null;
		$post_args['status']  = ( ! $is_user_logged_in || ! WPF()->usergroup->can( 'aum' ) ) ? 0 : null;
		$print_avatar = $instance['display_avatar'] && wpforo_setting( 'profiles', 'avatars' ) && WPF()->usergroup->can( 'va' );

		ob_start();
		if( $post_args['limit_per_topic'] ) {
			if( $instance['display_only_unread'] && $is_user_logged_in ) {
				$grouped_postids = WPF()->post->get_unread_posts( $post_args, $post_args['row_count'] );
			} else {
				$grouped_postids = WPF()->post->get_posts( $post_args );
			}
			if( ! empty( $grouped_postids ) ) {
				$grouped_postids = implode( ',', $grouped_postids );
				$postids         = array_filter( array_map( 'wpforo_bigintval', explode( ',', $grouped_postids ) ) );
				rsort( $postids );
				foreach( $postids as $postid ) {
					$class = '';
					$post  = wpforo_post( $postid );
					if( ! wpfval($post, 'forumid') ) continue;
					if( ! WPF()->post->view_access( $post ) ) continue;
					$current = $post['topicid'] == wpfval( WPF()->current_object, 'topicid' );
					if( ! $current ) {
						$class = 'class="' . ( $instance['display_only_unread'] ? 'wpf-unread-post' : wpforo_unread( $post['topicid'], 'post', false, $post['postid'] ) ) . '"';
					}
					$member = wpforo_member( $post );
					?>
                    <li <?php echo $class; ?>>
                        <div class="wpforo-list-item ">
							<?php if( $print_avatar ): ?>
                                <div class="wpforo-list-item-left">
									<?php echo wpforo_user_avatar( $member ); ?>
                                </div>
							<?php endif; ?>
                            <div class="wpforo-list-item-right" <?php if( ! $print_avatar ): ?> style="width: 100%"<?php endif; ?>>
                                <p class="posttitle">
                                    <a href="<?php echo esc_url( (string) $post['url'] ) ?>"><?php
										if( $t = esc_html( trim( (string) $post['title'] ) ) ) {
											echo $t;
										} else {
											echo wpforo_phrase( 'RE', false, 'default' ) . ': ' . esc_html( trim( (string) wpforo_topic( $post['topicid'], 'title' ) ) );
										} ?>
                                    </a>
									<?php if( ! $current && $instance['display_new_indicator'] ) wpforo_unread_button( $post['topicid'], '', true, $post['postid'] ) ?>
                                </p>
                                <p class="posttext"><?php echo esc_html( wpforo_text( $post['body'], $instance['excerpt_length'], false ) ); ?></p>
                                <p class="postuser"><?php wpforo_phrase( 'by' ) ?> <?php wpforo_member_link( $member ) ?>
                                    , <span style="white-space: nowrap;"><?php wpforo_date( $post['created'] ) ?></span></p>
                            </div>
                            <div class="wpf-clear"></div>
                        </div>
                    </li>
					<?php
				}
			} else {
				$error_message = ( $instance['display_only_unread'] ) ? 'No new posts found' : 'No posts found';
				echo '<li class="wpf-no-post-found">' . wpforo_phrase( $error_message, false ) . '</li>';
			}
		} else {
			if( $instance['display_only_unread'] && $is_user_logged_in ) {
				$recent_posts = WPF()->post->get_unread_posts( $post_args, $post_args['row_count'] );
			} else {
				$recent_posts = WPF()->post->get_posts( $post_args );
			}
			if( ! empty( $recent_posts ) ) {
				foreach( $recent_posts as $post ) {
					$class    = '';
					$post_url = wpforo_post( $post['postid'], 'url' );
					$member   = wpforo_member( $post );
					$current  = $post['topicid'] == wpfval( WPF()->current_object, 'topicid' );
					if( ! $current ) {
						$class = 'class="' . ( $instance['display_only_unread'] ? 'wpf-unread-post' : wpforo_unread( $post['topicid'], 'post', false, $post['postid'] ) ) . '"';
					}
					?>
                    <li <?php echo $class; ?>>
                        <div class="wpforo-list-item">
							<?php if( $print_avatar ): ?>
                                <div class="wpforo-list-item-left">
									<?php echo wpforo_user_avatar( $member ); ?>
                                </div>
							<?php endif; ?>
                            <div class="wpforo-list-item-right" <?php if( ! $print_avatar ): ?> style="width:100%"<?php endif; ?>>
                                <p class="posttitle">
                                    <a href="<?php echo esc_url( (string) $post_url ) ?>"><?php
										if( $t = esc_html( trim( (string) $post['title'] ) ) ) {
											echo $t;
										} else {
											echo wpforo_phrase( 'RE', false, 'default' ) . ': ' . esc_html( trim( (string) wpforo_topic( $post['topicid'], 'title' ) ) );
										} ?>
                                    </a>
									<?php if( ! $current && $instance['display_new_indicator'] ) wpforo_unread_button( $post['topicid'], '', true, $post['postid'] ) ?>
                                </p>
                                <p class="posttext"><?php echo esc_html( wpforo_text( $post['body'], $instance['excerpt_length'], false ) ); ?></p>
                                <p class="postuser"><?php wpforo_phrase( 'by' ) ?> <?php wpforo_member_link( $member ) ?>
                                    , <span style="white-space: nowrap;"><?php wpforo_date( $post['created'] ) ?></span></p>
                            </div>
                            <div class="wpf-clear"></div>
                        </div>
                    </li>
					<?php
				}
			} else {
				$error_message = ( $instance['display_only_unread'] ) ? 'No new posts found' : 'No posts found';
				echo '<li class="wpf-no-post-found">' . wpforo_phrase( $error_message, false ) . '</li>';
			}
		}

        return sprintf( '<ul>%1$s</ul>', ob_get_clean() );
    }

	public function load_ajax_widget() {
		$_POST = wp_unslash( $_POST );
		$instance  = json_decode( (string) wpfval( $_POST, 'instance' ), true );
		$post_args = json_decode( (string) wpfval( $_POST, 'post_args' ), true );
		wp_send_json_success( [ 'html' => $this->get_widget( $instance, $post_args ) ] );
	}

	public function widget( $args, $instance ) {
		wp_enqueue_script( 'wpforo-widgets-js' );
		$is_user_logged_in    = (bool) WPF()->current_userid;
		$instance = wpforo_parse_args( $instance, $this->default_instance );
		if( $instance['display_only_unread'] ) {
			$display_widget = $is_user_logged_in;
			$display_widget = apply_filters( 'wpforo_widget_display_recent_posts', $display_widget );
		} else {
			$display_widget = true;
		}
		if( ! $display_widget ) return;

		if( $instance['current_forumid_filter'] && $instance['boardid'] === WPF()->board->get_current( 'boardid' ) && $current_forumid = wpfval( WPF()->current_object, 'forumid' ) ) $instance['forumids'] = (array) $current_forumid;
        $data = [
	        'boardid'    => $instance['boardid'],
	        'action'     => 'wpforo_load_ajax_widget_RecentPosts',
	        'instance'   => $instance,
	        'post_args'  => [
		        'forumids'        => ( $instance['forumids'] ?: $this->default_instance['forumids'] ),
		        'orderby'         => ( key_exists( $instance['orderby'], $this->orderby_fields ) ? $instance['orderby'] : $this->default_instance['orderby'] ),
		        'order'           => ( key_exists( $instance['order'], $this->order_fields ) ? $instance['order'] : $this->default_instance['order'] ),
		        'row_count'       => ( intval( $instance['count'] ) ?: $this->default_instance['count'] ),
		        'limit_per_topic' => ( intval( $instance['limit_per_topic'] ) ?: $this->default_instance['limit_per_topic'] ),
                'is_first_post'   => $instance['exclude_firstposts'] ? false : null,
		        'check_private'   => true,
	        ]
        ];
		if( WPF()->board->get_current( 'boardid' ) === $instance['boardid'] ){
            $html = $this->get_widget( $data['instance'], $data['post_args'] );
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
		$limit_per_topic        = (int) $instance['limit_per_topic'];
		$display_avatar         = (bool) $instance['display_avatar'];
		$forumids_filter        = (bool) $instance['forumids_filter'];
		$current_forumid_filter = (bool) $instance['current_forumid_filter'];
		$exclude_firstposts     = (bool) $instance['exclude_firstposts'];
		$display_only_unread    = (bool) $instance['display_only_unread'];
		$display_new_indicator  = (bool) $instance['display_new_indicator'];
		$refresh_interval       = (int) $instance['refresh_interval'];
		$excerpt_length         = (int) $instance['excerpt_length'];
		WPF()->change_board( $boardid );
		?>
        <style>
            .wpf-wdg-wrapper .wpf_wdg_forumids_wrap {
                display: none !important;
            }

            .wpf-wdg-wrapper input.wpf_wdg_forumids_filter_1:checked ~ .wpf_wdg_forumids_wrap {
                display: block !important;
            }
            .wpf_wdg_limit_per_topic {
                width: 53px;
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
                <label for="<?php echo $this->get_field_id( 'orderby' ) ?>"><?php _e( 'Order by', 'wpforo' ); ?>
                    :</label>
                <select class="wpf_wdg_orderby" name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>"
                        id="<?php echo $this->get_field_id( 'orderby' ) ?>" <?php echo( $limit_per_topic ? 'disabled' : '' ) ?>>
					<?php foreach( $this->orderby_fields as $orderby_key => $orderby_field ) : ?>
                        <option value="<?php echo $orderby_key; ?>"<?php echo( $orderby_key == $orderby ? ' selected' : '' ); ?>><?php echo $orderby_field; ?></option>
					<?php endforeach; ?>
                </select>
                <label>
                    <select class="wpf_wdg_order"
                            name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>" <?php echo( $limit_per_topic ? 'disabled' : '' ) ?>>
						<?php foreach( $this->order_fields as $order_key => $order_field ) : ?>
                            <option value="<?php echo $order_key; ?>"<?php echo( $order_key == $order ? ' selected' : '' ); ?>><?php echo $order_field; ?></option>
						<?php endforeach; ?>
                    </select>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'limit_per_topic' ) ?>"><?php _e( 'Limit Per Topic', 'wpforo' ); ?></label>&nbsp;
                <input id="<?php echo $this->get_field_id( 'limit_per_topic' ) ?>" class="wpf_wdg_limit_per_topic"
                       type="number" min="0"
                       name="<?php echo esc_attr( $this->get_field_name( 'limit_per_topic' ) ); ?>"
                       value="<?php echo esc_attr( $limit_per_topic ); ?>">
                <span style="color: #aaa;"><?php _e( 'set 0 to remove this limit', 'wpforo' ) ?></span>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'count' ) ?>"><?php _e( 'Number of Items', 'wpforo' ); ?></label>&nbsp;
                <input id="<?php echo $this->get_field_id( 'count' ) ?>" type="number" min="1" style="width: 53px;"
                       name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
                       value="<?php echo esc_attr( $count ); ?>">
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'excerpt_length' ) ?>"><?php _e( 'Excerpt Length', 'wpforo' ); ?></label>&nbsp;
                <input id="<?php echo $this->get_field_id( 'excerpt_length' ) ?>" type="number" min="0" style="display: inline-block; width: 53px;" name="<?php echo esc_attr( $this->get_field_name( 'excerpt_length' ) ); ?>" value="<?php echo esc_attr( $excerpt_length ); ?>">
                <span style="color: #ccc"><?php _e( 'Set 0 to disable autorefresh', 'wpforo' ) ?></span>
            </p>
            <p>
                <span><?php _e( 'Display with avatars', 'wpforo' ); ?> : </span>

                <label for="<?php echo $this->get_field_id( 'display_avatar' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_avatar' ) ?>_1" value="1" <?php checked( $display_avatar ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_avatar' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'display_avatar' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_avatar' ) ?>_0" value="0" <?php checked( $display_avatar, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_avatar' ) ); ?>">
            </p>
            <p>
                <span><?php _e( 'Exclude First Posts', 'wpforo' ); ?></span>
                <label for="<?php echo $this->get_field_id( 'exclude_firstposts' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'exclude_firstposts' ) ?>_1" value="1" <?php checked( $exclude_firstposts ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'exclude_firstposts' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'exclude_firstposts' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'exclude_firstposts' ) ?>_0" value="0" <?php checked( $exclude_firstposts, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'exclude_firstposts' ) ); ?>">
            </p>
            <p>
                <span><?php _e( 'Display Only Unread Posts', 'wpforo' ); ?></span>
                <label for="<?php echo $this->get_field_id( 'display_only_unread' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_only_unread' ) ?>_1" value="1" <?php checked( $display_only_unread ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_only_unread' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'display_only_unread' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_only_unread' ) ?>_0" value="0" <?php checked( $display_only_unread, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_only_unread' ) ); ?>">
            </p>
            <p>
                <span><?php _e( 'Display [new] indicator', 'wpforo' ); ?> : </span>

                <label for="<?php echo $this->get_field_id( 'display_new_indicator' ) ?>_1"><?php _e( 'Yes', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_new_indicator' ) ?>_1" value="1" <?php checked( $display_new_indicator ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_new_indicator' ) ); ?>">

                <label for="<?php echo $this->get_field_id( 'display_new_indicator' ) ?>_0"><?php _e( 'No', 'wpforo' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'display_new_indicator' ) ?>_0" value="0" <?php checked( $display_new_indicator, false ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'display_new_indicator' ) ); ?>">
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
		$instance['limit_per_topic']        = (int) $new_instance['limit_per_topic'];
		$instance['exclude_firstposts']     = (bool) (int) $new_instance['exclude_firstposts'];
		$instance['display_only_unread']    = (bool) (int) $new_instance['display_only_unread'];
		$instance['display_new_indicator']  = (bool) (int) $new_instance['display_new_indicator'];
		$instance['refresh_interval']       = (int) $new_instance['refresh_interval'];
		$instance['excerpt_length']         = (int) $new_instance['excerpt_length'];

		return $instance;
	}

	public function get_forum_tree() {
		ob_start();
		WPF()->forum->tree( 'select_box', false, [] );
		wp_send_json_success( [ 'html' => ob_get_clean() ] );
	}
}
