<?php

namespace wpforo\classes;

use FilesystemIterator;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

class Cache {
	public $dir = '';
	public $locale = '';
	public $special_key = '';
	private $touched_postids = [];

	function __construct() {
		add_action( 'wpforo_after_init_folders', function( $folders ) {
			$cache_dir = apply_filters('wpforo_cache_dir', $folders['cache']['dir'], $folders);
			if( ! is_dir( $cache_dir ) ) $this->dir( $cache_dir );
			if( ! is_dir( $cache_dir . DIRECTORY_SEPARATOR . 'tag' ) ) $this->mkdir( $cache_dir . DIRECTORY_SEPARATOR . 'tag' );
			if( ! is_dir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'tag' ) ) $this->mkdir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'tag' );
			if( ! is_dir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'url' ) ) $this->mkdir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'url' );
			if( ! is_dir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'avatar' ) ) $this->mkdir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'avatar' );
			if( ! is_dir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'reaction' ) ) $this->mkdir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'reaction' );
			if( ! is_dir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'option' ) ) $this->mkdir( $cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'option' );

			$this->dir = $cache_dir;
		} );
		
		if( apply_filters( 'wpforo_cache_by_usergroup', false ) ){
			add_action( 'wpforo_after_init_current_user', function (){
				$this->set_special_key();
			} );
		}

		add_action( 'wpforo_after_set_locale', function( $locale ) {
			$this->locale = $locale;
			$this->set_special_key();
		} );

		add_action( 'wpforo_after_add_board', function( $board ){
			if( $board['status'] ){
				WPF()->change_board( $board['boardid'] );
				wpforo_clean_cache();
			}
		} );

		add_action( 'wpforo_after_edit_board', function( $boardid ){
			$board = WPF()->board->_get_board( $boardid );
			if( wpfval($board, 'status' ) ){
				WPF()->change_board( $boardid );
				wpforo_clean_cache();
			}
		} );

		add_action( 'wpforo_after_add_reaction',     [ $this, 'after_add_reaction' ] );
		add_action( 'wpforo_after_edit_reaction',    [ $this, 'after_edit_reaction' ] );
		add_action( 'wpforo_before_delete_reaction', [ $this, 'before_delete_reaction' ], 10, 2 );
		add_action( 'wpforo_after_delete_reaction',  [ $this, 'after_delete_reaction' ] );
	}
	
	private function set_special_key(): void {
		$this->special_key = (
			apply_filters( 'wpforo_cache_by_usergroup', false )
				? implode( '-', WPF()->current_user_groupids ) . '-' : ''
	 	) . $this->locale;
	}

	public function get_key( $type = 'html' ) {
		if( $type === 'html' ) {
			$ug = WPF()->current_user_groupid;

			return md5( preg_replace( '#(.+)\#.+?$#is', '$1', (string) $_SERVER['REQUEST_URI'] ) . $ug );
		}
	}

	private function dir( $cache_dir ) {
		$dirs = [
			$cache_dir,
			$cache_dir . DIRECTORY_SEPARATOR . 'forum',
			$cache_dir . DIRECTORY_SEPARATOR . 'topic',
			$cache_dir . DIRECTORY_SEPARATOR . 'post',
			$cache_dir . DIRECTORY_SEPARATOR . 'tag',
			$cache_dir . DIRECTORY_SEPARATOR . 'item',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'forum',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'topic',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'post',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'tag',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'url',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'avatar',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'reaction',
			$cache_dir . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'option',
		];
		$this->mkdir( $dirs );
	}

	private function mkdir( $dirs ) {
		foreach( (array) $dirs as $dir ) {
			wp_mkdir_p( $dir );
			wpforo_write_file( $dir . '/index.html', '' );
			wpforo_write_file( $dir . '/.htaccess', 'deny from all' );
		}
	}

	public function on( $type = 'all' ){
        if( $type !== 'all' ){
            if( $type === 'forum' && !apply_filters( 'wpforo_cache_forum', true ) ) return false;
            if( $type === 'topic' && !apply_filters( 'wpforo_cache_topic', true ) ) return false;
            if( $type === 'post' && !apply_filters( 'wpforo_cache_post', true ) ) return false;
            if( $type === 'tag' && !apply_filters( 'wpforo_cache_tag', true ) ) return false;
            if( $type === 'url' && !apply_filters( 'wpforo_cache_url', true ) ) return false;
            if( $type === 'reaction' && !apply_filters( 'wpforo_cache_reaction', true ) ) return false;
            if( $type === 'avatar' && !apply_filters( 'wpforo_cache_avatar', true ) ) return false;
            if( $type === 'option' && !apply_filters( 'wpforo_cache_option', true ) ) return false;
        }
        return wpforo_setting( 'board', 'cache' );
	}

	public function get( $key, $type = 'loop', $template = null ) {
		$template       = ( $template ) ?: WPF()->current_object['template'];
		$loop_templates = [ 'forum', 'topic', 'post', 'tag' ];
		if( $type === 'loop' && $template ) {
			if( $this->exists( $key, $template ) ) {
				if( in_array( $template, $loop_templates ) ) {
					$cache_file = $this->dir . '/' . $template . '/' . $key;
					$array      = wpforo_get_file_content( $cache_file );

					return @unserialize( $array );
				}
			}
		}
	}

	public function get_item( $id, $type = 'post', $sub_type = '' ) {
		if( $id ) {
			$key = $id . '_' . $this->special_key;
            $domain_path = preg_replace('|https?://|i', '', get_site_url() );
			if( $this->exists( $key, 'item', $type, $sub_type ) ) {
				$cache_file = $this->dir . '/item/' . $type . '/' . ( $sub_type ? $sub_type . '_' : '' ) . $key;
				$array      = wpforo_get_file_content( $cache_file );
                $data = @unserialize( $array );
                if( $type === 'url' ) {
                    // Always make sure the cached URLs are pointed to current website
                    if( strpos( (string) $data, $domain_path ) === FALSE ) return null;
                }
                return $data;
			}
		}
        return null;
	}

	public function get_html() {
		$template = WPF()->current_object['template'];
		if( $template == 'forum' ) {
			$key = $this->get_key();
			if( $this->exists( $key, $template ) ) {
				$cache_file = $this->dir . '/' . $template . '/' . $key;
				$html       = wpforo_get_file_content( $cache_file );

				return $this->filter( $html );
			}
		}

		return false;
	}

	public function html( $content ) {
		/*$template = WPF()->current_object['template'];
		if( $template === 'forum' ){
			$key = $this->get_key();
			$this->create_html( $content, $template, $key );
		}*/
	}

	public function create( $mode = 'loop', $cache = [], $type = 'post' ) {
		if( ! $this->on() ) return false;
		$template = WPF()->current_object['template'];
		if( $template == 'forum' ) {
			$this->check( $this->dir . '/item/post' );
		}

		if( $mode === 'loop' && $template ) {
			if( wpfval( $cache, 'tags' ) ) {
				$this->create_files( $cache['tags'], 'tag' );
			}
			if( $template === 'forum' || $template === 'topic' || $template === 'post' ) {
				$cache = WPF()->forum->get_cache( 'forums' );
				$this->create_files( $cache, $template );
				$cache = WPF()->topic->get_cache( 'topics' );
				$this->create_files( $cache, $template );
				$cache = WPF()->post->get_cache( 'posts' );
				$this->create_files( $cache, $template );
			}
		} elseif( $mode === 'item' && ! empty( $cache ) ) {
			$this->create_files( $cache, 'item', $type );
		}
	}

	public function create_files( $cache = [], $template = '', $type = '' ) {
		if( ! empty( $cache ) ) {
			$type = ( $type ) ? $type . '/' : '';
			foreach( $cache as $key => $object ) {
				if( $template === 'item' ) $key = $key . '_' . $this->special_key;
				if( ! $this->exists( $key, $template ) ) {
					$object = serialize( $object );
					wpforo_write_file( $this->dir . '/' . $template . '/' . $type . $key, $object );
				}
			}
		}
	}

	public function create_html( $content, $template = '', $key = '' ) {
		if( $content ) {
			if( ! $this->exists( $key, $template ) ) {
				wpforo_write_file( $this->dir . '/' . $template . '/' . $key, $content );
			}
		}
	}

	public function create_custom( $args = [], $items = [], $template = 'post', $items_count = 0 ) {
		if( empty( $args ) || ! is_array( $args ) ) return;
		if( empty( $items ) || ! is_array( $items ) ) return;
		$cache                               = [];
		$hach                                = serialize( $args );
		$object_key                          = md5( $hach . WPF()->current_user_groupid );
		$cache[ $object_key ]['items']       = $items;
		$cache[ $object_key ]['items_count'] = $items_count;
		$this->create_files( $cache, $template );
	}

	public function filter( $html = '' ) {
		//exit();
		$html = preg_replace( '|<div[\s\t]*id=\"wpf-msg-box\"|i', '<div style="display:none;"', (string) $html );

		return $html;
	}

	#################################################################################

	/**
	 * Cleans forum cache
	 *
	 * @param integer        Item ID        (e.g.: $topicid or $postid) | (!) ID is 0 on dome actions (e.g.: delete actions)
	 * @param string        Item Type    (e.g.: 'forum', 'topic', 'post', 'user', 'widget', etc...)
	 * @param array        Item data as array
	 *
	 * @return    NULL
	 * @since 1.2.1
	 *
	 */

	public function clean( $id, $template, $item = [] ) {

		$dirs     = [];
		$userid   = ( isset( $item['userid'] ) && $item['userid'] ) ? $item['userid'] : 0;
		$postid   = ( isset( $item['postid'] ) && $item['postid'] ) ? $item['postid'] : 0;
		$topicid  = ( isset( $item['topicid'] ) && $item['topicid'] ) ? $item['topicid'] : 0;
		$forumid  = ( isset( $item['forumid'] ) && $item['forumid'] ) ? $item['forumid'] : 0;
		$parentid = ( isset( $item['parentid'] ) && $item['parentid'] ) ? $item['parentid'] : 0;
		$root     = ( isset( $item['root'] ) && $item['root'] ) ? $item['root'] : 0;
		$tagid    = ( isset( $item['tagid'] ) && $item['tagid'] ) ? $item['tagid'] : 0;

        if( isset( WPF()->forum ) && method_exists(WPF()->forum, 'reset') ) WPF()->forum->reset();
        if( isset( WPF()->topic ) && method_exists(WPF()->topic, 'reset') ) WPF()->topic->reset();
        if( isset( WPF()->post ) && method_exists(WPF()->post, 'reset') ) WPF()->post->reset();

		if( $template === 'forum' || $template === 'forum-soft' ) {
			$id = isset( $id ) ? $id : $forumid;
			if( $template === 'forum' ) {
				$dirs = [ $this->dir . '/forum', $this->dir . '/item/forum', $this->dir . '/item/url' ];
				WPF()->seo->clear_cache();
			}
            if( $template === 'forum-soft' ) {
				$dirs = [ $this->dir . '/forum' ];
			}
			if( $id ) {
				$file = $this->dir . '/item/forum/' . $id . '_' . $this->special_key;
				$this->clean_file( $file );
			}
		} elseif( $template === 'topic' || $template === 'topic-first-post' || $template === 'topic-soft' ) {
			$id = isset( $id ) ? $id : $topicid;
			if( $template === 'topic' || $template === 'topic-first-post' ) {
				WPF()->seo->clear_cache();
				$dirs = [ $this->dir . '/forum', $this->dir . '/topic', $this->dir . '/post' ];
			}
			if( $forumid ) {
				$file = $this->dir . '/item/forum/' . $forumid . '_' . $this->special_key;
				$this->clean_file( $file );
			}
			if( $id ) {
                $file = $this->dir . '/item/topic/' . $id . '_' . $this->special_key;
				$this->clean_file( $file );
                $file = $this->dir . '/item/url/topic_' . $id . '_' . $this->special_key;
                $this->clean_file( $file );
                $postid = ( isset( $item['first_postid'] ) && $item['first_postid'] ) ? $item['first_postid'] : 0;
				if( $postid ) $file = $this->dir . '/item/post/' . $postid . '_' . $this->special_key;
				$this->clean_file( $file );
                if( $template === 'topic' ) $this->clear_topic_posts_urls( $id );
			}
			WPF()->statistic_cache_clean();
			$this->clear_visitor_tracking();
		} elseif( $template === 'post' || $template === 'post-soft' ) {
			$id = isset( $id ) ? $id : $postid;
			if( $template === 'post' ) {
				$dirs = [ $this->dir . '/forum', $this->dir . '/topic', $this->dir . '/post' ];
			}
			if( $forumid ) {
				$file = $this->dir . '/item/forum/' . $forumid . '_' . $this->special_key;
				$this->clean_file( $file );
			}
			if( $topicid ) {
				$file = $this->dir . '/item/topic/' . $topicid . '_' . $this->special_key;
				$this->clean_file( $file );
			}
			if( $parentid ) {
				$file = $this->dir . '/item/post/' . $parentid . '_' . $this->special_key;
				$this->clean_file( $file );
			}
			if( $root ) {
				$file = $this->dir . '/item/post/' . $root . '_' . $this->special_key;
				$this->clean_file( $file );
			}
			if( $id ) {
				$file = $this->dir . '/item/post/' . $id . '_' . $this->special_key;
				$this->clean_file( $file );
                $file = $this->dir . '/item/url/post_' . $id . '_' . $this->special_key;
                $this->clean_file( $file );
                $this->clear_topic_next_posts_urls( $id, $topicid );
			}
			WPF()->statistic_cache_clean();
			$this->clear_visitor_tracking();
		} elseif( $template === 'tag' ) {
			if( $id ) {
				$file = $this->dir . '/item/tag/' . md5( $id ) . '_' . $this->special_key;
				$this->clean_file( $file );
			} else {
				$dirs = [ $this->dir . '/tag' ];
			}
		} elseif( $template === 'option' ) {
			$dirs = [ $this->dir . '/item/option' ];
		} elseif( $template === 'url' ) {
			$dirs = [ $this->dir . '/item/url' ];
		} elseif( $template === 'avatar' ) {
            if( $id ){
                $file = $this->dir . '/item/avatar/' . $id . '_' . $this->special_key;
                $this->clean_file( $file );
            } else {
                $dirs = [ $this->dir . '/item/avatar' ];
            }
        } elseif( $template === 'reaction' ) {
            if( $id ){
                $file = $this->dir . '/item/reaction/' . $id . '_' . $this->special_key;
                $this->clean_file( $file );
				$this->clean( $id, 'post' );
            } else {
                $dirs = [ $this->dir . '/item/reaction' ];
            }
        } elseif( $template === 'user' ) {
			if( wpforo_setting( 'seo', 'seo_profile' ) ) WPF()->seo->clear_cache();
		} elseif( $template === 'loop' ) {
			$dirs = [ $this->dir . '/forum', $this->dir . '/topic', $this->dir . '/post' ];
			WPF()->seo->clear_cache();
		} elseif( $template === 'item' ) {
			$dirs = [ $this->dir . '/item/post', $this->dir . '/item/topic', $this->dir . '/item/forum' ];
			WPF()->seo->clear_cache();
		} else {
			$dirs = [
				$this->dir . '/forum',
				$this->dir . '/topic',
				$this->dir . '/post',
				$this->dir . '/tag',
				$this->dir . '/item/post',
				$this->dir . '/item/topic',
				$this->dir . '/item/forum',
				$this->dir . '/item/tag',
				$this->dir . '/item/url',
				$this->dir . '/item/avatar',
				$this->dir . '/item/option',
				//$this->dir . '/item/reaction',
			];
			WPF()->seo->clear_cache();
		}

		if( ! empty( $dirs ) ) {
			foreach( $dirs as $dir ) {
				$this->clean_files( $dir );
			}
		}
	}

	public function clean_files( $directory ) {
		$directory = (string) $directory;
		$directory    = wpforo_fix_dir_sep( $directory );
		$directory_ns = trim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*';
		$directory_ws = DIRECTORY_SEPARATOR . trim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*';
		$glob         = glob( $directory_ns );
		if( empty( $glob ) ) $glob = glob( $directory_ws );
		foreach( $glob as $item ) {
			if( strpos( (string) $item, 'index.html' ) !== false || strpos( (string) $item, '.htaccess' ) !== false ) continue;
			if( ! is_dir( $item ) && file_exists( $item ) ) {
				@unlink( $item );
			}
		}
	}

	public function clean_file( $file ) {
		if( ! is_dir( $file ) && file_exists( $file ) ) {
			@unlink( $file );
		}
	}

    public function clear_topic_posts_urls( $topicid ){
        if( !$topicid ) return;
        $post_ids = WPF()->db->get_results("SELECT `postid` FROM " . WPF()->tables->posts . " WHERE `topicid` = " . intval( $topicid ), ARRAY_A );
        if( !empty( $post_ids ) ){
            foreach( $post_ids as $post_id ){
                $this->clean_file( $this->dir . '/item/post/' . $post_id['postid'] . '_' . $this->special_key );
                $this->clean_file( $this->dir . '/item/url/post_' . $post_id['postid'] . '_' . $this->special_key );
            }
        }
    }

    public function clear_topic_next_posts_urls( $postid, $topicid ){
        $topicid = ( $topicid ) ? : wpforo_post( $postid, 'topicid' );
        $post_ids = WPF()->db->get_results("SELECT `postid` FROM " . WPF()->tables->posts . " WHERE `topicid` = " . intval( $topicid ) . " AND `postid` > " . intval( $postid ), ARRAY_A );
        if( !empty( $post_ids ) ){
            foreach( $post_ids as $post_id ){
                $this->clean_file( $this->dir . '/item/post/' . $post_id['postid'] . '_' . $this->special_key );
                $this->clean_file( $this->dir . '/item/url/post_' . $post_id['postid'] . '_' . $this->special_key );
            }
        }
    }

	public function exists( $key, $template, $type = '', $sub_type = '' ) {
		$type = ( $type ) ? $type . '/' : '';
		if( file_exists( $this->dir . '/' . $template . '/' . $type . ( $sub_type ? $sub_type . '_' : '' ) . $key ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function check( $directory ) {
		$directory = (string) $directory;
		$directory = wpforo_fix_dir_sep( $directory );
		$filecount = 0;
		if( class_exists( 'FilesystemIterator' ) && is_dir( $directory ) ) {
			$fi        = new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS );
			$filecount = iterator_count( $fi );
		}
		if( ! $filecount ) {
			$directory_ns = trim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*';
			$directory_ws = DIRECTORY_SEPARATOR . trim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*';
			$files        = glob( $directory_ns );
			if( empty( $files ) ) $files = glob( $directory_ws );
			$filecount = count( $files );
		}
		if( $filecount > 1000 ) {
			$this->clean_files( $directory );
		}
	}

	public function clear_visitor_tracking() {
		$keep_vistors_data = apply_filters( 'wpforo_keep_visitors_data', 4000 );
		$time              = (int) time() - (int) $keep_vistors_data;
		$online            = (int) time() - (int) wpforo_setting( 'profiles', 'online_status_timeout' );
		if( $time > 1 ) {
			WPF()->db->query( "DELETE FROM `" . WPF()->tables->visits . "` WHERE `time` < " . intval( $time ) . "  OR (`time` < " . intval( $online ) . " AND `userid` = 0)" );
		}
	}

    public function cache_plugins(){
        $board_paths = [];
        $cache_plugins = [];

        if( is_wpforo_multiboard() ){
            $boards = WPF()->board->get_boards();
            if(!empty($boards)){
                foreach($boards as $board){
                    $board_paths[] = '/' . $board['slug'] . '/';
                }
            }
        } else {
            $board_paths[] = '/' . WPF()->board->get_current( 'slug' ) . '/';
        }

        $board_paths[] = '/' . wpforo_settings_get_slug('member') . '/';
        $board_paths[] = '/' . wpforo_settings_get_slug('register') . '/';
        $board_paths[] = '/' . wpforo_settings_get_slug('login') . '/';
        $board_paths[] = '/' . wpforo_settings_get_slug('lostpassword') . '/';

        if (function_exists("wpsc_init")) {
            //WP Super Cache
            $cache_plugins['WPSuper']['name'] = 'WP Super Cache';
            $cache_plugins['WPSuper']['steps'][] = __('Please navigate in Dashboard to Settings > WP Super Cache', 'wpforo');
            $cache_plugins['WPSuper']['steps'][] = __('Go to Advanced Tab, scroll down to "Rejected URL Strings" option', 'wpforo');
            $cache_plugins['WPSuper']['steps'][] = __('Insert the URL path(s) of your forum page(s) one per line in the option textarea:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', $board_paths) . '</code>';
            $cache_plugins['WPSuper']['steps'][] = __('Save it and delete all caches.', 'wpforo');
        }
        if (defined("LSCWP_V")) {
            //LiteSpeed Cache
            $cache_plugins['LiteSpeed']['name'] = 'LiteSpeed Cache';
            $cache_plugins['LiteSpeed']['steps'][] = __('Please navigate in Dashboard to LiteSpeed Cache > Cache admin page', 'wpforo');
            $cache_plugins['LiteSpeed']['steps'][] = __('Go to Exclude Tab, find the "Do Not Cache URIs" option', 'wpforo');
            $cache_plugins['LiteSpeed']['steps'][] = __('Insert the URL path(s) of your forum page(s) one per line in the option textarea:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', $board_paths) . '</code>';
            $cache_plugins['LiteSpeed']['steps'][] = __('Save it and delete all caches.', 'wpforo');
        }
        if (function_exists("rocket_clean_post")) {
            //WP Rocket Cache
            $cache_plugins['WPRocket']['name'] = 'WP Rocket Cache';
            $cache_plugins['WPRocket']['steps'][] = __('Please navigate in Dashboard to WP Rocket > Advanced Rules Tab', 'wpforo');
            $cache_plugins['WPRocket']['steps'][] = __('Scroll down to "Never cache (URLs)" option', 'wpforo');
            $cache_plugins['WPRocket']['steps'][] = __('Insert the URL path(s) of your forum page(s) one per line with wildcard (.*) in the option textarea:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', array_map( function ( $value ){ return $value . '(.*)'; }, $board_paths)) . '</code>';
            $cache_plugins['WPRocket']['steps'][] = __('Save it and delete all caches.', 'wpforo');
        }
        if (function_exists("wpfc_clear_post_cache_by_id")) {
            //WP Fastest Cache
            $cache_plugins['WPFastest']['name'] = 'WP Fastest Cache';
            $cache_plugins['WPFastest']['steps'][] = __('Please navigate in Dashboard to WP Fastest Cache > Exclude Tab', 'wpforo');
            $cache_plugins['WPFastest']['steps'][] = __('In the "Exclude Pages" section click the [Add New Rule] button', 'wpforo');
            $cache_plugins['WPFastest']['steps'][] = __('Select [Start with] option in the drop-down menu and insert the URL path(s) of your forum page(s) one per rule in the next field:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', $board_paths) . '</code>';
            $cache_plugins['WPFastest']['steps'][] = __('If you have more than one forum pages (boards) you should create separate rules for each forum board.', 'wpforo');
            $cache_plugins['WPFastest']['steps'][] = __('Save rules and delete all caches.', 'wpforo');
        }
        if (function_exists("w3tc_flush_post")) {
            //W3 Total Cache
            $cache_plugins['W3Total']['name'] = 'W3 Total Cache';
            $cache_plugins['W3Total']['steps'][] = __('Please navigate in Dashboard to Performance > Page Cache admin page', 'wpforo');
            $cache_plugins['W3Total']['steps'][] = __('Go to Advanced Tab, scroll down to Rejected URL Strings option', 'wpforo');
            $cache_plugins['W3Total']['steps'][] = __('Scroll to Advanced section and insert the URL path(s) of your forum page(s) one per line in the "Never cache the following pages" textarea:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', $board_paths) . '</code>';
            $cache_plugins['W3Total']['steps'][] = __('Save it and delete all caches.', 'wpforo');
        }
        if (is_callable(["WPO_Page_Cache", "delete_single_post_cache"])) {
            //WP-Optimize Cache
            $cache_plugins['WPOptimize']['name'] = 'WP-Optimize Cache';
            $cache_plugins['WPOptimize']['steps'][] = __('Please navigate in Dashboard to WP-Optimize > Cache admin page', 'wpforo');
            $cache_plugins['WPOptimize']['steps'][] = __('Go to Advanced Settings Tab, find the "URLs to exclude from caching" option', 'wpforo');
            $cache_plugins['WPOptimize']['steps'][] = __('Insert the URL path(s) of your forum page(s) one per line with wildcard [*] in the option textarea:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', array_map( function ( $value ){ return $value . '*'; }, $board_paths)) . '</code>';
            $cache_plugins['WPOptimize']['steps'][] = __('Save it and delete all caches.', 'wpforo');
        }
        if (class_exists("\SiteGround_Optimizer\Supercacher\Supercacher")) {
           //SiteGround Optimizer
            $cache_plugins['SiteGround']['name'] = 'SiteGround Optimizer';
            $cache_plugins['SiteGround']['steps'][] = __('Please navigate in Dashboard to SG Optimizer > Caching admin page', 'wpforo');
            $cache_plugins['SiteGround']['steps'][] = __('Scroll to Exclude URLs from Caching section and click the "pencil" button, enable it, and click the button again', 'wpforo');
            $cache_plugins['SiteGround']['steps'][] = __('Insert the URL path(s) of your forum page(s) with wildcard [*] in the pop-up filed:', 'wpforo') . ' <br><code>' . implode('</code> <br> <code>', array_map( function ( $value ){ return $value . '*'; }, $board_paths)) . '</code>';
            $cache_plugins['SiteGround']['steps'][] = __('Save it and delete all caches.', 'wpforo');
        }
        return $cache_plugins;
    }

    public function cache_plugins_status(){
        $not_exluded = [];
        $cache_plugins = $this->cache_plugins();
        $excluded = wpforo_get_option( 'wpforo_excluded_cache', '', false );
        $excluded = explode(',', $excluded );
        if( ! empty( $cache_plugins ) ){
            foreach( $cache_plugins as $cache_plugin ){
                if( ! in_array( $cache_plugin['name'], $excluded ) ){
                    $not_exluded[] = $cache_plugin;
                }
            }
        }
        return $not_exluded;
    }

	public function after_add_reaction( $reaction ) {
		if( wpfval( $reaction, 'postid' ) ) {
			WPF()->reaction->create_reaction_cache( $reaction['postid'] );
		}
	}

	public function after_edit_reaction( $fields ) {
		if( wpfval( $fields, 'postid' ) ) {
			wpforo_clean_cache( 'reaction', $fields['postid'] );
		}
	}

	public function before_delete_reaction( $args, $operator ) {
		$this->touched_postids = WPF()->reaction->_get_reactions_col( 'postid', $args, $operator );
	}

	public function after_delete_reaction(){
		foreach( array_unique(array_filter( $this->touched_postids )) as $postid ) wpforo_clean_cache( 'reaction', $postid );
	}

}
