# Database Schema Provisioning Pattern (Plugin Activation)

## Goal

Safely create and manage custom database tables during plugin activation
using WordPress-native tools.

This pattern is used when:
- storing structured analytics data
- logging system events
- building performance or reporting systems
- persisting high-volume custom datasets

---

# Example Implementation

```php
public static function activate() {

    global $wpdb;

    $table_name = $wpdb->prefix . 'pl_browser_asset_reports';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        url TEXT NOT NULL,
        report LONGTEXT NOT NULL,
        collected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY url_idx (url(191)),
        KEY collected_at_idx (collected_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta($sql);
}
```

---

# What This Pattern Does

This pattern:

- runs during plugin activation
- defines a custom database table
- ensures charset/collation consistency
- creates indexes for query performance
- uses WordPress-native schema management (`dbDelta`)

---

# Why dbDelta is Used

WordPress does not use a traditional migration system.

Instead, `dbDelta()`:

- creates tables if they do not exist
- updates schema when possible
- preserves existing data where safe
- handles incremental schema changes

---

# Design Considerations

## 1. Prefixing Tables

```php
$wpdb->prefix . 'pl_browser_asset_reports';
```

Ensures:
- multisite compatibility
- avoids naming collisions
- respects WP installation context

---

## 2. Index Strategy

```sql
KEY url_idx (url(191))
KEY collected_at_idx (collected_at)
```

Indexes are used for:
- filtering by URL
- time-based queries
- report aggregation

⚠️ Note:
MySQL requires prefix length indexing for TEXT fields.

---

## 3. Data Type Choices

- `TEXT` → URL storage (variable length)
- `LONGTEXT` → serialized report payloads
- `DATETIME` → collection timestamp

Tradeoff:
- flexible storage vs query efficiency

---

# Limitations of This Pattern

## 1. No explicit migrations system

Schema changes depend on:
- re-running activation logic
- modifying SQL manually

---

## 2. dbDelta inconsistencies

`dbDelta()` can be:
- strict about SQL formatting
- inconsistent across environments

---

## 3. Manual schema evolution

You must handle:
- column additions
- index changes
- backward compatibility

---

# Common Anti-Patterns

## 1. No indexing on query fields

Leads to:
- slow reporting queries
- full table scans

---

## 2. Storing structured data without schema planning

Example:
- storing everything in `LONGTEXT` without structure

---

## 3. Ignoring multisite prefixing

Hardcoding table names breaks:
- multisite compatibility
- shared DB environments

---

# When to Use This Pattern

Use when building:

- analytics systems
- logging pipelines
- performance monitoring tools
- audit trails
- reporting dashboards

---

# Alternative Approaches

## 1. Custom tables (this pattern)

Best for:
- high-performance datasets
- large-scale logs
- structured querying

---

## 2. Post meta (WordPress default)

Best for:
- small datasets
- flexible content
- low query complexity

---

## 3. External storage

Best for:
- large-scale analytics
- distributed systems
- heavy write workloads

---

# Related Patterns

- REST API ingestion patterns
- performance reporting pipelines
- object caching strategies
- data aggregation workflows
- plugin activation lifecycle design

---

# Notes

This pattern is used in performance reporting systems where
frontend-collected data is stored for later analysis.

It prioritises:
- write efficiency
- query performance
- schema clarity over flexibility