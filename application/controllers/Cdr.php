<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cdr extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Cdr_model');
        $this->load->model('Campaign_model');
        $this->load->model('Campaign_number_model');
    }

    /**
     * List all CDR records
     */
    public function index() {
        $data = array();

        // Filters
        $campaign_id = $this->input->get('campaign_id');
        $disposition = $this->input->get('disposition');
        $date_from = $this->input->get('date_from');
        $date_to = $this->input->get('date_to');

        // Build filter array
        $filters = array();
        if ($campaign_id) $filters['campaign_id'] = $campaign_id;
        if ($disposition) $filters['disposition'] = $disposition;
        if ($date_from) $filters['date_from'] = $date_from;
        if ($date_to) $filters['date_to'] = $date_to;

        // Pagination
        $this->load->library('pagination');
        $config['base_url'] = site_url('cdr/index');
        $config['total_rows'] = $this->Cdr_model->count_all($filters);
        $config['per_page'] = 50;
        $config['uri_segment'] = 3;
        $config['reuse_query_string'] = TRUE;

        // Bootstrap 4 pagination
        $config['full_tag_open'] = '<ul class="pagination">';
        $config['full_tag_close'] = '</ul>';
        $config['first_tag_open'] = '<li class="page-item">';
        $config['first_tag_close'] = '</li>';
        $config['last_tag_open'] = '<li class="page-item">';
        $config['last_tag_close'] = '</li>';
        $config['next_tag_open'] = '<li class="page-item">';
        $config['next_tag_close'] = '</li>';
        $config['prev_tag_open'] = '<li class="page-item">';
        $config['prev_tag_close'] = '</li>';
        $config['cur_tag_open'] = '<li class="page-item active"><a class="page-link" href="#">';
        $config['cur_tag_close'] = '</a></li>';
        $config['num_tag_open'] = '<li class="page-item">';
        $config['num_tag_close'] = '</li>';
        $config['attributes'] = array('class' => 'page-link');

        $this->pagination->initialize($config);

        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

        $data['cdr_records'] = $this->Cdr_model->get_all($filters, $config['per_page'], $page);
        $data['pagination'] = $this->pagination->create_links();

        // Get campaigns for filter
        $data['campaigns'] = $this->Campaign_model->get_all();

        // Filter values
        $data['filter_campaign_id'] = $campaign_id;
        $data['filter_disposition'] = $disposition;
        $data['filter_date_from'] = $date_from;
        $data['filter_date_to'] = $date_to;

        $this->load->view('templates/header', $data);
        $this->load->view('cdr/index', $data);
        $this->load->view('templates/footer');
    }

    /**
     * View CDR detail
     */
    public function view($id) {
        $data['cdr'] = $this->Cdr_model->get_by_id($id);

        if (!$data['cdr']) {
            show_404();
        }

        $this->load->view('templates/header', $data);
        $this->load->view('cdr/view', $data);
        $this->load->view('templates/footer');
    }

    /**
     * Get recording file path (handles both absolute and relative paths)
     */
    private function get_recording_path($recording_file) {
        if (empty($recording_file)) {
            return null;
        }

        // Check if it's an absolute path
        if (strpos($recording_file, '/') === 0) {
            return file_exists($recording_file) ? $recording_file : null;
        }

        // Try monitor directory first (dialer recordings)
        $path = '/var/spool/asterisk/monitor/' . $recording_file;
        if (file_exists($path)) {
            return $path;
        }

        // Fall back to recording directory
        $path = '/var/spool/asterisk/recording/' . $recording_file;
        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Download recording
     */
    public function download_recording($id) {
        $cdr = $this->Cdr_model->get_by_id($id);

        if (!$cdr || !$cdr->recording_file) {
            show_404();
        }

        $file_path = $this->get_recording_path($cdr->recording_file);

        if (!$file_path) {
            show_error('Recording file not found');
            return;
        }

        $this->load->helper('download');
        force_download($file_path, NULL);
    }

    /**
     * Play recording (stream audio)
     */
    public function play_recording($id) {
        $cdr = $this->Cdr_model->get_by_id($id);

        if (!$cdr || !$cdr->recording_file) {
            show_404();
        }

        $file_path = $this->get_recording_path($cdr->recording_file);

        if (!$file_path) {
            show_error('Recording file not found');
            return;
        }

        // Stream the file
        $fp = fopen($file_path, 'rb');

        header("Content-Type: audio/wav");
        header("Content-Length: " . filesize($file_path));
        header("Accept-Ranges: bytes");

        fpassthru($fp);
        fclose($fp);
        exit;
    }

    /**
     * Export CDR to CSV
     */
    public function export() {
        // Filters
        $campaign_id = $this->input->get('campaign_id');
        $disposition = $this->input->get('disposition');
        $date_from = $this->input->get('date_from');
        $date_to = $this->input->get('date_to');

        // Build filter array
        $filters = array();
        if ($campaign_id) $filters['campaign_id'] = $campaign_id;
        if ($disposition) $filters['disposition'] = $disposition;
        if ($date_from) $filters['date_from'] = $date_from;
        if ($date_to) $filters['date_to'] = $date_to;

        // Get all CDR records with filters
        $cdr_records = $this->Cdr_model->get_all($filters);

        // Generate CSV
        $filename = 'cdr_export_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, array('ID', 'Campaign', 'Caller ID', 'Destination', 'Destination Name', 'Agent', 'Start Time', 'Answer Time', 'End Time', 'Duration', 'Billsec', 'Disposition'));

        // Data rows
        foreach ($cdr_records as $cdr) {
            // Get destination name from campaign_numbers if available
            $dest_name = '';
            if (isset($cdr->campaign_number_id) && $cdr->campaign_number_id) {
                $this->load->model('Campaign_number_model');
                $number = $this->Campaign_number_model->get_by_id($cdr->campaign_number_id);
                if ($number && $number->data) {
                    $data = json_decode($number->data, true);
                    $dest_name = isset($data['name']) ? $data['name'] : '';
                }
            }

            fputcsv($output, array(
                $cdr->id,
                $cdr->campaign_id,
                $cdr->callerid,
                $cdr->destination,
                $dest_name,
                $cdr->agent,
                $cdr->start_time,
                $cdr->answer_time,
                $cdr->end_time,
                $cdr->duration,
                $cdr->billsec,
                $cdr->disposition
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Get CDR stats (AJAX)
     */
    public function stats() {
        header('Content-Type: application/json');

        $campaign_id = $this->input->get('campaign_id');
        $date_from = $this->input->get('date_from');
        $date_to = $this->input->get('date_to');

        $filters = array();
        if ($campaign_id) $filters['campaign_id'] = $campaign_id;
        if ($date_from) $filters['date_from'] = $date_from;
        if ($date_to) $filters['date_to'] = $date_to;

        $stats = $this->Cdr_model->get_stats($filters);

        echo json_encode($stats);
    }
}
