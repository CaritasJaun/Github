<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Term extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('term_model');
    }

    public function index()
    {
        if ($_POST) {
            $this->term_model->save_term($this->input->post());
            set_alert('success', 'Term saved successfully');
            redirect(base_url('term'));
        }

        $this->data['terms'] = $this->term_model->get_all_terms();
        $this->data['title'] = translate('manage_terms');
        $this->data['sub_page'] = 'term/index';
        $this->data['main_menu'] = 'academic';
        $this->load->view('layout/index', $this->data);
    }

    public function delete($id)
    {
        $this->db->where('id', $id)->delete('terms');
        set_alert('success', 'Term deleted');
        redirect(base_url('term'));
    }

    public function edit($id)
    {
        $this->data['term'] = $this->term_model->get_term($id);
        $this->data['terms'] = $this->term_model->get_all_terms();
        $this->data['title'] = translate('edit_term');
        $this->data['sub_page'] = 'term/index';
        $this->data['main_menu'] = 'academic';
        $this->load->view('layout/index', $this->data);
    }
}
