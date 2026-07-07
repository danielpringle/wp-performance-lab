<?php

add_action('admin_menu', function () {

    add_submenu_page(
        'performance-lab',
        'WP Cron Dashboard',
        'WP Cron Dashboard',
        'manage_options',
        'wp-cron-dashboard',
        'wp_cron_dashboard_render'
    );


});

function wp_cron_dashboard_render() {
    if (!current_user_can('manage_options')) return;

    $cron = _get_cron_array();
    $schedules = wp_get_schedules();
    $now = time();

    echo '<div class="wrap"><h1>WP Cron Dashboard</h1>';

    echo '<style>
        .cron-badge {
            padding: 4px 8px;
            border-radius: 4px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }
        .ok { background: #46b450; }
        .warn { background: #ffb900; }
        .bad { background: #dc3232; }
        table td { vertical-align: top; }
        code { font-size: 11px; }
    </style>';

    echo '<table class="widefat fixed striped">';
    echo '<thead>
        <tr>
            <th>Hook</th>
            <th>Schedule</th>
            <th>Next Run</th>
            <th>Time Until Run</th>
            <th>Arguments</th>
            <th>Duplicate Count</th>
            <th>Status</th>
        </tr>
    </thead><tbody>';

    if (empty($cron)) {
        echo '<tr><td colspan="7">No cron events found</td></tr>';
        echo '</tbody></table></div>';
        return;
    }

    foreach ($cron as $timestamp => $hooks) {
        foreach ($hooks as $hook => $events) {

            $duplicate_count = count($events);

            foreach ($events as $event) {

                $args = $event['args'] ?? [];
                $schedule_key = $event['schedule'] ?? '';

                $schedule = ($schedule_key && isset($schedules[$schedule_key]))
                    ? $schedules[$schedule_key]['display']
                    : 'single';

                $diff = $timestamp - $now;

                $time_until = $diff >= 0
                    ? human_time_diff($now, $timestamp) . ' remaining'
                    : human_time_diff($timestamp, $now) . ' overdue';

                $next_run = date('Y-m-d H:i:s', $timestamp);

                $status = wp_cron_health_status($hook, $timestamp, $schedule_key, $schedules, $duplicate_count);

                echo '<tr>';

                echo '<td><strong>' . esc_html($hook) . '</strong></td>';
                echo '<td>' . esc_html($schedule) . '</td>';
                echo '<td>' . esc_html($next_run) . '</td>';
                echo '<td>' . esc_html($time_until) . '</td>';
                echo '<td><code>' . esc_html(json_encode($args)) . '</code></td>';
                echo '<td>' . esc_html($duplicate_count) . '</td>';
                echo '<td>' . wp_cron_status_badge($status) . '</td>';

                echo '</tr>';
            }
        }
    }

    echo '</tbody></table></div>';
}

/**
 * STATUS ENGINE
 */
function wp_cron_health_status($hook, $timestamp, $schedule_key, $schedules, $duplicate_count) {

    $issues = [];

    // ORPHANED
    if (!has_action($hook)) {
        $issues[] = 'ORPHANED';
    }

    // TOO FREQUENT (< 60s interval)
    if ($schedule_key && isset($schedules[$schedule_key])) {
        $interval = $schedules[$schedule_key]['interval'] ?? 0;
        if ($interval > 0 && $interval < 60) {
            $issues[] = 'TOO_FREQUENT';
        }
    }

    // OVERDUE (> 5 min late)
    if ($timestamp < time() - 300) {
        $issues[] = 'OVERDUE';
    }

    // DUPLICATED
    if ($duplicate_count > 5) {
        $issues[] = 'DUPLICATED';
    }

    return empty($issues) ? 'OK' : implode(',', $issues);
}

/**
 * BADGE RENDERING
 */
function wp_cron_status_badge($status) {

    $class = 'ok';

    if ($status === 'OK') {
        $label = 'OK';
        $class = 'ok';
    } elseif (str_contains($status, 'ORPHANED') || str_contains($status, 'OVERDUE')) {
        $label = $status;
        $class = 'bad';
    } else {
        $label = $status;
        $class = 'warn';
    }

    return '<span class="cron-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}