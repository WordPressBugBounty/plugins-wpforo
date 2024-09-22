<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
if( ! current_user_can( 'administrator' ) ) exit;
$view = ( wpfval( $_GET, 'view' ) ) ? sanitize_text_field( $_GET['view'] ) : '';
?>

    <form method="POST" class="validate">
        <div class="wpf-tool-box" style="margin-top: 15px;">
            <h3>
				<?php _e( 'Debug Information', 'wpforo' ); ?> &nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;

				<?php if( $view == 'user' || $view == '' ): ?>
                    [ <?php _e( 'User Data', 'wpforo' ); ?> ]
				<?php else: ?>
                    [ <a href="<?php echo admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) . '&tab=debug&view=user' ) ?>" style="text-decoration: none; outline: none;box-shadow:none;"><?php _e( 'User Data', 'wpforo' ); ?></a> ]
				<?php endif; ?>

				<?php if( $view == 'server' ): ?>
                    [ <?php _e( 'Server', 'wpforo' ); ?> ]
				<?php else: ?>
                    [ <a href="<?php echo admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) . '&tab=debug&view=server' ) ?>" style="text-decoration: none; outline: none;box-shadow:none;"><?php _e( 'Server', 'wpforo' ); ?></a> ]
				<?php endif; ?>

				<?php if( $view == 'issues' ): ?>
                    [ <?php _e( 'Errors & Issues', 'wpforo' ); ?> ]
				<?php else: ?>
                    [ <a href="<?php echo admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'tools' ) . '&tab=debug&view=issues' ) ?>" style="text-decoration: none; outline: none;box-shadow:none;"><?php _e( 'Errors & Issues', 'wpforo' ); ?></a> ]
				<?php endif; ?>
            </h3>
            <div style="margin-top:10px; clear:both;">

                <table style="width:100%;">
                    <tbody style="padding:10px;">
					<?php if( $view == 'user' || $view == '' ): ?>
                        <tr>
                            <td>
                                <h4>
									<?php _e( 'User Data', 'wpforo' ) ?> &nbsp;&nbsp; | &nbsp;&nbsp;
									<?php
									$wpfu     = ( wpfval( $_POST, 'wpfu' ) ) ? intval( $_POST['wpfu'] ) : WPF()->current_userid;
									$user     = WPF()->member->get_member( $wpfu );
									$usermeta = get_user_meta( $wpfu );
									?>
                                    <form method="POST" style="display: inline-block">
										<?php _e( 'User ID', 'wpforo' ) ?>: &nbsp; <input style="display: inline-block; font-size: 12px; padding: 3px 10px; width: 80px; vertical-align: middle;" type="text" name="wpfu" placeholder="<?php _e( 'User ID', 'wpforo' ) ?>" value="<?php echo intval( $wpfu ) ?>">
                                        <input style="display: inline-block; font-size: 12px; line-height: 20px; height: auto; vertical-align:middle;" type="submit" class="button" value="<?php _e( 'Display User Data', 'wpforo' ); ?>"/>
                                    </form>
                                </h4>
                            </td>
                        </tr>
						<?php if( ! empty( $user ) ): ?>
                            <tr>
                                <td><?php echo wpforo_display_array_data( $user ); ?></td>
                            </tr>
                            <tr>
                                <td><h4><?php _e( 'User Meta Data', 'wpforo' ) ?></h4></td>
                            </tr>
                            <tr>
                                <td>
									<?php
									$keys = [ 'nickname', 'rich_editing', 'show_admin_bar_front', 'locale', WPF()->blog_prefix . 'capabilities', WPF()->blog_prefix . 'user_level', 'last_activity', wpforo_prefix( 'read_topics' ), 'session_tokens' ];
									echo wpforo_display_array_data( $usermeta, $keys );
									?>
                                </td>
                            </tr>
							<?php if( $wpfu == WPF()->current_userid ): ?>
                                <tr>
                                    <td><h4><?php _e( 'User Cookies', 'wpforo' ) ?></h4></td>
                                </tr>
                                <tr>
                                    <td>
										<?php
										$keys = [ wpforo_prefix( 'all_read' ), wpforo_prefix( 'read_topics' ), wpforo_prefix( 'read_forums' ) ];
										echo wpforo_display_array_data( $_COOKIE, $keys );
										?>
                                    </td>
                                </tr>
							<?php endif; ?>
						<?php else: ?>
                            <tr>
                                <td><p style="padding: 0px 20px;"><?php _e( 'No user found.', 'wpforo' ) ?></p></td>
                            </tr>
						<?php endif; ?>
					<?php endif; ?>
					<?php if( $view == 'server' ): ?>
                        <tr>
                            <td><h4><?php _e( 'Server Information', 'wpforo' ) ?></h4></td>
                        </tr>
                        <tr>
                            <td>
                                <table class="wpf-table-data" style="margin: 10px; width: 98%;">
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">USER AGENT</td>
                                        <td class="wpf-dw-td-value"><?php echo esc_html( $_SERVER['HTTP_USER_AGENT'] ) ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">Web Server</td>
                                        <td class="wpf-dw-td-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">PHP Version</td>
                                        <td class="wpf-dw-td-value"><?php echo phpversion(); ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">MySQL Version</td>
                                        <td class="wpf-dw-td-value"><?php echo WPF()->db->get_var( "SELECT VERSION()" ); ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">PHP Max Post Size</td>
                                        <td class="wpf-dw-td-value"><?php echo ini_get( 'post_max_size' ); ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">PHP Max Upload Size</td>
                                        <td class="wpf-dw-td-value"><?php echo ini_get( 'upload_max_filesize' ); ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">PHP Memory Limit</td>
                                        <td class="wpf-dw-td-value"><?php echo ini_get( 'memory_limit' ); ?></td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">PHP DateTime Class</td>
                                        <td class="wpf-dw-td-value" style="line-height: 18px!important;">
											<?php echo ( class_exists( 'DateTime' ) && class_exists( 'DateTimeZone' ) && method_exists( 'DateTime', 'setTimestamp' ) ) ? '<span class="wpf-green">' . __( 'Available', 'wpforo' ) . '</span>' : '<span class="wpf-red">' . __(
													'The setTimestamp() method of PHP DateTime class is not available. Please make sure you use PHP 5.4 and higher version on your hosting service.',
													'wpforo'
												) . '</span> | <a href="http://php.net/manual/en/datetime.settimestamp.php" target="_blank">more info&raquo;</a>'; ?> </td>
                                    </tr>
                                    <tr class="wpf-dw-tr">
                                        <td class="wpf-dw-td">PHP cURL Module</td>
                                        <td class="wpf-dw-td-value" style="line-height: 18px!important;">
											<?php echo ( function_exists( 'curl_version' ) || extension_loaded( 'curl' ) || in_array( 'curl', get_loaded_extensions() ) ) ? '<span class="wpf-green">' . __( 'Available', 'wpforo' ) . '</span>' : '<span class="wpf-red">' . __( 'Not available', 'wpforo' ) . '</span> <span style="font-size: smaller; color: gray;">( ' . __(
													'Please contact to your hosting service support team and ask them enable PHP cURL module'
												) . ' )</span> | <a href="https://deliciousbrains.com/php-curl-how-wordpress-makes-http-requests/" target="_blank">more info&raquo;</a>'; ?> </td>
                                    </tr>
									<?php if( function_exists( 'apache_get_modules' ) ): ?>
                                        <tr class="wpf-dw-tr">
                                            <td class="wpf-dw-td">Permalinks / mod_rewrite</td>
                                            <td class="wpf-dw-td-value" style="line-height: 18px!important;">
												<?php echo ( in_array( 'mod_rewrite', apache_get_modules() ) ) ? '<span class="wpf-green">' . __( 'Available', 'wpforo' ) . '</span>' : '<span class="wpf-red">' . __( 'Not enabled', 'wpforo' ) . '</span> <span style="font-size: smaller; color: gray;">( ' . __(
														'Please enable mod_rewrite on your server, this is required for wpForo forum'
													) . ' )</span> | <a href="https://codex.wordpress.org/Using_Permalinks#mod_rewrite:_.22Pretty_Permalinks.22" target="_blank">more info&raquo;</a>'; ?>
                                            </td>
                                        </tr>
									<?php endif; ?>
									<?php do_action( 'wpforo_dashboard_widget_server' ) ?>
                                </table>
                            </td>
                        </tr>
					<?php endif; ?>
					<?php if( $view == 'issues' ): ?>
                        <tr>
                            <td><h4><?php _e( 'Issues and Recommendations', 'wpforo' ) ?></h4></td>
                        </tr>
                        <tr>
                            <td>
								<?php wpforo_issues() ?>
                            </td>
                        </tr>
                        <tr>
                            <td><h4><?php _e( 'Error Logs', 'wpforo' ) ?></h4></td>
                        </tr>
                        <tr>
                            <td>
								<?php
								$error_log_file = rtrim( ABSPATH, '/' ) . '/' . 'error_log';
								if( file_exists( $error_log_file ) ) {
									wpforo_read_file_revers( $error_log_file );
								}
								?>
								<?php
								$error_log_file = rtrim( ABSPATH, '/' ) . '/wp-content/' . 'debug.log';
								if( file_exists( $error_log_file ) ) {
									wpforo_read_file_revers( $error_log_file );
								}
								?>
								<?php
								$error_log_file = rtrim( ABSPATH, '/' ) . '/wp-admin/' . 'error_log';
								if( file_exists( $error_log_file ) ) {
									wpforo_read_file_revers( $error_log_file );
								}
								?>
                                &nbsp;
                            </td>
                        </tr>
					<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
<?php
function wpforo_display_array_data( $array, $keys = [] ) {
	$html = '';
	foreach( $array as $k => $v ) {
		if( ( ! empty( $keys ) && ! in_array( $k, $keys ) && strpos( (string) $k, 'capabilities' ) === false ) || $k == 'user_pass' ) continue;
		if( is_serialized( $v ) || is_array( $v ) ) {
			$v      = ( is_array( $v ) ) ? $v : @unserialize( $v );
			$v_html = '';
			if( is_array( $v ) && ! empty( $v ) ) {
				foreach( $v as $kk => $vv ) {
					if( is_serialized( $vv ) || is_array( $vv ) ) {
						$v_html = '';
						$vv     = ( is_array( $vv ) ) ? $vv : unserialize( $vv );
						if( $k === wpforo_prefix( 'read_topics' ) ) {
							$v_html .= "<span class='wpf-sb-data'>" . count( $vv ) . "</span> ";
						} else {
							if( is_array( $vv ) && ! empty( $vv ) ) {
								foreach( $vv as $kkk => $vvv ) {
									if( is_array( $vvv ) ) $vvv = implode( ' &nbsp; | &nbsp; ', $vvv );
									$v_html .= "<span class='wpf-sb-data'><key>" . $kkk . ':</key> <value>' . wp_unslash( $vvv ) . "</value></span> &nbsp; | &nbsp; ";
								}
							}
						}
					} else {
						$v_html .= "<span class='wpf-sb-data'><key>" . $kk . ':</key> <value>' . $vv . "</value></span> &nbsp; | &nbsp; ";
					}
				}
			}
			$v = $v_html;
		}
		if( $v == '' ) $v = 'null';
		$html .= "<div class='wpf-data'><key>" . $k . ':</key> <value>' . wp_unslash( $v ) . "</value></div>";
	}

	return $html . "<div style='clear:both'></div>";
}

function wpforo_issues() {

	$issues = [];
	##########################################################
	//Usergroups//////////////////////////////////////////////
	$guest_exists = WPF()->db->get_var( "SELECT `groupid` FROM `" . WPF()->tables->usergroups . "` WHERE `groupid` = 4" );
	if( ! $guest_exists ) {
		$issues['usergroup']['guest']['level']    = 3;
		$issues['usergroup']['guest']['message']  = __( 'The default Guest usergroup is not found!', 'wpforo' );
		$issues['usergroup']['guest']['solution'] = __( 'Please execute this SQL in your Website Hosting cPanel > phpMyAdmin Database Manager', 'wpforo' ) . ": <br><pre style='white-space: pre-line; word-break: break-all; color:#006600'>" . 'INSERT INTO `' . WPF()->tables->usergroups . '` (`groupid`, `name`, `cans`, `description`, `utitle`, `role`, `access`, `color`, `visible`, `secondary`) VALUES
(4, \'Guest\', \'a:32:{s:2:\"mf\";s:1:\"0\";s:2:\"ms\";s:1:\"0\";s:2:\"mt\";s:1:\"0\";s:2:\"mp\";s:1:\"0\";s:3:\"mth\";s:1:\"0\";s:2:\"vm\";s:1:\"0\";s:3:\"aum\";s:1:\"0\";s:2:\"em\";s:1:\"0\";s:3:\"vmg\";s:1:\"0\";s:3:\"aup\";s:1:\"0\";s:4:\"vmem\";s:1:\"1\";s:4:\"vprf\";s:1:\"1\";s:4:\"vpra\";s:1:\"1\";s:4:\"vprs\";s:1:\"0\";s:2:\"bm\";s:1:\"0\";s:2:\"dm\";s:1:\"0\";s:3:\"upa\";s:1:\"0\";s:3:\"ups\";s:1:\"0\";s:2:\"va\";s:1:\"1\";s:3:\"vmu\";s:1:\"0\";s:3:\"vmm\";s:1:\"0\";s:3:\"vmt\";s:1:\"1\";s:4:\"vmct\";s:1:\"1\";s:3:\"vmr\";s:1:\"1\";s:3:\"vmw\";s:1:\"0\";s:4:\"vmsn\";s:1:\"1\";s:4:\"vmrd\";s:1:\"1\";s:3:\"vml\";s:1:\"1\";s:3:\"vmo\";s:1:\"1\";s:3:\"vms\";s:1:\"1\";s:4:\"vmam\";s:1:\"1\";s:4:\"vwpm\";s:1:\"0\";}\', \'\', \'Guest\', \'\', \'read_only\', \'#222222\', 0, 0);
' . "</pre>";
	}

	$perms           = [];
	$nonsec          = [
		'mf'  => __( 'Dashboard - Manage Boards & Forums', 'wpforo' ),
		'ms'  => __( 'Dashboard - Manage Settings', 'wpforo' ),
		'mt'  => __( 'Dashboard - Manage Tools', 'wpforo' ),
		'vm'  => __( 'Dashboard - Manage Members', 'wpforo' ),
		'aum' => __( 'Dashboard - Moderate Topics & Posts', 'wpforo' ),
		'vmg' => __( 'Dashboard - Manage Usergroups', 'wpforo' ),
		'mp'  => __( 'Dashboard - Manage Phrases', 'wpforo' ),
		'mth' => __( 'Dashboard - Manage Themes', 'wpforo' ),
		'em'  => __( 'Dashboard - Can edit member', 'wpforo' ),
		'bm'  => __( 'Dashboard - Can ban member', 'wpforo' ),
		'dm'  => __( 'Dashboard - Can delete member', 'wpforo' ),
		'vmu' => __( 'Front - Can view member username', 'wpforo' ),
	];
	$default_ug      = WPF()->usergroup->default_groupid;
	$default_ug_cans = WPF()->db->get_var( "SELECT `cans` FROM `" . WPF()->tables->usergroups . "` WHERE `groupid` = " . intval( $default_ug ) );
	$default_ug_cans = unserialize( $default_ug_cans );
	if( ! empty( $default_ug_cans ) ) {
		foreach( $default_ug_cans as $key => $value ) {
			if( wpfkey( $nonsec, $key ) && $value == 1 ) {
				$perms[] = $nonsec[ $key ];
			}
		}
		if( ! empty( $perms ) ) {
			$cans                                            = '<ul style="list-style:disc; margin:10px 20px; font-size:12px; line-height:14px;"><li>' . implode( '</li><li>', $perms ) . '</li></ul>';
			$issues['usergroup']['default_perm']['level']    = 3;
			$issues['usergroup']['default_perm']['message']  = __( 'New registered users get access to forum settings!', 'wpforo' );
			$issues['usergroup']['default_perm']['solution'] = __( sprintf( 'Please navigate to Forums > Usergroups admin page, edit the "Default" usergroup (ID = %s) and uncheck/disable following permission(s):', $default_ug ), 'wpforo' ) . $cans;
		}
	}
	#########################################################
	//Plugin Conflicts///////////////////////////////////////
	if( class_exists( 'autoptimizeCache' ) ) {
		$autopt = get_option( 'autoptimize_js_exclude' );
		if( $autopt && strpos( (string) $autopt, 'wp-includes/js/tinymce' ) === false ) {
			$issues['conflicts']['autoptimize']['level']    = 3;
			$issues['conflicts']['autoptimize']['message']  = __( 'Conflict with Autoptimize plugin!', 'wpforo' );
			$issues['conflicts']['autoptimize']['solution'] = __( 'Please navigate to Settings > Autoptimize > Main Tab, click on top right [Show advanced settings] button, find "Exclude scripts from Autoptimize" option, add this JS path <code>,wp-includes/js/tinymce</code>, than click on [Save Changes and Empty Cache] button bellow.', 'wpforo' );
		}
	}
	#########################################################
	//Email Issues //////////////////////////////////////////
	$email   = "test@example.com";
	$subject = "Email Test";
	$message = "This is a mail testing email function on server";
	if( ! wp_mail( $email, $subject, $message ) ) {
		$issues['email']['wp_mail']['level']    = 3;
		$issues['email']['wp_mail']['message']  = __( 'WordPress Email sending function wp_mail() doesn\'t work!', 'wpforo' );
		$issues['email']['wp_mail']['solution'] = __( 'In most cases this is a server issue. We recommend you contact to your hosting service support team or open a support topic in wordpress.org support forum. Also there are many good articles regarding this issue in web. For example ', 'wpforo' ) . ' - <a href="https://www.wpbeginner.com/wp-tutorials/how-to-fix-wordpress-not-sending-email-issue/">' . __( 'How to Fix WordPress Not Sending Email Issue', 'wpforo' ) . '</a>';
	}
    #########################################################
    //Custom Template Files Detected ////////////////////////
    if( is_dir(get_stylesheet_directory(). '/wpforo/' ) ){
        $issues['theme']['custom_theme']['level'] = 1;
        $issues['theme']['custom_theme']['message'] = "Potential Conflict with wpForo Template Files";
        $issues['theme']['custom_theme']['solution'] = "Please make sure the <code style='color:red;'>/wpforo/</code> folder in the current WordPress active theme directory contains wpForo v2.0 template files. If the template files have been moved to this folder from the old v1.x version you should delete them to avoid lots of errors and problems on the forum front-end. You can use an FTP client or the File Manager of your Hosting cPanel to rename or remove the <code style='color:red;'>/wpforo/</code> folder from <code style='color:blue;'>/wp-content/themes/" . basename((string) get_stylesheet_directory()) . "</code> directory";
    }

    #########################################################
    //Check Rewrite Rules ////////////////////////
    if( $pageid = WPF()->board->get_current( 'pageid' ) ){
        $rr_issue = [];
        if( $rewrite_rules = get_option('rewrite_rules') ){
            $rules = [  ')?' . wpforo_settings_get_slug('member') . '(?',
                ')?sign-out(?',
                ')?' . wpforo_settings_get_slug('lostpassword') . '(?',
                ')?' . wpforo_settings_get_slug('login') . '(?',
                ')?' . wpforo_settings_get_slug('register') . '(?',
                ')?' . wpforo_settings_get_slug('members') . '(?',
                ')?' . WPF()->board->get_current( 'slug' ) . '(?'
            ];
            foreach( $rewrite_rules as $r_key => $r_value ){
                $r_key = stripslashes((string) $r_key);
                foreach( $rules as $rule ){
                    if( strpos( $r_key, $rule ) !== FALSE ){
                        if( strpos( (string) $r_value, '&page_id=' ) !== FALSE
                                && strpos( (string) $r_value, '&page_id=' . $pageid ) === FALSE
                        ) {
                            $rr_issue[] = '<li style="list-style: disc; margin: 0 20px;">' . $r_key . ' &nbsp;|&nbsp; ' . $r_value . '<li>';
                        }
                    }
                }

                if($rr_issue){
                    $admin_page = ( is_wpforo_multiboard() ) ? 'wpForo > Dashbaord admin page' : 'wpForo > Overview admin page, scroll down';
                    $issues['pages']['rewrite_rules']['level'] = 3;
                    $issues['pages']['rewrite_rules']['message'] = "Action Required!<br>Some forum pages may be not accessible";
                    $issues['pages']['rewrite_rules']['solution'] = "wpForo detected wrong rewrite rules, please navigate to $admin_page, click the [Soft Flush Permalinks] button, if the issue is not fixed, please click the [Hard Flush Permalinks] button. <br /><br /> Wrong revrite rules (current pageid is " . $pageid . "): <ul style='margin: 0;'>" . implode('', $rr_issue) . "</ul>";
                }
            }
        }
    } else {
        $issues['board']['page']['level'] = 3;
        $issues['board']['page']['message'] = "wpForo Base Page is Not Found";
        $issues['board']['page']['solution'] = "Please check pageid column in the list of boards in wpForo > Boards admin page and make sure this board is connected to a page with [wpforo] shortcode. The documentation: " . "<a href='https://wpforo.com/docs/wpforo-v2/categories-and-forums/forum-boards/' target='_blank'>boards and forum page</a>";
    }

    #########################################################
	//Other Issues //////////////////////////////////////////
	$phrases = WPF()->db->get_var( "SELECT COUNT(*) FROM `" . WPF()->tables->phrases . "`" );
	if( ! $phrases ) {
		$issues['other']['empty_phrase_table']['level']    = 3;
		$issues['other']['empty_phrase_table']['message']  = __( 'wpForo phrases are missing!', 'wpforo' );
		$issues['other']['empty_phrase_table']['solution'] = __( 'Please navigate to wpForo > Overview admin page and click the [Rebuild Phrases] button', 'wpforo');
	}

	echo '<table class="wpf-table-data" style="margin: 10px; width: 98%;">';
	if( ! empty( $issues ) ) {
		foreach( $issues as $key => $issue ) {
			if( ! empty( $issue ) ) {
				echo '<tr><td colspan="6" style="background:#d86868; color: #ffffff;font-size:14px; line-height:26px"><b>' . strtoupper( (string) $key ) . '</b></td></tr>';
				foreach( $issue as $data ) {
					echo '<tr>';
					echo '<td class="wpf-ilevel-' . intval( $data['level'] ) . '" style="vertical-align: top;width:25%; font-size:15px; padding:10px; line-height: 22px;">' . $data['message'] . '</td>';
					echo '<td style="vertical-align: top;font-size:13px;line-height:20px;padding:10px;">' . $data['solution'] . '</td>';
					echo '</tr>';
				}
			}
		}
	} else {
		echo '<tr><td><p>&nbsp;&nbsp;&nbsp;' . __( 'No issues found', 'wpforo' ) . '<p></td></tr>';
	}
	echo '</table>';
}

function wpforo_read_file_revers( $file ) {
	$result  = [];
	$numRows = 100;
	$handle  = fopen( $file, "r" );
	while( ! feof( $handle ) ) {
		array_push( $result, fgets( $handle, 4096 ) );
		if( count( $result ) > $numRows ) array_shift( $result );
	}
	$result = array_filter( $result );
	$result = array_reverse( $result );
	if( ! empty( $result ) ) {
		echo '<h3 style="padding:5px 20px;">' . __( 'Error Log File', 'wpforo' ) . ': <code style="font-size:14px;">' . $file . '</code><h3>';
		echo '<ul style="list-style:disc; margin:20px 30px;">';
		foreach( $result as $error ) {
			echo '<li style="border-bottom:1px dotted #cccccc; font-size:12px;line-height:16px; padding:0px 0px 3px 0px">' . $error . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<p>' . __( 'No errors found', 'wpforo' ) . '</p>';
	}

}
