<?php if( ! defined( "ABSPATH" ) ) exit() ?>

    <input type="hidden" name="wpfaction" value="recaptcha_settings_save">

<?php
WPF()->settings->header( 'recaptcha' );
WPF()->settings->form_field( 'recaptcha', 'version' );
WPF()->settings->form_field( 'recaptcha', 'site_key_secret_key' );
WPF()->settings->form_field( 'recaptcha', 'v3_score_threshold' );
WPF()->settings->form_field( 'recaptcha', 'theme' );
WPF()->settings->form_field( 'recaptcha', 'topic_editor' );
WPF()->settings->form_field( 'recaptcha', 'post_editor' );
WPF()->settings->form_field( 'recaptcha', 'wpf_login_form' );
WPF()->settings->form_field( 'recaptcha', 'wpf_reg_form' );
WPF()->settings->form_field( 'recaptcha', 'wpf_lostpass_form' );
WPF()->settings->form_field( 'recaptcha', 'login_form' );
WPF()->settings->form_field( 'recaptcha', 'reg_form' );
WPF()->settings->form_field( 'recaptcha', 'lostpass_form' );
?>

<script>
(function(){
    function toggleRecaptchaFields() {
        var versionSelect = document.querySelector('select[name="recaptcha[version]"]');
        if (!versionSelect) return;

        var version = versionSelect.value;
        var v3ScoreRow = document.querySelector('select[name="recaptcha[v3_score_threshold]"]');
        var themeRow = document.querySelector('select[name="recaptcha[theme]"]');

        // Find parent rows
        if (v3ScoreRow) {
            var v3Row = v3ScoreRow.closest('tr');
            if (v3Row) {
                v3Row.style.display = (version === 'v3') ? '' : 'none';
            }
        }

        if (themeRow) {
            var themeRowEl = themeRow.closest('tr');
            if (themeRowEl) {
                // Theme is only relevant for v2_checkbox
                themeRowEl.style.display = (version === 'v2_checkbox') ? '' : 'none';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var versionSelect = document.querySelector('select[name="recaptcha[version]"]');
        if (versionSelect) {
            versionSelect.addEventListener('change', toggleRecaptchaFields);
            toggleRecaptchaFields();
        }
    });
})();
</script>
<?php
