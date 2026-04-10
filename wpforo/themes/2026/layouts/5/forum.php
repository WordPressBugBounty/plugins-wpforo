<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Layout: Boxed
 * Version: 1.0.0
 * Author: wpForo Team
 * Description: Modern card-based layout with cover images and clean stats display.
 */

$cover_styles = wpforo_get_forum_cover_styles( $cat );
?>

<div class="wpfl-5 wpforo-section">
    <!-- wpforo-category -->
    <div class="wpforo-category" <?php echo $cover_styles['cover'] ?>>
        <div class="wpforo-cat-panel" <?php echo $cover_styles['blur'] ?>>
            <div class="cat-title" title="<?php echo esc_attr( strip_tags( $cat['description'] ) ); ?>">
                <span class="cat-name" <?php echo $cover_styles['title'] ?>><?php echo esc_html( $cat['title'] ); ?></span>
            </div>
            <?php if( WPF()->current_object['template'] === 'forum' ) wpforo_template_add_topic_button( $cat['forumid'] ); ?>
        </div>
    </div>
    <!-- wpforo-category -->

    <?php if( WPF()->current_object['template'] === 'forum' ) wpforo_template_topic_portable_form( $cat['forumid'] ); ?>

    <?php
    $forum_list = false;
    ?>

    <!-- Forums Grid (Only Child Forums, Not Categories) -->
    <div class="wpforo-forum-grid">
        <?php foreach( $forums as $key => $forum ) :
            if( ! WPF()->perm->forum_can( 'vf', $forum['forumid'] ) ) continue;

            $forum_list = true;

            // Get forum data with full details (including cover_url)
            $forum_full = wpforo_forum( $forum['forumid'] );
            $data   = wpforo_forum( $forum['forumid'], 'childs' );
            $counts = wpforo_forum( $forum['forumid'], 'counts' );
            $forum_url = wpforo_forum( $forum['forumid'], 'url' );

            // Get cover image URL (wpForo provides this automatically)
            $cover_image_url = ! empty( $forum_full['cover_url'] ) ? $forum_full['cover_url'] : '';

            // Get icon
            if( ! empty( $forum['icon'] ) ) {
                $forum['icon'] = trim( (string) $forum['icon'] );
                if( strpos( (string) $forum['icon'], ' ' ) === false ) $forum['icon'] = 'fas ' . $forum['icon'];
            }
            $forum_icon = ( ! empty( $forum['icon'] ) ) ? $forum['icon'] : 'fas fa-comments';

            // Get recent active users for avatar display (top 3)
            $recent_users = [];
            $recent_topics = WPF()->topic->get_topics( [
                'forumids' => $data,
                'orderby' => 'modified',
                'order' => 'DESC',
                'row_count' => 3
            ] );
            if( ! empty( $recent_topics ) ) {
                $user_ids = [];
                foreach( $recent_topics as $topic ) {
                    if( ! in_array( $topic['userid'], $user_ids ) ) {
                        if( $user = wpforo_member( $topic['userid'] ) ) {
                            $recent_users[] = $user;
                            $user_ids[] = $topic['userid'];
                            if( count( $recent_users ) >= 3 ) break;
                        }
                    }
                }
            }

            // Get subforums
            $sub_forums = WPF()->forum->get_forums( [ "parentid" => $forum['forumid'], "type" => 'forum' ] );
            $has_sub_forums = is_array( $sub_forums ) && ! empty( $sub_forums );
            ?>
            <div id="wpf-forum-<?php echo intval( $forum['forumid'] ) ?>" class="wpforo-forum-card <?php wpforo_unread( $forum['forumid'], 'forum' ) ?>">

                <!-- Card Cover with Stats Overlay (Clickable) -->
                <a href="<?php echo esc_url( (string) $forum_url ); ?>" class="forum-card-cover-link">
                <div class="forum-card-cover" <?php if( $cover_image_url ): ?>style="background-image: url('<?php echo esc_url( $cover_image_url ); ?>');"<?php else: ?>style="background: linear-gradient(135deg, <?php echo esc_attr( $forum['color'] ); ?>55 0%, <?php echo esc_attr( $forum['color'] ); ?>99 100%);"<?php endif; ?>>

                    <!-- Stats and Avatars Overlay -->
                    <div class="forum-card-overlay">
                        <div class="forum-stats-overlay">
                            <div class="stat-item">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: #fff;  height: 18px; margin-right: 7px;">
                                    <g id="_01_align_center" data-name="01 align center">
                                        <path d="M21,0H3A3,3,0,0,0,0,3V20H6.9l3.808,3.218a2,2,0,0,0,2.582,0L17.1,20H24V3A3,3,0,0,0,21,0Zm1,18H16.366L12,21.69,7.634,18H2V3A1,1,0,0,1,3,2H21a1,1,0,0,1,1,1Z"/>
                                        <rect x="6" y="5" width="6" height="2"/>
                                        <rect x="6" y="9" width="12" height="2"/>
                                        <rect x="6" y="13" width="12" height="2"/>
                                    </g>
                                </svg>
                                <span class="stat-value"><?php echo wpforo_print_number( $counts['topics'] ); ?></span>
                            </div>
                            <div class="stat-item">
                                <svg style="fill: #fff;  height: 18px; margin-right: 7px; margin-top: 4px; transform: rotate(180deg);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M23,24a1,1,0,0,1-1-1,6.006,6.006,0,0,0-6-6H10.17v1.586A2,2,0,0,1,6.756,20L.877,14.121a3,3,0,0,1,0-4.242L6.756,4A2,2,0,0,1,10.17,5.414V7H15a9.01,9.01,0,0,1,9,9v7A1,1,0,0,1,23,24ZM8.17,5.414,2.291,11.293a1,1,0,0,0,0,1.414L8.17,18.586V16a1,1,0,0,1,1-1H16a7.984,7.984,0,0,1,6,2.714V16a7.008,7.008,0,0,0-7-7H9.17a1,1,0,0,1-1-1Z"/>
                                </svg>
                                <span class="stat-value"><?php echo wpforo_print_number( $counts['posts'] ); ?></span>
                            </div>
                        </div>

                        <?php if( ! empty( $recent_users ) ): ?>
                            <div class="forum-users-overlay">
                                <?php foreach( $recent_users as $user ): ?>
                                    <div class="user-avatar-mini">
                                        <?php echo wpforo_user_avatar( $user, 32 ); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </a>

                <!-- Card Info (Icon + Title + Description) -->
                <div class="forum-card-info">
                    <!--
                        <div class="forum-card-icon-box">
                            <i class="<?php echo esc_attr( $forum_icon ); ?>" style="color: <?php echo esc_attr( $forum['color'] ); ?>;"></i>
                        </div>
                    -->
                    <div class="forum-card-text">
                        <h3 class="forum-card-title">
                            <a href="<?php echo esc_url( (string) $forum_url ); ?>"><?php echo esc_html( $forum['title'] ); ?></a>
                            <?php wpforo_viewing( $forum ); ?>
                        </h3>
                        <?php if( ! empty( $forum['description'] ) ): ?>
                            <div class="forum-card-description"><?php echo wp_kses_post( wpforo_text( $forum['description'], 100, false ) ); ?></div>
                        <?php endif; ?>

                        <?php if( $has_sub_forums ): ?>
                            <div class="forum-card-subforums">
                                <?php
                                $subforum_links = [];
                                foreach( $sub_forums as $sub_forum ):
                                    if( ! WPF()->perm->forum_can( 'vf', $sub_forum['forumid'] ) ) continue;
                                    $subforum_links[] = '<a href="' . esc_url( (string) wpforo_forum( $sub_forum['forumid'], 'url' ) ) . '" style="border-bottom: 1px solid ' . esc_attr( $sub_forum['color'] ) . ';">' . esc_html( $sub_forum['title'] ) . '</a>';
                                endforeach;
                                echo implode( ' · ', $subforum_links );
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- wpforo-forum-card -->

            <?php do_action( 'wpforo_loop_hook', $key, $forum ) ?>

        <?php endforeach; ?> <!-- $forums as $forum -->
    </div><!-- wpforo-forum-grid -->

    <?php if( ! $forum_list ): ?>
        <?php do_action( 'wpforo_forum_loop_no_forums', $cat ); ?>
    <?php endif; ?>

</div><!-- wpfl-5 -->
