<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

$subscribes = (array) wpfval( WPF()->current_object, 'subscribes' );
?>

<div class="wpforo-subscriptions">
    <?php wpforo_template_profile_board_panel() ?>
    <?php do_action( 'wpforo_template_profile_subscriptions_head_bar', $subscribes ); ?>

	<?php if( $subscribes ) : ?>
        <div class="wpforo-sbs">
			<?php foreach( $subscribes as $subscribe ) : ?>
				<?php
				$item_url = '#';
				if( in_array( $subscribe['type'], [ 'forum', 'forum-topic' ] ) ) {
					$item     = WPF()->forum->get_forum( $subscribe['itemid'] );
					if( !empty( $item ) ) $item_url = WPF()->forum->get_forum_url( $item['forumid'] );
				} elseif( $subscribe['type'] === 'topic' ) {
					$item     = WPF()->topic->get_topic( $subscribe['itemid'] );
					if( !empty( $item ) ) $item_url = WPF()->topic->get_url( $item['topicid'] );
				} elseif( in_array( $subscribe['type'], [ 'forums', 'forums-topics' ] ) ) {
					$item     = [ 'title' => wpforo_phrase( 'All ' . $subscribe['type'], false ) ];
				}
				if( empty( $item ) ) continue;
				?>
                <div class="wpforo-sb">
                    <div class="sbn-icon"><i class="fas fa-1x <?php echo ( $subscribe['type'] == 'forum' ) ? 'fa-comments' : 'fa-file-alt'; ?>"></i></div>
                    <div class="sbn-title"><a href="<?php echo esc_url( (string) $item_url ) ?>"><?php echo esc_html( $item['title'] ) ?></a></div>
					<?php if( WPF()->current_object['user_is_same_current_user'] || wpforo_current_user_is( 'admin' ) ) : ?>
                        <div class="sbn-action"><span class="wpf-sbn-unsbscrb" data-boardid="<?php echo WPF()->board->get_current( 'boardid' ) ?>" data-key="<?php echo $subscribe['confirmkey'] ?>" title="<?php wpforo_phrase( 'Unsubscribe' ); ?>"><i class="fas fa-bell-slash"></i></span></div>
					<?php else : ?>
                        <div class="sbn-action">&nbsp;</div>
					<?php endif ?>
                </div>
				<?php endforeach ?>
        </div>
        <div class="sbn-foot">
			<?php wpforo_template_pagenavi() ?>
        </div>
	<?php else : ?>
        <p class="wpf-p-error"> <?php wpforo_phrase( 'No subscriptions found for this member.' ) ?> </p>
	<?php endif; ?>
</div>
