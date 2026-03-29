<?php
/*
Plugin Name: Jeromedia: LinkedIn Posts
Plugin URI: https://jeromedia.com/wp/plugins/jm-linkedin-posts
Description: Retrieves posts from an external API and displays them via shortcode.
Version: 1.18
Author: Jeromedia
Author URI: https://jeromedia.com
License: GPL2
*/

if (!defined('ABSPATH')) exit;

// === Constants ===
define('JM_LI_PLUGIN_FOLDER', basename(dirname(__FILE__)));
define('JM_LI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('JM_LI_PLUGIN_URL', plugin_dir_url(__FILE__));

// === Includes ===
require_once JM_LI_PLUGIN_PATH . 'includes/database.php';
require_once JM_LI_PLUGIN_PATH . 'includes/functions.php';
require_once JM_LI_PLUGIN_PATH . 'includes/cache.php';
require_once JM_LI_PLUGIN_PATH . 'includes/api.php';
require_once JM_LI_PLUGIN_PATH . 'includes/api-functions.php';
require_once JM_LI_PLUGIN_PATH . 'includes/shortcode.php';
require_once JM_LI_PLUGIN_PATH . 'includes/dashboard.php';
require_once JM_LI_PLUGIN_PATH . 'includes/settings.php';
require_once JM_LI_PLUGIN_PATH . 'includes/settings-handler.php';
require_once JM_LI_PLUGIN_PATH . 'includes/settings-save.php';
require_once JM_LI_PLUGIN_PATH . 'includes/menu.php';

// === Activation Hook ===
register_activation_hook(__FILE__, function () {
    jm_li_create_table();
    jm_li_create_cache_table();
});

// === Admin Menu ===
add_action('admin_menu', 'jm_li_add_admin_menu');

// === Enqueue Admin Styles ===
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, JM_LI_PLUGIN_FOLDER) !== false) {
        wp_enqueue_style('jm-linkedin-style', JM_LI_PLUGIN_URL . 'assets/css/jm-main.css');
    }
});

// === GitHub Auto Update ===
define('YOUR_PLUGIN_GITHUB_API_URL', 'https://api.github.com/repos/Jeromedia/WP-JM-Plugin-Linkedin-Posts/releases/latest');

add_filter('site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) return $transient;

    $plugin_slug = JM_LI_PLUGIN_FOLDER . '/' . JM_LI_PLUGIN_FOLDER . '.php';
    $current_version = $transient->checked[$plugin_slug] ?? '';

    $response = wp_remote_get(YOUR_PLUGIN_GITHUB_API_URL, [
        'headers' => [
            'Authorization' => 'token ' . GITHUB_TOKEN,
            'User-Agent'    => 'WordPress/' . get_bloginfo('version'),
        ]
    ]);

    // Debug: log the API response
    if (is_wp_error($response)) {
        error_log('GitHub API Error: ' . $response->get_error_message());
    } else {
        error_log('GitHub API Response: ' . wp_remote_retrieve_body($response));
    }

    if (is_wp_error($response)) return $transient;

    $release_data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($release_data['tag_name']) && version_compare($release_data['tag_name'], $current_version, '>')) {
        $transient->response[$plugin_slug] = [
            'slug'        => JM_LI_PLUGIN_FOLDER,
            'plugin'      => $plugin_slug,
            'new_version' => $release_data['tag_name'],
            'url'         => $release_data['html_url'] ?? '',
            'package'     => $release_data['zipball_url'] ?? ''
        ];
    }

    return $transient;
});

// === GitHub Post Install Folder Fix ===
add_filter('upgrader_post_install', function ($response, $hook_extra) {
    $plugin_slug = JM_LI_PLUGIN_FOLDER . '/' . JM_LI_PLUGIN_FOLDER . '.php';
    global $wp_filesystem;

    $correct_path = WP_PLUGIN_DIR . '/' . JM_LI_PLUGIN_FOLDER . '/';

    if (
        isset($hook_extra['plugin']) &&
        $hook_extra['plugin'] === $plugin_slug &&
        $response['destination'] !== $correct_path
    ) {
        $wp_filesystem->move($response['destination'], $correct_path);
        $response['destination'] = $correct_path;
    }

    return $response;
}, 10, 2);
