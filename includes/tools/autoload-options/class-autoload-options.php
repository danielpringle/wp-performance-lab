<?php

// ------------------------------
// AUDIT CORE FUNCTION
// ------------------------------
function wp_performance_autoload_audit() {
    global $wpdb;

    // More reliable autoload detection
    $rows = $wpdb->get_results("
        SELECT option_name, option_value
        FROM {$wpdb->options}
        WHERE autoload != 'no'
    ");

    $report = [
        'total_size' => 0,
        'count'      => 0,
        'options'    => [],
    ];

    if (empty($rows)) {
        return $report;
    }

    foreach ($rows as $row) {
        $value = maybe_unserialize($row->option_value);

        $size = strlen($row->option_value);

        $info = [
            'name'        => $row->option_name,
            'size_bytes'  => $size,
            'size_kb'     => round($size / 1024, 2),
            'type'        => gettype($value),
            'serialized'  => is_serialized($row->option_value),
            'array_count' => null,
            'depth'       => null,
            'rating'      => 'good',
            'impact'      => 0,
            'recommendation' => '',
        ];

        // Array analysis
        if (is_array($value)) {
            $info['array_count'] = count($value);
            $info['depth'] = wp_performance_array_depth($value);
        }

        // ------------------------------
        // IMPACT SCORE (IMPORTANT)
        // ------------------------------
        $impact = 0;

        if ($size > 500000) $impact += 100;
        elseif ($size > 100000) $impact += 50;
        elseif ($size > 50000)  $impact += 20;

        if (!empty($info['depth']) && $info['depth'] > 5) {
            $impact += 20;
        }

        // Cache / transient detection (PHP 7 safe)
        if (
            strpos($row->option_name, 'cache') !== false ||
            strpos($row->option_name, 'transient') !== false ||
            strpos($row->option_name, 'session') !== false
        ) {
            $info['likely_cache'] = true;
            $impact -= 10;
        }

        $info['impact'] = $impact;

        // ------------------------------
        // RATING
        // ------------------------------
        if ($impact > 100) {
            $info['rating'] = 'critical';
        } elseif ($impact > 50) {
            $info['rating'] = 'warning';
        }

        // ------------------------------
        // RECOMMENDATIONS
        // ------------------------------
        if (!empty($info['likely_cache'])) {
            $info['recommendation'] = 'Safe cache/transient data';
        } elseif ($size > 100000) {
            $info['recommendation'] = 'Investigate plugin generating large option';
        } elseif (strpos($row->option_name, 'woocommerce') !== false) {
            $info['recommendation'] = 'WooCommerce core data (usually safe)';
        } elseif ($row->option_name === 'cron') {
            $info['recommendation'] = 'WordPress scheduling system (normal)';
        } else {
            $info['recommendation'] = 'Review if option is still needed';
        }

        $report['options'][] = $info;

        $report['total_size'] += $size;
        $report['count']++;
    }

    // Sort by impact (NOT size — more useful)
    usort($report['options'], function ($a, $b) {
        return $b['impact'] <=> $a['impact'];
    });

    $report['total_size_mb'] = round($report['total_size'] / 1024 / 1024, 2);

    return $report;
}


// ------------------------------
// ARRAY DEPTH HELPER
// ------------------------------
function wp_performance_array_depth($array) {
    $max_depth = 1;

    foreach ($array as $value) {
        if (is_array($value)) {
            $depth = wp_performance_array_depth($value) + 1;
            if ($depth > $max_depth) {
                $max_depth = $depth;
            }
        }
    }

    return $max_depth;
}


// ------------------------------
// ADMIN MENU
// ------------------------------
add_action('admin_menu', function () {

    add_submenu_page(
        'performance-lab',
        'Performance Audit',
        'Performance Audit',
        'manage_options',
        'wp-performance-audit',
        'wp_performance_render_dashboard',
    );
});


// ------------------------------
// DASHBOARD UI (IMPROVED)
// ------------------------------
function wp_performance_render_dashboard() {

echo '<div class="notice notice-info" style="padding:10px;">';

echo '<h2>How to read this report</h2>';

echo '<ul>';

echo '<li><strong>Impact score:</strong> Estimated cost of loading this option on every request (higher = more potential performance impact)</li>';

echo '<li><strong>Critical:</strong> Very large or complex option that may affect memory or load time</li>';

echo '<li><strong>Warning:</strong> Moderately large option worth reviewing</li>';

echo '<li><strong>Good:</strong> Normal WordPress or plugin configuration</li>';

echo '<li><strong>Autoload:</strong> Data loaded on every page request (frontend + admin)</li>';

echo '</ul>';

echo '</div>';

echo '<div class="notice notice-secondary" style="padding:10px;">';

echo '<h3>Impact scoring model</h3>';

echo '<p>Impact is calculated using:</p>';

echo '<pre>
Size (KB)
+ Nested structure depth
+ Array complexity
- Cache/transient reduction
</pre>';

echo '<p><em>This is a heuristic model, not a WordPress metric.</em></p>';

echo '</div>';

echo '<p>
<span style="color:red;">■ Critical</span> 
<span style="color:orange;">■ Warning</span> 
<span style="color:green;">■ Good</span>
</p>';

echo '<div class="notice notice-warning" style="padding:10px;">';

echo '<h3>What to do with this report</h3>';

echo '<ol>';

echo '<li>Focus on top 5 “Impact” items first</li>';
echo '<li>Ignore WordPress core entries (cron, rewrite_rules)</li>';
echo '<li>Investigate large plugin options (>100 KB)</li>';
echo '<li>Look for unused plugins contributing data</li>';

echo '</ol>';

echo '</div>';

    if (!current_user_can('manage_options')) {
        return;
    }

    $report = wp_performance_autoload_audit();

    echo '<div class="wrap">';
    echo '<h1>WordPress Autoload Performance Audit</h1>';

    // SUMMARY
    echo '<h2>Summary</h2>';
    echo '<p><strong>Total Size:</strong> ' . esc_html($report['total_size_mb']) . ' MB</p>';
    echo '<p><strong>Total Options:</strong> ' . esc_html($report['count']) . '</p>';

    // TOP ISSUES (more useful than raw list)
    echo '<h2>Top Issues (by impact)</h2>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>
            <th>Option</th>
            <th>Size (KB)</th>
            <th>Impact</th>
            <th>Rating</th>
            <th>Recommendation</th>
          </tr></thead><tbody>';

    $top = array_slice($report['options'], 0, 20);

foreach ($top as $opt) {

    echo '<tr>';

    echo '<td>' . esc_html($opt['name']) . '</td>';

    echo '<td style="' . esc_attr(wp_perf_color('size', $opt['size_kb'])) . '">'
        . esc_html($opt['size_kb']) .
    '</td>';

    echo '<td style="' . esc_attr(wp_perf_color('impact', $opt['impact'])) . '">'
        . esc_html($opt['impact']) .
    '</td>';

    echo '<td style="' . esc_attr(wp_perf_color('rating', $opt['rating'])) . '">'
        . esc_html($opt['rating']) .
    '</td>';

    echo '<td>' . esc_html($opt['recommendation']) . '</td>';

    echo '</tr>';
}

    echo '</tbody></table>';

    echo '</div>';
}


function wp_perf_color($type, $value) {

    switch ($type) {

        case 'rating':
            return match ($value) {
                'critical' => 'color:#b30000;font-weight:bold;',
                'warning'  => 'color:#c47a00;font-weight:bold;',
                default    => 'color:#2e7d32;',
            };

        case 'size':
            if ($value > 500) return 'color:#b30000;font-weight:bold;';
            if ($value > 100) return 'color:#c47a00;';
            return 'color:#2e7d32;';

        case 'impact':
            if ($value > 100) return 'background:#ffcccc;';
            if ($value > 50) return 'background:#fff2cc;';
            return 'background:#e6ffe6;';

        default:
            return '';
    }
}