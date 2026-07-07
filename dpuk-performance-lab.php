<?php
/**
 * WP Performance Lab
 *
 * @package           DPUK\PerformanceLab
 * @author            Daniel Pringle
 *
 * @wordpress-plugin
 * Plugin Name:       WP Performance Lab
 * Plugin URI:        https://danielpringle.co.uk
 * Description:       Performance profiling, diagnostics, and frontend observability tools for WordPress.
 * Version:           0.0.1
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Daniel Pringle
 * Author URI:        https://danielpringle.co.uk
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-performance-lab
 */

namespace DPUK_Performance_Lab;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * -----------------------------
 * Admin Menu
 * -----------------------------
 */
add_action('admin_menu', __NAMESPACE__ . '\\pl_add_options_page');

function pl_add_options_page() {
    add_menu_page(
        'Performance Lab',
        'Performance Lab',
        'manage_options',
        'performance-lab',
        __NAMESPACE__ . '\\performance_lab_render',
        'dashicons-performance',
        80
    );

    add_submenu_page(
        'performance-lab',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'performance-lab',
        __NAMESPACE__ . '\\performance_lab_render'
    );
}

function performance_lab_render() {
    ?>
    <div class="wrap">
        <h1>Performance Lab</h1>
    </div>
    <?php
}

/**
 * -----------------------------
 * Load Core Classes
 * -----------------------------
 */
require_once __DIR__ . '/includes/class-db-manager.php';
require_once __DIR__ . '/includes/tools/cron/class-cron.php';
require_once __DIR__ . '/includes/tools/transients/class-transients.php';
require_once __DIR__ . '/includes/tools/autoload-options/class-autoload-options.php';
require_once __DIR__ . '/includes/tools/browser-asset-inspector/class-browser-asset-inspector.php';


use DPUK_Performance_Lab\Includes\DPUK_DB_Manager;
use DPUK_Performance_Lab\Tools\Browser_Asset_Inspector;

/**
 * -----------------------------
 * Activation / Deactivation
 * -----------------------------
 */
register_activation_hook(__FILE__, [DPUK_DB_Manager::class, 'activate']);
register_deactivation_hook(__FILE__, [DPUK_DB_Manager::class, 'deactivate']);


add_action('plugins_loaded', function () {

    // Core tools
    new Browser_Asset_Inspector();
});