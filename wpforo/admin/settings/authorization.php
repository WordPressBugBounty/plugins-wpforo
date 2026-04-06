<?php if( ! defined( "ABSPATH" ) ) exit(); ?>

    <input type="hidden" name="wpfaction" value="authorization_settings_save">

<?php WPF()->settings->header( 'authorization' ); ?>

<?php
WPF()->settings->form_field( 'authorization', 'user_register' );
WPF()->settings->form_field( 'authorization', 'user_register_email_confirm' );
WPF()->settings->form_field( 'authorization', 'manually_approval' );
WPF()->settings->form_field( 'authorization', 'manually_approval_contact_form' );
WPF()->settings->form_field( 'authorization', 'role_synch' );
WPF()->settings->form_field( 'authorization', 'user_delete_method' );
WPF()->settings->form_field( 'authorization', 'send_email_after_user_delete' );
WPF()->settings->form_field( 'authorization', 'use_our_register_url' );
WPF()->settings->form_field( 'authorization', 'use_our_login_url' );
WPF()->settings->form_field( 'authorization', 'use_our_lostpassword_url' );
WPF()->settings->form_field( 'authorization', 'custom_auth_urls' );
WPF()->settings->form_field( 'authorization', 'custom_redirect_urls' );
?>
