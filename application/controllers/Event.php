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
class Event extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('event_model');
    }

    public function index()
    {
        // check access permission
        if (!get_permission('event', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {

            // --- permissions: allow Teachers to add too --------------------- //
            $role_id = (int)$this->session->userdata('loggedin_role_id');
            if (!get_permission('event', 'is_add') && $role_id !== 3) {
               ajax_access_denied();
            }

            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('title', translate('title'), 'trim|required');

            if (!isset($_POST['holiday'])) {
                $this->form_validation->set_rules('type_id', translate('type'), 'trim|required');
                $this->form_validation->set_rules('audition', translate('audition'), 'trim|required');
                $audition = (int)$this->input->post('audition');
            } else {
                $audition = 1;
            }

            $this->form_validation->set_rules('daterange', translate('date'), 'trim|required');

            if ($audition == 2) {
                $this->form_validation->set_rules('selected_audience[]', translate('class'), 'trim|required');
            } elseif ($audition == 3) {
                $this->form_validation->set_rules('selected_audience[]', translate('section'), 'trim|required');
            }
            $this->form_validation->set_rules('user_photo', 'profile_picture', 'callback_photoHandleUpload[user_photo]');

            // ── Timed / All-day support
            $all_day = (int)$this->input->post('all_day') === 1 ? 1 : 0;
            if ($all_day !== 1) {
                $this->form_validation->set_rules('start_time', 'start time', 'trim|required');
                $this->form_validation->set_rules('end_time', 'end time', 'trim|required');
            }

            if ($this->form_validation->run() !== false) {
                // Build selected list (nullable if everybody)
                if ($audition != 1) {
                    $selectedList = array_map('intval', (array)$this->input->post('selected_audience'));
                } else {
                    $selectedList = null;
                }

                // TEACHER: if targeting Selected Class, sanitize to assigned classes only
                if ((int)$this->session->userdata('loggedin_role_id') === 3 && $audition === 2) {
                    $teacherClassIDs = $this->get_teacher_classes_ids();
                    if (empty($teacherClassIDs)) {
                        echo json_encode([
                            'status' => 'fail',
                            'url'    => '',
                            'error'  => ['selected_audience' => translate('no_class_assigned')]
                        ]);
                        exit();
                    }
                    $allowed = array_values(array_intersect($selectedList ?? [], $teacherClassIDs));
                    if (empty($allowed)) {
                        echo json_encode([
                            'status' => 'fail',
                            'url'    => '',
                            'error'  => ['selected_audience' => translate('please_select_at_least_one_class')]
                        ]);
                        exit();
                    }
                    $selectedList = $allowed;
                }

                // type / holiday
                $holiday = $this->input->post('holiday');
                $type    = empty($holiday) ? $this->input->post('type_id') : 'holiday';

                // dates
                $daterange  = explode(' - ', $this->input->post('daterange'));
                $start_date = date("Y-m-d", strtotime($daterange[0]));
                $end_date   = date("Y-m-d", strtotime($daterange[1]));

                // times (nullable if all-day)
                $start_time = ($all_day ? null : $this->input->post('start_time'));
                $end_time   = ($all_day ? null : $this->input->post('end_time'));

                // Same-day time sanity
                if ($all_day !== 1 && $start_date === $end_date) {
                    if (strtotime($end_time) <= strtotime($start_time)) {
                        $array = array('status' => 'fail', 'url' => '', 'error' => ['end_time' => 'End time must be after start time']);
                        echo json_encode($array);
                        exit();
                    }
                }

                $event_image = 'defualt.png';
                if (isset($_FILES["user_photo"]) && $_FILES['user_photo']['name'] != '' && (!empty($_FILES['user_photo']['name']))) {
                    $event_image = $this->event_model->fileupload("user_photo", "./uploads/frontend/events/",'', false);
                }
                $arrayEvent = array(
                    'branch_id'     => $branchID,
                    'type'          => $type,
                    'audition'      => $audition,
                    'image'         => $event_image,
                    'selected_list' => json_encode($selectedList),
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                    'all_day'       => $all_day,
                    'start_time'    => $start_time,
                    'end_time'      => $end_time,
                );
                $this->event_model->save($arrayEvent);
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url = base_url('event');
                $array = array('status' => 'success', 'url' => $url, 'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }

        // pass teacher class list for the create form (multi-select)
        if ((int)$this->session->userdata('loggedin_role_id') === 3) {
            $this->data['teacher_classes'] = $this->get_teacher_classes_kv();
        }

        // Provide branch-scoped event types to the form (works for all roles)
        $this->data['event_type_options'] = $this->event_type_options($branchID);

        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('events');
        $this->data['sub_page'] = 'event/index';
        $this->data['main_menu'] = 'event';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/summernote/summernote.css',
                'vendor/daterangepicker/daterangepicker.css',
                'vendor/bootstrap-fileupload/bootstrap-fileupload.min.css',
            ),
            'js' => array(
                'vendor/summernote/summernote.js',
                'vendor/moment/moment.js',
                'vendor/daterangepicker/daterangepicker.js',
                'vendor/bootstrap-fileupload/bootstrap-fileupload.min.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function edit($id='')
    {
        // check access permission
        if (!get_permission('event', 'is_edit')) {
            access_denied();
        }
        $this->data['event'] = $this->app_lib->getTable('event', array('t.id' => $id), true);
        if (empty($this->data['event'])) {
            redirect('dashboard');
        }

        $branchID = $this->application_model->get_branch_id();
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('title', translate('title'), 'trim|required');
            if (!isset($_POST['holiday'])) {
                $this->form_validation->set_rules('type_id', translate('type'), 'trim|required');
                $this->form_validation->set_rules('audition', translate('audition'), 'trim|required');
                $audition = (int)$this->input->post('audition');
            } else {
                $audition = 1;
            }

            $this->form_validation->set_rules('daterange', translate('date'), 'trim|required');

            if ($audition == 2) {
                $this->form_validation->set_rules('selected_audience[]', translate('class'), 'trim|required');
            } elseif ($audition == 3) {
                $this->form_validation->set_rules('selected_audience[]', translate('section'), 'trim|required');
            }
            $this->form_validation->set_rules('user_photo', 'profile_picture', 'callback_photoHandleUpload[user_photo]');

            // ── Timed / All-day support
            $all_day = (int)$this->input->post('all_day') === 1 ? 1 : 0;
            if ($all_day !== 1) {
                $this->form_validation->set_rules('start_time', 'start time', 'trim|required');
                $this->form_validation->set_rules('end_time', 'end time', 'trim|required');
            }

            if ($this->form_validation->run() !== false) {
                // Build selected list (nullable if everybody)
                if ($audition != 1) {
                    $selectedList = array_map('intval', (array)$this->input->post('selected_audience'));
                } else {
                    $selectedList = null;
                }

                // TEACHER: if targeting Selected Class, sanitize to assigned classes only
                if ((int)$this->session->userdata('loggedin_role_id') === 3 && $audition === 2) {
                    $teacherClassIDs = $this->get_teacher_classes_ids();
                    if (empty($teacherClassIDs)) {
                        $array = array('status' => 'fail', 'url' => '', 'error' => ['selected_audience' => translate('no_class_assigned')]);
                        echo json_encode($array); exit();
                    }
                    $allowed = array_values(array_intersect($selectedList ?? [], $teacherClassIDs));
                    if (empty($allowed)) {
                        $array = array('status' => 'fail', 'url' => '', 'error' => ['selected_audience' => translate('please_select_at_least_one_class')]);
                        echo json_encode($array); exit();
                    }
                    $selectedList = $allowed;
                }

                $holiday = $this->input->post('holiday');
                $type    = empty($holiday) ? $this->input->post('type_id') : 'holiday';

                $daterange  = explode(' - ', $this->input->post('daterange'));
                $start_date = date("Y-m-d", strtotime($daterange[0]));
                $end_date   = date("Y-m-d", strtotime($daterange[1]));

                $start_time = ($all_day ? null : $this->input->post('start_time'));
                $end_time   = ($all_day ? null : $this->input->post('end_time'));

                if ($all_day !== 1 && $start_date === $end_date) {
                    if (strtotime($end_time) <= strtotime($start_time)) {
                        $array = array('status' => 'fail', 'url' => '', 'error' => ['end_time' => 'End time must be after start time']);
                        echo json_encode($array);
                        exit();
                    }
                }

                $event_image = $this->input->post('old_event_image');
                if (isset($_FILES["user_photo"]) && $_FILES['user_photo']['name'] != '' && (!empty($_FILES['user_photo']['name']))) {
                    $eventimage  = ($event_image == 'defualt.png' ? '' : $event_image);
                    $event_image = $this->event_model->fileupload("user_photo", "./uploads/frontend/events/", $eventimage, false);
                }

                $arrayEvent = array(
                    'id'            => $this->input->post('id'),
                    'branch_id'     => $branchID,
                    'type'          => $type,
                    'audition'      => $audition,
                    'image'         => $event_image,
                    'selected_list' => json_encode($selectedList),
                    'start_date'    => $start_date,
                    'end_date'      => $end_date,
                    'all_day'       => $all_day,
                    'start_time'    => $start_time,
                    'end_time'      => $end_time,
                );
                $this->event_model->save($arrayEvent);
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url = base_url('event');
                $array = array('status' => 'success', 'url' => $url, 'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }

        // Provide types to the edit form as well
        $this->data['event_type_options'] = $this->event_type_options($branchID);

        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('events');
        $this->data['sub_page'] = 'event/edit';
        $this->data['main_menu'] = 'event';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/summernote/summernote.css',
                'vendor/daterangepicker/daterangepicker.css',
                'vendor/bootstrap-fileupload/bootstrap-fileupload.min.css',
            ),
            'js' => array(
                'vendor/summernote/summernote.js',
                'vendor/moment/moment.js',
                'vendor/daterangepicker/daterangepicker.js',
                'vendor/bootstrap-fileupload/bootstrap-fileupload.min.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function delete($id = '')
    {
        // check access permission
        if (get_permission('event', 'is_delete')) {
            $event_db = $this->db->where('id', $id)->get('event')->row_array();
            $file_name = $event_db['image'];
            if ($event_db['created_by'] == get_loggedin_user_id() || is_superadmin_loggedin()) {
                $this->db->where('id', $id);
                $this->db->delete('event');
                if ($file_name !== 'defualt.png') {
                    $file_name = 'uploads/frontend/events/' . $file_name;
                    if (file_exists($file_name)) {
                        unlink($file_name);
                    }
                }
            } else {
                set_alert('error', 'You do not have permission to delete');
            }
        } else {
            set_alert('error', translate('access_denied'));
        }
    }

    /* types form validation rules */
    protected function types_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('type_name', translate('name'), 'trim|required|callback_unique_type');
    }

    // event type create/list page
    public function types()
    {
        if (isset($_POST['save'])) {
            if (!get_permission('event_type', 'is_add')) {
                access_denied();
            }
            $this->types_validation();
            if ($this->form_validation->run() !== false) {
                $data['name']      = $this->input->post('type_name');
                $data['icon']      = $this->input->post('event_icon');
                $data['color']     = $this->input->post('event_color') ?: null;
                $data['branch_id'] = $this->application_model->get_branch_id();
                $this->db->insert('event_types', $data);
                set_alert('success', translate('information_has_been_saved_successfully'));
                redirect(current_url());
            }
        }
        $this->data['typelist']  = $this->app_lib->getTable('event_types');
        $this->data['sub_page']  = 'event/types';
        $this->data['main_menu'] = 'event';
        $this->data['title']     = translate('event_type');
        $this->load->view('layout/index', $this->data);
    }

    public function types_edit()
    {
        if ($_POST) {
            if (!get_permission('event_type', 'is_edit')) {
                ajax_access_denied();
            }
            $this->types_validation();
            if ($this->form_validation->run() !== false) {
                $data['name']      = $this->input->post('type_name');
                $data['icon']      = $this->input->post('event_icon');
                $data['color']     = $this->input->post('event_color') ?: null;
                $data['branch_id'] = $this->application_model->get_branch_id();
                $this->db->where('id', $this->input->post('type_id'));
                $this->db->update('event_types', $data);
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url   = base_url('event/types');
                $array = array('status' => 'success', 'url' => $url, 'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function type_delete($id)
    {
        if (!get_permission('event_type', 'is_delete')) {
            access_denied();
        }
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('id', $id);
        $this->db->delete('event_types');
    }

    /* unique valid type name verification is done here */
    public function unique_type($name)
    {
        $branchID = $this->application_model->get_branch_id();
        $type_id  = $this->input->post('type_id');
        if (!empty($type_id)) {
            $this->db->where_not_in('id', $type_id);
        }
        $this->db->where(array('name' => $name, 'branch_id' => $branchID));
        $uniform_row = $this->db->get('event_types')->num_rows();
        if ($uniform_row == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_type", translate('already_taken'));
            return false;
        }
    }

    // publish on show website
    public function show_website()
    {
        $id     = $this->input->post('id');
        $status = $this->input->post('status');
        $arrayData['show_web'] = ($status == 'true') ? 1 : 0;
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('id', $id)->update('event', $arrayData);
        $return = array('msg' => translate('information_has_been_updated_successfully'), 'status' => true);
        echo json_encode($return);
    }

    // publish status
    public function status()
    {
        $id     = $this->input->post('id');
        $status = $this->input->post('status');
        $arrayData['status'] = ($status == 'true') ? 1 : 0;
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('id', $id)->update('event', $arrayData);
        $return = array('msg' => translate('information_has_been_updated_successfully'), 'status' => true);
        echo json_encode($return);
    }

public function getDetails()
{
    if (!is_loggedin()) {
        show_404();
        return;
    }

    $id = (int)$this->input->post('id');

    // base query
    $this->db->from('event e');
    $this->db->select('e.*, et.name AS type_name');
    if ($this->db->table_exists('event_types')) {
        $this->db->join('event_types et', 'et.id = e.type', 'left');
    }

    // scope to branch for non-superadmin
    if (!is_superadmin_loggedin() && $this->db->field_exists('branch_id','event')) {
        $this->db->where('e.branch_id', get_loggedin_branch_id());
    }

    $this->db->where('e.id', $id);
    $row = $this->db->get()->row_array();

    if (!$row) {
        echo '<tbody><tr><td>'.translate('no_information_available').'</td></tr></tbody>';
        return;
    }

    // choose date columns safely
    $start = isset($row['start_date']) ? $row['start_date'] : (isset($row['start']) ? $row['start'] : '');
    $end   = isset($row['end_date'])   ? $row['end_date']   : (isset($row['end'])   ? $row['end']   : '');
    
    // ---- TIME ROW (robust extraction) ----
$allDayFlag = ($this->db->field_exists('all_day','event') && isset($row['all_day'])) ? (int)$row['all_day'] : null;

// Tiny helper to pull a readable time from various formats
$extractTime = function($val) {
    if ($val === null || $val === '') return '';
    // Handles: "09:00", "9:00 AM", "2025-10-17 09:00:00", "2025-10-17T09:00:00"
    $ts = strtotime($val);
    if ($ts !== false) return date('g:i A', $ts);
    if (preg_match('/\b(\d{1,2}:\d{2})(?::\d{2})?\s*(AM|PM)?\b/i', $val, $m)) {
        return date('g:i A', strtotime(trim($m[0])));
    }
    return '';
};

// Try dedicated time columns first (cover schema variants)
$startTime = '';
$endTime   = '';
foreach (['start_time','time_from','from_time','stime','starttime'] as $c) {
    if (array_key_exists($c, $row) && $row[$c] !== null && $row[$c] !== '') { $startTime = $extractTime($row[$c]); break; }
}
foreach (['end_time','time_to','to_time','etime','endtime'] as $c) {
    if (array_key_exists($c, $row) && $row[$c] !== null && $row[$c] !== '') { $endTime = $extractTime($row[$c]); break; }
}

// If still empty, try to extract from the date columns (when they include time)
$startTime = $startTime ?: $extractTime(isset($start) ? $start : '');
$endTime   = $endTime   ?: $extractTime(isset($end)   ? $end   : '');

// Show the row if there is any time, unless the record is explicitly all-day (==1)
if (($startTime || $endTime) && $allDayFlag !== 1) {
    $html .= '<tr><th>'.translate('time').'</th><td>'
          . ($startTime ?: '-') . ' - ' . ($endTime ?: '-') . '</td></tr>';
}

    // build rows for #ev_table
    $html  = '<tbody>';
    $html .= '<tr><th>'.translate('title').'</th><td>'.html_escape($row['title']).'</td></tr>';
    $html .= '<tr><th>'.translate('type').'</th><td>'.($row['type']==='holiday' ? translate('holiday') : html_escape($row['type_name'])).'</td></tr>';
    $html .= '<tr><th>'.translate('date_of_start').'</th><td>'._d($start).'</td></tr>';
    $html .= '<tr><th>'.translate('date_of_end').'</th><td>'._d($end).'</td></tr>';

    if (!empty($row['audition'])) {
        $auditions = ["1"=>'everybody',"2"=>'class',"3"=>'section'];
        $aud = isset($auditions[$row['audition']]) ? translate($auditions[$row['audition']]) : '-';
        $html .= '<tr><th>'.translate('audience').'</th><td>'.$aud.'</td></tr>';
    }

    if (!empty($row['remarks'])) {
        $html .= '<tr><th>'.translate('description').'</th><td>'.$row['remarks'].'</td></tr>';
    }

    if (!empty($row['image'])) {
        $src = base_url('uploads/frontend/events/'.$row['image']);
        $html .= '<tr><th>'.translate('image').'</th><td><img src="'.$src.'" style="max-height:120px"></td></tr>';
    }

    if (!empty($row['created_by'])) {
        $html .= '<tr><th>'.translate('created_by').'</th><td>'.get_type_name_by_id('staff', $row['created_by']).'</td></tr>';
    }

    $html .= '</tbody>';
    echo $html;
}


    /* generate section with class group */
    public function getSectionByBranch()
    {
        $html = "";
        $branchID = $this->application_model->get_branch_id();
        if (!empty($branchID)) {
            $result = $this->db->get_where('class', array('branch_id' => $branchID))->result_array();
            if (count($result)) {
                foreach ($result as $class) {
                    $html .= '<optgroup label="' . $class['name'] . '">';
                    $allocations = $this->db->get_where('sections_allocation', array('class_id' => $class['id']))->result_array();
                    if (count($allocations)) {
                        foreach ($allocations as $allocation) {
                            $section = $this->db->get_where('section', array('id' => $allocation['section_id']))->row_array();
                            $html .= '<option value="' . $class['id']. "-" .$allocation['section_id'] . '">' . $section['name'] . '</option>';
                        }
                    } else {
                        $html .= '<option value="">' . translate('no_selection_available') . '</option>';
                    }
                    $html .= '</optgroup>';
                }
            }
        }
        echo $html;
    }

  public function get_events_list($branchID = '')
{
    header('Content-Type: application/json');

    if (!is_loggedin()) { echo json_encode([]); return; }

    // Branch resolve
    if (is_superadmin_loggedin()) {
        $branchID = $this->input->get('branch_id');
        if (empty($branchID)) {
            $branch = $this->db->select('id')->order_by('id','ASC')->limit(1)->get('branch')->row();
            $branchID = $branch ? $branch->id : null;
        }
    } else {
        $branchID = get_loggedin_branch_id();
    }

    $role_id = (int)$this->session->userdata('loggedin_role_id');
    $user_id = (int)$this->session->userdata('loggedin_id');

    // 🔑 IMPORTANT: fetch teacher classes BEFORE building the events query
    $teacherClassIDs = [];
    if ($role_id === 3) {
        $teacherClassIDs = $this->get_teacher_classes_ids(); // this function resets QB internally
    }

    // Build events query from a clean builder
    $this->db->reset_query();
    $this->db->from('event e');
    $this->db->where('e.branch_id', $branchID);
    $this->db->where('e.status', 1);

    if ($role_id === 3) {
        $this->db->group_start()
            ->where('e.audition', 1)                 // Everybody
            ->or_where('e.created_by', $user_id)     // Own events
            ->or_group_start()
                ->where('e.audition', 2);            // Class events
                if (!empty($teacherClassIDs)) {
                    $this->db->group_start();
                    foreach ($teacherClassIDs as $cid) {
                        $this->db->or_like('e.selected_list', '"' . (int)$cid . '"');
                    }
                    $this->db->group_end();
                } else {
                    $this->db->where('e.id', 0);
                }
            $this->db->group_end()
        ->group_end();
    }

    $query = $this->db->get();
    if (!$query) { echo json_encode([]); return; }

    $events = $query->result();
    $eventdata = [];

    foreach ($events as $row) {
        $e = [
            'id'     => $row->id,
            'title'  => $row->title,
            'allDay' => (isset($row->all_day) ? ((int)$row->all_day === 1) : true),
        ];

        if (!isset($row->all_day) || (int)$row->all_day === 1) {
            $e['start'] = $row->start_date;
            $e['end']   = date('Y-m-d', strtotime($row->end_date . ' +1 day')); // exclusive end
        } else {
            $st = $row->start_time ?: '00:00:00';
            $et = $row->end_time   ?: $st;
            $e['start'] = $row->start_date . 'T' . $st;
            $e['end']   = $row->end_date   . 'T' . $et;
        }

        if ($row->type == 'holiday') {
            $e['className'] = 'fc-event-danger';
            $e['icon']      = 'umbrella-beach';
            $e['color']     = '#ef4444';
            $e['textColor'] = '#ffffff';
        } else {
            $icon      = get_type_name_by_id('event_types', $row->type, 'icon');
            $typeColor = get_type_name_by_id('event_types', $row->type, 'color');
            $e['icon'] = $icon;

            if (!empty($typeColor)) {
                $e['color']     = $typeColor;
                $e['textColor'] = $this->autoTextColor($typeColor);
            } elseif ((int)$row->audition === 2) {
                $e['color']     = '#16a34a';
                $e['textColor'] = '#ffffff';
            }
        }

        $eventdata[] = $e;
    }

    echo json_encode($eventdata);
    exit;
}

    // Calendar page
    public function calendar()
    {
        if (!get_permission('event', 'is_view')) {
            access_denied();
        }

        $this->data['title']     = translate('events');
        $this->data['main_menu'] = 'event';
        $this->data['sub_page']  = 'event/calendar';
        $this->data['my_class_id']   = (int)$this->input->get('class_id');
        $this->data['my_section_id'] = (int)$this->input->get('section_id');

        $this->load->view('layout/index', $this->data);
    }

    // JSON feed for FullCalendar
   public function feed()
{
    if (!is_loggedin()) show_404();

    $branchID   = $this->application_model->get_branch_id();
    $user_id    = (int)$this->session->userdata('loggedin_id');
    $role_id    = (int)$this->session->userdata('loggedin_role_id');

    $start = $this->input->get('start'); // YYYY-MM-DD
    $end   = $this->input->get('end');   // YYYY-MM-DD

    $teacherClassIDs = [];
    if ($role_id === 3) {
        $teacherClassIDs = $this->get_teacher_classes_ids();
    }

    $this->db->reset_query();
    $this->db->from('event e');
    $this->db->where('e.branch_id', $branchID);
    $this->db->where('e.status', 1);

    // Date overlap with FullCalendar window
    if ($start) $this->db->where('e.end_date >=', $start);
    if ($end)   $this->db->where('e.start_date <=', $end);

    if ($role_id === 3) {
        $this->db->group_start()
            ->where('e.audition', 1)                    // everybody
            ->or_where('e.created_by', $user_id)        // their own
            ->or_group_start()                          // class-scoped
                ->where('e.audition', 2);
                if (!empty($teacherClassIDs)) {
                    $this->db->group_start();
                    foreach ($teacherClassIDs as $cid) {
                        $this->db->or_like('e.selected_list', '"' . (int)$cid . '"');
                    }
                    $this->db->group_end();
                } else {
                    $this->db->where('e.id', 0); // no classes -> no results
                }
            $this->db->group_end()
        ->group_end();
    }

    $rows = $this->db->get()->result();
    $out  = [];

    foreach ($rows as $row) {
        $e = [
            'id'     => $row->id,
            'title'  => $row->title,
            'allDay' => (isset($row->all_day) ? ((int)$row->all_day === 1) : true),
        ];

        if (!isset($row->all_day) || (int)$row->all_day === 1) {
            $e['start'] = $row->start_date;
            $e['end']   = date('Y-m-d', strtotime($row->end_date . ' +1 day'));
        } else {
            $st = $row->start_time ?: '00:00:00';
            $et = $row->end_time   ?: $st;
            $e['start'] = $row->start_date . 'T' . $st;
            $e['end']   = $row->end_date   . 'T' . $et;
        }

        if ($row->type == 'holiday') {
            $e['className'] = 'fc-event-danger';
            $e['icon']      = 'umbrella-beach';
            $e['color']     = '#ef4444';
            $e['textColor'] = '#ffffff';
        } else {
            $icon      = get_type_name_by_id('event_types', $row->type, 'icon');
            $typeColor = get_type_name_by_id('event_types', $row->type, 'color');
            $e['icon'] = $icon;
            if (!empty($typeColor)) {
                $e['color']     = $typeColor;
                $e['textColor'] = $this->autoTextColor($typeColor);
            } elseif ((int)$row->audition === 2) {
                $e['color']     = '#16a34a';
                $e['textColor'] = '#ffffff';
            }
        }

        $out[] = $e;
    }

    $this->output->set_content_type('application/json')->set_output(json_encode($out));
}

    // Lightweight AJAX save (uses your existing model->save())
    // URL: POST /event/save_quick
    public function save_quick()
    {
        if (!$this->input->is_ajax_request()) show_404();

        // permissions: allow Teachers to add as well
        $role_id = (int)$this->session->userdata('loggedin_role_id');
        if (!get_permission('event', 'is_add') && $role_id !== 3) {
            ajax_access_denied();
        }

        $branchID = $this->application_model->get_branch_id();

        // Respect teacher's chosen audience (teachers can post to Everybody now)
        $audition = (int)$this->input->post('audition');
        $selected = null;

        if ($role_id === 3) {
            if ($audition === 2) {
                $teacherClassIDs = $this->get_teacher_classes_ids();
                if (empty($teacherClassIDs)) {
                    $res = [
                        'success' => false,
                        'error'   => translate('no_class_assigned'),
                        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
                    ];
                    $this->output->set_content_type('application/json')->set_output(json_encode($res));
                    return;
                }
                $posted  = $this->input->post('selected_audience');
                if ($posted === null) $posted = $this->input->post('class_ids');
                $posted  = array_map('intval', (array)$posted);
                $allowed = array_values(array_intersect($posted, $teacherClassIDs));
                if (empty($allowed)) {
                    $res = [
                        'success' => false,
                        'error'   => translate('please_select_at_least_one_class'),
                        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
                    ];
                    $this->output->set_content_type('application/json')->set_output(json_encode($res));
                    return;
                }
                $selected = json_encode($allowed);
            } elseif ($audition !== 1 && $audition !== 3) {
                // fallback
                $audition = 1;
            }
        } else {
            // non-teachers keep existing behavior
            $highlight_class = ((int)$this->input->post('highlight_class') === 1);
            $class_id        = (int)$this->input->post('class_id');
            if (!$audition) { $audition = ($highlight_class ? 2 : 1); }
            $selected        = ($audition === 2 && $class_id > 0) ? json_encode([$class_id]) : null;
        }

        $start_date = $this->input->post('start_date');
        $end_date   = $this->input->post('end_date') ?: $start_date;
        $all_day    = (int)$this->input->post('all_day') === 1 ? 1 : 0;

        $payload = [
            'id'            => $this->input->post('id') ?: null,
            'branch_id'     => $branchID,
            'type'          => $this->input->post('type') ?: 'general',
            'audition'      => $audition,
            'image'         => 'defualt.png',
            'selected_list' => $selected,
            'start_date'    => date('Y-m-d', strtotime($start_date)),
            'end_date'      => date('Y-m-d', strtotime($end_date)),
            'all_day'       => $all_day,
            'start_time'    => ($all_day ? null : ($this->input->post('start_time') ?: null)),
            'end_time'      => ($all_day ? null : ($this->input->post('end_time') ?: $this->input->post('start_time'))),
        ];

        try {
            $this->event_model->save($payload);
            $res = [
                'success' => true,
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
            ];
        } catch (Throwable $e) {
            $res = [
                'success' => false,
                'error'   => $e->getMessage(),
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
            ];
        }

        $this->output->set_content_type('application/json')->set_output(json_encode($res));
    }

    /** Resolve the logged-in teacher's assigned class (and optional section). */
    private function get_teacher_assignment(): ?array
    {
        $staff_id = (int) get_loggedin_user_id();
        $this->db->reset_query();

        if ($this->db->table_exists('teacher_allocation')) {
            $row = $this->db->select('class_id, section_id')
                            ->from('teacher_allocation')
                            ->where('teacher_id', $staff_id)
                            ->order_by('id', 'ASC')
                            ->limit(1)
                            ->get()
                            ->row_array();
            $this->db->reset_query();
            if ($row && !empty($row['class_id'])) {
                return [
                    'class_id'   => (int)$row['class_id'],
                    'section_id' => isset($row['section_id']) ? (int)$row['section_id'] : null,
                ];
            }
        }

        if ($this->db->table_exists('class_teacher')) {
            $row = $this->db->select('class_id, section_id')
                            ->from('class_teacher')
                            ->where('staff_id', $staff_id)
                            ->order_by('id', 'ASC')
                            ->limit(1)
                            ->get()
                            ->row_array();
            $this->db->reset_query();
            if ($row && !empty($row['class_id'])) {
                return [
                    'class_id'   => (int)$row['class_id'],
                    'section_id' => isset($row['section_id']) ? (int)$row['section_id'] : null,
                ];
            }
        }

        return null;
    }

    /** list of class IDs this teacher is assigned to (unique). */
    private function get_teacher_classes_ids(): array
    {
        $staff_id = (int) get_loggedin_user_id();
        $ids = [];

        $this->db->reset_query();
        if ($this->db->table_exists('teacher_allocation')) {
            $q = $this->db->select('DISTINCT class_id', false)
                          ->from('teacher_allocation')
                          ->where('teacher_id', $staff_id)
                          ->get();
            if ($q) foreach ($q->result_array() as $r) if (!empty($r['class_id'])) $ids[] = (int)$r['class_id'];
        }

        $this->db->reset_query();
        if ($this->db->table_exists('class_teacher')) {
            $q = $this->db->select('DISTINCT class_id', false)
                          ->from('class_teacher')
                          ->where('staff_id', $staff_id)
                          ->get();
            if ($q) foreach ($q->result_array() as $r) if (!empty($r['class_id'])) $ids[] = (int)$r['class_id'];
        }

        $this->db->reset_query();
        return array_values(array_unique($ids));
    }

    private function autoTextColor($hex)
    {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) !== 6) return '#ffffff';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 150) ? '#111111' : '#ffffff';
    }
    
    /** teacher classes as [id => name] */
    private function get_teacher_classes_kv(): array
    {
        $ids = $this->get_teacher_classes_ids();
        if (empty($ids)) return [];

        $this->db->reset_query();
        $rows = $this->db->select('id, name')
                         ->from('class')
                         ->where_in('id', $ids)
                         ->order_by('name', 'ASC')
                         ->get()
                         ->result_array();

        $out = [];
        foreach ($rows as $r) $out[(int)$r['id']] = $r['name'];
        $this->db->reset_query();
        return $out;
    }

    /** Branch-scoped event type options for dropdowns */
    private function event_type_options($branchID): array
    {
        $opts = ['' => translate('select')];
        if (empty($branchID)) return $opts;

        $rows = $this->db->select('id, name')
                         ->from('event_types')
                         ->where('branch_id', (int)$branchID)
                         ->order_by('name', 'ASC')
                         ->get()
                         ->result_array();
        foreach ($rows as $r) $opts[(int)$r['id']] = $r['name'];
        return $opts;
    }

    /** Upload validation callback for user_photo. */
    public function photoHandleUpload($str, $field)
    {
        if (!isset($_FILES[$field]) || empty($_FILES[$field]['name'])) {
            return true;
        }
        $name = $_FILES[$field]['name'];
        $type = $_FILES[$field]['type'];
        $size = (int)$_FILES[$field]['size'];

        $allowed_ext  = ['jpg','jpeg','png','gif','webp'];
        $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true) || !in_array($type, $allowed_mime, true)) {
            $this->form_validation->set_message('photoHandleUpload', translate('invalid_file_type'));
            return false;
        }
        if ($size > (5 * 1024 * 1024)) {
            $this->form_validation->set_message('photoHandleUpload', translate('file_size_exceeded'));
            return false;
        }
        return true;
    }
    // Return branch-scoped (plus global) event types as <option> list for the Type <select>
public function getTypesByBranch()
{
    if (!$this->input->is_ajax_request()) show_404();
    $branchID = (int)($this->input->post('branch_id') ?: $this->application_model->get_branch_id());

    $rows = $this->db->select('id,name')
        ->from('event_types')
        ->where('branch_id', $branchID)
        ->order_by('name','ASC')
        ->get()->result_array();

    $html = '<option value="">'.translate('select').'</option>';
    foreach ($rows as $r) {
        $html .= '<option value="'.(int)$r['id'].'">'.html_escape($r['name']).'</option>';
    }
    echo $html;
}


}
