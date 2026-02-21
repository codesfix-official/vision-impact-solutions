<?php
/**
 * Google Sheets API Handler
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Google_Sheets {

    /**
     * Google Auth instance
     * @var VICS_Google_Auth
     */
    private $auth;

    /**
     * Google Sheets service
     * @var Google_Service_Sheets
     */
    private $sheets_service;

    /**
     * Google Drive service
     * @var Google_Service_Drive
     */
    private $drive_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new VICS_Google_Auth();
        $this->init_services();
    }

    /**
     * Initialize Google Services
     */
    private function init_services() {
        if ($this->auth->is_authenticated() && class_exists('Google_Service_Sheets')) {
            $client = $this->auth->get_client();
            $this->sheets_service = new Google_Service_Sheets($client);
            $this->drive_service = new Google_Service_Drive($client);
        }
    }

    /**
     * Check if services are available
     *
     * @return bool
     */
    public function is_available() {
        return $this->sheets_service && $this->drive_service;
    }

    /**
     * Get Google Sheets service
     *
     * @return Google_Service_Sheets|null
     */
    public function get_sheets_service() {
        return $this->sheets_service;
    }

    /**
     * Get Google Drive service
     *
     * @return Google_Service_Drive|null
     */
    public function get_drive_service() {
        return $this->drive_service;
    }

    /**
     * Create a new sheet by copying from master sheet
     *
     * @param string $master_sheet_id
     * @param string $new_sheet_name
     * @return string|null New sheet ID
     */
    public function create_sheet_from_master($master_sheet_id, $new_sheet_name, $agent_email = null) {
        vics_log('DEBUG: create_sheet_from_master called with master_id=' . $master_sheet_id . ', name=' . $new_sheet_name . ', email=' . $agent_email);
        
        if (!$this->is_available()) {
            vics_log('DEBUG: Google services not available', 'error');
            return null;
        }

        try {
            vics_log('DEBUG: Creating new spreadsheet using Sheets API...');
            // Create a new spreadsheet using Sheets API
            $new_spreadsheet = new Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => $new_sheet_name,
                    'locale' => 'en_US'
                ]
            ]);

            $created_spreadsheet = $this->sheets_service->spreadsheets->create($new_spreadsheet);
            $new_sheet_id = $created_spreadsheet->getSpreadsheetId();
            vics_log('DEBUG: New spreadsheet created successfully with ID: ' . $new_sheet_id);

            // Get the default sheet ID from the newly created spreadsheet (we'll delete it later)
            $new_spreadsheet_details = $this->sheets_service->spreadsheets->get($new_sheet_id);
            $default_sheet_id = $new_spreadsheet_details->getSheets()[0]->getProperties()->getSheetId();
            vics_log('DEBUG: Default sheet ID in new spreadsheet: ' . $default_sheet_id);

            // Get master sheet details to copy it WITH formatting
            vics_log('DEBUG: Fetching master sheet details...');
            $master_spreadsheet = $this->sheets_service->spreadsheets->get($master_sheet_id);
            $master_sheets = $master_spreadsheet->getSheets();
            
            if (empty($master_sheets)) {
                vics_log('DEBUG: Master spreadsheet has no sheets', 'error');
                return null;
            }

            $master_sheet = $master_sheets[0]; // Get first sheet
            $master_sheet_id_int = $master_sheet->getProperties()->getSheetId();
            $master_sheet_title = $master_sheet->getProperties()->getTitle();
            vics_log('DEBUG: Master sheet to copy - Title: ' . $master_sheet_title . ', SheetId: ' . $master_sheet_id_int);

            // Copy the master sheet to the new spreadsheet (this preserves ALL formatting)
            vics_log('DEBUG: Copying master sheet WITH formatting to new spreadsheet...');
            $copy_request = new Google_Service_Sheets_CopySheetToAnotherSpreadsheetRequest([
                'destinationSpreadsheetId' => $new_sheet_id
            ]);

            $response = $this->sheets_service->spreadsheets_sheets->copyTo(
                $master_sheet_id,
                $master_sheet_id_int,
                $copy_request
            );

            $copied_sheet_id = $response->getSheetId();
            vics_log('DEBUG: Sheet copied successfully with formatting. Copied SheetId: ' . $copied_sheet_id);

            // Now delete the default blank sheet that was created
            vics_log('DEBUG: Deleting default blank sheet...');
            $requests = [
                new Google_Service_Sheets_Request([
                    'deleteSheet' => [
                        'sheetId' => $default_sheet_id
                    ]
                ])
            ];

            $batch_update = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            $this->sheets_service->spreadsheets->batchUpdate($new_sheet_id, $batch_update);
            vics_log('DEBUG: Default blank sheet deleted successfully');

            // Share the sheet with the agent if email provided
            if ($agent_email) {
                vics_log('DEBUG: Sharing sheet with agent email: ' . $agent_email);
                $this->share_sheet_with_agent_sheets_api($new_sheet_id, $agent_email);
            }

            vics_log('Google Sheet Clone Created Successfully: ' . $new_sheet_id . ' (with formatting)');
            return $new_sheet_id;
        } catch (Exception $e) {
            vics_log('Google Sheets Creation Error: ' . $e->getMessage(), 'error');
            vics_log('DEBUG Error Details: ' . $e->getTraceAsString(), 'error');
            return null;
        }
    }



    /**
     * Share sheet with agent user via Drive API (fallback - may not work)
     * or just log that manual sharing is needed
     *
     * @param string $sheet_id
     * @param string $user_email
     * @return bool
     */
    private function share_sheet_with_agent_sheets_api($sheet_id, $user_email) {
        vics_log('DEBUG: share_sheet_with_agent_sheets_api called with sheet_id=' . $sheet_id . ', email=' . $user_email);
        
        if (!$this->drive_service) {
            vics_log('Sheet created but Drive API not available for sharing. Manual sharing may be needed for: ' . $user_email, 'warning');
            return false;
        }

        try {
            vics_log('DEBUG: Granting writer access to agent via Drive API...');

            // First, ensure agent has access (writer)
            $writer_permission = new Google_Service_Drive_Permission([
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $user_email
            ]);

            $this->drive_service->permissions->create($sheet_id, $writer_permission, [
                'sendNotificationEmail' => false,
                'emailMessage' => '',
                'supportsAllDrives' => true
            ]);

            vics_log('Sheet shared with writer access to agent: ' . $user_email);

            // Then attempt to transfer ownership (optional, may fail in some orgs)
            try {
                vics_log('DEBUG: Attempting ownership transfer to agent (optional)...');
                $owner_permission = new Google_Service_Drive_Permission([
                    'type' => 'user',
                    'role' => 'owner',
                    'emailAddress' => $user_email
                ]);

                $this->drive_service->permissions->create($sheet_id, $owner_permission, [
                    'sendNotificationEmail' => false,
                    'transferOwnership' => true,
                    'emailMessage' => '',
                    'supportsAllDrives' => true
                ]);

                vics_log('Sheet ownership transferred successfully to agent: ' . $user_email);
            } catch (Exception $ownership_error) {
                vics_log('Ownership transfer failed (non-critical): ' . $ownership_error->getMessage(), 'warning');
            }

            // Ensure link-based edit access so agent doesn't need manual owner approval
            $this->enable_link_editor_access($sheet_id);

            return true;
        } catch (Exception $e) {
            vics_log('Could not share sheet at all: ' . $e->getMessage(), 'error');

            // Fallback: make link editable so sheet is still accessible without manual approval flow
            if ($this->enable_link_editor_access($sheet_id)) {
                vics_log('Fallback link-sharing editor access enabled for sheet: ' . $sheet_id . '. Agent can edit via direct link.', 'warning');
                return true;
            }

            vics_log('Sheet created for ' . $user_email . ' but they still need to be manually granted access', 'warning');
            return false;
        }
    }

    /**
     * Enable "anyone with link" editor access for a sheet.
     *
     * @param string $sheet_id
     * @return bool
     */
    private function enable_link_editor_access($sheet_id) {
        if (!$this->drive_service) {
            return false;
        }

        try {
            $link_permission = new Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'writer',
                'allowFileDiscovery' => false
            ]);

            $this->drive_service->permissions->create($sheet_id, $link_permission, [
                'supportsAllDrives' => true
            ]);

            vics_log('Enabled anyone-with-link editor access for sheet: ' . $sheet_id);
            return true;
        } catch (Exception $e) {
            vics_log('Could not enable link-sharing editor fallback for sheet ' . $sheet_id . ': ' . $e->getMessage(), 'warning');
            return false;
        }
    }



    /**
     * Get master sheet structure (headers and columns)
     * Reads the actual master sheet to understand its structure dynamically
     *
     * @param string $master_sheet_id
     * @return array|null Array with 'sheet_title', 'headers', 'column_count', 'total_rows'
     */
    public function get_master_sheet_structure($master_sheet_id) {
        if (!$this->is_available()) {
            return null;
        }

        try {
            // Get master spreadsheet metadata
            $master_spreadsheet = $this->sheets_service->spreadsheets->get($master_sheet_id);

            // Get the first sheet in the master workbook
            $sheets = $master_spreadsheet->getSheets();
            if (empty($sheets)) {
                vics_log('No sheets found in master spreadsheet', 'error');
                return null;
            }

            $first_sheet = $sheets[0];
            $sheet_title = $first_sheet->getProperties()->getTitle();

            // Read all data from master sheet (no column limit)
            $range = $sheet_title . '!A:ZZ'; // Extended range to catch all columns
            $response = $this->sheets_service->spreadsheets_values->get($master_sheet_id, $range);
            $values = $response->getValues();

            if (empty($values)) {
                vics_log('Master sheet is empty', 'warning');
                return null;
            }

            // Extract headers from first row
            $headers = $values[0] ?? [];
            $column_count = count($headers);

            vics_log('Master sheet structure detected: ' . $column_count . ' columns | Headers: ' . implode(', ', $headers));

            return [
                'sheet_title' => $sheet_title,
                'headers' => $headers,
                'column_count' => $column_count,
                'total_rows' => count($values)
            ];
        } catch (Exception $e) {
            vics_log('Error reading master sheet structure: ' . $e->getMessage(), 'error');
            return null;
        }
    }



    /**
     * Convert column number to letter (1 = A, 2 = B, 27 = AA, etc.)
     *
     * @param int $col
     * @return string
     */
    private function num_to_col($col) {
        $col = intval($col);
        $result = '';
        while ($col > 0) {
            $col--;
            $result = chr(65 + ($col % 26)) . $result;
            $col = intval($col / 26);
        }
        return $result;
    }

}
