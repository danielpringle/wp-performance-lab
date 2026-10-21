<?php

/**
 * 2. PERFORMANCE TRACKING ENGINE
 */
add_action( 'cron_action_scheduler_before_execute', 'wpcpl_start_timer', 10, 2 );

function wpcpl_start_timer( $hook, $args ) {
	global $wpcpl_timers;

	if ( ! is_array( $wpcpl_timers ) ) {
		$wpcpl_timers = [];
	}

	$key = md5( $hook . serialize( $args ) );

	$wpcpl_timers[ $key ] = [
		'time'   => microtime( true ),
		'memory' => memory_get_usage( true ),
	];
}


add_action( 'cron_action_scheduler_after_execute', 'wpcpl_stop_timer', 10, 2 );

function wpcpl_stop_timer( $hook, $args ) {

	global $wpdb, $wpcpl_timers;

	$key = md5( $hook . serialize( $args ) );

	if ( ! isset( $wpcpl_timers[ $key ] ) ) {
		return;
	}

	$duration = round(
		microtime( true ) - $wpcpl_timers[ $key ]['time'],
		4
	);

	$memory = round(
		( memory_get_usage( true ) - $wpcpl_timers[ $key ]['memory'] ) / 1048576,
		2
	);

	unset( $wpcpl_timers[ $key ] );

	$table = $wpdb->prefix . 'pl_action_scheduler_profiler';

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE hook_name = %s",
			$hook
		)
	);

	if ( $existing ) {

		$runs = $existing->runs + 1;

		$wpdb->update(
			$table,
			[
				'runs'              => $runs,
				'total_duration'    => $existing->total_duration + $duration,
				'average_duration'  => ( $existing->total_duration + $duration ) / $runs,
				'max_duration'      => max( $existing->max_duration, $duration ),
				'last_duration'     => $duration,
				'max_memory'        => max( $existing->max_memory, $memory ),
				'last_memory'       => $memory,
				'successes'         => $existing->successes + 1,
				'last_run'          => current_time( 'mysql' ),
			],
			[
				'hook_name' => $hook,
			]
		);

	} else {

		$wpdb->insert(
			$table,
			[
				'hook_name'         => $hook,
				'runs'              => 1,
				'total_duration'    => $duration,
				'average_duration'  => $duration,
				'max_duration'      => $duration,
				'last_duration'     => $duration,
				'max_memory'        => $memory,
				'last_memory'       => $memory,
				'successes'         => 1,
				'failures'          => 0,
				'last_run'          => current_time( 'mysql' ),
			]
		);

	}
}


/**
 * 3. DASHBOARD WIDGET TO VIEW DATA
 */
add_action( 'admin_menu', 'wpcpl_add_admin_page' );

function wpcpl_add_admin_page() {
    add_submenu_page(
        'performance-lab',              // Parent menu slug
        'Action Sheduler',             // Page title
        'Action Sheduler',             // Menu title
        'manage_options',               // Capability
        'pl-action-scheduler-performance',       // Menu slug
        'wpcpl_render_dashboard_widget' // Callback
    );
}



function wpcpl_render_dashboard_widget() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'pl_action_scheduler_profiler';

	$test_url = wp_nonce_url(
		admin_url( 'admin.php?action=wpcpl_run_test' ),
		'wpcpl_run_test'
	);

	echo '<div class="wrap">';

	echo '<h1>Action Sheduler</h1>';

	echo '<p class="description">';
	echo 'Monitor Action Scheduler performance to identify slow-running hooks, excessive memory usage, and failed jobs.';
	echo '</p>';

	if ( isset( $_GET['wpcpl_success'] ) ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>Success!</strong> The test Action Scheduler job completed successfully.</p>';
		echo '</div>';
	}

	echo '<div class="notice notice-info inline">';
	echo '<p><strong>About this report</strong></p>';
	echo '<p>';
	echo 'This profiler records execution statistics for Action Scheduler hooks. '
		. 'Use it to identify long-running background tasks that may affect site performance.';
	echo '</p>';

	echo '<ul style="list-style:disc;padding-left:20px;margin:10px 0 0;">';
	echo '<li><strong>Average Duration</strong> &ndash; Typical execution time.</li>';
	echo '<li><strong>Max Duration</strong> &ndash; Slowest recorded execution.</li>';
	echo '<li><strong>Peak Memory</strong> &ndash; Highest memory usage.</li>';
	echo '<li><strong>Failed Runs</strong> &ndash; Number of recorded failures.</li>';
	echo '</ul>';
	echo '</div>';

	echo '<p>';
	echo '<a class="button button-primary" href="' . esc_url( $test_url ) . '">';
	echo 'Run Test Job';
	echo '</a> ';
	echo '<span class="description">Run a sample Action Scheduler task to verify profiling is working.</span>';
	echo '</p>';

	$results = $wpdb->get_results(
		"SELECT
			hook_name,
			runs,
			average_duration,
			max_duration,
			last_duration,
			max_memory,
			failures,
			last_run
		FROM {$table_name}
		ORDER BY average_duration DESC
		LIMIT 20"
	);

	if ( empty( $results ) ) {

		echo '<div class="notice notice-warning inline">';
		echo '<p>No Action Scheduler jobs have been profiled yet. Run the test job above or wait for scheduled actions to execute.</p>';
		echo '</div>';

		echo '</div>';

		return;
	}

	$total_runs = array_sum( wp_list_pluck( $results, 'runs' ) );

	echo '<p>';
	printf(
		'<strong>%1$s hooks profiled</strong> across %2$s recorded executions.',
		number_format_i18n( count( $results ) ),
		number_format_i18n( $total_runs )
	);
	echo '</p>';

	echo '<table class="widefat striped">';
	echo '<thead>';
	echo '<tr>';
	echo '<th>Hook</th>';
	echo '<th>Runs</th>';
	echo '<th>Avg Duration</th>';
	echo '<th>Max Duration</th>';
	echo '<th>Last Duration</th>';
	echo '<th>Peak Memory</th>';
	echo '<th>Failed Runs</th>';
	echo '<th>Last Run</th>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';

	foreach ( $results as $row ) {

		$avg_style = '';

		if ( $row->average_duration > 5 ) {
			$avg_style = 'style="color:#d63638;font-weight:600;"';
		} elseif ( $row->average_duration > 2 ) {
			$avg_style = 'style="color:#dba617;font-weight:600;"';
		}

		$fail_style = $row->failures > 0
			? 'style="color:#d63638;font-weight:600;"'
			: '';

		echo '<tr>';

		echo '<td><code>' . esc_html( $row->hook_name ) . '</code></td>';

		echo '<td>' . number_format_i18n( $row->runs ) . '</td>';

		echo '<td ' . $avg_style . '>';
		echo esc_html( number_format( $row->average_duration, 3 ) ) . ' s';
		echo '</td>';

		echo '<td>';
		echo esc_html( number_format( $row->max_duration, 3 ) ) . ' s';
		echo '</td>';

		echo '<td>';
		echo esc_html( number_format( $row->last_duration, 3 ) ) . ' s';
		echo '</td>';

		echo '<td>';
		echo esc_html( number_format( $row->max_memory, 2 ) ) . ' MB';
		echo '</td>';

		echo '<td ' . $fail_style . '>';
		echo number_format_i18n( $row->failures );
		echo '</td>';

		echo '<td>';
		echo esc_html(
			sprintf(
				'%s ago',
				human_time_diff(
					strtotime( $row->last_run ),
					current_time( 'timestamp' )
				)
			)
		);
		echo '<br><span style="color:#666;font-size:12px;">';
		echo esc_html( $row->last_run );
		echo '</span>';
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';

	echo '<p class="description" style="margin-top:12px;">';
	echo '<strong>Thresholds:</strong> ';
	echo '<span style="color:#dba617;">2&ndash;5 seconds</span> may indicate a performance issue. ';
	echo '<span style="color:#d63638;">Over 5 seconds</span> should be investigated.';
	echo '</p>';

	echo '</div>';
}


/**
 * 4. AUTOMATIC CLEANUP (Keep database light)
 * Scheduled via the standard WP Cron system to purge logs older than 7 days
 */
if ( ! wp_next_scheduled( 'pl_cleanup_action_schedular_logs' ) ) {
    wp_schedule_event( time(), 'daily', 'pl_cleanup_action_schedular_logs' );
}

add_action( 'pl_cleanup_action_schedular_logs', 'purge_action_schedular_database_logs' );
function purge_action_schedular_database_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pl_action_scheduler_profiler';
    $wpdb->query( "DELETE FROM $table_name WHERE run_time < NOW() - INTERVAL 7 DAY" );
}





/**
 * 5. LEARNING EXPERIMENT: TEST ACTIONS & TRIGGERS
 */

// Register our custom test hook with WordPress
add_action( 'wpcpl_heavy_learning_test', 'wpcpl_simulate_heavy_cron' );

function wpcpl_simulate_heavy_cron() {
    // 1. Simulate Memory Usage (Create a massive text array)
    $memory_hog = [];
    for ( $i = 0; $i < 150000; $i++ ) {
        $memory_hog[] = 'Learning WordPress Performance is fun! ' . $i;
    }
    
    // 2. Simulate Time Delay (Pause for 0.4 seconds)
    usleep( 400000 ); 
    
    // Clear variable explicitly to be nice to the server afterwards
    unset( $memory_hog );
}

// Listen for the custom "Run Test" button click in the dashboard
add_action( 'admin_init', 'wpcpl_handle_manual_trigger' );
function wpcpl_handle_manual_trigger() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'wpcpl_run_test' ) {
        // Check security permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        
        // Manually trigger the exact internal WordPress core cron execution cycle
        do_action( 'cron_action_scheduler_before_execute', 'wpcpl_heavy_learning_test', [] );
        do_action( 'wpcpl_heavy_learning_test' );
        do_action( 'cron_action_scheduler_after_execute', 'wpcpl_heavy_learning_test', [] );
        
        // Redirect back to dashboard to clear the URL parameters
       wp_safe_redirect(
            add_query_arg(
                array(
                    'page'           => 'pl-action-scheduler-performance',
                    'wpcpl_success'  => 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
