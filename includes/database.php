<?php

function jm_li_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "jmli_settings";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        jmli_name VARCHAR(255) NOT NULL,
        jmli_value VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Insert default testing company name
    $default_settings = [
        'jm_li_settings_testing_company_name' => 'jeromedia',
        'jm_li_settings_api_base_url' => 'https://api.jeromedia.com/linkedin',
        'jm_li_settings_company_name' => '',
        'jm_li_settings_api_token' => '',
        'jm_li_settings_column_limit' => '3',
        'jm_li_settings_post_limit' => '3',
        'jm_li_settings_testing_mode' => 'yes',
        'jm_li_settings_cache_timeout' => '3600', // 3600 in seconds = 1 hour
    ];

    foreach ($default_settings as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE jmli_name = %s", $key));
        if (!$exists) {
            $wpdb->insert($table_name, ['jmli_name' => $key, 'jmli_value' => $value], ['%s', '%s']);
        }
    }
}
function jm_li_create_cache_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "jmli_cache";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cache_key VARCHAR(255) NOT NULL UNIQUE,
        cache_value LONGTEXT NOT NULL,
        expires_at INT(11) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

}
