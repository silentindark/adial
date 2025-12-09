<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API Base Controller
 * Handles API authentication, JSON responses, and common API functionality
 */
class API_Controller extends CI_Controller {

    protected $user;
    protected $token_data;
    protected $require_auth = true;

    public function __construct() {
        parent::__construct();

        // Set JSON header
        header('Content-Type: application/json');

        // Enable CORS if needed
        $this->enable_cors();

        // Load required models
        $this->load->model('Api_token_model');

        // Authenticate if required
        if ($this->require_auth) {
            $this->authenticate();
        }
    }

    /**
     * Enable CORS for API requests
     */
    private function enable_cors() {
        // Allow from any origin
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }

            exit(0);
        }
    }

    /**
     * Authenticate API request using Bearer token
     */
    private function authenticate() {
        $token = $this->get_bearer_token();

        if (!$token) {
            $this->response_error('Authentication required', 401);
        }

        $token_data = $this->Api_token_model->validate($token);

        if (!$token_data) {
            $this->response_error('Invalid or expired token', 401);
        }

        // Store token data and user info
        $this->token_data = $token_data;
        $this->user = (object) array(
            'id' => $token_data->user_id,
            'username' => $token_data->username,
            'email' => $token_data->email,
            'role' => $token_data->role
        );
    }

    /**
     * Get Bearer token from Authorization header
     */
    private function get_bearer_token() {
        $headers = $this->get_authorization_header();

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        // Also check for token in query string (less secure, for development only)
        if (isset($_GET['api_token'])) {
            return $_GET['api_token'];
        }

        return null;
    }

    /**
     * Get Authorization header
     */
    private function get_authorization_header() {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        return $headers;
    }

    /**
     * Check if user has admin role
     */
    protected function require_admin() {
        if ($this->user->role !== 'admin') {
            $this->response_error('Admin access required', 403);
        }
    }

    /**
     * Check endpoint permission
     */
    protected function check_permission($endpoint) {
        if (!$this->Api_token_model->has_permission($this->token_data, $endpoint)) {
            $this->response_error('Permission denied for this endpoint', 403);
        }
    }

    /**
     * Send JSON success response
     */
    protected function response_success($data = null, $message = null, $code = 200) {
        $response = array(
            'success' => true,
            'code' => $code
        );

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        http_response_code($code);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send JSON error response
     */
    protected function response_error($message, $code = 400, $errors = null) {
        $response = array(
            'success' => false,
            'code' => $code,
            'message' => $message
        );

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        http_response_code($code);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send paginated response
     */
    protected function response_paginated($data, $total, $page, $per_page, $message = null) {
        $response = array(
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => array(
                'total' => (int)$total,
                'per_page' => (int)$per_page,
                'current_page' => (int)$page,
                'total_pages' => ceil($total / $per_page),
                'from' => (($page - 1) * $per_page) + 1,
                'to' => min($page * $per_page, $total)
            )
        );

        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Get request body as JSON
     */
    protected function get_json_input() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    /**
     * Validate required fields
     */
    protected function validate_required($data, $required_fields) {
        $missing = array();

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->response_error('Missing required fields', 400, array(
                'missing_fields' => $missing
            ));
        }
    }

    /**
     * Sanitize input data
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map(array($this, 'sanitize'), $data);
        }

        return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get pagination parameters from request
     */
    protected function get_pagination() {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
        $offset = ($page - 1) * $per_page;

        return array(
            'page' => $page,
            'per_page' => $per_page,
            'offset' => $offset
        );
    }
}
