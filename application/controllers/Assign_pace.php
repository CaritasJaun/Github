<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Assign_pace extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Assign_pace_model');
        $this->load->model('Subjectpace_model');
        $this->load->model('Subject_model');
    }

    public function index()
    {
        $this->data['title'] = "Assign PACEs";
        $this->data['sub_page'] = 'assignpace/index';
        $this->data['main_menu'] = 'academic';

        $user_role = $this->session->userdata('user_role');
        $user_id   = $this->session->userdata('user_id');

        if ($user_role === 'teacher') {
            $class_id = $this->db
                ->select('class_id')
                ->from('teacher_allocation')
                ->where('teacher_id', $user_id)
                ->get()
                ->row('class_id');

            $this->data['paces'] = $this->Assign_pace_model->get_filtered_paces_by_grade($class_id);
        } else {
            $this->data['paces'] = $this->Assign_pace_model->get_all_paces();
        }

        $this->load->view('layout/index', $this->data);
    }
}
