<?php

/**
 * Uninstall script for the Cal.com WordPress plugin.
 *
 * Removes all plugin data: options, transients, user meta, and post meta.
 *
 * @package Cal.com
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Abort if WordPress core functions are not available
if (!function_exists('delete_option')) {
    exit;
}

// Delete plugin options stored via get_option / update_option.
delete_option('calcom_api_key');
delete_option('calcom_selected_event_type');
delete_option('cal_com_integrations');
delete_option('cal_com_woocommerce_settings');
delete_option('cal_com_fluentforms_settings');
delete_option('cal_com_wpforms_settings');
delete_option('cal_com_contact_form_7_settings');

// Delete transients
delete_transient('calcom_event_types');

// Delete user meta for all users.
$calcom_blog_users = get_users(
    array(
        'blog_id' => 1,
        'fields'  => 'ID',
    )
);

if ($calcom_blog_users) {
    foreach ($calcom_blog_users as $calcom_user_id) {
        delete_user_meta($calcom_user_id, '_calcom_booking_url');
        delete_user_meta($calcom_user_id, '_calcom_booking_button_text');
    }
}

// Delete post meta for WooCommerce products and any other posts.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, WordPress.DB.SlowDBQuery -- Direct DB access needed for bulk post meta cleanup on uninstall.
global $wpdb;

$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => '_calcom_event_type_id'),
    array('%s')
);
$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => '_calcom_event_url'),
    array('%s')
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, WordPress.DB.SlowDBQuery
