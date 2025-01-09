<?php

require_once(ABSPATH . 'wp-includes/functions.php');

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class VideoWatchTracker
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);
        add_action('show_user_profile', [$this, 'lwt_add_user_watch_list']);
        add_action('edit_user_profile', [$this, 'lwt_add_user_watch_list']);
        add_shortcode('lwt_user_watch_list', [$this, 'lwt_user_watch_list_shortcode']);
        add_shortcode('lwt_lesson_checkbox', [$this, 'lwt_checkbox_shortcode']);
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'vimeo-player-api',
            'https://player.vimeo.com/api/player.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'video-watch-tracker-js',
            LWT_PLUGIN_URL . 'assets/js/tracker.js',
            ['jquery', 'vimeo-player-api'],
            '1.0',
            true
        );

        wp_localize_script('video-watch-tracker-js', 'VideoTracker', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('video-tracker/v1/track'),
            'is_download_url' => rest_url('video-tracker/v1/edit_is_download'),
            'get_check_status_url' => rest_url('video-tracker/v1/get-checkbox-status'),
            'is_checked_url' => rest_url('video-tracker/v1/edit_is_checked'),
            'nonce'    => wp_create_nonce('wp_rest')
        ]);

        error_log('Tracker script enqueued');
    }

    public function register_rest_endpoints()
    {
        register_rest_route('video-tracker/v1', '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_video'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-tracker/v1', '/edit_is_download', [
            'methods' => 'POST',
            'callback' => [$this, 'update_is_download'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-tracker/v1', '/edit_is_checked', [
            'methods' => 'POST',
            'callback' => [$this, 'update_is_checked'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-tracker/v1', '/get-checkbox-status', [
            'methods' => 'GET',
            'callback' => [$this, 'lwt_get_checkbox_status'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    public function track_video(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lesson_video_progress';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in to track progress', ['status' => 401]);
        }

        $video_id = sanitize_text_field($request->get_param('video_id'));
        $watch_time = intval($request->get_param('watch_time'));
        $is_complete = $request->get_param('is_complete') === 'true' ? 1 : 0;
        $duration = intval($request->get_param('duration')); // Fetch duration from the request
        $post_id = intval($request->get_param('lesson_id'));

        // Retrieve hierarchy
        $parent_id = wp_get_post_parent_id($post_id);
        $grandparent_id = $parent_id ? wp_get_post_parent_id($parent_id) : null;
        $main_post_id = get_main_post_id($post_id);
        $main_post_type = get_post_type($main_post_id);

        $hierarchy_data = [
            'user_id' => $user_id,
            'video_id' => $video_id,
            'post_id' => $post_id,
            'parent_id' => $parent_id,
            'grandparent_id' => $grandparent_id,
            'main_post_id' => $main_post_id,
            'main_post_type' => $main_post_type,
            'watch_time' => $watch_time,
            'duration' => $duration, // Store duration
            'is_complete' => $is_complete,
            'last_updated' => date('Y-m-d H:i:s'),
        ];

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND post_id = %s", $user_id, $post_id)
        );

        if ($existing) {
            if ($existing->is_complete) {
                return rest_ensure_response(['success' => true]);
            }
            $wpdb->update(
                $table_name,
                $hierarchy_data,
                ['user_id' => $user_id, 'post_id' => $post_id],
                ['%d', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s'],
                ['%d', '%s']
            );
        } else {
            $wpdb->insert(
                $table_name,
                $hierarchy_data,
                ['%d', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s']
            );
        }

        return rest_ensure_response(['success' => true]);
    }

    public function update_is_download(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lesson_video_progress';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in to track progress', ['status' => 401]);
        }

        $post_id = intval($request->get_param('lesson_id'));
        $is_downloaded = $request->get_param('is_downloaded') === 'true' ? 1 : 0;

        //if post is not in the table, insert it with is_downloaded, otherwise update it
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND post_id = %s", $user_id, $post_id)
        );

        if ($existing) {
            $wpdb->update(
                $table_name,
                ['is_downloaded' => $is_downloaded],
                ['user_id' => $user_id, 'post_id' => $post_id],
                ['%d'],
                ['%d', '%s']
            );
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'post_id' => $post_id,
                    'is_downloaded' => $is_downloaded,
                    'last_updated' => date('Y-m-d H:i:s'),
                ],
                ['%d', '%d', '%d', '%s']
            );
        }

        return rest_ensure_response(['success' => true]);
    }

    public function lwt_get_checkbox_status(WP_REST_Request $request)
    {
        $post_id = intval($request->get_param('post_id'));

        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'lesson_video_progress';

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT is_complete FROM $table_name WHERE user_id = %d AND post_id = %d", $user_id, $post_id)
        );

        if (!$existing) {
            return new WP_REST_Response(['success' => true, 'is_complete' => false], 200);
        }

        return new WP_REST_Response(['success' => true, 'is_complete' => (bool) $existing->is_complete], 200);
    }


    // Add the function to handle the checkbox update
    public function update_is_checked(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lesson_video_progress';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in to update this', ['status' => 401]);
        }

        $post_id = intval($request->get_param('post_id'));
        $is_complete = $request->get_param('is_complete') ? 1 : 0;

        // Check if an entry exists for this user and post
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND post_id = %d", $user_id, $post_id)
        );

        if ($existing) {
            $wpdb->update(
                $table_name,
                ['is_complete' => $is_complete, 'is_checked_complete' => $is_complete, 'watch_time' => $existing->duration, 'last_updated' => date('Y-m-d H:i:s')],
                ['user_id' => $user_id, 'post_id' => $post_id],
                ['%d'],
                ['%d', '%d']
            );
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'post_id' => $post_id,
                    'is_complete' => $is_complete,
                    'is_checked_complete' => $is_complete,
                    'last_updated' => date('Y-m-d H:i:s'),
                ],
                ['%d', '%d', '%d', '%s']
            );
        }

        return rest_ensure_response(['success' => true]);
    }

    // Shortcode to render the checkbox
    public function lwt_checkbox_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to update your lesson progress.', 'lessons-watch-time') . '</p>';
        }

        ob_start();
        include LWT_PLUGIN_PATH . 'templates/lesson-checkbox.php';
        return ob_get_clean();
    }


    // show progress in the admin dashboard
    function lwt_add_user_watch_list($user)
    {
        if (!current_user_can('edit_users')) {
            return;
        }

        global $wpdb;

        $user_id = $user->ID;

        // Step 1: Fetch all required course posts
        $coursePosts = $wpdb->get_results(
            "SELECT ID, post_title, post_content 
        FROM {$wpdb->posts} 
        WHERE post_type = 'acf-post-type' AND post_status = 'publish'"
        );

        $extractedData = [];

        // Step 2: Extract necessary data from the course posts
        foreach ($coursePosts as $post) {
            $contentData = maybe_unserialize($post->post_content);
            if (is_array($contentData) && isset($contentData['post_type'], $contentData['labels']['menu_name'])) {
                $extractedData[] = [
                    'post_id' => $post->ID,
                    'menu_name' => $contentData['labels']['menu_name'],
                    'post_type' => $contentData['post_type']
                ];
            }
        }

        // Step 3: Fetch parent posts, lesson progress, and completion data for each post type
        foreach ($extractedData as &$data) {
            // Fetch parent posts for the current post_type
            $parent_posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT p.post_parent, pp.post_title AS parent_title, pp.menu_order
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->posts} AS pp ON p.post_parent = pp.ID
                WHERE p.post_type = %s AND pp.post_type = %s AND p.post_status = 'publish'
                ORDER BY pp.menu_order ASC",
                    $data['post_type'],
                    $data['post_type']
                )
            );

            $data['parent_posts'] = $parent_posts;

            // Calculate lesson completion data
            $completion_data = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) AS total_lessons,
                    SUM(CASE WHEN lwp.is_complete = 1 THEN 1 ELSE 0 END) AS completed_lessons,
                    SUM(CASE WHEN lwp.is_complete != 1 AND lwp.watch_time > 0 THEN 1 ELSE 0 END) AS in_progress_lessons,
                    SUM(CASE WHEN lwp.watch_time = 0 THEN 1 ELSE 0 END) AS not_started_lessons
                FROM {$wpdb->posts} AS p
                LEFT JOIN {$wpdb->prefix}lesson_video_progress AS lwp
                    ON p.ID = lwp.post_id AND lwp.user_id = %d
                WHERE p.post_parent > 0 AND p.post_type = %s AND p.post_status = 'publish'",
                $user_id,
                $data['post_type']
            ));


            $data['completion_data'] = $completion_data;
        }
        // echo "<pre>";
        // print_r($extractedData);
        // Step 4: Include the template file and pass the data to it
        include LWT_PLUGIN_PATH . 'templates/admin-watch-list.php';
    }

    // Shortcode to show user watch list in the frontend
    function lwt_user_watch_list_shortcode()
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your lessons progress.', 'lessons-watch-time') . '</p>';
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // Step 1: Fetch all required course posts
        $coursePosts = $wpdb->get_results(
            "SELECT ID, post_title, post_content 
        FROM {$wpdb->posts} 
        WHERE post_type = 'acf-post-type' AND post_status = 'publish'"
        );

        $extractedData = [];

        // Step 2: Extract necessary data from the course posts
        foreach ($coursePosts as $post) {
            $contentData = maybe_unserialize($post->post_content);
            if (is_array($contentData) && isset($contentData['post_type'], $contentData['labels']['menu_name'])) {
                $extractedData[] = [
                    'post_id' => $post->ID,
                    'menu_name' => $contentData['labels']['menu_name'],
                    'post_type' => $contentData['post_type']
                ];
            }
        }

        // Step 3: Fetch parent posts, lesson progress, and completion data for each post type
        foreach ($extractedData as &$data) {
            // Fetch parent posts for the current post_type
            $parent_posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT p.post_parent, pp.post_title AS parent_title, pp.menu_order
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->posts} AS pp ON p.post_parent = pp.ID
                WHERE p.post_type = %s AND pp.post_type = %s AND p.post_status = 'publish'
                ORDER BY pp.menu_order ASC",
                    $data['post_type'],
                    $data['post_type']
                )
            );

            $data['parent_posts'] = $parent_posts;

            // Calculate lesson completion data
            $completion_data = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) AS total_lessons,
                    SUM(CASE WHEN lwp.is_complete = 1 THEN 1 ELSE 0 END) AS completed_lessons,
                    SUM(CASE WHEN lwp.is_complete != 1 AND lwp.watch_time > 0 THEN 1 ELSE 0 END) AS in_progress_lessons,
                    SUM(CASE WHEN lwp.watch_time = 0 THEN 1 ELSE 0 END) AS not_started_lessons
                FROM {$wpdb->posts} AS p
                LEFT JOIN {$wpdb->prefix}lesson_video_progress AS lwp
                    ON p.ID = lwp.post_id AND lwp.user_id = %d
                WHERE p.post_parent > 0 AND p.post_type = %s AND p.post_status = 'publish'",
                $user_id,
                $data['post_type']
            ));

            $data['completion_data'] = $completion_data;
        }

        // Step 4: Include the frontend template file and pass the data to it
        ob_start();
        include LWT_PLUGIN_PATH . 'templates/frontend-watch-list.php';
        return ob_get_clean();
    }
}
