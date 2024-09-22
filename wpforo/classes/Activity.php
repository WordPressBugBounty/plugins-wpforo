<?php

namespace wpforo\classes;

use stdClass;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

class Activity {
	private $default;
	public  $activity;
	private $actions;
	public  $notifications = [];
	
	public function __construct() {
		add_action( 'wpforo_after_init', [ $this, 'init' ] );
	}
	
	public function init() {
		$this->init_defaults();
		$this->activity = $this->default->activity;
		$this->init_hooks();
		$this->init_actions();
		if( is_user_logged_in() && wpforo_setting( 'notifications', 'notifications' ) ) {
			$this->notifications = $this->get_notifications();
		}
	}
	
	private function init_actions() {
		$this->actions = [
			'edit_topic'    => [
				'title'       => wpforo_phrase( 'Edit Topic', false ),
				'icon'        => '',
				'description' => wpforo_phrase( 'This topic was modified %s by %s', false ),
				'before'      => '<div class="wpf-post-edited"><i class="far fa-edit"></i>',
				'after'       => '</div>',
			],
			'edit_post'     => [
				'title'       => wpforo_phrase( 'Edit Post', false ),
				'icon'        => '',
				'description' => wpforo_phrase( 'This post was modified %s by %s', false ),
				'before'      => '<div class="wpf-post-edited"><i class="far fa-edit"></i>',
				'after'       => '</div>',
			],
			'new_reply'     => [
				'title'       => wpforo_phrase( 'New Reply', false ),
				'icon'        => '<svg style="transform: rotate(180deg);" height="12" width="12" viewBox="0 0 512 512"><path fill="currentColor" d="M8.309 189.836L184.313 37.851C199.719 24.546 224 35.347 224 56.015v80.053c160.629 1.839 288 34.032 288 186.258c0 61.441-39.581 122.309-83.333 154.132c-13.653 9.931-33.111-2.533-28.077-18.631c45.344-145.012-21.507-183.51-176.59-185.742V360c0 20.7-24.3 31.453-39.687 18.164l-176.004-152c-11.071-9.562-11.086-26.753 0-36.328"></path></svg>',
				'description' => wpforo_phrase( 'New reply from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_reply">',
				'after'       => '</li>',
			],
			'new_like'      => [
				'title'       => wpforo_phrase( 'New Like', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M313.4 32.9c26 5.2 42.9 30.5 37.7 56.5l-2.3 11.4c-5.3 26.7-15.1 52.1-28.8 75.2H464c26.5 0 48 21.5 48 48c0 18.5-10.5 34.6-25.9 42.6C497 275.4 504 288.9 504 304c0 23.4-16.8 42.9-38.9 47.1c4.4 7.3 6.9 15.8 6.9 24.9c0 21.3-13.9 39.4-33.1 45.6c.7 3.3 1.1 6.8 1.1 10.4c0 26.5-21.5 48-48 48H294.5c-19 0-37.5-5.6-53.3-16.1l-38.5-25.7C176 420.4 160 390.4 160 358.3V320 272 247.1c0-29.2 13.3-56.7 36-75l7.4-5.9c26.5-21.2 44.6-51 51.2-84.2l2.3-11.4c5.2-26 30.5-42.9 56.5-37.7zM32 192H96c17.7 0 32 14.3 32 32V448c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V224c0-17.7 14.3-32 32-32z"/></svg>',
				'description' => wpforo_phrase( 'New like from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_like">',
				'after'       => '</li>',
			],
			'new_dislike'   => [
				'title'       => wpforo_phrase( 'New Dislike', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M313.4 479.1c26-5.2 42.9-30.5 37.7-56.5l-2.3-11.4c-5.3-26.7-15.1-52.1-28.8-75.2H464c26.5 0 48-21.5 48-48c0-18.5-10.5-34.6-25.9-42.6C497 236.6 504 223.1 504 208c0-23.4-16.8-42.9-38.9-47.1c4.4-7.3 6.9-15.8 6.9-24.9c0-21.3-13.9-39.4-33.1-45.6c.7-3.3 1.1-6.8 1.1-10.4c0-26.5-21.5-48-48-48H294.5c-19 0-37.5 5.6-53.3 16.1L202.7 73.8C176 91.6 160 121.6 160 153.7V192v48 24.9c0 29.2 13.3 56.7 36 75l7.4 5.9c26.5 21.2 44.6 51 51.2 84.2l2.3 11.4c5.2 26 30.5 42.9 56.5 37.7zM32 384H96c17.7 0 32-14.3 32-32V128c0-17.7-14.3-32-32-32H32C14.3 96 0 110.3 0 128V352c0 17.7 14.3 32 32 32z"/></svg>',
				'description' => wpforo_phrase( 'New dislike from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_dislike">',
				'after'       => '</li>',
			],
			'new_up_vote'   => [
				'title'       => wpforo_phrase( 'New Up Vote', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M8 256C8 119 119 8 256 8s248 111 248 248-111 248-248 248S8 393 8 256zm292 116V256h70.9c10.7 0 16.1-13 8.5-20.5L264.5 121.2c-4.7-4.7-12.2-4.7-16.9 0l-115 114.3c-7.6 7.6-2.2 20.5 8.5 20.5H212v116c0 6.6 5.4 12 12 12h64c6.6 0 12-5.4 12-12z"/></svg>',
				'description' => wpforo_phrase( 'New up vote from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_up_vote">',
				'after'       => '</li>',
			],
			'new_down_vote' => [
				'title'       => wpforo_phrase( 'New Down Vote', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M504 256c0 137-111 248-248 248S8 393 8 256 119 8 256 8s248 111 248 248zM212 140v116h-70.9c-10.7 0-16.1 13-8.5 20.5l114.9 114.3c4.7 4.7 12.2 4.7 16.9 0l114.9-114.3c7.6-7.6 2.2-20.5-8.5-20.5H300V140c0-6.6-5.4-12-12-12h-64c-6.6 0-12 5.4-12 12z"/></svg>',
				'description' => wpforo_phrase( 'New down vote from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_down_vote">',
				'after'       => '</li>',
			],
			'new_reaction'  => [
				'title'       => wpforo_phrase( 'New Reaction', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M349.4 44.6c5.9-13.7 1.5-29.7-10.6-38.5s-28.6-8-39.9 1.8l-256 224c-10 8.8-13.6 22.9-8.9 35.3S50.7 288 64 288H175.5L98.6 467.4c-5.9 13.7-1.5 29.7 10.6 38.5s28.6 8 39.9-1.8l256-224c10-8.8 13.6-22.9 8.9-35.3s-16.6-20.7-30-20.7H272.5L349.4 44.6z"/></svg>',
				'description' => wpforo_phrase( 'New Reaction from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_reaction">',
				'after'       => '</li>',
			],
			'new_mention'   => [
				'title'       => wpforo_phrase( 'New User Mentioning', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 8C118.9 8 8 118.9 8 256c0 137.1 110.9 248 248 248 48.2 0 95.3-14.1 135.4-40.2 12-7.8 14.6-24.3 5.6-35.4l-10.2-12.4c-7.7-9.4-21.2-11.7-31.4-5.1C325.9 429.8 291.3 440 256 440c-101.5 0-184-82.5-184-184S154.5 72 256 72c100.1 0 184 57.6 184 160 0 38.8-21.1 79.7-58.2 83.7-17.3-.5-16.9-12.9-13.5-30l23.4-121.1C394.7 149.8 383.3 136 368.2 136h-45a13.5 13.5 0 0 0 -13.4 12l0 .1c-14.7-17.9-40.4-21.8-60-21.8-74.6 0-137.8 62.2-137.8 151.5 0 65.3 36.8 105.9 96 105.9 27 0 57.4-15.6 75-38.3 9.5 34.1 40.6 34.1 70.7 34.1C462.6 379.4 504 307.8 504 232 504 95.7 394 8 256 8zm-21.7 304.4c-22.2 0-36.1-15.6-36.1-40.8 0-45 30.8-72.7 58.6-72.7 22.3 0 35.6 15.2 35.6 40.8 0 45.1-33.9 72.7-58.2 72.7z"/></svg>',
				'description' => wpforo_phrase( '%1$s has mentioned you, %2$s', false ),
				'before'      => '<li class="wpf-new_mention">',
				'after'       => '</li>',
			],
			'default'       => [
				'title'       => wpforo_phrase( 'New Notification', false ),
				'icon'        => '<svg height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M224 0c-17.7 0-32 14.3-32 32V51.2C119 66 64 130.6 64 208v18.8c0 47-17.3 92.4-48.5 127.6l-7.4 8.3c-8.4 9.4-10.4 22.9-5.3 34.4S19.4 416 32 416H416c12.6 0 24-7.4 29.2-18.9s3.1-25-5.3-34.4l-7.4-8.3C401.3 319.2 384 273.9 384 226.8V208c0-77.4-55-142-128-156.8V32c0-17.7-14.3-32-32-32zm45.3 493.3c12-12 18.7-28.3 18.7-45.3H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7z"/></svg>',
				'description' => wpforo_phrase( 'New notification from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_note">',
				'after'       => '</li>',
			],
		];
		
		$this->actions = apply_filters( 'wpforo_register_actions', $this->actions );
	}
	
	private function init_defaults() {
		$this->default                  = new stdClass();
		$this->default->activity        = [
			'id'            => 0,
			'type'          => '',
			'itemid'        => 0,
			'itemtype'      => '',
			'itemid_second' => 0,
			'userid'        => 0,
			'name'          => '',
			'email'         => '',
			'date'          => 0,
			'content'       => '',
			'permalink'     => '',
			'new'           => 0,
		];
		$this->default->activity_format = [
			'id'            => '%d',
			'type'          => '%s',
			'itemid'        => '%d',
			'itemtype'      => '%s',
			'itemid_second' => '%d',
			'userid'        => '%d',
			'name'          => '%s',
			'email'         => '%s',
			'date'          => '%d',
			'content'       => '%s',
			'permalink'     => '%s',
			'new'           => '%d',
		];
		$this->default->sql_select_args = [
			'type'              => null,
			'userid'            => null,
			'itemtype'          => null,
			'new'               => null,
			'include'           => [],
			'exclude'           => [],
			'userids_include'   => [],
			'userids_exclude'   => [],
			'types_include'     => [],
			'types_exclude'     => [],
			'itemids_include'   => [],
			'itemids_exclude'   => [],
			'itemtypes_include' => [],
			'itemtypes_exclude' => [],
			'emails_include'    => [],
			'emails_exclude'    => [],
			'orderby'           => 'id',
			'order'             => 'ASC',
			'offset'            => null,
			'row_count'         => null,
		];
		
		$this->default = apply_filters( 'wpforo_activity_after_init_defaults', $this->default );
	}
	
	private function init_hooks() {
		if( wpforo_setting( 'posting', 'edit_topic' ) ) {
			add_action( 'wpforo_after_edit_topic', [ $this, 'after_edit_topic' ] );
		}
		if( wpforo_setting( 'posting', 'edit_post' ) ) {
			add_action( 'wpforo_after_edit_post', [ $this, 'after_edit_post' ] );
		}
		
		if( WPF()->current_userid && wpforo_setting( 'notifications', 'notifications' ) ) {
			if( wpforo_setting( 'notifications', 'notifications_bar' ) ) {
				add_action( 'wpforo_before_search_toggle', [ $this, 'bell' ] );
			}
			add_action( 'wpforo_after_add_post', [ $this, 'after_add_post' ], 10, 2 );
			add_action( 'wpforo_post_status_update', [ $this, 'update_notification' ], 10, 2 );
			add_action( 'wpforo_vote', [ $this, 'after_vote' ], 10, 2 );
			add_action( 'wpforo_react_post', [ &$this, 'after_react' ], 10, 2 );
			add_action( 'wpforo_unreact_post', [ &$this, 'after_unreact' ] );
		}
	}
	
	private function filter_built_html_rows( $rows ) {
		$_rows = [];
		foreach( $rows as $row_key => $row ) {
			$in_array = false;
			if( $_rows ) {
				foreach( $_rows as $_row_key => $_row ) {
					if( in_array( $row, $_row ) ) {
						$in_array  = true;
						$match_key = $_row_key;
						break;
					}
				}
			}
			if( $in_array && isset( $match_key ) ) {
				$_rows[ $match_key ]['times'] ++;
			} else {
				$_rows[ $row_key ]['html']  = $row;
				$_rows[ $row_key ]['times'] = 1;
			}
		}
		
		$rows = [];
		foreach( $_rows as $_row ) {
			$times = '';
			if( $_row['times'] > 1 ) {
				$times = ' ' . sprintf(
						wpforo_phrase( '%d times', false ),
						$_row['times']
					);
			}
			
			$rows[] = sprintf( $_row['html'], $times );
		}
		
		$limit = wpforo_setting( 'posting', 'edit_log_display_limit' );
		if( $limit ) $rows = array_slice( $rows, ( - 1 * $limit ), $limit );
		
		return $rows;
	}
	
	private function parse_activity( $data ) {
		return apply_filters( 'wpforo_activty_parse_activity', array_merge( $this->default->activity, $data ) );
	}
	
	private function parse_args( $args ) {
		$args = wpforo_parse_args( $args, $this->default->sql_select_args );
		
		$args['include'] = wpforo_parse_args( $args['include'] );
		$args['exclude'] = wpforo_parse_args( $args['exclude'] );
		
		$args['userids_include'] = wpforo_parse_args( $args['userids_include'] );
		$args['userids_exclude'] = wpforo_parse_args( $args['userids_exclude'] );
		
		$args['types_include'] = wpforo_parse_args( $args['types_include'] );
		$args['types_exclude'] = wpforo_parse_args( $args['types_exclude'] );
		
		$args['itemids_include'] = wpforo_parse_args( $args['itemids_include'] );
		$args['itemids_exclude'] = wpforo_parse_args( $args['itemids_exclude'] );
		
		$args['itemtypes_include'] = wpforo_parse_args( $args['itemtypes_include'] );
		$args['itemtypes_exclude'] = wpforo_parse_args( $args['itemtypes_exclude'] );
		
		$args['emails_include'] = wpforo_parse_args( $args['emails_include'] );
		$args['emails_exclude'] = wpforo_parse_args( $args['emails_exclude'] );
		
		return $args;
	}
	
	private function build_sql_select( $args ) {
		$args = $this->parse_args( $args );
		
		$wheres = [];
		
		if( ! is_null( $args['type'] ) ) $wheres[] = "`type` = '" . esc_sql( $args['type'] ) . "'";
		if( ! is_null( $args['itemtype'] ) ) $wheres[] = "`itemtype` = '" . esc_sql( $args['itemtype'] ) . "'";
		if( ! is_null( $args['userid'] ) ) $wheres[] = "`userid` = " . intval( $args['userid'] );
		if( ! is_null( $args['new'] ) ) $wheres[] = "`new` = " . intval( $args['new'] );
		
		if( ! empty( $args['include'] ) ) $wheres[] = "`id` IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['include'] ) ) . ")";
		if( ! empty( $args['exclude'] ) ) $wheres[] = "`id` NOT IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['exclude'] ) ) . ")";
		
		if( ! empty( $args['userids_include'] ) ) $wheres[] = "`userid` IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['userids_include'] ) ) . ")";
		if( ! empty( $args['userids_exclude'] ) ) $wheres[] = "`userid` NOT IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['userids_exclude'] ) ) . ")";
		
		if( ! empty( $args['types_include'] ) ) $wheres[] = "`type` IN('" . implode( "','", array_map( 'trim', $args['types_include'] ) ) . "')";
		if( ! empty( $args['types_exclude'] ) ) $wheres[] = "`type` NOT IN('" . implode( "','", array_map( 'trim', $args['types_exclude'] ) ) . "')";
		
		if( ! empty( $args['itemids_include'] ) ) $wheres[] = "`itemid` IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['itemids_include'] ) ) . ")";
		if( ! empty( $args['itemids_exclude'] ) ) $wheres[] = "`itemid` NOT IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['itemids_exclude'] ) ) . ")";
		
		if( ! empty( $args['itemtypes_include'] ) ) $wheres[] = "`itemtype` IN('" . implode( "','", array_map( 'trim', $args['itemtypes_include'] ) ) . "')";
		if( ! empty( $args['itemtypes_exclude'] ) ) $wheres[] = "`itemtype` NOT IN('" . implode( "','", array_map( 'trim', $args['itemtypes_exclude'] ) ) . "')";
		
		if( ! empty( $args['emails_include'] ) ) $wheres[] = "`email` IN('" . implode( "','", array_map( 'trim', $args['emails_include'] ) ) . "')";
		if( ! empty( $args['emails_exclude'] ) ) $wheres[] = "`email` NOT IN('" . implode( "','", array_map( 'trim', $args['emails_exclude'] ) ) . "')";
		
		$sql = "SELECT * FROM " . WPF()->tables->activity;
		if( $wheres ) $sql .= " WHERE " . implode( " AND ", $wheres );
		$sql .= " ORDER BY " . $args['orderby'] . " " . $args['order'];
		if( $args['row_count'] ) {
			if( ! empty( $args['offset'] ) ) {
				$sql .= " LIMIT " . wpforo_bigintval( $args['offset'] ) . "," . wpforo_bigintval( $args['row_count'] );
			} else {
				$sql .= " LIMIT " . wpforo_bigintval( $args['row_count'] );
			}
		}
		
		return $sql;
	}
	
	public function get_activity( $args ) {
		if( ! $args ) return false;
		
		return $this->parse_activity( (array) WPF()->db->get_row( $this->build_sql_select( $args ), ARRAY_A ) );
	}
	
	public function get_activities( $args ) {
		if( ! $args ) return [];
		
		return array_map( [ $this, 'parse_activity' ], (array) WPF()->db->get_results( $this->build_sql_select( $args ), ARRAY_A ) );
	}
	
	public function after_edit_topic( $topic ) {
		$data = [
			'type'      => 'edit_topic',
			'itemid'    => $topic['topicid'],
			'itemtype'  => 'topic',
			'userid'    => WPF()->current_userid,
			'name'      => WPF()->current_user_display_name,
			'email'     => WPF()->current_user_email,
			'permalink' => wpforo_topic( $topic['topicid'], 'url' ),
		];
		
		$this->add( $data );
	}
	
	public function after_edit_post( $post ) {
		$data = [
			'type'      => 'edit_post',
			'itemid'    => $post['postid'],
			'itemtype'  => 'post',
			'userid'    => WPF()->current_userid,
			'name'      => WPF()->current_user_display_name,
			'email'     => WPF()->current_user_email,
			'permalink' => wpforo_post( $post['postid'], 'url' ),
		];
		
		$this->add( $data );
	}
	
	public function after_add_post( $post, $topic ) {
		$this->add_notification_new_reply( 'new_reply', $post, $topic );
	}
	
	private function add( $data ) {
		if( empty( $data ) ) return false;
		$activity = array_merge( $this->default->activity, $data );
		unset( $activity['id'] );
		
		if( ! $activity['type'] || ! $activity['itemid'] || ! $activity['itemtype'] ) return false;
		if( ! $activity['date'] ) $activity['date'] = time();
		
		$activity = apply_filters( 'wpforo_add_activity_data_filter', $activity );
		do_action( 'wpforo_before_add_activity', $activity );
		
		$activity = wpforo_array_ordered_intersect_key( $activity, $this->default->activity_format );
		if( WPF()->db->insert(
			WPF()->tables->activity,
			$activity,
			wpforo_array_ordered_intersect_key( $this->default->activity_format, $activity )
		) ) {
			$activity['id'] = WPF()->db->insert_id;
			do_action( 'wpforo_after_add_activity', $activity );
			
			return $activity['id'];
		}
		
		return false;
	}
	
	private function edit( $data, $where ) {
		if( empty( $data ) || empty( $where ) ) return false;
		if( is_numeric( $where ) ) $where = [ 'id' => $where ];
		$data  = (array) $data;
		$where = (array) $where;
		
		$data  = apply_filters( 'wpforo_activity_edit_data_filter', $data );
		$where = apply_filters( 'wpforo_activity_edit_where_filter', $where );
		do_action( 'wpforo_before_edit_activity', $data, $where );
		
		$data  = wpforo_array_ordered_intersect_key( $data, $this->default->activity_format );
		$where = wpforo_array_ordered_intersect_key( $where, $this->default->activity_format );
		if( false !== WPF()->db->update(
				WPF()->tables->activity,
				$data,
				$where,
				wpforo_array_ordered_intersect_key( $this->default->activity_format, $data ),
				wpforo_array_ordered_intersect_key( $this->default->activity_format, $where )
			) ) {
			do_action( 'wpforo_after_edit_activity', $data, $where );
			
			return true;
		}
		
		return false;
	}
	
	private function delete( $where ): bool {
		if( empty( $where ) ) return false;
		if( is_numeric( $where ) ) $where = [ 'id' => $where ];
		$where = (array) $where;
		
		$where = apply_filters( 'wpforo_activity_delete_where_filter', $where );
		do_action( 'wpforo_before_delete_activity', $where );
		
		$where = wpforo_array_ordered_intersect_key( $where, $this->default->activity_format );
		if( false !== WPF()->db->delete(
				WPF()->tables->activity,
				$where,
				wpforo_array_ordered_intersect_key( $this->default->activity_format, $where )
			) ) {
			do_action( 'wpforo_after_delete_activity', $where );
			
			return true;
		}
		
		return false;
	}
	
	public function build( $itemtype, $itemid, $type, $echo = false ): string {
		$rows = [];
		$args = [
			'itemtypes_include' => $itemtype,
			'itemids_include'   => $itemid,
			'types_include'     => $type,
		];
		if( $activities = $this->get_activities( $args ) ) {
			foreach( $activities as $activity ) {
				switch( $activity['type'] ) {
					case 'edit_topic':
					case 'edit_post':
						$rows[] = $this->_build_edit_topic_edit_post( $activity );
					break;
				}
			}
		}
		
		$rows = $this->filter_built_html_rows( $rows );
		
		$html = ( $rows ? implode( '', $rows ) : '' );
		if( $echo ) echo $html;
		
		return $html;
	}
	
	private function _build_edit_topic_edit_post( $activity ) {
		$html   = '';
		$type   = $activity['type'];
		$userid = $activity['userid'];
		$date   = wpforo_date( $activity['date'], 'ago', false ) . '%s';
		
		if( $userid ) {
			$profile_url  = wpforo_member( $userid, 'profile_url' );
			$display_name = wpforo_member( $userid, 'display_name' );
			$user         = sprintf( '<a href="%s">%s</a>', $profile_url, $display_name );
		} else {
			$user = $activity['name'] ?: wpforo_phrase( 'Guest', false );
		}
		
		if( wpfval( $this->actions, $type, 'before' ) ) {
			$html = $this->actions[ $type ]['before'];
			$html = apply_filters( 'wpforo_activity_action_html_before', $html, $activity );
		}
		if( wpfval( $this->actions, $type, 'description' ) ) {
			$html .= sprintf( $this->actions[ $activity['type'] ]['description'], $date, str_replace( '%', '%%', $user ) );
			$html = apply_filters( 'wpforo_activity_action_html', $html, $activity );
		}
		if( wpfval( $this->actions, $type, 'after' ) ) {
			$html .= $this->actions[ $type ]['after'];
			$html = apply_filters( 'wpforo_activity_action_html_after', $html, $activity );
		}
		
		return $html;
	}
	
	public function bell( $class = 'wpf-alerts' ) {
		wp_enqueue_script( 'wpforo-widgets-js' );
		
		$class   = ( ! $class ) ? 'wpf-alerts' : $class;
		$count   = ( ! empty( $this->notifications ) ) ? count( $this->notifications ) : 0;
		$phrase  = ( $count > 1 ) ? wpforo_phrase( 'You have new notifications', false ) : wpforo_phrase( 'You have a new notification', false );
		$tooltip = ' wpf-tooltip="' . esc_attr( $phrase ) . '" wpf-tooltip-size="middle"';
		?>
        <div class="<?php echo esc_attr( $class ) ?> <?php echo ( $count ) ? 'wpf-new' : ''; ?>">
			<?php if( $count ): ?>
                <div class="wpf-bell" <?php echo $tooltip ?>>
                    <svg height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                        <path fill="currentColor"
                              d="M224 0c-17.7 0-32 14.3-32 32V51.2C119 66 64 130.6 64 208v18.8c0 47-17.3 92.4-48.5 127.6l-7.4 8.3c-8.4 9.4-10.4 22.9-5.3 34.4S19.4 416 32 416H416c12.6 0 24-7.4 29.2-18.9s3.1-25-5.3-34.4l-7.4-8.3C401.3 319.2 384 273.9 384 226.8V208c0-77.4-55-142-128-156.8V32c0-17.7-14.3-32-32-32zm45.3 493.3c12-12 18.7-28.3 18.7-45.3H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7z"/>
                    </svg>
                    <span class="wpf-alerts-count"><?php echo $count ?></span>
                </div>
			<?php else: ?>
                <div class="wpf-bell">
                    <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                        <path fill="currentColor"
                              d="M224 0c-17.7 0-32 14.3-32 32V51.2C119 66 64 130.6 64 208v25.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V208c0-77.4-55-142-128-156.8V32c0-17.7-14.3-32-32-32zm0 96c61.9 0 112 50.1 112 112v25.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V208c0-61.9 50.1-112 112-112zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"/>
                    </svg>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}
	
	public function notifications() {
		?>
        <div class="wpf-notifications">
            <div class="wpf-notification-head">
                <svg width="12" height="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                    <path fill="currentColor"
                          d="M224 0c-17.7 0-32 14.3-32 32V51.2C119 66 64 130.6 64 208v25.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V208c0-77.4-55-142-128-156.8V32c0-17.7-14.3-32-32-32zm0 96c61.9 0 112 50.1 112 112v25.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V208c0-61.9 50.1-112 112-112zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"/>
                </svg> <?php wpforo_phrase( 'Notifications' ) ?>
            </div>
            <div class="wpf-notification-content">
                <div class="wpf-nspin">
                    <svg width="24" height="24" viewBox="0 0 24 24">
                        <g stroke="currentColor">
                            <circle cx="12" cy="12" r="9.5" fill="none" stroke-linecap="round" stroke-width="3">
                                <animate attributeName="stroke-dasharray" calcMode="spline" dur="1.5s" keySplines="0.42,0,0.58,1;0.42,0,0.58,1;0.42,0,0.58,1" keyTimes="0;0.475;0.95;1"
                                         repeatCount="indefinite" values="0 150;42 150;42 150;42 150"></animate>
                                <animate attributeName="stroke-dashoffset" calcMode="spline" dur="1.5s" keySplines="0.42,0,0.58,1;0.42,0,0.58,1;0.42,0,0.58,1" keyTimes="0;0.475;0.95;1"
                                         repeatCount="indefinite" values="0;-16;-59;-59"></animate>
                            </circle>
                            <animateTransform attributeName="transform" dur="2s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"></animateTransform>
                        </g>
                    </svg>
                </div>
            </div>
            <div class="wpf-notification-actions">
                <span class="wpf-action wpf-notification-action-clear-all" data-foro_n="<?php echo wp_create_nonce( 'wpforo_clear_notifications' ) ?>"><?php wpforo_phrase( 'Clear all' ) ?></span>
            </div>
        </div>
		<?php
	}
	
	public function notifications_list( $echo = true ) {
		$items     = [];
		$list_html = '';
		if( ! empty( $this->notifications ) && is_array( $this->notifications ) ) {
			$list_html .= '<ul>';
			foreach( $this->notifications as $n ) {
				if( $type = wpfval( $n, 'type' ) ) {
					$html              = wpfval( $this->actions, $type ) ? $this->actions[ $type ] : $this->actions['default'];
					$items[ $n['id'] ] = $html['before'];
					if( wpfval( $n, 'itemid_second' ) ) {
						$member      = wpforo_member( $n['itemid_second'] );
						$member_name = wpfval( $member, 'display_name' ) ? $member['display_name'] : wpforo_phrase( 'Guest', false );
					} else {
						$member_name = wpfval( $n, 'name' ) ? $n['name'] : wpforo_phrase( 'Guest', false );
					}
					if( strpos( (string) $n['permalink'], '#' ) === false ) {
						$n['permalink'] = wp_nonce_url( $n['permalink'] . '?_nread=' . $n['id'], 'wpforo_mark_notification_read', 'foro_n' );
					} else {
						$n['permalink'] = str_replace( '#', '?_nread=' . $n['id'] . '#', $n['permalink'] );
						$n['permalink'] = wp_nonce_url( $n['permalink'], 'wpforo_mark_notification_read', 'foro_n' );
					}
					$date              = wpforo_date( $n['date'], 'ago', false );
					$length            = apply_filters( 'wpforo_notification_description_length', 40 );
					$items[ $n['id'] ] .= '<div class="wpf-nleft">' . $html['icon'] . '</div>';
					$items[ $n['id'] ] .= '<div class="wpf-nright">';
					$items[ $n['id'] ] .= '<a href="' . esc_url_raw( $n['permalink'] ) . '">';
					$items[ $n['id'] ] .= sprintf( $html['description'], '<strong>' . $member_name . '</strong>', $date );
					$items[ $n['id'] ] .= '</a>';
					$items[ $n['id'] ] .= '<div class="wpf-ndesc">' . stripslashes( wpforo_text( (string) $n['content'], $length, false ) ) . '</div>';
					$items[ $n['id'] ] .= '</div>';
					$items[ $n['id'] ] .= $html['after'];
				}
			}
			$items     = apply_filters( 'wpforo_notifications_list', $items );
			$list_html .= implode( "\r\n", $items );
			$list_html .= '</ul>';
		} else {
			$list_html = $this->get_no_notifications_html();
		}
		if( $echo ) echo $list_html;
		
		return $list_html;
	}
	
	public function get_no_notifications_html() {
		return '<div class="wpf-no-notification">' . wpforo_phrase( 'You have no new notifications', false ) . '</div>';
	}
	
	public function get_notifications() {
		$args = [ 'itemtype' => 'alert', 'userid' => WPF()->current_userid, 'row_count' => 100, 'orderby' => 'date', 'order' => 'DESC' ];
		$args = apply_filters( 'wpforo_get_notifications_args', $args );
		
		return $this->get_activities( $args );
	}
	
	public function add_notification_new_reply( $type, $post, $topic = [] ): void {
		if( ! wpfval( $post, 'status' ) ) {
			$notification = [
				'type'          => $type,
				'itemid'        => $post['postid'],
				'itemtype'      => 'alert',
				'itemid_second' => $post['userid'],
				'name'          => $post['name'],
				'email'         => $post['email'],
				'content'       => $post['title'],
				'permalink'     => $post['posturl'],
				'new'           => 1,
			];
			// Notify replied person
			$replied_post = wpforo_post( $post['parentid'] );
			if( ! empty( $replied_post ) && wpfval( $replied_post, 'userid' ) != wpfval( $post, 'userid' ) ) {
				$notification['userid'] = $replied_post['userid'];
				$notification           = apply_filters( 'wpforo_add_notification_new_reply_data', $notification, $type, $post, $topic, $replied_post );
				$this->add( $notification );
			}
			// Notify the topic author
			if( ! empty( $topic ) && $topic['userid'] != $post['userid'] && ! ( ! empty( $replied_post ) && $topic['userid'] == $replied_post['userid'] ) ) {
				$notification['userid'] = $topic['userid'];
				$notification           = apply_filters( 'wpforo_add_notification_new_reply_data', $notification, $type, $post, $topic, $replied_post );
				$this->add( $notification );
			}
		}
	}
	
	public function add_notification( $type, $args ): void {
		if( $args['userid'] != WPF()->current_userid ) {
			$length       = apply_filters( 'wpforo_notification_saved_description_length', 50 );
			$notification = [
				'type'          => $type,
				'itemid'        => $args['itemid'],
				'itemtype'      => 'alert',
				'itemid_second' => WPF()->current_userid,
				'userid'        => $args['userid'],
				'name'          => WPF()->current_user_display_name,
				'email'         => WPF()->current_user_email,
				'content'       => wpforo_text( $args['content'], $length, false ),
				'permalink'     => ( wpfval( $args, 'permalink' ) ? $args['permalink'] : '#' ),
				'new'           => 1,
			];
			$notification = apply_filters( 'wpforo_add_notification_data', $notification, $type, $args );
			$this->add( $notification );
		}
	}
	
	public function clear_all_reaction_notifications( $postid ) {
		if( $postid = wpforo_bigintval( $postid ) ) {
			$sql = "DELETE FROM `" . WPF()->tables->activity . "` WHERE `itemtype` = 'alert' AND `type` IN('new_like', 'new_dislike', 'new_reaction') AND `itemid` = %d AND `itemid_second` = %d";
			$sql = WPF()->db->prepare( $sql, $postid, WPF()->current_userid );
			WPF()->db->query( $sql );
		}
	}
	
	public function after_react( $reaction, $post ): void {
		if( $post ) {
			$this->clear_all_reaction_notifications( wpfval( $reaction, 'postid' ) );
			$args = [
				'itemid'    => $post['postid'],
				'userid'    => $post['userid'],
				'content'   => $post['body'],
				'permalink' => WPF()->post->get_url( $post['postid'] ),
			];
			switch( wpfval( $reaction, 'type' ) ) {
				case 'up':
					$ntype = 'new_like';
				break;
				case  'down':
					$ntype = 'new_dislike';
				break;
				default:
					$ntype = 'new_reaction';
				break;
			}
			$this->add_notification( $ntype, $args );
		}
	}
	
	public function after_unreact( $args ): void {
		if( $postid = wpforo_bigintval( wpfval( $args, 'postid' ) ) ) {
			$this->clear_all_reaction_notifications( $postid );
		}
	}
	
	public function after_vote( $reaction, $post ) {
		if( $post ) {
			if( $reaction == 1 ) {
				$args = [
					'itemid'    => $post['postid'],
					'userid'    => $post['userid'],
					'content'   => $post['body'],
					'permalink' => WPF()->post->get_url( $post['postid'] ),
				];
				$this->add_notification( 'new_up_vote', $args );
				$args = [
					'type'          => 'new_down_vote',
					'itemid'        => $post['postid'],
					'itemtype'      => 'alert',
					'itemid_second' => WPF()->current_userid,
				];
				$this->delete_notification( $args );
			} elseif( $reaction == - 1 ) {
				$args = [
					'itemid'    => $post['postid'],
					'userid'    => $post['userid'],
					'content'   => $post['body'],
					'permalink' => WPF()->post->get_url( $post['postid'] ),
				];
				$this->add_notification( 'new_down_vote', $args );
				$args = [
					'type'          => 'new_up_vote',
					'itemid'        => $post['postid'],
					'itemtype'      => 'alert',
					'itemid_second' => WPF()->current_userid,
				];
				$this->delete_notification( $args );
			}
		}
	}
	
	public function delete_notification( $args ) {
		$this->delete( $args );
	}
	
	public function update_notification( $post, $status ) {
		$post['status']  = $status = intval( $status );
		$post['posturl'] = WPF()->post->get_url( $post['postid'] );
		if( wpfval( $post, 'topicid' ) ) {
			$topic = WPF()->topic->get_topic( $post['topicid'] );
			if( $status ) {
				$args = [
					'type'     => 'new_reply',
					'itemid'   => $post['postid'],
					'itemtype' => 'alert',
				];
				$this->delete_notification( $args );
			} else {
				$this->add_notification_new_reply( 'new_reply', $post, $topic );
			}
		}
	}
	
	public function read_notification( $id, $userid = null ) {
		$userid = is_null( $userid ) ? WPF()->current_userid : $userid;
		$args   = [
			'id'     => $id,
			'userid' => $userid,
		];
		$this->delete_notification( $args );
	}
	
	public function clear_notifications( $userid = null ) {
		$userid = is_null( $userid ) ? WPF()->current_userid : $userid;
		$args   = [
			'userid' => $userid,
		];
		$this->delete_notification( $args );
	}
	
}
