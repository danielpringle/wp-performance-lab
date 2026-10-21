<?php

namespace DPUK_Performance_Lab\Includes;

class DPUK_DB_Manager {

    const DB_VERSION = '1.0.0';
    const OPTION_KEY = 'pl_db_version';

    public static function activate(): void
    {
        self::install();
    }


    private static function install(){
        
        self::create_browser_asset_reports_table();
        self::create_action_scheduler_table();
        update_option(self::OPTION_KEY, self::DB_VERSION);
        
    }

    private static function create_browser_asset_reports_table(){
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'pl_browser_asset_reports';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            report LONGTEXT NOT NULL,
            collected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url_idx (url(191)),
            KEY collected_at_idx (collected_at)
        ) $charset;";

        dbDelta($sql);
    }

    private static function create_action_scheduler_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix . 'pl_action_scheduler_profiler';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            hook_name varchar(191) NOT NULL,
            runs bigint(20) unsigned NOT NULL DEFAULT 0,
            total_duration decimal(10,4) NOT NULL DEFAULT 0,
            average_duration decimal(10,4) NOT NULL DEFAULT 0,
            max_duration decimal(10,4) NOT NULL DEFAULT 0,
            last_duration decimal(10,4) NOT NULL DEFAULT 0,
            max_memory decimal(10,2) NOT NULL DEFAULT 0,
            last_memory decimal(10,2) NOT NULL DEFAULT 0,
            successes bigint(20) unsigned NOT NULL DEFAULT 0,
            failures bigint(20) unsigned NOT NULL DEFAULT 0,
            last_run datetime DEFAULT NULL,
            last_failure datetime DEFAULT NULL,
            PRIMARY KEY  (hook_name)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    public static function deactivate() {
        // Nothing to do on deactivation.
        wp_clear_scheduled_hook( 'pl_cleanup_action_schedular_logs' );
    }
}