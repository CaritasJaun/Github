<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class StudentPace extends Admin_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('studentpace_model');
    }

    public function assign() {
        if ($this->input->post()) {
            $student_id = $this->input->post('student_id');
            $term = $this->input->post('term');
            $pace_ids = $this->input->post('pace_ids'); // array of subject_ids

            foreach ($pace_ids as $subject_id) {
                $data = array(
                    'student_id' => $student_id,
                    'subject_id' => $subject_id,
                    'term' => $term,
                    'status' => 'assigned',
                    'session_id' => get_session_id(), // your function here
                    'branch_id' => get_loggedin_branch_id(),
                    'assigned_date' => date('Y-m-d'),
                );
                $this->studentpace_model->assign_pace($data);
            }

            set_alert('success', 'PACEs assigned successfully');
            redirect('studentpace/assign');
        }

        $this->data['students'] = $this->student_model->getAll(); // existing model
        $this->data['paces'] = $this->subject_model->getAll();   // existing subjects
        $this->load->view('studentpace/assign', $this->data);
    }
}
