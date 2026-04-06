<div class="wpforo-ai-helper<?php echo $wpf_ai_assistant_open ? ' wpf-ai-open' : ''; ?>" data-ai-preferences="<?php echo esc_attr( wp_json_encode( $wpf_ai_preferences ) ); ?>">
    <div class="wpf-ai-helper-content"<?php echo $wpf_ai_assistant_open ? ' style="display: block;"' : ''; ?>>
        <div class="wpf-ai-helper-inner">
            <!-- Tabs Navigation -->
            <?php if ( $wpf_ai_chatbot_enabled || ( $wpf_ai_show_preferences && $wpf_ai_is_logged_in ) ) : ?>
            <div class="wpf-ai-tabs">
                <?php if ( $wpf_ai_search_enabled ) : ?>
                    <div class="wpf-ai-tab wpf-ai-tab-active" data-tab="ai-search">
                        <i class="fas fa-search"></i>
                        <span><?php wpforo_phrase('Search') ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( $wpf_ai_chatbot_enabled ) : ?>
                    <div class="wpf-ai-tab<?php echo ! $wpf_ai_search_enabled ? ' wpf-ai-tab-active' : ''; ?>" data-tab="ai-chat">
                        <i class="fas fa-comments"></i>
                        <span><?php wpforo_phrase('Chat') ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( $wpf_ai_show_preferences && $wpf_ai_is_logged_in ) : ?>
                    <div class="wpf-ai-tab<?php echo ! $wpf_ai_search_enabled && ! $wpf_ai_chatbot_enabled ? ' wpf-ai-tab-active' : ''; ?>" data-tab="preferences">
                        <i class="fas fa-cog"></i>
                        <span><?php wpforo_phrase('Preferences') ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( ! $wpf_ai_assistant_open ) : ?>
                <div class="wpf-ai-tab-close">
                    <i class="fas fa-times"></i>
                    <span><?php wpforo_phrase('Close') ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tab Content: AI Search -->
            <?php if ( $wpf_ai_search_enabled ) : ?>
                <div class="wpf-ai-tab-content wpf-ai-tab-content-active" data-tab-content="ai-search">
                    <!-- Search Mode Toggle -->
                    <?php if ( $wpf_ai_classic_search ) : ?>
                    <div class="wpf-ai-search-toggle">
                        <div class="wpf-ai-search-mode wpf-ai-search-mode-active" data-mode="ai">
                            <?php wpforo_phrase('AI Search') ?>
                        </div>
                        <div class="wpf-ai-search-mode" data-mode="classic">
                            <?php wpforo_phrase('Classic Search') ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- AI Search Form -->
                    <div class="wpf-ai-search-form wpf-ai-search-form-active" data-search-form="ai">
                        <form class="wpf-ai-form" method="post">
                            <div class="wpf-ai-input-wrap">
                                <div class="wpf-ai-input-icon wpf-ai-icon-left">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="55px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 0 0 1.275 1.275L21 12l-5.813 1.912a2 2 0 0 0-1.275 1.275L12 21l-1.912-5.813a2 2 0 0 0-1.275-1.275L3 12l5.813-1.912a2 2 0 0 0 1.275-1.275L12 3z"></path><path d="M5 3v4"></path><path d="M3 5h4"></path><path d="M19 17v4"></path><path d="M17 19h4"></path></svg>
                                </div>
                                <input type="text" name="wpf_ai_query" class="wpf-ai-input" placeholder="<?php wpforo_phrase('Describe what you\'re looking for...') ?>" autocomplete="off" />
                                <button type="submit" class="wpf-ai-submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        <!-- AI Search Results -->
                        <div class="wpf-ai-results" style="display: none;">
                            <div class="wpf-ai-results-list"></div>
                            <div class="wpf-ai-results-more" style="display: none;">
                                <button type="button" class="wpf-ai-more-btn"><?php wpforo_phrase('More Results') ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Classic Search Form -->
                    <?php if ( $wpf_ai_classic_search ) : ?>
                    <div class="wpf-ai-search-form" data-search-form="classic">
                        <?php wpforo_post_search_form( WPF()->current_object['args'] ?? [] ) ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Tab Content: AI Chat -->
            <?php if ( $wpf_ai_chatbot_enabled ) : ?>
                <div class="wpf-ai-tab-content<?php echo ! $wpf_ai_search_enabled ? ' wpf-ai-tab-content-active' : ''; ?>" data-tab-content="ai-chat">
                    <div class="wpf-ai-chat" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpforo_ai_chatbot' ) ); ?>">
                        <!-- Conversations Sidebar -->
                        <div class="wpf-ai-chat-sidebar">
                            <div class="wpf-ai-chat-sidebar-header">
                                <h4><?php wpforo_phrase('Conversations') ?></h4>
                                <button type="button" class="wpf-ai-chat-new" title="<?php wpforo_phrase('New Conversation') ?>">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="wpf-ai-chat-conversations">
                                <!-- Conversations list will be loaded here -->
                                <div class="wpf-ai-chat-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                        </div>
                        <!-- Chat Main Area -->
                        <div class="wpf-ai-chat-main">
                            <div class="wpf-ai-chat-messages">
                                <!-- Messages will be loaded here -->
                                <div class="wpf-ai-chat-welcome">
                                    <div class="wpf-ai-chat-welcome-icon">
                                        <svg viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                                            <path d="M9.5 2l1.5 4.5L15.5 8l-4.5 1.5L9.5 14l-1.5-4.5L3.5 8l4.5-1.5L9.5 2z"/>
                                            <path d="M18 12l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/>
                                        </svg>
                                    </div>
                                    <p class="wpf-ai-chat-welcome-text"><?php wpforo_phrase('Start a new conversation or select an existing one') ?></p>
                                </div>
                            </div>
                            <div class="wpf-ai-chat-input-wrap" style="display: none;">
                                <form class="wpf-ai-chat-form" method="post">
                                    <input type="hidden" name="conversation_id" value="" />
                                    <div class="wpf-ai-chat-input-container">
                                        <textarea name="message" class="wpf-ai-chat-input" placeholder="<?php wpforo_phrase('Type your message...') ?>" rows="1"></textarea>
                                        <button type="submit" class="wpf-ai-chat-send" disabled>
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tab Content: Preferences -->
            <?php if ( $wpf_ai_show_preferences && $wpf_ai_is_logged_in ) : ?>
                <div class="wpf-ai-tab-content<?php echo ! $wpf_ai_search_enabled && ! $wpf_ai_chatbot_enabled ? ' wpf-ai-tab-content-active' : ''; ?>" data-tab-content="preferences">
                    <div class="wpf-ai-preferences">
                        <form class="wpf-ai-preferences-form" method="post">
                            <?php wp_nonce_field( 'wpforo_ai_preferences', 'wpf_ai_pref_nonce' ); ?>

                            <div class="wpf-ai-pref-section">
                                <h4 class="wpf-ai-pref-title"><?php wpforo_phrase('AI Features Preferences') ?></h4>

                                <div class="wpf-ai-pref-field">
                                    <label for="wpf-ai-pref-language"><?php wpforo_phrase('AI Features Response Language') ?></label>
                                    <select id="wpf-ai-pref-language" name="wpf_ai_language" class="wpf-ai-pref-select">
                                        <?php
                                        $current_lang = $wpf_ai_preferences['language'];
                                        foreach ( $wpf_ai_languages as $code => $name ) :
                                            $selected = ( $current_lang === $code ) ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo esc_attr( $code ); ?>" <?php echo $selected; ?>><?php echo esc_html( $name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="wpf-ai-pref-desc"><?php wpforo_phrase('AI will generate responses in this language') ?></p>
                                </div>

                                <div class="wpf-ai-pref-field">
                                    <label for="wpf-ai-pref-max-results"><?php wpforo_phrase('Maximum Number of Search Results') ?></label>
                                    <input type="number" id="wpf-ai-pref-max-results" name="wpf_ai_max_results" class="wpf-ai-pref-input" value="<?php echo esc_attr( $wpf_ai_preferences['max_results'] ); ?>" min="1" max="10" />
                                    <p class="wpf-ai-pref-desc"><?php wpforo_phrase('Number of search results to display (1-10)') ?></p>
                                </div>
                            </div>

                            <div class="wpf-ai-pref-actions">
                                <button type="submit" class="wpf-ai-pref-save"><?php wpforo_phrase('Save Preferences') ?></button>
                                <span class="wpf-ai-pref-status"></span>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="wpf-ai-helper-bar">
        <div class="wpf-ai-helper-toggle<?php echo ( $wpf_ai_assistant_highlight && ! $wpf_ai_assistant_open ) ? ' wpf-ai-highlight' : ''; ?>">
            <?php if ( ! empty( $wpf_ai_assistant_icon ) ) : ?>
                <?php
                $wpf_ai_icon_clean = preg_replace( '/\s*(width|height)\s*=\s*"[^"]*"/i', '', $wpf_ai_assistant_icon );
                $wpf_ai_icon_clean = preg_replace( '/<svg\b/i', '<svg class="wpf-ai-sparkle-icon"', $wpf_ai_icon_clean );
                echo wp_kses( $wpf_ai_icon_clean, array( 'svg' => array( 'class' => true, 'viewbox' => true, 'fill' => true, 'xmlns' => true ), 'path' => array( 'd' => true, 'fill' => true ), 'circle' => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ), 'rect' => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true ), 'line' => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ), 'polyline' => array( 'points' => true, 'fill' => true ), 'polygon' => array( 'points' => true, 'fill' => true ), 'g' => array( 'fill' => true, 'transform' => true ) ) );
                ?>
            <?php else : ?>
                <svg class="wpf-ai-sparkle-icon" xmlns="http://www.w3.org/2000/svg" height="55px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 0 0 1.275 1.275L21 12l-5.813 1.912a2 2 0 0 0-1.275 1.275L12 21l-1.912-5.813a2 2 0 0 0-1.275-1.275L3 12l5.813-1.912a2 2 0 0 0 1.275-1.275L12 3z"></path><path d="M5 3v4"></path><path d="M3 5h4"></path><path d="M19 17v4"></path><path d="M17 19h4"></path></svg>
            <?php endif; ?>
            <span><?php wpforo_phrase('AI Assistant') ?></span>
            <i class="fas fa-chevron-down wpf-ai-toggle-icon"></i>
        </div>
    </div>
</div>
