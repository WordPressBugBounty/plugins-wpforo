<?php

namespace wpforo\modules\reactions\classes;

use wpforo\modules\reactions\Reactions;

class Template {
	public function __construct() {
		$this->init_hooks();
	}
	
	private function init_hooks() {
		add_action( 'wpforo_post_footer_start', function( $post ) {
			echo $this->like_button( $post );
		} );
		add_action( 'wpforo_post_content_end', function( $post ) {
			echo $this->like_button( $post );
		} );
		add_action( 'wpforo_post_bottom_start', function( $post, $topic, $forum, $layout ) {
			if( $layout === 1 ) echo $this->like_button( $post ) . '&nbsp;&nbsp;&nbsp;';
		},          1, 4 );
		add_action( 'wpforo_post_bottom_start', function( $post, $topic, $forum, $layout ) {
			if( $layout === 1 ) echo '<div class="reacted-users">' . $this->likers( $post['postid'] ) . '</div>';
		},          2, 4 );
		add_action( 'wpforo_post_bottom_end', function( $post ) {
			echo '<div class="reacted-users">' . $this->likers( $post['postid'] ) . '</div>';
		} );
		add_action( 'wpforo_post_footer_bottom_start', function( $post ) {
			echo '<div class="reacted-users">' . $this->likers( $post['postid'] ) . '</div>';
		} );
	}
	
	private function get_button_by_type( $type ) {
		$types = Reactions::get_types();
		if( $_type = wpfval( $types, $type ) ) return $_type['icon'];
		
		return '<i class="far fa-thumbs-up"></i>';
	}
	
	public function like_button( $post, $userid = 0 ): string {
		if( ! WPF()->current_userid || wpforo_is_owner( $post['userid'], $post['email'] ) || ! WPF()->perm->forum_can( 'l', (int) wpfval( $post, 'forumid' ) ) ) return '';
		$reaction = WPF()->reaction->get_user_reaction( $post['postid'], $userid );
		$type     = wpfval( $reaction, 'type' );
		$all      = [];
		foreach( array_reverse( Reactions::get_types() ) as $key => $_type ) {
			$all[ $key ] = sprintf(
				'<span class="%1$s wpf-react-%2$s" data-type="%2$s">%3$s</span>',
				( $type !== $key ? 'wpf-react' : '' ),
				$key,
				$_type['icon']
			);
		}
		
		return sprintf(
			'<div class="wpf-reaction-wrap"><div class="wpforo-reaction wpf-popover" aria-haspopup="true" data-currentstate="%1$s">
				<span class="wpf-current-reaction %2$s" data-type="%1$s">%3$s</span>
				<div class="wpf-popover-content">%4$s</div>
			</div></div>',
			$type,
			( ! $type ? 'wpf-react wpf-unreacted' : 'wpf-unreact wpf-react-' . $type ),
			$this->get_button_by_type( $type ),
			implode( '', $all )
		);
	}
	
	public function likers( $postid ): string {
		if( ! $postid ) return '';
		
		$likers = apply_filters( 'wpforo_reactions_likers_start', null, $postid );
		if( ! is_null( $likers ) ) return (string) $likers;
		
		$l_count     = wpforo_post( $postid, 'likes_count' );
		$l_usernames = wpforo_post( $postid, 'likers_usernames' );
		$return      = '';
		
		if( $l_count && $l_usernames ) {
			if( $l_usernames[0]['userid'] == WPF()->current_userid ) $l_usernames[0]['dname'] = wpforo_phrase( 'You', false );
			if( $l_count === 1 ) {
				$return = sprintf(
					wpforo_phrase( '%s reacted', false ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[0]['userid'] ), '', 30, '', false, $l_usernames[0]['dname'] )
				);
			} elseif( $l_count == 2 ) {
				$return = sprintf(
					wpforo_phrase( '%s and %s reacted', false ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[0]['userid'] ), '', 30, '', false, $l_usernames[0]['dname'] ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[1]['userid'] ), '', 30, '', false, $l_usernames[1]['dname'] )
				);
			} elseif( $l_count === 3 ) {
				$return = sprintf(
					wpforo_phrase( '%s, %s and %s reacted', false ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[0]['userid'] ), '', 30, '', false, $l_usernames[0]['dname'] ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[1]['userid'] ), '', 30, '', false, $l_usernames[1]['dname'] ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[2]['userid'] ), '', 30, '', false, $l_usernames[2]['dname'] )
				);
			} elseif( $l_count >= 4 ) {
				$return = sprintf(
					wpforo_phrase( '%s, %s, %s and %d people reacted', false ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[0]['userid'] ), '', 30, '', false, $l_usernames[0]['dname'] ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[1]['userid'] ), '', 30, '', false, $l_usernames[1]['dname'] ),
					wpforo_member_link( WPF()->member->get_member( $l_usernames[2]['userid'] ), '', 30, '', false, $l_usernames[2]['dname'] ),
					( $l_count - 3 )
				);
			}
		}
		
		return (string) apply_filters( 'wpforo_reactions_likers_end', $return, $postid, $l_count, $l_usernames );
	}
}
