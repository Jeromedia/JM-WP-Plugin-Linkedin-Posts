<?php
if (!defined('ABSPATH')) {
    exit;
}

function jm_li_api_error($code, $message)
{
    return new WP_Error($code, $message);
}

function jm_li_api_request($path)
{
    $base_url = jm_li_validate_api_base_url(jm_li_get_custom_setting('jm_li_settings_api_base_url'));
    if (is_wp_error($base_url)) {
        return $base_url;
    }

    $url = $base_url . '/' . ltrim($path, '/');
    $request_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    if (!in_array($request_host, jm_li_get_allowed_api_hosts(), true)) {
        return jm_li_api_error('jm_li_invalid_api_host', __('The API host is not allowed.', 'jm-linkedin-posts'));
    }

    $token = jm_li_get_api_token();
    $args = [
        'timeout' => 15,
        'redirection' => 0,
        'limit_response_size' => 1024 * 1024,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
    ];
    $response = wp_safe_remote_get($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status >= 300 && $status < 400) {
        return jm_li_api_error('jm_li_api_redirect_rejected', __('The API redirect was rejected.', 'jm-linkedin-posts'));
    }
    if ($status < 200 || $status >= 300) {
        return jm_li_api_error('jm_li_api_request_failed', __('The API request failed.', 'jm-linkedin-posts'));
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) {
        return jm_li_api_error('jm_li_invalid_api_response', __('The API returned an invalid response.', 'jm-linkedin-posts'));
    }

    return $data;
}

function jm_li_fetch_connection_to_jeromedia()
{
    $company = rawurlencode(jm_li_get_active_company_name());
    return jm_li_api_request('connection/' . $company);
}

function jm_li_fetch_posts()
{
    $company = jm_li_get_active_company_name();
    $cache_key = 'jmli_posts_' . md5((string) $company);
    $cached = jm_li_cache_get($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $lock_key = 'jmli_posts_lock_' . md5((string) $company);
    if (!add_option($lock_key, time(), '', 'no')) {
        $locked_at = absint(get_option($lock_key));
        if ($locked_at && $locked_at < (time() - 60)) {
            delete_option($lock_key);
        }
        if (!add_option($lock_key, time(), '', 'no')) {
            return jm_li_api_error('jm_li_api_request_in_progress', __('The posts are being refreshed. Please try again shortly.', 'jm-linkedin-posts'));
        }
    }

    try {
        $posts = jm_li_api_request('posts/' . rawurlencode($company));
        if (!is_wp_error($posts) && is_array($posts)) {
            $timeout = absint(jm_li_get_cache_timeout());
            jm_li_cache_set($cache_key, $posts, $timeout > 0 ? $timeout : 900);
        }

        return $posts;
    } finally {
        delete_option($lock_key);
    }
}
