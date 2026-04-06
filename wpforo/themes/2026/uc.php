<?php
if( ! defined( 'ABSPATH' ) ) exit;

if( WPF()->board->get_current( 'is_standalone' ) ) get_header(); ?>
<div id="wpforo">
	<div id="wpforo-wrap" class="<?php do_action( 'wpforo_wrap_class' ); ?>">
        <div style="text-align: center; font-size: 55px; color: #0A75B5;">
            &#128679;&nbsp;&nbsp;<?php wpforo_phrase( 'Forum Board is Under Construction' ) ?>&nbsp;&nbsp;&#128679;
        </div>
	</div>
</div>
<?php if( WPF()->board->get_current( 'is_standalone' ) ) get_footer(); ?>
