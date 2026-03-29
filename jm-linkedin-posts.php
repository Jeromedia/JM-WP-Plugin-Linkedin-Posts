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

// === GitHub Config ===
define('JM_LI_GITHUB_API_URL', 'https://api.github.com/repos/Jeromedia/WP-JM-Plugin-Linkedin-Posts/releases/latest');

// If you already define GITHUB_TOKEN somewhere else, remove this block.
if (!defined('GITHUB_TOKEN')) {
    define('GITHUB_TOKEN', 'YOUR_GITHUB_TOKEN_HERE');
}

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
add_filter('site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = plugin_basename(__FILE__);
    $current_version = get_file_data(__FILE__, ['Version' => 'Version'])['Version'];

    $response = wp_remote_get(JM_LI_GITHUB_API_URL, [
        'headers' => [
            'Authorization' => 'Bearer ' . GITHUB_TOKEN,
            'User-Agent'    => 'WordPress/' . get_bloginfo('version'),
            'Accept'        => 'application/vnd.github+json',
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        return $transient;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        return $transient;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($release_data) || empty($release_data['tag_name'])) {
        return $transient;
    }

    $latest_version = ltrim($release_data['tag_name'], 'v');

    $package_url = '';
    if (!empty($release_data['assets']) && is_array($release_data['assets'])) {
        foreach ($release_data['assets'] as $asset) {
            if (!empty($asset['browser_download_url']) && !empty($asset['name']) && substr($asset['name'], -4) === '.zip') {
                $package_url = $asset['browser_download_url'];
                break;
            }
        }
    }

    if (empty($package_url)) {
        return $transient;
    }

    if (version_compare($latest_version, $current_version, '>')) {
        $transient->response[$plugin_slug] = (object) [
            'slug'        => dirname($plugin_slug),
            'plugin'      => $plugin_slug,
            'new_version' => $latest_version,
            'url'         => $release_data['html_url'] ?? '',
            'package'     => $package_url,
        ];
    }

    return $transient;
});

// === Optional: Plugin Details Popup ===
add_filter('plugins_api', function ($result, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug)) {
        return $result;
    }

    $plugin_slug = dirname(plugin_basename(__FILE__));

    if ($args->slug !== $plugin_slug) {
        return $result;
    }

    $response = wp_remote_get(JM_LI_GITHUB_API_URL, [
        'headers' => [
            'Authorization' => 'Bearer ' . GITHUB_TOKEN,
            'User-Agent'    => 'WordPress/' . get_bloginfo('version'),
            'Accept'        => 'application/vnd.github+json',
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $result;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($release_data) || empty($release_data['tag_name'])) {
        return $result;
    }

    $latest_version = ltrim($release_data['tag_name'], 'v');

    $package_url = '';
    if (!empty($release_data['assets']) && is_array($release_data['assets'])) {
        foreach ($release_data['assets'] as $asset) {
            if (!empty($asset['browser_download_url']) && !empty($asset['name']) && substr($asset['name'], -4) === '.zip') {
                $package_url = $asset['browser_download_url'];
                break;
            }
        }
    }

    return (object) [
        'name'          => 'Jeromedia: LinkedIn Posts',
        'slug'          => $plugin_slug,
        'version'       => $latest_version,
        'author'        => '<a href="https://jeromedia.com">Jeromedia</a>',
        'homepage'      => 'https://jeromedia.com/wp/plugins/jm-linkedin-posts',
        'download_link' => $package_url,
        'sections'      => [
            'description' => 'Retrieves posts from an external API and displays them via shortcode.',
            'changelog'   => !empty($release_data['body']) ? nl2br(esc_html($release_data['body'])) : 'No changelog provided.',
        ],
    ];
}, 10, 3);