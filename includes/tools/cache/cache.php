<?php



/**
 * Demonstrates WordPress transient caching.
 *
 * Attempts to retrieve a value stored in a transient using
 * get_transient(). If the value does not exist or has expired,
 * simulates an expensive operation, generates a random value,
 * and stores it for 60 seconds using set_transient().
 *
 * Displays the cached value, cache status, execution time,
 * and an explanation of the result.
 *
 * Unlike the default request-local object cache, transients
 * can persist between page requests. Their storage mechanism
 * depends on the WordPress configuration.
 *
 * Usage: [transient_cache_demo]
 *
 * @return string HTML output showing the value, cache status,
 *                execution time, and explanatory text.
 */
function my_transient_cache_demo() {
    $key   = 'my_demo_value';
    $start = microtime(true);

    $value = get_transient($key);

    if (false === $value) {
        $cache_status = 'MISS';

        // Simulate expensive work.
        sleep(2);

        $value = wp_rand(1000, 9999);

        // Store the value for 60 seconds.
        set_transient($key, $value, 60);

        $explanation = 'No valid cached value was found. '
            . 'WordPress simulated an expensive operation, '
            . 'generated a new value, and stored it for 60 seconds.';
    } else {
        $cache_status = 'HIT';

        $explanation = 'WordPress found a cached value and reused it. '
            . 'The expensive operation was skipped.';
    }

    $elapsed = microtime(true) - $start;

    return sprintf(
        '<div class="transient-cache-demo">
            <h3>WordPress Transient Cache Experiment</h3>
            <p>This experiment stores a value for 60 seconds so '
                . 'it can potentially be reused across page loads.</p>
            <p><strong>Value:</strong> %s</p>
            <p><strong>Cache status:</strong> %s</p>
            <p><strong>Shortcode execution time:</strong> %.4f seconds</p>
            <p>%s</p>
        </div>',
        esc_html((string) $value),
        esc_html($cache_status),
        $elapsed,
        esc_html($explanation)
    );
}

add_shortcode( 'transient_cache_demo', __NAMESPACE__ . '\my_transient_cache_demo' );




/**
 * Demonstrates WordPress object caching with wp_cache_get().
 *
 * Attempts to retrieve a value from the object cache. On a cache miss,
 * simulates an expensive operation and stores the result in the cache.
 *
 * Displays the cached value, cache status, and execution time.
 *
 * Note: Without a persistent object-cache backend, the cache usually
 * lasts only for the current PHP request. Because each page load is
 * a separate request, the cache will typically miss on every refresh.
 *
 * Usage: [object_cache_demo]
 *
 * @return string HTML output showing cache status and execution time.
 */
function my_object_cache_demo() {
    $key       = 'demo_value';
    $group     = 'my_cache_experiment';
    $start     = microtime(true);
    $found     = false;
    $value     = wp_cache_get($key, $group, false, $found);
    $cache_status = $found ? 'HIT' : 'MISS';

    if (!$found) {
        // Simulate expensive work.
        sleep(2);

        $value = wp_rand(1000, 9999);

        wp_cache_set($key, $value, $group, 60);
        $check = wp_cache_get($key, $group, false, $found);

error_log('Immediate cache check: ' . ($found ? 'HIT' : 'MISS'));
error_log('Stored value: ' . print_r($check, true));
    }

    $elapsed = microtime(true) - $start;

    return sprintf(
        '<p>Value: %s</p>
         <p>Cache: %s</p>
         <p>Execution time: %.4f seconds</p>',
        esc_html((string) $value),
        esc_html($cache_status),
        $elapsed
    );
}

add_shortcode('object_cache_demo', __NAMESPACE__ . '\my_object_cache_demo');




/**
 * Demonstrates WordPress object caching within a single request.
 *
 * Performs two lookups using wp_cache_get().
 *
 * The first lookup should produce a cache miss, simulate an
 * expensive operation, and store the result using wp_cache_set().
 * The second lookup should produce a cache hit and reuse the
 * stored value without repeating the expensive operation.
 *
 * By default, WordPress's object cache is request-local, so
 * the value generally will not survive a page refresh unless
 * a persistent object-cache backend is configured.
 *
 * Usage: [shared_cache_demo]
 *
 * @return string HTML output showing both lookups, cache status,
 *                values, execution times, and explanations.
 */
function my_shared_cache_demo() {
    $key   = 'shared_demo_value';
    $group = 'my_cache_experiment';

    $output = '';
    $output .= '<div class="shared-cache-demo">';
    $output .= '<h3>WordPress Object Cache Experiment</h3>';
    $output .= '<p>This experiment performs the same lookup twice '
        . 'during one page request. The first lookup should miss '
        . 'and store a value; the second should reuse it.</p>';

    for ($i = 1; $i <= 2; $i++) {
        $start = microtime(true);
        $found = false;

        $value = wp_cache_get($key, $group, false, $found);

        if (!$found) {
            // Cache miss: simulate expensive work.
            sleep(2);

            $value = wp_rand(1000, 9999);

            wp_cache_set($key, $value, $group, 60);

            $status = 'MISS';
            $explanation = 'No cached value was found. '
                . 'Generated a new value and stored it.';
        } else {
            // Cache hit: reuse the existing value.
            $status = 'HIT';
            $explanation = 'The cached value was found and reused. '
                . 'The expensive operation was skipped.';
        }

        $elapsed = microtime(true) - $start;

        $output .= sprintf(
            '<section>
                <h4>Lookup %d</h4>
                <p><strong>Cache status:</strong> %s</p>
                <p><strong>Value:</strong> %s</p>
                <p><strong>Execution time:</strong> %.4f seconds</p>
                <p>%s</p>
            </section>',
            $i,
            esc_html($status),
            esc_html((string) $value),
            $elapsed,
            esc_html($explanation)
        );
    }

    $output .= '</div>';

    return $output;
}


add_shortcode('shared_cache_demo', __NAMESPACE__ . '\my_shared_cache_demo');