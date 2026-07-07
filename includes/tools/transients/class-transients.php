<?php
/**
 * Transient Audit Dashboard (Standalone)
 */

add_action('admin_menu', function () {

    add_submenu_page(
        'performance-lab',
        'Transient Audit',
        'Transient Audit',
        'manage_options',
        'wp-transient-audit',
        'wp_perf_render_transient_dashboard',
    );
});


/**
 * MAIN DASHBOARD
 */
function wp_perf_render_transient_dashboard() {

echo '<div class="notice notice-info" style="padding:12px; margin-bottom:15px;">';

echo '<h2 style="margin-top:0;">How to interpret this dashboard</h2>';

echo '<ul>';

echo '<li><strong>🟢 Normal:</strong> Expected WordPress or plugin cache. No action needed.</li>';

echo '<li><strong>🟡 Monitor:</strong> Normal but may grow over time. Check occasionally.</li>';

echo '<li><strong>🟠 Investigate:</strong> Large or unusual cache entries. Check plugin source.</li>';

echo '<li><strong>🔴 Risk:</strong> Very large or unexplained entries that may affect database performance.</li>';

echo '</ul>';

echo '</div>';


echo '<div class="notice notice-info" style="padding:12px; margin-bottom:15px;">';

echo '<h2 style="margin-top:0;">What this dashboard shows</h2>';

echo '<p><strong>Transients</strong> are cached pieces of data stored in the database to improve performance. This dashboard helps you understand how plugins and WordPress are using that cache layer.</p>';

echo '<ul>';

echo '<li><strong>Size:</strong> How much database space each cached item uses</li>';

echo '<li><strong>Age:</strong> Approximate time since the cache was created</li>';

echo '<li><strong>Expiry:</strong> When WordPress expects the cache to become invalid</li>';

echo '<li><strong>Expired:</strong> Cache is past its intended lifetime (may still exist in DB)</li>';

echo '<li><strong>Orphan:</strong> No expiry metadata found — usually leftover or mismanaged cache</li>';

echo '<li><strong>Owner:</strong> Best guess of which plugin or system created the cache</li>';

echo '</ul>';

echo '<p><em>This is a diagnostic tool. Do not delete entries manually unless you are sure what created them.</em></p>';

echo '</div>';


echo '<div class="notice notice-warning" style="padding:12px; margin-bottom:15px;">';

echo '<h3>What matters most</h3>';

echo '<ul>';

echo '<li>Large cache items (>100 KB) — may indicate heavy API or plugin usage</li>';

echo '<li>Orphaned transients — may indicate incomplete cleanup or plugin bugs</li>';

echo '<li>Very old expired entries — WP-Cron may not be running properly</li>';

echo '<li>Unknown owners — plugins not mapped in attribution system</li>';

echo '</ul>';

echo '</div>';

    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    // ----------------------------
    // Get all transient VALUES
    // ----------------------------
    $rows = $wpdb->get_results("
        SELECT option_name, option_value
        FROM {$wpdb->options}
        WHERE option_name LIKE '\_transient\_%'
          AND option_name NOT LIKE '\_transient\_timeout\_%'
    ");

    $report = [];
    $now = time();

    foreach ($rows as $row) {

        // ----------------------------
        // Detect type (site or normal)
        // ----------------------------
        $is_site = str_starts_with($row->option_name, '_site_transient_');

        $prefix = $is_site ? '_site_transient_' : '_transient_';

        $name = str_replace($prefix, '', $row->option_name);

        $size_bytes = strlen($row->option_value);

        // ----------------------------
        // Find matching timeout row
        // ----------------------------
        $timeout_key = $prefix . 'timeout_' . $name;

        $expiry = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value
                 FROM {$wpdb->options}
                 WHERE option_name = %s",
                $timeout_key
            )
        );

        $expiry = $expiry ? (int) $expiry : null;

        // ----------------------------
        // State detection
        // ----------------------------
        $expired = ($expiry && $expiry < $now);

        $orphan = is_null($expiry);

        // ----------------------------
        // Age (approximation only)
        // ----------------------------
        $age_human = $expiry
            ? human_time_diff($expiry - HOUR_IN_SECONDS, $now)
            : 'unknown';

        // ----------------------------
        // Build record
        // ----------------------------
        $report[] = [
            'name'    => $name,
            'type'    => $is_site ? 'site_transient' : 'transient',
            'size_kb' => round($size_bytes / 1024, 2),
            'expiry'  => $expiry ? date('Y-m-d H:i:s', $expiry) : 'none',
            'expired' => $expired,
            'orphan'  => $orphan,
            'age'     => $age_human,
            'owner'   => wp_perf_transient_owner($name),
        ];
    }

    // sort by size desc
    usort($report, fn($a, $b) => $b['size_kb'] <=> $a['size_kb']);

    // ----------------------------
    // UI OUTPUT
    // ----------------------------
    echo '<div class="wrap">';
    echo '<h1>Transient Audit Dashboard</h1>';

    echo '<table class="widefat striped">';
    echo '<thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Size (KB)</th>
            <th>Age</th>
            <th>Expiry</th>
            <th>Expired</th>
            <th>Orphan</th>
            <th>Owner</th>
            <th>Action</th>
        </tr>
    </thead><tbody>';

    foreach (array_slice($report, 0, 50) as $row) {

        $expired_style = $row['expired']
            ? 'color:#b30000;font-weight:bold;'
            : 'color:#2e7d32;';

        $orphan_style = $row['orphan']
            ? 'color:#c47a00;font-weight:bold;'
            : 'color:#2e7d32;';

        echo '<tr>';

        echo '<td>' . esc_html($row['name']) . '</td>';
        echo '<td>' . esc_html($row['type']) . '</td>';
        echo '<td>' . esc_html($row['size_kb']) . '</td>';
        echo '<td>' . esc_html($row['age']) . '</td>';
        echo '<td>' . esc_html($row['expiry']) . '</td>';

        echo '<td style="' . esc_attr($expired_style) . '">'
            . ($row['expired'] ? 'YES' : 'NO') .
        '</td>';

        echo '<td style="' . esc_attr($orphan_style) . '">'
            . ($row['orphan'] ? 'YES' : 'NO') .
        '</td>';

        echo '<td>' . esc_html($row['owner']) . '</td>';

        echo '<td>' . esc_html(wp_perf_transient_action($row)) . '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}


/**
 * SIMPLE OWNER DETECTION
 */
function wp_perf_transient_owner($name) {

    $map = [
        'woocommerce' => 'woocommerce',
        'elementor'   => 'elementor',
        'wpml'        => 'wpml',
        'acf'         => 'acf',
        'feed'        => 'external_feed',
        'site'        => 'wordpress_core',
        'wp'          => 'wordpress_core',
    ];

    foreach ($map as $pattern => $owner) {
        if (strpos($name, $pattern) !== false) {
            return $owner;
        }
    }

    return 'unknown';
}

function wp_perf_transient_action($row) {

    if ($row['size_kb'] > 500) {
        return '🔴 Investigate large cache usage';
    }

    if ($row['orphan']) {
        return '🟠 Orphaned — monitor or check plugin';
    }

    if ($row['size_kb'] > 100) {
        return '🟡 Monitor growth';
    }

    return '🟢 Normal';
}