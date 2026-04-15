# Vision Impact Solutions

WordPress plugin for agent onboarding and operations, including orientation gating, profile and license management, leaders/events content modules, LMS progress integration, and Google Sheets template provisioning.

## Version

- Current plugin version: 1.2.2
- PHP requirement: 7.4+

## Core Functionality

### 1) Agent orientation workflow

- Shows a disclosure popup and orientation flow for logged-in non-admin users who have not completed onboarding.
- Supports YouTube, Vimeo, and direct HTML5 video URLs.
- Tracks orientation progress in a custom DB table (timestamp, completion status, form state).
- Enforces configurable video completion threshold before marking orientation complete.

### 2) Agent profile management

- Frontend profile page via shortcode with editable personal/contact/social fields.
- Avatar upload support and password update endpoints.
- Dynamic "About You" questions configurable by admins.
- Profile data stored in custom profile table linked to WordPress users.

### 3) License management

- Agents can create/update/delete their own licenses.
- Admins can review and manage licenses from a dedicated management page.
- Status-based tracking (active/pending/expired/rejected) with reporting in admin views.

### 4) Leaders and events content modules

- Registers two custom post types:
  - `leaders`
  - `events`
- Includes admin meta boxes for leader/event details.
- Provides archive/single templates and frontend styles.
- Includes event search AJAX endpoint and recurring event handling logic.

### 5) LMS integration

- Provider abstraction layer with auto-detection.
- Prefers LearnDash when active; uses Tutor LMS as fallback.
- Exposes enrolled courses/progress data for profile page display.

### 6) Google integration

- OAuth-based Google connection (Sheets + Drive).
- On new `agent` user registration, creates a personal sheet by cloning the configured master template.
- Stores generated sheet ID in the agent profile record.

Important behavior:

- This plugin currently clones and provisions sheets only.
- It does not perform ongoing profile-to-sheet live sync.

### 7) Birthday reminders

- Daily cron job checks for upcoming agent birthdays.
- Displays admin notices for birthdays in the next 7 days.
- Sends email reminder to site admin for birthdays exactly 2 days away.

## Admin Area

Main menu: Vision Impact

Available sections:

- Orientation
- Profile
- Questions
- Google Sheets
- Upcoming Birthdays
- Debug
- All Agents
- Orientation Report
- License Management

Admin can also:

- Approve or reject pending agent codes.
- Reset orientation state per user.
- Trigger Google auth/sheet-related actions from settings UI.

## Shortcodes

- `[agent_profile]` - renders the agent profile experience.
- `[vics_logout_link]` - renders customizable logout link for logged-in users.
- `[events_archive]` - renders events archive UI.
- `[leaders_grid]` - renders leaders listing/grid.

## Database Tables

The plugin creates and migrates these custom tables:

- `wp_vics_orientation_progress`
- `wp_vics_agent_profile`
- `wp_vics_agent_licenses`

## Installation

1. Place plugin in WordPress plugins directory:
   - `/wp-content/plugins/vision-impact-solutions/`
2. Install dependencies:

   ```bash
   composer install
   ```

3. Activate the plugin in WordPress admin.
4. After activation, the plugin automatically:
   - creates required DB tables
   - creates `agent` role
   - creates `my-profile` page with `[agent_profile]`

## Google Setup

1. In Google Cloud Console, enable:
   - Google Sheets API
   - Google Drive API
2. Create OAuth 2.0 Web credentials.
3. Add exact redirect URI:

   ```
   https://your-domain.com/wp-admin/admin.php?page=vics-settings&tab=google
   ```

4. In WordPress admin (Vision Impact > Google Sheets), set:
   - Google Client ID
   - Google Client Secret
   - Master Sheet ID
5. Connect account and verify authentication success.

If authentication appears stale, you can inspect or remove the stored token with WP-CLI:

```bash
wp option get vics_google_access_token
wp option delete vics_google_access_token
```

## Security Notes

- AJAX endpoints use nonce checks for orientation, profile, license, and admin actions.
- Most user actions require login; admin actions require `manage_options`.
- Inputs are sanitized before DB writes.

## Troubleshooting

- If Google auth fails, verify redirect URI exactly matches configured value.
- If agent sheets are not created, verify:
  - user has `agent` role
  - master sheet ID is configured
  - Google APIs are enabled
  - plugin is authenticated with Google
- If plugin routes do not resolve for leaders/events, re-save permalinks or reactivate plugin to refresh rewrite rules.

## Development Notes

- Composer packages are required for Google API classes.
- Debug logging is available when WordPress debug mode is enabled and plugin debug setting is turned on.