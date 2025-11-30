<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 5.0
 * @developed by : Eduassist 
 * @support : suppport@eduassistance.co.za 
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Classes.php
 * 
 */

class Classes extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('classes_model');
    }

    /* class form validation rules */
    protected function class_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('name', translate('name'), 'trim|required');
        $this->form_validation->set_rules('name_numeric', translate('name_numeric'), 'trim|numeric');
        $this->form_validation->set_rules('sections[]', translate('section'), 'trim|required');
    }

    public function index()
    {
        if (!get_permission('classes', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            if (get_permission('classes', 'is_add')) {
                $this->class_validation();
                if ($this->form_validation->run() !== false) {
                    $arrayClass = array(
                        'name' => $this->input->post('name'),
                        'name_numeric' => $this->input->post('name_numeric'),
                        'branch_id' => $this->application_model->get_branch_id(),
                    );
                    $this->db->insert('class', $arrayClass);
                    $class_id = $this->db->insert_id();
                    $sections = $this->input->post('sections');
                    foreach ($sections as $section) {
                        $arrayData = array(
                            'class_id' => $class_id,
                            'section_id' => $section,
                        );
                        $query = $this->db->get_where("sections_allocation", $arrayData);
                        if ($query->num_rows() == 0) {
                            $this->db->insert('sections_allocation', $arrayData);
                        }
                    }
                    set_alert('success', translate('information_has_been_saved_successfully'));
                    $url = base_url('classes');
                    $array = array('status' => 'success', 'url' => $url, 'error' => '');
                } else {
                    $error = $this->form_validation->error_array();
                    $array = array('status' => 'fail', 'url' => '', 'error' => $error);
                }
                echo json_encode($array);
                exit();
            }
        }
        $this->data['classlist'] = $this->app_lib->getTable('class');
        $this->data['query_classes'] = $this->db->get('class');
        $this->data['title'] = translate('control_classes');
        $this->data['sub_page'] = 'classes/index';
        $this->data['main_menu'] = 'classes';
        $this->load->view('layout/index', $this->data);

    }

    public function edit($id = '')
    {
        if (!get_permission('classes', 'is_edit')) {
            access_denied();
        }
        if ($_POST) {
            $this->class_validation();
            if ($this->form_validation->run() !== false) {
                $id = $this->input->post('class_id');
                $arrayClass = array(
                    'name' => $this->input->post('name'),
                    'name_numeric' => $this->input->post('name_numeric'),
                    'branch_id' => $this->application_model->get_branch_id(),
                );
                $this->db->where('id', $id);
                $this->db->update('class', $arrayClass);
                $sections = $this->input->post('sections');
                foreach ($sections as $section) {
                    $query = $this->db->get_where("sections_allocation", array('class_id' => $id, 'section_id' => $section));
                    if ($query->num_rows() == 0) {
                        $this->db->insert('sections_allocation', array('class_id' => $id, 'section_id' => $section));
                    }
                }
                $this->db->where_not_in('section_id', $sections);
                $this->db->where('class_id', $id);
                $this->db->delete('sections_allocation');
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url = base_url('classes');
                $array = array('status' => 'success', 'url' => $url, 'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['class'] = $this->app_lib->getTable('class', array('t.id' => $id), true);
        $this->data['title'] = translate('control_classes');
        $this->data['sub_page'] = 'classes/edit';
        $this->data['main_menu'] = 'classes';
        $this->load->view('layout/index', $this->data);
    }

    public function delete($id = '')
    {
        if (get_permission('classes', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('class');
            if ($this->db->affected_rows() > 0) {
                $this->db->where('class_id', $id);
                $this->db->delete('sections_allocation');
            }
        }
    }

    // class teacher allocation
    public function teacher_allocation()
    {
        if (!get_permission('assign_class_teacher', 'is_view')) {
            access_denied();
        }
        $branch_id = $this->application_model->get_branch_id();
        $this->data['branch_id'] = $branch_id;
        $this->data['query'] = $this->classes_model->getTeacherAllocation($branch_id);
        $this->data['title'] = translate('assign_class_teacher');
        $this->data['sub_page'] = 'classes/teacher_allocation';
        $this->data['main_menu'] = 'classes';
        $this->load->view('layout/index', $this->data);
    }

    public function getAllocationTeacher()
    {
        if (get_permission('assign_class_teacher', 'is_edit')) {
            $allocation_id = $this->input->post('id');
            $this->data['data'] = $this->app_lib->get_table('teacher_allocation', $allocation_id, true);
            $this->load->view('classes/tallocation_modalEdit', $this->data);
        }
    }

    public function teacher_allocation_save()
    {
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('class_id', translate('class'), 'required');
            // section is required for UI, and limit is PER CLASS+SECTION
            $this->form_validation->set_rules('section_id', translate('section'), 'required');

            // Support both single and multi select teacher fields
            $hasMulti = is_array($this->input->post('staff_ids'));
            if ($hasMulti) {
                $this->form_validation->set_rules('staff_ids[]', translate('teacher'), 'required');
            } else {
                // duplicate check compares per class+section
                $this->form_validation->set_rules('staff_id', translate('teacher'), 'required|callback_unique_teacherID');
            }

            if ($this->form_validation->run() !== false) {
                // Common scope
                $branch_id  = is_superadmin_loggedin() ? (int)$this->input->post('branch_id') : (int)$this->application_model->get_branch_id();
                $class_id   = (int)$this->input->post('class_id');
                $section_id = (int)$this->input->post('section_id');
                $session_id = (int)get_session_id();
                $allocation_id = (int)$this->input->post('allocation_id'); // may be empty on add

                if ($hasMulti) {
                    // CURRENT COUNT PER CLASS+SECTION+SESSION
                    $current = $this->db->get_where('teacher_allocation', [
                        'branch_id'  => $branch_id,
                        'class_id'   => $class_id,
                        'section_id' => $section_id,
                        'session_id' => $session_id,
                    ])->num_rows();

                    $available = max(0, 2 - $current);
                    $saved = 0;
                    $teacher_ids = (array)$this->input->post('staff_ids');

                    foreach ($teacher_ids as $tid) {
                        if ($available <= 0) break;
                        $teacher_id = (int)$tid;

                        // skip duplicates of the SAME teacher already in THIS CLASS+SECTION
                        $dup = $this->db->get_where('teacher_allocation', [
                            'branch_id'  => $branch_id,
                            'class_id'   => $class_id,
                            'section_id' => $section_id,
                            'session_id' => $session_id,
                            'teacher_id' => $teacher_id,
                        ])->num_rows();
                        if ($dup) {
                            continue;
                        }

                        // Save using existing model method (one-by-one)
                        $post = $this->input->post();
                        $post['staff_id'] = $teacher_id;
                        unset($post['staff_ids']);

                        $this->classes_model->teacherAllocationSave($post);
                        $saved++;
                        $available--;
                    }

                    if ($saved > 0) {
                        $url = base_url('classes/teacher_allocation');
                        $array = array('status' => 'success', 'url' => $url);
                    } else {
                        $array = array(
                            'status' => 'fail',
                            'error'  => array('staff_ids[]' => 'No slots available for this class & section (max 2) or selected teachers are already assigned.')
                        );
                    }
                } else {
                    // Single teacher flow (backward compatible), enforce max two PER CLASS+SECTION when adding new
                    $current = $this->db->get_where('teacher_allocation', [
                        'branch_id'  => $branch_id,
                        'class_id'   => $class_id,
                        'section_id' => $section_id,
                        'session_id' => $session_id,
                    ])->num_rows();

                    if (empty($allocation_id) && $current >= 2) {
                        $array = array(
                            'status' => 'fail',
                            'error'  => array('staff_id' => 'ACE allows a maximum of 2 teachers per class & section (Supervisor + Monitor).')
                        );
                    } else {
                        $post = $this->input->post();
                        $this->classes_model->teacherAllocationSave($post);
                        $url = base_url('classes/teacher_allocation');
                        $array = array('status' => 'success', 'url' => $url);
                    }
                }
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function teacher_allocation_delete($id = '')
    {
        if (get_permission('assign_class_teacher', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('teacher_allocation');
        }
    }

    // SAME teacher check scoped to CLASS+SECTION (prevents duplicates in the same section; allows another section)
    public function unique_teacherID($teacher_id)
    {
        if (!empty($teacher_id)) {
            $classID    = (int)$this->input->post('class_id');
            $sectionID  = (int)$this->input->post('section_id');
            $allocationID = (int)$this->input->post('allocation_id');
            $branch_id  = is_superadmin_loggedin() ? (int)$this->input->post('branch_id') : (int)$this->application_model->get_branch_id();
            $session_id = (int)get_session_id();

            if (!empty($allocationID)) {
                $this->db->where_not_in('id', $allocationID);
            }
            $this->db->where('teacher_id', (int)$teacher_id);
            $this->db->where('branch_id', $branch_id);
            $this->db->where('class_id', $classID);
            $this->db->where('section_id', $sectionID);
            $this->db->where('session_id', $session_id);
            $query = $this->db->get('teacher_allocation');
            if ($query->num_rows() > 0) {
                $this->form_validation->set_message("unique_teacherID", translate('this_teacher_is_already_allocated_for_this_class_and_section'));
                return false;
            } else {
                return true;
            }
        }
    }

    // legacy (kept, not used in validation now)
    public function unique_sectionID($sectionID)
    {
        if (!empty($sectionID)) {
            $classID = $this->input->post('class_id');
            $allocationID = $this->input->post('allocation_id');
            if (!empty($allocationID)) {
                $this->db->where_not_in('id', $allocationID);
            }
            $this->db->where('class_id', $classID);
            $this->db->where('section_id', $sectionID);
            $query = $this->db->get('teacher_allocation');
            if ($query->num_rows() > 0) {
                $this->form_validation->set_message("unique_sectionID", translate('this_class_teacher_already_assigned'));
                return false;
            } else {
                return true;
            }
        }
    }
}
