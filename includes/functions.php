<?php
if (!defined('ABSPATH')) {
    exit;
}

function jm_li_get_custom_setting($key)
{
    global $wpdb;
    $table_name = $wpdb->prefix . "jmli_settings";
    return $wpdb->get_var($wpdb->prepare("SELECT jmli_value FROM $table_name WHERE jmli_name = %s", $key));
}

function jm_li_get_active_company_name()
{
    $testing_mode = jm_li_get_custom_setting('jm_li_settings_testing_mode');
    if ($testing_mode == 'yes') {
        return jm_li_get_custom_setting('jm_li_settings_testing_company_name');
    }
    return jm_li_get_company_name();
}

function jm_li_get_company_name()
{
    return jm_li_get_custom_setting('jm_li_settings_company_name');
}

function jm_li_get_testing_mode()
{
    $testing_mode = jm_li_get_custom_setting('jm_li_settings_testing_mode');
    return $testing_mode == 'yes';
}

function jm_li_get_api_base_url()
{
    $url = jm_li_validate_api_base_url(jm_li_get_custom_setting('jm_li_settings_api_base_url'));

    return is_wp_error($url) ? '' : $url;
}

function jm_li_get_allowed_api_hosts()
{
    return ['api.jeromedia.com'];
}

function jm_li_validate_api_base_url($url)
{
    $url = esc_url_raw(trim((string) $url));
    $parts = wp_parse_url($url);

    if (!$parts || empty($parts['scheme']) || empty($parts['host'])
        || strtolower($parts['scheme']) !== 'https'
        || !in_array(strtolower($parts['host']), jm_li_get_allowed_api_hosts(), true)
        || !empty($parts['user']) || !empty($parts['pass'])
        || (!empty($parts['port']) && (int) $parts['port'] !== 443)) {
        return new WP_Error('jm_li_invalid_api_url', __('The API URL is not allowed.', 'jm-linkedin-posts'));
    }

    return rtrim($url, '/');
}

function jm_li_get_api_token()
{
    return jm_li_get_custom_setting('jm_li_settings_api_token');
}

function jm_li_get_columns_limit()
{
    return absint(jm_li_get_custom_setting('jm_li_settings_column_limit'));
}

function jm_li_get_cache_timeout()
{
    return absint(jm_li_get_custom_setting('jm_li_settings_cache_timeout'));
}

function jm_li_get_post_limit()
{
    return intval(jm_li_get_custom_setting('jm_li_settings_post_limit'));
}

function jm_li_settings_register_settings()
{
    add_settings_section('jm_li_settings_section', 'Visual Configuration', null, 'jm_li_settings');
    add_settings_field('jm_li_settings_column_limit', 'Number of Columns', 'jm_li_settings_column_limit_callback', 'jm_li_settings', 'jm_li_settings_section');
    add_settings_field('jm_li_settings_post_limit', 'Number of Posts', 'jm_li_settings_post_limit_callback', 'jm_li_settings', 'jm_li_settings_section');
    add_settings_field('jm_li_settings_api_base_url', 'Api Base Url', 'jm_li_settings_api_base_url_callback', 'jm_li_settings', 'jm_li_settings_section');
    add_settings_field('jm_li_settings_testing_mode', 'Testing Mode', 'jm_li_settings_testing_mode_callback', 'jm_li_settings', 'jm_li_settings_section');
    add_settings_field('jm_li_settings_cache_timeout', 'Cache Timeout', 'jm_li_settings_cache_timeout_callback', 'jm_li_settings', 'jm_li_settings_section');
}

add_action('admin_init', 'jm_li_settings_register_settings');

function jm_li_settings_column_limit_callback()
{
    echo '<input type="number" name="jm_li_settings_column_limit" value="' . esc_attr(jm_li_get_custom_setting('jm_li_settings_column_limit')) . '" />';
}

function jm_li_settings_post_limit_callback()
{
    echo '<input type="number" name="jm_li_settings_post_limit" value="' . esc_attr(jm_li_get_custom_setting('jm_li_settings_post_limit')) . '" />';
}

function jm_li_settings_testing_mode_callback()
{
    echo '<input type="checkbox" name="jm_li_settings_testing_mode" value="' . esc_attr(jm_li_get_custom_setting('jm_li_settings_testing_mode')) . '" />';
}

function jm_li_settings_api_base_url_callback()
{
    echo '<input type="text" name="jm_li_settings_api_base_url" value="' . esc_attr(jm_li_get_custom_setting('jm_li_settings_api_base_url')) . '" />';
}

function jm_li_settings_cache_timeout_callback()
{
    echo '<input type="text" name="jm_li_settings_cache_timeout" value="' . esc_attr(jm_li_get_custom_setting('jm_li_settings_cache_timeout')) . '" />';
}

function jm_li_save_custom_settings($data)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'jmli_settings';

    $api_url = jm_li_validate_api_base_url(wp_unslash($data['jm_li_settings_api_base_url'] ?? ''));
    if (is_wp_error($api_url)) {
        return $api_url;
    }

    $fields = [
        'jm_li_settings_company_name' => strtolower(sanitize_text_field(wp_unslash($data['jm_li_settings_company_name'] ?? ''))),
        'jm_li_settings_api_base_url' => $api_url,
        'jm_li_settings_column_limit' => absint($data['jm_li_settings_column_limit'] ?? 0),
        'jm_li_settings_post_limit' => absint($data['jm_li_settings_post_limit'] ?? 0),
        'jm_li_settings_testing_mode' => isset($data['jm_li_settings_testing_mode']) ? 'yes' : 'no',
    ];

    $submitted_token = (string) wp_unslash($data['jm_li_settings_api_token'] ?? '');
    if ($submitted_token !== '' && strpos($submitted_token, '*') === false) {
        $fields['jm_li_settings_api_token'] = preg_replace('/[^A-Za-z0-9\\-._~+\\/=]/', '', $submitted_token);
    }

    if ($fields['jm_li_settings_column_limit'] > 5) {
        $fields['jm_li_settings_column_limit'] = 5;
    }
    if ($fields['jm_li_settings_post_limit'] > 10) {
        $fields['jm_li_settings_post_limit'] = 10;
    }

    foreach ($fields as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE jmli_name = %s", $key));
        if ($exists) {
            $wpdb->update(
                $table_name,
                ['jmli_value' => $value],
                ['jmli_name' => $key],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $table_name,
                ['jmli_name' => $key, 'jmli_value' => $value],
                ['%s', '%s']
            );
        }
    }

    return true;
}

function jm_li_save_custom_settings_cache($data)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'jmli_settings';

    $fields = [
        'jm_li_settings_cache_timeout' => max(1, absint($data['jm_li_settings_cache_timeout'] ?? 900)),
    ];

    foreach ($fields as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE jmli_name = %s", $key));
        if ($exists) {
            $wpdb->update(
                $table_name,
                ['jmli_value' => $value],
                ['jmli_name' => $key],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $table_name,
                ['jmli_name' => $key, 'jmli_value' => $value],
                ['%s', '%s']
            );
        }
    }

    return true;
}

function jm_li_handle_settings_submission($data)
{
    if (empty($data['jm_li_save_settings']) && empty($data['jm_li_save_settings_cache']) && empty($data['jm_li_clear_cache'])) {
        return null;
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('jm_li_forbidden', __('You are not allowed to change these settings.', 'jm-linkedin-posts'));
    }

    if (!empty($data['jm_li_save_settings'])) {
        if (empty($data['jm_li_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($data['jm_li_settings_nonce'])), 'jm_li_save_settings')) {
            return new WP_Error('jm_li_invalid_nonce', __('Invalid settings request.', 'jm-linkedin-posts'));
        }
        return jm_li_save_custom_settings($data);
    }

    if (!empty($data['jm_li_save_settings_cache'])) {
        if (empty($data['jm_li_cache_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($data['jm_li_cache_nonce'])), 'jm_li_save_cache_settings')) {
            return new WP_Error('jm_li_invalid_nonce', __('Invalid cache settings request.', 'jm-linkedin-posts'));
        }
        return jm_li_save_custom_settings_cache($data);
    }

    if (empty($data['jm_li_clear_cache_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($data['jm_li_clear_cache_nonce'])), 'jm_li_clear_cache')) {
        return new WP_Error('jm_li_invalid_nonce', __('Invalid cache clear request.', 'jm-linkedin-posts'));
    }

    jm_li_cache_clear_all();
    return true;
}
