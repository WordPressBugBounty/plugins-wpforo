<?php

namespace wpforo\admin\listtables;

use WP_List_Table;

require_once( ABSPATH . 'wp-admin/includes/template.php' );
require_once( ABSPATH . 'wp-admin/includes/screen.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-screen.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Moderations extends WP_List_Table {

	public $wpfitems_count;

	/**
	 * Cache for moderation data to avoid repeated queries
	 */
	private $moderation_cache = [];

	/** ************************************************************************
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 ***************************************************************************/
	function __construct() {
		//Set parent defaults
		parent::__construct(
			[
				'singular' => 'moderation',     //singular name of the listed records
				'plural'   => 'moderations',    //plural name of the listed records
				'ajax'     => false,            //does this table support ajax?
				'screen'   => 'wpForoModerations',
			]
		);

	}


	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized. For example, if the class needs to process a column
	 * named 'title', it would first see if a method named $this->column_title()
	 * exists - if it does, that method will be used. If it doesn't, this one will
	 * be used. Generally, you should try to use custom column methods as much as
	 * possible.
	 *
	 * Since we have defined a column_title() method later on, this method doesn't
	 * need to concern itself with any column with a name of 'title'. Instead, it
	 * needs to handle everything else.
	 *
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 * @param string $column_name The name/slug of the column to be processed
	 *
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'title':
				return apply_filters( 'wpforo_admin_listtables_moderations_column_title', $item[ $column_name ], $item );
			case 'userid':
				$userdata = get_userdata( $item[ $column_name ] );
				$display_name = ! empty( $userdata->user_nicename ) ? urldecode( $userdata->user_nicename ) : $item[ $column_name ];

				// Check if user is banned
				$member = WPF()->member->get_member( $item[ $column_name ] );
				$is_banned = ( ! empty( $member ) && isset( $member['status'] ) && $member['status'] === 'banned' );

				if( $is_banned ) {
					return '<span style="color: #dc3545; font-weight: 500;" title="' . esc_attr__( 'Banned', 'wpforo' ) . '">' . esc_html( $display_name ) . '</span>';
				}

				return esc_html( $display_name );
			case 'is_first_post':
				return ( $item[ $column_name ] ) ? __( 'TOPIC', 'wpforo' ) : __( 'REPLY', 'wpforo' );
			case 'private':
				return ( $item[ $column_name ] ) ? __( 'YES', 'wpforo' ) : __( 'NO', 'wpforo' );
			default:
				return $item[ $column_name ];
		}
	}


	/** ************************************************************************
	 * Recommended. This is a custom column method and is responsible for what
	 * is rendered in any column with a name/slug of 'title'. Every time the class
	 * needs to render a column, it first looks for a method named
	 * column_{$column_title} - if it exists, that method is run. If it doesn't
	 * exist, column_default() is called instead.
	 *
	 * This example also illustrates how to implement rollover actions. Actions
	 * should be an associative array formatted as 'slug'=>'link html' - and you
	 * will need to generate the URLs yourself. You could even ensure the links
	 *
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************@see WP_List_Table::::single_row_columns()
	 */
	function column_postid( $item ) {
		$vhref = WPF()->moderation->get_view_url( $item['postid'] );
		//Build row actions
		$actions = [ 'view' => '<a href="' . $vhref . '" target="_blank">' . __( 'View', 'wpforo' ) . '</a>' ];
		if( $this->get_filter_by_status_var() ) {
			$ahref                 = wp_nonce_url( admin_url( sprintf( 'admin.php?page=%1$s&wpfaction=%2$s&postid=%3$s', wpforo_prefix_slug( 'moderations' ), 'dashboard_post_approve', $item['postid'] ) ), 'wpforo-approve-post-' . $item['postid'] );
			$actions['wpfapprove'] = '<a href="' . $ahref . '">' . __( 'Approve', 'wpforo' ) . '</a>';
		} else {
			$uhref                   = wp_nonce_url( admin_url( sprintf( 'admin.php?page=%1$s&wpfaction=%2$s&postid=%3$s', wpforo_prefix_slug( 'moderations' ), 'dashboard_post_unapprove', $item['postid'] ) ), 'wpforo-unapprove-post-' . $item['postid'] );
			$actions['wpfunapprove'] = '<a href="' . $uhref . '">' . __( 'Unapprove', 'wpforo' ) . '</a>';
		}

		$dhref             = wp_nonce_url( admin_url( sprintf( 'admin.php?page=%1$s&wpfaction=%2$s&postid=%3$s', wpforo_prefix_slug( 'moderations' ), 'dashboard_post_delete', $item['postid'] ) ), 'wpforo-delete-post-' . $item['postid'] );
		$actions['delete'] = '<a onclick="return confirm(\'' . __( "Are you sure you want to DELETE this item?", 'wpforo' ) . '\');" href="' . $dhref . '">' . __( 'Delete', 'wpforo' ) . '</a>';

		// Ban/Unban User action - show based on user's current ban status
		if( ! empty( $item['userid'] ) && $item['userid'] > 0 && WPF()->usergroup->can( 'bm' ) && intval( $item['userid'] ) !== WPF()->current_userid ) {
			$member = WPF()->member->get_member( $item['userid'] );
			$is_banned = ( ! empty( $member ) && isset( $member['status'] ) && $member['status'] === 'banned' );

			if( $is_banned ) {
				$unban_url = wp_nonce_url( admin_url( sprintf( 'admin.php?page=%1$s&wpfaction=%2$s&userid=%3$s', wpforo_prefix_slug( 'members' ), 'user_unban', $item['userid'] ) ), 'wpforo-user-unban-' . $item['userid'] );
				$actions['ban_user'] = '<a style="white-space:nowrap; color:#006600;" onclick="return confirm(\'' . __( "Are you sure you want to UNBAN this user?", 'wpforo' ) . '\');" href="' . esc_url( $unban_url ) . '">' . __( 'Unban&nbsp;User', 'wpforo' ) . '</a>';
			} else {
				$ban_url = wp_nonce_url( admin_url( sprintf( 'admin.php?page=%1$s&wpfaction=%2$s&userid=%3$s', wpforo_prefix_slug( 'members' ), 'user_ban', $item['userid'] ) ), 'wpforo-user-ban-' . $item['userid'] );
				$actions['ban_user'] = '<a style="white-space:nowrap; color:orange;" onclick="return confirm(\'' . __( "Are you sure you want to BAN this user?", 'wpforo' ) . '\');" href="' . esc_url( $ban_url ) . '">' . __( 'Ban&nbsp;User', 'wpforo' ) . '</a>';
			}
		}

		// Delete User action - links to WordPress delete user page
		if( ! empty( $item['userid'] ) && $item['userid'] > 0 && WPF()->usergroup->can( 'dm' ) && intval( $item['userid'] ) !== WPF()->current_userid ) {
			$delete_user_url         = wp_nonce_url( admin_url( 'users.php?action=delete&user=' . intval( $item['userid'] ) ), 'bulk-users' );
			$actions['delete_user']  = '<a style="white-space:nowrap;" onclick="return confirm(\'' . __( "Are you sure you want to DELETE this USER? This will open the WordPress user deletion page where you can choose to delete or reassign their content.", 'wpforo' ) . '\');" href="' . esc_url( $delete_user_url ) . '">' . __( 'Delete&nbsp;User', 'wpforo' ) . '</a>';
		}

		$actions = apply_filters( 'wpforo_admin_listtables_moderations_actions', $actions, $item );

		//Return the title contents
		return sprintf(
			         '%1$s %2$s',
			/*$1%s*/ $item['postid'],
			/*$2%s*/ $this->row_actions( $actions )
		);
	}


	/** ************************************************************************
	 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
	 * is given special treatment when columns are processed. It ALWAYS needs to
	 * have its own method.
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************@see WP_List_Table::::single_row_columns()
	 */
	function column_cb( $item ) {
		return sprintf(
			         '<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ 'postids',  //Let's simply repurpose the table's singular label ("movie")
			/*$2%s*/ $item['postid']         //The value of the checkbox should be the record's id
		);
	}


	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * to bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************@see WP_List_Table::::single_row_columns()
	 */
	function get_columns() {
		return [
			'cb'            => '<input type="checkbox" />', //Render a checkbox instead of text
			'postid'        => __( 'ID', 'wpforo' ),
			'title'         => __( 'Title', 'wpforo' ),
			'is_first_post' => __( 'Type', 'wpforo' ),
			'userid'        => __( 'Created By', 'wpforo' ),
			'created'       => __( 'Created', 'wpforo' ),
			'private'       => __( 'Private', 'wpforo' ),
		];
	}


	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	function get_sortable_columns() {
		return [
			'postid'        => [ 'postid', false ],     //true means it's already sorted
			'title'         => [ 'title', false ],
			'is_first_post' => [ 'is_first_post', false ],
			'userid'        => [ 'userid', false ],
			'created'       => [ 'created', false ],
			'private'       => [ 'private', false ],
		];
	}


	/** ************************************************************************
	 * Optional. If you need to include bulk actions in your list table, this is
	 * the place to define them. Bulk actions are an associative array in the format
	 * 'slug'=>'Visible Title'
	 *
	 * If this method returns an empty value, no bulk action will be rendered. If
	 * you specify any bulk actions, the bulk actions box will be rendered with
	 * the table automatically on display().
	 *
	 * Also note that list tables are not automatically wrapped in <form> elements,
	 * so you will need to create those manually in order for bulk actions to function.
	 *
	 * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_bulk_actions() {
		$bulk_actions = [];
		if( $this->get_filter_by_status_var() ) {
			$bulk_actions['approve'] = __( 'Approve', 'wpforo' );
		} else {
			$bulk_actions['unapprove'] = __( 'Unapprove', 'wpforo' );
		}
		$bulk_actions['delete'] = __( 'Delete', 'wpforo' );

		return $bulk_actions;
	}


	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {
		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = wpforo_get_option( 'count_per_page', 10 );


		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();


		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		/**
		 * REQUIRED. Now we can add our *sorted* data to the items' property, where
		 * it can be used by the rest of the class.
		 */
		$args = [ 'check_private' => false, 'status' => $this->get_filter_by_status_var(), 'orderby' => '`created` DESC, `postid` DESC' ];
		if( $s = wpfval( $_REQUEST, 's' ) ) {
			$args['include'] = WPF()->moderation->search( $s );
		}
		$filter_by_userid = $this->get_filter_by_userid_var();
		$orderby          = wpfval( $_REQUEST, 'orderby' );
		$order            = strtoupper( (string) wpfval( $_REQUEST, 'order' ) );
		if( $filter_by_userid !== - 1 ) $args['userid'] = $filter_by_userid;

        if( $type = $this->get_filter_by_type_var() ){
            $args['is_first_post'] = $type === 'topic';
        }

		if( array_key_exists( $orderby, $sortable ) ) $args['orderby'] = sanitize_text_field( $orderby );
		if( in_array( $order, [ 'ASC', 'DESC' ] ) ) $args['order'] = sanitize_text_field( $order );

		$paged             = $this->get_pagenum();
		$args['offset']    = ( $paged - 1 ) * $per_page;
		$args['row_count'] = $per_page;

		$items_count = 0;
		$this->items = ( isset( $args['include'] ) && empty( $args['include'] ) ? [] : WPF()->post->get_posts( $args, $items_count ) );

		$this->wpfitems_count = $items_count;

		$this->set_pagination_args( [
			                            'total_items' => $items_count,                  //WE have to calculate the total number of items
			                            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
			                            'total_pages' => ceil( $items_count / $per_page )   //WE have to calculate the total number of pages
		                            ] );
	}

	public function get_filter_by_status_var() {
		$filter_by_status = wpfval( $_REQUEST, 'filter_by_status' );
		if( ! is_null( $filter_by_status ) && $filter_by_status !== '-1' ) {
			$status = intval( $filter_by_status );
		} else {
			$status = 1;
		}

		return $status;
	}

    public function get_filter_by_type_var() {
		$filter_by_type = wpfval( $_REQUEST, 'filter_by_type' );

	    return in_array( $filter_by_type, [ 'topic', 'reply' ], true ) ? $filter_by_type : '';
	}

	public function get_filter_by_userid_var() {
		$filter_by_userid = wpfval( $_REQUEST, 'filter_by_userid' );
		if( ! is_null( $filter_by_userid ) && $filter_by_userid !== '-1' ) {
			$userid = wpforo_bigintval( $filter_by_userid );
		} else {
			$userid = - 1;
		}

		return $userid;
	}

	public function users_dropdown() {
		?>
        <label>
            <select name="filter_by_userid">
                <option value="-1">-- <?php _e( 'All Users', 'wpforo' ); ?> --</option>

				<?php
				if( $userids = WPF()->moderation->get_distinct_userids( $this->get_filter_by_status_var() ) ) {
					$current_userid = $this->get_filter_by_userid_var();
					foreach( $userids as $userid ) {
						$userid   = wpforo_bigintval( $userid );
						$userdata = get_userdata( $userid );
						?>
                        <option value="<?php echo $userid ?>" <?php echo( $current_userid === $userid ? 'selected' : '' ) ?> > <?php echo( ! empty( $userdata->user_nicename ) ? urldecode( $userdata->user_nicename ) : $userid ) ?> </option>
						<?php
					}
				}
				?>
            </select>
        </label>
		<?php
	}

	public function status_dropdown() {
		$filter_by_status = $this->get_filter_by_status_var();
		if( $statuses = WPF()->moderation->post_statuses ) : ?>
            <label>
                <select name="filter_by_status">
					<?php foreach( $statuses as $key => $status ) : ?>
                        <option value="<?php echo esc_attr( $key ) ?>" <?php echo( $filter_by_status === $key ? 'selected' : '' ) ?>><?php echo esc_html( $status ) ?></option>
					<?php endforeach; ?>
                </select>
            </label>
		<?php
		endif;
	}

    public function type_dropdown() {
		$filter_by_type = $this->get_filter_by_type_var();
        ?>
		<label>
            <select name="filter_by_type">
                <option value="">-- <?php _e('All', 'wpforo') ?> --</option>
                <option value="topic" <?php echo( $filter_by_type === 'topic' ? 'selected' : '' ) ?>><?php _e('Topic', 'wpforo') ?></option>
                <option value="reply" <?php echo( $filter_by_type === 'reply' ? 'selected' : '' ) ?>><?php _e('Reply', 'wpforo') ?></option>
            </select>
        </label>
        <?php
	}

	/**
	 * Override single_row to add moderation report row after each post row
	 *
	 * @param array $item The current item
	 */
	public function single_row( $item ) {
		// Output the regular row
		parent::single_row( $item );

		// Only show moderation report row for unapproved posts
		if( $this->get_filter_by_status_var() !== 1 ) {
			return;
		}

		// Get moderation data for this post
		$moderation_data = $this->get_moderation_data( $item );

		// Always render the report row to maintain odd/even striping
		// Empty rows will be hidden via CSS
		$this->render_moderation_report_row( $item, $moderation_data );
	}

	/**
	 * Get moderation data for a post
	 *
	 * @param array $item Post item data
	 * @return array|null Moderation data or null
	 */
	private function get_moderation_data( $item ) {
		$postid = intval( $item['postid'] );

		// Check cache first
		if( isset( $this->moderation_cache[ $postid ] ) ) {
			return $this->moderation_cache[ $postid ];
		}

		$result = [];

		// Determine content type
		$is_first_post = ! empty( $item['is_first_post'] );
		$content_type  = $is_first_post ? 'topic' : 'post';
		$content_id    = $is_first_post ? ( $item['topicid'] ?? $item['postid'] ) : $item['postid'];

		// Get AI Moderation data (if AI Moderation is active)
		if( class_exists( '\wpforo\classes\AIContentModeration' ) && ! empty( WPF()->ai_content_moderation ) ) {
			$ai_logs = WPF()->ai_content_moderation->get_moderation_logs( $content_type, (int) $content_id );
			if( ! empty( $ai_logs ) ) {
				$result['ai_moderation'] = $ai_logs;
			}
		}

		// Check for wpForo built-in antispam (this is simple - just a flag that post was unapproved by antispam)
		// Built-in antispam doesn't store detailed data, but we can check if it was likely antispam-based
		// by looking at whether user is new or if spam patterns were detected
		if( empty( $result['ai_moderation'] ) && ! empty( $item['userid'] ) ) {
			$new_user_max_posts = wpforo_setting( 'antispam', 'new_user_max_posts' );
			if( $new_user_max_posts ) {
				$user_posts = WPF()->member->member_approved_posts( $item['userid'] );
				if( $user_posts <= $new_user_max_posts ) {
					// Likely caught by built-in antispam for new users
					$result['builtin_antispam'] = [
						'reason' => 'new_user',
						'label'  => __( 'New User Filter', 'wpforo' ),
						'description' => __( 'Post was automatically held for moderation because the author is a new user.', 'wpforo' ),
					];
				}
			}
		}

		// Cache the result
		$this->moderation_cache[ $postid ] = $result;

		return $result;
	}

	/**
	 * Render the moderation report row
	 *
	 * @param array $item Post item data
	 * @param array $moderation_data Moderation data (can be empty)
	 */
	private function render_moderation_report_row( $item, $moderation_data ) {
		$columns_count = count( $this->get_columns() );
		$has_data = ! empty( $moderation_data );
		$row_class = 'wpf-moderation-report-row' . ( $has_data ? '' : ' wpf-moderation-report-empty' );
		?>
		<tr class="<?php echo esc_attr( $row_class ); ?>">
			<?php if( $has_data ) : ?>
			<td colspan="<?php echo esc_attr( $columns_count ); ?>" class="wpf-moderation-report-cell">
				<div class="wpf-moderation-report-container">
					<?php
					// Display AI Moderation reports
					if( ! empty( $moderation_data['ai_moderation'] ) ) {
						foreach( $moderation_data['ai_moderation'] as $log ) {
							$this->render_ai_moderation_report( $log );
						}
					}

					// Display built-in antispam info
					if( ! empty( $moderation_data['builtin_antispam'] ) ) {
						$this->render_builtin_antispam_report( $moderation_data['builtin_antispam'] );
					}
					?>
				</div>
			</td>
			<?php else : ?>
			<td colspan="<?php echo esc_attr( $columns_count ); ?>"></td>
			<?php endif; ?>
		</tr>
		<?php
	}

	/**
	 * Render AI moderation report
	 *
	 * @param array $log Moderation log data
	 */
	private function render_ai_moderation_report( $log ) {
		$score       = (int) ( $log['score'] ?? 0 );
		$confidence  = (float) ( $log['confidence'] ?? 0 );
		$mod_type    = $log['moderation_type'] ?? 'spam';
		$action      = $log['action_taken'] ?? 'none';
		$summary     = $log['analysis_summary'] ?? '';
		$indicators  = $log['indicators'] ?? [];
		$quality     = $log['quality_tier'] ?? 'fast';
		$credits     = (int) ( $log['credits_used'] ?? 0 );
		$created     = $log['created'] ?? '';
		$context_used = ! empty( $log['context_used'] );
		$is_ai       = ( $quality !== 'rule_based' );

		// Decode indicators if string
		if( is_string( $indicators ) && ! empty( $indicators ) ) {
			$indicators = json_decode( $indicators, true ) ?: [];
		}

		// Moderation type config
		$type_config = [
			'spam' => [
				'label' => $is_ai ? __( 'Spam Detection', 'wpforo' ) : __( 'Auto Moderation', 'wpforo' ),
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>',
				'color' => $is_ai ? '#d63384' : '#0d6efd',
			],
			'toxicity' => [
				'label' => __( 'Toxicity Detection', 'wpforo' ),
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
				'color' => '#fd7e14',
			],
			'compliance' => [
				'label' => __( 'Policy Compliance', 'wpforo' ),
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>',
				'color' => '#6f42c1',
			],
			'flood' => [
				'label' => __( 'Auto Moderation', 'wpforo' ),
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
				'color' => '#0d6efd',
			],
		];

		$config = $type_config[ $mod_type ] ?? $type_config['spam'];

		// Status class and label
		$status_class = 'clean';
		$status_label = __( 'Clean', 'wpforo' );
		if( $score >= 85 ) {
			$status_class = 'detected';
			$status_label = __( 'Detected', 'wpforo' );
		} elseif( $score >= 70 ) {
			$status_class = 'suspected';
			$status_label = __( 'Suspected', 'wpforo' );
		} elseif( $score >= 51 ) {
			$status_class = 'uncertain';
			$status_label = __( 'Uncertain', 'wpforo' );
		}

		// Action labels
		$action_labels = [
			'none'          => __( 'No action', 'wpforo' ),
			'approve'       => __( 'Auto-approved', 'wpforo' ),
			'auto_approve'  => __( 'Auto-approved', 'wpforo' ),
			'unapprove'     => __( 'Unapproved', 'wpforo' ),
			'unapprove_ban' => __( 'Unapproved + Banned', 'wpforo' ),
			'delete_author' => __( 'Deleted + Banned', 'wpforo' ),
		];
		$action_label = $action_labels[ $action ] ?? $action;

		// Quality labels
		$quality_labels = [
			'fast'       => __( 'Fast', 'wpforo' ),
			'balanced'   => __( 'Balanced', 'wpforo' ),
			'advanced'   => __( 'Advanced', 'wpforo' ),
			'premium'    => __( 'Premium', 'wpforo' ),
			'rule_based' => __( 'Rule-based', 'wpforo' ),
		];
		$quality_label = $quality_labels[ $quality ] ?? $quality;
		?>
		<div class="wpf-mod-report wpf-mod-report-ai wpf-mod-status-<?php echo esc_attr( $status_class ); ?>" data-type="<?php echo esc_attr( $mod_type ); ?>">
			<div class="wpf-mod-report-type">
				<span class="wpf-mod-type-icon" style="color: <?php echo esc_attr( $config['color'] ); ?>">
					<?php echo $config['icon']; ?>
				</span>
				<span class="wpf-mod-type-label"><?php echo esc_html( $config['label'] ); ?></span>
			</div>
			<div class="wpf-mod-report-content">
				<div class="wpf-mod-report-header">
					<span class="wpf-mod-status wpf-mod-status-<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
					<?php if( $is_ai ) : ?>
						<span class="wpf-mod-score"><?php printf( __( 'Score: %d%%', 'wpforo' ), $score ); ?></span>
						<span class="wpf-mod-confidence"><?php printf( __( 'Confidence: %d%%', 'wpforo' ), round( $confidence * 100 ) ); ?></span>
					<?php else : ?>
						<span class="wpf-mod-score"><?php _e( 'Score: -', 'wpforo' ); ?></span>
					<?php endif; ?>
					<span class="wpf-mod-action"><?php printf( __( 'Action: %s', 'wpforo' ), $action_label ); ?></span>
				</div>

				<?php if( ! empty( $summary ) ) : ?>
				<div class="wpf-mod-report-summary">
					<strong><?php _e( 'Summary:', 'wpforo' ); ?></strong> <?php echo esc_html( $summary ); ?>
				</div>
				<?php endif; ?>

				<?php if( ! empty( $indicators ) && is_array( $indicators ) ) : ?>
				<div class="wpf-mod-report-indicators">
					<strong><?php _e( 'Indicators:', 'wpforo' ); ?></strong>
					<ul class="wpf-mod-indicator-list">
						<?php foreach( $indicators as $indicator ) : ?>
						<li class="wpf-mod-indicator wpf-mod-severity-<?php echo esc_attr( strtolower( $indicator['severity'] ?? 'medium' ) ); ?>">
							<span class="wpf-mod-indicator-category"><?php echo esc_html( $indicator['category'] ?? '' ); ?></span>
							<?php if( ! empty( $indicator['description'] ) ) : ?>
							<span class="wpf-mod-indicator-desc"><?php echo esc_html( $indicator['description'] ); ?></span>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<div class="wpf-mod-report-meta">
					<?php if( $is_ai ) : ?>
					<span class="wpf-mod-quality"><?php printf( __( 'AI executor: %s', 'wpforo' ), $quality_label ); ?></span>
					<?php if( $credits > 0 ) : ?>
					<span class="wpf-mod-credits"><?php printf( _n( '%d credit', '%d credits', $credits, 'wpforo' ), $credits ); ?></span>
					<?php endif; ?>
					<?php if( $context_used ) : ?>
					<span class="wpf-mod-context"><?php _e( 'Context-aware', 'wpforo' ); ?></span>
					<?php endif; ?>
					<?php endif; ?>
					<?php if( ! empty( $created ) ) : ?>
					<span class="wpf-mod-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created ) ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render built-in antispam report
	 *
	 * @param array $data Antispam data
	 */
	private function render_builtin_antispam_report( $data ) {
		?>
		<div class="wpf-mod-report wpf-mod-report-builtin">
			<div class="wpf-mod-report-type">
				<span class="wpf-mod-type-icon" style="color: #0d6efd;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
				</span>
				<span class="wpf-mod-type-label"><?php _e( 'wpForo Antispam', 'wpforo' ); ?></span>
			</div>
			<div class="wpf-mod-report-content">
				<div class="wpf-mod-report-header">
					<span class="wpf-mod-status wpf-mod-status-builtin"><?php echo esc_html( $data['label'] ); ?></span>
				</div>
				<div class="wpf-mod-report-summary">
					<?php echo esc_html( $data['description'] ); ?>
				</div>
			</div>
		</div>
		<?php
	}
}
