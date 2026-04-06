<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

$follows = (array) wpfval( WPF()->current_object, 'follows' );
?>

<div class="wpforo-followers">
    <?php do_action( 'wpforo_template_profile_followers_head_bar', $follows ); ?>

    <h3 class="wpf-tab-subtitle"><?php wpforo_phrase('Followers') ?></h3>

	<?php if( $follows ) : ?>
        <div class="wpforo-followers-list">
			<?php
            foreach( $follows as $follow ) {
				if( $follow['itemtype'] === 'user' ) {
					if( $user = WPF()->member->get_member( $follow['userid'] ) ){
						printf(
							'<div class="wpforo-follower">
                                <div class="follower-avatar">%1$s</div>
                                <div class="follower-title">%2$s</div>
                            </div>',
							wpforo_member_link( $user, '', 64, '', false, 'avatar' ),
							wpforo_member_link( $user, '', 30, '', false )
						);
					}
				}
			}
            ?>
        </div>
        <div class="followers-foot">
			<?php wpforo_template_pagenavi() ?>
        </div>
	<?php else : ?>
        <p class="wpf-p-error"> <?php wpforo_phrase( 'No followers found for this member.' ) ?> </p>
	<?php endif; ?>
</div>
