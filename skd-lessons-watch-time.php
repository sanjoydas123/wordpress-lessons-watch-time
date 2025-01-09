<?php
/*
Plugin Name: SKD Lessons Watch Time
Plugin URI:  https://example.com/
Description: Track user watch time for lessons
Version:     1.0
Author:      SKD
Author URI:  https://example.com/
Text Domain: skd-lessons-watch-time
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('LWT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LWT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin Activation Hook: Ensure ACF is Active
 */
function lwt_plugin_activation()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'lesson_video_progress';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        video_id VARCHAR(100) DEFAULT NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        parent_id BIGINT(20) UNSIGNED DEFAULT NULL,
        grandparent_id BIGINT(20) UNSIGNED DEFAULT NULL,
        main_post_id BIGINT(20) UNSIGNED DEFAULT NULL,
        main_post_type VARCHAR(50) DEFAULT NULL,
        watch_time INT NOT NULL DEFAULT 0,
        duration INT DEFAULT NULL,
        is_complete TINYINT(1) NOT NULL DEFAULT 0,
        is_checked_complete TINYINT(1) NOT NULL DEFAULT 0,
        is_downloaded TINYINT(1) NOT NULL DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_video (user_id, post_id)
    ) $charset_collate;";

    // Include the WordPress upgrade library to run the table creation
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'lwt_plugin_activation');

function get_main_post_id($post_id)
{
    $parent_id = wp_get_post_parent_id($post_id);

    if (!$parent_id) {
        // No parent, so this is the main post
        return $post_id;
    }

    // Recursive call to find the top-level parent
    return get_main_post_id($parent_id);
}

//track video progress
require_once plugin_dir_path(__FILE__) . 'includes/track-video-progress.php';
new VideoWatchTracker();

/**
 * Plugin Deactivation Hook
 */
function lwt_plugin_deactivation()
{
    // global $wpdb;
    // $table_name = $wpdb->prefix . 'lesson_video_progress';
    // $sql = "DROP TABLE IF EXISTS $table_name";
    // $wpdb->query($sql);
}
register_deactivation_hook(__FILE__, 'lwt_plugin_deactivation');

function lwt_admin_styles($hook)
{
    if ('profile.php' !== $hook && 'user-edit.php' !== $hook) {
        return;
    }

    wp_enqueue_style(
        'lwt-admin-styles',
        LWT_PLUGIN_URL . 'assets/css/admin.css',
        [],
        '1.0'
    );

    // Load Chart.js library
    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [],
        null,
        true
    );
}
add_action('admin_enqueue_scripts', 'lwt_admin_styles');

function lwt_frontend_styles_and_scripts()
{
    // Enqueue the frontend CSS file
    wp_enqueue_style(
        'lwt-frontend-styles',
        LWT_PLUGIN_URL . 'assets/css/frontend.css', // Use the same CSS if it applies to both admin and frontend
        [],
        '1.0'
    );

    // Enqueue the Chart.js library
    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [],
        null,
        true // Load in the footer
    );
}
add_action('wp_enqueue_scripts', 'lwt_frontend_styles_and_scripts');
