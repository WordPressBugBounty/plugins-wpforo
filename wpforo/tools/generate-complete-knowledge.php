#!/usr/bin/env php
<?php
/**
 * wpForo Complete Knowledge Generator
 *
 * Generates comprehensive expert knowledge including:
 * 1. All settings with behavioral effects
 * 2. Feature documentation with how things work
 * 3. User scenarios and troubleshooting
 * 4. Interconnections between settings
 * 5. Incremental update support
 *
 * Usage: php generate-complete-knowledge.php [output-dir]
 */

$WPFORO_DIR = dirname(__DIR__);
$OUTPUT_DIR = $argv[1] ?? $WPFORO_DIR . '/docs/expert-knowledge';

if (!is_dir($OUTPUT_DIR)) mkdir($OUTPUT_DIR, 0755, true);
if (!is_dir("$OUTPUT_DIR/settings")) mkdir("$OUTPUT_DIR/settings", 0755, true);
if (!is_dir("$OUTPUT_DIR/features")) mkdir("$OUTPUT_DIR/features", 0755, true);
if (!is_dir("$OUTPUT_DIR/troubleshooting")) mkdir("$OUTPUT_DIR/troubleshooting", 0755, true);
if (!is_dir("$OUTPUT_DIR/scenarios")) mkdir("$OUTPUT_DIR/scenarios", 0755, true);
if (!is_dir("$OUTPUT_DIR/for-indexing")) mkdir("$OUTPUT_DIR/for-indexing", 0755, true);

echo "wpForo Complete Knowledge Generator\n";
echo "====================================\n\n";

// ============================================================================
// PART 1: EXTRACT ALL SETTINGS WITH BEHAVIORAL CONTEXT
// ============================================================================

echo "Extracting settings with behavioral context...\n";

function extractSettings($wpforoDir) {
    $settingsFile = "$wpforoDir/classes/Settings.php";
    $content = file_get_contents($settingsFile);

    $settings = [];
    $currentCategory = '';

    // Find all setting categories
    preg_match_all('/\'(\w+)\'\s*=>\s*\[\s*"title"\s*=>\s*[^,]+,/', $content, $categories);

    // Extract individual settings with their metadata
    preg_match_all('/
        "([a-z_]+)"\s*=>\s*\[\s*
        "type"\s*=>\s*"(\w+)"[^]]*
        "label"\s*=>\s*(?:esc_html__\(\s*"([^"]+)"|"([^"]+)")[^]]*
        (?:"description"\s*=>\s*(?:esc_html__\(\s*"([^"]*)"|"([^"]*)")[^]]*)?
        (?:"description_original"\s*=>\s*"([^"]*)")[^]]*
        (?:"docurl"\s*=>\s*"([^"]*)"|)
    /xms', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $name = $match[1];
        $type = $match[2];
        $label = $match[3] ?: ($match[4] ?? '');
        $description = $match[5] ?: ($match[6] ?? ($match[7] ?? ''));
        $docurl = $match[8] ?? '';

        $settings[$name] = [
            'name' => $name,
            'type' => $type,
            'label' => $label,
            'description' => $description,
            'docurl' => $docurl,
        ];
    }

    return $settings;
}

$settings = extractSettings($WPFORO_DIR);
echo "  Found " . count($settings) . " settings\n";

// ============================================================================
// PART 2: DEFINE BEHAVIORAL KNOWLEDGE (Manual Expert Content)
// ============================================================================

// This is the BEHAVIORAL knowledge that code analysis cannot capture
// This should be maintained by support team based on user questions

$behavioralKnowledge = [
    // ========== MEMBERS & PROFILES ==========
    'member_threshold_posts' => [
        'category' => 'members',
        'setting' => 'threshold_posts',
        'behavior' => 'Controls how many approved posts a user needs before being considered a "trusted" member. Until this threshold is reached, users are treated as "new users" with restricted privileges.',
        'affects' => [
            'Profile editing access',
            'Signature display',
            'Avatar upload',
            'Link posting in content',
            'Auto-moderation bypass',
        ],
        'common_issues' => [
            'User cannot edit profile' => 'User has fewer approved posts than threshold_posts setting',
            'Signature not showing' => 'User needs more approved posts OR signature feature disabled',
            'Links being stripped' => 'User is "new" and link stripping is enabled for new users',
        ],
        'related_settings' => ['new_user_max_posts', 'link_stripping_newuser'],
        'admin_path' => 'Dashboard > wpForo > Settings > Members > General',
    ],

    'new_user_unapprove' => [
        'category' => 'members',
        'setting' => 'new_user_unapprove',
        'behavior' => 'When enabled, posts from "new users" (those below threshold_posts) automatically go to moderation queue instead of being published immediately.',
        'affects' => [
            'Topic and reply publishing',
            'Moderation queue volume',
            'User experience for new members',
        ],
        'common_issues' => [
            'New user post not visible' => 'Post is in moderation queue waiting for approval',
            'Too many posts in moderation' => 'Consider increasing threshold or disabling for verified emails',
        ],
        'related_settings' => ['threshold_posts', 'new_user_unapprove_if_link'],
        'admin_path' => 'Dashboard > wpForo > Settings > Members > General',
    ],

    'can_edit_profile' => [
        'category' => 'members',
        'setting' => 'can_edit_profile',
        'behavior' => 'Controls whether users can edit their own profile. When disabled globally, even "trusted" users cannot edit profiles.',
        'common_issues' => [
            'Edit Profile tab missing' => 'Check: 1) can_edit_profile enabled, 2) user has enough approved posts (threshold_posts), 3) usergroup has profile edit permission',
        ],
        'related_settings' => ['threshold_posts'],
        'admin_path' => 'Dashboard > wpForo > Settings > Profiles > Options',
    ],

    // ========== PERMISSIONS & ACCESS ==========
    'usergroup_permissions' => [
        'category' => 'permissions',
        'feature' => 'usergroup_system',
        'behavior' => 'wpForo uses its own permission system separate from WordPress roles. Each usergroup has forum-specific permissions (can view forum, can create topic, can reply, etc.).',
        'key_concepts' => [
            'Usergroups' => 'Define what users CAN do (capabilities)',
            'Forum Access' => 'Define WHERE users can do things (per-forum)',
            'Permission Codes' => 'vf=view forum, vt=view topics, ct=create topic, cr=create reply, et=edit topic, er=edit reply, l=like, s=subscribe, au=auto-unapprove bypass',
        ],
        'common_issues' => [
            'User cannot post in forum' => 'Check usergroup has "ct" or "cr" permission for that forum',
            'User cannot see forum' => 'Check usergroup has "vf" permission for that forum',
            'Private forum visible to wrong users' => 'Check Forum Access settings in Forum edit page',
        ],
        'admin_path' => 'Dashboard > wpForo > Usergroups (for capabilities) AND Forums > Edit Forum > Access (for per-forum)',
    ],

    // ========== TOPICS & POSTS ==========
    'topic_status' => [
        'category' => 'topics',
        'feature' => 'topic_status_system',
        'behavior' => 'Topics have multiple status flags: approved/unapproved (status), open/closed, solved/unsolved, private/public. These affect visibility and interaction.',
        'status_values' => [
            'status = 0' => 'Approved - visible to all with access',
            'status = 1' => 'Unapproved - only visible to author, mods, admins',
            'closed = 1' => 'No new replies allowed',
            'private = 1' => 'Only visible to author and those with "vp" permission',
            'solved = 1' => 'Marked as answered (Q&A layout)',
        ],
        'common_issues' => [
            'Topic not showing after creation' => '1) Check status (might be unapproved), 2) Check private flag, 3) Clear cache',
            'Cannot reply to topic' => 'Topic might be closed OR user lacks "cr" permission',
        ],
    ],

    'post_moderation' => [
        'category' => 'topics',
        'feature' => 'moderation_system',
        'behavior' => 'Posts can go to moderation queue based on: new user status, link detection, spam detection, or AI moderation (if enabled). Moderators see unapproved posts with "Approve" button.',
        'triggers' => [
            'new_user_unapprove = true' => 'New users (below threshold) auto-moderated',
            'new_user_unapprove_if_link = true' => 'New user posts with links auto-moderated',
            'AI Moderation' => 'Content flagged by AI goes to moderation',
            'Spam detection' => 'Certain patterns trigger moderation',
        ],
        'admin_path' => 'Dashboard > wpForo > Moderation (to see queue)',
    ],

    // ========== SUBSCRIPTIONS & EMAIL ==========
    'email_notifications' => [
        'category' => 'email',
        'feature' => 'subscription_system',
        'behavior' => 'Users can subscribe to forums or topics to receive email notifications. Emails are sent via WordPress wp_mail() which respects SMTP plugins.',
        'subscription_types' => [
            'All Forums' => 'Email for any new topic in any forum',
            'Forum' => 'Email for new topics in specific forum',
            'Topic' => 'Email for new replies to specific topic',
        ],
        'common_issues' => [
            'Not receiving subscription emails' => '1) Check spam folder, 2) Check user subscriptions exist, 3) Check SMTP working, 4) Check async queue if enabled',
            'Duplicate emails' => 'User might be subscribed at multiple levels (forum + topic)',
            'Email delayed' => 'Async email queue enabled - check Tools > Email Queue',
        ],
        'related_settings' => ['async_notifications', 'new_topic_notify', 'new_reply_notify'],
        'admin_path' => 'Dashboard > wpForo > Settings > Emails',
    ],

    'async_notifications' => [
        'category' => 'email',
        'setting' => 'async_notifications',
        'behavior' => 'When enabled, subscription emails are queued and sent in background via WP Cron instead of immediately. Prevents slow page loads when posting to topics with many subscribers.',
        'common_issues' => [
            'Emails not sending' => 'Check Tools > Email Queue - Cron might be stalled. Emails fall back to sync if cron unhealthy.',
            'Emails delayed' => 'Normal with async - processed every 30 seconds',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Emails + Tools > Email Queue',
    ],

    // ========== SEARCH & PERFORMANCE ==========
    'search_behavior' => [
        'category' => 'search',
        'feature' => 'search_system',
        'behavior' => 'wpForo has two search modes: Legacy (MySQL LIKE/FULLTEXT) and AI Semantic Search (requires AI subscription). Legacy search is keyword-based, AI search understands meaning.',
        'modes' => [
            'Legacy Search' => 'Uses MySQL queries, exact keyword matching, fast but less accurate',
            'AI Semantic Search' => 'Uses vector embeddings, understands meaning, finds related content',
        ],
        'common_issues' => [
            'Search returns no results' => 'Check: 1) Content is indexed, 2) User has access to forums containing results, 3) Search index not corrupted',
            'Search slow' => 'Large forums may need MySQL optimization or switch to AI search',
        ],
        'related_settings' => ['search_max_results', 'ai settings'],
    ],

    // ========== LAYOUTS & DISPLAY ==========
    'forum_layouts' => [
        'category' => 'layouts',
        'feature' => 'layout_system',
        'behavior' => 'wpForo has 5 forum layouts that change how topics and posts are displayed. Layout is set per-forum, not globally. Each layout has unique template files and settings.',
        'layouts' => [
            'Extended (Layout 1)' => 'Info-rich layout with topic/post previews on forum listing. Best for general discussions. Shows recent topics expanded.',
            'Simplified (Layout 2)' => 'Clean minimal layout, compact topic list. Good for high-volume forums. Has Add Topic button toggle.',
            'Q&A (Layout 3)' => 'Question/Answer format with voting, best answer marking, and comments. Uses different terminology: Questions, Answers, Comments.',
            'Threaded (Layout 4)' => 'Nested reply tree structure like Reddit. Configurable nesting depth (default 5 levels). Shows conversation threads.',
            'Boxed (Layout 5)' => 'Modern card-based design with cover images. NEW in 2026 theme. Clean visual appearance with stats display.',
        ],
        'layout_settings' => [
            'Extended' => 'intro_topics_toggle, intro_topics_count (3), intro_topics_length (45), intro_posts_toggle, intro_posts_count (4)',
            'Simplified' => 'add_topic_button toggle',
            'Q&A' => 'posts_per_page (15), comments_limit (3), answer_editor_display, first_post_reply toggle',
            'Threaded' => 'nesting_level (5), posts_per_page (5), display_subforums, filter_buttons toggle',
            'Boxed' => 'Inherits simplified settings, uses cover images',
        ],
        'common_issues' => [
            'Wrong layout showing' => 'Check forum settings - layout is per-forum, not global. Dashboard > Forums > Edit Forum > Layout',
            'Threaded replies not nesting' => 'Check layout_threaded_nesting_level setting (Settings > Forums)',
            'Q&A voting not working' => 'Ensure layout is set to Q&A (3) and user has "v" (vote) permission',
            'Comments not showing in Q&A' => 'Check layout_qa_comments_limit_count setting',
        ],
        'admin_path' => 'Dashboard > Forums > Edit Forum > Layout dropdown',
    ],

    // ========== BOARDS (MULTI-BOARD SYSTEM) ==========
    'boards_system' => [
        'category' => 'boards',
        'feature' => 'multi_board_system',
        'behavior' => 'wpForo Boards allow multiple separate forum instances within one WordPress installation. Each board has its own forums, topics, posts, and can have separate settings. Great for multi-language forums or different communities.',
        'key_concepts' => [
            'Board' => 'A complete forum instance with its own content and URL slug',
            'Default Board (ID 0)' => 'The primary board, uses standard table names (wp_wpforo_forums)',
            'Additional Boards' => 'Use prefixed tables (wp_wpforo_1_forums, wp_wpforo_2_forums)',
            'Standalone Mode' => 'Makes one board replace the entire WordPress frontend',
        ],
        'board_specific_data' => [
            'forums, topics, posts' => 'Completely separate per board',
            'subscriptions, visits, reactions' => 'Separate per board',
            'usergroups, profiles' => 'SHARED across all boards',
            'settings' => 'Can be global or board-specific (override)',
        ],
        'common_issues' => [
            'Content not showing' => 'Make sure you are viewing the correct board - check URL slug',
            'Settings not saving for specific board' => 'Check if editing board-specific settings page (?page=wpforo-{boardid}-settings)',
            'Users missing from board' => 'User profiles are shared - check forum permissions for that board',
        ],
        'admin_path' => 'Dashboard > wpForo > Boards',
    ],

    // ========== FORUMS MANAGEMENT ==========
    'forums_management' => [
        'category' => 'forums',
        'feature' => 'forum_hierarchy',
        'behavior' => 'Forums are organized in a hierarchy with Categories (parent) and Forums (children). Each forum can have its own layout, permissions, and access settings.',
        'forum_types' => [
            'Category' => 'Container for forums, cannot have topics directly. parentid=0',
            'Forum' => 'Where topics are posted. Has parentid pointing to category',
            'Subforum' => 'Forum nested under another forum',
        ],
        'forum_properties' => [
            'layout' => 'Which of the 5 layouts to use (1-5)',
            'permissions' => 'Per-usergroup access (maps groupid to access level)',
            'status' => '0=open, 1=closed (no new topics)',
            'is_cat' => '1=category, 0=forum',
            'cover' => 'Cover image for Boxed layout',
        ],
        'common_issues' => [
            'Forum not visible' => 'Check: 1) Forum status is open, 2) User has "vf" permission, 3) Cache cleared',
            'Cannot create subforum' => 'Select parent forum when creating new forum',
            'Forum order wrong' => 'Drag and drop forums in admin to reorder',
        ],
        'admin_path' => 'Dashboard > wpForo > Forums (drag-drop reordering)',
    ],

    // ========== AI FEATURES ==========
    'ai_features' => [
        'category' => 'ai',
        'feature' => 'ai_system',
        'behavior' => 'wpForo AI features require cloud subscription and include: Semantic Search, Topic Suggestions, Translation, Summarization, Content Moderation, and AI Chatbot.',
        'features' => [
            'Semantic Search' => 'Find content by meaning, not just keywords',
            'Topic Suggestions' => 'Suggest similar topics before user posts',
            'AI Moderation' => 'Auto-detect spam, toxic content, policy violations',
            'AI Chatbot' => 'Answer user questions based on forum content',
            'Translation' => 'Translate posts to other languages',
            'Summarization' => 'Generate topic summaries',
        ],
        'requirements' => [
            'Active gVectors AI subscription',
            'Forum content indexed in cloud',
            'Credits available',
        ],
        'common_issues' => [
            'AI features not working' => 'Check: 1) Subscription active, 2) API connected, 3) Credits available, 4) Content indexed',
            'Search not finding content' => 'Content needs to be indexed - check AI Content Indexing tab',
            'Moderation not flagging' => 'Check moderation settings and thresholds',
        ],
        'admin_path' => 'Dashboard > wpForo > AI Features',
    ],

    'storage_mode' => [
        'category' => 'ai',
        'setting' => 'storage_mode',
        'behavior' => 'Determines where AI embeddings are stored. Cloud mode stores in AWS S3, Local mode stores in WordPress database. Cloud is recommended for better performance and all features.',
        'modes' => [
            'Cloud (S3)' => 'Best performance, all features, requires subscription',
            'Local (WordPress)' => 'Limited features, slower, works offline',
        ],
        'common_issues' => [
            'Switching modes' => 'Requires re-indexing all content',
            'Local mode slow' => 'SQLite queries slower than S3 Vectors - use cloud for large forums',
        ],
    ],

    // ========== PERMISSIONS SYSTEM (DETAILED) ==========
    'permission_codes' => [
        'category' => 'permissions',
        'feature' => 'permission_codes_reference',
        'behavior' => 'wpForo uses two-tier permissions: Usergroup capabilities (what users CAN do) and Forum Access (WHERE they can do it). Each forum maps usergroups to access levels.',
        'forum_permission_codes' => [
            'vf' => 'View Forum - see forum in listing',
            'enf' => 'Enter Forum - access forum page',
            'vt' => 'View Topics - see topic list',
            'ent' => 'Enter Topic - access topic page',
            'vr' => 'View Replies - see posts in topic',
            'ct' => 'Create Topic - post new topics',
            'cr' => 'Create Reply - post replies',
            'ocr' => 'Reply to Own Topic only',
            'et' => 'Edit Any Topic - moderator level',
            'er' => 'Edit Any Reply - moderator level',
            'eot' => 'Edit Own Topic',
            'eor' => 'Edit Own Reply',
            'dt' => 'Delete Any Topic - moderator level',
            'dr' => 'Delete Any Reply - moderator level',
            'dot' => 'Delete Own Topic',
            'dor' => 'Delete Own Reply',
            'l' => 'Like - react to posts',
            'v' => 'Vote - Q&A voting',
            's' => 'Sticky - make topics sticky',
            'p' => 'Private - set any topic private',
            'op' => 'Own Private - set own topic private',
            'au' => 'Auto-Unapprove bypass - skip moderation',
            'vp' => 'View Private - see private topics',
            'mt' => 'Move Topic - move between forums',
            'cot' => 'Close Topic',
            'sv' => 'Solved - mark any topic solved',
            'osv' => 'Own Solved - mark own topic solved',
            'tag' => 'Add Tags',
            'sb' => 'Subscribe',
            'r' => 'Report - report content',
            'a' => 'Attach - upload files',
            'va' => 'View Attachments',
            'bm' => 'Ban Member',
            'dm' => 'Delete Member',
        ],
        'access_levels' => [
            'no_access' => 'Cannot see or interact with forum at all',
            'read_only' => 'Can view but not post (default for Guests)',
            'standard' => 'Can view, post, edit own content (default for Registered)',
            'moderator' => 'Can edit/delete any content, approve posts',
            'full' => 'All permissions enabled (default for Admin)',
        ],
        'common_issues' => [
            'User cannot see forum' => 'Check usergroup has "vf" and "enf" for that forum',
            'User cannot post' => 'Check usergroup has "ct" (topics) or "cr" (replies) for forum',
            'Moderator cannot approve' => 'Check usergroup has "au" permission',
            'Cannot edit own post' => 'Check "eot"/"eor" AND check edit time limit in Settings > Posting',
        ],
        'admin_path' => 'Dashboard > wpForo > Usergroups (capabilities) + Forums > Edit > Access (per-forum)',
    ],

    'default_usergroups' => [
        'category' => 'permissions',
        'feature' => 'default_usergroups',
        'behavior' => 'wpForo has 5 default usergroups that map to WordPress roles. Groups 1,2,4 are protected (cannot be deleted). Users can have primary + secondary groups.',
        'usergroups' => [
            'Admin (ID 1)' => 'Maps to WP Administrator. Full access. Protected.',
            'Moderator (ID 2)' => 'Maps to WP Editor. Can moderate content. Protected.',
            'Registered (ID 3)' => 'Maps to WP Subscriber. Standard posting rights. Can be secondary.',
            'Guest (ID 4)' => 'Non-logged-in users. Read-only by default. Protected.',
            'Customer (ID 5)' => 'Maps to WP Customer role (WooCommerce). Can be secondary.',
        ],
        'secondary_groups' => 'Users can belong to multiple groups. Permissions are cumulative - if ANY group grants permission, user has it.',
        'common_issues' => [
            'New user has wrong permissions' => 'Check role-to-usergroup mapping in Usergroups settings',
            'WooCommerce customer cannot post' => 'Check Customer usergroup has posting permissions',
        ],
        'admin_path' => 'Dashboard > wpForo > Usergroups',
    ],

    // ========== TOOLS ==========
    'tools_debug' => [
        'category' => 'tools',
        'feature' => 'debug_tools',
        'behavior' => 'Debug tab shows system information, user data viewer, and error logs. Use when troubleshooting installation or permission issues.',
        'features' => [
            'User Data Viewer' => 'See any member profile, user meta, cookies',
            'Server Information' => 'PHP version, MySQL, server software, extensions',
            'Error & Issues' => 'Display error logs and system recommendations',
        ],
        'admin_path' => 'Dashboard > wpForo > Tools > Debug',
    ],

    'tools_database' => [
        'category' => 'tools',
        'feature' => 'database_tools',
        'behavior' => 'Database Tables tab shows full schema, detects problems, and can repair tables. Use after updates or when experiencing data issues.',
        'features' => [
            'Schema Display' => 'All tables with columns, types, indexes',
            'Problem Detection' => 'Find missing fields, keys, or tables',
            'Repair' => 'Generate and run SQL to fix issues',
        ],
        'common_issues' => [
            'Missing table after update' => 'Run database repair from Tools > Database Tables',
            'Index errors' => 'Check for missing keys and repair',
        ],
        'admin_path' => 'Dashboard > wpForo > Tools > Database Tables',
    ],

    'tools_email_queue' => [
        'category' => 'tools',
        'feature' => 'email_queue_tools',
        'behavior' => 'Email Queue tab shows async email status, pending/failed/sent emails, and cron health. Essential for debugging notification issues.',
        'features' => [
            'Queue Statistics' => 'Pending, failed, sent today, total counts',
            'Cron Status' => 'Shows if WP Cron is healthy or stalled',
            'Email List' => 'Search/filter emails, retry failed, delete stuck',
            'Process Now' => 'Manually trigger queue processing',
        ],
        'common_issues' => [
            'Emails stuck in queue' => 'Check Cron Status - if stalled, emails fall back to sync mode',
            'Failed emails' => 'Click retry or check error message for SMTP issues',
        ],
        'admin_path' => 'Dashboard > wpForo > Tools > Email Queue',
    ],

    // ========== CACHE SYSTEM ==========
    'cache_system' => [
        'category' => 'cache',
        'feature' => 'wpforo_cache',
        'behavior' => 'wpForo uses file-based caching for forums, topics, posts, avatars, and more. Cache improves performance but needs clearing after changes.',
        'cache_types' => [
            'Forum cache' => 'Forum listing pages',
            'Topic cache' => 'Topic lists within forums',
            'Post cache' => 'Individual post content',
            'Item caches' => 'Individual objects (forum, topic, post, avatar, reaction, URL)',
            'RAM cache' => 'In-memory cache for single request',
        ],
        'auto_invalidation' => [
            'New topic/post' => 'Forum and topic caches cleared',
            'Edit content' => 'Specific item cache cleared',
            'Board change' => 'Full cache cleared for board',
            'Settings change' => 'Relevant caches cleared',
        ],
        'common_issues' => [
            'Old content showing' => 'Clear wpForo cache in Dashboard > wpForo > Dashboard > Clear Cache',
            'Cache plugin conflicts' => 'Exclude /forum/ pages from external cache plugins',
            'Memory issues' => 'Cache auto-cleans when >1000 files in directory',
        ],
        'admin_path' => 'Dashboard > wpForo > Dashboard (Clear Cache button) + Settings > Board > Cache',
    ],

    // ========== SEO SYSTEM ==========
    'seo_system' => [
        'category' => 'seo',
        'feature' => 'seo_sitemaps',
        'behavior' => 'wpForo generates XML sitemaps for forums, topics, and members. Sitemaps help search engines discover forum content.',
        'sitemap_types' => [
            'forum-sitemap.xml' => 'All public forums',
            'topic-sitemap#.xml' => 'All public topics (paginated)',
            'profile-sitemap#.xml' => 'All public member profiles',
            'sitemap_index.xml' => 'Index of all sitemaps',
        ],
        'features' => [
            'Auto Ping' => 'Notify Google/Bing when content changes',
            'RFC 3986 URLs' => 'Properly encoded URLs',
            'Permission Aware' => 'Only includes publicly visible content',
        ],
        'settings' => [
            'topics_sitemap' => 'Enable topic sitemaps',
            'members_sitemap' => 'Enable member profile sitemaps',
            'forums_sitemap' => 'Enable forum sitemaps',
            'allow_ping' => 'Enable search engine pinging',
        ],
        'common_issues' => [
            'Topics not in sitemap' => 'Private or unapproved topics excluded',
            'Sitemap not updating' => 'Sitemaps cached for 24 hours - wait or clear cache',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > SEO',
    ],

    // ========== PHRASES (TRANSLATION) ==========
    'phrases_system' => [
        'category' => 'phrases',
        'feature' => 'translation_system',
        'behavior' => 'Phrases are translatable text strings used throughout wpForo. You can translate to any language or customize default English text.',
        'features' => [
            'Add Phrases' => 'Create new translatable strings',
            'Edit Phrases' => 'Change existing text in any language',
            'Search' => 'Find phrases by key or value',
            'Import/Export' => 'XML-based language file management',
            'Packages' => 'Namespace system for addon phrases',
        ],
        'translation_workflow' => [
            '1. Select language' => 'Choose target language from dropdown',
            '2. Find phrase' => 'Search by English text or phrase key',
            '3. Edit translation' => 'Enter translated text',
            '4. Save' => 'Changes apply immediately',
        ],
        'common_issues' => [
            'Translation not showing' => 'Check correct language is selected, clear cache',
            'Missing phrases after update' => 'Run phrase crawl to detect new strings',
            'Addon phrases missing' => 'Check addon package is selected in filter',
        ],
        'admin_path' => 'Dashboard > wpForo > Phrases',
    ],

    // ========== THEMES ==========
    'themes_system' => [
        'category' => 'themes',
        'feature' => 'theme_system',
        'behavior' => 'wpForo themes control the visual appearance. The 2026 theme is the latest with all 5 layouts. Themes can be customized via child themes or custom CSS.',
        'available_themes' => [
            '2026' => 'Latest theme, all 5 layouts including Boxed, modern design',
            '2022' => 'Previous stable theme, 4 layouts',
            'Classic' => 'Legacy theme for backwards compatibility',
        ],
        'customization' => [
            'Custom CSS' => 'Settings > Styles > Custom CSS',
            'Color Styles' => 'Settings > Styles > Forum Color Styles (8 color positions)',
            'Font Sizes' => 'Settings > Styles > Font sizes for forum/topic/post',
            'Child Theme' => 'Create wp-content/themes/{child}/wpforo/ folder to override templates',
        ],
        'common_issues' => [
            'Styles not loading' => 'Check for CSS conflicts, clear cache',
            'Theme customizations lost' => 'Use Custom CSS or child theme instead of editing plugin files',
        ],
        'admin_path' => 'Dashboard > wpForo > Themes + Settings > Styles',
    ],

    // ========== AUTHORIZATION & REGISTRATION ==========
    'authorization_system' => [
        'category' => 'authorization',
        'feature' => 'registration_settings',
        'behavior' => 'Controls user registration, email confirmation, and role-usergroup mapping. Works with WordPress default registration or can override.',
        'settings' => [
            'Use WordPress Registration' => 'Redirect to wp-login.php or use wpForo forms',
            'Email Confirmation' => 'Require email verification before posting',
            'Manual Approval' => 'Admin must approve new users',
            'Default Usergroup' => 'Which group new users join',
            'Role Sync' => 'Map WordPress roles to wpForo usergroups',
        ],
        'common_issues' => [
            'New users cannot post' => 'Check if email confirmation required but not completed',
            'Registration not working' => 'Check WordPress registration settings and SMTP',
            'Wrong usergroup assigned' => 'Check role-usergroup mapping',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Authorization',
    ],

    // ========== ANTISPAM ==========
    'antispam_system' => [
        'category' => 'antispam',
        'feature' => 'spam_protection',
        'behavior' => 'Multiple layers of spam protection: new user restrictions, link detection, flood protection, file scanning, and optional Akismet integration.',
        'features' => [
            'New User Limits' => 'Restrict new users from posting links, attachments',
            'Flood Protection' => 'Prevent rapid posting (per-user and per-IP)',
            'Auto Unapprove' => 'Send new user posts to moderation',
            'Link Detection' => 'Flag posts with links from new users',
            'File Scanning' => 'Check attachments for malware signatures',
            'Akismet' => 'Optional integration with Akismet spam service',
        ],
        'settings' => [
            'new_user_max_posts' => 'Max posts for new users before limits lift',
            'unapprove_if_link' => 'Auto-moderate posts with links',
            'flood_interval' => 'Minimum seconds between posts',
            'flood_ip_interval' => 'Per-IP posting limit',
        ],
        'common_issues' => [
            'Legitimate users flagged' => 'Lower threshold_posts or disable link detection',
            'Spam getting through' => 'Enable Akismet, tighten new user limits',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Antispam + Akismet',
    ],

    // ========== ACTIVITY SYSTEM ==========
    'activity_system' => [
        'category' => 'activity',
        'feature' => 'activity_logging',
        'behavior' => 'Tracks forum activities for display in activity feeds and member profiles. Shows recent topics, replies, likes, and other actions.',
        'logged_activities' => [
            'New topics' => 'When user creates topic',
            'New replies' => 'When user posts reply',
            'Approvals' => 'When content is approved',
            'Reactions' => 'Likes and other reactions',
            'Solutions' => 'Topics marked as solved',
        ],
        'settings' => [
            'activity_types' => 'Which activities to track',
            'display_options' => 'How to show in feeds',
            'retention' => 'How long to keep activity data',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Activity',
    ],

    // ========== RSS FEEDS ==========
    'rss_system' => [
        'category' => 'rss',
        'feature' => 'rss_feeds',
        'behavior' => 'wpForo generates RSS feeds for forums and topics, allowing users to subscribe via feed readers.',
        'feed_types' => [
            'General Forum Feed' => 'All new topics across forums',
            'Per-Forum Feed' => 'New topics in specific forum',
            'Per-Topic Feed' => 'New replies to specific topic',
        ],
        'settings' => [
            'rss_general' => 'Enable general forum RSS',
            'rss_forum' => 'Enable per-forum RSS',
            'rss_topic' => 'Enable per-topic RSS',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > RSS',
    ],

    // ========== LEGAL & GDPR ==========
    'legal_system' => [
        'category' => 'legal',
        'feature' => 'gdpr_compliance',
        'behavior' => 'GDPR and privacy compliance features including consent checkboxes, privacy policy links, data export, and account deletion.',
        'features' => [
            'Privacy Policy Page' => 'Link to privacy policy on registration',
            'Terms Page' => 'Terms of service agreement',
            'Forum Rules' => 'Display forum-specific rules',
            'Cookie Notice' => 'GDPR cookie consent',
            'Data Export' => 'Users can export their data',
            'Account Deletion' => 'Users can request deletion',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Legal',
    ],

    // ========== POSTING SETTINGS ==========
    'posting_settings' => [
        'category' => 'posting',
        'feature' => 'content_rules',
        'behavior' => 'Controls content length limits, editing timeframes, attachments, and editor options.',
        'length_limits' => [
            'topic_title_min/max_length' => 'Topic title character limits',
            'topic_body_min/max_length' => 'Topic content limits',
            'post_body_min/max_length' => 'Reply content limits',
            'comment_body_min/max_length' => 'Q&A comment limits',
        ],
        'editing' => [
            'edit_own_topic_durr' => 'Minutes allowed to edit own topic (0 = unlimited)',
            'edit_own_post_durr' => 'Minutes allowed to edit own reply',
            'delete_own_topic_durr' => 'Minutes allowed to delete own topic',
            'delete_own_post_durr' => 'Minutes allowed to delete own reply',
        ],
        'attachments' => [
            'max_upload_size' => 'Maximum file size in MB',
            'attachs_to_medialib' => 'Add forum attachments to Media Library',
        ],
        'common_issues' => [
            'Cannot edit old post' => 'Edit time limit expired - check edit_own_post_durr setting',
            'Content too short error' => 'Increase content or lower min_length setting',
            'Upload failed' => 'Check max_upload_size and PHP upload_max_filesize',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Posting',
    ],

    // ========== NOTIFICATIONS ==========
    'notifications_system' => [
        'category' => 'notifications',
        'feature' => 'live_notifications',
        'behavior' => 'Real-time notification system with bell icon in forum header. Shows mentions, replies to subscribed topics, likes, etc.',
        'features' => [
            'Notification Bell' => 'Shows count of unread notifications',
            'Live Updates' => 'Real-time updates without page refresh (if enabled)',
            'Notification Types' => 'Replies, mentions, likes, follows',
        ],
        'settings' => [
            'live_notifications' => 'Enable real-time notification updates',
            'notification_bell' => 'Display notification bell in header',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Notifications',
    ],

    // ========== LOGGING & TRACKING ==========
    'logging_system' => [
        'category' => 'logging',
        'feature' => 'view_tracking',
        'behavior' => 'Tracks forum/topic views and read status. Enables "jump to unread" and "who viewed" features.',
        'features' => [
            'View Logging' => 'Track who views forums and topics',
            'Read Tracking' => 'Track read/unread status per user',
            'Jump to Unread' => 'Link to first unread post in topic',
            'Display Viewers' => 'Show who is currently viewing',
        ],
        'common_issues' => [
            'Unread badges not updating' => 'Check logging is enabled and cookies working',
            'Performance slow' => 'Disable detailed view logging on high-traffic forums',
        ],
        'admin_path' => 'Dashboard > wpForo > Settings > Logging',
    ],
];

// ============================================================================
// PART 3: DEFINE TROUBLESHOOTING DECISION TREES
// ============================================================================

$troubleshootingTrees = [
    'user_cannot_edit_profile' => [
        'title' => 'User Cannot Edit Their Profile',
        'symptoms' => ['Edit Profile tab missing', 'Save button does nothing', 'Profile fields disabled'],
        'decision_tree' => [
            [
                'check' => 'Is profile editing enabled globally?',
                'how' => 'Dashboard > wpForo > Settings > Profiles > "Can Edit Profile"',
                'if_no' => 'Enable the setting',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does user have enough approved posts?',
                'how' => 'Check user\'s post count vs Settings > Members > "New User Threshold"',
                'if_no' => 'User needs more approved posts OR lower threshold',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have profile edit permission?',
                'how' => 'Dashboard > wpForo > Usergroups > [User\'s Group] > "Can Edit Own Profile"',
                'if_no' => 'Enable permission for usergroup',
                'if_yes' => 'Check for plugin conflicts or custom code',
            ],
        ],
        'related_settings' => ['can_edit_profile', 'threshold_posts'],
    ],

    'topic_not_showing' => [
        'title' => 'Topic Created But Not Visible',
        'symptoms' => ['Topic not in listing', 'Author cannot find their topic', 'No error on creation'],
        'decision_tree' => [
            [
                'check' => 'Is topic status approved (status=0)?',
                'how' => 'Dashboard > wpForo > Moderation - look for topic in queue',
                'if_no' => 'Topic is in moderation - approve it or check why auto-moderated',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is topic private?',
                'how' => 'Edit topic - check "Private" flag',
                'if_yes' => 'Only author and mods can see it',
                'if_no' => 'Continue to next check',
            ],
            [
                'check' => 'Does viewer have forum access?',
                'how' => 'Check viewer\'s usergroup has "vf" and "vt" permission for forum',
                'if_no' => 'Grant access to usergroup',
                'if_yes' => 'Clear cache - Dashboard > wpForo > Tools > Clear Cache',
            ],
        ],
        'related_settings' => ['new_user_unapprove', 'topic moderation settings'],
    ],

    'emails_not_sending' => [
        'title' => 'Email Notifications Not Being Received',
        'symptoms' => ['No subscription emails', 'User says they subscribed but no emails', 'Emails delayed'],
        'decision_tree' => [
            [
                'check' => 'Is user actually subscribed?',
                'how' => 'Dashboard > wpForo > Subscriptions - search for user',
                'if_no' => 'User needs to subscribe to topic/forum',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is async email enabled and working?',
                'how' => 'Dashboard > wpForo > Tools > Email Queue - check Cron Status',
                'if_stalled' => 'Cron not running - emails fall back to sync but may be slow',
                'if_healthy' => 'Continue to next check',
            ],
            [
                'check' => 'Are emails being sent at all?',
                'how' => 'Check Tools > Email Queue > Sent tab for recent emails',
                'if_no' => 'Check SMTP plugin, wp_mail() function',
                'if_yes' => 'Emails sent - check spam folder, email deliverability',
            ],
            [
                'check' => 'Is WordPress email working?',
                'how' => 'Use a plugin like WP Mail SMTP to test email sending',
                'if_no' => 'Fix WordPress email configuration first',
            ],
        ],
        'related_settings' => ['async_notifications', 'email settings'],
    ],

    'user_cannot_post' => [
        'title' => 'User Cannot Create Topic or Reply',
        'symptoms' => ['No reply button', 'New Topic button missing', 'Permission denied error'],
        'decision_tree' => [
            [
                'check' => 'Is user logged in?',
                'how' => 'Guest posting might be disabled',
                'if_no' => 'User needs to login OR enable guest posting',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have posting permission?',
                'how' => 'Usergroups > [Group] > check "ct" (create topic) and "cr" (create reply)',
                'if_no' => 'Grant permission to usergroup',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have access to THIS forum?',
                'how' => 'Forums > Edit Forum > Access tab - check usergroup has ct/cr for this forum',
                'if_no' => 'Grant forum-specific permission',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is topic closed?',
                'how' => 'Check topic status - closed topics block replies',
                'if_yes' => 'Reopen topic or explain to user',
                'if_no' => 'Check for flood control or rate limiting',
            ],
        ],
        'related_settings' => ['usergroup permissions', 'forum access settings'],
    ],

    'ai_search_not_working' => [
        'title' => 'AI Search Not Finding Results',
        'symptoms' => ['Search returns nothing', 'Old content not found', 'Only recent content found'],
        'decision_tree' => [
            [
                'check' => 'Is AI connected and subscription active?',
                'how' => 'Dashboard > wpForo > AI Features > check connection status',
                'if_no' => 'Connect to AI service or renew subscription',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is content indexed?',
                'how' => 'AI Features > AI Content Indexing > check indexed count',
                'if_no' => 'Start indexing - may take time for large forums',
                'if_partial' => 'Wait for indexing to complete or index manually',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does user have access to forums with results?',
                'how' => 'Search respects forum permissions - private forums excluded',
                'if_no' => 'Results exist but user cannot see them',
                'if_yes' => 'Check search quality settings or contact support',
            ],
        ],
        'related_settings' => ['ai settings', 'storage_mode', 'indexing settings'],
    ],

    'forum_not_visible' => [
        'title' => 'Forum Not Visible to Users',
        'symptoms' => ['Forum missing from listing', 'Users report cannot see forum', 'Forum shows for some users not others'],
        'decision_tree' => [
            [
                'check' => 'Is forum status open?',
                'how' => 'Dashboard > wpForo > Forums > check forum status column',
                'if_closed' => 'Forum is closed - reopen if needed',
                'if_open' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have "vf" (view forum) permission?',
                'how' => 'Forums > Edit Forum > Access tab > check usergroup row for "vf"',
                'if_no' => 'Enable "vf" permission for affected usergroup',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is forum set to private/restricted access?',
                'how' => 'Forums > Edit Forum > Access > check if only specific groups have access',
                'if_yes' => 'Add usergroups that should have access',
                'if_no' => 'Clear cache - may be caching issue',
            ],
        ],
        'related_settings' => ['forum permissions', 'usergroup access'],
    ],

    'wrong_usergroup_assigned' => [
        'title' => 'User Has Wrong Usergroup',
        'symptoms' => ['Wrong permissions', 'User cannot do things they should', 'Badge shows wrong group'],
        'decision_tree' => [
            [
                'check' => 'What WordPress role does user have?',
                'how' => 'Users > Edit User > check Role dropdown',
                'if_wrong' => 'Fix WordPress role first - wpForo syncs from WP roles',
                'if_correct' => 'Continue to next check',
            ],
            [
                'check' => 'Is role-to-usergroup mapping correct?',
                'how' => 'Dashboard > wpForo > Usergroups > check "WordPress Role" column',
                'if_no' => 'Edit usergroup to map to correct WordPress role',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does user have manually assigned usergroup?',
                'how' => 'Dashboard > wpForo > Members > Edit Member > check Usergroup field',
                'if_manual' => 'Manual assignment overrides role sync - change if needed',
                'if_auto' => 'Check for secondary usergroups that may be affecting permissions',
            ],
        ],
        'related_settings' => ['role sync settings', 'usergroup mapping'],
    ],

    'signature_not_showing' => [
        'title' => 'User Signature Not Displaying',
        'symptoms' => ['Signature field empty', 'Signature saved but not shown', 'Some users have signatures others do not'],
        'decision_tree' => [
            [
                'check' => 'Is signature feature enabled globally?',
                'how' => 'Dashboard > wpForo > Settings > Profiles > "Member Signature"',
                'if_disabled' => 'Enable signatures globally',
                'if_enabled' => 'Continue to next check',
            ],
            [
                'check' => 'Does user have enough approved posts?',
                'how' => 'Check user post count vs Settings > Members > "New User Threshold"',
                'if_below' => 'User needs more approved posts to display signature',
                'if_above' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have signature permission?',
                'how' => 'Dashboard > wpForo > Usergroups > [Group] > "Can Have Signature" (ups)',
                'if_no' => 'Enable signature permission for usergroup',
                'if_yes' => 'Check if signature contains HTML/links that are being stripped',
            ],
        ],
        'related_settings' => ['signature settings', 'threshold_posts', 'usergroup permissions'],
    ],

    'avatar_not_showing' => [
        'title' => 'User Avatar Not Displaying',
        'symptoms' => ['Default avatar shows', 'Uploaded avatar not appearing', 'Gravatar not loading'],
        'decision_tree' => [
            [
                'check' => 'Is custom avatar enabled?',
                'how' => 'Dashboard > wpForo > Settings > Profiles > "Custom Avatar"',
                'if_disabled' => 'Enable custom avatars',
                'if_enabled' => 'Continue to next check',
            ],
            [
                'check' => 'Does user have enough posts to upload avatar?',
                'how' => 'Check threshold_posts setting - new users may be restricted',
                'if_no' => 'User needs more posts OR lower threshold',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have avatar upload permission?',
                'how' => 'Dashboard > wpForo > Usergroups > [Group] > "Can Upload Avatar" (upa)',
                'if_no' => 'Enable avatar permission',
                'if_yes' => 'Check file upload size limits and image format',
            ],
        ],
        'related_settings' => ['avatar settings', 'upload limits'],
    ],

    'cache_issues' => [
        'title' => 'Content Not Updating / Cache Issues',
        'symptoms' => ['Old content showing', 'Changes not appearing', 'Different users see different content'],
        'decision_tree' => [
            [
                'check' => 'Clear wpForo cache',
                'how' => 'Dashboard > wpForo > Dashboard > Clear Cache button',
                'after' => 'Check if issue resolved',
                'if_no' => 'Continue to next check',
            ],
            [
                'check' => 'Is external cache plugin active?',
                'how' => 'Check for WP Super Cache, W3 Total Cache, LiteSpeed, WP Rocket, etc.',
                'if_yes' => 'Exclude forum pages from external cache OR clear external cache',
                'if_no' => 'Continue to next check',
            ],
            [
                'check' => 'Is there server-level caching?',
                'how' => 'Check with hosting provider for Varnish, Redis, or CDN caching',
                'if_yes' => 'Purge server cache or add forum exclusion rules',
                'if_no' => 'Check browser cache - try incognito/private mode',
            ],
        ],
        'related_settings' => ['cache settings', 'external cache plugin settings'],
    ],

    'threaded_replies_flat' => [
        'title' => 'Threaded Replies Not Nesting',
        'symptoms' => ['All replies at same level', 'No indentation', 'Flat reply list'],
        'decision_tree' => [
            [
                'check' => 'Is forum using Threaded layout (Layout 4)?',
                'how' => 'Dashboard > Forums > Edit Forum > check Layout dropdown',
                'if_no' => 'Switch to Threaded layout for nested replies',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is nesting level set correctly?',
                'how' => 'Settings > Forums > "Threaded Layout - Replies Nesting Levels Deep"',
                'if_zero' => 'Increase nesting level (default 5)',
                'if_correct' => 'Continue to next check',
            ],
            [
                'check' => 'Are users replying to specific posts?',
                'how' => 'Users must click Reply on specific post, not general reply button',
                'if_no' => 'Instruct users to use per-post Reply buttons',
                'if_yes' => 'Check theme CSS for display issues',
            ],
        ],
        'related_settings' => ['layout_threaded_nesting_level', 'forum layout'],
    ],

    'qa_voting_not_working' => [
        'title' => 'Q&A Voting Not Working',
        'symptoms' => ['Vote buttons missing', 'Votes not counting', 'Cannot mark best answer'],
        'decision_tree' => [
            [
                'check' => 'Is forum using Q&A layout (Layout 3)?',
                'how' => 'Dashboard > Forums > Edit Forum > check Layout is "Q&A"',
                'if_no' => 'Switch to Q&A layout for voting functionality',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Does usergroup have vote permission?',
                'how' => 'Forums > Edit Forum > Access > check "v" (vote) for usergroup',
                'if_no' => 'Enable vote permission',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Can user mark best answer?',
                'how' => 'Only topic author or users with "sv" permission can mark solved',
                'if_not_author' => 'User needs "sv" (solved) permission in forum access',
                'if_author' => 'Check for JavaScript errors in browser console',
            ],
        ],
        'related_settings' => ['forum layout', 'vote permission', 'solved permission'],
    ],

    'multiboard_content_missing' => [
        'title' => 'Content Missing in Multi-Board Setup',
        'symptoms' => ['Topics not found', 'Forums empty on one board', 'Content appears on wrong board'],
        'decision_tree' => [
            [
                'check' => 'Are you viewing the correct board?',
                'how' => 'Check URL slug - each board has different slug (e.g., /community/ vs /support/)',
                'if_wrong' => 'Navigate to correct board',
                'if_correct' => 'Continue to next check',
            ],
            [
                'check' => 'Was content created on this board?',
                'how' => 'Content is board-specific - forums/topics/posts do not share between boards',
                'if_wrong_board' => 'Content exists on different board - recreate or use correct board',
                'if_correct' => 'Continue to next check',
            ],
            [
                'check' => 'Are forums created on this board?',
                'how' => 'Dashboard > wpForo > Boards > select board > Forums',
                'if_no' => 'Create forums for this board',
                'if_yes' => 'Check board-specific settings and permissions',
            ],
        ],
        'related_settings' => ['board settings', 'board-specific options'],
    ],

    'registration_issues' => [
        'title' => 'User Registration Not Working',
        'symptoms' => ['Cannot register', 'Email not received', 'Account not created'],
        'decision_tree' => [
            [
                'check' => 'Is user registration enabled in WordPress?',
                'how' => 'Settings > General > "Anyone can register"',
                'if_disabled' => 'Enable WordPress registration',
                'if_enabled' => 'Continue to next check',
            ],
            [
                'check' => 'Is wpForo using WordPress or custom registration?',
                'how' => 'Dashboard > wpForo > Settings > Authorization > registration settings',
                'if_wp' => 'Check WordPress registration flow',
                'if_custom' => 'Check wpForo registration form and settings',
            ],
            [
                'check' => 'Is email confirmation required?',
                'how' => 'Settings > Authorization > "Email Confirmation"',
                'if_yes' => 'User must click confirmation link in email - check spam folder',
                'if_no' => 'Check for reCAPTCHA issues or form validation errors',
            ],
            [
                'check' => 'Is WordPress email working?',
                'how' => 'Install WP Mail SMTP or similar to test email',
                'if_no' => 'Fix WordPress email first',
                'if_yes' => 'Check server error logs for registration errors',
            ],
        ],
        'related_settings' => ['authorization settings', 'email settings', 'reCAPTCHA'],
    ],

    'spam_issues' => [
        'title' => 'Too Much Spam Getting Through',
        'symptoms' => ['Spam topics appearing', 'Spam replies', 'Bot registrations'],
        'decision_tree' => [
            [
                'check' => 'Is reCAPTCHA enabled?',
                'how' => 'Dashboard > wpForo > Settings > reCAPTCHA > enable and add keys',
                'if_no' => 'Enable reCAPTCHA v3 for invisible protection',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is new user moderation enabled?',
                'how' => 'Settings > Antispam > "Auto Unapprove New User Posts"',
                'if_no' => 'Enable to catch spam before it publishes',
                'if_yes' => 'Continue to next check',
            ],
            [
                'check' => 'Is Akismet integrated?',
                'how' => 'Settings > Akismet > enable integration',
                'if_no' => 'Enable Akismet for spam detection',
                'if_yes' => 'Increase threshold_posts to restrict new users longer',
            ],
        ],
        'related_settings' => ['reCAPTCHA', 'antispam settings', 'Akismet', 'threshold_posts'],
    ],
];

// ============================================================================
// PART 4: GENERATE OUTPUT FILES
// ============================================================================

echo "Generating knowledge files...\n";

// 4.1 Settings Documentation
$settingsDoc = "# wpForo Settings Reference\n\n";
$settingsDoc .= "Complete reference of all wpForo settings with behavioral effects.\n\n";
$settingsDoc .= "## Quick Reference\n\n";
$settingsDoc .= "| Setting | Type | Purpose |\n";
$settingsDoc .= "|---------|------|----------|\n";

foreach ($settings as $name => $setting) {
    $label = substr($setting['label'], 0, 50);
    $settingsDoc .= "| `{$name}` | {$setting['type']} | {$label} |\n";
}

$settingsDoc .= "\n---\n\n## Behavioral Knowledge\n\n";
$settingsDoc .= "This section explains HOW settings affect user experience.\n\n";

foreach ($behavioralKnowledge as $key => $knowledge) {
    $title = $knowledge['setting'] ?? $knowledge['feature'];
    $settingsDoc .= "### {$title}\n\n";
    $settingsDoc .= "**Category**: {$knowledge['category']}\n\n";
    $settingsDoc .= "**Behavior**: {$knowledge['behavior']}\n\n";

    if (isset($knowledge['affects'])) {
        $settingsDoc .= "**Affects**:\n";
        foreach ($knowledge['affects'] as $effect) {
            $settingsDoc .= "- {$effect}\n";
        }
        $settingsDoc .= "\n";
    }

    if (isset($knowledge['common_issues'])) {
        $settingsDoc .= "**Common Issues**:\n";
        foreach ($knowledge['common_issues'] as $issue => $solution) {
            $settingsDoc .= "- **{$issue}**: {$solution}\n";
        }
        $settingsDoc .= "\n";
    }

    if (isset($knowledge['admin_path'])) {
        $settingsDoc .= "**Admin Path**: {$knowledge['admin_path']}\n\n";
    }

    $settingsDoc .= "---\n\n";
}

file_put_contents("$OUTPUT_DIR/settings/settings-reference.md", $settingsDoc);
echo "  Generated: settings/settings-reference.md\n";

// 4.2 Troubleshooting Guides
foreach ($troubleshootingTrees as $key => $tree) {
    $troubleDoc = "# {$tree['title']}\n\n";
    $troubleDoc .= "## Symptoms\n\n";
    foreach ($tree['symptoms'] as $symptom) {
        $troubleDoc .= "- {$symptom}\n";
    }
    $troubleDoc .= "\n## Diagnostic Steps\n\n";

    $step = 1;
    foreach ($tree['decision_tree'] as $check) {
        $troubleDoc .= "### Step {$step}: {$check['check']}\n\n";
        $troubleDoc .= "**How to check**: {$check['how']}\n\n";

        foreach ($check as $condition => $action) {
            if (strpos($condition, 'if_') === 0) {
                $condLabel = ucfirst(str_replace(['if_', '_'], ['', ' '], $condition));
                $troubleDoc .= "- **{$condLabel}**: {$action}\n";
            }
        }
        $troubleDoc .= "\n";
        $step++;
    }

    if (isset($tree['related_settings'])) {
        $troubleDoc .= "## Related Settings\n\n";
        foreach ($tree['related_settings'] as $setting) {
            $troubleDoc .= "- `{$setting}`\n";
        }
    }

    file_put_contents("$OUTPUT_DIR/troubleshooting/{$key}.md", $troubleDoc);
}
echo "  Generated: " . count($troubleshootingTrees) . " troubleshooting guides\n";

// 4.3 Feature Documentation
$featureDoc = "# wpForo Features Overview\n\n";

$features = [
    'user_system' => [
        'title' => 'User & Member System',
        'description' => 'wpForo extends WordPress users with forum-specific profiles, usergroups, and permissions.',
        'key_concepts' => [
            'wpForo Profile syncs with WordPress user',
            'Usergroups define capabilities (what users CAN do)',
            'Forum Access defines permissions per-forum (WHERE users can do things)',
            'New User Threshold determines when user becomes "trusted"',
        ],
        'admin_locations' => [
            'Members' => 'Dashboard > wpForo > Members',
            'Usergroups' => 'Dashboard > wpForo > Usergroups',
            'Settings' => 'Dashboard > wpForo > Settings > Members/Profiles',
        ],
    ],
    'topic_system' => [
        'title' => 'Topics & Posts',
        'description' => 'Forum content organization with topics (threads) containing posts (replies).',
        'key_concepts' => [
            'Topics have status: approved(0), unapproved(1)',
            'Topics can be: open/closed, private/public, solved/unsolved',
            'Posts belong to topics and can be nested (threaded layout)',
            'First post of topic is special (is_first_post=1)',
        ],
        'admin_locations' => [
            'Moderation' => 'Dashboard > wpForo > Moderation',
            'Settings' => 'Dashboard > wpForo > Settings > Topics/Posting',
        ],
    ],
    'permission_system' => [
        'title' => 'Permissions & Access Control',
        'description' => 'Two-layer permission system: Usergroup capabilities + Forum-specific access.',
        'key_concepts' => [
            'Usergroup = WHAT user can do (capabilities)',
            'Forum Access = WHERE user can do it (per-forum permissions)',
            'Permission codes: vf, vt, ct, cr, et, er, dt, dr, l, v, s, au',
            'Private forums require explicit access grant',
        ],
        'permission_codes' => [
            'vf' => 'View Forum',
            'vt' => 'View Topics',
            'vp' => 'View Private Topics',
            'ct' => 'Create Topic',
            'cr' => 'Create Reply',
            'et' => 'Edit Own Topic',
            'er' => 'Edit Own Reply',
            'dt' => 'Delete Own Topic',
            'dr' => 'Delete Own Reply',
            'l' => 'Like/React',
            'v' => 'Vote (Q&A)',
            's' => 'Subscribe',
            'au' => 'Auto-Unapprove bypass',
        ],
    ],
    'subscription_system' => [
        'title' => 'Subscriptions & Notifications',
        'description' => 'Email notification system for forum activity.',
        'key_concepts' => [
            'Users subscribe to forums or topics',
            'Emails sent on new topic/reply in subscribed item',
            'Async queue prevents slow page loads (optional)',
            'Respects WordPress SMTP plugins',
        ],
    ],
    'moderation_system' => [
        'title' => 'Content Moderation',
        'description' => 'Review and approve content before publication.',
        'key_concepts' => [
            'New user posts can auto-queue for moderation',
            'Posts with links from new users can be flagged',
            'AI Moderation (optional) detects problematic content',
            'Moderators see unapproved content with approve/reject',
        ],
    ],
    'layout_system' => [
        'title' => 'Forum Layouts',
        'description' => 'wpForo offers 5 different layouts for different forum styles, set per-forum.',
        'key_concepts' => [
            'Extended (1) - Info-rich with topic previews',
            'Simplified (2) - Clean minimal design',
            'Q&A (3) - Question/Answer with voting and best answer',
            'Threaded (4) - Nested reply tree like Reddit',
            'Boxed (5) - Modern card-based design (2026 theme)',
        ],
        'admin_locations' => [
            'Set Layout' => 'Dashboard > Forums > Edit Forum > Layout dropdown',
            'Layout Settings' => 'Dashboard > wpForo > Settings > Forums',
        ],
    ],
    'board_system' => [
        'title' => 'Multi-Board System',
        'description' => 'Run multiple separate forum instances within one WordPress installation.',
        'key_concepts' => [
            'Each board has its own forums, topics, posts',
            'Boards can have different URLs, languages, settings',
            'User profiles are SHARED across boards',
            'Settings can be global or board-specific',
            'Default board (ID 0) uses standard table names',
        ],
        'admin_locations' => [
            'Manage Boards' => 'Dashboard > wpForo > Boards',
            'Board Settings' => 'Dashboard > wpForo > Settings (select board)',
        ],
    ],
    'cache_system' => [
        'title' => 'Caching System',
        'description' => 'File-based caching for improved performance.',
        'key_concepts' => [
            'Caches forums, topics, posts, avatars, URLs',
            'Auto-invalidates on content changes',
            'RAM cache for single request optimization',
            'Compatible with external cache plugins (with exclusions)',
        ],
        'admin_locations' => [
            'Clear Cache' => 'Dashboard > wpForo > Dashboard',
            'Cache Settings' => 'Dashboard > wpForo > Settings > Board',
        ],
    ],
    'seo_system' => [
        'title' => 'SEO & Sitemaps',
        'description' => 'Search engine optimization with XML sitemaps.',
        'key_concepts' => [
            'Forum sitemap, topic sitemap, profile sitemap',
            'Automatic ping to Google/Bing on new content',
            'Only includes publicly accessible content',
            'Respects forum permissions',
        ],
        'admin_locations' => [
            'SEO Settings' => 'Dashboard > wpForo > Settings > SEO',
        ],
    ],
    'phrases_system' => [
        'title' => 'Phrases & Translation',
        'description' => 'Multi-language support through translatable phrases.',
        'key_concepts' => [
            'All text is translatable via Phrases admin',
            'Import/export language files (XML)',
            'Addon phrases organized by package',
            'Supports all WordPress locales',
        ],
        'admin_locations' => [
            'Manage Phrases' => 'Dashboard > wpForo > Phrases',
        ],
    ],
    'tools_system' => [
        'title' => 'Admin Tools',
        'description' => 'Debugging, database management, and email queue tools.',
        'key_concepts' => [
            'Debug - Server info, user data viewer, error logs',
            'Database Tables - Schema viewer, problem detection, repair',
            'Email Queue - Async email status, retry failed, cron health',
            'Admin Note - Forum-wide announcements',
        ],
        'admin_locations' => [
            'Tools' => 'Dashboard > wpForo > Tools',
        ],
    ],
    'theme_system' => [
        'title' => 'Themes & Styling',
        'description' => 'Visual customization through themes and CSS.',
        'key_concepts' => [
            '2026 theme is latest with all 5 layouts',
            'Custom CSS field for modifications',
            '8 color positions for forum styling',
            'Child theme support for template overrides',
        ],
        'admin_locations' => [
            'Select Theme' => 'Dashboard > wpForo > Themes',
            'Custom Styles' => 'Dashboard > wpForo > Settings > Styles',
        ],
    ],
    'ai_system' => [
        'title' => 'AI Features',
        'description' => 'AI-powered features including search, suggestions, moderation, and chatbot.',
        'key_concepts' => [
            'Semantic Search - Find content by meaning, not keywords',
            'Topic Suggestions - Suggest similar topics before posting',
            'AI Moderation - Auto-detect spam, toxicity, violations',
            'AI Chatbot - Answer questions from forum content',
            'Translation & Summarization - AI-powered content tools',
            'Requires gVectors AI subscription and content indexing',
        ],
        'admin_locations' => [
            'AI Dashboard' => 'Dashboard > wpForo > AI Features',
            'AI Settings' => 'Dashboard > wpForo > Settings > AI',
        ],
    ],
    'antispam_system' => [
        'title' => 'Spam Protection',
        'description' => 'Multi-layer spam prevention for forums.',
        'key_concepts' => [
            'New user restrictions (links, attachments, posts)',
            'Flood protection (per-user and per-IP)',
            'Auto-moderation for new user posts',
            'Akismet integration (optional)',
            'reCAPTCHA support (v2/v3)',
        ],
        'admin_locations' => [
            'Antispam' => 'Dashboard > wpForo > Settings > Antispam',
            'Akismet' => 'Dashboard > wpForo > Settings > Akismet',
            'reCAPTCHA' => 'Dashboard > wpForo > Settings > reCAPTCHA',
        ],
    ],
    'legal_system' => [
        'title' => 'Legal & GDPR',
        'description' => 'Privacy compliance features for forums.',
        'key_concepts' => [
            'Privacy policy and terms page links',
            'Forum rules display',
            'Cookie consent notice',
            'User data export and deletion',
        ],
        'admin_locations' => [
            'Legal Settings' => 'Dashboard > wpForo > Settings > Legal',
        ],
    ],
];

foreach ($features as $key => $feature) {
    $featureDoc .= "## {$feature['title']}\n\n";
    $featureDoc .= "{$feature['description']}\n\n";

    $featureDoc .= "### Key Concepts\n\n";
    foreach ($feature['key_concepts'] as $concept) {
        $featureDoc .= "- {$concept}\n";
    }
    $featureDoc .= "\n";

    if (isset($feature['permission_codes'])) {
        $featureDoc .= "### Permission Codes\n\n";
        $featureDoc .= "| Code | Meaning |\n|------|--------|\n";
        foreach ($feature['permission_codes'] as $code => $meaning) {
            $featureDoc .= "| `{$code}` | {$meaning} |\n";
        }
        $featureDoc .= "\n";
    }

    if (isset($feature['admin_locations'])) {
        $featureDoc .= "### Admin Locations\n\n";
        foreach ($feature['admin_locations'] as $name => $path) {
            $featureDoc .= "- **{$name}**: {$path}\n";
        }
        $featureDoc .= "\n";
    }

    $featureDoc .= "---\n\n";
}

file_put_contents("$OUTPUT_DIR/features/features-overview.md", $featureDoc);
echo "  Generated: features/features-overview.md\n";

// 4.4 Generate chunks for RAG indexing
$chunks = [];
$chunkId = 1;

// Add behavioral knowledge as chunks
foreach ($behavioralKnowledge as $key => $knowledge) {
    $content = "Setting/Feature: " . ($knowledge['setting'] ?? $knowledge['feature']) . "\n\n";
    $content .= "Behavior: {$knowledge['behavior']}\n\n";

    if (isset($knowledge['common_issues'])) {
        $content .= "Common Issues:\n";
        foreach ($knowledge['common_issues'] as $issue => $solution) {
            $content .= "- {$issue}: {$solution}\n";
        }
    }

    $chunks[] = [
        'id' => "expert-behavior-{$chunkId}",
        'title' => $knowledge['setting'] ?? $knowledge['feature'],
        'content' => $content,
        'category' => $knowledge['category'],
        'type' => 'behavioral_knowledge',
        'content_source' => 'wpforo_expert',
        'priority' => 1.0,
    ];
    $chunkId++;
}

// Add troubleshooting as chunks
foreach ($troubleshootingTrees as $key => $tree) {
    $content = "Problem: {$tree['title']}\n\n";
    $content .= "Symptoms: " . implode(", ", $tree['symptoms']) . "\n\n";
    $content .= "Diagnostic Steps:\n";

    foreach ($tree['decision_tree'] as $i => $check) {
        $content .= ($i+1) . ". {$check['check']} ({$check['how']})\n";
    }

    $chunks[] = [
        'id' => "expert-troubleshoot-{$chunkId}",
        'title' => $tree['title'],
        'content' => $content,
        'category' => 'troubleshooting',
        'type' => 'troubleshooting_guide',
        'content_source' => 'wpforo_expert',
        'priority' => 1.0,
    ];
    $chunkId++;
}

// Add feature docs as chunks
foreach ($features as $key => $feature) {
    $content = "{$feature['title']}\n\n";
    $content .= "{$feature['description']}\n\n";
    $content .= "Key Concepts:\n";
    foreach ($feature['key_concepts'] as $concept) {
        $content .= "- {$concept}\n";
    }

    $chunks[] = [
        'id' => "expert-feature-{$chunkId}",
        'title' => $feature['title'],
        'content' => $content,
        'category' => 'features',
        'type' => 'feature_documentation',
        'content_source' => 'wpforo_expert',
        'priority' => 0.9,
    ];
    $chunkId++;
}

file_put_contents("$OUTPUT_DIR/for-indexing/chunks.json", json_encode($chunks, JSON_PRETTY_PRINT));
echo "  Generated: for-indexing/chunks.json (" . count($chunks) . " chunks)\n";

// 4.5 Generate manifest for incremental updates
$manifest = [
    'generated_at' => date('Y-m-d H:i:s'),
    'version' => '1.0.0',
    'stats' => [
        'settings' => count($settings),
        'behavioral_knowledge' => count($behavioralKnowledge),
        'troubleshooting_guides' => count($troubleshootingTrees),
        'features' => count($features),
        'total_chunks' => count($chunks),
    ],
    'files' => [
        'settings/settings-reference.md',
        'features/features-overview.md',
        'for-indexing/chunks.json',
    ],
    'troubleshooting_files' => array_map(fn($k) => "troubleshooting/{$k}.md", array_keys($troubleshootingTrees)),
];

file_put_contents("$OUTPUT_DIR/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
echo "  Generated: manifest.json\n";

echo "\n=== Summary ===\n";
echo "Settings documented: " . count($settings) . "\n";
echo "Behavioral knowledge items: " . count($behavioralKnowledge) . "\n";
echo "Troubleshooting guides: " . count($troubleshootingTrees) . "\n";
echo "Feature docs: " . count($features) . "\n";
echo "RAG chunks: " . count($chunks) . "\n";
echo "\nOutput: $OUTPUT_DIR\n";
echo "\nTo add this to AI:\n";
echo "  POST /v1/ingest/knowledge-base\n";
echo "  Body: contents of for-indexing/chunks.json\n";
