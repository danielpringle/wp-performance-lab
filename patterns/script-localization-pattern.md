# Script Localization for Secure Data Passing

**Pattern:** Script localization for secure, dynamic data transfer to frontend

---

## Purpose
Safely pass server-generated data (such as nonces, configuration, or user info) from PHP to JavaScript in WordPress.

---

## How It Works

1. **Register and enqueue a script (can be a dummy script):**
   ```php
   wp_register_script('dpuk-perf-collector', false);
   wp_enqueue_script('dpuk-perf-collector');
   ```

2. **Localize the script with server data:**
   ```php
   wp_localize_script('dpuk-perf-collector', 'dpukPerfLab', [
       'restNonce' => wp_create_nonce('wp_rest')
   ]);
   ```

3. **Access the data in JavaScript:**
   ```js
   // Now available globally in the browser:
   window.dpukPerfLab.restNonce
   ```

---

## Benefits
- Securely exposes dynamic PHP data to JS
- Avoids hardcoding or insecure inline scripts
- Works for nonces, config, user info, REST URLs, etc.

---

## Use Cases
- REST API nonce passing (as in the Browser Asset Inspector collector)
- AJAX URLs and security tokens
- User or site config for frontend scripts

---

## Example (from Browser Asset Inspector)

```php
add_action('wp_enqueue_scripts', function() {
    wp_register_script('dpuk-perf-collector', false);
    wp_enqueue_script('dpuk-perf-collector');
    wp_localize_script('dpuk-perf-collector', 'dpukPerfLab', [
        'restNonce' => wp_create_nonce('wp_rest')
    ]);
});
```

```js
fetch('/wp-json/perf-lab/v1/report', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.dpukPerfLab.restNonce
    },
    body: JSON.stringify(payload)
});
```

---

## Summary
This pattern is a WordPress best practice for bridging PHP and JS securely and flexibly. It is especially important for REST API security, AJAX, and dynamic configuration.
