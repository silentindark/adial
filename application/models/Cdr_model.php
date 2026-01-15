<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cdr_model extends CI_Model {

    private $cdr_db;

    public function __construct() {
        parent::__construct();
        // Load asteriskcdrdb connection
        $this->cdr_db = $this->load->database('asteriskcdr', TRUE);
    }

    /**
     * Get CDR records with filters from asteriskcdrdb
     * Filter by accountcode which contains campaign_id
     */
    public function get_all($filters = array(), $limit = 100, $offset = 0) {
        // Only get dialer CDRs (accountcode is numeric = campaign_id)
        $this->cdr_db->where("accountcode != ''");
        $this->cdr_db->where("accountcode REGEXP '^[0-9]+$'", NULL, FALSE);

        if (isset($filters['campaign_id'])) {
            $this->cdr_db->where('accountcode', $filters['campaign_id']);
        }

        if (isset($filters['disposition'])) {
            $this->cdr_db->where('disposition', strtoupper($filters['disposition']));
        }

        if (isset($filters['start_date'])) {
            $this->cdr_db->where('calldate >=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $this->cdr_db->where('calldate <=', $filters['end_date']);
        }

        if (isset($filters['search'])) {
            $this->cdr_db->group_start();
            $this->cdr_db->like('src', $filters['search']);
            $this->cdr_db->or_like('dst', $filters['search']);
            $this->cdr_db->or_like('clid', $filters['search']);
            $this->cdr_db->group_end();
        }

        // Select with aliases to match view expectations
        // asteriskcdrdb.cdr fields: calldate, clid, src, dst, duration, billsec, disposition, accountcode, uniqueid, userfield, recordingfile
        return $this->cdr_db->select("
                *,
                uniqueid as id,
                accountcode as campaign_id,
                NULL as campaign_number_id,
                calldate as start_time,
                CASE WHEN billsec > 0 THEN DATE_ADD(calldate, INTERVAL (duration - billsec) SECOND) ELSE NULL END as answer_time,
                DATE_ADD(calldate, INTERVAL duration SECOND) as end_time,
                src as callerid,
                dst as destination,
                dstchannel as agent,
                LOWER(disposition) as disposition,
                recordingfile as recording_file
            ", FALSE)
                        ->order_by('calldate', 'DESC')
                        ->limit($limit, $offset)
                        ->get('cdr')
                        ->result();
    }

    /**
     * Count CDR records with filters
     */
    public function count_all($filters = array()) {
        // Only get dialer CDRs
        $this->cdr_db->where("accountcode != ''");
        $this->cdr_db->where("accountcode REGEXP '^[0-9]+$'", NULL, FALSE);

        if (isset($filters['campaign_id'])) {
            $this->cdr_db->where('accountcode', $filters['campaign_id']);
        }

        if (isset($filters['disposition'])) {
            $this->cdr_db->where('disposition', strtoupper($filters['disposition']));
        }

        if (isset($filters['start_date'])) {
            $this->cdr_db->where('calldate >=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $this->cdr_db->where('calldate <=', $filters['end_date']);
        }

        if (isset($filters['search'])) {
            $this->cdr_db->group_start();
            $this->cdr_db->like('src', $filters['search']);
            $this->cdr_db->or_like('dst', $filters['search']);
            $this->cdr_db->or_like('clid', $filters['search']);
            $this->cdr_db->group_end();
        }

        return $this->cdr_db->count_all_results('cdr');
    }

    /**
     * Get CDR by uniqueid
     */
    public function get_by_uniqueid($uniqueid) {
        return $this->cdr_db->where('uniqueid', $uniqueid)
                        ->get('cdr')
                        ->row();
    }

    /**
     * Get CDR by id (uniqueid)
     */
    public function get_by_id($id) {
        return $this->cdr_db->select("
                *,
                uniqueid as id,
                accountcode as campaign_id,
                NULL as campaign_number_id,
                calldate as start_time,
                CASE WHEN billsec > 0 THEN DATE_ADD(calldate, INTERVAL (duration - billsec) SECOND) ELSE NULL END as answer_time,
                DATE_ADD(calldate, INTERVAL duration SECOND) as end_time,
                src as callerid,
                dst as destination,
                dstchannel as agent,
                LOWER(disposition) as disposition,
                recordingfile as recording_file
            ", FALSE)
                        ->where('uniqueid', $id)
                        ->get('cdr')
                        ->row();
    }

    /**
     * Get today's call stats for dashboard
     */
    public function get_today_stats() {
        $today = date('Y-m-d');

        $total = $this->cdr_db->where("accountcode != ''")
                              ->where("accountcode REGEXP '^[0-9]+$'", NULL, FALSE)
                              ->where("DATE(calldate)", $today)
                              ->count_all_results('cdr');

        $answered = $this->cdr_db->where("accountcode != ''")
                                  ->where("accountcode REGEXP '^[0-9]+$'", NULL, FALSE)
                                  ->where("DATE(calldate)", $today)
                                  ->where('disposition', 'ANSWERED')
                                  ->count_all_results('cdr');

        return [
            'total' => $total,
            'answered' => $answered
        ];
    }
}
