<?php
/*
Plugin Name: Jeromedia: LinkedIn Posts
Plugin URI: https://jeromedia.com/wp/plugins/jm-linkedin-posts
Description: Retrieves posts from an external API and displays them via shortcode.
Version: 1.19.2
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
if (!defined('JM_LI_GITHUB_UPDATE_ASSET_NAME')) {
    define('JM_LI_GITHUB_UPDATE_ASSET_NAME', 'jm-linkedin-posts.zip');
}
if (!defined('JM_LI_GITHUB_CHECKSUM_ASSET_NAME')) {
    define('JM_LI_GITHUB_CHECKSUM_ASSET_NAME', 'jm-linkedin-posts.zip.sha256');
}

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

function jm_li_is_allowed_github_url($url)
{
    $parts = wp_parse_url($url);
    $hosts = ['api.github.com', 'github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];
    return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && in_array(strtolower($parts['host'] ?? ''), $hosts, true);
}

function jm_li_get_update_assets($release)
{
    if (!is_array($release['assets'] ?? null)) {
        return false;
    }

    $assets = [];
    foreach ($release['assets'] as $asset) {
        if (!empty($asset['name'])
            && in_array($asset['name'], [JM_LI_GITHUB_UPDATE_ASSET_NAME, JM_LI_GITHUB_CHECKSUM_ASSET_NAME], true)
            && isset($assets[$asset['name']])) {
            return false;
        }
        if (!empty($asset['name']) && !empty($asset['browser_download_url']) && jm_li_is_allowed_github_url($asset['browser_download_url'])) {
            $assets[$asset['name']] = $asset['browser_download_url'];
        }
    }

    if (empty($assets[JM_LI_GITHUB_UPDATE_ASSET_NAME]) || empty($assets[JM_LI_GITHUB_CHECKSUM_ASSET_NAME])) {
        return false;
    }

    return ['package' => $assets[JM_LI_GITHUB_UPDATE_ASSET_NAME], 'checksum' => $assets[JM_LI_GITHUB_CHECKSUM_ASSET_NAME]];
}

function jm_li_parse_update_checksum($checksum)
{
    $checksum = trim((string) $checksum);
    $pattern = '/\\A([a-f0-9]{64})(?:  jm-linkedin-posts\\.zip)?\\z/i';

    return preg_match($pattern, $checksum, $matches) === 1 ? strtolower($matches[1]) : false;
}

function jm_li_get_latest_release()
{
    if (!jm_li_is_allowed_github_url(JM_LI_GITHUB_API_URL)) {
        return new WP_Error('jm_li_invalid_github_url', __('The update URL is not allowed.', 'jm-linkedin-posts'));
    }
    $response = wp_safe_remote_get(JM_LI_GITHUB_API_URL, ['timeout' => 20, 'redirection' => 0, 'limit_response_size' => 1024 * 1024, 'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version'), 'Accept' => 'application/vnd.github+json']]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return new WP_Error('jm_li_release_unavailable', __('The update release could not be retrieved.', 'jm-linkedin-posts'));
    }
    $release = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($release) ? $release : new WP_Error('jm_li_invalid_release', __('The update release is invalid.', 'jm-linkedin-posts'));
}

function jm_li_download_github_file($url, $filename)
{
    for ($redirects = 0; $redirects < 4; $redirects++) {
        if (!jm_li_is_allowed_github_url($url)) {
            return new WP_Error('jm_li_invalid_download_url', __('The update download URL is not allowed.', 'jm-linkedin-posts'));
        }
        $response = wp_safe_remote_get($url, ['timeout' => 30, 'redirection' => 0, 'stream' => true, 'filename' => $filename, 'limit_response_size' => 50 * 1024 * 1024]);
        if (is_wp_error($response)) {
            return $response;
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status >= 200 && $status < 300) {
            return $filename;
        }
        $location = wp_remote_retrieve_header($response, 'location');
        if ($status < 300 || $status >= 400 || empty($location)) {
            return new WP_Error('jm_li_download_failed', __('The update download failed.', 'jm-linkedin-posts'));
        }
        $url = wp_http_validate_url($location) ? $location : WP_Http::make_absolute_url($location, $url);
    }
    return new WP_Error('jm_li_too_many_redirects', __('The update download redirected too many times.', 'jm-linkedin-posts'));
}

add_filter('site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) { return $transient; }
    $release = jm_li_get_latest_release();
    if (is_wp_error($release) || !($assets = jm_li_get_update_assets($release)) || empty($release['tag_name'])) { return $transient; }
    $current = get_file_data(__FILE__, ['Version' => 'Version'])['Version'];
    $latest = ltrim($release['tag_name'], 'v');
    if (version_compare($latest, $current, '>')) {
        $slug = plugin_basename(__FILE__);
        $transient->response[$slug] = (object) ['slug' => dirname($slug), 'plugin' => $slug, 'new_version' => $latest, 'url' => jm_li_is_allowed_github_url($release['html_url'] ?? '') ? $release['html_url'] : '', 'package' => $assets['package']];
    }
    return $transient;
});

add_filter('plugins_api', function ($result, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== dirname(plugin_basename(__FILE__))) {
        return $result;
    }
    $release = jm_li_get_latest_release();
    if (is_wp_error($release) || !($assets = jm_li_get_update_assets($release)) || empty($release['tag_name'])) {
        return $result;
    }
    return (object) [
        'name' => 'Jeromedia: LinkedIn Posts',
        'slug' => dirname(plugin_basename(__FILE__)),
        'version' => ltrim($release['tag_name'], 'v'),
        'author' => '<a href="https://jeromedia.com">Jeromedia</a>',
        'homepage' => 'https://jeromedia.com/wp/plugins/jm-linkedin-posts',
        'download_link' => $assets['package'],
        'sections' => [
            'description' => 'Retrieves posts from an external API and displays them via shortcode.',
            'changelog' => !empty($release['body']) ? nl2br(esc_html($release['body'])) : 'No changelog provided.',
        ],
    ];
}, 10, 3);

add_filter('upgrader_pre_download', function ($reply, $package, $upgrader) {
    if (!jm_li_is_allowed_github_url($package)) { return $reply; }
    $release = jm_li_get_latest_release();
    if (is_wp_error($release) || !($assets = jm_li_get_update_assets($release)) || !hash_equals($assets['package'], $package)) { return new WP_Error('jm_li_update_verification_failed', __('The update package could not be verified.', 'jm-linkedin-posts')); }
    $checksum_response = wp_safe_remote_get($assets['checksum'], ['timeout' => 20, 'redirection' => 0, 'limit_response_size' => 8192]);
    $checksum = is_wp_error($checksum_response) || wp_remote_retrieve_response_code($checksum_response) !== 200 ? false : jm_li_parse_update_checksum(wp_remote_retrieve_body($checksum_response));
    if ($checksum === false) { return new WP_Error('jm_li_missing_update_checksum', __('The update checksum is missing or invalid.', 'jm-linkedin-posts')); }
    $file = wp_tempnam($package);
    if (!$file) { return new WP_Error('jm_li_update_download_failed', __('The update package could not be downloaded.', 'jm-linkedin-posts')); }
    $download = jm_li_download_github_file($package, $file);
    if (is_wp_error($download) || !hash_equals($checksum, strtolower((string) hash_file('sha256', $file)))) { @unlink($file); return new WP_Error('jm_li_update_checksum_mismatch', __('The update package checksum did not match.', 'jm-linkedin-posts')); }
    return $file;
}, 10, 3);
