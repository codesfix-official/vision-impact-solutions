<?php
/**
 * Google Authentication Handler
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Google API client early if not already loaded
if (!class_exists('Google_Client')) {
    $autoload_path = dirname(__FILE__, 2) . '/vendor/autoload.php';
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
    }
}

class VICS_Google_Auth {

    /**
     * Google Client instance
     * @var Google_Client
     */
    private $client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_client();
    }

    /**
     * Initialize Google Client
     */
    private function init_client() {
        if (class_exists('Google_Client')) {
            vics_log('Google_Client class found, initializing client');
            $this->client = new Google_Client();
            $this->client->setApplicationName('Vision Impact Custom Solutions');
            $this->client->setScopes([
                Google_Service_Sheets::SPREADSHEETS,
                Google_Service_Drive::DRIVE,
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile'
            ]);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');

            // Set credentials from settings
            $client_id = get_option('vics_google_client_id');
            $client_secret = get_option('vics_google_client_secret');

            if ($client_id && $client_secret) {
                $this->client->setClientId($client_id);
                $this->client->setClientSecret($client_secret);
                vics_log('Google client credentials set');
            } else {
                vics_log('Google client credentials not set in options', 'warning');
            }
        } else {
            vics_log('Google_Client class not found', 'error');
        }
    }

    /**
     * Load Google API client manually (fallback)
     */
    private function load_google_api_manually() {
        // This would require manual download and inclusion of Google API files
        // For now, we'll log an error
        vics_log('Google API client not found. Please install via Composer or manually.', 'error');
    }

    /**
     * Get Google Client
     *
     * @return Google_Client|null
     */
    public function get_client() {
        return $this->client;
    }

    /**
     * Check if authenticated
     *
     * @return bool
     */
    public function is_authenticated() {
        if (!$this->client) {
            vics_log('No Google client available', 'error');
            return false;
        }

        $token = get_option('vics_google_access_token');
        if (!$token) {
            vics_log('No stored access token', 'warning');
            return false;
        }

        // Check if token has required scopes
        if (is_array($token) && isset($token['scope'])) {
            $required_scopes = ['https://www.googleapis.com/auth/userinfo.email'];
            $token_scopes = explode(' ', $token['scope']);
            $has_required_scopes = false;
            foreach ($required_scopes as $scope) {
                if (in_array($scope, $token_scopes)) {
                    $has_required_scopes = true;
                    break;
                }
            }
            if (!$has_required_scopes) {
                vics_log('Token missing required userinfo scopes, clearing token', 'warning');
                delete_option('vics_google_access_token');
                return false;
            }
        }

        $this->client->setAccessToken($token);
        vics_log('Client access token set in is_authenticated, checking if token is set: ' . ($this->client->getAccessToken() ? 'yes' : 'no'));

        // Check if token is expired
        $is_expired = $this->client->isAccessTokenExpired();
        vics_log('isAccessTokenExpired() returns: ' . ($is_expired ? 'true' : 'false'));

        if ($is_expired) {
            vics_log('Access token is expired according to client', 'warning');
            // Try to refresh token
            if ($this->client->getRefreshToken()) {
                vics_log('Attempting to refresh token');
                try {
                    $refreshed_token = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    vics_log('fetchAccessTokenWithRefreshToken returned: ' . json_encode($refreshed_token));

                    if (isset($refreshed_token['error'])) {
                        vics_log('Refresh failed with error: ' . $refreshed_token['error'], 'error');
                        return false;
                    } else {
                        $new_token = $this->client->getAccessToken();
                        vics_log('New token from client after refresh: ' . json_encode($new_token));
                        update_option('vics_google_access_token', $new_token);
                        vics_log('Token refreshed successfully in is_authenticated');
                        return true;
                    }
                } catch (Exception $e) {
                    vics_log('Token refresh failed in is_authenticated: ' . $e->getMessage(), 'error');
                    return false;
                }
            } else {
                vics_log('No refresh token available in is_authenticated', 'warning');
                return false;
            }
        }

        vics_log('Token appears valid');
        return true;
    }

    /**
     * Get authentication URL
     *
     * @param string $redirect_uri
     * @return string
     */
    public function get_auth_url($redirect_uri = '') {
        if (!$this->client) {
            return '';
        }

        if (!function_exists('wp_create_nonce')) {
            // Fallback: return normal auth url
            if (!$redirect_uri) {
                $redirect_uri = admin_url('admin.php?page=vics-settings&tab=google');
            }
            $this->client->setRedirectUri($redirect_uri);
            return $this->client->createAuthUrl();
        }

        if (!$redirect_uri) {
            $redirect_uri = admin_url('admin.php?page=vics-settings&tab=google');
        }

        // Use a state that includes a WordPress nonce for verification
        $nonce = wp_create_nonce('vics_google_auth');
        $state = 'vics_google_auth:' . $nonce;

        $this->client->setRedirectUri($redirect_uri);
        $this->client->setState($state);

        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback
     *
     * @param string $code
     * @param string $redirect_uri
     * @return bool
     */
    public function handle_callback($code, $redirect_uri = '') {
        if (!$this->client) {
            return false;
        }

        if (!$redirect_uri) {
            $redirect_uri = admin_url('admin.php?page=vics-settings&tab=google');
        }

        // Ensure redirect URI is set before exchanging code
        $this->client->setRedirectUri($redirect_uri);

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                vics_log('Google OAuth Error: ' . $token['error'], 'error');
                return false;
            }

            update_option('vics_google_access_token', $token);
            return true;
        } catch (Exception $e) {
            vics_log('Google OAuth Exception: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get authenticated user email
     *
     * @return string|null
     */
    public function get_authenticated_email() {
        if (!$this->is_authenticated()) {
            vics_log('Not authenticated according to is_authenticated() check', 'warning');
            return null;
        }

        // Debug: Check token details
        $token = get_option('vics_google_access_token');
        if ($token) {
            if (is_array($token)) {
                vics_log('Token is array with keys: ' . implode(', ', array_keys($token)));
                if (isset($token['access_token'])) {
                    vics_log('Access token exists, expires: ' . ($token['expires_in'] ?? 'unknown'));
                }
                if (isset($token['refresh_token'])) {
                    vics_log('Refresh token exists');
                } else {
                    vics_log('No refresh token in stored token', 'warning');
                }
            } else {
                vics_log('Token is not an array: ' . substr($token, 0, 50) . '...', 'warning');
            }
        }

        // Ensure client has the token set
        $this->client->setAccessToken($token);
        vics_log('Client access token set, checking if token is set: ' . ($this->client->getAccessToken() ? 'yes' : 'no'));

        try {
            // Try to get user info using the access token
            $token = $this->client->getAccessToken();
            if (!$token || !is_array($token) || !isset($token['access_token'])) {
                vics_log('No valid access token available', 'error');
                return null;
            }

            // Make a direct API call to get user info
            $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15
            );

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                vics_log('Failed to get user info: ' . $response->get_error_message(), 'error');
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            $user_data = json_decode($body, true);

            if (!$user_data || !isset($user_data['email'])) {
                vics_log('Invalid user info response', 'error');
                return null;
            }

            $email = $user_data['email'];
            vics_log('Successfully got user email: ' . $email);
            return $email;
        } catch (Exception $e) {
            vics_log('Could not get authenticated user email: ' . $e->getMessage(), 'error');
            // Try to refresh token if it's expired
            if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), 'unauthorized') !== false) {
                vics_log('Token appears expired, attempting refresh', 'warning');
                if ($this->client->getRefreshToken()) {
                    vics_log('Refresh token available, calling fetchAccessTokenWithRefreshToken');
                    try {
                        $refreshed_token = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                        vics_log('fetchAccessTokenWithRefreshToken returned: ' . json_encode($refreshed_token));

                        if (isset($refreshed_token['error'])) {
                            vics_log('Refresh token failed with error: ' . $refreshed_token['error'], 'error');
                        } else {
                            $new_token = $this->client->getAccessToken();
                            vics_log('New token from client: ' . json_encode($new_token));
                            update_option('vics_google_access_token', $new_token);
                            vics_log('Token refreshed and stored successfully');

                            // Try again with refreshed token
                            $this->client->setAccessToken($new_token);
                            vics_log('Client access token set with refreshed token, checking if token is set: ' . ($this->client->getAccessToken() ? 'yes' : 'no'));

                            $oauth2 = new Google_Service_Oauth2($this->client);
                            $userInfo = $oauth2->userinfo->get();
                            $email = $userInfo->email ?? null;
                            vics_log('Got email after refresh: ' . ($email ?: 'null'));
                            return $email;
                        }
                    } catch (Exception $refresh_error) {
                        vics_log('Token refresh failed: ' . $refresh_error->getMessage(), 'error');
                        // Clear invalid token
                        delete_option('vics_google_access_token');
                        vics_log('Cleared invalid token', 'warning');
                    }
                } else {
                    vics_log('No refresh token available', 'warning');
                }
            }
            return null;
        }
    }
}
