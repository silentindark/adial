<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

/**
 * API Controller v1
 * RESTful API for ARI Dialer
 *
 * Authentication: Bearer token in Authorization header
 * Example: Authorization: Bearer your_api_token_here
 */
class Api extends API_Controller {

    public function __construct() {
        parent::__construct();

        // Load all required models
        $this->load->model('Campaign_model');
        $this->load->model('Campaign_number_model');
        $this->load->model('Cdr_model');
        $this->load->model('Ivr_menu_model');
        $this->load->model('Ivr_action_model');
        $this->load->model('User_model');
        $this->load->model('Settings_model');

        // Load ARI client library
        $this->load->library('Ari_client');
    }

    /**
     * API Index - Show API info
     * GET /api
     */
    public function index() {
        $this->response_success(array(
            'name' => 'ARI Dialer API',
            'version' => '1.0',
            'user' => array(
                'id' => $this->user->id,
                'username' => $this->user->username,
                'role' => $this->user->role
            ),
            'endpoints' => array(
                'campaigns' => '/api/campaigns',
                'numbers' => '/api/numbers',
                'cdr' => '/api/cdr',
                'monitoring' => '/api/monitoring',
                'ivr' => '/api/ivr',
                'settings' => '/api/settings'
            )
        ));
    }

    // =========================================================================
    // CAMPAIGN ENDPOINTS
    // =========================================================================

    /**
     * List all campaigns
     * GET /api/campaigns
     * Query params: status (optional: stopped, running, paused)
     */
    public function campaigns_list() {
        $status = $this->input->get('status');
        $campaigns = $this->Campaign_model->get_all($status);

        // Get stats for each campaign
        foreach ($campaigns as &$campaign) {
            $campaign->stats = $this->Campaign_model->get_stats($campaign->id);
        }

        $this->response_success($campaigns, 'Campaigns retrieved successfully');
    }

    /**
     * Get campaign details
     * GET /api/campaigns/:id
     */
    public function campaigns_view($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        // Get campaign statistics
        $campaign->stats = $this->Campaign_model->get_stats($id);

        $this->response_success($campaign, 'Campaign retrieved successfully');
    }

    /**
     * Create new campaign
     * POST /api/campaigns
     * Body: name, description, trunk_type, trunk_value, callerid, agent_dest_type, agent_dest_value, record_calls, concurrent_calls, retry_times, retry_delay
     */
    public function campaigns_create() {
        $data = $this->get_json_input();

        // Validate required fields
        $this->validate_required($data, array('name', 'trunk_type', 'trunk_value', 'agent_dest_type', 'agent_dest_value'));

        // Sanitize input
        $campaign_data = array(
            'name' => $this->sanitize($data['name']),
            'description' => isset($data['description']) ? $this->sanitize($data['description']) : null,
            'trunk_type' => $this->sanitize($data['trunk_type']),
            'trunk_value' => $this->sanitize($data['trunk_value']),
            'callerid' => isset($data['callerid']) ? $this->sanitize($data['callerid']) : null,
            'agent_dest_type' => $this->sanitize($data['agent_dest_type']),
            'agent_dest_value' => $this->sanitize($data['agent_dest_value']),
            'record_calls' => isset($data['record_calls']) ? (int)$data['record_calls'] : 0,
            'concurrent_calls' => isset($data['concurrent_calls']) ? (int)$data['concurrent_calls'] : 1,
            'retry_times' => isset($data['retry_times']) ? (int)$data['retry_times'] : 0,
            'retry_delay' => isset($data['retry_delay']) ? (int)$data['retry_delay'] : 300,
            'status' => 'stopped'
        );

        // Validate trunk_type
        if (!in_array($campaign_data['trunk_type'], array('custom', 'pjsip', 'sip'))) {
            $this->response_error('Invalid trunk_type. Must be: custom, pjsip, or sip', 400);
        }

        // Validate agent_dest_type
        if (!in_array($campaign_data['agent_dest_type'], array('custom', 'exten', 'ivr'))) {
            $this->response_error('Invalid agent_dest_type. Must be: custom, exten, or ivr', 400);
        }

        $campaign_id = $this->Campaign_model->create($campaign_data);

        if ($campaign_id) {
            $campaign = $this->Campaign_model->get_by_id($campaign_id);
            $this->response_success($campaign, 'Campaign created successfully', 201);
        } else {
            $this->response_error('Failed to create campaign', 500);
        }
    }

    /**
     * Update campaign
     * PUT /api/campaigns/:id
     * Body: Any campaign fields to update
     */
    public function campaigns_update($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        // Cannot update running campaign
        if ($campaign->status === 'running') {
            $this->response_error('Cannot update running campaign. Stop it first.', 400);
        }

        $data = $this->get_json_input();

        // Build update array
        $update_data = array();

        $allowed_fields = array('name', 'description', 'trunk_type', 'trunk_value', 'callerid',
                               'agent_dest_type', 'agent_dest_value', 'record_calls',
                               'concurrent_calls', 'retry_times', 'retry_delay');

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $this->sanitize($data[$field]);
            }
        }

        if (empty($update_data)) {
            $this->response_error('No valid fields to update', 400);
        }

        if ($this->Campaign_model->update($id, $update_data)) {
            $campaign = $this->Campaign_model->get_by_id($id);
            $this->response_success($campaign, 'Campaign updated successfully');
        } else {
            $this->response_error('Failed to update campaign', 500);
        }
    }

    /**
     * Delete campaign
     * DELETE /api/campaigns/:id
     */
    public function campaigns_delete($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        // Cannot delete running campaign
        if ($campaign->status === 'running') {
            $this->response_error('Cannot delete running campaign. Stop it first.', 400);
        }

        if ($this->Campaign_model->delete($id)) {
            $this->response_success(null, 'Campaign deleted successfully');
        } else {
            $this->response_error('Failed to delete campaign', 500);
        }
    }

    /**
     * Start campaign
     * POST /api/campaigns/:id/start
     */
    public function campaigns_start($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        if ($campaign->status === 'running') {
            $this->response_error('Campaign is already running', 400);
        }

        // Update status to running
        if ($this->Campaign_model->update_status($id, 'running')) {
            $this->response_success(array(
                'campaign_id' => $id,
                'status' => 'running'
            ), 'Campaign started successfully');
        } else {
            $this->response_error('Failed to start campaign', 500);
        }
    }

    /**
     * Stop campaign
     * POST /api/campaigns/:id/stop
     */
    public function campaigns_stop($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        if ($campaign->status === 'stopped') {
            $this->response_error('Campaign is already stopped', 400);
        }

        // Update status to stopped
        if ($this->Campaign_model->update_status($id, 'stopped')) {
            // Reset numbers for fresh start
            $this->db->where('campaign_id', $id);
            $this->db->where_in('status', array('calling', 'pending'));
            $this->db->update('campaign_numbers', array(
                'status' => 'pending',
                'attempts' => 0
            ));

            $this->response_success(array(
                'campaign_id' => $id,
                'status' => 'stopped'
            ), 'Campaign stopped successfully');
        } else {
            $this->response_error('Failed to stop campaign', 500);
        }
    }

    /**
     * Pause campaign
     * POST /api/campaigns/:id/pause
     */
    public function campaigns_pause($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        if ($campaign->status !== 'running') {
            $this->response_error('Can only pause a running campaign', 400);
        }

        // Update status to paused
        if ($this->Campaign_model->update_status($id, 'paused')) {
            $this->response_success(array(
                'campaign_id' => $id,
                'status' => 'paused'
            ), 'Campaign paused successfully');
        } else {
            $this->response_error('Failed to pause campaign', 500);
        }
    }

    /**
     * Resume campaign (unpause)
     * POST /api/campaigns/:id/resume
     */
    public function campaigns_resume($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        if ($campaign->status !== 'paused') {
            $this->response_error('Can only resume a paused campaign', 400);
        }

        // Update status to running
        if ($this->Campaign_model->update_status($id, 'running')) {
            $this->response_success(array(
                'campaign_id' => $id,
                'status' => 'running'
            ), 'Campaign resumed successfully');
        } else {
            $this->response_error('Failed to resume campaign', 500);
        }
    }

    /**
     * Get campaign statistics
     * GET /api/campaigns/:id/stats
     */
    public function campaigns_stats($id) {
        $campaign = $this->Campaign_model->get_by_id($id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        $stats = $this->Campaign_model->get_stats($id);

        $this->response_success($stats, 'Campaign statistics retrieved successfully');
    }

    // =========================================================================
    // CAMPAIGN NUMBERS ENDPOINTS
    // =========================================================================

    /**
     * Get numbers for a campaign
     * GET /api/campaigns/:id/numbers
     * Query params: status, page, per_page
     */
    public function numbers_list($campaign_id) {
        $campaign = $this->Campaign_model->get_by_id($campaign_id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        $pagination = $this->get_pagination();
        $status = $this->input->get('status');

        $numbers = $this->Campaign_number_model->get_by_campaign(
            $campaign_id,
            $status,
            $pagination['per_page'],
            $pagination['offset']
        );

        // Get total count
        $this->db->where('campaign_id', $campaign_id);
        if ($status) {
            $this->db->where('status', $status);
        }
        $total = $this->db->count_all_results('campaign_numbers');

        $this->response_paginated(
            $numbers,
            $total,
            $pagination['page'],
            $pagination['per_page'],
            'Numbers retrieved successfully'
        );
    }

    /**
     * Add single number to campaign
     * POST /api/campaigns/:id/numbers
     * Body: phone_number, data (optional JSON object with name, etc.)
     */
    public function numbers_add($campaign_id) {
        $campaign = $this->Campaign_model->get_by_id($campaign_id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        $input = $this->get_json_input();

        // Validate required fields
        $this->validate_required($input, array('phone_number'));

        $phone_number = $this->sanitize($input['phone_number']);
        $data = isset($input['data']) ? $input['data'] : null;

        if ($this->Campaign_number_model->add_number($campaign_id, $phone_number, $data)) {
            $this->response_success(array(
                'campaign_id' => $campaign_id,
                'phone_number' => $phone_number
            ), 'Number added successfully', 201);
        } else {
            $this->response_error('Failed to add number', 500);
        }
    }

    /**
     * Bulk add numbers to campaign
     * POST /api/campaigns/:id/numbers/bulk
     * Body: numbers array [{ phone_number, data }, ...]
     */
    public function numbers_bulk_add($campaign_id) {
        $campaign = $this->Campaign_model->get_by_id($campaign_id);

        if (!$campaign) {
            $this->response_error('Campaign not found', 404);
        }

        $input = $this->get_json_input();

        if (!isset($input['numbers']) || !is_array($input['numbers'])) {
            $this->response_error('Invalid input. Expected "numbers" array', 400);
        }

        $numbers = array();
        foreach ($input['numbers'] as $item) {
            if (!isset($item['phone_number'])) {
                continue;
            }

            $numbers[] = array(
                'campaign_id' => $campaign_id,
                'phone_number' => $this->sanitize($item['phone_number']),
                'data' => isset($item['data']) ? json_encode($item['data']) : null,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => date('Y-m-d H:i:s')
            );
        }

        if (empty($numbers)) {
            $this->response_error('No valid numbers provided', 400);
        }

        if ($this->Campaign_number_model->bulk_add($campaign_id, $numbers)) {
            $this->response_success(array(
                'campaign_id' => $campaign_id,
                'count' => count($numbers)
            ), count($numbers) . ' numbers added successfully', 201);
        } else {
            $this->response_error('Failed to add numbers', 500);
        }
    }

    /**
     * Delete number from campaign
     * DELETE /api/numbers/:id
     */
    public function numbers_delete($number_id) {
        $number = $this->Campaign_number_model->get_by_id($number_id);

        if (!$number) {
            $this->response_error('Number not found', 404);
        }

        // Cannot delete if currently calling
        if ($number->status === 'calling') {
            $this->response_error('Cannot delete number with status "calling"', 400);
        }

        if ($this->Campaign_number_model->delete($number_id)) {
            $this->response_success(null, 'Number deleted successfully');
        } else {
            $this->response_error('Failed to delete number', 500);
        }
    }

    /**
     * Get number details
     * GET /api/numbers/:id
     */
    public function numbers_view($number_id) {
        $number = $this->Campaign_number_model->get_by_id($number_id);

        if (!$number) {
            $this->response_error('Number not found', 404);
        }

        $this->response_success($number, 'Number retrieved successfully');
    }

    // =========================================================================
    // CDR ENDPOINTS
    // =========================================================================

    /**
     * Get CDR records
     * GET /api/cdr
     * Query params: campaign_id, disposition, start_date, end_date, search, page, per_page
     */
    public function cdr_list() {
        $pagination = $this->get_pagination();

        $filters = array(
            'campaign_id' => $this->input->get('campaign_id'),
            'disposition' => $this->input->get('disposition'),
            'start_date' => $this->input->get('start_date'),
            'end_date' => $this->input->get('end_date'),
            'search' => $this->input->get('search')
        );

        $records = $this->Cdr_model->get_all($filters, $pagination['per_page'], $pagination['offset']);
        $total = $this->Cdr_model->count_all($filters);

        $this->response_paginated(
            $records,
            $total,
            $pagination['page'],
            $pagination['per_page'],
            'CDR records retrieved successfully'
        );
    }

    /**
     * Get CDR record details
     * GET /api/cdr/:id
     */
    public function cdr_view($id) {
        $record = $this->Cdr_model->get_by_id($id);

        if (!$record) {
            $this->response_error('CDR record not found', 404);
        }

        $this->response_success($record, 'CDR record retrieved successfully');
    }

    /**
     * Get CDR statistics
     * GET /api/cdr/stats
     * Query params: campaign_id, start_date, end_date
     */
    public function cdr_stats() {
        $campaign_id = $this->input->get('campaign_id');
        $start_date = $this->input->get('start_date');
        $end_date = $this->input->get('end_date');

        $this->db->select('
            COUNT(*) as total_calls,
            SUM(CASE WHEN disposition = "answered" THEN 1 ELSE 0 END) as answered,
            SUM(CASE WHEN disposition = "no_answer" THEN 1 ELSE 0 END) as no_answer,
            SUM(CASE WHEN disposition = "busy" THEN 1 ELSE 0 END) as busy,
            SUM(CASE WHEN disposition = "failed" THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN disposition = "cancelled" THEN 1 ELSE 0 END) as cancelled,
            AVG(duration) as avg_duration,
            AVG(billsec) as avg_billsec,
            SUM(duration) as total_duration,
            SUM(billsec) as total_billsec
        ');

        if ($campaign_id) {
            $this->db->where('campaign_id', $campaign_id);
        }

        if ($start_date) {
            $this->db->where('start_time >=', $start_date);
        }

        if ($end_date) {
            $this->db->where('start_time <=', $end_date);
        }

        $stats = $this->db->get('cdr')->row();

        $this->response_success($stats, 'CDR statistics retrieved successfully');
    }

    // =========================================================================
    // MONITORING ENDPOINTS
    // =========================================================================

    /**
     * Get system status
     * GET /api/monitoring/status
     */
    public function monitoring_status() {
        // Check Asterisk status
        $asterisk_status = false;
        try {
            $info = $this->Ari_client->get_asterisk_info();
            $asterisk_status = isset($info['system_name']);
        } catch (Exception $e) {
            $asterisk_status = false;
        }

        // Check database
        $database_status = $this->db->conn_id ? true : false;

        // Get active channels count
        $active_channels = 0;
        if ($asterisk_status) {
            try {
                $channels = $this->Ari_client->get_channels();
                $active_channels = is_array($channels) ? count($channels) : 0;
            } catch (Exception $e) {
                $active_channels = 0;
            }
        }

        // Get active campaigns count
        $active_campaigns = $this->Campaign_model->get_active();
        $active_campaigns_count = is_array($active_campaigns) ? count($active_campaigns) : 0;

        $status = array(
            'asterisk' => $asterisk_status,
            'database' => $database_status,
            'active_channels' => $active_channels,
            'active_campaigns' => $active_campaigns_count,
            'timestamp' => date('Y-m-d H:i:s')
        );

        $this->response_success($status, 'System status retrieved successfully');
    }

    /**
     * Get active channels
     * GET /api/monitoring/channels
     */
    public function monitoring_channels() {
        try {
            $channels = $this->Ari_client->get_channels();

            $this->response_success(array(
                'channels' => $channels,
                'count' => is_array($channels) ? count($channels) : 0
            ), 'Active channels retrieved successfully');
        } catch (Exception $e) {
            $this->response_error('Failed to get channels: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get real-time data for all campaigns
     * GET /api/monitoring/realtime
     */
    public function monitoring_realtime() {
        // Get active campaigns with stats
        $campaigns = $this->Campaign_model->get_all('running');
        foreach ($campaigns as &$campaign) {
            $campaign->stats = $this->Campaign_model->get_stats($campaign->id);
        }

        // Get channels
        $channels = array();
        $channel_count = 0;
        try {
            $channels = $this->Ari_client->get_channels();
            $channel_count = is_array($channels) ? count($channels) : 0;
        } catch (Exception $e) {
            // Ignore error
        }

        // Get today's stats
        $today = date('Y-m-d');
        $this->db->select('
            COUNT(*) as total_calls,
            SUM(CASE WHEN disposition = "answered" THEN 1 ELSE 0 END) as answered,
            SUM(CASE WHEN disposition = "no_answer" THEN 1 ELSE 0 END) as no_answer,
            SUM(CASE WHEN disposition = "busy" THEN 1 ELSE 0 END) as busy,
            SUM(CASE WHEN disposition = "failed" THEN 1 ELSE 0 END) as failed
        ');
        $this->db->where('DATE(start_time)', $today);
        $today_stats = $this->db->get('cdr')->row();

        $data = array(
            'campaigns' => $campaigns,
            'channels' => $channels,
            'channel_count' => $channel_count,
            'today_stats' => $today_stats,
            'timestamp' => date('Y-m-d H:i:s')
        );

        $this->response_success($data, 'Real-time data retrieved successfully');
    }

    // =========================================================================
    // IVR ENDPOINTS
    // =========================================================================

    /**
     * List IVR menus
     * GET /api/ivr
     * Query params: campaign_id (optional)
     */
    public function ivr_list() {
        $campaign_id = $this->input->get('campaign_id');

        if ($campaign_id) {
            $menus = $this->Ivr_menu_model->get_by_campaign($campaign_id);
        } else {
            $menus = $this->Ivr_menu_model->get_all();
        }

        $this->response_success($menus, 'IVR menus retrieved successfully');
    }

    /**
     * Get IVR menu with actions
     * GET /api/ivr/:id
     */
    public function ivr_view($id) {
        $menu = $this->Ivr_menu_model->get_with_actions($id);

        if (!$menu) {
            $this->response_error('IVR menu not found', 404);
        }

        $this->response_success($menu, 'IVR menu retrieved successfully');
    }

    /**
     * Delete IVR menu
     * DELETE /api/ivr/:id
     */
    public function ivr_delete($id) {
        $menu = $this->Ivr_menu_model->get_by_id($id);

        if (!$menu) {
            $this->response_error('IVR menu not found', 404);
        }

        if ($this->Ivr_menu_model->delete($id)) {
            $this->response_success(null, 'IVR menu deleted successfully');
        } else {
            $this->response_error('Failed to delete IVR menu', 500);
        }
    }

    // =========================================================================
    // SETTINGS & USER MANAGEMENT ENDPOINTS (Admin only)
    // =========================================================================

    /**
     * List all users (Admin only)
     * GET /api/users
     */
    public function users_list() {
        $this->require_admin();

        $users = $this->User_model->get_all();

        // Remove password field
        foreach ($users as &$user) {
            unset($user->password);
        }

        $this->response_success($users, 'Users retrieved successfully');
    }

    /**
     * Create user (Admin only)
     * POST /api/users
     * Body: username, password, email, full_name, role, api_access
     */
    public function users_create() {
        $this->require_admin();

        $input = $this->get_json_input();

        $this->validate_required($input, array('username', 'password', 'email'));

        $data = array(
            'username' => $this->sanitize($input['username']),
            'password' => $input['password'], // Will be hashed by model
            'email' => $this->sanitize($input['email']),
            'full_name' => isset($input['full_name']) ? $this->sanitize($input['full_name']) : null,
            'role' => isset($input['role']) ? $this->sanitize($input['role']) : 'user',
            'api_access' => isset($input['api_access']) ? (int)$input['api_access'] : 1,
            'is_active' => 1
        );

        $user_id = $this->User_model->create($data);

        if ($user_id) {
            $user = $this->User_model->get_by_id($user_id);
            unset($user->password);
            $this->response_success($user, 'User created successfully', 201);
        } else {
            $this->response_error('Failed to create user', 500);
        }
    }

    /**
     * Update user (Admin only)
     * PUT /api/users/:id
     */
    public function users_update($id) {
        $this->require_admin();

        $user = $this->User_model->get_by_id($id);
        if (!$user) {
            $this->response_error('User not found', 404);
        }

        $input = $this->get_json_input();

        $update_data = array();
        $allowed_fields = array('username', 'email', 'full_name', 'role', 'is_active', 'api_access');

        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_data[$field] = $this->sanitize($input[$field]);
            }
        }

        // Handle password separately
        if (isset($input['password']) && !empty($input['password'])) {
            $update_data['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        if (empty($update_data)) {
            $this->response_error('No valid fields to update', 400);
        }

        if ($this->User_model->update($id, $update_data)) {
            $user = $this->User_model->get_by_id($id);
            unset($user->password);
            $this->response_success($user, 'User updated successfully');
        } else {
            $this->response_error('Failed to update user', 500);
        }
    }

    /**
     * Delete user (Admin only)
     * DELETE /api/users/:id
     */
    public function users_delete($id) {
        $this->require_admin();

        if ($this->User_model->delete($id)) {
            $this->response_success(null, 'User deleted successfully');
        } else {
            $this->response_error('Failed to delete user', 500);
        }
    }

    // =========================================================================
    // API TOKEN MANAGEMENT
    // =========================================================================

    /**
     * List API tokens for current user
     * GET /api/tokens
     */
    public function tokens_list() {
        $tokens = $this->Api_token_model->get_by_user($this->user->id);

        // Mask tokens for security (show only first 8 chars)
        foreach ($tokens as &$token) {
            $token->token = substr($token->token, 0, 8) . '...' . substr($token->token, -8);
        }

        $this->response_success($tokens, 'API tokens retrieved successfully');
    }

    /**
     * Create new API token
     * POST /api/tokens
     * Body: name, permissions (optional), expires_at (optional)
     */
    public function tokens_create() {
        $input = $this->get_json_input();

        $name = isset($input['name']) ? $this->sanitize($input['name']) : 'API Token';
        $permissions = isset($input['permissions']) ? $input['permissions'] : null;
        $expires_at = isset($input['expires_at']) ? $input['expires_at'] : null;

        $token = $this->Api_token_model->create($this->user->id, $name, $permissions, $expires_at);

        if ($token) {
            $this->response_success(array(
                'token' => $token,
                'name' => $name,
                'message' => 'Save this token securely. It will not be shown again.'
            ), 'API token created successfully', 201);
        } else {
            $this->response_error('Failed to create API token', 500);
        }
    }

    /**
     * Revoke API token
     * DELETE /api/tokens/:id
     */
    public function tokens_revoke($id) {
        $token = $this->Api_token_model->get_by_id($id);

        if (!$token) {
            $this->response_error('Token not found', 404);
        }

        // Only owner or admin can revoke
        if ($token->user_id != $this->user->id && $this->user->role !== 'admin') {
            $this->response_error('You do not have permission to revoke this token', 403);
        }

        if ($this->Api_token_model->revoke($id)) {
            $this->response_success(null, 'API token revoked successfully');
        } else {
            $this->response_error('Failed to revoke API token', 500);
        }
    }
}
