# Vision Impact Custom Solutions

Complete agent management system with orientation, profile management, license tracking, and Google Workspace API integration.

## Features

- **Agent Orientation System**: Interactive video-based orientation with progress tracking
- **Profile Management**: Comprehensive agent profile with social media integration
- **License Tracking**: Full license management system with status tracking
- **Google Sheets Integration**: Automatic sync of agent data to personal Google Sheets
- **Master Tracker**: Admin dashboard showing all agents with birthday reminders

## Google Sheets Integration

### Requirements

Each new member automatically gets a dedicated Google Sheet (clone of master sheet). Any profile or tracker updates sync automatically to their personal sheet. Admins receive a master tracker showing all agents with automatic birthday reminders (2 days before).

### Setup Instructions

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Google Cloud Console Setup**
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select existing one
   - Enable the following APIs:
     - Google Sheets API
     - Google Drive API

3. **Create OAuth 2.0 Credentials**
   - Go to "APIs & Credentials" > "Credentials"
   - Click "Create Credentials" > "OAuth 2.0 Client IDs"
   - Choose "Web application"
   - Add authorized redirect URIs (must match exactly):
     - `https://yourdomain.com/wp-admin/admin.php?page=vics-settings&tab=google`
   - Copy the Client ID and Client Secret

   Note: The plugin now uses a WP nonce embedded into the OAuth `state` parameter for callback verification. If you see the auth callback return without connecting, please ensure the redirect URI configured in Google matches exactly and retry the Connect flow from the plugin settings page.

If Connect still fails, check for the stored token by running:

```bash
wp option get vics_google_access_token
```

Or remove any stale token and retry:

```bash
wp option delete vics_google_access_token
```

If you don't have WP-CLI, you can temporarily inspect or delete the `vics_google_access_token` option using a small PHP snippet in the admin (or use a DB tool to inspect the `wp_options` table).

4. **Configure Plugin Settings**
   - Go to WordPress Admin > Vision Impact > Google Sheets
   - Enter your Client ID and Client Secret
   - Enter your Master Sheet ID (the template sheet to clone)
   - Click "Connect to Google" to authorize the application
   - **Important:** Note the "Authenticated Account" email shown after connecting

5. **Create Master Sheet Template**
   - Create a Google Sheet with the following structure:
   ```
   A1: Full Name
   B1: Email
   C1: Phone
   D1: Agent Code
   E1: NPN
   F1: License Number
   G1: Date of Birth
   H1: City
   I1: State
   J1: Goals for Year
   K1: Experience Level
   L1: Facebook
   M1: Instagram
   N1: Twitter
   O1: TikTok
   P1: YouTube
   Q1: LinkedIn
   ```
   - Share the master sheet with the authenticated account email (shown in plugin settings)
   - Give it "Editor" permissions

6. **User Sheet Sharing**
   - When users enter their Google Sheet ID in their profile, they must share that sheet with the authenticated account email
   - Users can share their sheet by: Sheet > Share > Enter the authenticated email > Set as "Editor"

### How It Works

1. **New Agent Registration**: When a new user registers, the system automatically:
   - Creates a copy of the master sheet
   - Names it "[Agent Name] - Agent Tracker"
   - Pre-fills it with the agent's current profile data
   - Stores the sheet ID in the agent's profile

2. **Profile Updates**: Any changes to agent profiles automatically sync to:
   - Their personal sheet (with timestamp)
   - The master tracker sheet

3. **Master Tracker**: Admins can manually sync all agent data to the master tracker sheet

4. **Clone Master Sheet**: Admins can create additional copies of the master sheet for backup, regional tracking, or other purposes

5. **Birthday Reminders**: The system checks daily for agents with birthdays in 2 days and:
   - Shows notices in the WordPress admin
   - Sends email notifications to admins

### Current Limitations

- **Sheet Sharing Required**: Users must manually share their Google Sheets with the authenticated admin account email for sync to work
- **Single Admin Account**: Currently uses one Google account for all sheet operations (per-user OAuth planned for future updates)

### User Instructions

**For Users (Agents):**
1. Register for an account on the website
2. Your personal Google Sheet will be automatically created with your current profile data
3. Share your Google Sheet with the admin account email shown in the profile notice
4. Set the admin as "Editor" permissions
5. Your profile updates will automatically sync to your sheet with timestamps

**For Admins:**
1. Connect your Google account in the plugin settings
2. Note the authenticated email address displayed
3. Create a master sheet template with headers A1:R1
4. Enter the master sheet ID in plugin settings
5. New agents will automatically get their own sheets when they register
6. Use "Sync to Master Tracker" to populate the master sheet with all agent data
7. Use "Clone Sheet" to create additional copies of the master sheet

### Troubleshooting

- **Authentication Issues**: Ensure redirect URI matches exactly in Google Cloud Console
- **Re-authentication Required**: If you see a "Re-authentication Required" notice, disconnect and reconnect your Google account to update permissions
- **Permission Errors**: Users must share their Google Sheets with the authenticated account email shown in plugin settings
- **Sheet Not Found**: Verify the Google Sheet ID is correct and the sheet exists
- **Sync Failures**: Check that all required APIs are enabled and credentials are correct
- **Database Errors**: Run the plugin activation again to ensure all tables are created

## Installation

1. Upload the plugin files to `/wp-content/plugins/vision-impact-custom-solutions/`
2. Activate the plugin through the WordPress admin
3. Configure settings as needed
4. Set up Google Sheets integration following the instructions above

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- Composer (for Google API dependencies)

## Changelog

### 1.0.0
- Initial release
- Agent orientation system
- Profile management
- License tracking
- Google Sheets integration