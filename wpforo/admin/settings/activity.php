<?php if( ! defined( "ABSPATH" ) ) exit() ?>

    <input type="hidden" name="wpfaction" value="activity_settings_save">

<?php
WPF()->settings->header( 'activity' );
WPF()->settings->form_field( 'activity', 'enabled_types' );
WPF()->settings->form_field( 'activity', 'items_per_page' );
WPF()->settings->form_field( 'activity', 'default_period' );
WPF()->settings->form_field( 'activity', 'show_avatars' );
WPF()->settings->form_field( 'activity', 'show_excerpt' );
WPF()->settings->form_field( 'activity', 'excerpt_length' );
WPF()->settings->form_field( 'activity', 'retention_days' );
