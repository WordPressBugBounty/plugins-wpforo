<?php

namespace wpforo\modules\follows\classes;

class Actions {
	public function __construct() {
		$this->init_hooks();
	}
	
	private function init_hooks() {
		add_action( 'wpforo_action_followconfirm', [ $this, 'confirmation' ] );
		add_action( 'wpforo_action_unfollow', [ $this, 'unfollow' ] );
		
		add_action( 'wpforo_after_add_topic', [ $this, 'after_add_topic' ], 7, 2 );
		add_action( 'wpforo_after_add_post', [ $this, 'after_add_post' ], 7, 2 );
		
		add_action( 'wpforo_post_approve', [ $this, 'after_post_approve' ], 7 );
		add_action( 'wpforo_after_delete_user', [ $this, 'after_delete_user' ] );
		
		## -- AJAX ACTIONS -- ##
		add_action( 'wp_ajax_wpforo_follow_unfollow_user', [ $this, 'follow_unfollow_user' ] );
	}
	
	public function confirmation() {
		if( $confirmkey = wpfval( WPF()->GET, 'key' ) ) WPF()->sbscrb->Follows->confirm( sanitize_text_field( $confirmkey ) );
		
		wp_safe_redirect( wpforo_url( '', 'members' ) );
		exit();
	}
	
	public function unfollow() {
		if( $confirmkey = wpfval( WPF()->GET, 'key' ) ) {
			if( WPF()->sbscrb->Follows->unfollow_by_key( sanitize_text_field( $confirmkey ) ) ) {
				WPF()->notice->add( 'Unfollow Done', 'success' );
			} else {
				WPF()->notice->add( 'Unfollow Error', 'error' );
			}
		}
		
		wp_safe_redirect( wpforo_url( '', 'members' ) );
		exit();
	}
	
	public function after_add_topic( $topic, $forum ) {
		if( ! wpfval( $topic, 'url' ) ) $topic['url'] = wpforo_topic( $topic['topicid'], 'url' );
		if( ! wpfkey( $topic, 'body' ) ) $topic['body'] = wpforo_post( $topic['first_postid'], 'body' );
		$owner = wpforo_member( $topic );
		
		$follows = WPF()->sbscrb->Follows->get_follows( [ 'itemid' => $topic['userid'], 'itemtype' => 'user', 'active' => true ] );
		foreach( $follows as $follow ) {
			if( $follow['userid'] ) {
				$user = WPF()->member->get_member( $follow['userid'] );
			} elseif( $follow['email'] ) {
				$user = [ 'groupid' => 4, 'groupids' => [ 4 ], 'display_name' => ( $follow['name'] ?: $follow['email'] ), 'user_email' => $follow['email'] ];
			} else {
				continue;
			}
			
			if( apply_filters( 'break_wpforo_follow_send_email_after_add_topic', false, $user, $owner, $topic, $forum ) ) continue;
			
			$key = 'topicid_' . $topic['topicid'] . '_user_email_' . $user['user_email'];
			if( ! WPF()->ram_cache->exists( $key ) ) {
				
				if( in_array( wpfval( $user, 'status' ), [ 'banned', 'inactive' ], true ) ) continue;
				if( wpforo_is_users_same( $owner, $user ) ) continue;
				if( wpforo_is_users_same( $user ) ) continue;
				if( ! WPF()->topic->view_access( $topic, $user ) ) continue;
				
				$sbj_msg = WPF()->sbscrb->get_sbj_msg( 'user_follow', $forum, $topic, $owner, $user, $follow['unfollow_link'] );
				wpforo_send_email( $user['user_email'], $sbj_msg['sbj'], $sbj_msg['msg'] );
				
				WPF()->ram_cache->set( $key, true );
			}
		}
	}
	
	public function after_add_post( $post, $topic ) {
		$owner   = wpforo_member( $post );
		$follows = WPF()->sbscrb->Follows->get_follows( [ 'itemid' => $post['userid'], 'itemtype' => 'user', 'active' => true ] );
		foreach( $follows as $follow ) {
			if( $follow['userid'] ) {
				$user = WPF()->member->get_member( $follow['userid'] );
			} elseif( $follow['user_email'] ) {
				$user = [ 'groupid' => 4, 'groupids' => [ 4 ], 'display_name' => ( $follow['name'] ?: $follow['email'] ), 'user_email' => $follow['email'] ];
			} else {
				continue;
			}
			
			if( apply_filters( 'break_wpforo_follow_send_email_after_add_post', false, $user, $owner, $post, $topic ) ) continue;
			
			$key = 'postid_' . $post['postid'] . '_user_email_' . $user['user_email'];
			if( ! WPF()->ram_cache->exists( $key ) ) {
				
				if( in_array( wpfval( $user, 'status' ), [ 'banned', 'inactive' ], true ) ) continue;
				if( wpforo_is_users_same( $owner, $user ) ) continue;
				if( wpforo_is_users_same( $user ) ) continue;
				if( ! WPF()->post->view_access( $post, $user ) ) continue;
				
				$sbj_msg = WPF()->sbscrb->get_sbj_msg( 'user_follow', $topic, $post, $owner, $user, $follow['unfollow_link'] );
				wpforo_send_email( $user['user_email'], $sbj_msg['sbj'], $sbj_msg['msg'] );
				
				WPF()->ram_cache->set( $key, true );
			}
		}
	}
	
	public function after_post_approve( $post ) {
		if( ( $topicid = wpforo_bigintval( wpfval( $post, 'topicid' ) ) ) && ( $topic = WPF()->topic->get_topic( $topicid ) ) ) {
			$this->after_add_post( $post, $topic );
		}
	}
	
	public function after_delete_user( $userid ) {
		WPF()->sbscrb->Follows->delete( [ 'itemid' => $userid, 'itemtype' => 'user' ] );
		WPF()->sbscrb->Follows->delete( [ 'userid' => $userid ] );
	}
	
	
	### -- AJAX ACTIONS -- ###
	
	public function follow_unfollow_user() {
		wpforo_verify_nonce( 'wpforo_follow_unfollow_user' );
		if( WPF()->current_userid && ( $userid = wpforo_bigintval( wpfval( $_POST, 'userid' ) ) ) ) {
			if( WPF()->current_userid !== $userid ) {
				if( (int) wpfval( $_POST, 'stat' ) ) {
					if( WPF()->sbscrb->Follows->follow( WPF()->current_userid, $userid ) ) {
						wp_send_json_success( [
							                      'stat'            => 1,
							                      'phrase'          => wpforo_phrase( 'Unfollow', false ),
							                      'followers_count' => WPF()->sbscrb->Follows->get_count( [ 'itemid' => $userid, 'itemtype' => 'user', 'active' => true ] ),
							                      'notice'          => wpforo_phrase( 'done', false ),
						                      ] );
					}
				} else {
					if( WPF()->sbscrb->Follows->unfollow( WPF()->current_userid, $userid ) ) {
						wp_send_json_success( [
							                      'stat'            => 0,
							                      'phrase'          => wpforo_phrase( 'Follow', false ),
							                      'followers_count' => WPF()->sbscrb->Follows->get_count( [ 'itemid' => $userid, 'itemtype' => 'user', 'active' => true ] ),
							                      'notice'          => wpforo_phrase( 'done', false ),
						                      ] );
					}
				}
			} else {
				wp_send_json_error( [ 'notice' => wpforo_phrase( 'Self following not allowed!', false ) ] );
			}
		}
		
		wp_send_json_error( [ 'notice' => wpforo_phrase( 'You are not logged in or sent wrong data!', false ) ] );
	}
}
