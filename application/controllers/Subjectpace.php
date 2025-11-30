<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Subjectpace extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Subjectpace_model');
        $this->load->model('Subject_model'); 
    }

public function library_list()
{
    $data = $this->data;

    $this->data['global_config'] = $this->db->get_where('global_settings', ['id' => 1])->row_array();
    $this->data['title']         = 'PACE Library';
    $this->data['sub_page']      = 'subjectpace/library_list';
    $this->data['main_menu']     = 'academic';

    $this->data['subjects']      = $this->Subjectpace_model->subject_dropdown();

    // Get current user's role and ID from session
    $user_role = $this->session->userdata('user_role');
    $user_id   = $this->session->userdata('user_id');

    if ($user_role == 'teacher') {
        // Get the class ID assigned to this teacher
        $class_id = $this->db
            ->select('class.id')
            ->from('teacher_allocation')
            ->join('class', 'class.id = teacher_allocation.class_id')
            ->where('teacher_allocation.teacher_id', $user_id)
            ->get()
            ->row('id');

        // Get numeric grade from that class
        $grade = $this->db
            ->select('name_numeric')
            ->from('class')
            ->where('id', $class_id)
            ->get()
            ->row('name_numeric');

        $this->data['rows'] = $this->Subjectpace_model->get_all([$grade]);

    } else {
        // Admins see everything
        $this->data['rows'] = $this->Subjectpace_model->get_all();
    }

    $this->load->view('layout/index', $this->data);
}


    public function save()
    {
        $id = $this->input->post('id');
        $grade = $this->input->post('grade');
        $subject_id = $this->input->post('subject_id');
        $pace_number = $this->input->post('pace_number');

        $success = $this->Subjectpace_model->save($id, $grade, $subject_id, $pace_number);

        if ($success) {
            $this->session->set_flashdata('success', 'PACE saved successfully.');
        } else {
            $this->session->set_flashdata('error', 'PACE not saved (possible duplicate).');
        }

        redirect(base_url('subjectpace/library_list'));
    }

    public function delete($id)
    {
        $this->Subjectpace_model->delete($id);
        $this->session->set_flashdata('success', 'PACE deleted.');
        redirect(base_url('subjectpace/library_list'));
    }

    public function bulk()
    {
        $grade = $this->input->post('grade_bulk');
        $subject_id = $this->input->post('subject_id_bulk');
        $start_number = (int)$this->input->post('start_number');

        $added = $this->Subjectpace_model->bulk_generate($grade, $subject_id, $start_number);

        if ($added) {
            $this->session->set_flashdata('success', '12 PACEs generated successfully.');
        } else {
            $this->session->set_flashdata('error', 'No PACEs added (duplicates may exist).');
        }

        redirect(base_url('subjectpace/library_list'));
    }
}
