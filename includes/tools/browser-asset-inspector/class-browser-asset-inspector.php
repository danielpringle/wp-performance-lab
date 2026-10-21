<?php
/**
 * Browser Asset Inspector Tool
 *
 * Collects and displays frontend asset and performance data for WordPress pages.
 * Part of the DPUK Performance Lab plugin.
 *
 * @package DPUK_Performance_Lab\Tools
 */

namespace DPUK_Performance_Lab\Tools;

/**
 * Class Browser_Asset_Inspector
 *
 * Handles collection, storage, and admin display of browser asset reports.
 * Provides REST API endpoint and frontend collector injection.
 */
class Browser_Asset_Inspector {
    
    /**
     * Option name for enabling the frontend asset collector.
     *
     * @var string
     */
    private $option_enable_collector = 'pl_enable_collector';

    /**
     * Constructor.
     *
     * Registers all WordPress hooks for settings, admin UI, REST API, and frontend collector injection.
     * - Registers plugin settings and admin menu.
     * - Enqueues a dummy script to localize the REST nonce for the collector.
     * - Registers REST API endpoint for asset reports.
     * - Injects the frontend collector script in the footer.
     */
    public function __construct() {
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Enqueue a dummy script to ensure WordPress outputs the REST API nonce to the frontend.
        // This allows the JavaScript collector to securely authenticate POST requests to the REST API,
        // even though the script itself does not load any assets. The nonce is passed via wp_localize_script.
        add_action('wp_enqueue_scripts', function() {
            wp_register_script('dpuk-perf-collector', false);
            wp_enqueue_script('dpuk-perf-collector');
            wp_localize_script('dpuk-perf-collector', 'dpukPerfLab', [
                'restNonce' => wp_create_nonce('wp_rest')
            ]);
        });

        /**
         * Admin UI
         */
        add_action('admin_menu', [$this, 'add_admin_page']);

        /**
         * REST API endpoint
         */
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        /**
         * Inject frontend collector
         */
        add_action('wp_footer', [$this, 'inject_collector_script'], 999);
    }

    /**
     * ==========================================
     * ADMIN MENU
     * ==========================================
     */
    public function add_admin_page() {

        add_submenu_page(
            'performance-lab',
            'Browser Asset Inspector',
            'Asset Inspector',
            'manage_options',
            'pl-browser-asset-inspector',
            [$this, 'render_admin_page'],
        );
    }

    /**
     * ==========================================
     * REGISTER SETTINGS
     * ==========================================
     */
    public function register_settings() {
        register_setting('pl_browser_asset_inspector', $this->option_enable_collector);
        add_settings_section('pl_collector_section', '', null, 'pl_browser_asset_inspector');
        add_settings_field(
            $this->option_enable_collector,
            'Enable Asset Collector',
            function() {
                $enabled = get_option($this->option_enable_collector, '1');
                echo '<input type="checkbox" name="' . esc_attr($this->option_enable_collector) . '" value="1"' . checked($enabled, '1', false) . '> ';
                echo '<span>Inject the frontend collector script (required for collecting new reports)</span>';
            },
            'pl_browser_asset_inspector',
            'pl_collector_section'
        );
    }

    /**
     * Render the main admin dashboard page for the Browser Asset Inspector.
     *
     * Displays collected reports, settings form, and options to clear data.
     * Handles page selection and report rendering for individual URLs.
     */
    public function render_admin_page() {

        global $wpdb;
        $table_name = $wpdb->prefix . 'pl_browser_asset_reports';
        $rows = $wpdb->get_results("SELECT url, report FROM $table_name ORDER BY collected_at DESC", ARRAY_A);
        $reports = [];
        foreach ($rows as $row) {
            $url = $row['url'];
            $data = json_decode($row['report'], true);
            // Group by URL, keep latest (or could collect all per URL if needed)
            $reports[$url] = $data;
        }


        echo '<div class="wrap">';

        echo '<h1>Performance Lab Browser Asset Inspector</h1>';

        // Settings form
        echo '<form method="post" action="options.php" style="margin-bottom:24px;">';
        settings_fields('pl_browser_asset_inspector');
        do_settings_sections('pl_browser_asset_inspector');
        submit_button('Save Settings');
        echo '</form>';

        // Info box for ?pl-collect=1 usage
        echo '<div style="background:#eaf6ff;border-left:4px solid #2271b1;padding:12px 20px;margin-bottom:18px;max-width:700px;">
            <strong>Tip:</strong> To collect performance data as a non-admin or logged-out user, add <code>?pl-collect=1</code> to any frontend URL. Example: <code>https://yoursite.com/?pl-collect=1</code>
        </div>';

        echo '<p>
            Visit any frontend page on your site.<br>
            Then refresh this dashboard to inspect collected browser assets.
        </p>';

        /**
         * Clear reports
         */
        if (isset($_POST['pl_clear_reports'])) {
            check_admin_referer('pl_clear_reports');
            global $wpdb;
            $table_name = $wpdb->prefix . 'pl_browser_asset_reports';
            $wpdb->query("TRUNCATE TABLE $table_name");
            echo '<div class="notice notice-success"><p>Reports cleared.</p></div>';
            $reports = [];
        }

        echo '<form method="post">';

        wp_nonce_field('pl_clear_reports');

        submit_button(
            'Clear Reports',
            'delete',
            'pl_clear_reports'
        );

        echo '</form>';

        if (empty($reports)) {

            echo '<p><strong>No reports collected yet.</strong></p>';

            echo '<p>
                Open a frontend page of your website in another tab,
                wait for it to fully load,
                then return here and refresh.
            </p>';

            echo '</div>';

            return;
        }

        /**
         * Page selector
         */
        echo '<h2>Collected Pages</h2>';

        echo '<ul>';

        foreach ($reports as $page_url => $report) {

            echo '<li>';

            echo '<a href="?page=pl-browser-asset-inspector&report=' .
                urlencode($page_url) .
                '">';

            echo esc_html($page_url);

            echo '</a>';

            echo '</li>';
        }

        echo '</ul>';

        /**
         * Selected report
         */
        if (!empty($_GET['report'])) {

            $selected = esc_url_raw($_GET['report']);

            if (!empty($reports[$selected])) {

                $this->render_report(
                    $selected,
                    $reports[$selected]
                );
            }
        }

        echo '</div>';
    }

    /**
     * Render a detailed asset report for a specific page.
     *
     * Outputs a summary and a table of all collected assets, including type, owner, size, duration,
     * warnings, render-blocking status, and URLs.
     *
     * @param string $url    The page URL for which the report is rendered.
     * @param array  $report The array of asset data for the page.
     */
    private function render_report($url, $report) {

        echo '<hr>';

        echo '<h2>Report</h2>';

        echo '<p><strong>URL:</strong> ' . esc_html($url) . '</p>';

        /**
         * Summary
         */
        $total_size = 0;
        $script_count = 0;
        $css_count = 0;
        $image_count = 0;

        foreach ($report as $entry) {

            $total_size += $entry['transferSize'] ?? 0;

            switch ($entry['initiatorType']) {

                case 'script':
                    $script_count++;
                    break;

                case 'css':
                case 'link':
                    $css_count++;
                    break;

                case 'img':
                    $image_count++;
                    break;
            }
        }

        echo '<div style="
            background:#fff;
            border:1px solid #ddd;
            padding:20px;
            margin-bottom:20px;
        ">';

        echo '<h3>Summary</h3>';

        echo '<p><strong>Total Assets:</strong> ' .
            esc_html(count($report)) .
            '</p>';

        echo '<p><strong>Total Transfer Size:</strong> ' .
            esc_html(size_format($total_size)) .
            '</p>';

        echo '<p><strong>Scripts:</strong> ' .
            esc_html($script_count) .
            '</p>';

        echo '<p><strong>Styles:</strong> ' .
            esc_html($css_count) .
            '</p>';

        echo '<p><strong>Images:</strong> ' .
            esc_html($image_count) .
            '</p>';

        echo '</div>';

        /**
         * Assets table
         */
        echo '<table class="widefat striped">';

        echo '<thead>
            <tr>
                <th>Type</th>
                <th>Owner</th>
                <th>Size</th>
                <th>Duration</th>
                <th>Warnings</th>
                <th>Render Blocking</th>
                <th>URL</th>
            </tr>
        </thead>';

        echo '<tbody>';

        foreach ($report as $entry) {

            $url = $entry['name'] ?? '';
            $size = $entry['transferSize'] ?? 0;
            $duration = round($entry['duration'] ?? 0, 2);
            $render_blocking = isset($entry['renderBlocking']) && $entry['renderBlocking'] ? 'Yes' : '-';

            echo '<tr>';

            echo '<td>' .
                esc_html($entry['initiatorType'] ?? '-') .
                '</td>';

            echo '<td>' .
                esc_html($this->detect_owner($url)) .
                '</td>';

            echo '<td>' .
                esc_html(size_format($size)) .
                '</td>';

            echo '<td>' .
                esc_html($duration . 'ms') .
                '</td>';

            echo '<td>' .
                esc_html($this->get_warnings($entry)) .
                '</td>';

            echo '<td>' .
                esc_html($render_blocking) .
                '</td>';

            echo '<td style="max-width:500px;word-break:break-all;">' .
                esc_html($url) .
                '</td>';

            echo '</tr>';
        }

        echo '</tbody>';

        echo '</table>';
    }

    /**
     * Register the REST API routes for the Browser Asset Inspector.
     *
     * Adds a POST endpoint for receiving asset reports from the frontend collector.
     * Secured with a nonce via the X-WP-Nonce header.
     */
    public function register_rest_routes() {

        register_rest_route(
            'perf-lab/v1',
            '/report',
            [
                'methods' => 'POST',
                'callback' => [$this, 'receive_report'],
                'permission_callback' => function( $request ) {
                    $nonce = $request->get_header('x_wp_nonce');
                    return wp_verify_nonce($nonce, 'wp_rest');
                },
            ]
        );
    }

    /**
     * Handle incoming asset report submissions from the frontend collector.
     *
     * Validates and stores the submitted report data in the custom database table.
     * Returns a REST response indicating success or failure.
     *
     * @param \WP_REST_Request $request The REST API request object containing report data.
     * @return \WP_REST_Response
     */
    public function receive_report($request) {

        $data = $request->get_json_params();

        if (empty($data['url']) || empty($data['resources'])) {
            return new \WP_REST_Response([
                'success' => false
            ], 400);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pl_browser_asset_reports';
        $wpdb->insert(
            $table_name,
            [
                'url' => $data['url'],
                'report' => wp_json_encode($data['resources']),
                'collected_at' => current_time('mysql', 1)
            ],
            [
                '%s',
                '%s',
                '%s'
            ]
        );

        return new \WP_REST_Response([
            'success' => true
        ]);
    }

    /**
     * Inject the frontend JavaScript collector script into the site footer.
     *
     * Outputs a script that collects resource timing and asset data from the browser,
     * then submits it to the REST API endpoint. Only runs if enabled in settings and
     * for admins or when ?pl-collect=1 is present.
     */
    public function inject_collector_script() {

        // Only inject if enabled in settings
        $enabled = get_option($this->option_enable_collector, '1');
        if ($enabled !== '1') return;
        // Restrict to admins or ?pl-collect=1
        if ( is_admin() ) return;
        if ( ! current_user_can('manage_options') && ! isset($_GET['pl-collect']) ) return;

        ?>
        <script>
        window.addEventListener('load', () => {
            setTimeout(() => {
                const resources = performance.getEntriesByType('resource');
                // Helper to check if a script/link is render-blocking
                function isRenderBlocking(entry) {
                    if (entry.initiatorType === 'script') {
                        // Find script tag by src
                        const scripts = document.querySelectorAll('head script[src]');
                        for (const s of scripts) {
                            if (s.src === entry.name) {
                                // Not render-blocking if async or defer
                                if (s.hasAttribute('async') || s.hasAttribute('defer')) return false;
                                return true;
                            }
                        }
                    }
                    if (entry.initiatorType === 'css' || entry.initiatorType === 'link') {
                        // Find link tag by href
                        const links = document.querySelectorAll('head link[rel="stylesheet"]');
                        for (const l of links) {
                            if (l.href === entry.name) {
                                // Not render-blocking if media is not all or screen
                                const media = l.getAttribute('media');
                                if (media && media !== 'all' && media !== 'screen') return false;
                                return true;
                            }
                        }
                    }
                    return false;
                }
                const cleaned = resources.map(resource => ({
                    name: resource.name,
                    initiatorType: resource.initiatorType,
                    duration: resource.duration,
                    transferSize: resource.transferSize || 0,
                    renderBlocking: isRenderBlocking(resource)
                }));
                fetch('<?php echo esc_url(rest_url('perf-lab/v1/report')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (window.dpukPerfLab && window.dpukPerfLab.restNonce) ? window.dpukPerfLab.restNonce : ''
                    },
                    body: JSON.stringify({
                        url: window.location.href,
                        resources: cleaned
                    })
                });
            }, 1000);
        });
        </script>
        <?php
    }

    /**
     * Attempt to determine the owner of an asset based on its URL.
     *
     * Currently detects plugin ownership by matching the /plugins/{plugin}/ path.
     * Returns 'Unknown' if no owner can be determined.
     *
     * @param string $url The asset URL.
     * @return string The detected owner or 'Unknown'.
     */
private function detect_owner( $url ) {

	if ( empty( $url ) ) {
		return 'Unknown';
	}

	$url = wp_parse_url( $url, PHP_URL_PATH );

	if ( empty( $url ) ) {
		return 'Unknown';
	}

	/**
	 * Measurement / profiling tools
	 */
	if ( strpos( $url, '/10-browser-dev-tools/' ) !== false ) {
		return '10-browser-dev-tools (Profiler)';
	}

	if ( strpos( $url, '/debug-bar/' ) !== false ) {
		return 'Debug Bar (Profiler)';
	}


	/**
	 * WordPress Core
	 */
	if (
		strpos( $url, '/wp-includes/' ) !== false ||
		strpos( $url, '/wp-admin/' ) !== false
	) {
		return 'WordPress Core';
	}


	/**
	 * Plugins
	 */
	if ( strpos( $url, '/wp-content/plugins/' ) !== false ) {

		preg_match(
			'#/wp-content/plugins/([^/]+)/#',
			$url,
			$matches
		);

		if ( ! empty( $matches[1] ) ) {

			return ucwords(
				str_replace(
					array( '-', '_' ),
					' ',
					$matches[1]
				)
			);
		}
	}


	/**
	 * Themes
	 */
	if ( strpos( $url, '/wp-content/themes/' ) !== false ) {

		preg_match(
			'#/wp-content/themes/([^/]+)/#',
			$url,
			$matches
		);

		if ( ! empty( $matches[1] ) ) {

			return ucwords(
				str_replace(
					array( '-', '_' ),
					' ',
					$matches[1]
				)
			) . ' Theme';
		}
	}


	/**
	 * Media uploads
	 */
	if ( strpos( $url, '/wp-content/uploads/' ) !== false ) {
		return 'Media Library';
	}


	/**
	 * External resources
	 */
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$url_host  = wp_parse_url( $url, PHP_URL_HOST );

	if ( $url_host && $url_host !== $site_host ) {
		return 'External Resource';
	}


	return 'Unknown';
}

    /**
     * Generate warnings for a given asset entry based on size and duration.
     *
     * Flags large assets, slow requests, and heavy JavaScript files.
     *
     * @param array $entry The asset entry data.
     * @return string Comma-separated warnings or '-' if none.
     */
    private function get_warnings($entry) {

        $warnings = [];

        $size = $entry['transferSize'] ?? 0;

        $duration = $entry['duration'] ?? 0;

        /**
         * Large assets
         */
        if ($size > 300000) {
            $warnings[] = 'Large asset';
        }

        /**
         * Slow requests
         */
        if ($duration > 500) {
            $warnings[] = 'Slow request';
        }

        /**
         * Large JS
         */
        if (
            ($entry['initiatorType'] ?? '') === 'script'
            && $size > 200000
        ) {
            $warnings[] = 'Heavy JavaScript';
        }

        return !empty($warnings)
            ? implode(', ', $warnings)
            : '-';
    }
}