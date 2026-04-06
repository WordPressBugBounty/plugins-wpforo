<?php

namespace wpforo\classes;

use stdClass;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

class Activity {
	private $default;
	public  $activity;
	public  $actions;
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
			'wpforo_topic'  => [
				'title'       => wpforo_phrase( 'New Topic', false ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g id="_01_align_center" data-name="01 align center"><path d="M21,0H3A3,3,0,0,0,0,3V20H6.9l3.808,3.218a2,2,0,0,0,2.582,0L17.1,20H24V3A3,3,0,0,0,21,0Zm1,18H16.366L12,21.69,7.634,18H2V3A1,1,0,0,1,3,2H21a1,1,0,0,1,1,1Z"></path><rect x="6" y="5" width="6" height="2"></rect><rect x="6" y="9" width="12" height="2"></rect><rect x="6" y="13" width="12" height="2"></rect></g></svg>',
				'description' => wpforo_phrase( '%1$s created a new topic, %2$s', false ),
				'before'      => '<li class="wpf-wpforo_topic">',
				'after'       => '</li>',
			],
			'wpforo_post'   => [
				'title'       => wpforo_phrase( 'New Reply', false ),
				'icon'        => '<svg style="transform: rotate(180deg);" height="12" width="12" viewBox="0 0 512 512"><path fill="currentColor" d="M8.309 189.836L184.313 37.851C199.719 24.546 224 35.347 224 56.015v80.053c160.629 1.839 288 34.032 288 186.258c0 61.441-39.581 122.309-83.333 154.132c-13.653 9.931-33.111-2.533-28.077-18.631c45.344-145.012-21.507-183.51-176.59-185.742V360c0 20.7-24.3 31.453-39.687 18.164l-176.004-152c-11.071-9.562-11.086-26.753 0-36.328"></path></svg>',
				'description' => wpforo_phrase( '%1$s posted a reply, %2$s', false ),
				'before'      => '<li class="wpf-wpforo_post">',
				'after'       => '</li>',
			],
			'edit_topic'    => [
				'title'       => wpforo_phrase( 'Edit Topic', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M362.7 19.3L314.3 67.7 444.3 197.7l48.4-48.4c25-25 25-65.5 0-90.5L453.3 19.3c-25-25-65.5-25-90.5 0zm-71 71L58.6 323.5c-10.4 10.4-18 23.3-22.2 37.4L1 481.2C-1.5 489.7 .8 498.8 7 505s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L421.7 220.3 291.7 90.3z"/></svg>',
				'description' => wpforo_phrase( 'This topic was modified %s by %s', false ),
				'before'      => '<div class="wpf-post-edited"><svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M362.7 19.3L314.3 67.7 444.3 197.7l48.4-48.4c25-25 25-65.5 0-90.5L453.3 19.3c-25-25-65.5-25-90.5 0zm-71 71L58.6 323.5c-10.4 10.4-18 23.3-22.2 37.4L1 481.2C-1.5 489.7 .8 498.8 7 505s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L421.7 220.3 291.7 90.3z"/></svg>',
				'after'       => '</div>',
			],
			'edit_post'     => [
				'title'       => wpforo_phrase( 'Edit Post', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M362.7 19.3L314.3 67.7 444.3 197.7l48.4-48.4c25-25 25-65.5 0-90.5L453.3 19.3c-25-25-65.5-25-90.5 0zm-71 71L58.6 323.5c-10.4 10.4-18 23.3-22.2 37.4L1 481.2C-1.5 489.7 .8 498.8 7 505s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L421.7 220.3 291.7 90.3z"/></svg>',
				'description' => wpforo_phrase( 'This post was modified %s by %s', false ),
				'before'      => '<div class="wpf-post-edited"><svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M362.7 19.3L314.3 67.7 444.3 197.7l48.4-48.4c25-25 25-65.5 0-90.5L453.3 19.3c-25-25-65.5-25-90.5 0zm-71 71L58.6 323.5c-10.4 10.4-18 23.3-22.2 37.4L1 481.2C-1.5 489.7 .8 498.8 7 505s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L421.7 220.3 291.7 90.3z"/></svg>',
				'after'       => '</div>',
			],
			'new_reply'     => [
				'title'       => wpforo_phrase( 'New Reply', false ),
				'icon'        => '<svg style="transform: rotate(180deg); vertical-align: bottom;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M23,24a1,1,0,0,1-1-1,6.006,6.006,0,0,0-6-6H10.17v1.586A2,2,0,0,1,6.756,20L.877,14.121a3,3,0,0,1,0-4.242L6.756,4A2,2,0,0,1,10.17,5.414V7H15a9.01,9.01,0,0,1,9,9v7A1,1,0,0,1,23,24ZM8.17,5.414,2.291,11.293a1,1,0,0,0,0,1.414L8.17,18.586V16a1,1,0,0,1,1-1H16a7.984,7.984,0,0,1,6,2.714V16a7.008,7.008,0,0,0-7-7H9.17a1,1,0,0,1-1-1Z"></path></svg>',
				'description' => wpforo_phrase( 'New reply from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_reply">',
				'after'       => '</li>',
			],
			'new_like'      => [
				'title'       => wpforo_phrase( 'New Like', false ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" style="width: 17px; height: 17px;" height="12" width="12" viewBox="0 0 24 24"><g id="_01_align_center" data-name="01 align center"><path d="M15.021,7l.336-2.041a3.044,3.044,0,0,0-4.208-3.287A3.139,3.139,0,0,0,9.582,3.225L7.717,7H3a3,3,0,0,0-3,3v9a3,3,0,0,0,3,3H22.018L24,10.963,24.016,7ZM2,19V10A1,1,0,0,1,3,9H7V20H3A1,1,0,0,1,2,19Zm20-8.3L20.33,20H9V8.909l2.419-4.9A1.07,1.07,0,0,1,13.141,3.8a1.024,1.024,0,0,1,.233.84L12.655,9H22Z"></path></g></svg>',
				'description' => wpforo_phrase( 'New like from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_like">',
				'after'       => '</li>',
			],
			'new_dislike'   => [
				'title'       => wpforo_phrase( 'New Dislike', false ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" style="width: 17px; height: 17px; transform: rotate(180deg);" viewBox="0 0 24 24"><g id="_01_align_center" data-name="01 align center"><path d="M15.021,7l.336-2.041a3.044,3.044,0,0,0-4.208-3.287A3.139,3.139,0,0,0,9.582,3.225L7.717,7H3a3,3,0,0,0-3,3v9a3,3,0,0,0,3,3H22.018L24,10.963,24.016,7ZM2,19V10A1,1,0,0,1,3,9H7V20H3A1,1,0,0,1,2,19Zm20-8.3L20.33,20H9V8.909l2.419-4.9A1.07,1.07,0,0,1,13.141,3.8a1.024,1.024,0,0,1,.233.84L12.655,9H22Z"></path></g></svg>',
				'description' => wpforo_phrase( 'New dislike from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_dislike">',
				'after'       => '</li>',
			],
			'new_up_vote'   => [
				'title'       => wpforo_phrase( 'New Up Vote', false ),
				'icon'        => '<svg width="12px" height="12px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 32px; height: 32px;"> <g id="_24x24_On_Light_Arrow-Top" data-name="24x24/On Light/Arrow-Top" transform="translate(24) rotate(90)"> <rect id="view-box" width="24" height="24" fill="none"/> <path id="Shape" d="M.22,10.22A.75.75,0,0,0,1.28,11.28l5-5a.75.75,0,0,0,0-1.061l-5-5A.75.75,0,0,0,.22,1.28l4.47,4.47Z" transform="translate(14.75 17.75) rotate(180)"/> </g> </svg>',
				'description' => wpforo_phrase( 'New up vote from %1$s, %2$s', false ),
				'before'      => '<li class="wpf-new_up_vote">',
				'after'       => '</li>',
			],
			'new_down_vote' => [
				'title'       => wpforo_phrase( 'New Down Vote', false ),
				'icon'        => '<svg width="12px" height="12px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 32px; height: 32px;" transform="matrix(-1, 0, 0, -1, 0, 0)"> <g id="SVGRepo_bgCarrier" stroke-width="0"/> <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"/> <g id="SVGRepo_iconCarrier"> <g id="_24x24_On_Light_Arrow-Top" data-name="24x24/On Light/Arrow-Top" transform="translate(24) rotate(90)"> <rect id="view-box" width="24" height="24" fill="none"/> <path id="Shape" d="M.22,10.22A.75.75,0,0,0,1.28,11.28l5-5a.75.75,0,0,0,0-1.061l-5-5A.75.75,0,0,0,.22,1.28l4.47,4.47Z" transform="translate(14.75 17.75) rotate(180)"/> </g> </g> </svg>',
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
			'new_favorite'  => [
				'title'       => wpforo_phrase( 'New Favorite', false ),
				'icon'        => '<svg width="12px" height="12px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 19px; height: 19px; margin-top: 1px;"> <path d="M5 6.2C5 5.07989 5 4.51984 5.21799 4.09202C5.40973 3.71569 5.71569 3.40973 6.09202 3.21799C6.51984 3 7.07989 3 8.2 3H15.8C16.9201 3 17.4802 3 17.908 3.21799C18.2843 3.40973 18.5903 3.71569 18.782 4.09202C19 4.51984 19 5.07989 19 6.2V21L12 16L5 21V6.2Z" stroke-width="2" stroke-linejoin="round"/> </svg>',
				'description' => wpforo_phrase( '%1$s favorited, %2$s', false ),
				'before'      => '<li class="wpf-new_favorite">',
				'after'       => '</li>',
			],
			'topic_solved'  => [
				'title'       => wpforo_phrase( 'Topic Solved', false ),
				'icon'        => '<svg width="12px" height="12px"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 415.582 415.582"> <g> <path d="M411.47,96.426l-46.319-46.32c-5.482-5.482-14.371-5.482-19.853,0L152.348,243.058l-82.066-82.064 c-5.48-5.482-14.37-5.482-19.851,0l-46.319,46.32c-5.482,5.481-5.482,14.37,0,19.852l138.311,138.31 c2.741,2.742,6.334,4.112,9.926,4.112c3.593,0,7.186-1.37,9.926-4.112L411.47,116.277c2.633-2.632,4.111-6.203,4.111-9.925 C415.582,102.628,414.103,99.059,411.47,96.426z"/> </g> </svg>',
				'description' => wpforo_phrase( '%1$s marked as solved, %2$s', false ),
				'before'      => '<li class="wpf-topic_solved">',
				'after'       => '</li>',
			],
			'topic_closed'  => [
				'title'       => wpforo_phrase( 'Topic Closed', false ),
				'icon'        => '<svg height="12" width="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z"/></svg>',
				'description' => wpforo_phrase( '%1$s closed the topic, %2$s', false ),
				'before'      => '<li class="wpf-topic_closed">',
				'after'       => '</li>',
			],
			'post_answer'   => [
				'title'       => wpforo_phrase( 'Marked as Answer', false ),
				'icon'        => '<svg height="12" width="12" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 21px; height: 21px; margin-top: 2px;"><path d="M12.0867962,18 L6,21.8042476 L6,18 L4,18 C2.8954305,18 2,17.1045695 2,16 L2,4 C2,2.8954305 2.8954305,2 4,2 L20,2 C21.1045695,2 22,2.8954305 22,4 L22,16 C22,17.1045695 21.1045695,18 20,18 L12.0867962,18 Z M8,18.1957524 L11.5132038,16 L20,16 L20,4 L4,4 L4,16 L8,16 L8,18.1957524 Z M11,10.5857864 L15.2928932,6.29289322 L16.7071068,7.70710678 L11,13.4142136 L7.29289322,9.70710678 L8.70710678,8.29289322 L11,10.5857864 Z" fill-rule="evenodd"></path></svg>',
				'description' => wpforo_phrase( '%1$s marked as answer, %2$s', false ),
				'before'      => '<li class="wpf-post_answer">',
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
			'itemid'            => null,
			'itemtype'          => null,
			'itemid_second'     => null,
			'userid'            => null,
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
			'date_from'         => null,
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

		// Register activity cleanup cron hook
		add_action( 'wpforo_activity_cleanup', [ $this, 'cron_cleanup_old_activities' ] );

		// Schedule activity cleanup cron on admin init if retention_days > 0 and not already scheduled
		if( is_admin() ) {
			add_action( 'admin_init', [ $this, 'maybe_schedule_cleanup' ] );
		}

		// Activity log entries for new topics and posts (always enabled for Recent Activity page)
		add_action( 'wpforo_after_add_topic', [ $this, 'log_new_topic' ], 10, 2 );
		add_action( 'wpforo_after_add_post', [ $this, 'log_new_post' ], 10, 2 );

		// Log activity when previously unapproved content is approved
		// wpforo_topic_approve fires for first posts (topics)
		// wpforo_post_approve fires for non-first posts (replies)
		add_action( 'wpforo_topic_approve', [ $this, 'log_approved_topic' ] );
		add_action( 'wpforo_post_approve', [ $this, 'log_approved_post' ] );

		// Clean up activities when topics/posts are deleted
		add_action( 'wpforo_after_delete_topic', [ $this, 'after_delete_topic' ] );
		add_action( 'wpforo_after_delete_post', [ $this, 'after_delete_post' ] );

		// Log activity when topic is marked as solved or closed
		add_action( 'wpforo_topic_solved', [ $this, 'log_topic_solved' ], 10, 2 );
		add_action( 'wpforo_topic_closed', [ $this, 'log_topic_closed' ] );

		// Log activity when a post is marked as the answer (Q&A layout)
		add_action( 'wpforo_answer', [ $this, 'log_post_answer' ], 10, 2 );

		// Log activity when a post is bookmarked (favorited)
		add_action( 'wpforo_after_add_bookmark', [ $this, 'log_new_favorite' ] );

		// Activity logging hooks - always enabled for What's New page
		// These create activity entries regardless of notification settings
		add_action( 'wpforo_vote', [ $this, 'after_vote' ], 10, 2 );
		add_action( 'wpforo_react_post', [ &$this, 'after_react' ], 10, 2 );
		add_action( 'wpforo_unreact_post', [ &$this, 'after_unreact' ] );

		// Notification UI features - only when notifications enabled
		if( WPF()->current_userid && wpforo_setting( 'notifications', 'notifications' ) ) {
			if( wpforo_setting( 'notifications', 'notifications_bar' ) ) {
				add_action( 'wpforo_before_search_toggle', [ $this, 'bell' ] );
			}
			add_action( 'wpforo_after_add_post', [ $this, 'after_add_post' ], 10, 2 );
			add_action( 'wpforo_post_status_update', [ $this, 'update_notification' ], 10, 2 );
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
		// Ensure defaults are initialized
		if( ! isset( $this->default->activity ) ) {
			$this->init_defaults();
		}
		return apply_filters( 'wpforo_activty_parse_activity', array_merge( $this->default->activity, $data ) );
	}
	
	private function parse_args( $args ) {
		// Ensure defaults are initialized
		if( ! isset( $this->default->sql_select_args ) ) {
			$this->init_defaults();
		}
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
	
	private function build_sql_select( $args, $count_only = false ) {
		$args = $this->parse_args( $args );

		$wheres = [];

		if( ! is_null( $args['type'] ) ) $wheres[] = "`type` = '" . esc_sql( $args['type'] ) . "'";
		if( ! is_null( $args['itemid'] ) ) $wheres[] = "`itemid` = " . wpforo_bigintval( $args['itemid'] );
		if( ! is_null( $args['itemtype'] ) ) $wheres[] = "`itemtype` = '" . esc_sql( $args['itemtype'] ) . "'";
		if( ! is_null( $args['itemid_second'] ) ) $wheres[] = "`itemid_second` = " . wpforo_bigintval( $args['itemid_second'] );
		if( ! is_null( $args['userid'] ) ) $wheres[] = "`userid` = " . intval( $args['userid'] );
		if( ! is_null( $args['new'] ) ) $wheres[] = "`new` = " . intval( $args['new'] );

		if( ! empty( $args['include'] ) ) $wheres[] = "`id` IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['include'] ) ) . ")";
		if( ! empty( $args['exclude'] ) ) $wheres[] = "`id` NOT IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['exclude'] ) ) . ")";

		if( ! empty( $args['userids_include'] ) ) $wheres[] = "`userid` IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['userids_include'] ) ) . ")";
		if( ! empty( $args['userids_exclude'] ) ) $wheres[] = "`userid` NOT IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['userids_exclude'] ) ) . ")";

		if( ! empty( $args['types_include'] ) ) $wheres[] = "`type` IN('" . implode( "','", array_map( 'esc_sql', $args['types_include'] ) ) . "')";
		if( ! empty( $args['types_exclude'] ) ) $wheres[] = "`type` NOT IN('" . implode( "','", array_map( 'esc_sql', $args['types_exclude'] ) ) . "')";

		if( ! empty( $args['itemids_include'] ) ) $wheres[] = "`itemid` IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['itemids_include'] ) ) . ")";
		if( ! empty( $args['itemids_exclude'] ) ) $wheres[] = "`itemid` NOT IN(" . implode( ',', array_map( 'wpforo_bigintval', $args['itemids_exclude'] ) ) . ")";

		if( ! empty( $args['itemtypes_include'] ) ) $wheres[] = "`itemtype` IN('" . implode( "','", array_map( 'esc_sql', $args['itemtypes_include'] ) ) . "')";
		if( ! empty( $args['itemtypes_exclude'] ) ) $wheres[] = "`itemtype` NOT IN('" . implode( "','", array_map( 'esc_sql', $args['itemtypes_exclude'] ) ) . "')";

		if( ! empty( $args['emails_include'] ) ) $wheres[] = "`email` IN('" . implode( "','", array_map( 'esc_sql', $args['emails_include'] ) ) . "')";
		if( ! empty( $args['emails_exclude'] ) ) $wheres[] = "`email` NOT IN('" . implode( "','", array_map( 'esc_sql', $args['emails_exclude'] ) ) . "')";

		// Date filtering for time period
		if( ! empty( $args['date_from'] ) ) $wheres[] = "`date` >= " . intval( $args['date_from'] );

		if( $count_only ) {
			$sql = "SELECT COUNT(*) FROM " . WPF()->tables->activity;
			if( $wheres ) $sql .= " WHERE " . implode( " AND ", $wheres );
			return $sql;
		}

		$sql = "SELECT * FROM " . WPF()->tables->activity;
		if( $wheres ) $sql .= " WHERE " . implode( " AND ", $wheres );
		$sql .= " ORDER BY " . esc_sql( $args['orderby'] ) . " " . esc_sql( $args['order'] );
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
	
	public function get_activities( $args, &$items_count = 0, $count = true, $check_access = false ) {
		if( ! $args ) return [];

		// If not checking access, use simple query
		if( ! $check_access ) {
			if( $count ) {
				$count_sql   = $this->build_sql_select( $args, true );
				$items_count = (int) WPF()->db->get_var( $count_sql );
			}
			return array_map( [ $this, 'parse_activity' ], (array) WPF()->db->get_results( $this->build_sql_select( $args ), ARRAY_A ) );
		}

		// With access control: fetch and filter, retry if empty (max 10 iterations)
		$row_count       = isset( $args['row_count'] ) ? (int) $args['row_count'] : 20;
		$original_offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$activities      = [];
		$iteration       = 0;
		$max_iterations  = 10;
		$db_has_more     = true; // Assume there's more until proven otherwise

		while( $iteration < $max_iterations ) {
			$args['offset']    = $original_offset + ( $iteration * $row_count );
			$args['row_count'] = $row_count;

			$batch = array_map( [ $this, 'parse_activity' ], (array) WPF()->db->get_results( $this->build_sql_select( $args ), ARRAY_A ) );

			// No more results from database
			if( empty( $batch ) ) {
				$db_has_more = false;
				break;
			}

			// Filter this batch
			$filtered = $this->access_filter( $batch );
			$activities = array_merge( $activities, $filtered );

			// We have enough results
			if( count( $activities ) >= $row_count ) {
				$activities = array_slice( $activities, 0, $row_count );
				break;
			}

			$iteration++;
		}

		if( $count ) {
			// Set items_count to indicate if there might be more activities to load
			// If DB has more or we hit max iterations, assume there could be more
			if( $db_has_more || $iteration >= $max_iterations ) {
				// Set to a high number to trigger "has_more" in pagination
				$items_count = PHP_INT_MAX;
			} else {
				$items_count = count( $activities );
			}
		}

		return $activities;
	}

	/**
	 * Filter activities based on current user access permissions.
	 * Removes activities that the current user doesn't have permission to see.
	 *
	 * @param array $activities List of activities
	 * @return array Filtered activities
	 */
	private function access_filter( $activities ) {
		if( empty( $activities ) ) return [];

		$filtered = [];
		foreach( $activities as $activity ) {
			if( $this->activity_view_access( $activity ) ) {
				$filtered[] = $activity;
			}
		}

		return $filtered;
	}

	/**
	 * Check if current user can view an activity.
	 *
	 * @param array $activity Activity data
	 * @return bool True if user can view, false otherwise
	 */
	private function activity_view_access( $activity ) {
		if( empty( $activity['itemid'] ) ) return false;

		// Determine the post and topic based on activity type
		$post  = null;
		$topic = null;

		if( $activity['type'] === 'wpforo_topic' || $activity['type'] === 'edit_topic' ) {
			// itemid is topicid
			$topic = wpforo_topic( $activity['itemid'] );
			if( ! $topic ) return false;
			// Get first post for the topic
			$post = wpforo_post( wpfval( $topic, 'first_postid' ) );
		} else {
			// itemid is postid for all other activity types
			$post = wpforo_post( $activity['itemid'] );
			if( ! $post ) return false;
			$topic = wpforo_topic( $post['topicid'] );
		}

		if( ! $post || ! $topic ) return false;

		$forumid = (int) wpfval( $post, 'forumid' );
		if( ! $forumid ) return false;

		// Allow users to see their own posts and actions on their own posts
		if( WPF()->current_userid ) {
			// User's own post
			if( (int) wpfval( $post, 'userid' ) === WPF()->current_userid ) {
				return true;
			}
			// User's own topic
			if( (int) wpfval( $topic, 'userid' ) === WPF()->current_userid ) {
				return true;
			}
		}

		// Check forum access (vf - view forum)
		if( ! WPF()->perm->forum_can( 'vf', $forumid ) ) {
			return false;
		}

		// Check view topic access (vt)
		if( ! WPF()->perm->forum_can( 'vt', $forumid ) ) {
			return false;
		}

		// Check view reply access for non-first posts
		if( ! (int) wpfval( $post, 'is_first_post' ) && ! WPF()->perm->forum_can( 'vr', $forumid ) ) {
			return false;
		}

		// Check unapproved post access
		if( (int) wpfval( $post, 'status' ) ) {
			// Post is unapproved (status = 1)
			if( ! WPF()->perm->forum_can( 'au', $forumid ) ) {
				return false;
			}
		}

		// Check private topic access
		if( (int) wpfval( $topic, 'private' ) ) {
			if( ! WPF()->perm->forum_can( 'vp', $forumid ) ) {
				return false;
			}
		}

		return true;
	}

	public function get_activities_count( $args ) {
		if( ! $args ) return 0;

		return (int) WPF()->db->get_var( $this->build_sql_select( $args, true ) );
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

	/**
	 * Log new topic creation for Recent Activity page
	 *
	 * @param array $topic Topic data
	 * @param array $forum Forum data
	 */
	public function log_new_topic( $topic, $forum = [] ) {
		// Don't log unapproved or private topics
		if( ! empty( $topic['status'] ) || ! empty( $topic['private'] ) ) return;

		$data = [
			'type'         => 'wpforo_topic',
			'itemid'       => $topic['topicid'],
			'itemtype'     => 'activity',
			'itemid_second'=> wpfval( $forum, 'forumid' ) ?: wpfval( $topic, 'forumid' ),
			'userid'       => wpfval( $topic, 'userid' ) ?: WPF()->current_userid,
			'name'         => wpfval( $topic, 'name' ) ?: WPF()->current_user_display_name,
			'email'        => wpfval( $topic, 'email' ) ?: WPF()->current_user_email,
			'content'      => wpfval( $topic, 'title' ),
			'permalink'    => wpfval( $topic, 'url' ) ?: WPF()->topic->get_url( $topic['topicid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Log new post/reply creation for Recent Activity page
	 *
	 * @param array $post Post data
	 * @param array $topic Topic data
	 */
	public function log_new_post( $post, $topic = [] ) {
		// Don't log unapproved posts or first posts (those are logged as topics)
		if( ! empty( $post['status'] ) ) return;
		if( ! empty( $post['is_first_post'] ) ) return;

		$data = [
			'type'         => 'wpforo_post',
			'itemid'       => $post['postid'],
			'itemtype'     => 'activity',
			'itemid_second'=> wpfval( $topic, 'topicid' ) ?: wpfval( $post, 'topicid' ),
			'userid'       => wpfval( $post, 'userid' ) ?: WPF()->current_userid,
			'name'         => wpfval( $post, 'name' ) ?: WPF()->current_user_display_name,
			'email'        => wpfval( $post, 'email' ) ?: WPF()->current_user_email,
			'content'      => wpfval( $topic, 'title' ) ?: wpfval( $post, 'title' ),
			'permalink'    => wpfval( $post, 'posturl' ) ?: WPF()->post->get_url( $post['postid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Log activity when a previously unapproved topic is approved
	 * This is called via wpforo_topic_approve hook (for first posts)
	 *
	 * @param array $topic Topic data
	 */
	public function log_approved_topic( $topic ) {
		if( empty( $topic['topicid'] ) ) return;

		// Don't log private topics
		if( ! empty( $topic['private'] ) ) return;

		// Check if activity already exists (avoid duplicates)
		$existing = $this->get_activity( [
			'type'     => 'wpforo_topic',
			'itemid'   => $topic['topicid'],
			'itemtype' => 'activity',
		] );
		if( wpfval($existing, 'id') ) return;

		$forum = WPF()->forum->get_forum( $topic['forumid'] );

		$data = [
			'type'         => 'wpforo_topic',
			'itemid'       => $topic['topicid'],
			'itemtype'     => 'activity',
			'itemid_second'=> wpfval( $forum, 'forumid' ) ?: $topic['forumid'],
			'userid'       => $topic['userid'],
			'name'         => wpfval( $topic, 'name' ) ?: '',
			'email'        => wpfval( $topic, 'email' ) ?: '',
			'content'      => $topic['title'],
			'permalink'    => WPF()->topic->get_url( $topic['topicid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Log activity when a previously unapproved post/reply is approved
	 * This is called via wpforo_post_approve hook (for non-first posts only)
	 *
	 * @param array $post Post data
	 */
	public function log_approved_post( $post ) {
		if( empty( $post['postid'] ) || empty( $post['topicid'] ) ) return;

		// Skip first posts - they are handled by log_approved_topic via wpforo_topic_approve
		if( ! empty( $post['is_first_post'] ) ) return;

		// Check if activity already exists (avoid duplicates)
		$existing = $this->get_activity( [
			'type'     => 'wpforo_post',
			'itemid'   => $post['postid'],
			'itemtype' => 'activity',
		] );
		if( wpfval($existing, 'id') ) return;

		$topic = WPF()->topic->get_topic( $post['topicid'] );

		$data = [
			'type'         => 'wpforo_post',
			'itemid'       => $post['postid'],
			'itemtype'     => 'activity',
			'itemid_second'=> $post['topicid'],
			'userid'       => $post['userid'],
			'name'         => wpfval( $post, 'name' ) ?: '',
			'email'        => wpfval( $post, 'email' ) ?: '',
			'content'      => wpfval( $topic, 'title' ) ?: '',
			'permalink'    => WPF()->post->get_url( $post['postid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Delete activities when a topic is deleted
	 *
	 * @param array $topic Topic data
	 */
	public function after_delete_topic( $topic ) {
		if( empty( $topic['topicid'] ) ) return;

		// Delete the topic activity entry
		$this->delete( [
			'itemid' => $topic['topicid'],
			'type'   => 'wpforo_topic',
		] );

		// Also delete any activities that reference this topic via itemid_second
		// (e.g., reactions, favorites, solved markers on posts within this topic)
		WPF()->db->delete(
			WPF()->tables->activity,
			[ 'itemid_second' => $topic['topicid'] ],
			[ '%d' ]
		);
	}

	/**
	 * Delete activities when a post is deleted
	 *
	 * @param array $post Post data
	 */
	public function after_delete_post( $post ) {
		if( empty( $post['postid'] ) ) return;

		// Delete all activities related to this post (replies, reactions, etc.)
		$this->delete( [ 'itemid' => $post['postid'] ] );
	}

	/**
	 * Log activity when a topic is marked as solved
	 *
	 * @param array $topic Topic data
	 * @param array $post  Post data (the answer post)
	 */
	public function log_topic_solved( $topic, $post = [] ) {
		if( empty( $topic['topicid'] ) ) return;

		// Check for existing activity to prevent duplicates
		$existing = $this->get_activity( [
			'type'     => 'topic_solved',
			'itemid'   => $topic['topicid'],
			'itemtype' => 'activity',
		] );
		if( wpfval($existing, 'id') ) return;

		$data = [
			'type'          => 'topic_solved',
			'itemid'        => $topic['topicid'],
			'itemtype'      => 'activity',
			'itemid_second' => WPF()->current_userid,
			'userid'        => $topic['userid'],
			'name'          => WPF()->current_user_display_name,
			'email'         => WPF()->current_user_email,
			'content'       => $topic['title'],
			'permalink'     => WPF()->topic->get_url( $topic['topicid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Log activity when a topic is closed
	 *
	 * @param array $topic Topic data
	 */
	public function log_topic_closed( $topic ) {
		if( empty( $topic['topicid'] ) ) return;

		// Check for existing activity to prevent duplicates
		$existing = $this->get_activity( [
			'type'     => 'topic_closed',
			'itemid'   => $topic['topicid'],
			'itemtype' => 'activity',
		] );
		if( wpfval($existing, 'id') ) return;

		$data = [
			'type'          => 'topic_closed',
			'itemid'        => $topic['topicid'],
			'itemtype'      => 'activity',
			'itemid_second' => WPF()->current_userid,
			'userid'        => $topic['userid'],
			'name'          => WPF()->current_user_display_name,
			'email'         => WPF()->current_user_email,
			'content'       => $topic['title'],
			'permalink'     => WPF()->topic->get_url( $topic['topicid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Log activity when a post is marked as the answer (Q&A layout)
	 *
	 * @param int   $status Answer status (1 = marked as answer, 0 = unmarked)
	 * @param array $post   Post data
	 */
	public function log_post_answer( $status, $post ) {
		// Only log when marking as answer, not when unmarking
		if( ! $status || empty( $post['postid'] ) ) return;

		$topic = WPF()->topic->get_topic( $post['topicid'] );
		if( ! $topic ) return;

		// Check for existing activity to prevent duplicates
		$existing = $this->get_activity( [
			'type'     => 'post_answer',
			'itemid'   => $post['postid'],
			'itemtype' => 'activity',
		] );
		if( wpfval($existing, 'id') ) return;

		$data = [
			'type'          => 'post_answer',
			'itemid'        => $post['postid'],
			'itemtype'      => 'activity',
			'itemid_second' => WPF()->current_userid,
			'userid'        => $post['userid'],
			'name'          => WPF()->current_user_display_name,
			'email'         => WPF()->current_user_email,
			'content'       => $topic['title'],
			'permalink'     => WPF()->post->get_url( $post['postid'] ),
		];

		$this->add( $data );
	}

	/**
	 * Log activity when a post is bookmarked (favorited)
	 *
	 * @param array $bookmark Bookmark data with postid, userid, etc.
	 */
	public function log_new_favorite( $bookmark ) {
		if( empty( $bookmark['postid'] ) ) return;

		$post = WPF()->post->get_post( $bookmark['postid'] );
		if( ! $post ) return;

		$topic = WPF()->topic->get_topic( $post['topicid'] );
		if( ! $topic ) return;

		// Check for existing activity to prevent duplicates (same user favoriting same post)
		$existing = $this->get_activity( [
			'type'          => 'new_favorite',
			'itemid'        => $bookmark['postid'],
			'itemid_second' => $bookmark['userid'],
			'itemtype'      => 'activity',
		] );
		if( wpfval($existing, 'id') ) return;

		$member = wpforo_member( $bookmark['userid'] );

		$data = [
			'type'          => 'new_favorite',
			'itemid'        => $bookmark['postid'],
			'itemtype'      => 'activity',
			'itemid_second' => $bookmark['userid'],
			'userid'        => $post['userid'],
			'name'          => wpfval( $member, 'display_name' ) ?: '',
			'email'         => wpfval( $member, 'user_email' ) ?: '',
			'content'       => $topic['title'],
			'permalink'     => WPF()->post->get_url( $bookmark['postid'] ),
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
		if( wpforo_is_id( $where ) ) $where = [ 'id' => $where ];
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

	/**
	 * Delete an activity by ID (public method with permission check)
	 *
	 * @param int $activity_id Activity ID to delete
	 * @return bool True on success, false on failure
	 */
	public function delete_activity( $activity_id ): bool {
		$activity_id = wpforo_bigintval( $activity_id );
		if( ! $activity_id ) return false;

		// Permission check - must have moderation permission
		if( ! WPF()->usergroup->can( 'aum' ) ) {
			return false;
		}

		return $this->delete( [ 'id' => $activity_id ] );
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
		$args = [ 'itemtype' => 'alert', 'userid' => WPF()->current_userid, 'new' => 1, 'row_count' => 100, 'orderby' => 'date', 'order' => 'DESC' ];
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
		$where  = [
			'id'     => $id,
			'userid' => $userid,
		];
		// Mark as read instead of deleting (preserves activity for Recent Activity page)
		$this->edit( [ 'new' => 0 ], $where );
	}
	
	public function clear_notifications( $userid = null ) {
		$userid = is_null( $userid ) ? WPF()->current_userid : $userid;
		$where  = [
			'userid'   => $userid,
			'itemtype' => 'alert',
		];
		// Mark all as read instead of deleting (preserves activity for Recent Activity page)
		$this->edit( [ 'new' => 0 ], $where );
	}

	/**
	 * Delete activities older than specified number of days
	 *
	 * @param int $days Number of days to retain activities
	 *
	 * @return int Number of deleted activities
	 */
	public function cleanup_old_activities( int $days ): int {
		if( $days <= 0 ) {
			return 0;
		}

		// The date field is stored as Unix timestamp (integer)
		$cutoff_timestamp = strtotime( "-{$days} days" );

		$result = WPF()->db->query(
			WPF()->db->prepare(
				"DELETE FROM `" . WPF()->tables->activity . "` WHERE `date` < %d",
				$cutoff_timestamp
			)
		);

		return $result !== false ? (int) $result : 0;
	}

	/**
	 * Cron callback for activity cleanup
	 * Called daily by WordPress cron if scheduled
	 */
	public function cron_cleanup_old_activities(): void {
		$retention_days = (int) wpforo_setting( 'activity', 'retention_days' );

		// If 0, auto-cleanup is disabled
		if( $retention_days <= 0 ) {
			return;
		}

		$deleted = $this->cleanup_old_activities( $retention_days );

		if( $deleted > 0 ) {
			\wpforo_ai_log( 'info', "Cron cleanup: deleted {$deleted} activities older than {$retention_days} days", 'Activity' );
		}
	}

	/**
	 * Schedule activity cleanup cron if not already scheduled
	 */
	public function schedule_cleanup(): void {
		if( ! wp_next_scheduled( 'wpforo_activity_cleanup' ) ) {
			// Schedule to run at 6 AM server time (off-peak hours)
			wp_schedule_event( strtotime( 'tomorrow 6:00am' ), 'daily', 'wpforo_activity_cleanup' );
		}
	}

	/**
	 * Unschedule activity cleanup cron
	 */
	public function unschedule_cleanup(): void {
		$timestamp = wp_next_scheduled( 'wpforo_activity_cleanup' );
		if( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpforo_activity_cleanup' );
		}
	}

	/**
	 * Check and schedule cleanup cron on admin init if needed
	 * This ensures first-time setup when viewing activity settings
	 */
	public function maybe_schedule_cleanup(): void {
		// Only run on wpForo settings pages
		if( empty( $_GET['page'] ) || strpos( $_GET['page'], 'wpforo' ) === false ) {
			return;
		}

		$retention_days = (int) wpforo_setting( 'activity', 'retention_days' );

		if( $retention_days > 0 ) {
			$this->schedule_cleanup();
		}
	}

}
