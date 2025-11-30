<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Projection extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Projection_model', 'projection');
        $this->load->helper('security'); // for xss_clean
    }

    /** GET (or AJAX) – return JSON for student/year */
    public function get()
    {
        $this->output->set_content_type('application/json');

        $student_id = (int)$this->input->get_post('student_id', true);
        $year       = (int)$this->input->get_post('year', true);

        if ($student_id <= 0 || $year <= 0) {
            return $this->json_fail('Missing student or year');
        }

        $data = $this->projection->get_projection_block($student_id, $year);
        return $this->json_ok($data);
    }

    /** POST – save labels/committed/actual arrays */
    public function save()
{
    $this->output->set_content_type('application/json');

    // Accept normal POST or AJAX
    if (strtolower($this->input->method()) !== 'post') {
        return $this->json_fail('Invalid request method');
    }

    $student_id = (int)$this->input->post('student_id', true);
    $year       = (int)$this->input->post('year', true);

    // Accept both proj_labels and proj_labels[] style keys
    $labels    = $this->input->post('proj_labels', true);
    if ($labels === null) $labels = $this->input->post('proj_labels[]', true);
    $committed = $this->input->post('proj_committed', true);
    if ($committed === null) $committed = $this->input->post('proj_committed[]', true);
    $actual    = $this->input->post('proj_actual', true);
    if ($actual === null) $actual = $this->input->post('proj_actual[]', true);

    if ($student_id <= 0 || $year <= 0) {
        return $this->json_fail('Missing student or year');
    }

    // Normalize to arrays
    $labels    = is_array($labels) ? $labels : [];
    $committed = is_array($committed) ? $committed : [];
    $actual    = is_array($actual) ? $actual : [];

    // Sanitize
    $labels    = array_map(function($v){ return xss_clean((string)$v); }, $labels);
    $committed = array_map(function($v){ return (is_numeric($v) ? 0 + $v : null); }, $committed);
    $actual    = array_map(function($v){ return (is_numeric($v) ? 0 + $v : null); }, $actual);

    // DB transaction + detailed error logging
    $this->db->trans_start();
    $ok = $this->projection->save_projection_block($student_id, $year, $labels, $committed, $actual);
    $this->db->trans_complete();

    if (!$ok || $this->db->trans_status() === false) {
        $err = $this->db->error();
        log_message('error', 'Projection SAVE failed: '.($err['message'] ?? 'unknown').' :: payload='.json_encode([
            'student_id'=>$student_id,'year'=>$year,'labels'=>$labels,'committed'=>$committed,'actual'=>$actual
        ]));
        return $this->json_fail('Could not save projection block');
    }

    return $this->json_ok([
        'saved'     => true,
        'echo'      => ['labels'=>$labels,'committed'=>$committed,'actual'=>$actual],
    ]);
}


    private function json_ok($payload)
    {
        $out = [
            'status' => 1,
            'data'   => $payload,
            // refresh CSRF for subsequent AJAX posts
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ];
        return $this->output->set_output(json_encode($out));
    }

    private function json_fail($msg)
    {
        $out = [
            'status'  => 0,
            'message' => $msg,
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ];
        return $this->output->set_output(json_encode($out));
    }
}
