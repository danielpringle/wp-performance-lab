<?php
/**
 * Uninstall script for DPUK Performance Lab
 *
 * Runs ONLY when the plugin is deleted from WordPress admin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Optional: Only proceed if user wants full cleanup.
 * (You can extend this later with a setting like "keep data")
 */

$table_reports = $wpdb->prefix . 'pl_browser_asset_reports';
$table_cron    = $wpdb->prefix . 'pl_cron_logs';

/**
 * Drop Browser Asset Reports table
 */
$wpdb->query(
    "DROP TABLE IF EXISTS `{$table_reports}`"
);

/**
 * Drop Cron Logs table
 */
$wpdb->query(
    "DROP TABLE IF EXISTS `{$table_cron}`"
);

/**
 * OPTIONAL CLEANUP (recommended in real plugins)
 * Uncomment if you store options later
 */

delete_option('pl_db_version');
// delete_transient('dpuk_performance_cache');
