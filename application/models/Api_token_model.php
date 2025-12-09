<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API Token Model
 * Handles API token operations for REST API authentication
 */
class Api_token_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Generate a secure random API token
     */
    public function generate_token() {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * Create a new API token
     */
    public function create($user_id, $name = null, $permissions = null, $expires_at = null) {
        $token = $this->generate_token();

        $data = array(
            'user_id' => $user_id,
            'token' => $token,
            'name' => $name,
            'permissions' => $permissions ? json_encode($permissions) : null,
            'is_active' => 1,
            'expires_at' => $expires_at,
            'created_at' => date('Y-m-d H:i:s')
        );

        $this->db->insert('api_tokens', $data);
        return $token;
    }

    /**
     * Validate API token and return user info
     */
    public function validate($token) {
        $this->db->select('api_tokens.*, users.username, users.email, users.role, users.is_active as user_active, users.api_access');
        $this->db->from('api_tokens');
        $this->db->join('users', 'users.id = api_tokens.user_id');
        $this->db->where('api_tokens.token', $token);
        $this->db->where('api_tokens.is_active', 1);
        $this->db->where('users.is_active', 1);
        $this->db->where('users.api_access', 1);

        // Check if not expired
        $this->db->group_start();
        $this->db->where('api_tokens.expires_at IS NULL');
        $this->db->or_where('api_tokens.expires_at >', date('Y-m-d H:i:s'));
        $this->db->group_end();

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $token_data = $query->row();

            // Update last used timestamp
            $this->db->where('id', $token_data->id);
            $this->db->update('api_tokens', array('last_used' => date('Y-m-d H:i:s')));

            return $token_data;
        }

        return false;
    }

    /**
     * Check if token has permission for an endpoint
     */
    public function has_permission($token_data, $endpoint) {
        // If no permissions specified, allow all
        if (empty($token_data->permissions)) {
            return true;
        }

        $permissions = json_decode($token_data->permissions, true);
        if (!is_array($permissions)) {
            return true;
        }

        // Check if endpoint matches any permission pattern
        foreach ($permissions as $pattern) {
            if ($this->match_permission($endpoint, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match endpoint against permission pattern
     * Supports wildcards: campaigns/* matches campaigns/list, campaigns/view, etc.
     */
    private function match_permission($endpoint, $pattern) {
        // Convert pattern to regex
        $regex = str_replace(['/', '*'], ['\/', '.*'], $pattern);
        $regex = '/^' . $regex . '$/';

        return preg_match($regex, $endpoint) === 1;
    }

    /**
     * Get all tokens for a user
     */
    public function get_by_user($user_id) {
        $this->db->where('user_id', $user_id);
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('api_tokens')->result();
    }

    /**
     * Get token by ID
     */
    public function get_by_id($id) {
        return $this->db->get_where('api_tokens', array('id' => $id))->row();
    }

    /**
     * Update token
     */
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id);
        return $this->db->update('api_tokens', $data);
    }

    /**
     * Revoke/deactivate token
     */
    public function revoke($id) {
        return $this->update($id, array('is_active' => 0));
    }

    /**
     * Delete token
     */
    public function delete($id) {
        $this->db->where('id', $id);
        return $this->db->delete('api_tokens');
    }

    /**
     * Get all tokens (admin only)
     */
    public function get_all() {
        $this->db->select('api_tokens.*, users.username, users.email');
        $this->db->from('api_tokens');
        $this->db->join('users', 'users.id = api_tokens.user_id');
        $this->db->order_by('api_tokens.created_at', 'DESC');
        return $this->db->get()->result();
    }

    /**
     * Clean up expired tokens
     */
    public function cleanup_expired() {
        $this->db->where('expires_at <', date('Y-m-d H:i:s'));
        $this->db->where('expires_at IS NOT NULL');
        return $this->db->delete('api_tokens');
    }
}
