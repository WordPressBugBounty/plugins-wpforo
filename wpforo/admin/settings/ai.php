<?php if( ! defined( "ABSPATH" ) ) exit() ?>

<?php
// Enqueue AI features script for user search and other AI settings functionality
// (On the dedicated AI Features page this is already done in ai-features.php,
// but on the Settings page we need to enqueue it here)
if ( ! wp_script_is( 'wpforo-ai-features', 'enqueued' ) ) {
	wp_enqueue_script( 'wpforo-ai-features', WPFORO_URL . '/admin/assets/js/ai-features.js', [ 'jquery', 'suggest' ], WPFORO_VERSION, true );
	wp_localize_script( 'wpforo-ai-features', 'wpforoAIAdmin', [
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'wpforo_ai_features_nonce' ),
		'adminNonce' => wp_create_nonce( 'wpforo_admin_ajax' ),
		'debugMode'  => (bool) wpforo_setting( 'general', 'debug_mode' ),
	] );
}
?>

    <input type="hidden" name="wpfaction[]" value="ai_settings_save">

<?php
WPF()->settings->header( 'ai' );
?>

<!-- Collapse/Expand All Buttons -->
<div class="wpf-ai-section-controls" style="margin: 5px 0 5px 0; padding: 5px 0 0 0; text-align: right">
    <button type="button" class="button wpf-ai-collapse-all" style="margin-right: 8px; border: none;">
        <span class="dashicons dashicons-arrow-up-alt2" style="vertical-align: middle; margin-right: 3px;"></span>
        <?php _e( 'Collapse All', 'wpforo' ); ?>
    </button>
    <button type="button" class="button wpf-ai-expand-all" style="border: none;">
        <span class="dashicons dashicons-arrow-down-alt2" style="vertical-align: middle; margin-right: 3px;"></span>
        <?php _e( 'Expand All', 'wpforo' ); ?>
    </button>
</div>

<!-- AI Assistant Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M12 3l1.912 5.813a2 2 0 0 0 1.275 1.275L21 12l-5.813 1.912a2 2 0 0 0-1.275 1.275L12 21l-1.912-5.813a2 2 0 0 0-1.275-1.275L3 12l5.813-1.912a2 2 0 0 0 1.275-1.275L12 3z"/></svg> <?php _e( 'AI Assistant', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'assistant' );
        WPF()->settings->form_field( 'ai', 'assistant_open' );
        WPF()->settings->form_field( 'ai', 'assistant_classic_search' );
        WPF()->settings->form_field( 'ai', 'assistant_preferences' );
        WPF()->settings->form_field( 'ai', 'assistant_highlight' );
        WPF()->settings->form_field( 'ai', 'assistant_icon' );
        ?>
    </div>
</div>

<!-- AI Semantic Search Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8a2 2 0 0 0-2 2"/><path d="M11 8V6"/><path d="M11 8h2"/></svg> <?php _e( 'AI Semantic Search', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'search' );
        WPF()->settings->form_field( 'ai', 'search_quality' );
        WPF()->settings->form_field( 'ai', 'search_enhance' );
        WPF()->settings->form_field( 'ai', 'search_enhance_quality' );
        WPF()->settings->form_field( 'ai', 'search_language' );
        WPF()->settings->form_field( 'ai', 'search_max_results' );
        ?>
    </div>
</div>

<!-- AI Translation Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg> <?php _e( 'AI Translation', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'translation' );
        WPF()->settings->form_field( 'ai', 'translation_quality' );
        ?>
    </div>
</div>

<!-- AI Topic Summarization Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> <?php _e( 'AI Topic Summarization', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'topic_summary' );
        WPF()->settings->form_field( 'ai', 'topic_summary_quality' );
        WPF()->settings->form_field( 'ai', 'topic_summary_style' );
        WPF()->settings->form_field( 'ai', 'topic_summary_min_replies' );
        WPF()->settings->form_field( 'ai', 'topic_summary_language' );
        ?>
    </div>
</div>

<!-- AI Topic Suggestions Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M12 3l1.912 5.813a2 2 0 0 0 1.275 1.275L21 12l-5.813 1.912a2 2 0 0 0-1.275 1.275L12 21l-1.912-5.813a2 2 0 0 0-1.275-1.275L3 12l5.813-1.912a2 2 0 0 0 1.275-1.275L12 3z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></svg> <?php _e( 'AI Topic Suggestions', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'topic_suggestions' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_quality' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_min_words' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_max_calls' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_show_related' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_show_answer' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_max_similar' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_max_related' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_similarity' );
        WPF()->settings->form_field( 'ai', 'topic_suggestions_language' );
        ?>
    </div>
</div>

<!-- AI Content Moderation - Spam Detection Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg> <?php _e( 'AI Content Moderation - Spam Detection', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'moderation_spam' );
        WPF()->settings->form_field( 'ai', 'moderation_spam_quality' );
        WPF()->settings->form_field( 'ai', 'moderation_spam_action_detected' );
        WPF()->settings->form_field( 'ai', 'moderation_spam_action_suspected' );
        WPF()->settings->form_field( 'ai', 'moderation_spam_action_uncertain' );
        WPF()->settings->form_field( 'ai', 'moderation_spam_action_clean' );
        // Only show forum context settings if storage mode is 'cloud' (Lambda can access S3 Vectors)
        $storage_mode = WPF()->vector_storage ? WPF()->vector_storage->get_storage_mode() : 'local';
        if ( $storage_mode === 'cloud' ) {
            WPF()->settings->form_field( 'ai', 'moderation_spam_use_context' );
            WPF()->settings->form_field( 'ai', 'moderation_spam_min_indexed' );
        }

        // Custom display for usergroups with "Dashboard - Moderate Topics & Posts" permission
        $aup_groupids = array_map( 'intval', WPF()->usergroup->get_groupids_by_can( 'aum' ) );
        $usergroups = WPF()->usergroup->get_usergroups( 'full' );
        $aup_groups = [];
        foreach( $usergroups as $group ) {
            if( in_array( (int) $group['groupid'], $aup_groupids, true ) ) {
                $aup_groups[] = $group;
            }
        }
        ?>
        <div class="wpf-opt-row" data-wpf-opt="moderation_spam_exempt_usergroups">
            <div class="wpf-opt-name">
                <label><?php _e( 'Usergroups Not Scanned by AI Antispam', 'wpforo' ); ?></label>
                <p class="wpf-desc"><?php _e( 'Usergroups with the "Dashboard - Moderate Topics & Posts" permission enabled will skip AI spam detection. Edit usergroup permissions to enable or disable this for each group. The "Front - Can pass moderation" permission is only bypass the default "unapproved" status and the post is being scanned by spam filters.', 'wpforo' ); ?></p>
            </div>
            <div class="wpf-opt-input">
                <?php if( ! empty( $aup_groups ) ) : ?>
                    <ul style="margin: 0; padding-left: 0; list-style-type: none; font-size: 14px;">
                        <?php foreach( $aup_groups as $group ) :
                            $edit_url = admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'usergroups' ) . '&groupid=' . intval( $group['groupid'] ) . '&wpfaction=wpforo_usergroup_save_form' );
                        ?>
                            <li style="margin-bottom: 4px;">» <a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $group['name'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <em style="color: #999;"><?php _e( 'No usergroups have "Dashboard - Moderate Topics & Posts" enabled.', 'wpforo' ); ?></em>
                <?php endif; ?>
            </div>
            <div class="wpf-opt-doc">&nbsp;</div>
        </div>
        <?php
        WPF()->settings->form_field( 'ai', 'moderation_spam_exempt_minposts' );
        WPF()->settings->form_field( 'ai', 'moderation_spam_autoban_unapproved' );
        ?>
    </div>
</div>

<!-- AI Content Moderation - Toxicity Detection Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg> <?php _e( 'AI Content Moderation - Content Safety & Toxicity Detection', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'moderation_toxicity' );
        WPF()->settings->form_field( 'ai', 'moderation_toxicity_sensitivity' );
        WPF()->settings->form_field( 'ai', 'moderation_toxicity_action' );
        ?>
    </div>
</div>

<!-- AI Content Moderation - Rule Compliance Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg> <?php wpforo_phrase( 'AI Content Moderation - Rule Compliance & Policy Enforcement' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php
        WPF()->settings->form_field( 'ai', 'moderation_compliance' );

        // Get built-in policy and rules status
        $legal_settings = WPF()->settings->legal;
        $policy_enabled = ! empty( $legal_settings['checkbox_forum_privacy'] );
        $policy_text = trim( $legal_settings['forum_privacy_text'] ?? '' );
        $policy_word_count = $policy_enabled && $policy_text ? str_word_count( strip_tags( $policy_text ) ) : 0;

        $rules_enabled = ! empty( $legal_settings['rules_checkbox'] );
        $rules_text = trim( $legal_settings['rules_text'] ?? '' );
        $rules_word_count = $rules_enabled && $rules_text ? str_word_count( strip_tags( $rules_text ) ) : 0;

        // Get all pages for custom policy/rules dropdowns
        $pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );
        $current_custom_policy = intval( wpforo_setting( 'ai', 'moderation_compliance_custom_policy' ) );
        $current_custom_rules = intval( wpforo_setting( 'ai', 'moderation_compliance_custom_rules' ) );
        ?>

        <!-- Built-in Forum Policy & Rules Status -->
        <div class="wpf-opt-row" data-wpf-opt="moderation_compliance_builtin_status">
            <div class="wpf-opt-name">
                <label><?php wpforo_phrase( 'Built-in Forum Policy & Rules' ); ?></label>
                <p class="wpf-desc"><?php wpforo_phrase( 'Status of the built-in wpForo forum policy and rules. These are configured in Settings > Legal & Privacy.' ); ?></p>
            </div>
            <div class="wpf-opt-input">
                <table style="border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 4px 15px 4px 0; color: #666;"><?php wpforo_phrase( 'Forum Privacy Policy' ); ?>:</td>
                        <td style="padding: 4px 0;">
                            <?php if ( $policy_enabled && $policy_word_count > 0 ) : ?>
                                <span style="color: #46b450;">✓ <?php wpforo_phrase( 'Enabled' ); ?></span>
                                <span style="color: #999; margin-left: 8px;">(<?php echo esc_html( sprintf( wpforo_phrase( '%d words', false ), $policy_word_count ) ); ?>)</span>
                            <?php else : ?>
                                <span style="color: #999;">✗ <?php wpforo_phrase( 'Disabled' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 15px 4px 0; color: #666;"><?php wpforo_phrase( 'Forum Rules' ); ?>:</td>
                        <td style="padding: 4px 0;">
                            <?php if ( $rules_enabled && $rules_word_count > 0 ) : ?>
                                <span style="color: #46b450;">✓ <?php wpforo_phrase( 'Enabled' ); ?></span>
                                <span style="color: #999; margin-left: 8px;">(<?php echo esc_html( sprintf( wpforo_phrase( '%d words', false ), $rules_word_count ) ); ?>)</span>
                            <?php else : ?>
                                <span style="color: #999;">✗ <?php wpforo_phrase( 'Disabled' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php if ( ! $policy_enabled && ! $rules_enabled ) : ?>
                    <p style="margin-top: 10px; color: #d63638; font-size: 13px;">
                        <span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: text-bottom;"></span>
                        <?php wpforo_phrase( 'No built-in policy or rules configured. Enable them in Settings > Legal & Privacy, or select custom pages below.' ); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="wpf-opt-doc">&nbsp;</div>
        </div>

        <!-- Custom Policy Page Dropdown -->
        <div class="wpf-opt-row wpf-opt-row-select" data-wpf-opt="moderation_compliance_custom_policy">
            <div class="wpf-opt-name">
                <label for="ai-moderation_compliance_custom_policy"><?php wpforo_phrase( 'Custom Policy Page' ); ?></label>
                <p class="wpf-desc"><?php wpforo_phrase( 'Optional: Select a WordPress page containing additional terms or policies. This will be used alongside the built-in forum policy.' ); ?></p>
            </div>
            <div class="wpf-opt-input">
                <div class="wpf-switch-field">
                    <select id="ai-moderation_compliance_custom_policy" name="ai[moderation_compliance_custom_policy]">
                        <option value="0"><?php wpforo_phrase( '— Not Selected —' ); ?></option>
                        <?php foreach ( $pages as $page ) : ?>
                            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $current_custom_policy, $page->ID ); ?>>
                                <?php echo esc_html( $page->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="wpf-opt-doc">&nbsp;</div>
        </div>

        <!-- Custom Rules Page Dropdown -->
        <div class="wpf-opt-row wpf-opt-row-select" data-wpf-opt="moderation_compliance_custom_rules">
            <div class="wpf-opt-name">
                <label for="ai-moderation_compliance_custom_rules"><?php wpforo_phrase( 'Custom Rules Page' ); ?></label>
                <p class="wpf-desc"><?php wpforo_phrase( 'Optional: Select a WordPress page containing additional forum rules. This will be used alongside the built-in forum rules.' ); ?></p>
            </div>
            <div class="wpf-opt-input">
                <div class="wpf-switch-field">
                    <select id="ai-moderation_compliance_custom_rules" name="ai[moderation_compliance_custom_rules]">
                        <option value="0"><?php wpforo_phrase( '— Not Selected —' ); ?></option>
                        <?php foreach ( $pages as $page ) : ?>
                            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $current_custom_rules, $page->ID ); ?>>
                                <?php echo esc_html( $page->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="wpf-opt-doc">&nbsp;</div>
        </div>

        <?php WPF()->settings->form_field( 'ai', 'moderation_compliance_action' ); ?>
    </div>
</div>

<?php
// AI Bot Reply requires Professional plan or higher
$wpf_ai_bot_reply_available = isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'ai_bot_reply' );
?>

<!-- AI Bot Reply Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><rect width="18" height="10" x="3" y="11" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><path d="M8 16h0"/><path d="M16 16h0"/><path d="m2 2 2 2"/><path d="m22 2-2 2"/></svg> <?php _e( 'AI Bot Reply', 'wpforo' ) ?>
        <?php if ( ! $wpf_ai_bot_reply_available ) : ?>
            <span class="wpf-plan-badge wpf-plan-professional" style="margin-left: 8px; font-size: 11px; padding: 2px 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 10px; font-weight: 500;"><?php _e( 'Professional', 'wpforo' ); ?></span>
        <?php endif; ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php if ( $wpf_ai_bot_reply_available ) : ?>
            <?php
            WPF()->settings->form_field( 'ai', 'bot_reply' );
            WPF()->settings->form_field( 'ai', 'bot_reply_quality' );
            WPF()->settings->form_field( 'ai', 'bot_reply_style' );
            WPF()->settings->form_field( 'ai', 'bot_reply_tone' );
            WPF()->settings->form_field( 'ai', 'bot_reply_length' );
            WPF()->settings->form_field( 'ai', 'bot_reply_knowledge_source' );
            WPF()->settings->form_field( 'ai', 'bot_reply_includes' );
            WPF()->settings->form_field( 'ai', 'bot_reply_language' );
            ?>

            <div class="wpf-opt-row wpf-opt-subheader">
                <div class="wpf-opt-name">
                    <label style="font-weight: 600; color: #1d2327;"><?php _e( 'Bot Settings', 'wpforo' ); ?></label>
                </div>
            </div>

            <?php WPF()->settings->form_field( 'ai', 'bot_reply_unapproved' ); ?>

            <!-- Bot Reply User - Custom autocomplete search field -->
            <div class="wpf-opt-row">
                <div class="wpf-opt-name">
                    <label for="wpforo-ai-bot-user-search"><?php _e( 'Bot Reply User', 'wpforo' ); ?></label>
                </div>
                <div class="wpf-opt-input">
                    <div class="wpforo-ai-user-search-wrapper" style="max-width: 400px;">
                        <?php
                        $bot_user_id = WPF()->settings->ai['bot_reply_user_id'];
                        $bot_user_label = '';
                        if ( $bot_user_id ) {
                            $user = get_userdata( $bot_user_id );
                            if ( $user ) {
                                $role = ! empty( $user->roles ) ? ucfirst( $user->roles[0] ) : '';
                                $bot_user_label = sprintf(
                                    '%s (%s)%s',
                                    $user->display_name,
                                    $user->user_login,
                                    $role ? ' - ' . $role : ''
                                );
                            }
                        }
                        ?>
                        <input type="text"
                               id="wpforo-ai-bot-user-search"
                               class="wpforo-ai-user-search"
                               placeholder="<?php esc_attr_e( 'Type to search users...', 'wpforo' ); ?>"
                               value="<?php echo esc_attr( $bot_user_label ); ?>"
                               autocomplete="off" />
                        <input type="hidden"
                               name="ai[bot_reply_user_id]"
                               class="wpforo-ai-user-id-input"
                               value="<?php echo esc_attr( $bot_user_id ); ?>" />
                        <div class="wpforo-ai-user-search-results"></div>
                    </div>
                    <?php wp_nonce_field( 'wpforo_ai_bot_user_search', 'wpforo_ai_bot_user_nonce' ); ?>
                    <p class="wpf-info"><?php _e( 'Select the WordPress user account that will be used for bot-generated replies. Create a dedicated user account for the bot.', 'wpforo' ); ?></p>
                </div>
                <div class="wpf-opt-doc">&nbsp;</div>
            </div>

            <?php
            WPF()->settings->form_field( 'ai', 'bot_reply_max_per_topic' );
            WPF()->settings->form_field( 'ai', 'bot_reply_max_per_day' );
            ?>
        <?php else : ?>
            <div class="wpf-opt-row">
                <div class="wpf-opt-name">
                    <label><?php _e( 'Upgrade Required', 'wpforo' ); ?></label>
                </div>
                <div class="wpf-opt-input">
                    <p style="color: #666; margin: 0;">
                        <?php _e( 'AI Bot Reply and Reply Suggestion is available on Professional plan and higher.', 'wpforo' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'ai#wpforo-ai-plans' ) ) ); ?>" style="margin-left: 5px;"><?php _e( 'View Plans', 'wpforo' ); ?> →</a>
                    </p>
                </div>
                <div class="wpf-opt-doc">&nbsp;</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// AI Chat Assistant requires Business plan or higher
$wpf_ai_chatbot_available = isset( WPF()->ai_client ) && WPF()->ai_client->is_feature_available( 'ai_assistant_chatbot' );
?>

<!-- AI Chat Assistant Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/></svg> <?php _e( 'AI Chat Assistant', 'wpforo' ) ?>
        <?php if ( ! $wpf_ai_chatbot_available ) : ?>
            <span class="wpf-plan-badge wpf-plan-professional" style="margin-left: 8px; font-size: 11px; padding: 2px 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 10px; font-weight: 500;"><?php _e( 'Business', 'wpforo' ); ?></span>
        <?php endif; ?>
    </div>
    <div class="wpf-ai-section-content">
        <?php if ( $wpf_ai_chatbot_available ) : ?>
            <?php
            WPF()->settings->form_field( 'ai', 'chatbot' );
            WPF()->settings->form_field( 'ai', 'chatbot_quality' );
            WPF()->settings->form_field( 'ai', 'chatbot_welcome_message' );
            WPF()->settings->form_field( 'ai', 'chatbot_no_content_message' );
            WPF()->settings->form_field( 'ai', 'chatbot_max_conversations' );
            WPF()->settings->form_field( 'ai', 'chatbot_context_update_threshold' );
            WPF()->settings->form_field( 'ai', 'chatbot_use_rag' );
            WPF()->settings->form_field( 'ai', 'chatbot_use_local_context' );
            WPF()->settings->form_field( 'ai', 'chatbot_allowed_groups' );
            WPF()->settings->form_field( 'ai', 'chatbot_language' );
            ?>
        <?php else : ?>
            <div class="wpf-opt-row">
                <div class="wpf-opt-name">
                    <label><?php _e( 'Upgrade Required', 'wpforo' ); ?></label>
                </div>
                <div class="wpf-opt-input">
                    <p style="color: #666; margin: 0;">
                        <?php _e( 'AI Chat Assistant is available on Professional plan and higher.', 'wpforo' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . wpforo_prefix_slug( 'ai#wpforo-ai-plans' ) ) ); ?>" style="margin-left: 5px;"><?php _e( 'View Plans', 'wpforo' ); ?> →</a>
                    </p>
                </div>
                <div class="wpf-opt-doc">&nbsp;</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rate Limiting Section -->
<div class="wpf-ai-collapsible-section">
    <div class="wpf-subtitle wpf-ai-section-header">
        <span class="wpf-ai-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php _e( 'Rate Limiting', 'wpforo' ) ?>
    </div>
    <div class="wpf-ai-section-content">
        <p class="wpf-info" style="margin: 10px 0 20px 0;">
            <?php _e( 'Limit how many AI requests guests and logged-in users can make per day. Moderators are exempt from these limits.', 'wpforo' ); ?>
        </p>

        <?php
        $rate_limits = wpforo_setting( 'ai', 'rate_limits' );
        $features = [
            'search'        => __( 'AI Semantic Search', 'wpforo' ),
            'translation'   => __( 'AI Translation', 'wpforo' ),
            'summarization' => __( 'AI Topic Summarization', 'wpforo' ),
            'suggestions'   => __( 'AI Topic Suggestions', 'wpforo' ),
            'chatbot'       => __( 'AI Chat Assistant', 'wpforo' ),
        ];
        ?>

        <table class="wpf-rate-limits-table" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="text-align: left; padding: 10px; font-weight: 600; border-bottom: 2px solid #e0e0e0;"><?php _e( 'Feature', 'wpforo' ); ?></th>
                    <th style="text-align: center; padding: 10px; font-weight: 600; border-bottom: 2px solid #e0e0e0; width: 150px;"><?php _e( 'Guest Limit/Day', 'wpforo' ); ?></th>
                    <th style="text-align: center; padding: 10px; font-weight: 600; border-bottom: 2px solid #e0e0e0; width: 150px;"><?php _e( 'User Limit/Day', 'wpforo' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $features as $feature_key => $feature_label ) :
                    // Skip chatbot if not available (requires Business+ plan)
                    if ( $feature_key === 'chatbot' && ! $wpf_ai_chatbot_available ) {
                        continue;
                    }
                    $guest_limit = isset( $rate_limits[$feature_key]['guest'] ) ? (int) $rate_limits[$feature_key]['guest'] : 0;
                    $user_limit = isset( $rate_limits[$feature_key]['user'] ) ? (int) $rate_limits[$feature_key]['user'] : 0;
                    $guest_disabled = ( $feature_key === 'chatbot' ); // Chatbot requires login
                ?>
                <tr style="border-bottom: 1px solid #e8e8e8;">
                    <td style="padding: 12px 10px;">
                        <strong><?php echo esc_html( $feature_label ); ?></strong>
                        <?php if ( $feature_key === 'chatbot' ) : ?>
                            <span style="color: #999; font-size: 12px; margin-left: 5px;"><?php _e( '(login required)', 'wpforo' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; padding: 10px;">
                        <?php if ( $guest_disabled ) : ?>
                            <span style="color: #999;">—</span>
                            <input type="hidden" name="ai[rate_limits][<?php echo esc_attr( $feature_key ); ?>][guest]" value="0">
                        <?php else : ?>
                            <input type="number" name="ai[rate_limits][<?php echo esc_attr( $feature_key ); ?>][guest]"
                                   value="<?php echo esc_attr( $guest_limit ); ?>"
                                   min="0" max="1000" step="1"
                                   style="width: 80px; text-align: center;">
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; padding: 10px;">
                        <input type="number" name="ai[rate_limits][<?php echo esc_attr( $feature_key ); ?>][user]"
                               value="<?php echo esc_attr( $user_limit ); ?>"
                               min="0" max="1000" step="1"
                               style="width: 80px; text-align: center;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="wpf-desc" style="color: #666; margin: 0;">
            <strong><?php _e( 'Note:', 'wpforo' ); ?></strong>
            <?php _e( 'Set to 0 to disable access for that user type. Moderators (users with "ms" permission) are always unlimited. Limits reset daily at midnight.', 'wpforo' ); ?>
        </p>
    </div>
</div>

<!-- Collapsible Sections CSS & JS -->
<style>
.wpf-ai-collapsible-section {
    margin-bottom: 0;
}
.wpf-ai-section-header {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-left: 30px !important;
    transition: background-color 0.2s ease;
}
.wpf-ai-section-header:hover {
    background-color: #f8f9fa;
}
.wpf-ai-toggle-icon {
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    color: #43A6DF;
    font-size: 18px;
    transition: transform 0.2s ease;
}
.wpf-ai-collapsible-section.collapsed .wpf-ai-toggle-icon {
    transform: translateY(-50%) rotate(-90deg);
}
.wpf-ai-section-content {
    overflow: hidden;
    transition: max-height 0.3s ease-out, opacity 0.2s ease-out;
    max-height: 5000px;
    opacity: 1;
}
.wpf-ai-collapsible-section.collapsed .wpf-ai-section-content {
    max-height: 0;
    opacity: 0;
    padding: 0;
}
.wpf-ai-section-controls .button {
    display: inline-flex;
    align-items: center;
}
.wpf-ai-section-controls .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle individual section
    $('.wpf-ai-section-header').on('click', function(e) {
        // Don't toggle if clicking on a link or badge inside the header
        if ($(e.target).is('a, .wpf-plan-badge')) {
            return;
        }
        $(this).closest('.wpf-ai-collapsible-section').toggleClass('collapsed');
    });

    // Collapse All button
    $('.wpf-ai-collapse-all').on('click', function() {
        $('.wpf-ai-collapsible-section').addClass('collapsed');
    });

    // Expand All button
    $('.wpf-ai-expand-all').on('click', function() {
        $('.wpf-ai-collapsible-section').removeClass('collapsed');
    });
});
</script>
