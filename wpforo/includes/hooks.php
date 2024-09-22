<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'plugin_action_links_' . WPFORO_BASENAME, function( $links ) {
	$uninstall_url = wp_nonce_url( admin_url( 'plugins.php?wpfaction=uninstall' ), 'wpforo_uninstall' );
	$links[]       = '<a href="' . esc_url(
			(string) $uninstall_url
		) . '" class="wpforo-uninstall" style="color:#a00;" onclick="return confirm(\'' . __(
		                 'IMPORTANT! Uninstall is not a simple deactivation action. This action will permanently remove all forum data (forums, topics, replies, attachments...) from database. Please backup database before this action, you may need this forum data in future. If you are sure that you want to delete all forum data please confirm. If not, just cancel it, then you can deactivate this plugin, that will not remove forum data.',
		                 'wpforo'
	                 ) . '\')">' . __( 'Uninstall', 'wpforo' ) . '</a>';
	$settings_link = '<a href="' . esc_url( (string) admin_url( 'admin.php?page=wpforo-boards' ) ) . '">' . __(
			'Boards',
			'wpforo'
		) . '</a>';
	array_unshift( $links, $settings_link );
	
	return $links;
} );

function wpforo_notice_show() {
	WPF()->notice->show();
}

add_action( 'wp_footer', 'wpforo_notice_show' );

add_action( 'show_admin_bar', function( $show_admin_bar ) {
	if( ! is_super_admin() && is_user_logged_in() && WPF()->wp_current_user && ! array_intersect(
			[ 'editor', 'administrator', 'author', ],
			(array) WPF()->wp_current_user->roles
		) ) {
		$show_admin_bar = (bool) array_intersect(
			WPF()->current_user_groupids,
			wpforo_setting( 'general', 'admin_bar' )
		);
	}
	
	return $show_admin_bar;
} );

add_action( 'admin_notices', function() {
	if( strpos( wpforo_get_request_uri(), 'nav-menus.php' ) !== false ) {
		$message = '';
		foreach(
			WPF()->tpl->menu as $key => $value
		) {
			$message .= "<tr><td> " . $value['label'] . ": </td><td> /%$key%/ </td></tr>";
		}
		$message .= "<tr><td> " . wpforo_phrase( 'register', false ) . ": </td><td> /%wpforo-register%/ </td></tr>
			 <tr><td> " . wpforo_phrase( 'login', false ) . ": </td><td> /%wpforo-login%/    </td></tr>";
		printf(
			'<div class="notice notice-warning wpforo-menu-shortcodes">
                    <p class="wpforo-menu-shortcodes-head" style="cursor: pointer;">wpForo Menu Shortcodes hint <span class="dashicons dashicons-arrow-down-alt2"></span></p>
                    <div class="wpforo-menu-shortcodes-body" style="display: none;">
                        <hr/><table>%1$s</table>
                    </div>
            </div>',
			$message
		);
		?>
        <script>
			jQuery(document).ready(function ($) {
				$('body').on('click', '.wpforo-menu-shortcodes .wpforo-menu-shortcodes-head', {}, function () {
					var $this = $(this);
					var wrap = $this.closest('.wpforo-menu-shortcodes');
					var body = $('.wpforo-menu-shortcodes-body', wrap);
					body.toggle();
				});
			});
        </script>
		<?php
	}
} );

add_filter( 'comments_open', function( $open ) {
	if( is_wpforo_page() ) $open = false;
	
	return $open;
} );

add_filter( 'comments_array', function( $comments ) {
	if( is_wpforo_page() ) $comments = [];
	
	return $comments;
},          10, 2 );

add_action( 'wpforo_actions_end', function() {
	if( is_wpforo_page() ) {
		remove_post_type_support( 'post', 'comments' );
		remove_post_type_support( 'page', 'comments' );
	}
},          100 );

add_action( 'wpforo_actions_end', function() {
	if( ! WPF()->board->get_current( 'is_standalone' ) && ! is_front_page() && ! is_home() && is_wpforo_url() ) {
		global $wp_query, $post;
		$pageid = WPF()->board->get_current( 'pageid' );
		if( is_a( $post, 'WP_Post' ) && $post->ID !== $pageid ) {
			$args = [
				'post_count'    => 1,
				'found_posts'   => 1,
				'max_num_pages' => 0,
				'is_404'        => false,
				'is_page'       => true,
				'is_singular'   => true,
			];
			
			$target_post = get_post( $pageid );
			if( ! is_a( $target_post, 'WP_Post' ) ) {
				return;
			}
			
			$post = $target_post;
			
			$wp_query->posts             = [ $post ];
			$wp_query->queried_object_id = $post->ID;
			$wp_query->queried_object    = $post;
			
			foreach( $args as $key => $value ) {
				$wp_query->$key = $value;
			}
			
			setup_postdata( $post );
		}
	}
} );

add_filter( 'author_link', function( $link, $author_id ) {
	if( wpforo_setting( 'profiles', 'profile' ) === 'wpforo' ) return WPF()->member->get_profile_url( $author_id );
	
	return $link;
},          10, 2 );

add_filter( 'get_comment_author_url', function( $link, $ID = 0, $object = null ) {
	if( isset( $object->user_id ) && $object->user_id && wpforo_setting(
		                                                     'profiles',
		                                                     'profile'
	                                                     ) === 'wpforo' ) {
		return WPF()->member->get_profile_url(
			$object->user_id
		);
	}
	
	return $link;
},          10, 3 );

add_filter( 'register_url', function( $register_url ) {
	if( wpforo_setting( 'authorization', 'use_our_register_url' ) ) $register_url = wpforo_register_url();
	
	return $register_url;
} );

add_filter( 'login_url', function( $login_url ) {
	if( wpforo_setting( 'authorization', 'use_our_login_url' ) && strpos(
		                                                              (string) $_SERVER['REQUEST_URI'],
		                                                              '/wp-json/'
	                                                              ) !== 0 && strpos(
		                                                                         (string) wp_debug_backtrace_summary(),
		                                                                         'wp_auth_check_html, wp_login_url,'
	                                                                         ) === false ) {
		$login_url = wpforo_login_url();
	}
	
	return $login_url;
} );

add_filter( 'logout_url', function( $logout_url ) {
	if( wpforo_setting( 'authorization', 'use_our_login_url' ) ) $logout_url = wpforo_logout_url();
	
	return $logout_url;
} );

add_filter( 'pre_trash_post', function( $check, $post ) {
	if( in_array( $post->ID, WPF()->board->get_boards_pageids(), true ) ) {
		$check = false;
		WPF()->notice->add( 'DO NOT DELETE WPFORO PAGE!!!', 'error' );
	}
	
	return $check;
},          10, 2 );

add_filter( 'wp_dropdown_pages', function( $output, $r ) {
	$pageids = WPF()->board->get_boards_pageids();
	if( $r['name'] === 'page_for_posts' || ( $r['name'] === 'page_on_front' && wpforo_get_shortcode_pageid(
				$pageids
			) ) ) {
		$pattern = '#[\r\n\t\s]*<option[^<>]*?value=[\'"](' . implode(
				'|',
				$pageids
			) . ')[\'"][^<>]*?>[^<>]*?</option>#isu';
		$output  = preg_replace( $pattern, '', (string) $output );
	}
	
	return $output;
},          10, 2 );

add_filter( 'pre_update_option', function( $value, $option, $old_value ) {
	if( $option === 'page_on_front' ) {
		$pageids = WPF()->board->get_boards_pageids();
		if( in_array( wpforo_bigintval( $value ), $pageids, true ) ) {
			if( ! $page_id = wpforo_get_shortcode_pageid( $pageids ) ) {
				$page_id = wp_insert_post(
					[
						'post_date'         => current_time( 'mysql', 1 ),
						'post_date_gmt'     => current_time( 'mysql', 1 ),
						'post_content'      => '[wpforo]',
						'post_title'        => 'Forum page_on_front',
						'post_status'       => 'publish',
						'comment_status'    => 'close',
						'ping_status'       => 'close',
						'post_name'         => 'front-community',
						'post_modified'     => current_time( 'mysql', 1 ),
						'post_modified_gmt' => current_time( 'mysql', 1 ),
						'post_parent'       => 0,
						'menu_order'        => 0,
						'post_type'         => 'page',
					]
				);
			}
			$value = ( $page_id && ! is_wp_error( $page_id ) ? $page_id : $old_value );
		}
	}
	
	return $value;
},          10, 3 );

function wpftpl_url( $filename ) {
	$tpl_url = '';
	if( $filename ) {
		if( $tpl_url = locate_template( 'wpforo/' . $filename ) ) {
			$tpl_url = get_template_directory_uri() . '/wpforo/' . $filename;
		}
		if( ! $tpl_url ) $tpl_url = WPF()->tpl->template_url . '/' . $filename;
	}
	
	return apply_filters( 'wpforo_wpftpl_url', $tpl_url, $filename );
}

function wpftpl( $filename ) {
	$tpl = '';
	if( $filename ) {
		$tpl = locate_template( 'wpforo/' . $filename );
		if( ! $tpl ) $tpl = WPF()->tpl->template_dir . '/' . $filename;
	}
	
	return apply_filters( 'wpforo_wpftpl', $tpl, $filename );
}

add_shortcode( 'wpforo', function( $atts ) {
	wpforo_include_once_theme_functions();
	if( defined( 'REST_REQUEST' ) && REST_REQUEST ) return '';
	if( ! is_wpforo_url() ) {
		if( ! $atts ) $atts = [ 'item' => 'forum' ];
		WPF()->init_current_url( $atts );
		WPF()->init_current_object();
		wpforo_frontend_enqueue_scripts();
	}
	if( apply_filters( 'on_wpforo_load_remove_the_content_all_filters', false ) ) {
		remove_all_filters( 'the_content' );
	}
	
	ob_start();
	if( wpforo_current_user_is( 'admin' ) || ! wpforo_setting( 'board', 'under_construction' ) ) {
		include( wpftpl( 'index.php' ) );
	} else {
		include( wpftpl( 'uc.php' ) );
	}
	$output = ob_get_clean();
	$output = trim( (string) $output );
	if( ! $output ) $output = wpforo_hook_usage( 'the_content' );
	
	return $output;
} );

function wpforo_hook_usage( $hook = '' ) {
	global $wp_filter;
	
	$output = '<div style="color: #990000; font-size: 16px;">Notice: a plugin conflict has been detected. wpForo forums are affected by other plugin errors.
        Please deactivate all plugins, delete all caches and test again.
        Then activate all plugins back one by one and find the conflict maker plugin.</div>
        <pre style="display: none;">' . ( empty( $hook ) || ! isset( $wp_filter[ $hook ] ) ? 'No hook usage' : print_r(
			$wp_filter[ $hook ],
			true
		) ) . '</pre>';
	
	return $output;
}

add_action( 'wpforo_actions_end', 'wpforo_set_header_status' );
function wpforo_set_header_status() {
	if( is_wpforo_page() ) {
		global $wp_query;
		
		$status = ( WPF()->current_object['is_404'] ? 404 : 200 );
		status_header( $status );
		if( $status === 404 ) nocache_headers();
		$wp_query->is_404 = false;
	}
}

add_filter( 'pre_handle_404', function( $bypass ) {
	if( is_wpforo_page() ) return true;
	
	return $bypass;
} );

add_action( 'wpforo_actions_end', function() {
	if( is_wpforo_page() && WPF()->board->get_current( 'is_standalone' ) ) {
		add_rewrite_rule( '(.*)', 'index.php?page_id=' . WPF()->board->get_current( 'pageid' ), 'top' );
		add_filter( 'template_include', function( $template ) {
			if( is_wpforo_page() && ! is_wpforo_shortcode_page() && ( $wpforo_template = wpftpl(
					'index.php'
				) ) ) {
				return $wpforo_template;
			}
			
			return $template;
		} );
	}
} );

add_filter( 'rewrite_rules_array', function( $rules ) {
	$rewrite_prefix = '(?:([a-z]{2,3})/)?';
	if( function_exists( 'pll_languages_list' ) ) {
		$rewrite_prefix = '(?:(' . implode(
				'|',
				pll_languages_list()
			) . ')/)?';
	}
	
	if( ! WPF()->board->get_current( 'is_standalone' ) ) {
		$pageid = 0;
		$boards = WPF()->board->get_boards( [ 'status' => true ] );
		foreach( $boards as $board ) {
			if( $board['boardid'] === 0 ) $pageid = $board['pageid'];
			$route   = urldecode( (string) $board['slug'] );
			$route   = preg_replace( '#^/?index\.php/?#iu', '', $route );
			$route   = trim( (string) $route, '/' );
			$pattern = $rewrite_prefix . preg_quote( $route ) . '(?:/|$).*$';
			$to_url  = 'index.php?lang=$matches[1]&page_id=' . $board['pageid'];
			if( ! array_key_exists( $pattern, $rules ) ) $rules = array_merge( [ $pattern => $to_url ], $rules );
		}
		
		if( ! $pageid ) $pageid = wpforo_get_option( 'wpforo_pageid', 0 );
		foreach( WPF()->board->routes as $route ) {
			//            $route   = utf8_uri_encode( urldecode( (string) $route ) );
			$route   = urldecode( (string) $route );
			$route   = preg_replace( '#^/?index\.php/?#isu', '', $route );
			$route   = trim( (string) $route, '/' );
			$pattern = $rewrite_prefix . preg_quote( $route ) . '(?:/|$).*$';
			$to_url  = 'index.php?lang=$matches[1]&page_id=' . $pageid;
			if( ! array_key_exists( $pattern, $rules ) ) $rules = array_merge( [ $pattern => $to_url ], $rules );
		}
	}
	
	return $rules;
} );

add_action( 'wpforo_actions_end', function() {
	$pageid = WPF()->board->get_current( 'pageid' );
	if( is_wpforo_url() && $pageid && WPF()->board->route && get_the_ID() !== $pageid ) {
		wpforo_repair_main_shortcode_page();
	}
} );


function wpforo_include_once_theme_functions() {
	$path = wpftpl( 'functions.php' );
	if( file_exists( $path ) ) include_once( $path );
	
	$path = wpftpl( 'functions-wp.php' );
	if( file_exists( $path ) ) include_once( $path );
}

add_action( 'wpforo_after_init', 'wpforo_include_once_theme_functions' );

function wpforo_meta_title( $title ) {
	$meta_title = [];
	
	if( ! wpforo_setting( 'seo', 'seo_title' ) ) return $title;
	
	if( is_wpforo_page() ) {
		$template = WPF()->current_object['template'];
		if( ! WPF()->current_object['is_404'] ) {
			$paged = ( WPF()->current_object['paged'] > 1 ) ? wpforo_phrase( 'page', false ) . ' ' . WPF()->current_object['paged'] . ' ' : '';
			if( ! empty( WPF()->current_object['forum'] ) ) {
				$forum = WPF()->current_object['forum'];
			}
			if( ! empty( WPF()->current_object['topic'] ) ) {
				$topic = WPF()->current_object['topic'];
			}
			if( ! empty( WPF()->current_object['user'] ) ) {
				$user = WPF()->current_object['user'];
			}
			if( isset( $topic['title'] ) && isset( $forum['title'] ) && isset(
					WPF()->board->get_current(
						'settings'
					)['title']
				) ) {
				$meta_title = [
					$topic['title'],
					$paged,
					$forum['title'],
					WPF()->board->get_current( 'settings' )['title'],
				];
				$meta_title = apply_filters( 'wpforo_seo_topic_title', $meta_title );
			} elseif( ! isset( $topic['title'] ) && isset( $forum['title'] ) && isset(
					WPF()->board->get_current(
						'settings'
					)['title']
				) ) {
				$meta_title = [ $forum['title'], $paged, WPF()->board->get_current( 'settings' )['title'] ];
				$meta_title = apply_filters( 'wpforo_seo_forum_title', $meta_title );
			} elseif( ! in_array( $template, [ 'forum', 'topic', 'post' ], true ) ) {
				if( wpforo_is_member_template( $template ) ) {
					if( isset( $user['display_name'] ) ) {
						$meta_title = [
							$user['display_name'],
							wpforo_phrase( ucfirst( (string) $template ), false ),
							WPF()->board->get_current( 'settings' )['title'],
							$paged,
						];
					} elseif( isset( WPF()->current_object['user_nicename'] ) ) {
						$meta_title = [
							WPF()->current_object['user_nicename'],
							wpforo_phrase( ucfirst( (string) $template ), false ),
							WPF()->board->get_current( 'settings' )['title'],
							$paged,
						];
					} else {
						$meta_title = [
							wpforo_phrase( 'Member', false ),
							wpforo_phrase( ucfirst( (string) $template ), false ),
							WPF()->board->get_current( 'settings' )['title'],
							$paged,
						];
					}
					$meta_title = apply_filters( 'wpforo_seo_profile_title', $meta_title );
				} elseif( $template === 'recent' ) {
					$wpfpaged = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? ' - ' . wpforo_phrase(
							'page',
							false
						) . ' ' . $_GET['wpfpaged'] . ' ' : '';
					$view     = wpfval( $_GET, 'view' );
					if( $view == 'unread' ) {
						$main_title = wpforo_phrase( 'Unread Posts', false );
					} elseif( $view == 'prefix' ) {
						$main_title = wpforo_phrase( 'Topic Prefix', false );
					} else {
						$main_title = wpforo_phrase( 'Recent Posts', false );
					}
					$meta_title = [ $main_title . $wpfpaged, WPF()->board->get_current( 'settings' )['title'] ];
					$meta_title = apply_filters( 'wpforo_seo_recent_posts_title', $meta_title );
				} elseif( $template === 'tags' ) {
					$wpfpaged   = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? ' - ' . wpforo_phrase(
							'page',
							false
						) . ' ' . $_GET['wpfpaged'] . ' ' : '';
					$meta_title = [
						wpforo_phrase( 'Tags', false ) . $wpfpaged,
						WPF()->board->get_current( 'settings' )['title'],
					];
					$meta_title = apply_filters( 'wpforo_seo_tags_title', $meta_title );
				} elseif( $template === 'search' && wpfval( $_GET, 'wpfin' ) && wpfval(
						$_GET,
						'wpfs'
					) && $_GET['wpfin'] === 'tag' ) {
					$wpfpaged   = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? ' - ' . wpforo_phrase(
							'page',
							false
						) . ' ' . $_GET['wpfpaged'] . ' ' : '';
					$meta_title = [
						wpforo_phrase( 'Topic Tag:', false ) . ' ' . esc_html( $_GET['wpfs'] ),
						$wpfpaged,
						WPF()->board->get_current( 'settings' )['title'],
					];
					$meta_title = apply_filters( 'wpforo_seo_tag_title', $meta_title );
				} elseif( $template ) {
					$wpfpaged   = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? ' - ' . wpforo_phrase(
							'page',
							false
						) . ' ' . $_GET['wpfpaged'] . ' ' : '';
					$meta_title = [
						wpforo_phrase( ucfirst( (string) $template ), false ) . $wpfpaged,
						WPF()->board->get_current( 'settings' )['title'],
					];
					$meta_title = apply_filters( 'wpforo_seo_template_title', $meta_title );
				} elseif( $title ) {
					$meta_title = ( is_array( $title ) ) ? $title : [ $title ];
					$meta_title = apply_filters( 'wpforo_seo_x_title', $meta_title );
				} else {
					$meta_title = [ wpforo_phrase( 'Forum', false ), get_bloginfo( 'name' ) ];
					$meta_title = apply_filters( 'wpforo_seo_general_title', $meta_title );
				}
			} elseif( isset( WPF()->board->get_current( 'settings' )['title'] ) && WPF()->board->get_current(
					'settings'
				)['title'] ) {
				$meta_title = [ WPF()->board->get_current( 'settings' )['title'], get_bloginfo( 'name' ) ];
				$meta_title = apply_filters( 'wpforo_seo_main_title', $meta_title );
			} elseif( $title ) {
				$meta_title = ( is_array( $title ) ) ? $title : [ $title ];
				$meta_title = apply_filters( 'wpforo_seo_x_title', $meta_title );
			} else {
				$meta_title = [ wpforo_phrase( 'Forum', false ), get_bloginfo( 'name' ) ];
				$meta_title = apply_filters( 'wpforo_seo_general_title', $meta_title );
			}
		} else {
			$meta_title = [
				wpforo_phrase( '404 - Page not found', false ),
				WPF()->board->get_current( 'settings' )['title'],
			];
			$meta_title = apply_filters( 'wpforo_seo_404_title', $meta_title );
		}
	}
	if( ! empty( $meta_title ) ) {
		return $meta_title;
	} else {
		return $title;
	}
}

add_filter( 'document_title_parts', 'wpforo_meta_title', 100 );

function wpforo_meta_wp_title( $title ) {
	if( ! wpforo_setting( 'seo', 'seo_title' ) ) return $title;
	$meta_title = wpforo_meta_title( $title );
	if( is_array( $meta_title ) && ! empty( $meta_title ) ) {
		$title = implode( ' &#8211; ', $meta_title );
	}
	
	return $title;
}

add_filter( 'wp_title', 'wpforo_meta_wp_title', 100 );

function wpforo_add_meta_tags() {
	if( ! wpforo_setting( 'seo', 'seo_meta' ) ) {
		return;
	}
	
	if( is_wpforo_page() && ! is_wpforo_shortcode_page() ) {
		$title       = '';
		$og_img      = '';
		$tw_img      = '';
		$schema      = '';
		$noindex     = '';
		$template    = '';
		$description = '';
		$udata       = [];
		if( preg_match( '#\?.*$#is', (string) WPF()->canonical_url, $requests ) ) {
			if( strpos( (string) $requests[0], 'orderby' ) !== false ) {
				$canonical = preg_replace( '#\?.*$#is', '', (string) WPF()->canonical_url );
			} else {
				$canonical = wpforo_home_url( $requests[0] );
			}
		} else {
			$canonical = WPF()->canonical_url;
		}
		$noindex_urls = wpforo_setting( 'seo', 'noindex' );
		$image        = wpforo_find_image_urls( '', true, 'og:image' );
		foreach( $noindex_urls as $noindex_url ) {
			$noindex_url = strtok( $noindex_url, "#" );
			if( strpos( (string) $noindex_url, '*' ) !== false ) {
				$noindex_url = strtok( $noindex_url, "*" );
				if( preg_match( '|^' . preg_quote( $noindex_url ) . '|is', (string) $canonical ) ) {
					$noindex = "<meta name=\"robots\" content=\"noindex\">\r\n";
					break;
				}
			} elseif( $canonical == $noindex_url ) {
				$noindex = "<meta name=\"robots\" content=\"noindex\">\r\n";
				break;
			}
		}
		$paged = ( WPF()->current_object['paged'] > 1 ) ? wpforo_phrase( 'page', false ) . ' ' . WPF()->current_object['paged'] . ' | ' : '';
		if( isset( WPF()->current_object['template'] ) ) {
			$template = WPF()->current_object['template'];
		}
		if( ! empty( WPF()->current_object['forum'] ) ) {
			$forum = WPF()->current_object['forum'];
		}
		if( ! empty( WPF()->current_object['topic'] ) ) {
			$topic = WPF()->current_object['topic'];
		}
		if( ! empty( WPF()->current_object['user'] ) ) {
			$user = WPF()->current_object['user'];
		}
		if( isset( WPF()->current_object ) ) {
			if( wpfval( WPF()->current_object, 'forumid' ) && ! wpfval( WPF()->current_object, 'topicid' ) ) {
				if( isset( $forum['title'] ) ) {
					$title = $forum['title'];
				}
				if( isset( WPF()->current_object['forum_meta_desc'] ) && WPF()->current_object['forum_meta_desc'] != '' ) {
					$description = $paged . WPF()->current_object['forum_meta_desc'];
				} elseif( isset( WPF()->current_object['forum_desc'] ) && WPF()->current_object['forum_desc'] != '' ) {
					$description = $paged . WPF()->current_object['forum_desc'];
				}
			} elseif( isset( WPF()->current_object['topicid'] ) && isset( $topic['first_postid'] ) ) {
				$post    = WPF()->post->get_post( $topic['first_postid'] );
				$content = wpforo_content( $post, false );
				$image   = wpforo_find_image_urls( $content, true, 'og:image' );
				if( isset( $post['title'] ) ) {
					$title = wpforo_text( $paged . $post['title'], 60, false );
				}
				if( isset( $post['body'] ) ) {
					$description = wpforo_text( $paged . $post['body'], 150, false );
				}
				if( isset( $post['body'] ) ) {
					$schema = wpforo_schema( $forum, $topic, $post );
				}
			} elseif( wpforo_is_member_template( $template ) ) {
				if( isset( WPF()->board->get_current( 'settings' )['title'] ) ) {
					$title = $paged . WPF()->board->get_current( 'settings' )['title'];
				}
				$udata['name']  = ( isset( $user['display_name'] ) && $user['display_name'] ) ? wpforo_phrase(
					                                                                                'User',
					                                                                                false
				                                                                                ) . ': ' . $user['display_name'] : '';
				$udata['title'] = ( isset( $user['rating']['title'] ) && $user['rating']['title'] ) ? wpforo_phrase(
					                                                                                      'Title',
					                                                                                      false
				                                                                                      ) . ': ' . $user['rating']['title'] : '';
				$udata['about'] = ( isset( $user['about'] ) && $user['about'] ) ? wpforo_phrase(
					                                                                  'About',
					                                                                  false
				                                                                  ) . ': ' . wpforo_text(
					                                                                  $user['about'],
					                                                                  150,
					                                                                  false
				                                                                  ) : '';
				$description    = $title . ' - ' . wpforo_phrase( 'Member Profile', false ) . ' &gt; ' . wpforo_phrase(
						ucfirst( (string) $template ),
						false
					) . ' ' . wpforo_phrase( 'Page', false ) . '. ' . implode( ', ', $udata );
				if( ! wpforo_setting( 'seo', 'seo_profile' ) ) {
					$noindex = "<meta name=\"robots\" content=\"noindex\">\r\n";
				}
			} elseif( isset( WPF()->current_object['template'] ) && WPF()->current_object['template'] === 'member' ) {
				$wpfpaged    = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? wpforo_phrase(
					                                                                         'Page',
					                                                                         false
				                                                                         ) . ' ' . $_GET['wpfpaged'] . ' | ' : '';
				$description = $wpfpaged . wpforo_phrase( 'Forum Members List', false );
			} elseif( isset( WPF()->current_object['template'] ) && WPF()->current_object['template'] === 'recent' ) {
				$wpfpaged    = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? wpforo_phrase(
					                                                                         'Page',
					                                                                         false
				                                                                         ) . ' ' . $_GET['wpfpaged'] . ' | ' : '';
				$description = $wpfpaged . wpforo_phrase( 'Recent Posts', false );
			} elseif( isset( WPF()->current_object['template'] ) && WPF()->current_object['template'] === 'tags' ) {
				$wpfpaged    = ( isset( $_GET['wpfpaged'] ) && $_GET['wpfpaged'] > 1 ) ? wpforo_phrase(
					                                                                         'Page',
					                                                                         false
				                                                                         ) . ' ' . $_GET['wpfpaged'] . ' | ' : '';
				$description = $wpfpaged . wpforo_phrase( 'Tags', false );
			} else {
				if( isset( WPF()->board->get_current( 'settings' )['title'] ) ) {
					$title = $paged . WPF()->board->get_current( 'settings' )['title'];
				}
				if( isset( WPF()->board->get_current( 'settings' )['desc'] ) ) {
					$description = $paged . WPF()->board->get_current( 'settings' )['desc'];
				}
				if( isset( WPF()->current_object['template'] ) && ( WPF()->current_object['template'] == 'login' || WPF()->current_object['template'] == 'register' ) ) {
					$noindex = "<meta name=\"robots\" content=\"noindex\">\r\n";
				}
			}
			$description = strip_tags( str_replace( '>', '> ', $description ) );
			$description = preg_replace( '#[\t\r\n]+#iu', ' ', $description );
			if( $image ) {
				$og_img = '<meta property="og:image" content="' . $image . '" />' . "\r\n";
				$tw_img = '<meta property="twitter:image" content="' . $image . '" />' . "\r\n";
			}
			$meta_tags = "\r\n<!-- wpForo SEO -->\r\n" . $noindex . "<link rel=\"canonical\" href=\"" . $canonical . "\" />\r\n<meta name=\"description\" content=\"" . esc_html(
					$description
				) . "\" />\r\n<meta property=\"og:title\" content=\"" . esc_html(
				             $title
			             ) . "\" />\r\n<meta property=\"og:description\" content=\"" . esc_html(
				             $description
			             ) . "\" />\r\n<meta property=\"og:url\" content=\"" . $canonical . "\" />\r\n<meta property=\"og:locale\" content=\"" . WPF(
			             )->locale . "\" />\r\n" . $og_img . "<meta property=\"og:site_name\" content=\"" . get_bloginfo(
				             'name'
			             ) . "\" />\r\n<meta property=\"og:type\" content=\"website\" />\r\n<meta name=\"twitter:description\" content=\"" . esc_html(
				             $description
			             ) . "\"/>\r\n<meta name=\"twitter:title\" content=\"" . esc_html(
				             $title
			             ) . "\" />\r\n<meta property=\"twitter:card\" content=\"summary_large_image\" />\r\n" . $tw_img . "<!-- wpForo SEO End -->\r\n\r\n";
			$schema    = "<!-- wpForo Schema -->" . $schema . "\r\n<!-- wpForo Schema End -->\r\n\r\n";
			echo apply_filters( 'wpforo_seo_meta_tags', $meta_tags . $schema );
		}
	}
}

add_action( 'wp_head', 'wpforo_add_meta_tags', 1 );

add_action( 'wp_ajax_wpforo_answer_ajax', 'wpf_answer' );
function wpf_answer() {
	wpforo_verify_nonce( 'wpforo_answer_ajax' );
	$response = [ 'notice' => WPF()->notice->get_notices() ];
	if( ! is_user_logged_in() ) {
		WPF()->notice->add( wpforo_get_login_or_register_notice_text() );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	if( ! isset( $_POST['answerstatus'] ) || ! isset( $_POST['postid'] ) || ! $postid = intval( $_POST['postid'] ) ) {
		WPF()->notice->add( 'action error', 'error' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	if( ! $post = WPF()->post->get_post( $postid ) ) {
		WPF()->notice->add( 'post not found', 'error' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	if( ! $topic = WPF()->topic->get_topic( $post['topicid'] ) ) {
		WPF()->notice->add( 'topic not found', 'error' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	if( ! ( WPF()->perm->forum_can( 'at', $post['forumid'] ) || ( WPF()->perm->forum_can(
				'oat',
				$post['forumid']
			) && WPF()->current_userid == $topic['userid'] ) ) ) {
		WPF()->notice->add( 'You don\'t have permission to make topic answered', 'error' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	if( intval( $_POST['answerstatus'] ) && WPF()->topic->has_is_answer_post( $topic['topicid'] ) ) {
		WPF()->notice->add( 'You don\'t have permission to make two best answers for one topic', 'error' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	if( false !== WPF()->db->query(
			"UPDATE " . WPF()->tables->posts . " SET is_answer = " . intval(
				$_POST['answerstatus']
			) . " WHERE postid = " . intval( $postid )
		) ) {
		wpforo_clean_cache( 'post', $postid, $post );
		WPF()->db->query(
			"UPDATE " . WPF()->tables->topics . " SET `solved` = " . intval(
				$_POST['answerstatus']
			) . " WHERE `topicid` = " . intval( $post['topicid'] )
		);
		do_action( 'wpforo_answer', intval( $_POST['answerstatus'] ), $post );
		WPF()->notice->add( 'done', 'success' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_success( $response );
	}
	wp_send_json_error( $response );
}

add_action( 'wp_ajax_wpforo_quote_ajax', 'wpf_quote' );
add_action( 'wp_ajax_nopriv_wpforo_quote_ajax', 'wpf_quote' );
function wpf_quote() {
	wpforo_verify_nonce( 'wpforo_quote_ajax' );
	if( ! $current_topicid = wpforo_bigintval( WPF()->current_object['topicid'] ) ) {
		exit();
	}
	$post = WPF()->db->get_row(
		'SELECT * FROM ' . WPF()->tables->posts . ' WHERE topicid = ' . $current_topicid . ' AND postid =' . wpforo_bigintval(
			$_POST['postid']
		),
		ARRAY_A
	);
	if( ! ( WPF()->perm->forum_can( 'cr', $post['forumid'] ) || ( wpforo_is_owner(
		                                                              wpfval(
			                                                              WPF()->current_object['topic'],
			                                                              'userid'
		                                                              ),
		                                                              wpfval( WPF()->current_object['topic'], 'email' )
	                                                              ) && WPF()->perm->forum_can(
				'ocr',
				$post['forumid']
			) ) ) ) {
		return;
	}
	$post     = apply_filters( 'wpforo_quote_post_ajax', $post );
	$response = sprintf(
		'[quote data-userid="%1$d" data-postid="%2$d"]%3$s[/quote]<p></p>',
		$post['userid'],
		$post['postid'],
		wpautop( $post['body'] )
	);
	wp_send_json_success( $response );
}

add_action( 'wp_ajax_wpforo_report_ajax', 'wpf_report' );
function wpf_report() {
	wpforo_verify_nonce( 'wpforo_report_ajax' );
	if( ! is_user_logged_in() ) {
		WPF()->notice->add( wpforo_get_login_or_register_notice_text() );
		wp_send_json_error( WPF()->notice->get_notices() );
	}
	
	if( ! isset( $_POST['reportmsg'] ) || ! $_POST['reportmsg'] || ! isset( $_POST['postid'] ) || ! $_POST['postid'] ) {
		WPF()->notice->add( 'Error: please insert some text to report.', 'error' );
		wp_send_json_error( WPF()->notice->get_notices() );
	}
	
	$postid  = intval( $_POST['postid'] );
	$forumid = wpforo_post( $postid, 'forumid' );
	
	if( ! WPF()->perm->can_report( $forumid ) ) {
		WPF()->notice->add( 'You are not allowed to report.', 'error' );
		wp_send_json_error( WPF()->notice->get_notices() );
	}
	
	############### Sending Email  ##################
	$report_text = substr( strip_tags( (string) $_POST['reportmsg'] ), 0, 1000 );
	$reporter    = '<a href="' . WPF()->current_user['profile_url'] . '">' . ( WPF()->current_user['display_name'] ? WPF()->current_user['display_name'] : urldecode(
			(string) WPF()->current_user['user_nicename']
		) ) . '</a>';
	$reportmsg   = wpforo_kses( $report_text, 'email' );
	$post_url    = WPF()->post->get_url( $postid );
	
	$subject = wpforo_setting( 'email', 'report_email_subject' );
	$message = wpforo_setting( 'email', 'report_email_message' );
	
	$from_tags = [ "[reporter]", "[message]", "[post_url]" ];
	$to_words  = [
		sanitize_text_field( $reporter ),
		$reportmsg,
		'<a target="_blank" href="' . esc_url( (string) $post_url ) . '">' . esc_url( (string) $post_url ) . '</a>',
	];
	
	$subject = stripslashes( strip_tags( str_replace( $from_tags, $to_words, (string) $subject ) ) );
	$message = stripslashes( str_replace( $from_tags, $to_words, (string) $message ) );
	
	$admin_emails = wpforo_setting( 'email', 'admin_emails' );
	$admin_email  = wpfval( $admin_emails, 0 );
	$headers      = wpforo_admin_mail_headers();
	
	add_filter( 'wp_mail_content_type', 'wpforo_set_html_content_type', 999 );
	if( ! @wp_mail( $admin_email, $subject, $message, $headers ) ) {
		WPF()->notice->add( 'Can\'t send report email', 'error' );
		wp_send_json_error( WPF()->notice->get_notices() );
	}
	remove_filter( 'wp_mail_content_type', 'wpforo_set_html_content_type' );
	
	############### Sending Email end  ##############
	do_action( 'wpforo_after_post_report', $postid, $forumid, $_POST['reportmsg'] );
	WPF()->notice->add( 'Message has been sent', 'success' );
	wp_send_json_success( WPF()->notice->get_notices() );
}

add_action( 'wp_ajax_wpforo_sticky_ajax', 'wpf_sticky' );
add_action( 'wp_ajax_nopriv_wpforo_sticky_ajax', 'wpf_sticky' );
function wpf_sticky() {
	wpforo_verify_nonce( 'wpforo_sticky_ajax' );
	WPF()->notice->add( 'wrong data', 'error' );
	$response = [ 'notice' => WPF()->notice->get_notices() ];
	if( ! $topicid = wpforo_bigintval( wpfval( $_POST, 'topicid' ) ) ) {
		wp_send_json_error( $response );
	}
	
	$sql     = "SELECT `forumid` FROM `" . WPF()->tables->topics . "` WHERE `topicid` = $topicid";
	$forumid = WPF()->db->get_var( $sql );
	if( ! WPF()->perm->forum_can( 's', $forumid ) ) {
		WPF()->notice->add( 'You don\'t have permission to do this action from this forum', 'error' );
		$response['notice'] = WPF()->notice->get_notices();
		wp_send_json_error( $response );
	}
	$status = wpfval( $_POST, 'status' );
	if( $status === 'sticky' ) {
		$sql = "UPDATE `" . WPF()->tables->topics . "` SET `type` = 1 WHERE `topicid` = $topicid";
		if( false !== WPF()->db->query( $sql ) ) {
			wpforo_clean_cache( 'topic-first-post', $topicid );
			WPF()->notice->add( 'Done!', 'success' );
			$response['notice'] = WPF()->notice->get_notices();
			wp_send_json_success( $response );
		}
	} elseif( $status === 'unsticky' ) {
		$sql = "UPDATE `" . WPF()->tables->topics . "` SET `type` = 0 WHERE `topicid` = $topicid";
		if( false !== WPF()->db->query( $sql ) ) {
			wpforo_clean_cache( 'topic-first-post', $topicid );
			WPF()->notice->add( 'Done!', 'success' );
			$response['notice'] = WPF()->notice->get_notices();
			wp_send_json_success( $response );
		}
	}
	wp_send_json_error( $response );
}

add_action( 'wp_ajax_wpforo_private_ajax', 'wpf_private' );
function wpf_private() {
	wpforo_verify_nonce( 'wpforo_private_ajax' );
	if( ! WPF()->current_userid ) {
		wp_send_json_error();
	}
	if( ! ( $topicid = wpforo_bigintval( wpfval( $_POST, 'topicid' ) ) ) ) {
		wp_send_json_error();
	}
	if( ! ( $topic = WPF()->topic->get_topic( $topicid ) ) ) {
		wp_send_json_error();
	}
	if( ! ( WPF()->perm->forum_can( 'p', $topic['forumid'] ) || ( WPF()->current_userid == $topic['userid'] && WPF()->perm->forum_can( 'op', $topic['forumid'] ) ) ) ) {
		wp_send_json_error();
	}
	$status = wpfval( $_POST, 'status' );
	if( $status === 'private' ) {
		WPF()->topic->wprivate( $topicid, 1 );
		wp_send_json_success();
	} elseif( $status === 'public' ) {
		WPF()->topic->wprivate( $topicid, 0 );
		wp_send_json_success();
	}
	wp_send_json_error();
}

add_action( 'wp_ajax_wpforo_solved_ajax', 'wpf_solved' );
add_action( 'wp_ajax_nopriv_wpforo_solved_ajax', 'wpf_solved' );
function wpf_solved() {
	wpforo_verify_nonce( 'wpforo_solved_ajax' );
	if( ! WPF()->current_userid ) {
		wp_send_json_error();
	}
	if( ! $postid = wpforo_bigintval( wpfval( $_POST, 'postid' ) ) ) {
		wp_send_json_error();
	}
	if( $post = WPF()->post->get_post( $postid ) ) {
		if( WPF()->perm->forum_can( 'sv', $post['forumid'] ) || ( WPF()->current_userid == $post['userid'] && WPF()->perm->forum_can( 'osv', $post['forumid'] ) ) ) {
			$solved = ( wpfval( $_POST, 'status' ) === 'solved' ? 1 : 0 );
			$sql    = "UPDATE " . WPF()->tables->topics . " SET `solved` = %d WHERE `topicid` = %d";
			WPF()->db->query( WPF()->db->prepare( $sql, $solved, $post['topicid'] ) );
			wpforo_clean_cache( 'topic-first-post', $post['topicid'] );
			wp_send_json_success();
		}
	}
	wp_send_json_error();
}

add_action( 'wp_ajax_wpforo_approve_ajax', 'wpf_approve' );
function wpf_approve() {
	wpforo_verify_nonce( 'wpforo_approve_ajax' );
	if( ! WPF()->current_userid ) {
		WPF()->notice->add( wpforo_get_login_or_register_notice_text() );
		wp_send_json_error();
	}
	if( ! $postid = wpforo_bigintval( wpfval( $_POST, 'postid' ) ) ) {
		wp_send_json_error();
	}
	$status = wpfval( $_POST, 'status' );
	if( $status === 'approve' ) {
		WPF()->moderation->post_approve( $postid );
		wp_send_json_success();
	} elseif( $status === 'unapprove' ) {
		WPF()->moderation->post_unapprove( $postid );
		wp_send_json_success();
	}
	wp_send_json_error();
}

add_action( 'wp_ajax_wpforo_close_ajax', 'wpf_close' );
function wpf_close() {
	wpforo_verify_nonce( 'wpforo_close_ajax' );
	if( ! WPF()->current_userid ) {
		wp_send_json_error();
	}
	
	if( ! $topicid = wpforo_bigintval( wpfval( $_POST, 'topicid' ) ) ) {
		wp_send_json_error();
	}
	$status = wpfval( $_POST, 'status' );
	if( $status === 'closed' ) {
		WPF()->topic->open( $topicid );
		wp_send_json_success();
	} elseif( $status === 'close' ) {
		WPF()->topic->close( $topicid );
		wp_send_json_success();
	}
	wp_send_json_error();
}

add_action( 'wp_ajax_wpforo_post_edit', 'wpforo_post_edit' );
add_action( 'wp_ajax_nopriv_wpforo_post_edit', 'wpforo_post_edit' );
function wpforo_post_edit() {
	wpforo_verify_nonce( 'wpforo_post_edit' );
	$r = [ 'html' => '' ];
	if( $postid = wpforo_bigintval( wpfval( $_POST, 'postid' ) ) ) {
		if( $post = WPF()->post->get_post( $postid, false ) ) {
			if( WPF()->perm->forum_can( 'eor', $post['forumid'] ) || WPF()->perm->forum_can(
					'eot',
					$post['forumid']
				) ) {
				if( $topic = WPF()->topic->get_topic( $post['topicid'] ) ) {
					$postmetas    = (array) WPF()->postmeta->get_postmeta( $postid, '', true );
					$post['body'] = htmlspecialchars( $post['body'], ENT_NOQUOTES );
					$values       = array_merge( $post, $topic, $postmetas );
					$values       = apply_filters( 'wpforo_edit_post_ajax', $values, $post, $topic, $postmetas );
					ob_start();
					if( intval( $post['is_first_post'] ) ) {
						WPF()->form->current['varname'] = 'thread';
						WPF()->tpl->topic_form( $post['forumid'], $values );
					} else {
						WPF()->form->current['varname'] = 'post';
						if( trim( (string) $post['title'] ) ) {
							$values['title'] = $post['title'];
						}
						WPF()->tpl->reply_form( $topic, $values );
					}
					$r['html'] = ob_get_clean();
					wp_send_json_success( $r );
				}
			}
		}
	}
	wp_send_json_error( $r );
}

add_action( 'wp_ajax_wpforo_delete_ajax', 'wpf_delete' );
function wpf_delete() {
	wpforo_verify_nonce( 'wpforo_delete_ajax' );
	$resp   = [];
	$status = (string) wpfval( $_POST, 'status' );
	$postid = (int) wpfval( $_POST, 'postid' );
	if( $status === 'topic' ) {
		if( WPF()->topic->delete( $postid ) ) {
			$forumid        = (int) wpfval( $_POST, 'forumid' );
			$resp           = [
				'postid'   => $postid,
				'location' => $forumid ? WPF()->forum->get_forum_url( $forumid ) : wpforo_home_url(),
			];
			$resp['notice'] = WPF()->notice->get_notices();
			wp_send_json_success( $resp );
		}
	} elseif( $status === 'reply' ) {
		if( WPF()->post->delete( $postid ) ) {
			$root               = (int) WPF()->post->get_root( $postid );
			$root_replies_count = (int) WPF()->post->get_root_replies_count( $root );
			$resp               = [
				'postid'     => $postid,
				'root'       => $root,
				'root_count' => $root_replies_count,
			];
			$resp['notice']     = WPF()->notice->get_notices();
			wp_send_json_success( $resp );
		}
	}
	
	$resp['notice'] = WPF()->notice->get_notices();
	wp_send_json_error( $resp );
}

add_action( 'wp_ajax_wpforo_layout4_loadmore', 'wpfl4_loadmore' );
add_action( 'wp_ajax_nopriv_wpforo_layout4_loadmore', 'wpfl4_loadmore' );
function wpfl4_loadmore() {
	wpforo_verify_nonce( 'wpforo_layout4_loadmore' );
	$success = false;
	WPF()->notice->add( 'wrong data', 'error' );
	$response = [ 'no_more' => 0, 'output_html' => '', 'notice' => WPF()->notice->get_notices() ];
	$request  = [
		'forumid' => 0,
		'filter'  => 'newest',
		'paged'   => 1,
	];
	$request  = array_merge( $request, $_POST );
	
	if( $forumid = intval( $request['forumid'] ) ) {
		$items_count = 0;
		$childs      = WPF()->forum->get_childs( $forumid );
		$childs[]    = $forumid;
		$args        = [
			'offset'    => ( $request['paged'] - 1 ) * wpforo_setting( 'forums', 'layout_threaded_intro_topics_count' ),
			'row_count' => wpforo_setting( 'forums', 'layout_threaded_intro_topics_count' ),
			'forumids'  => $childs,
			'orderby'   => 'type, modified',
			'order'     => 'DESC',
		];
		
		switch( $request['filter'] ) {
			case 'solved':
				$args['solved'] = 1;
				$args['type']   = 0;
			break;
			case 'unsolved':
				$args['solved'] = 0;
				$args['type']   = 0;
				$args['closed'] = 0;
			break;
			case 'hottest':
				$args['orderby'] = 'posts';
			break;
		}
		
		$topics = WPF()->topic->get_topics( $args, $items_count );
		if( $topics ) {
			ob_start();
			if( function_exists( 'wpforo_thread_forum_template' ) ) {
				foreach( $topics as $topic ) {
					wpforo_thread_forum_template( $topic['topicid'] );
				}
			}
			$response['output_html'] = ob_get_clean();
			$success                 = true;
			$response['notice']      = '';
			if( count( $topics ) < wpforo_setting( 'forums', 'layout_threaded_intro_topics_count' ) ) {
				$response['no_more'] = 1;
			}
		} else {
			$response['no_more'] = 1;
		}
		
		if( $response['no_more'] ) {
			WPF()->notice->add( 'all topics has been loaded in this list', 'success' );
			$response['notice'] = WPF()->notice->get_notices();
		}
	}
	
	if( $success ) {
		wp_send_json_success( $response );
	} else {
		wp_send_json_error( $response );
	}
}

add_action( 'wp_ajax_wpforo_topic_portable_form', 'wpforo_topic_portable_form' );
add_action( 'wp_ajax_nopriv_wpforo_topic_portable_form', 'wpforo_topic_portable_form' );
function wpforo_topic_portable_form() {
	wpforo_verify_nonce( 'wpforo_topic_portable_form' );
	$html = '';
	if( $forumid = wpfval( $_POST, 'forumid' ) ) {
		WPF()->form->current['varname'] = 'thread';
		ob_start();
		WPF()->tpl->topic_form( $forumid );
		$html = trim( (string) ob_get_clean() );
	}
	if( $html ) {
		wp_send_json_success( $html );
	} else {
		wp_send_json_error();
	}
}

add_action( 'wp_ajax_wpforo_qa_comment_loadrest', 'wpforo_qa_comment_loadrest' );
add_action( 'wp_ajax_nopriv_wpforo_qa_comment_loadrest', 'wpforo_qa_comment_loadrest' );
function wpforo_qa_comment_loadrest() {
	wpforo_verify_nonce( 'wpforo_qa_comment_loadrest' );
	WPF()->notice->add( 'wrong data', 'error' );
	$response = [ 'output_html' => '', 'notice' => WPF()->notice->get_notices() ];
	if( $parentid = wpfval( $_POST, 'parentid' ) ) {
		$args = [
			'root'      => $parentid,
			'offset'    => wpforo_setting( 'topics', 'layout_qa_comments_limit_count' ),
			'row_count' => PHP_INT_MAX,
		];
		if( ! wpforo_root_exist() ) {
			unset( $args['root'] );
			$args['parentid'] = $parentid;
		}
		if( $comments = WPF()->post->get_posts( $args ) ) {
			ob_start();
			include_once( wpftpl( 'layouts/3/comment.php' ) );
			ob_clean();
			
			foreach( $comments as $comment ) {
				wpforo_qa_comment_template( $comment );
			}
			
			$response['output_html'] = ob_get_clean();
			$response['notice']      = '';
			wp_send_json_success( $response );
		}
	}
	wp_send_json_error( $response );
}

add_action( 'wp_ajax_wpforo_post_url_fixer', 'wpforo_post_url_fixer' );
add_action( 'wp_ajax_nopriv_wpforo_post_url_fixer', 'wpforo_post_url_fixer' );
function wpforo_post_url_fixer() {
	wpforo_verify_nonce( 'wpforo_post_url_fixer' );
	if( ( $postid = wpforo_bigintval( wpfval( $_POST, 'postid' ) ) ) && ( $referer = wpfval( $_POST, 'referer' ) ) ) {
		if( is_wpforo_url( $referer ) ) {
			$diff         = 100;
			$referer_hash = md5( $referer . $postid );
			$nowtime      = time();
			if( $lasttime = (int) get_transient( 'wpforo_post_url_fixer' . $referer_hash ) ) {
				$diff = $nowtime - $lasttime;
			}
			if( $diff <= 8 ) {
				exit();
			}
			set_transient( 'wpforo_post_url_fixer' . $referer_hash, $nowtime, 1000 );
			if( $post = WPF()->post->get_post( $postid ) ) {
				echo WPF()->post->get_url( $post );
			}
		}
	}
	exit();
}

add_action( 'wp_ajax_wpforo_update_database', 'wpforo_ajax_update_database' );
function wpforo_ajax_update_database() {
	check_admin_referer( 'wpforo_update_database' );
	wpforo_set_max_execution_time();
	wpforo_update_db();
	exit();
}

############### Sending Email  ##################
function wpforo_set_html_content_type() {
	return apply_filters( 'wpforo_emails_content_type', 'text/html' );
}

function __wpforo_set_html_content_type() {
	return 'text/html';
}

function wpforo_mail_from_name() {
	return wpforo_setting( 'email', 'from_name' ) ?: get_option( 'blogname' );
}

function wpforo_mail_from_email() {
	return wpforo_setting( 'email', 'from_email' ) ?: get_option( 'admin_email' );
}

function wpforo_mail_headers( $from_name = '', $from_email = '', $cc = [], $bcc = [] ) {
	$H = [];
	if( ! $from_name ) {
		$from_name = wpforo_mail_from_name();
	}
	if( ! $from_email ) {
		$from_email = wpforo_mail_from_email();
	}
	$H[] = 'From: ' . $from_name . ' <' . $from_email . '>';
	if( ! empty( $cc ) ) {
		foreach( $cc as $c ) {
			$c   = sanitize_email( $c );
			$H[] = 'CC: ' . $c;
		}
	}
	if( ! empty( $bcc ) ) {
		foreach( $bcc as $b ) {
			$b   = sanitize_email( $b );
			$H[] = 'BCC: ' . $b;
		}
	}
	
	return $H;
}

function wpforo_admin_mail_headers( $from_name = '', $from_email = '', $cc = [], $bcc = [] ) {
	$H = [];
	if( ! $from_name ) {
		$from_name = wpforo_mail_from_name();
	}
	if( ! $from_email ) {
		$from_email = wpforo_mail_from_email();
	}
	$H[] = 'From: ' . $from_name . ' <' . $from_email . '>';
	if( empty( $cc ) ) $cc = wpforo_setting( 'email', 'admin_emails' );
	if( ! empty( $cc ) ) {
		foreach( $cc as $c ) {
			$c   = sanitize_email( $c );
			$H[] = 'CC: ' . $c;
		}
	}
	if( ! empty( $bcc ) ) {
		foreach( $bcc as $b ) {
			$b   = sanitize_email( $b );
			$H[] = 'BCC: ' . $b;
		}
	}
	
	return $H;
}

function wpforo_get_login_or_register_notice_text() {
	$popup_html = '';
	if( ! wpforo_is_bot() ) {
		$popup_html = sprintf(
			wpforo_phrase( 'Please %s or %s', false ),
			'<a href="' . wpforo_login_url() . '" rel="noindex,nofollow">' . wpforo_phrase(
				'Login',
				false
			) . '</a>',
			'<a href="' . wpforo_register_url() . '" rel="noindex,nofollow">' . wpforo_phrase(
				'Register',
				false
			) . '</a>'
		);
	}
	
	return apply_filters( 'wpforo_login_or_register_popup_message', $popup_html );
}

function wpforo_dynamic_phrases_register() {
	if( ! $js = WPF()->phrase->get_wpforo_phrases_inline_js() ) {
		return;
	}
	$md5_js = md5( $js );
	
	$inline          = false;
	$dynamic_js_file = WPF()->folders['assets']['dir'] . DIRECTORY_SEPARATOR . 'phrases.js';
	if( ! file_exists( $dynamic_js_file ) || $md5_js !== md5_file( $dynamic_js_file ) ) {
		$result = wpforo_write_file( $dynamic_js_file, $js );
		if( wpfval( $result, 'error' ) ) {
			$inline = true;
		}
	}
	
	wp_register_script(
		'wpforo-dynamic-phrases',
		WPF()->folders['assets']['url'] . '/phrases.js',
		false,
		WPFORO_VERSION . '.' . $md5_js
	);
	if( $inline ) {
		$js = preg_replace( '|[\r\n\t]+|', '', $js );
		wp_add_inline_script( 'wpforo-dynamic-phrases', $js );
	}
}

function wpforo_dynamic_style_enqueue() {
	if( ! $css = WPF()->tpl->generate_dynamic_css() ) {
		return;
	}
	$md5_css = md5( $css );
	
	$inline           = false;
	$dynamic_css_file = WPF()->folders['assets']['dir'] . DIRECTORY_SEPARATOR . 'colors.css';
	if( ! file_exists( $dynamic_css_file ) || $md5_css !== md5_file( $dynamic_css_file ) ) {
		$result = wpforo_write_file( $dynamic_css_file, $css );
		if( wpfval( $result, 'error' ) ) {
			$inline = true;
		}
	}
	
	wp_register_style(
		'wpforo-dynamic-style',
		WPF()->folders['assets']['url'] . '/colors.css',
		false,
		WPFORO_VERSION . '.' . $md5_css
	);
	if( $inline ) {
		$css = preg_replace( '|[\r\n\t]+|', '', $css );
		wp_add_inline_style( 'wpforo-dynamic-style', $css );
	}
	
	wp_enqueue_style( 'wpforo-dynamic-style' );
}

add_action( 'wp_enqueue_scripts', 'wpforo_dynamic_style_enqueue', 999 );

function wpforo_frontend_register_scripts() {
	wp_register_style(
		'wpforo-font-awesome',
		WPFORO_URL . '/assets/css/font-awesome/css/fontawesome-all.min.css',
		false,
		'6.1.1'
	);
	//wp_register_style( 'wpforo-font-awesome-rtl', WPFORO_URL . '/assets/css/font-awesome/css/font-awesome-rtl.css', [ 'wpforo-font-awesome' ], WPFORO_VERSION );
	wpforo_dynamic_phrases_register();
	wp_register_script( 'wpforo-frontend-js', WPFORO_URL . '/assets/js/frontend.js', [
		'jquery',
		'wpforo-dynamic-phrases',
	],                  WPFORO_VERSION, true );
	wp_localize_script( 'wpforo-frontend-js', 'wpforo', [
		'ajax_url'         => wpforo_get_ajax_url(),
		'nonces'           => wpforo_generate_ajax_nonces(),
		'settings_slugs'   => WPF()->settings->slugs,
		'editor_settings'  => WPF()->tpl->editor_buttons( 'post' ),
		'revision_options' => WPF()->settings->posting,
		'notice'           => [
			'login_or_register' => wpforo_get_login_or_register_notice_text(),
			'timeouts'          => WPF()->notice->get_timeouts(),
		],
	] );
	wp_register_script( 'wpforo-ajax', WPFORO_URL . '/assets/js/ajax.js', [
		'suggest',
		'wpforo-frontend-js',
	],                  WPFORO_VERSION, true );
	wp_register_style( 'wpforo-style', wpftpl_url( 'style.css' ), false, WPFORO_VERSION );
	wp_register_style( 'wpforo-style-rtl', wpftpl_url( 'style-rtl.css' ), false, WPFORO_VERSION );
	wp_register_style( 'wpforo-widgets', wpftpl_url( 'widgets.css' ), [], WPFORO_VERSION );
	wp_register_style( 'wpforo-widgets-rtl', wpftpl_url( 'widgets-rtl.css' ), [], WPFORO_VERSION );
	wp_register_script( 'wpforo-widgets-js', WPFORO_URL . '/assets/js/widgets.js', [ 'jquery' ], WPFORO_VERSION, true );
	$wpforo_widgets = [
		'ajax_url'                    => wpforo_get_ajax_url(),
		'is_live_notifications_on'    => 0,
		'live_notifications_start'    => 30000,
		'live_notifications_interval' => 60000,
	];
	if( WPF()->current_userid && wpforo_setting( 'notifications', 'notifications' ) && wpforo_setting(
			'notifications',
			'notifications_live'
		) ) {
		$start    = apply_filters( 'wpforo_notifications_list', 30000 );
		$interval = apply_filters( 'wpforo_notifications_list', 60000 );
		if( $interval < 10000 ) {
			$interval = 10000;
		}
		$wpforo_widgets['is_live_notifications_on']    = 1;
		$wpforo_widgets['live_notifications_start']    = $start;
		$wpforo_widgets['live_notifications_interval'] = $interval;
	}
	wp_localize_script( 'wpforo-widgets-js', 'wpforo_widgets', $wpforo_widgets );
	
	if( ! WPF()->perm->forum_can( 'va' ) || ! WPF()->usergroup->can( 'caa' ) ) {
		wp_add_inline_script(
			'wpforo-frontend-js',
			"jQuery(document).ready(function($){
            $('#wpforo-wrap').on('click', '.attach_cant_view', function(){
               wpforo_notice_show(
                    '<p>" . addslashes(
				(string) ( is_user_logged_in() ? wpforo_phrase(
					'You are not permitted to view this attachment',
					false
				) : wpforo_get_login_or_register_notice_text() )
			) . "</p>'
               );
            });
        })"
		);
	}
	
	do_action( 'wpforo_frontend_register_scripts' );
	
	wpforo_frontend_enqueue_scripts();
}

add_action( 'wp_enqueue_scripts', 'wpforo_frontend_register_scripts' );

function wpforo_frontend_enqueue_scripts() {
	$fontawesome = wpforo_setting( 'general', 'fontawesome' );
	if( $fontawesome === 'sitewide' || ( $fontawesome === 'forum' && is_wpforo_page() ) ) {
		wp_enqueue_style( 'wpforo-font-awesome' );
		//if( is_rtl() ) wp_enqueue_style( 'wpforo-font-awesome-rtl' );
	}
	
	if( is_wpforo_page() ) {
		wp_enqueue_script( 'wpforo-dynamic-phrases' );
		wp_enqueue_script( 'wpforo-frontend-js' );
		wp_enqueue_script( 'wpforo-ajax' );
		if( is_rtl() ) {
			wp_enqueue_style( 'wpforo-style-rtl' );
		} else {
			wp_enqueue_style( 'wpforo-style' );
		}
	}
	
	if( is_rtl() ) {
		wp_enqueue_style( 'wpforo-widgets-rtl' );
	} else {
		wp_enqueue_style( 'wpforo-widgets' );
	}
	
	do_action( 'wpforo_frontend_enqueue_scripts' );
}

function wpforo_style_options( $css ) {
	$css .= "\r\n#wpforo-wrap .wpforo-forum-title{font-size: " . wpforo_setting(
			'styles',
			'font_size_forum'
		) . "px!important; line-height: " . ( wpforo_setting( 'styles', 'font_size_forum' ) + 1 ) . "px!important;}";
	$css .= "\r\n#wpforo-wrap .wpforo-topic-title a { font-size: " . wpforo_setting(
			'styles',
			'font_size_topic'
		) . "px!important; }";
	$css .= "\r\n#wpforo-wrap .wpforo-post .wpf-right .wpforo-post-content {font-size: " . wpforo_setting(
			'styles',
			'font_size_post_content'
		) . "px!important;}\r\n#wpforo-wrap .wpforo-post .wpf-right .wpforo-post-content p {font-size: " . wpforo_setting(
		        'styles',
		        'font_size_post_content'
	        ) . "px;}";
	
	if( 'bottom' === wpforo_setting( 'posting', 'topic_editor_toolbar_location' ) ) {
		$css .= "\r\n
	    #wpforo #wpforo-wrap .wpf-topic-create .mce-container-body{display: flex; flex-direction: column;}
	    #wpforo #wpforo-wrap .wpf-topic-create .mce-top-part{order: 1}
	    #wpforo #wpforo-wrap .wpf-topic-create .wpf-field-wrap > .wpf-extra-fields,
        #wpforo #wpforo-wrap .wpforo-portable-form-wrap .wpf-extra-fields,
        #wpforo #wpforo-wrap .wpforo-messages-content .wpfpm-main .wpfpm-form-wrapper .wpf-extra-fields{ margin-top: 10px !important; }";
	}
	if( 'bottom' === wpforo_setting( 'posting', 'reply_editor_toolbar_location' ) ) {
		$css .= "\r\n
	    #wpforo #wpforo-wrap .wpf-post-create .mce-container-body{display: flex; flex-direction: column;}
	    #wpforo #wpforo-wrap .wpf-post-create .mce-top-part{order: 1}
	    #wpforo #wpforo-wrap .wpf-post-create .wpf-field-wrap > .wpf-extra-fields,
        #wpforo #wpforo-wrap .wpforo-portable-form-wrap .wpf-extra-fields,
        #wpforo #wpforo-wrap .wpforo-messages-content .wpfpm-main .wpfpm-form-wrapper .wpf-extra-fields{ margin-top: 10px !important; }";
	}
	if( $ccss = wpforo_setting( 'styles', 'custom_css' ) ) $css .= "\r\n" . stripslashes( (string) $ccss );
	
	return $css;
}

add_filter( 'wpforo_dynamic_css_filter', 'wpforo_style_options' );

function wpforo_admin_enqueue() {
	wp_register_style(
		'wpforo-font-awesome',
		WPFORO_URL . '/assets/css/font-awesome/css/fontawesome-all.min.css',
		false,
		'6.1.1'
	);
	wp_register_style(
		'wpforo-iconpicker-css',
		WPFORO_URL . '/admin/assets/third-party/iconpicker/fontawesome-iconpicker.min.css',
		[ 'wpforo-font-awesome' ],
		'2.0.0'
	);
	wp_register_script(
		'wpforo-iconpicker-js',
		WPFORO_URL . '/admin/assets/third-party/iconpicker/fontawesome-iconpicker.js',
		[ 'jquery' ],
		'2.0.0',
		true
	);
	wp_register_style( 'wpforo-admin', WPFORO_URL . '/admin/assets/css/admin.css', false, WPFORO_VERSION );
	wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', [
		'clear'         => __( 'Clear' ),
		'defaultString' => __( 'Default' ),
		'pick'          => __( 'Select Color' ),
	] );
	wp_register_script(
		'wpforo-contenthover-addons',
		WPFORO_URL . '/admin/assets/js/contenthover/jquery.contenthover.min.js',
		[ 'jquery' ],
		WPFORO_VERSION,
		false
	);
	wp_register_script(
		'wpforo-backend-widgets-js',
		WPFORO_URL . '/admin/assets/js/widgets.js',
		[ 'jquery' ],
		WPFORO_VERSION,
		true
	);
	wp_register_script(
		'wpforo-backend-js',
		WPFORO_URL . '/admin/assets/js/backend.js',
		[ 'jquery' ],
		WPFORO_VERSION,
		false
	);
	wp_localize_script( 'wpforo-backend-js', 'wpforo_admin', [
		'phrases' => [
			'move'   => __( 'Move', 'wpforo' ),
			'delete' => __( 'Delete', 'wpforo' ),
		],
	] );
	WPF()->settings->init_info();
	wp_localize_script(
		'wpforo-backend-js', 'wpforo', [
			                   'ajax_url' => wpforo_get_ajax_url(),
			                   'nonces'   => wpforo_generate_ajax_nonces(),
			                   'board'    => [ 'has_more_boards' => is_wpforo_multiboard() ],
			                   'settings' => [
				                   'info' => [
					                   'core'   => WPF()->settings->info->core,
					                   'addons' => WPF()->settings->info->addons,
				                   ],
			                   ],
		                   ]
	);
	wp_register_style(
		'wpforo-deactivation-css',
		WPFORO_URL . '/admin/assets/css/deactivation-dialog.css',
		[],
		WPFORO_VERSION
	);
	wp_register_script(
		'wpforo-deactivation-js',
		WPFORO_URL . '/admin/assets/js/deactivation-dialog.js',
		[ 'jquery' ],
		WPFORO_VERSION
	);
	wp_localize_script( 'wpforo-deactivation-js', 'wpforo_deactivation_obj', [
		'msgReasonRequired'             => __( 'Please choose one reasons before sending a feedback!', 'wpforo' ),
		'msgReasonDescRequired'         => __( 'Please provide more information', 'wpforo' ),
		'msgFeedbackHasEmailNoCheckbox' => __(
			'With the email address, please check the "I agree to receive email" checkbox to proceed.',
			'wpforo'
		),
		'msgFeedbackHasCheckboxNoEmail' => __( 'Please fill your email address for feedback', 'wpforo' ),
		'msgFeedbackNotValidEmail'      => __( 'Your email address is not valid', 'wpforo' ),
		'adminUrl'                      => get_admin_url(),
	] );
	wp_register_script( 'wpforo-iris', admin_url( 'js/iris.min.js' ), [
		'jquery-ui-draggable',
		'jquery-ui-slider',
		'jquery-touch-punch',
	],                  false, true );
	
	do_action( 'wpforo_admin_register_scripts' );
	
	if( ! empty( $_GET['page'] ) && strpos( (string) $_GET['page'], 'wpforo' ) === 0 ) {
		wp_enqueue_style( 'wpforo-admin' );
		wp_enqueue_script( 'wpforo-backend-js' );
		wp_enqueue_style( 'wpforo-iconpicker-css' );
		wp_enqueue_script( 'wpforo-iconpicker-js' );
		wp_enqueue_style( 'wpforo-font-awesome' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-widget' );
		wp_enqueue_script( 'jquery-ui-mouse' );
		wp_enqueue_script( 'jquery-ui-position' );
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'jquery-ui-droppable' );
		wp_enqueue_script( 'jquery-ui-menu' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-color' );
		wp_enqueue_script( 'wp-lists' );
		if( preg_match( '#^wpforo-(?:\d+-)?forums$#iu', (string) $_GET['page'] ) ) {
			if( ! empty( $_GET['action'] ) ) {
				//Just for excluding 'nav-menu' js loading//
				wp_enqueue_script( 'postbox' );
				wp_enqueue_script( 'link' );
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker-script-handle' );
			} else {
				wp_enqueue_script( 'nav-menu' );
			}
		} elseif( preg_match(
			          '#^wpforo-(?:\d+-)?settings$#iu',
			          (string) $_GET['page']
		          ) && ! empty( $_GET['wpf_tab'] ) && $_GET['wpf_tab'] === 'styles' ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wpforo-iris' );
			wp_enqueue_script( 'wp-color-picker' );
		} elseif( $_GET['page'] === wpforo_prefix_slug( 'dashboard' ) || $_GET['page'] === 'wpforo-overview' ) {
			wp_enqueue_script( 'postbox' );
			wp_enqueue_script( 'link' );
		} elseif( preg_match( '#^wpforo-(?:\d+-)?addons$#iu', (string) $_GET['page'] ) ) {
			wp_enqueue_script( 'wpforo-contenthover-addons' );
		}
	}
	if( ! wpforo_get_option( 'deactivation_dialog_never_show', false, false ) && ( strpos(
		                                                                               wpforo_get_request_uri(),
		                                                                               '/plugins.php'
	                                                                               ) !== false ) ) {
		wp_enqueue_style( 'wpforo-deactivation-css' );
		wp_enqueue_script( 'wpforo-deactivation-js' );
	}
	
	$screen = get_current_screen();
	if( ( 'user-edit' === $screen->id || 'profile' === $screen->id ) ) {
		wp_enqueue_style( 'wpforo-font-awesome' );
	}
	
	if( $screen->id === 'widgets' ) {
		wp_enqueue_script( 'wpforo-backend-widgets-js' );
	}
	
	do_action( 'wpforo_admin_enqueue_scripts' );
}

add_action( 'admin_enqueue_scripts', 'wpforo_admin_enqueue' );

function wpforo_admin_permalink_notice() {
	$permalink_structure = get_option( 'permalink_structure' );
	if( ! $permalink_structure ) {
		$class   = 'notice notice-warning';
		$message = __(
			'IMPORTANT: wpForo can\'t work with default permalink, please change permalink structure',
			'wpforo'
		);
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}
}

add_action( 'admin_notices', 'wpforo_admin_permalink_notice' );

function wpforo_userform_to_wpuser_html_form( $wp_user ) {
	if( is_super_admin() ) {
		$userid             = 0;
		$groupid            = 0;
		$timezone           = '';
		$secondary_groupids = [];
		if( is_object( $wp_user ) ) {
			$userid             = $wp_user->ID;
			$user               = WPF()->member->get_member( $wp_user->ID );
			$groupid            = $user['groupid'];
			$timezone           = sanitize_text_field( $user['timezone'] );
			$secondary_groupids = $user['secondary_groupids'];
		}
		?>
        <style>
            #wpf-rating-table {
                width: 100%;
                font-size: 12px;
            }

            #wpf-rating-table h4 {
                margin: 0;
                padding: 0;
                font-size: 14px;
                font-weight: bold;
                white-space: nowrap;
            }

            #wpf-rating-table .wpf-badge-full {
                color: #FFFFFF;
                white-space: nowrap;
                font-size: 15px;
                line-height: 16px;
                font-weight: bold;
                text-align: center;
                display: inline-block;
                padding: 2px 8px;
                min-width: 30px;
            }

            #wpf-rating-table .wpf-badge-full.wpf-badge-level-6, #wpf-rating-table .wpf-badge-full.wpf-badge-level-7, #wpf-rating-table .wpf-badge-full.wpf-badge-level-8 {
                font-size: 18px !important;
            }

            #wpf-rating-table .wpf-badge-full.wpf-badge-level-9, #wpf-rating-table .wpf-badge-full.wpf-badge-level-10 {
                font-size: 22px !important;
            }

            #wpf-rating-table th, #wpf-rating-table td {
                padding: 5px 10px;
                text-align: left;
                vertical-align: top;
            }

            #wpf-rating-table tr:nth-child(odd) {
                background: #f5f5f5;
            }

            #wpf-rating-table tr:nth-child(even) {
                background: #FFFFFF;
            }

            #wpf-rating-table th {
                text-transform: uppercase;
                font-size: 12px;
                padding: 10px;
            }

            .wpforo-profile-table input[type=checkbox] {
                width: auto !important;
            }

            @media screen and (max-width: 700px) {
                #wpf-rating-table th, #wpf-rating-table td {
                    display: table-cell !important;
                }
            }
        </style>
        <h2 style="margin-bottom: 30px; margin-top: 50px;"><?php _e( 'Forum Profile Fields - wpForo' ); ?></h2>
        <table class="form-table wpforo-profile-table"
               style="box-shadow: 1px 1px 6px #cccccc; background: #f7f7f7; margin-bottom: 30px; width: 97%;">
            <tr>
                <td colspan="2" style="padding: 5px;"></td>
            </tr>
            <tr class="form-field">
                <th scope="row" style="padding: 10px 20px 10px 20px; width: 30%;">
                    <label for="wpforo_usergroup">
						<?php _e( 'Forum - Usergroup', 'wpforo' ); ?>
                    </label>
					<?php if( wpforo_setting( 'authorization', 'role_synch' ) ): ?>
                        <p class="description" style="font-weight: normal;">
							<?php $wpforo_synch_table = admin_url(
								'admin.php?page=' . wpforo_prefix_slug( 'usergroups' )
							) ?>
							<?php echo sprintf(
								__(
									'Forum Usergroups are synched with User Roles based on the %s. When you change this user Role the Usergroup is automatically changed according to that table.',
									'wpforo'
								),
								'<a href="' . $wpforo_synch_table . '" target="_blank">Role-Usergroup synchronization table</a>'
							); ?>
                        </p>
					<?php endif; ?>
                </th>
                <td style="padding: 15px 20px 10px 20px; vertical-align: top;">
					<?php if( wpforo_setting( 'authorization', 'role_synch' ) ): ?>
                        <select id="wpforo_usergroup" disabled="disabled">
                            <option value="0"><?php _e( 'Synced with user role', 'wpforo' ); ?></option>
							<?php WPF()->usergroup->show_selectbox( $groupid ); ?>
                        </select>
                        <input type="hidden" name="wpforo_usergroup" value="<?php echo $groupid; ?>">
                        &nbsp; <span
                                style="color: green"><?php _e(
								'Role-Usergroup Synchronization is Turned ON!',
								'wpforo'
							); ?></span>
                        <br/>
                        <p class="description"
                           style="font-weight: normal; font-size: 13px; line-height: 18px;"><?php _e(
								'This user Usergroup is automatically changed according to current Role. If you want to disable Role-Usergroup synchronization and manage Usergroups and User Roles independently, please navigate to <b>Forums > Settings > Features</b> admin page and disable "Role-Usergroup Synchronization" option.',
								'wpforo'
							); ?></p>
					<?php else: ?>
                        <select id="wpforo_usergroup"
                                name="wpforo_usergroup"<?php if( wpforo_is_owner( $userid ) || ! current_user_can(
								'administrator'
							) ) {
							echo ' disabled="disabled"';
						} ?>>
							<?php WPF()->usergroup->show_selectbox( $groupid ?: WPF()->usergroup->default_groupid ); ?>
                        </select>
					<?php endif; ?>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" style="padding: 10px 20px 10px 20px;">
                    <label for="wpforo_usergroup">
						<?php _e( 'Forum - Secondary Usergroups', 'wpforo' ); ?>
                    </label>
                </th>
                <td style="padding: 15px 20px 10px 20px; vertical-align: top;">
					<?php $usergroups = WPF()->usergroup->get_secondary_groups(); ?>
					<?php if( ! empty( $usergroups ) ): ?>
						<?php foreach( $usergroups as $usergroup ): ?>
							<?php if( $usergroup['groupid'] == 1 || $usergroup['groupid'] == 4 ) {
								continue;
							} //|| $usergroup['groupid'] == $groupid ?>
                            <label style="min-width: 20%; display: inline-block; padding-bottom: 5px;">
                                <input type="checkbox"
                                       name="wpforo_secondary_groupids[]"
                                       value="<?php echo intval( $usergroup['groupid'] ) ?>"
									<?php checked(
										in_array( (int) $usergroup['groupid'], (array) $secondary_groupids, true )
									); ?>>&nbsp;
								<?php echo esc_html( $usergroup['name'] ); ?>
                            </label>
						<?php endforeach; ?>
					<?php endif; ?>
                    <input name="wpforo_secondary_groupids[]" value="0" type="hidden">
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" style="padding: 10px 20px 10px 20px;">
                    <label for="wpforo_usertimezone"><?php _e( 'Forum - User Timezone', 'wpforo' ); ?></label>
                </th>
                <td style="padding: 15px 20px 10px 20px; vertical-align: top;">
                    <select name="wpforo_usertimezone" id="wpforo_usertimezone">
						<?php echo wp_timezone_choice( $timezone ); ?>
                    </select>
                </td>
            </tr>
			<?php if( ! empty( $user ) ) : ?>
                <tr class="form-field">
                    <th scope="row" style="padding: 10px 20px 10px 20px;">
                        <label><?php _e( 'User Reputation', 'wpforo' ); ?></label>
                        <p class="description"
                           style="font-weight: normal;"><?php _e(
								'By default all members get rating badges and titles based on number of posts. However, using this option you can grant lower or higher rating to certain user (this user). The default member reputation badges, titles and points can be managed in Forums > Settings > Members Tab.',
								'wpforo'
							) ?></p>
                    </th>
                    <td style="padding: 15px 20px 10px 20px; vertical-align: top;">
                        <div style="padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #cccccc;">
                            <label><input type="radio" id="wpf-user-rating-default"
                                          name="wpforo_use_member_custom_points"
                                          value="0" <?php checked( ! $user['custom_points'] ) ?>> <?php _e(
									'Default Rating',
									'wpforo'
								) ?></label>
                            &nbsp;&nbsp;
                            <label><input type="radio" id="wpf-user-rating-custom"
                                          name="wpforo_use_member_custom_points"
                                          value="1" <?php checked( $user['custom_points'] ) ?>> <?php _e(
									'Custom Rating',
									'wpforo'
								) ?></label>
                        </div>

                        <table id="wpf-rating-table" cellspacing="0" cellpadding="0" border="0"
                               style="display: block; width: 100%; height: 150px; overflow-y: scroll;  hidden; border: 1px solid #ccc; border-top: none; border-bottom: none; <?php if( ! $user['custom_points'] ) echo 'opacity:0.5;' ?>">
                            <tr>
                                <th style="text-align: center; width: 5%;">#</th>
                                <th style="width: 30%"><?php _e( 'Rating Level', 'wpforo' ); ?></th>
                                <th style="width: 35%"><?php _e( 'Rating Title', 'wpforo' ); ?></th>
                                <th style="width: 30%;text-align:center;"><?php _e( 'Rating Badge', 'wpforo' ); ?></th>
                            </tr>
							<?php $levels = WPF()->member->levels(); ?>
							<?php foreach( $levels as $level ): $points = WPF()->member->rating( $level, 'points' );
								$bgx = $level === $user['rating']['level'] ? 'background-color: #feff88' : ''; ?>
                                <tr>
                                    <td style="text-align: center;<?php echo $bgx ?>"><input type="radio"
                                                                                             name="wpforo_member_custom_points"
                                                                                             id="wpf-user-rating-<?php echo intval(
										                                                         $points
									                                                         ) ?>"
                                                                                             value="<?php echo intval(
										                                                         $points
									                                                         ) ?>" <?php checked(
											$level === $user['rating']['level']
										) ?>></td>
                                    <td style="<?php echo $bgx ?>"><h4><label
                                                    for="wpf-user-rating-<?php echo intval( $points ) ?>"><?php _e(
													'Level',
													'wpforo'
												); ?><?php echo esc_html( $level ) ?></label>
                                        </h4></td>
                                    <td style="<?php echo $bgx ?>"><?php echo WPF()->member->rating(
											$level,
											'title'
										) ?></td>
                                    <td style="text-align:center;<?php echo $bgx ?>">
                                        <div class="wpf-badge-full wpf-badge-level-<?php echo esc_attr( $level ) ?>"
                                             style="color:<?php echo WPF()->member->rating(
											     $level,
											     'color'
										     ) ?>;"><?php echo WPF()->member->rating_badge( $level, 'full' ); ?></div>
                                    </td>
                                </tr>
							<?php endforeach; ?>
                        </table>
                    </td>
                </tr>
			<?php endif; ?>
            <tr>
                <td colspan="2" style="padding: 5px;"></td>
            </tr>
        </table>
        <script>
			jQuery(document).ready(function ($) {
				$('#wpf-user-rating-default').change(function () {if (this.checked) {$('#wpf-rating-table').css('opacity', 0.5);}});
				$('#wpf-user-rating-custom').change(function () {if (this.checked) { $('#wpf-rating-table').css('opacity', 1);}});
			});
        </script>
		<?php
	}
}

add_action( 'user_new_form', 'wpforo_userform_to_wpuser_html_form' );
add_action( 'show_user_profile', 'wpforo_userform_to_wpuser_html_form' );
add_action( 'edit_user_profile', 'wpforo_userform_to_wpuser_html_form' );

add_action( 'register_form', function() {
	wp_nonce_field( 'wpforo_user_register', '_wpfnonce' );
},          21 );

function wpforo_do_hook_user_register( $userid ) {
	WPF()->member->synchronize_user( $userid );
	if( wpfval( $_POST, 'wpfreg' ) ) {
		$data           = $_POST;
		$data['userid'] = $userid;
		$data['wpfreg'] = wpforo_clear_array( $data['wpfreg'], [
			'user_login',
			'user_email',
			'user_pass1',
			'user_pass2',
		],                                    'key' );
		WPF()->member->update( $data, 'full', false );
	}
}

add_action( 'user_register', 'wpforo_do_hook_user_register', 10, 1 );

function wpforo_do_hook_update_profile( $userid ) {
	if( $userid ) {
		if( current_user_can( 'create_users' ) || current_user_can( 'edit_user' ) ) {
			if( wpfval( $_POST, 'wpforo_usergroup' ) || wpfkey( $_POST, 'wpforo_usertimezone' ) ) {
				$member                = wpforo_member( $userid );
				$can_change_own_rating = true;
				if( wpfval( $member, 'userid' ) ) {
					$groupid            = $member['groupid'];
					$secondary_groupids = $member['secondary_groupids'];
					if( current_user_can( 'administrator' ) ) {
						if( wpfval( $_POST, 'wpforo_usergroup' ) ) {
							if( $userid != 1 && ! wpforo_is_owner( $userid ) && current_user_can( 'administrator' ) ) {
								$groupid = intval( $_POST['wpforo_usergroup'] );
							}
						}
						if( wpfval( $_POST, 'wpforo_secondary_groupids' ) ) {
							if( ! empty( $_POST['wpforo_secondary_groupids'] ) ) {
								$secondary_groupids = array_filter(
									array_map( 'intval', $_POST['wpforo_secondary_groupids'] )
								);
							}
						}
					} else {
						if( wpforo_is_owner( $userid ) ) {
							$can_change_own_rating = false;
						}
					}
					
					$custom_points = ( $can_change_own_rating && wpfval(
							$_POST,
							'wpforo_use_member_custom_points'
						) ) ? (int) wpfval( $_POST, 'wpforo_member_custom_points' ) : 0;
					
					$args = [
						'groupid'            => intval( $groupid ),
						'about'              => wpforo_kses( $_POST['description'], 'user_description' ),
						'timezone'           => ( isset( $_POST['wpforo_usertimezone'] ) ? sanitize_text_field(
							$_POST['wpforo_usertimezone']
						) : '' ),
						'secondary_groupids' => $secondary_groupids,
						'custom_points'      => $custom_points,
					];
					WPF()->member->update_profile_fields( $userid, $args, false );
					
					if( ! wpforo_is_owner( $userid ) ) {
						WPF()->member->inactive_to_active( $userid );
					}
				}
			}
		}
	}
}

add_action( 'personal_options_update', 'wpforo_do_hook_update_profile' );
add_action( 'edit_user_profile_update', 'wpforo_do_hook_update_profile' );

function wpforo_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
	if( ! wpforo_setting( 'profiles', 'replace_avatar' ) ) {
		return $avatar;
	}
	$user = false;
	if( is_numeric( $id_or_email ) ) {
		$id   = (int) $id_or_email;
		$user = get_user_by( 'id', $id );
	} elseif( is_object( $id_or_email ) ) {
		if( ! empty( $id_or_email->user_id ) ) {
			$id   = (int) $id_or_email->user_id;
			$user = get_user_by( 'id', $id );
		} elseif( ! empty( $id_or_email->ID ) ) {
			$id   = (int) $id_or_email->ID;
			$user = get_user_by( 'id', $id );
		}
	} else {
		$user = get_user_by( 'email', $id_or_email );
	}
	
	if( $user && is_object( $user ) ) {
		if( $src = WPF()->member->get_avatar_url( $user->data->ID ) ) {
			$avatar = "<img alt='" . esc_attr( $alt ) . "' src='" . esc_url(
					(string) $src
				) . "' class='avatar avatar-" . esc_attr( $size ) . " photo' height='" . esc_attr(
				          $size
			          ) . "' width='" . esc_attr( $size ) . "' />";
		}
	}
	
	return $avatar;
}

add_filter( 'get_avatar', 'wpforo_avatar', 10, 5 );

function wpforo_pre_get_avatar_data( $args, $id_or_email ) {
	if( wpforo_setting( 'profiles', 'replace_avatar' ) ) {
		$key = [ 'wpforo_pre_get_avatar_data', $id_or_email ];
		if( WPF()->ram_cache->exists( $key ) ) {
			$args['url'] = WPF()->ram_cache->get( $key );
		} else {
			$user = false;
			if( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
				$id_or_email = get_comment( $id_or_email );
			}
			// Process the user identifier.
			if( is_numeric( $id_or_email ) ) {
				$user = get_user_by( 'id', absint( $id_or_email ) );
			} elseif( is_string( $id_or_email ) ) {
				if( ! strpos( (string) $id_or_email, '@md5.gravatar.com' ) ) {
					$user = get_user_by( 'email', $id_or_email );
				}
			} elseif( $id_or_email instanceof WP_User ) {
				// User Object
				$user = $id_or_email;
			} elseif( $id_or_email instanceof WP_Post ) {
				// Post Object
				$user = get_user_by( 'id', (int) $id_or_email->post_author );
			} elseif( function_exists( 'is_avatar_comment_type' ) && $id_or_email instanceof WP_Comment ) {
				if( is_avatar_comment_type( get_comment_type( $id_or_email ) ) ) {
					if( ! empty( $id_or_email->user_id ) ) {
						$user = get_user_by( 'id', (int) $id_or_email->user_id );
					}
					if( ( ! $user || is_wp_error( $user ) ) && ! empty( $id_or_email->comment_author_email ) ) {
						$user = get_user_by( 'email', $id_or_email->comment_author_email );
					}
				}
			}
			
			if( $user && is_object( $user ) ) {
				if( $avatar_url = WPF()->member->get_avatar_url( $user->data->ID ) ) {
					WPF()->ram_cache->set( $key, $avatar_url );
					$args['url'] = $avatar_url;
				}
			}
		}
	}
	
	return $args;
}

add_filter( 'pre_get_avatar_data', 'wpforo_pre_get_avatar_data', 10, 2 );

function wpforo_move_uploded_default_attach( $argname, $return = 'html' ) {
	if( ! empty( $_FILES[ $argname ] ) && ! empty( $_FILES[ $argname ]['name'] ) ) {
		$name     = sanitize_file_name( $_FILES[ $argname ]['name'] );      //myimg.png
		$type     = sanitize_mime_type( $_FILES[ $argname ]['type'] );      //image/png
		$tmp_name = sanitize_text_field( $_FILES[ $argname ]['tmp_name'] ); //D:\wamp\tmp\php986B.tmp
		$error    = intval( $_FILES[ $argname ]['error'] );                 //0
		$size     = intval( $_FILES[ $argname ]['size'] );                  //6112
		
		$phpFileUploadErrors = [
			0 => 'There is no error, the file uploaded with success',
			1 => 'The uploaded file size is too big',
			2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
			3 => 'The uploaded file was only partially uploaded',
			4 => 'No file was uploaded',
			6 => 'Missing a temporary folder',
			7 => 'Failed to write file to disk.',
			8 => 'A PHP extension stopped the file upload.',
		];
		
		if( $error ) {
			WPF()->notice->add( $phpFileUploadErrors[ $error ], 'error' );
			
			return '';
		} elseif( $size > wpforo_setting( 'posting', 'max_upload_size' ) ) {
			WPF()->notice->add( 'The uploaded file size is too big', 'error' );
			
			return '';
		}
		
		if( function_exists( 'pathinfo' ) ) {
			$ext = pathinfo( $name, PATHINFO_EXTENSION );
		} else {
			$ext = substr( strrchr( (string) $name, '.' ), 1 );
		}
		$ext        = strtolower( (string) $ext );
		$mime_types = get_allowed_mime_types();
		$mime_types = array_flip( $mime_types );
		if( ! empty( $mime_types ) ) {
			$allowed_types = implode( '|', $mime_types );
			$expld         = explode( '|', $allowed_types );
			if( ! in_array( $ext, $expld ) ) {
				WPF()->notice->add( 'File type is not allowed', 'error' );
				
				return '';
			}
			if( ! WPF()->perm->can_attach_file_type( $ext ) ) {
				WPF()->notice->add( 'You are not allowed to attach this file type', 'error' );
				
				return '';
			}
		}
		
		$attach_dir = WPF()->folders['default_attachments']['dir'];
		$attach_url = WPF()->folders['default_attachments']['url//'];
		if( ! is_dir( $attach_dir ) ) wp_mkdir_p( $attach_dir );
		
		$fnm = pathinfo( $name, PATHINFO_FILENAME );
		$fnm = str_replace( ' ', '-', $fnm );
		while( strpos( (string) $fnm, '--' ) !== false ) {
			$fnm = str_replace( '--', '-', $fnm );
		}
		$fnm       = preg_replace( "/[^-a-zA-Z0-9_]/", "", (string) $fnm );
		$fnm       = trim( (string) $fnm, "-" );
		$fnm_empty = ! $fnm;
		
		$file_name = $fnm . "." . $ext;
		
		$attach_fname = time() . ( ! $fnm_empty ? '-' : '' ) . $file_name;
		$attach_path  = $attach_dir . DIRECTORY_SEPARATOR . $attach_fname;
		
		if( is_dir( $attach_dir ) && move_uploaded_file( $tmp_name, $attach_path ) ) {
			$attach_id = wpforo_insert_to_media_library( $attach_path, $fnm );
			if( $return === 'html' ) {
				return "\r\n" . '<div id="wpfa-' . $attach_id . '" class="wpforo-attached-file"><a class="wpforo-default-attachment" href="' . esc_url(
						(string) $attach_url . '/' . $attach_fname
					) . '" target="_blank" title="' . esc_attr(
					       basename( (string) $name )
				       ) . '"><i class="fas fa-paperclip"></i>&nbsp;' . esc_html(
					       basename( (string) $name )
				       ) . '</a></div>';
			} else {
				return [
					'fileurl'  => $attach_url . '/' . $attach_fname,
					'filename' => basename( (string) $name ),
					'mediaid'  => $attach_id,
				];
			}
		} else {
			WPF()->notice->add( 'Can\'t upload file', 'error' );
		}
	}
	
	return '';
}

function wpforo_add_default_attachment( $args ) {
	if( ! empty( $_FILES['attachfile'] ) && ! empty( $_FILES['attachfile']['name'] ) ) {
		if( WPF()->perm->can_attach( wpfval( $args, 'forumid' ) ) ) {
			if( $default_attach = wpforo_move_uploded_default_attach( 'attachfile' ) ) {
				$args['body']       .= $default_attach;
				$args['has_attach'] = 1;
			}
		}
	}
	
	return $args;
}

function wpforo_delete_attachment( $attach_post_id ) {
	if( ! $attach_post_id ) {
		return;
	}
	$posts = WPF()->db->get_results(
		"SELECT `postid`, `body` FROM `" . WPF()->tables->posts . "` WHERE `body` LIKE '%wpfa-" . intval(
			$attach_post_id
		) . "%'",
		ARRAY_A
	);
	if( ! empty( $posts ) || is_array( $posts ) ) {
		foreach( $posts as $post ) {
			$body = preg_replace(
				'|<div[^><]*id=[\'\"]+wpfa-' . $attach_post_id . '[\'\"]+[^><]*>.+?</div>|is',
				'<div class="wpforo-attached-file wpfa-deleted">' . wpforo_phrase(
					'Attachment removed',
					false
				) . '</div>',
				(string) $post['body']
			);
			if( $body ) {
				WPF()->db->query(
					"UPDATE `" . WPF()->tables->posts . "` SET `body` = '" . esc_sql(
						$body
					) . "' WHERE `postid` = " . intval( $post['postid'] )
				);
			}
		}
	}
}

function wpforo_default_attachments_filter( $text ) {
	if( preg_match_all(
		'#<a[^<>]*class=[\'"]wpforo-default-attachment[\'"][^<>]*href=[\'"]([^\'"]+)[\'"][^<>]*>[\r\n\t\s\0]*(?:<i[^<>]*>[\r\n\t\s\0]*</i>[\r\n\t\s\0]*)?([^<>]*)</a>#iu',
		(string) $text,
		$matches,
		PREG_SET_ORDER
	) ) {
		foreach( $matches as $match ) {
			$attach_html = '';
			if( file_exists( wpforo_fix_upload_dir( $match[1] ) ) ) {
				if( ! WPF()->perm->forum_can( 'va' ) || ! WPF()->usergroup->can( 'caa' ) ) {
					$attach_html .= '<br/><div class="wpfa-item wpfa-file"><a class="attach_cant_view" style="cursor: pointer;"><span style="color: #666;">' . wpforo_phrase(
							'Attachment',
							false
						) . ' : </span> ' . urldecode( basename( $match[2] ) ) . '</a></div>';
				}
			}
			if( $attach_html ) {
				$attach_html .= '<br/>';
				$text        = str_replace( $match[0], $attach_html, $text );
			}
		}
	}
	
	return $text;
}

function wpforo_content_enable_do_shortcode() {
	if( wpforo_setting( 'posting', 'content_do_shortcode' ) ) {
		add_filter( 'wpforo_content_after', 'do_shortcode', 20 );
	}
}

add_action( 'wpforo_after_init', 'wpforo_content_enable_do_shortcode' );

add_filter( 'wpforo_content_after', function( $text ) {
	return wpforo_strip_shortcodes( $text, true );
},          999 );

add_filter( 'wpforo_body_text_filter', function( $text ) {
	if( apply_filters( 'wpforo_allow_replace_3asterisk', true ) ) {
		$text = preg_replace(
			'#\*{3}([^*]+?)\*{3}#',
			'<span style="color: red; font-weight: bold;">$1</span>',
			(string) $text
		);
	}
	
	return $text;
} );

add_action( 'wp_footer', function() { WPF()->cache->create(); } );

add_filter( 'retrieve_password_message', function( $message, $key, $user_login, $user_data ) {
	$reset_password_url = '';
	if( preg_match( wpforo_get_wprp_url_pattern(), (string) $message, $match ) ) {
		if( wpforo_setting( 'authorization', 'use_our_lostpassword_url' ) ) {
			$reset_password_url = wpforo_resetpassword_url( $key, $user_login );
			$message            = str_replace( $match[0], $reset_password_url, $message );
		} else {
			$reset_password_url = $match[0];
		}
	}
	
	if( wpforo_setting( 'email', 'overwrite_reset_password_email' ) && $reset_password_url ) {
		$message = str_replace(
			[ '[user_login]', '[reset_password_url]' ],
			[ $user_login, $reset_password_url, ],
			wpforo_setting( 'email', 'reset_password_email_message' )
		);
		$message = _wpforo_apply_email_shortcodes( $message, [ 'user' => [ 'userid' => $user_data->ID ] ] );
		add_filter( 'wp_mail_content_type', '__wpforo_set_html_content_type', 999 );
	}
	
	return $message;
},          999, 4 );

function wpforo_user_field_shortcode_to_value( $shortcode, $userid = null, $implode = true, $separator = ',' ) {
	$value = null;
	
	if( $shortcode && ( $field = preg_replace(
			'#^\s*\[?\s*(?:user_|owner_)?(?:fields_)?([^\[\]]+?)\s*]?\s*$#iu',
			'$1',
			(string) $shortcode
		) ) ) {
		if( ! $userid ) $userid = WPF()->current_userid;
		if( in_array(
			$field,
			[ 'login', 'pass', 'nicename', 'email', 'url', 'registered', 'activation_key', 'status' ],
			true
		) ) {
			$field = 'user_' . $field;
		}
		$value = wpforo_member( $userid, $field );
		if( $implode && wpforo_is_array_of_scalars( $value ) ) $value = implode( $separator, $value );
	}
	
	return $value;
}

function wpforo_forum_field_shortcode_to_value( $shortcode, $forumid, $implode = true, $separator = ',' ) {
	$value = null;
	
	if( $forumid ) {
		if( $shortcode && ( $field = preg_replace(
				'#^\s*\[?\s*(?:forum_)?(?:fields_)?([^\[\]]+?)\s*]?\s*$#iu',
				'$1',
				(string) $shortcode
			) ) ) {
			if( $field === 'link' ) {
				$value = sprintf(
					'<a target="_blank" href="%1$s">%2$s</a>',
					esc_url( (string) _wpforo_forum( $forumid, 'url' ) ),
					sanitize_text_field( _wpforo_forum( $forumid, 'title' ) )
				);
				$value = stripslashes( (string) $value );
			} else {
				$value = _wpforo_forum( $forumid, $field );
				if( $implode && wpforo_is_array_of_scalars( $value ) ) $value = implode( $separator, $value );
			}
		}
	}
	
	return $value;
}

function wpforo_topic_field_shortcode_to_value( $shortcode, $topicid, $implode = true, $separator = ',' ) {
	$value = null;
	
	if( $topicid ) {
		if( $shortcode && ( $field = preg_replace(
				'#^\s*\[?\s*(?:topic_)?(?:fields_)?([^\[\]]+?)\s*]?\s*$#iu',
				'$1',
				(string) $shortcode
			) ) ) {
			if( $field === 'link' ) {
				$value = sprintf(
					'<a target="_blank" href="%1$s">%2$s</a>',
					esc_url( (string) _wpforo_topic( $topicid, 'short_url' ) ),
					sanitize_text_field( _wpforo_topic( $topicid, 'title' ) )
				);
				$value = stripslashes( $value );
			} else {
				$value = _wpforo_topic( $topicid, $field );
				if( $implode && wpforo_is_array_of_scalars( $value ) ) $value = implode( $separator, $value );
			}
		}
	}
	
	return $value;
}

function wpforo_post_field_shortcode_to_value( $shortcode, $postid, $implode = true, $separator = ',' ) {
	$value = null;
	
	if( $postid ) {
		if( $shortcode && ( $field = preg_replace(
				'#^\s*\[?\s*(?:post_)?(?:fields_)?([^\[\]]+?)\s*]?\s*$#iu',
				'$1',
				(string) $shortcode
			) ) ) {
			if( $field === 'link' ) {
				$value = sprintf(
					'<a target="_blank" href="%1$s">%2$s</a>',
					esc_url( (string) _wpforo_post( $postid, 'short_url' ) ),
					sanitize_text_field( _wpforo_post( $postid, 'title' ) )
				);
				$value = stripslashes( $value );
			} else {
				$value = _wpforo_post( $postid, $field );
				if( $implode && wpforo_is_array_of_scalars( $value ) ) $value = implode( $separator, $value );
			}
		}
	}
	
	return $value;
}

function wpforo_new_user_notification_email_admin( $wp_new_user_notification_email_admin, $user, $blogname ) {
	if( wpforo_setting( 'email', 'overwrite_new_user_notification_admin' ) ) {
		$wp_new_user_notification_email_admin['subject'] = str_replace(
			'[blogname]',
			'[' . $blogname . ']',
			wpforo_setting(
				'email',
				'wp_new_user_notification_email_admin_subject'
			)
		);
		$wp_new_user_notification_email_admin['message'] = str_replace(
			'[blogname]',
			$blogname,
			wpforo_setting(
				'email',
				'wp_new_user_notification_email_admin_message'
			)
		);
		add_filter( 'wp_mail_content_type', '__wpforo_set_html_content_type', 999 );
	}
	$userid                                          = $user->ID;
	$wp_new_user_notification_email_admin['message'] = preg_replace_callback(
		'#\[[^\[\]]+?]#isu',
		function( $match ) use ( $userid ) {
			$value = wpforo_user_field_shortcode_to_value( $match[0], $userid );
			if( ! $value || ! ( is_string( $value ) || is_numeric( $value ) ) ) $value = '';
			
			return $value;
		},
		(string) $wp_new_user_notification_email_admin['message']
	);
	
	return $wp_new_user_notification_email_admin;
}

add_filter( 'wp_new_user_notification_email_admin', 'wpforo_new_user_notification_email_admin', 999, 3 );

function wpforo_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
	$set_password_url = '';
	if( preg_match( wpforo_get_wprp_url_pattern(), (string) $wp_new_user_notification_email['message'], $match ) ) {
		if( wpforo_setting( 'authorization', 'use_our_lostpassword_url' ) ) {
			$set_password_url                          = wpforo_resetpassword_url( $match['key'], $user->user_login );
			$wp_new_user_notification_email['message'] = str_replace(
				$match[0],
				$set_password_url,
				$wp_new_user_notification_email['message']
			);
		} else {
			$set_password_url = $match[0];
		}
	}
	
	if( wpforo_setting( 'email', 'overwrite_new_user_notification' ) && $set_password_url ) {
		$wp_new_user_notification_email['subject'] = str_replace(
			'[blogname]',
			'[' . $blogname . ']',
			wpforo_setting(
				'email',
				'wp_new_user_notification_email_subject'
			)
		);
		$wp_new_user_notification_email['message'] = str_replace(
			[ '[user_login]', '[set_password_url]' ],
			[ $user->user_login, $set_password_url ],
			wpforo_setting( 'email', 'wp_new_user_notification_email_message' )
		);
		add_filter( 'wp_mail_content_type', '__wpforo_set_html_content_type', 999 );
	}
	
	return $wp_new_user_notification_email;
}

add_filter( 'wp_new_user_notification_email', 'wpforo_new_user_notification_email', 999, 3 );

function wpforo_get_wprp_url_pattern() {
	return '#(?:<\s*)?https?://\S+?wp-login\.php\?action=rp&key=(?<key>[^?&=\s]+)\S+(?:\s*>)?#isu';
}

function wpforo_synch_user_roles( $userid ) {
	
	$user = get_userdata( $userid );
	
	if( ! empty( $user ) && ! empty( $user->roles ) ) {
		$role_usergroups = WPF()->usergroup->get_role_usergroup_relation();
		
		if( ! empty( $role_usergroups ) ) {
			$append_second_groups = apply_filters( 'wpforo_synch_user_roles_append_secondary_groups', false );
			$ug_users_array       = WPF()->usergroup->build_users_groupid_array( $role_usergroups, [ $user ] );
			
			if( wpfval( $ug_users_array, 'user_prime_group' ) ) {
				$userid_groupids = current( $ug_users_array['user_prime_group'] );
				if( ! empty( $userid_groupids ) ) {
					$groupid = current( $userid_groupids );
					if( $groupid ) WPF()->member->set_groupid( $userid, $groupid );
				}
			}
			
			if( wpfval( $ug_users_array, 'user_second_groups' ) ) {
				foreach( $ug_users_array['user_second_groups'] as $userid => $secondary_usergroups ) {
					if( ! empty( $secondary_usergroups ) ) {
						if( $append_second_groups ) {
							// Merges current secondary groups with new secondary groups
							WPF()->member->append_secondary_groupids( $userid, $secondary_usergroups );
						} else {
							// Removes current secondary groups and sets new secondary groups
							WPF()->member->set_secondary_groupids( $userid, $secondary_usergroups );
						}
					}
				}
			} elseif( ! $append_second_groups && ! wpfval( $ug_users_array, 'user_second_groups' ) ) {
				WPF()->member->set_secondary_groupids( $userid, [] );
			}
			
			delete_user_meta( intval( $userid ), '_wpf_member_obj' );
		}
	}
}


function wpforo_synch_roles() {
	$status = [
		'progress' => 0,
		'error'    => 0,
		'start'    => 0,
		'step'     => 1,
		'left'     => 0,
		'total'    => 0,
		'id'       => 0,
	];
	
	$wpforo_synch_roles_data = isset( $_POST['wpforo_synch_roles_data'] ) ? $_POST['wpforo_synch_roles_data'] : '';
	
	if( $wpforo_synch_roles_data ) {
		parse_str( $wpforo_synch_roles_data, $data );
		check_ajax_referer( 'wpforo_synch_roles', 'checkthis' );
		$limit                = apply_filters( 'wpforo_synch_roles_step_limit', 20 );
		$append_second_groups = apply_filters( 'wpforo_synch_roles_append_secondary_groups', true );
		$success              = false;
		$group_users          = [];
		$user_prime_group     = [];
		$user_second_groups   = [];
		$options              = wpforo_get_option( 'synch_roles' );
		$step                 = isset( $data['wpf-step'] ) ? intval( $data['wpf-step'] ) : 1;
		$left                 = isset( $data['wpf-left-users'] ) ? intval( $data['wpf-left-users'] ) : 0;
		$id                   = isset( $data['wpf-start-id'] ) ? intval( $data['wpf-start-id'] ) : 0;
		$start                = isset( $data['wpf-start'] ) ? intval( $data['wpf-start'] ) : 0;
		$ug_role              = isset( $data['wpf_synch_roles'] ) ? $data['wpf_synch_roles'] : [];
		if( ! empty( $ug_role ) ) {
			/////////////////////////////////////////////////////
			/// Update Roles of Usergroups in usergroups table
			WPF()->usergroup->set_ug_roles( $ug_role );
			/////////////////////////////////////////////////////
			if( ! is_array( $options ) || $left > 0 ) {
				$args  = [ 'orderby' => 'ID', 'order' => 'ASC', 'offset' => $start, 'number' => $limit ];
				$users = get_users( $args );
				////////////////////////////////////////////////////////////////////////////////////
				/// Builds associative array of Usergroup ID => Users ID array()
				$ug_users_array = WPF()->usergroup->build_users_groupid_array( $ug_role, $users, true );
				////////////////////////////////////////////////////////////////////////////////////
				if( wpfval( $ug_users_array, 'group_users' ) ) {
					$group_users = $ug_users_array['group_users'];
				}
				if( wpfval( $ug_users_array, 'user_prime_group' ) ) {
					$user_prime_group = $ug_users_array['user_prime_group'];
				}
				if( wpfval( $ug_users_array, 'user_second_groups' ) ) {
					foreach( $ug_users_array['user_second_groups'] as $userid => $secondary_usergroups ) {
						if( ! empty( $secondary_usergroups ) ) {
							if( $append_second_groups ) {
								// Merges current secondary groups with new secondary groups
								WPF()->member->append_secondary_groupids( $userid, $secondary_usergroups );
							} else {
								// Removes current secondary groups and sets new secondary groups
								WPF()->member->set_secondary_groupids( $userid, $secondary_usergroups );
							}
						}
					}
				}
				if( ! empty( $group_users ) ) {
					/////////////////////////////////////////////////////////////////
					/// Updates users Usergroup Ids in profiles table
					$return = WPF()->usergroup->set_users_groupid( $group_users );
					/////////////////////////////////////////////////////////////////
					$success         = ( wpfval( $return, 'success' ) ) ? $return['success'] : $success;
					$status['error'] = ( wpfval( $return, 'error' ) ) ? $return['error'] : $status['error'];
					if( $success ) {
						end( $users );
						$key                = key( $users );
						$status['id']       = $users[ $key ]->ID;
						$result             = count_users();
						$status['total']    = ( wpfval( $result, 'total_users' ) ) ? intval(
							$result['total_users']
						) : 0;
						$status['start']    = $step * $limit;
						$status['left']     = ( $status['total'] > $status['start'] ) ? ( $status['total'] - $status['start'] ) : 0;
						$status['step']     = $step + 1;
						$progress           = ( $status['total'] > $status['start'] ) ? ( $status['start'] * 100 ) / $status['total'] : 100;
						$status['progress'] = round( $progress );
						if( $progress == 100 ) {
							wpforo_delete_option( 'synch_roles' );
						} else {
							wpforo_update_option( 'synch_roles', $status );
						}
					}
				} else {
					$status['total']    = 0;
					$status['start']    = 0;
					$status['left']     = 0;
					$status['step']     = 1;
					$status['progress'] = 100;
					wpforo_delete_option( 'synch_roles' );
				}
			} else {
				$result             = count_users();
				$status['total']    = ( wpfval( $result, 'total_users' ) ) ? intval( $result['total_users'] ) : 0;
				$status['start']    = $step * $limit;
				$status['left']     = 0;
				$status['step']     = 1;
				$status['progress'] = 100;
				wpforo_delete_option( 'synch_roles' );
			}
		}
	}
	
	if( intval( wpfval( $status, 'progress' ) ) === 100 ) {
		WPF()->notice->add( 'Role-Usergroup synchronization is complete!', 'success' );
	}
	
	wp_die( json_encode( $status ) );
}

add_action( 'wp_ajax_wpforo_synch_roles', 'wpforo_synch_roles' );


function wpforo_update_usergroup_on_role_change( $userid, $new_role, $old_roles = [] ) {
	if( wpforo_setting( 'authorization', 'role_synch' ) ) {
		wpforo_synch_user_roles( $userid );
	}
}

add_action( 'add_user_role', 'wpforo_update_usergroup_on_role_change', 99, 2 );
add_action( 'set_user_role', 'wpforo_update_usergroup_on_role_change', 99, 3 );


function wpforo_wp_admin_bar_red_circle_number( $number ) {
	$number = intval( $number );
	if( $number ) {
		return sprintf(
			'<div class="wp-core-ui wp-ui-notification wpforo-menu-notification-counter">%1$d</div>',
			$number
		);
	}
	
	return '';
}

function wpforo_get_memb_attention_count(): int {
	return WPF()->member->get_count( [ 'p.status' => [ 'banned', 'inactive' ] ] );
}

function wpforo_get_admin_bar_numbers(): array {
	$all_count = 0;
	$mod_count = WPF()->post->unapproved_count();
	$all_count += $mod_count;
	
	return compact( 'all_count', 'mod_count' );
}

function wpforo_admin_bar_menu( $wp_admin_bar ) {
	$admin_bar_numbers = wpforo_get_admin_bar_numbers();
	
	if( wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'new-forum',
			'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Forum', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'forums' ) . '&action=add' ),
			'parent' => 'new-content',
		];
		$wp_admin_bar->add_node( $args );
		$args = [
			'id'     => 'new-ugroup',
			'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New User Group', 'wpforo' ),
			'href'   => admin_url(
				'admin.php?page=' . wpforo_prefix_slug( 'usergroups' ) . '&wpfaction=wpforo_usergroup_save_form'
			),
			'parent' => 'new-content',
		];
		$wp_admin_bar->add_node( $args );
		$args = [
			'id'     => 'new-phrase',
			'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Phrase', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'phrases' ) . '&wpfaction=phrase_add_form' ),
			'parent' => 'new-content',
		];
		$wp_admin_bar->add_node( $args );
	}
	
	$args = [
		'id'     => 'wpforo-home',
		'title'  => __( 'Visit Forum', 'wpforo' ),
		'href'   => wpforo_home_url(),
		'parent' => 'wpf-community',
		//		'meta'   => [ 'target' => '_blank' ]
	];
	$wp_admin_bar->add_node( $args );
	
	if( wpforo_current_user_is( 'admin' ) || WPF()->usergroup->can( 'mf' ) || WPF()->usergroup->can( 'ms' ) || WPF()->usergroup->can( 'vm' ) || WPF()->usergroup->can( 'mp' ) || WPF()->usergroup->can(
			'aum'
		) || WPF()->usergroup->can( 'vmg' ) || WPF()->usergroup->can( 'mth' ) ) {
		$args = [
			'id'    => 'wpf-community',
			'title' => __( 'Forum Dashboard', 'wpforo' ) . wpforo_wp_admin_bar_red_circle_number(
					$admin_bar_numbers['all_count']
				),
			'href'  => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'dashboard' ) ),
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'mf' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-forums',
			'title'  => '' . __( 'Forums', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'forums' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
		$args = [
			'id'     => 'wpf-new-forum',
			'title'  => '' . __( 'Add New Forum', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'forums' ) . '&action=add' ),
			'parent' => 'wpf-forums',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'ms' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-settings',
			'title'  => '' . __( 'Settings', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'settings' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'mt' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-tools',
			'title'  => '' . __( 'Tools', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'aum' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-moderation',
			'title'  => '' . __( 'Moderation', 'wpforo' ) . wpforo_wp_admin_bar_red_circle_number(
					$admin_bar_numbers['mod_count']
				),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'moderations' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'ms' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpforo-accesses',
			'title'  => '' . __( 'Accesses', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=wpforo-accesses' ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
		$args = [
			'id'     => 'wpforo-new-accesses',
			'title'  => '' . __( 'Add New Forum Access', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=wpforo-accesses&wpfaction=wpforo_access_save_form' ),
			'parent' => 'wpforo-accesses',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'vm' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-members',
			'title'  => '' . __( 'Members', 'wpforo' ) . wpforo_wp_admin_bar_red_circle_number(
					wpforo_get_memb_attention_count()
				),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'members' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'vmg' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-usergroups',
			'title'  => '' . __( 'Usergroups', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'usergroups' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
		$args = [
			'id'     => 'wpf-new-ugroup',
			'title'  => '' . __( 'Add New Usergroup', 'wpforo' ),
			'href'   => admin_url(
				'admin.php?page=' . wpforo_prefix_slug( 'usergroups' ) . '&wpfaction=wpforo_usergroup_save_form'
			),
			'parent' => 'wpf-usergroups',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'mp' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-phrases',
			'title'  => '' . __( 'Phrases', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'phrases' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
		$args = [
			'id'     => 'wpf-new-phrase',
			'title'  => '' . __( 'Add New Phrase', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'phrases' ) . '&wpfaction=phrase_add_form' ),
			'parent' => 'wpf-phrases',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( WPF()->usergroup->can( 'mth' ) || wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-themes',
			'title'  => '' . __( 'Themes', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'themes' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
	}
	if( wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpf-addons',
			'title'  => '' . __( 'Addons', 'wpforo' ),
			'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'addons' ) ),
			'parent' => 'wpf-community',
		];
		$wp_admin_bar->add_node( $args );
	}
}

function wpforo_multiboard_admin_bar_menu( $wp_admin_bar ) {
	$bds_bar_numbers = [];
	if( wpforo_current_user_is( 'admin' ) ) {
		$args = [
			'id'     => 'wpforo-new-content',
			'title'  => 'wpForo',
			'href'   => admin_url( 'admin.php?page=wpforo-overview' ),
			'parent' => 'new-content',
		];
		$wp_admin_bar->add_node( $args );
		
		if( $boardids = WPF()->board->get_active_boardids() ) {
			foreach( $boardids as $boardid ) {
				WPF()->change_board( $boardid );
				$current                     = WPF()->board->get_current();
				$bds_bar_numbers[ $boardid ] = wpforo_get_admin_bar_numbers();
				
				$menuid = 'wpforo-new-content-' . $current['slug'];
				$args   = [
					'id'     => $menuid,
					'title'  => $current['title'],
					'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'dashboard' ) ),
					'parent' => 'wpforo-new-content',
				];
				$wp_admin_bar->add_node( $args );
				## ----------------------------------------------------------------------------------  ####
				$args = [
					'id'     => $menuid . 'new-forum',
					'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Forum', 'wpforo' ),
					'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'forums' ) . '&action=add' ),
					'parent' => $menuid,
				];
				$wp_admin_bar->add_node( $args );
				$args = [
					'id'     => $menuid . 'new-ugroup',
					'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New User Group', 'wpforo' ),
					'href'   => admin_url(
						'admin.php?page=' . wpforo_prefix_slug( 'usergroups' ) . '&wpfaction=wpforo_usergroup_save_form'
					),
					'parent' => $menuid,
				];
				$wp_admin_bar->add_node( $args );
				$args = [
					'id'     => $menuid . 'new-phrase',
					'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Phrase', 'wpforo' ),
					'href'   => admin_url(
						'admin.php?page=' . wpforo_prefix_slug( 'phrases' ) . '&wpfaction=phrase_add_form'
					),
					'parent' => $menuid,
				];
				$wp_admin_bar->add_node( $args );
				
			}
		}
	}
	
	if( wpforo_current_user_is( 'admin' ) || WPF()->usergroup->can( 'mf' ) || WPF()->usergroup->can( 'ms' ) || WPF()->usergroup->can( 'vm' ) || WPF()->usergroup->can( 'mp' ) || WPF()->usergroup->can(
			'aum'
		) || WPF()->usergroup->can( 'vmg' ) || WPF()->usergroup->can( 'mth' ) ) {
		$bds_bar_all = 0;
		foreach( $bds_bar_numbers as $x ) $bds_bar_all += $x['all_count'];
		$args = [
			'id'    => 'wpforo',
			'title' => 'wpForo' . wpforo_wp_admin_bar_red_circle_number( $bds_bar_all ),
			'href'  => admin_url( 'admin.php?page=wpforo-overview' ),
		];
		$wp_admin_bar->add_node( $args );
		
		
		if( $boardids = WPF()->board->get_active_boardids() ) {
			foreach( $boardids as $boardid ) {
				WPF()->change_board( $boardid );
				$current = WPF()->board->get_current();
				
				$menuid = 'wpforo-' . $current['slug'];
				$args   = [
					'id'     => $menuid,
					'title'  => '&#128489;&nbsp;&nbsp;' . $current['title'] . ' (' . strtok(
							$current['locale'],
							'_'
						) . ')' . wpforo_wp_admin_bar_red_circle_number( $bds_bar_numbers[ $boardid ]['all_count'] ),
					'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'dashboard' ) ),
					'parent' => 'wpforo',
				];
				$wp_admin_bar->add_node( $args );
				
				## ----------------------------------------------------------------------------------  ####
				
				$args = [
					'id'     => $menuid . '-home',
					'title'  => '&#128279;&nbsp;' . __( 'Visit Forum', 'wpforo' ),
					'href'   => wpforo_home_url(),
					'parent' => $menuid,
					//                    'meta'   => [ 'target' => '_blank' ]
				];
				$wp_admin_bar->add_node( $args );
				
				if( WPF()->usergroup->can( 'mf' ) || wpforo_current_user_is( 'admin' ) ) {
					$args = [
						'id'     => $menuid . '-forums',
						'title'  => '&#9776;&nbsp;&nbsp;' . __( 'Forums', 'wpforo' ),
						'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'forums' ) ),
						'parent' => $menuid,
					];
					$wp_admin_bar->add_node( $args );
					$args = [
						'id'     => $menuid . '-new-forum',
						'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Forum', 'wpforo' ),
						'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'forums' ) . '&action=add' ),
						'parent' => $menuid . '-forums',
					];
					$wp_admin_bar->add_node( $args );
				}
				if( WPF()->usergroup->can( 'ms' ) || wpforo_current_user_is( 'admin' ) ) {
					$args = [
						'id'     => $menuid . '-settings',
						'title'  => '&#9881;&nbsp;&nbsp;' . __( 'Settings', 'wpforo' ),
						'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'settings' ) ),
						'parent' => $menuid,
					];
					$wp_admin_bar->add_node( $args );
				}
				if( WPF()->usergroup->can( 'mt' ) || wpforo_current_user_is( 'admin' ) ) {
					$args = [
						'id'     => $menuid . '-tools',
						'title'  => '&#128295;&nbsp;&nbsp;' . __( 'Tools', 'wpforo' ),
						'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) ),
						'parent' => $menuid,
					];
					$wp_admin_bar->add_node( $args );
				}
				if( WPF()->usergroup->can( 'aum' ) || wpforo_current_user_is( 'admin' ) ) {
					$args = [
						'id'     => $menuid . '-moderation',
						'title'  => '&#128479;&nbsp;&nbsp;' . __(
								'Moderation',
								'wpforo'
							) . wpforo_wp_admin_bar_red_circle_number( $bds_bar_numbers[ $boardid ]['mod_count'] ),
						'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'moderations' ) ),
						'parent' => $menuid,
					];
					$wp_admin_bar->add_node( $args );
				}
				if( WPF()->usergroup->can( 'mp' ) || wpforo_current_user_is( 'admin' ) ) {
					$args = [
						'id'     => $menuid . '-phrases',
						'title'  => '&#9873;&nbsp;&nbsp;' . __( 'Phrases', 'wpforo' ),
						'href'   => admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'phrases' ) ),
						'parent' => $menuid,
					];
					$wp_admin_bar->add_node( $args );
					$args = [
						'id'     => $menuid . '-new-phrase',
						'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Phrase', 'wpforo' ),
						'href'   => admin_url(
							'admin.php?page=' . wpforo_prefix_slug( 'phrases' ) . '&wpfaction=phrase_add_form'
						),
						'parent' => $menuid . '-phrases',
					];
					$wp_admin_bar->add_node( $args );
				}
			}
		}
		
		##### -- ##### ----- $ ----- ###
		
		if( WPF()->usergroup->can( 'ms' ) || wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-boards',
				'title'  => '&#9776;&nbsp;&nbsp;' . __( 'Boards', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-boards' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
			$args = [
				'id'     => 'wpforo-new-board',
				'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Board', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-boards&wpfaction=wpforo_board_save_form' ),
				'parent' => 'wpforo-boards',
			];
			$wp_admin_bar->add_node( $args );
		}
		if( WPF()->usergroup->can( 'ms' ) || wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-accesses',
				'title'  => '&#33;&nbsp;&nbsp;' . __( 'Accesses', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-accesses' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
			$args = [
				'id'     => 'wpforo-new-accesses',
				'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Forum Access', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-accesses&wpfaction=wpforo_access_save_form' ),
				'parent' => 'wpforo-accesses',
			];
			$wp_admin_bar->add_node( $args );
		}
		if( WPF()->usergroup->can( 'vmg' ) || wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-usergroups',
				'title'  => '&#128101;&nbsp;&nbsp;' . __( 'Usergroups', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-usergroups' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
			$args = [
				'id'     => 'wpforo-new-ugroup',
				'title'  => '&#43;&nbsp;&nbsp;' . __( 'Add New Usergroup', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-usergroups&wpfaction=wpforo_usergroup_save_form' ),
				'parent' => 'wpforo-usergroups',
			];
			$wp_admin_bar->add_node( $args );
		}
		if( WPF()->usergroup->can( 'vm' ) || wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-members',
				'title'  => '&#128100;&nbsp;&nbsp;' . __( 'Members', 'wpforo' ) . wpforo_wp_admin_bar_red_circle_number(
						wpforo_get_memb_attention_count()
					),
				'href'   => admin_url( 'admin.php?page=wpforo-members' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
		}
		if( WPF()->usergroup->can( 'ms' ) || wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-base-settings',
				'title'  => '&#9881;&nbsp;&nbsp;' . __( 'Settings', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-base-settings' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
		}
		if( WPF()->usergroup->can( 'mth' ) || wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-themes',
				'title'  => '&#127912;&nbsp;&nbsp;' . __( 'Themes', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-themes' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
		}
		if( wpforo_current_user_is( 'admin' ) ) {
			$args = [
				'id'     => 'wpforo-addons',
				'title'  => '&#128268;&nbsp;&nbsp;' . __( 'Addons', 'wpforo' ),
				'href'   => admin_url( 'admin.php?page=wpforo-addons' ),
				'parent' => 'wpforo',
			];
			$wp_admin_bar->add_node( $args );
		}
		
	}
}

add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
	if( is_wpforo_multiboard() ) {
		wpforo_multiboard_admin_bar_menu( $wp_admin_bar );
		WPF()->change_board();
	} else {
		wpforo_admin_bar_menu( $wp_admin_bar );
	}
	echo '<style>
		#wpadminbar .wpforo-menu-notification-counter,
		#wpadminbar .wpforo-menu-notification-indicator{
			background-color: #d63638;
			color: #ffffff;
		}
		
		#wpadminbar .wpforo-menu-notification-counter{
			display: inline-block;
			min-width: 18px;
			height: 18px;
			border-radius: 50%;
			margin: 5px 5px 0 5px;
			vertical-align: top;
			font-size: 11px;
			line-height: 1.6;
			text-align: center;
		}
	</style>';
},          999 );

function wpforo_tag_search() {
	$s             = wp_unslash( $_GET['q'] );
	$tag_separator = ( strpos( $s, '،' ) !== false ) ? '،' : ',';
	$tag_separator = apply_filters( 'wpforo_tag_separator', $tag_separator );
	if( false !== strpos( (string) $s, $tag_separator ) ) {
		$s = str_replace( $tag_separator, ',', $s );
		$s = explode( ',', $s );
		$s = $s[ count( $s ) - 1 ];
	}
	$s                    = trim( (string) $s );
	$tag_search_min_chars = apply_filters( 'wpforo_tag_search_min_chars', 2 );
	if( wpforo_strlen( $s ) >= $tag_search_min_chars ) {
		$limit   = wpforo_setting( 'tags', 'suggest_limit' );
		$results = WPF()->db->get_col(
			"SELECT `tag` FROM `" . WPF()->tables->tags . "` WHERE `tag` LIKE '" . esc_sql( $s ) . "%' LIMIT " . $limit
		);
		if( ! empty( $results ) ) {
			echo implode( "\n", $results );
		}
	}
	wp_die();
}

add_action( 'wp_ajax_wpforo_tag_search', 'wpforo_tag_search' );
add_action( 'wp_ajax_nopriv_wpforo_tag_search', 'wpforo_tag_search' );

function wpforo_add_to_footer() {
	if( WPF()->current_object['load_tinymce'] ) {
		wp_enqueue_editor();
	}
}

add_action( 'wpforo_bottom_hook', 'wpforo_add_to_footer' );

function wpforo_check_notifications() {
	$data = [ 'alerts' => 0, 'notifications' => '' ];
	if( is_user_logged_in() && wpforo_setting( 'notifications', 'notifications' ) ) {
		$data['alerts'] = count( WPF()->activity->notifications );
		if( wpfval( $_POST, 'getdata' ) ) {
			$data['notifications'] = WPF()->activity->notifications_list( false );
		}
		wp_send_json_success( $data );
	}
	wp_send_json_error( $data );
}

add_action( 'wp_ajax_wpforo_notifications', 'wpforo_check_notifications' );

function wpforo_can_display_recaptcha_note() {
	$d = wpforo_is_admin() ? 'recaptcha_backend_note' : 'recaptcha_note';
	
	return ! WPF()->dissmissed[ $d ] && current_user_can( 'administrator' ) && ! wp_is_mobile() && wpforo_setting(
			'authorization',
			'user_register'
		) && ! wpforo_setting( 'authorization', 'register_url' ) && ! wpforo_is_recaptcha_configured();
}

add_action( 'wpforo_header_hook', 'wpforo_recaptcha_note' );
function wpforo_recaptcha_note() {
	if( wpforo_can_display_recaptcha_note() ) {
		?>
        <div class="wpforo-rcn-wrap">
            <div class="wpforo-rcn-body">
                <span class="wpforo-rcn-head"><i
                            class="fas fa-user-secret"></i> <?php wpforo_phrase(
		                'Protect your forum from spam user registration!'
	                ); ?></span>
				<?php printf(
					wpforo_phrase(
						'wpForo has not found any protection solution against spam user registration on the forum registration form. Please %1$s and enable the %2$s antibot protection in %3$s or install other alternative %4$s to avoid registration of spam users.',
						false,
						'native'
					),
					'<a class="wpf-rcnl" href="' . admin_url(
						'admin.php?page=' . wpforo_prefix_slug(
							'base-settings'
						) . '&wpf_tab=recaptcha#wpf-settings-tab'
					) . '" target="_blank">' . wpforo_phrase( 'configure', false, 'lower' ) . '</a>',
					'<a class="wpf-rcngl" href="https://developers.google.com/recaptcha" target="_blank">Google reCAPTCHA</a>',
					'<a href="' . admin_url(
						'admin.php?page=' . wpforo_prefix_slug(
							'base-settings'
						) . '&wpf_tab=recaptcha#wpf-settings-tab'
					) . '">' . wpforo_phrase( 'Settings &gt; Spam Protection Tab', false ) . '</a>',
					'<a class="wpf-rcnwl" href="https://wordpress.org/plugins/search/spam+users/" target="_blank">' . wpforo_phrase(
						'WordPress plugin',
						false
					) . '</a>'
				)
				?>
            </div>
            <div class="wpforo-rcn-footer">
                <div class="wpforo-rcn-info">
					<?php wpforo_phrase(
						'This notification is only visible for the website administrators. It will be automatically disabled once some antispam solution is enabled. If you don\'t use wpForo registration form or you\'re sure, that you have an antispam solution just click the [dismiss] button.'
					); ?>
                </div>
                <div class="wpforo-rcn-dismiss">
                    <span class="wpforo-rcn-dismiss-button"
                          wpf-tooltip="<?php wpforo_phrase( 'I got it, please dismiss this message' ) ?>"
                          wpf-tooltip-size="long"><?php wpforo_phrase( 'Dismiss' ) ?></span>
                </div>
            </div>
        </div>
		<?php
	}
}

//add_action('admin_notices', 'wpforo_admin_notice_recaptcha');
function wpforo_admin_notice_recaptcha() {
	if( wpforo_can_display_recaptcha_note() ) {
		wp_enqueue_script( 'wpforo-backend-js' );
		$class   = 'notice notice-error is-dismissible';
		$message = __(
			'IMPORTANT! The forum registration form is probably under risk of spam attacks. Please configure wpForo built-in %s antibot for registration form to avoid spam registrations. If you don\'t use the forum registration form or you are sure that your registration forms are secured, just click on (x) button to dismiss this message.',
			'wpforo'
		);
		$message = sprintf(
			$message,
			'<a href="' . admin_url(
				'admin.php?page=' . wpforo_prefix_slug( 'tools' ) . '&tab=antispam#wpf-recaptcha'
			) . '" target="_blank" style="text-decoration:none;">' . __(
				'Google reCAPTCHA',
				'wpforo'
			) . '</a>'
		);
		printf( '<div id="wpforo-admin-notice-recaptcha" class="%1$s"><p>%2$s</p></div>', $class, $message );
	}
}

function wpforo_is_recaptcha_configured() {
	return wpforo_setting( 'recaptcha', 'wpf_reg_form' ) && wpforo_setting( 'recaptcha', 'site_key' ) && wpforo_setting(
			'recaptcha',
			'secret_key'
		);
}


/*add_action( 'plugins_loaded', function() {
	if( ! (int) wpfval( WPF()->dissmissed, 'poll_version_is_old' ) && function_exists( 'WPF_POLL' ) ) {
		if( version_compare( WPFORO_VERSION, '1.7.7', '>' ) && version_compare( WPFOROPOLL_VERSION, '1.0.5', '<=' ) ) {
			remove_action( 'widgets_init', [ WPF_POLL(), 'init_widgets' ] );
			remove_action( 'wpforo_before_init', [ WPF_POLL(), 'init' ] );

			WPF()->dissmissed['poll_version_is_old'] = 0;
			wpforo_update_option( 'dissmissed', WPF()->dissmissed );
		} else {
			WPF()->dissmissed['poll_version_is_old'] = 1;
			wpforo_update_option( 'dissmissed', WPF()->dissmissed );
		}
	}
} );*/

add_action( 'admin_notices', function() {
	if( wpfkey( WPF()->dissmissed, 'poll_version_is_old' ) && ! (int) WPF()->dissmissed['poll_version_is_old'] ) {
		$class   = 'notice notice-error';
		$message = '<div style="font-size: 16px; padding: 10px 0;"><strong>' . __(
				'wpForo Polls addon is disabled!',
				'wpforo'
			) . '</strong><p style="font-size:15px; margin-bottom:0;">' . __(
			           ' Your addon version is not compatible with the current version of wpForo. Please update the addon or downgrade wpForo to 1.7.7',
			           'wpforo'
		           ) . '</p></div>';
		$message .= ' <a href="' . admin_url(
				wp_nonce_url(
					'admin.php?page=' . wpforo_prefix_slug( 'dashboard' ) . '&wpfaction=dissmiss_poll_version_is_old',
					'wpforo-dissmiss-poll-version-is-old'
				)
			) . '">[' . __( 'dismiss', 'wpforo' ) . ']</a>';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}
} );

add_action( 'wpforo_update_option', function() {
	wpforo_clean_folder(
		WPF()->folders['cache']['dir'] . DIRECTORY_SEPARATOR . 'item' . DIRECTORY_SEPARATOR . 'option'
	);
} );

add_action( 'wpforo_after_init', function() {
	add_action( 'wp_ajax_dismiss_wpforo_addon_note', [ WPF()->notice, 'dismissAddonNote' ] );
	add_action( 'admin_notices', [ WPF()->notice, 'addonNote' ] );
} );

add_action( 'wpforo_after_init', function() {
	add_action( 'wp_ajax_dismiss_wpforo_cache_conflict_note', [ WPF()->notice, 'dismissCacheConflict' ] );
} );

add_action( 'admin_enqueue_scripts', 'wpforo_cat_cover_js' );

function wpforo_cat_cover_js() {
	if( wpfval( $_GET, 'page' ) === wpforo_prefix_slug( 'forums' ) && ! did_action( 'wp_enqueue_media' ) ) {
		wp_enqueue_media();
	}
}

function wpforo_esc_html( $var ) {
	if( is_scalar( $var ) ) {
		$var = esc_html( $var );
	} else {
		$var = wpforo_esc_html( $var );
	}
	
	return $var;
}

add_action( 'wpforo_bottom_hook', 'wpforo_debug' );
function wpforo_debug() {
	if( wpforo_setting( 'general', 'debug_mode' ) ) : ?>
        <div id="wpforo-debug" style="display:none">
            <h4>Super Globals</h4>
            <textarea style="width:100%; height:300px;">
	            Requests: <?php //print_r(  array_map( 'wpforo_esc_html', $_REQUEST ) ); ?>
	            Server: <?php //print_r( $_SERVER ); ?>
	        </textarea>
            <h4>Options and Features</h4>
            <textarea style="width:100%; height:300px;">
	            <?php echo @ 'route: ' . WPF()->board->route . "\r\n";
	            echo @ 'use_home_url: ' . WPF()->board->get_current( 'is_standalone' ) . "\r\n";
	            echo @ 'url: ' . wpforo_home_url() . "\r\n";
	            echo @ 'pageid:' . WPF()->board->get_current( 'pageid' ) . "\r\n";
	            echo @ 'default_groupid: ' . WPF()->usergroup->default_groupid . "\r\n";
	            @print_r( WPF()->tpl->theme ) . "\r\n";
	            ?>
	        </textarea>
        </div>
	<?php
	endif;
}

add_filter( 'wpforo_topic_list_args', function( $args ) {
	if( wpforo_setting( 'topics', 'include_subforums_topics' ) ) {
		$forumid  = (int) $args['forumid'];
		$forumids = WPF()->forum->get_forumid_and_childids( $forumid );
		unset( $args['forumid'] );
		$args['forumids'] = $forumids;
	}
	
	return $args;
} );

function wpf_body_wp_encode_emoji( $post ) {
	if( wpfkey( $post, 'title' ) ) $post['title'] = wp_encode_emoji( $post['title'] );
	if( wpfkey( $post, 'body' ) ) $post['body'] = wp_encode_emoji( $post['body'] );
	
	return $post;
}

add_filter( 'wpforo_add_post_data_filter', 'wpf_body_wp_encode_emoji' );
add_filter( 'wpforo_edit_post_data_filter', 'wpf_body_wp_encode_emoji' );
add_filter( 'wpforo_add_topic_data_filter', 'wpf_body_wp_encode_emoji' );
add_filter( 'wpforo_edit_topic_data_filter', 'wpf_body_wp_encode_emoji' );
