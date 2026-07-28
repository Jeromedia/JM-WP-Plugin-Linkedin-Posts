<?php

$result = jm_li_handle_settings_submission($_POST);
if (is_wp_error($result)) {
    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
} elseif ($result === true && isset($_POST['jm_li_save_settings'])) {
    echo '<div class="updated"><p>Settings saved.</p></div>';
} elseif ($result === true && isset($_POST['jm_li_save_settings_cache'])) {
    echo '<div class="updated"><p>Cache saved.</p></div>';
} elseif ($result === true && isset($_POST['jm_li_clear_cache'])) {
    echo '<div class="updated"><p>Cache cleared successfully.</p></div>';
}

$column_limit = jm_li_get_custom_setting('jm_li_settings_column_limit');
$post_limit = jm_li_get_custom_setting('jm_li_settings_post_limit');
$testing_mode = jm_li_get_custom_setting('jm_li_settings_testing_mode');
$cache_timeout = jm_li_get_custom_setting('jm_li_settings_cache_timeout');

$company_name = jm_li_get_custom_setting('jm_li_settings_company_name');
$api_token = jm_li_get_custom_setting('jm_li_settings_api_token');
if (!empty($api_token)) {
    $api_token = substr($api_token, 0, 7) . str_repeat('*', 10);
}
$api_base_url = jm_li_get_custom_setting('jm_li_settings_api_base_url');
?>

<div id="jm-li-settings" class="wrap">
    <h1>JM LinkedIn Posts - Settings</h1>
    <p>Welcome to the Settings of JM LinkedIn Posts.</p>

    <form method="post">
        <?php wp_nonce_field('jm_li_save_settings', 'jm_li_settings_nonce'); ?>
        <h2>Company Details</h2>
        <div class="jmli-settings-section">
            <div>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="jm_li_settings_company_name">Company Name (lowercase)</label></th>
                        <td><input type="text" name="jm_li_settings_company_name"
                                value="<?php echo esc_attr($company_name); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jm_li_settings_api_token">API Token</label></th>
                        <td><input type="text" name="jm_li_settings_api_token"
                                value="<?php echo esc_attr($api_token); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jm_li_settings_api_base_url">API Base URL</label></th>
                        <td>
                            <?php
                            if (jm_li_get_testing_mode()) { ?>
                                <input type="url" name="jm_li_settings_api_base_url"
                                    value="<?php echo esc_attr($api_base_url); ?>" />
                            </td>
                        </tr>

                    <?php } else { ?>
                        <div class="jmli-fake-input">
                            <?php echo esc_html($api_base_url); ?></td>
                        </div>
                        <input type="hidden" name="jm_li_settings_api_base_url"
                            value="<?php echo esc_attr($api_base_url); ?>">
                        </tr>
                    <?php } ?>
                </table>
            </div>
            <div>

            </div>
        </div>

        <h2>Display</h2>
        <div class="jmli-settings-section">
            <div>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="jm_li_settings_column_limit">Number of Columns (max: 5)</label></th>
                        <td><input type="text" name="jm_li_settings_column_limit"
                                value="<?php echo esc_attr($column_limit); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jm_li_settings_post_limit">Number of Posts (max: 10)</label></th>
                        <td><input type="number" name="jm_li_settings_post_limit"
                                value="<?php echo esc_attr($post_limit); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jm_li_settings_testing_mode">Testing Mode</label></th>
                        <td><input type="checkbox" name="jm_li_settings_testing_mode" value="yes" <?php checked($testing_mode, 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <div style="display: flex; justify-content: end;">
                                <?php submit_button('Save Settings', 'primary', 'jm_li_save_settings'); ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </form>
    <h2>Cache</h2>
    <div class="jmli-settings-section">
        <div>
            <form method="post">
                <?php wp_nonce_field('jm_li_save_cache_settings', 'jm_li_cache_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="jm_li_settings_cache_timeout">Cache Timeout (in sec)</label></th>
                        <td><input type="text" name="jm_li_settings_cache_timeout"
                                value="<?php echo esc_attr($cache_timeout); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <div style="display: flex; justify-content: end;">
                                <?php submit_button('Save Cache', 'primary', 'jm_li_save_settings_cache'); ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </form>
            <form method="post" action="">
                <?php wp_nonce_field('jm_li_clear_cache', 'jm_li_clear_cache_nonce'); ?>
                <input type="hidden" name="jm_li_clear_cache" value="1">
                <?php submit_button('Clear Cache', 'secondary', 'clear_cache_button'); ?>
            </form>
        </div>

    </div>
</div>
