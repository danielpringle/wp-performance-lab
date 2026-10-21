# Browser Asset Inspector

A tool for collecting and analyzing frontend asset and browser performance data for any page on your WordPress site.

## Features
- Collects real browser asset data (scripts, styles, images, etc.)
- Stores reports in a custom database table for scalability
- Secure REST API endpoint with nonce verification (supports anonymous collection)
- Admin dashboard to view, analyze, and clear reports
- Opt-in frontend collector (admins only or via `?pl-collect=1`)

## How It Works
1. **Collector Script**: Injected into the frontend for admins or when `?pl-collect=1` is present. Collects all resource timing data after page load.
2. **REST API**: Collector POSTs data to a custom REST endpoint, secured by a WordPress REST nonce (works for logged-in and logged-out users).
3. **Data Storage**: Reports are stored in a custom table (`wp_pl_browser_asset_reports`) for efficient querying and scalability.
4. **Admin UI**: View all collected reports, see per-asset details, and clear all reports from the dashboard.

## Usage
- **Collect Data**: Visit any frontend page as an admin, or add `?pl-collect=1` to the URL for opt-in collection as any user (including logged out).
- **View Reports**: Go to the "Asset Inspector" menu in the WordPress admin to see collected data.
- **Clear Reports**: Use the "Clear Reports" button in the admin UI to remove all stored reports.

## Security & Privacy
- All POST requests to the REST endpoint require a valid WordPress REST nonce.
- No personal data is collected; only asset URLs, types, sizes, and timings are stored.
- Data is stored in a custom table, not in options, for performance and scalability.

## Roadmap / TODO
- CSV/JSON export buttons for reports
- Per-report deletion
- Table sorting/filtering and accessibility improvements
- Data retention policy (auto-expiry/limiting)
- Visual improvements (color highlights, icons)

## Developer Notes
- To enable collection for all users by default, adjust the collector injection logic in `inject_collector_script()`.
- Extend owner detection logic in `detect_owner()` for more accurate plugin/theme attribution.

---

**Browser Asset Inspector** is part of the DPUK Performance Lab plugin suite.