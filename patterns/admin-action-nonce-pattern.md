# Admin Action Nonce for Secure Form Submissions

**Pattern:** Admin action nonce for secure form submissions

---

## Purpose
Protect admin-side data modification or deletion actions from CSRF by requiring a valid nonce in form submissions.

---

## How It Works

1. **Add a nonce field to your admin form:**
   ```php
   wp_nonce_field('pl_clear_reports');
   ```

2. **Check the nonce before processing the action:**
   ```php
   if (isset($_POST['pl_clear_reports'])) {
       check_admin_referer('pl_clear_reports');
       // ...perform deletion or modification...
   }
   ```

---

## Example (from Browser Asset Inspector)

```php
// In the admin page HTML:
echo '<form method="post">';
wp_nonce_field('pl_clear_reports');
submit_button('Clear Reports', 'delete', 'pl_clear_reports');
echo '</form>';

// In the handler:
if (isset($_POST['pl_clear_reports'])) {
    check_admin_referer('pl_clear_reports');
    // Perform the destructive action (e.g., truncate table)
}
```

---

## Benefits
- Prevents CSRF attacks on admin actions
- Ensures only legitimate admin users can trigger data changes
- Standard WordPress security best practice

---

## Use Cases
- Deleting or resetting plugin data from admin UI
- Saving or updating sensitive settings
- Any admin-side form that modifies site data

---

This pattern is essential for any WordPress plugin or theme that allows data changes via admin forms.
