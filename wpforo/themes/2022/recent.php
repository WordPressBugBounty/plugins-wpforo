<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wpforo-recent-wrap">
	<?php do_action( 'wpforo_recent_list_head' ); ?>
    <div class="wpf-head-bar">
        <div class="wpf-head-top">
            <h1 id="wpforo-title"><?php echo wpforo_get_recent_page_title() ?></h1>
            <div class="wpforo-feed" style="float: none;">
				<?php wpforo_mark_all_read_link();
				wpforo_rss_feed_links(); ?>
            </div>
        </div>
        <div class="wpf-head-bottom">
            <div class="wpf-head-prefix">
				<?php do_action( 'wpforo_recent_posts_page_under_head', WPF()->current_object['args'], wpfval( WPF()->current_object['args'], 'view' ) ); ?>
            </div>
            <div class="wpf-head-buttons">
                <div class="wpf-head-filter">
					<?php echo wpforo_get_recent_page_filter_selectbox() ?>
                </div>
                <div class="wpf-snavi">
					<?php wpforo_template_pagenavi( 'wpf-navi-recentpost-top', false ); ?>
                </div>
            </div>
        </div>
    </div>
	
	<?php
	if( wpfval( WPF()->current_object['args'], 'type' ) === 'topics' ) {
		include( wpftpl( 'recent-topics.php' ) );
	} else {
		include( wpftpl( 'recent-posts.php' ) );
	}
	?>

    <div class="wpf-snavi">
		<?php wpforo_template_pagenavi( 'wpf-navi-recentpost-bottom', false ); ?>
    </div>
	
	<?php do_action( 'wpforo_recent_list_footer' ); ?>
</div>
<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
