<?php

namespace wpforo\modules\reactions\classes;

use wpforo\modules\reactions\Reactions;

class Actions {
	public function __construct() {
		$this->init_hooks();
	}
	
	private function init_hooks() {
		add_action( 'wpforo_after_delete_user', [ $this, 'after_delete_user' ], 10, 2 );
		add_action( 'wpforo_after_delete_post', [ $this, 'after_delete_post' ] );
		add_filter( 'wpforo_member_get_points_other_points', [ $this, 'calc_member_points' ], 10, 2 );
		
		#### -- AJAX ACTIONS -- ###
		add_action( 'wp_ajax_wpforo_vote_ajax', [ $this, 'vote' ] );
		
		add_action( 'wp_ajax_wpforo_react', [ $this, 'react' ] );
		add_action( 'wp_ajax_wpforo_unreact', [ $this, 'unreact' ] );
	}
	
	public function after_delete_user( $userid, $reassign ) {
		if( $reassign ) WPF()->reaction->edit_for_all_active_boards( [ 'post_userid' => $reassign ], [ 'post_userid' => $userid ] );
		WPF()->reaction->delete_for_all_active_boards( [ 'userid' => $userid ] );
	}
	
	public function after_delete_post( $post ) {
		WPF()->reaction->delete( [ 'postid' => $post['postid'] ] );
	}
	
	public function calc_member_points( $other_points, $member ) {
		$reaction_types = Reactions::get_types();
		$rpoints        = 0;
		foreach( wpfval( $member, 'reactions_in' ) as $key => $point ) {
			if( ! ( $point = intval( $point ) ) || in_array( $key, [ 'up', 'down' ] ) ) continue;
			if( $type = wpfval( $reaction_types, $key ) ) $rpoints += ( $point * floatval( $type['reaction'] ) );
		}
		
		return $other_points + $rpoints;
	}
	
	public function vote() {
		wpforo_verify_nonce( 'wpforo_vote_ajax' );
		if( ! WPF()->current_userid ) {
			WPF()->notice->add( wpforo_get_login_or_register_notice_text() );
			wp_send_json_error( [ 'notice' => WPF()->notice->get_notices() ] );
		}
		
		if( ! $postid = wpforo_bigintval( wpfval( $_POST, 'postid' ) ) ) {
			WPF()->notice->add( 'Wrong post data', 'error' );
			wp_send_json_error( [ 'notice' => WPF()->notice->get_notices() ] );
		}
		
		$votestatus = wpfval( $_POST, 'votestatus' );
		if( $votestatus === 'clear' ) {
			$votes = WPF()->post->clear_user_post_votes( $postid );
			WPF()->notice->add( 'You have removed your vote', 'success' );
			wp_send_json_success( [ 'votes' => $votes, 'notice' => WPF()->notice->get_notices() ] );
		} else {
			$reaction = $votestatus === 'down' ? - 1 : 1;
			
			if( WPF()->post->has_user_voted_post( $postid, $reaction ) ) {
				WPF()->notice->add( 'You are already voted this post' );
				wp_send_json_error( [ 'notice' => WPF()->notice->get_notices() ] );
			} else {
				WPF()->post->clear_user_post_votes( $postid );
			}
			
			if( false !== ( $votes = WPF()->post->vote_post( $postid, $reaction ) ) ) {
				wp_send_json_success( [ 'votes' => $votes, 'notice' => WPF()->notice->get_notices() ] );
			}
		}
		
		WPF()->notice->add( 'Wrong post data', 'error' );
		wp_send_json_error( [ 'notice' => WPF()->notice->get_notices() ] );
	}
	
	public function react() {
		wpforo_verify_nonce( 'wpforo_react' );
		if( ( $userid = WPF()->current_userid ) && ( $postid = wpfval( $_POST, 'postid' ) ) ) {
			$types = Reactions::get_types();
			if( ! ( $type = wpfval( $_POST, 'type' ) ) || ! wpfkey( $types, $type ) ) $type = 'up';
			
			$is_reacted = WPF()->reaction->is_reacted( $postid, $userid, $type );
			if( ! $is_reacted ) {
				$post     = wpforo_post( $postid );
				$_type    = wpfval( $types, $type );
				$reaction = [
					'postid'      => $postid,
					'post_userid' => wpforo_bigintval( wpfval( $post, 'userid' ) ),
					'userid'      => $userid,
					'type'        => $type,
					'reaction'    => (int) wpfval( $_type, 'reaction' ),
				];
				WPF()->reaction->delete(
					[
						'postid' => $postid,
						'userid' => $userid,
					]
				);
				if( WPF()->reaction->add( $reaction ) ) {
					wpforo_clean_cache( 'post-soft', $postid );
					
					do_action( 'wpforo_react_post', $reaction, $post );
					
					##############################
					if( $type === 'up' ) {
						/*
						 * @deprecated 2.3.4
						 */
						do_action( 'wpforo_undo_dislike', $post, WPF()->current_userid );
						/*
						 * @deprecated 2.3.4
						 */
						do_action( 'wpforo_like', $post, WPF()->current_userid );
					} elseif( $type === 'down' ) {
						/*
						 * @deprecated 2.3.4
						 */
						do_action( 'wpforo_undo_like', $post, WPF()->current_userid );
						/*
						 * @deprecated 2.3.4
						 */
						do_action( 'wpforo_dislike', $post, WPF()->current_userid );
					} else {
						//unknown like type
						/*
						 * @deprecated 2.3.4
						 */
						do_action( 'wpforo_like', $post, WPF()->current_userid );
					}
					#######################
					
					wp_send_json_success(
						[
							'like_button' => WPF()->reaction->Template->like_button( $post, $userid ),
							'likers'      => WPF()->reaction->Template->likers( $postid ),
						]
					);
				}
			}
		}
		
		wp_send_json_error();
	}
	
	public function unreact() {
		wpforo_verify_nonce( 'wpforo_unreact' );
		if( ( $userid = WPF()->current_userid ) && ( $postid = wpfval( $_POST, 'postid' ) ) ) {
			$post = wpforo_post( $postid );
			$args = [
				'postid' => $postid,
				'userid' => $userid,
			];
			WPF()->reaction->delete( $args );
			wpforo_clean_cache( 'post-soft', $postid );
			
			do_action( 'wpforo_unreact_post', $args );
			
			#################
			/*
			* @deprecated 2.3.4
			*/
			do_action( 'wpforo_undo_like', $post, WPF()->current_userid );
			/*
			* @deprecated 2.3.4
			*/
			do_action( 'wpforo_undo_dislike', $post, WPF()->current_userid );
			#################
			
			wp_send_json_success(
				[
					'like_button' => WPF()->reaction->Template->like_button( $post, $userid ),
					'likers'      => WPF()->reaction->Template->likers( $postid ),
				]
			);
		}
		
		wp_send_json_error();
	}
}
