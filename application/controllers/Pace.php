<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pace extends Admin_Controller
{

     public function __construct()
    {
        parent::__construct();

        // Allow Admin(1), Teacher(3), and optionally Office/Inventory role(6)
		if (!in_array((int)$this->session->userdata('loggedin_role_id'), [1,2,3,4,6,8])) {
    		access_denied();
		}

        $this->load->helper('custom');
        $this->load->model('pace_model');
		$this->load->model('pace_order_workflow_model');
		$this->load->model('Pace_order_workflow_model', 'pow');
		$this->load->model('Pace_orders_model', 'pom'); 

        // NEW: model for pre-invoice orders (review/edit before invoice)
        $this->load->model('Pace_orders_model', 'po');
    }

    // NEW: simple helper for finance/admin style roles
    private function is_finance_or_admin()
    {
        return in_array((int)$this->session->userdata('loggedin_role_id'), [1,2,4,8], true);
    }

    /* ─────────────────────────────────────────
     * LEGACY: Mark PACEs Completed screen
     * ───────────────────────────────────────── */
    public function mark_completed()
    {
        $this->data['title']      = 'Mark PACEs Completed';
        $this->data['main_menu']  = 'pace';
        $this->data['sub_page']   = 'pace/mark_completed';

        $this->data['students']         = $this->pace_model->get_all_students();
        $this->data['selected_student'] = $this->input->get('student_id');
        $this->data['selected_term']    = $this->input->get('term');

        if ($this->data['selected_student']) {
            $this->data['pace_assignments'] = $this->pace_model->get_student_paces(
                $this->data['selected_student'],
                $this->data['selected_term']
            );
        }

        $this->load->view('layout/index', $this->data);
    }

    /* ─────────────────────────────────────────
     * GENERAL status update (admin only)
     * ───────────────────────────────────────── */
    public function update_status()
    {
        $id     = (int)$this->input->post('id');
        $status = $this->input->post('status', true);
        $role   = (int)$this->session->userdata('loggedin_role_id');

        $allowed = ['assigned','ordered','paid','issued','completed','redo'];
        if ($role !== 1 || $id <= 0 || !in_array($status, $allowed, true)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'Invalid input or permissions']));
        }

        $now = date('Y-m-d H:i:s');
        $upd = ['status' => $status];
        switch ($status) {
            case 'ordered':   $upd['ordered_at']    = $now; break;
            case 'paid':      $upd['paid_at']       = $now; break;
            case 'issued':    $upd['issued_at']     = $now; break;
            case 'assigned':  $upd['assigned_date'] = date('Y-m-d'); break;
            case 'completed': $upd['completed_at']  = $now; break;
            case 'redo': /* keep timestamps */ break;
        }

        $this->db->where('id', $id)->update('student_assign_paces', $upd);
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => true]));
    }

    /* ─────────────────────────────────────────
     * ASSIGN PACEs (mirror of Order view; edit only after Issued)
     * ───────────────────────────────────────── */
    public function assign()
{
    $this->data['title']      = 'Assign PACEs';
    $this->data['main_menu']  = 'pace';
    $this->data['sub_page']   = 'pace/assign';

    $this->data['students'] = $this->pace_model->get_all_students();
    $this->data['subjects'] = $this->pace_model->get_all_subjects();

    $stu  = $this->input->get('student_id');
    $sub  = $this->input->get('subject_id');
    $term = $this->input->get('term');

    // ← normalize "Q1".."Q4" to "1".."4" if that’s what comes in
    if ($term && preg_match('/^Q([1-4])$/i', (string)$term, $m)) {
        $term = $m[1];
    }

    $this->data['selected_student'] = $stu;
    $this->data['selected_subject'] = $sub;
    $this->data['selected_term']    = $term;

    $this->data['assigned'] = [];
if ($stu && $sub) {
    $this->db->from('student_assign_paces')
             ->where('student_id', (int)$stu)
             ->where('subject_id', (int)$sub)
             ->where_in('status', ['ordered','paid','issued','assigned','completed','redo']);

    // ✅ Accept DB rows stored as 1 or 'Q1'
    if ($term !== null && $term !== '') {
        $this->db->group_start()
                 ->where('term', (string)$term)          // e.g. "1"
                 ->or_where('term', 'Q' . (string)$term)  // e.g. "Q1"
                 ->or_where('term', (int)$term)           // numeric column
                 ->group_end();
    }

    $this->db->order_by('pace_number', 'ASC');
    $this->data['assigned'] = $this->db->get()->result();
}

    $this->load->view('layout/index', $this->data);
}

    /* ─────────────────────────────────────────
     * Assign-page status changes (teacher/admin)
     * Allowed: assigned / completed / redo
     * Only after row is Issued (or already Assigned)
     * ───────────────────────────────────────── */
    public function update_assign_status()
{
    $id     = (int)$this->input->post('id');
    $status = strtolower(trim($this->input->post('status', true)));
    $role   = (int)$this->session->userdata('loggedin_role_id');

    if ($id <= 0 || !in_array($status, ['assigned','completed','redo','issued'], true)) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Invalid input']));
    }

    $row = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();
    if (!$row) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Row not found']));
    }

    $now = date('Y-m-d H:i:s');

    // --- Admin-side: allow ORDERED/PAID -> ISSUED
    if ($status === 'issued') {
            $allowed_roles = [1,2,4,8]; // super admin, admin, accounts, reception
        if (!in_array($role, $allowed_roles, true)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'Only office/admin can issue']));
        }

        if (!in_array($row['status'], ['ordered','paid'], true)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'Only Ordered/Paid can be issued']));
        }

        $this->db->where('id', $id)->update('student_assign_paces', [
            'status'    => 'issued',
            'issued_at' => $now,
        ]);
        $fresh = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();

        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'row' => $fresh]));
    }

    // --- Teacher/admin actions from the Assign screen
    if (in_array($row['status'], ['ordered','paid'], true)) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Waiting for Issued (office action).']));
    }

    if (!in_array($row['status'], ['issued','assigned'], true)) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Not assignable from current status.']));
    }

    // ✅ NEW: when switching to ASSIGNED, enforce previous pass ≥ 80%
    if ($status === 'assigned') {
        $this->load->model('pace_model');
        $student_id = (int)$row['student_id'];
        $subject_id = (int)$row['subject_id'];
        $pace_no    = (int)$row['pace_number'];

        if (!$this->pace_model->can_assign_next($student_id, $subject_id, $pace_no)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'error' => 'Previous PACE not passed (≥80%).']));
        }
    }

    $upd = ['status' => $status];
    if ($status === 'assigned') {
        $upd['assigned_date'] = date('Y-m-d');
    } elseif ($status === 'completed') {
        $upd['completed_at']  = $now;
    } elseif ($status === 'redo') {
        // timestamps unchanged; redo is handled elsewhere
    }

    $this->db->where('id', $id)->update('student_assign_paces', $upd);
    $fresh = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();

    // ✅ NEW: when assigned, ping Monitor Goal Check for today so it lights up
    $this->load->model('monitor_goal_check_model');
    if ($status === 'assigned' && method_exists($this->monitor_goal_check_model, 'set_assigned_pace')) {
        $this->monitor_goal_check_model->set_assigned_pace(
            (int)$fresh['student_id'],
            (int)$fresh['subject_id'],
            (int)$fresh['pace_number'],
            date('Y-m-d')
        );
    }

    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['success' => true, 'row' => $fresh]));
}



    /* ─────────────────────────────────────────
     * (Optional) legacy single assign endpoint still available
     * ───────────────────────────────────────── */
    public function ajax_assign_to_child()
{
    $id         = (int)$this->input->post('id');
    $slot_index = $this->input->post('slot_index', true);
    $term       = $this->input->post('term', true);

    if ($id <= 0) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'msg' => 'Bad id']));
    }

    $row = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();
    if (!$row) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'msg' => 'Not found']));
    }

    if ($row['status'] !== 'issued') {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'msg' => 'Only issued PACEs can be assigned']));
    }

    // ✅ NEW: enforce previous pass rule
    $this->load->model('pace_model');
    if (!$this->pace_model->can_assign_next((int)$row['student_id'], (int)$row['subject_id'], (int)$row['pace_number'])) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'msg' => 'Previous PACE not passed (≥80%).']));
    }

    $upd = [
        'status'        => 'assigned',
        'assigned_date' => date('Y-m-d'),
    ];
    if ($slot_index !== null && $slot_index !== '') $upd['slot_index'] = (int)$slot_index;
    if ($term) $upd['term'] = $term;

    $this->db->where('id', $id)->update('student_assign_paces', $upd);
    $fresh = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();

    // ✅ NEW: ping Monitor Goal Check so "current" PACE shows today
    $this->load->model('monitor_goal_check_model');
    if (method_exists($this->monitor_goal_check_model, 'set_assigned_pace')) {
        $this->monitor_goal_check_model->set_assigned_pace(
            (int)$fresh['student_id'],
            (int)$fresh['subject_id'],
            (int)$fresh['pace_number'],
            date('Y-m-d')
        );
    }

    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['ok' => true, 'row' => $fresh]));
}


    /* ─────────────────────────────────────────
     * RECORD SCORES (screen)
     * ───────────────────────────────────────── */
    public function record_score()
    {
        $this->data['title']      = 'Record PACE Scores';
        $this->data['main_menu']  = 'pace';
        $this->data['sub_page']   = 'pace/record_score';

        $this->data['students']         = $this->pace_model->get_all_students();
        $this->data['selected_student'] = $this->input->get('student_id');
        $this->data['selected_term']    = $this->input->get('term');

        $this->data['pending'] = [];
        if ($this->data['selected_student']) {
            $this->data['pending'] = $this->pace_model->get_pending_paces(
                $this->data['selected_student'],
                $this->data['selected_term']
            );
        }

        $this->load->view('layout/index', $this->data);
    }

    /* ─────────────────────────────────────────
     * BULK SCORE SAVE
     * ───────────────────────────────────────── */
    public function record_score_save()
    {
        $rows = $this->input->post('rows');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if ($row['score'] === '' || $row['score'] === null) continue;
                $this->pace_model->save_score([
                    'id'      => (int)$row['id'],
                    'score'   => (int)$row['score'],
                    'remarks' => $row['remarks'] ?? ''
                ]);
            }
        }
        $student = $this->input->post('filter_student');
        $term    = $this->input->post('filter_term');
        $this->session->set_flashdata('success', 'Scores saved.');
        redirect('pace/record_score?student_id=' . $student . '&term=' . $term);
    }

    /* ─────────────────────────────────────────
     * AJAX helpers for front-end lists
     * ───────────────────────────────────────── */
    public function ajax_list_assignments()
    {
        $student_id = (int)$this->input->get('student_id');
        $subject_id = (int)$this->input->get('subject_id');

        if ($student_id <= 0 || $subject_id <= 0) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'msg' => 'Missing student or subject']));
        }

        $rows = $this->db->select('id, student_id, subject_id, pace_number, term, status, ordered_at, paid_at, issued_at, completed_at, first_attempt_score, second_attempt_score, final_score')
            ->from('student_assign_paces')
            ->where('student_id', $student_id)
            ->where('subject_id', $subject_id)
            ->order_by('pace_number', 'ASC')
            ->get()->result_array();

        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true, 'rows' => $rows]));
    }

    /* =======================================================================
     * ORDER PACEs (teachers) + Admin status control (ordered ⇄ paid ⇄ issued)
     * ======================================================================= */

    // Screen
public function order()
{
    // Redirect any old/notification links that include ?invoice_id=… to Order Batches
    $invoice_id = (int)$this->input->get('invoice_id');
    if ($invoice_id > 0) {
        redirect(site_url('pace/orders_batches?invoice_id=' . $invoice_id), 'refresh'); // <-- plural
        return;
    }

    $role_id = (int) $this->session->userdata('loggedin_role_id');
    if (!in_array($role_id, [1,2,3,4,8], true)) { return access_denied(); }

    $this->load->model('pace_model');
    $this->load->model('student_model'); // <<— ADDED

    $this->data['title']     = 'Order PACEs';
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/order';

    // filters
    $student_id = (int)$this->input->get('student_id');
    $term       = trim((string)$this->input->get('term'));

    // Teachers: default = grade-restricted (show_all OFF).
    // Admins/office: default = all PACEs.
    $show_all = ($role_id === 3)
        ? ((string)$this->input->get('show_all') === '1')
        : true; // non-teachers always see all unless you add a toggle in UI

    // dropdowns
    $this->data['students']   = $this->pace_model->get_all_students(); // arrays
    $this->data['student_id'] = $student_id;
    $this->data['term']       = $term;
    $this->data['show_all']   = $show_all ? 1 : 0;

    // payload for the grid
    $this->data['subjects']  = [];
    $this->data['available'] = [];
    $this->data['orders']    = [];

    if ($student_id > 0 && $term !== '') {

        // --- STRICT FILTER: only show subjects assigned on "Assign Subjects"
        $year          = (int)date('Y'); // use your academic year if different
        $assigned_ids  = $this->student_model->get_assigned_subject_ids($student_id, $year);
        if (empty($assigned_ids)) {
            // fallback: any year/session mapping
            $assigned_ids = $this->pace_model->get_assigned_subject_ids_for_student_any($student_id);
        }

        $subjects = [];
        if (!empty($assigned_ids)) {
            // start from enrolled (class subjects), then filter to assigned_ids
            $all = $this->pace_model->get_enrolled_subjects($student_id); // arrays (id, name, subject_code)
            $subjects = array_values(array_filter($all, function($s) use ($assigned_ids) {
                return in_array((int)$s['id'], $assigned_ids, true);
            }));
        } else {
            // nothing assigned -> show nothing
            $this->data['no_assigned_subjects'] = true;
        }

        $this->data['subjects'] = $subjects;

        // Teacher may be limited to grade-specific list
        $grade = null;
        if ($role_id === 3 && !$show_all) {
            $g = $this->db->select('c.name_numeric AS grade')
                ->from('enroll e')
                ->join('class c', 'c.id = e.class_id', 'left')
                ->where('e.student_id', $student_id)
                ->where('e.session_id', get_session_id())   // ensure current session
                ->order_by('e.id', 'DESC')->limit(1)
                ->get()->row_array();
            $grade = $g ? (int)$g['grade'] : null;
        }

        // Build available list per subject (only for filtered subjects)
        $available = [];
        foreach ($subjects as $s) {
            $sid = (int)$s['id'];
            $available[$sid] = $grade
                ? $this->pace_model->get_available_paces_by_grade($student_id, $sid, $grade)
                : $this->pace_model->get_available_paces_admin($student_id, $sid);
        }
        $this->data['available'] = $available;

        // Current in-pipeline orders (JOIN subject so UI shows names, not IDs)
        $this->db->select('sap.*, subj.name AS subject_name, sap.is_redo')
                 ->from('student_assign_paces sap')
                 ->join('subject subj', 'subj.id = sap.subject_id', 'left')
                 ->where('sap.student_id', $student_id)
                 ->where_in('sap.status', ['ordered','paid','issued'])
                 ->order_by('sap.ordered_at','DESC');
        if ($term !== '') $this->db->where('sap.term', $term);
        $this->data['orders'] = $this->db->get()->result_array();
    }

    $this->load->view('layout/index', $this->data);
}
    
 // ====== Save orders from multi-subject grid ======
public function order_save()
{
    $student_id = (int)$this->input->post('student_id');
    $term       = (string)$this->input->post('term');
    $map        = (array)$this->input->post('pace');  // pace[subject_id][] = N

    // campus-local timezone for correct timestamps
    $tz = 'Africa/Johannesburg';
    if (function_exists('get_global_setting')) {
        $t = get_global_setting('timezone');
        if (!empty($t)) $tz = $t;
    }
    @date_default_timezone_set($tz);

    if ($student_id <= 0 || $term === '') {
        $this->session->set_flashdata('error', 'Select student and term.');
        redirect('pace/order'); return;
    }

    $now     = date('Y-m-d H:i:s');
    $session = (int) get_session_id();
    $branch  = (int) get_loggedin_branch_id();

    $rows = [];
    $orderedSubjectCandidates = []; // ⇐ NEW: subjects that had any PACEs ticked

    if (!empty($map)) {
        foreach ($map as $subject_id => $numbers) {
            $subject_id = (int)$subject_id;

            // keep only numeric, positive selections (so we can both insert and remember the subject)
            $selectedNums = array_values(array_filter((array)$numbers, function($n){
                return (int)$n > 0;
            }));

            if ($selectedNums) {
                $orderedSubjectCandidates[] = $subject_id; // ⇐ mark for optional selection
            }

            foreach ($selectedNums as $num) {
                $num = (int)$num; if ($num <= 0) continue;

                // avoid duplicates across statuses
                $exists = $this->db->where([
                        'student_id'  => $student_id,
                        'subject_id'  => $subject_id,
                        'pace_number' => $num,
                    ])->get('student_assign_paces')->row_array();
                if ($exists) continue;

                $this->db->insert('student_assign_paces', [
                    'student_id'     => $student_id,
                    'subject_id'     => $subject_id,
                    'pace_number'    => $num,
                    'term'           => $term,
                    'status'         => 'ordered',
                    'ordered_at'     => $now,
                    'branch_id'      => $branch,
                    'session_id'     => $session,
                    'attempt_number' => 1,
                ]);
                $id = (int)$this->db->insert_id();
                if ($id) {
                    $rows[] = ['id'=>$id,'subject_id'=>$subject_id,'pace_number'=>$num];
                    // kick off workflow (notifications/invoice/stock); ignore soft failures
                    if (isset($this->pace_order_workflow_model)) {
                        $this->pace_order_workflow_model->on_ordered($id);
                    } else {
                        $this->load->model('Pace_order_workflow_model','pace_order_workflow_model');
                        $this->pace_order_workflow_model->on_ordered($id);
                    }
                }
            }
        }
    }

    // ⇩ NEW: If any PACEs were ticked for OPTIONAL subjects, persist them as the student's optionals
    if ($orderedSubjectCandidates) {
        // keep only optionals in this campus
        $optRows = $this->db->select('id')
            ->from('subject')
            ->where_in('id', array_unique(array_map('intval', $orderedSubjectCandidates)))
            ->where('branch_id', $branch)
            ->where("subject_type <>", 'Mandatory')
            ->get()->result_array();

        $optionalIds = array_map(function($r){ return (int)$r['id']; }, $optRows);

        if ($optionalIds) {
            $this->load->model('monitor_goal_check_model');
            // merges (does not delete existing picks)
            $this->monitor_goal_check_model->add_student_optionals($student_id, $optionalIds, $branch, $session);
        }
    }
    // ⇧ NEW END

    $this->session->set_flashdata('success', 'Order placed.');
    redirect('pace/order?student_id='.$student_id.'&term='.rawurlencode($term));
}

    // Admin-only: update order status (Ordered ⇄ Paid ⇄ Issued)
   
public function update_order_status()
{
    $id     = (int)$this->input->post('id');
    $status = $this->input->post('status', true);

    if ((int)$this->session->userdata('loggedin_role_id') !== 1) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Admin only']));
    }
    if ($id <= 0 || !in_array($status, ['ordered','paid','issued'], true)) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Invalid input']));
    }

    $prev = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();
    if (!$prev) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Row not found']));
    }

    $now = date('Y-m-d H:i:s');

    // Promote REDO to ISSUED automatically when set to PAID
    $is_redo = !empty($prev['is_redo']);
    if ($status === 'paid' && $is_redo) {
        $status = 'issued';
    }

    $upd = ['status' => $status];
    if ($status === 'ordered') $upd['ordered_at'] = $now;
    if ($status === 'paid')    $upd['paid_at']    = $now;
    if ($status === 'issued')  { 
        // If moving straight to issued and not already paid, stamp paid too
        if (empty($prev['paid_at'])) $upd['paid_at'] = $now;
        $upd['issued_at'] = $now; 
    }

    $this->db->where('id', $id)->update('student_assign_paces', $upd);

    // Also update the parent invoice header away from DRAFT so future orders open a new invoice
    $inv = $this->db->select('invoice_id')
        ->from('hs_academy_invoice_items')
        ->where('sap_id', $id)
        ->order_by('invoice_id','DESC')->limit(1)
        ->get()->row_array();

    if ($inv && !empty($inv['invoice_id'])) {
        $invStatus = ($status === 'issued') ? 'billed' : (($status === 'paid') ? 'paid' : 'draft');
        if ($invStatus !== 'draft') {
            $this->db->where('id', (int)$inv['invoice_id'])->update('hs_academy_invoices', [
                'status'     => $invStatus,
                'updated_at' => $now,
            ]);
        }
    }

    $row = $this->db->get_where('student_assign_paces', ['id' => $id])->row_array();
    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['success' => true, 'row' => $row]));
}


public function ajax_assign_single()
{
    $student_id = (int)$this->input->post('student_id');
    $subject_id = (int)$this->input->post('subject_id');
    $term       = $this->input->post('term');
    $pace_no    = (int)$this->input->post('pace_no');

    $this->load->model('pace_model');
    $res = $this->pace_model->assign_single_pace($student_id, $subject_id, $term, $pace_no);

    echo json_encode($res ? ['ok' => true, 'slot' => $res['slot']] : ['ok' => false]);
}


// ======= NEW: All Orders (campus-scoped) =======
public function orders_all()
{
    $role_id = (int)$this->session->userdata('loggedin_role_id');
$allowed = [1,2,4,8]; // was [1,2,5,7]
if (!in_array($role_id, $allowed, true)) { return access_denied(); }

    $branch_id = (int)get_loggedin_branch_id();

    $this->data['title']     = 'All PACE Orders';
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/orders_all';

    $this->db->select("
        sap.id, sap.student_id, sap.subject_id, sap.pace_number, sap.term, sap.status,
        sap.ordered_at, sap.paid_at, sap.issued_at, sap.branch_id,
        CONCAT_WS(' ', st.first_name, st.last_name) AS student_name,
        subj.name AS subject_name,
        (SELECT hii.invoice_id FROM hs_academy_invoice_items hii WHERE hii.sap_id = sap.id LIMIT 1) AS invoice_id
    ", false)
    ->from('student_assign_paces sap')
    ->join('student st', 'st.id = sap.student_id', 'left')
    ->join('subject subj', 'subj.id = sap.subject_id', 'left')
    ->where_in('sap.status', ['ordered','paid','issued'])
    ->where('sap.branch_id', $branch_id)
    ->order_by('sap.ordered_at', 'DESC');

    $this->data['rows'] = $this->db->get()->result_array();
    $this->load->view('layout/index', $this->data);
}

// ======= NEW: Invoices list (campus-scoped) =======
public function invoices()
{
    $role_id = (int)$this->session->userdata('loggedin_role_id');
$allowed = [1,2,4,8];
if (!in_array($role_id, $allowed, true)) { return access_denied(); }

    $branch_id = (int)get_loggedin_branch_id();

    $this->data['title']     = 'Invoices';
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/invoices';

    $this->db->select("
        inv.id, inv.branch_id, inv.student_id, inv.session_id, inv.status, inv.total, inv.created_at, inv.updated_at,
        CONCAT_WS(' ', st.first_name, st.last_name) AS student_name
    ", false)
    ->from('hs_academy_invoices inv')
    ->join('student st', 'st.id = inv.student_id', 'left')
    ->where('inv.branch_id', $branch_id)
    ->order_by('inv.created_at', 'DESC');

    $this->data['invoices'] = $this->db->get()->result_array();
    $this->load->view('layout/index', $this->data);
}

// ======= Daily / Invoice / Student Order Batches (campus-scoped) =======
public function orders_batches()
{
    // allow super admin / admin / accountant / reception
    $role_id = (int)$this->session->userdata('loggedin_role_id');
    if (!in_array($role_id, [1,2,4,8], true)) { return access_denied(); }

    $this->data['title']     = 'PACE Order Batches'; // required by layout
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/orders_batches';

    $branch_id = (int) get_loggedin_branch_id();

    // remove obsolete 'order'; allow invoice | day | student
    $group = strtolower((string)($this->input->get('group') ?: 'invoice'));
    if ($group === 'order') { $group = 'invoice'; }
    if (!in_array($group, ['invoice','day','student'], true)) { $group = 'invoice'; }
    $this->data['group'] = $group;

    if ($group === 'invoice') {
    $student_id = (int)$this->input->get('student_id'); // <- from query string

    $qb = $this->db->select("
        inv.id AS invoice_id,
        inv.status AS invoice_status,
        DATE(inv.created_at) AS created_date,
        CONCAT(st.first_name,' ',st.last_name) AS student_name,
        COUNT(ii.id) AS total,
        SUM(CASE WHEN sap.ordered_at IS NOT NULL THEN 1 ELSE 0 END) AS ordered_cnt,
        SUM(CASE WHEN sap.paid_at    IS NOT NULL THEN 1 ELSE 0 END) AS paid_cnt,
        SUM(CASE WHEN sap.issued_at  IS NOT NULL THEN 1 ELSE 0 END) AS issued_cnt
    ", false)
    ->from('hs_academy_invoices inv')
    ->join('student st', 'st.id = inv.student_id', 'left')
    ->join('hs_academy_invoice_items ii', 'ii.invoice_id = inv.id', 'left')
    ->join('student_assign_paces sap', 'sap.id = ii.sap_id', 'left')
    ->where('inv.branch_id', $branch_id);

    if ($student_id > 0) {
        $qb->where('inv.student_id', $student_id);
    }

    $rows = $qb->group_by('inv.id')->order_by('inv.created_at','DESC')->get()->result_array();
    $this->data['rows'] = $rows;

    } elseif ($group === 'student') {
    // Per-student rollup: derive students from activity (SAP or Invoices) for this branch
    $sql = "
        SELECT
            s.id AS student_id,
            CONCAT_WS(' ', s.first_name, s.last_name) AS student_name,
            COALESCE(o.ordered_cnt, 0) AS ordered_cnt,
            COALESCE(b.billed_cnt, 0)  AS billed_cnt,
            COALESCE(p.paid_cnt, 0)    AS paid_cnt,
            COALESCE(u.issued_cnt, 0)  AS issued_cnt
        FROM (
            /* any student with activity in this branch */
            SELECT x.student_id
            FROM (
                SELECT sap.student_id
                  FROM student_assign_paces sap
                 WHERE sap.branch_id = ?
                   AND sap.ordered_at IS NOT NULL
                UNION
                SELECT iv.student_id
                  FROM hs_academy_invoices iv
                 WHERE iv.branch_id = ?
            ) x
            GROUP BY x.student_id
        ) act
        JOIN student s ON s.id = act.student_id

        /* ordered */
        LEFT JOIN (
            SELECT sap1.student_id, COUNT(*) AS ordered_cnt
              FROM student_assign_paces sap1
             WHERE sap1.branch_id = ?
               AND sap1.ordered_at IS NOT NULL
             GROUP BY sap1.student_id
        ) o ON o.student_id = s.id

        /* billed/checked */
        LEFT JOIN (
            SELECT iv.student_id, COUNT(ivi.id) AS billed_cnt
              FROM hs_academy_invoices iv
              JOIN hs_academy_invoice_items ivi ON ivi.invoice_id = iv.id
             WHERE iv.branch_id = ?
             GROUP BY iv.student_id
        ) b ON b.student_id = s.id

        /* paid */
        LEFT JOIN (
            SELECT iv2.student_id, COUNT(ivi2.id) AS paid_cnt
              FROM hs_academy_invoices iv2
              JOIN hs_academy_invoice_items ivi2 ON ivi2.invoice_id = iv2.id
             WHERE iv2.branch_id = ?
               AND (
                     iv2.status = 1
                  OR (iv2.status IS NOT NULL AND LOWER(CAST(iv2.status AS CHAR)) = 'paid')
               )
             GROUP BY iv2.student_id
        ) p ON p.student_id = s.id

        /* issued */
        LEFT JOIN (
            SELECT sap2.student_id, COUNT(*) AS issued_cnt
              FROM student_assign_paces sap2
             WHERE sap2.branch_id = ?
               AND sap2.issued_at IS NOT NULL
             GROUP BY sap2.student_id
        ) u ON u.student_id = s.id

        ORDER BY s.first_name, s.last_name
    ";

    $rows_q = $this->db->query($sql, [
        (int)$branch_id,  // act from SAP
        (int)$branch_id,  // act from Invoices
        (int)$branch_id,  // ordered
        (int)$branch_id,  // billed
        (int)$branch_id,  // paid
        (int)$branch_id   // issued
    ]);

    $this->data['rows'] = ($rows_q ? $rows_q->result_array() : []);
}

     else {
        // Per-day summary (based on timestamps)
        $rows = $this->db->select("
                DATE(sap.ordered_at) AS order_date,
                COUNT(*) AS total,
                SUM(CASE WHEN sap.ordered_at IS NOT NULL THEN 1 ELSE 0 END) AS ordered_cnt,
                SUM(CASE WHEN sap.paid_at    IS NOT NULL THEN 1 ELSE 0 END) AS paid_cnt,
                SUM(CASE WHEN sap.issued_at  IS NOT NULL THEN 1 ELSE 0 END) AS issued_cnt
            ", false)
            ->from('student_assign_paces sap')
            ->where('sap.branch_id', $branch_id)
            ->where('sap.ordered_at IS NOT NULL', null, false)
            ->group_by('DATE(sap.ordered_at)')
            ->order_by('order_date','DESC')
            ->get()->result_array();

        $this->data['rows'] = $rows;
    }

    $this->load->view('layout/index', $this->data);
}


// VIEW: one invoice (drill-down) for the batches page
public function orders_batch_view($invoice_id = 0)
{
    $invoice_id = (int)$invoice_id;

    // Header with student name for the batch view
    $invoice = $this->db->select("inv.*, CONCAT(st.first_name,' ',st.last_name) AS student_name", false)
        ->from('hs_academy_invoices inv')
        ->join('student st', 'st.id = inv.student_id', 'left')
        ->where('inv.id', $invoice_id)
        ->get()->row_array();

    // Items joined to SAP so the view can show Status + Ordered/Paid/Issued dates
    $items = $this->db->select("
            ii.*,
            subj.name AS subject_name,
            sap.status,
            sap.pace_number,
            sap.ordered_at,
            sap.paid_at,
            sap.issued_at
        ", false)
        ->from('hs_academy_invoice_items ii')
        ->join('student_assign_paces sap', 'sap.id = ii.sap_id', 'left')
        ->join('subject subj', 'subj.id = ii.subject_id', 'left')
        ->where('ii.invoice_id', $invoice_id)
        ->order_by('subj.name', 'ASC')
        ->get()->result_array();

    $this->data['title']     = 'Invoice Batch';
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/orders_batch_view';
    $this->data['invoice']   = $invoice; // keep variable name as your current view uses
    $this->data['items']     = $items;

    $this->load->view('layout/index', $this->data);
}

// AJAX: mark invoice status (issued / paid / draft / cancelled)
public function ajax_mark_invoice()
{
    if (!$this->input->is_ajax_request()) {
        show_404();
    }

    // Load deps used elsewhere in this controller
    $this->load->model('Pace_order_workflow_model');

    $invoice_id = (int)$this->input->post('invoice_id');
    $to         = (string)$this->input->post('to', true); // expected: issued|paid|draft|cancelled

    if ($invoice_id <= 0 || !in_array($to, ['issued','paid','draft','cancelled'], true)) {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'msg' => 'Invalid input']));
        return; // early
    }

    // Do the transition (adjust model/method name if yours differs)
    $ok = $this->Pace_order_workflow_model->mark_invoice_status($invoice_id, $to, (int)$this->session->userdata('loggedin_user_id'));

    // Always return clean JSON ONLY and terminate
    $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode(['ok' => (bool)$ok]));
    exit; // <-- IMPORTANT: stop any further output mixing into the response
}


// BULK mark an invoice as PAID or ISSUED (updates all its SAP rows)

public function orders_batch_mark()
{
    $role_id = (int)$this->session->userdata('loggedin_role_id');
    if (!in_array($role_id, [1,2,4,8])) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok'=>false,'msg'=>'No permission']));
    }

    $invoice_id = (int)$this->input->post('invoice_id');
    $to         = $this->input->post('to'); // 'paid' | 'issued'
    if ($invoice_id <= 0 || !in_array($to, ['paid','issued'], true)) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok'=>false,'msg'=>'Bad request']));
    }

    // All SAP rows on this invoice
    $sap_ids = array_column(
        $this->db->select('sap_id')->from('hs_academy_invoice_items')->where('invoice_id', $invoice_id)->get()->result_array(),
        'sap_id'
    );
    if (!$sap_ids) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok'=>false,'msg'=>'No items on invoice']));
    }

    // Which of those are REDO?
    $redo_ids = array_column(
        $this->db->select('sap.id')
            ->from('student_assign_paces sap')
            ->join('hs_academy_invoice_items ii','ii.sap_id = sap.id','left')
            ->where('ii.invoice_id', $invoice_id)
            ->group_start()
                ->where('sap.is_redo', 1)
                ->or_where('ii.is_redo', 1)
            ->group_end()
            ->get()->result_array(),
        'id'
    );
    $redo_ids = array_map('intval', $redo_ids);
    $normal_ids = array_values(array_diff(array_map('intval',$sap_ids), $redo_ids));

    $now = date('Y-m-d H:i:s');
    $this->db->trans_start();

    if ($to === 'paid') {
        // 1) normal rows: ordered -> paid
        if ($normal_ids) {
            $this->db->where_in('id', $normal_ids)
                     ->where('status', 'ordered')
                     ->update('student_assign_paces', ['status'=>'paid','paid_at'=>$now]);
        }
        // 2) redo rows: redo/ordered/paid -> issued (so teachers can assign immediately)
if ($redo_ids) {
    $this->db->where_in('id', $redo_ids)
             ->where_in('status', ['ordered','paid','redo'])
             ->update('student_assign_paces', ['status'=>'issued','paid_at'=>$now,'issued_at'=>$now]);
}
        // Header: move invoice out of draft
        $this->db->where('id', $invoice_id)->update('hs_academy_invoices', [
    'status'     => 'billed',
    'updated_at' => $now,
        ]);
    } else { // $to === 'issued'
        $this->db->where_in('id', $sap_ids)
                 ->where_in('status', ['ordered','paid'])
                 ->update('student_assign_paces', ['status'=>'issued','issued_at'=>$now] + ['paid_at'=>$now]);
        $this->db->where('id', $invoice_id)->update('hs_academy_invoices', [
            'status'     => 'issued',
            'updated_at' => $now,
        ]);
    }

    $this->db->trans_complete();
    $ok = $this->db->trans_status();

    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['ok'=> $ok ? true : false]));
}

// ======= NEW: Bulk update a batch (entire day -> paid OR issued) =======
public function batch_update_status()
{
    $role_id = (int)$this->session->userdata('loggedin_role_id');
$allowed = [1,2,4,8];
if (!in_array($role_id, $allowed, true)) {
    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['success' => false, 'error' => 'Not allowed']));
    }

    $order_date = $this->input->post('order_date', true); // YYYY-MM-DD
    $target     = $this->input->post('status', true);     // 'paid' | 'issued'
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$order_date) || !in_array($target, ['paid','issued'], true)) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Invalid input']));
    }

    $branch_id = (int)get_loggedin_branch_id();
    $now       = date('Y-m-d H:i:s');

    // Build the WHERE once
    $this->db->where('branch_id', $branch_id)
             ->where('DATE(ordered_at) =', $order_date, false)
             ->where_in('status', ['ordered','paid','issued']);

    // For PAID: only bump rows currently 'ordered'
    if ($target === 'paid') {
        $this->db->where('status', 'ordered');
        $this->db->update('student_assign_paces', ['status' => 'paid', 'paid_at' => $now]);
        $affected = $this->db->affected_rows();
    }

    // For ISSUED: bump rows currently 'ordered' or 'paid'
    if ($target === 'issued') {
        $this->db->where_in('status', ['ordered','paid']);
        $this->db->update('student_assign_paces', ['status' => 'issued', 'issued_at' => $now]);
        $affected = $this->db->affected_rows();
    }

    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['success' => true, 'updated' => (int)$affected]));
}

public function order_batches()
{
    $invoice_id = (int)$this->input->get('invoice_id');
    redirect(site_url('pace/orders_batches' . ($invoice_id ? '?invoice_id=' . $invoice_id : '')), 'refresh');
}

// === BEGIN: Assign Subjects (served under PACE module to pass RBAC) ===
// === Assign Subjects (UI) ===
// === Assign Subjects (UI) ===
public function assign_subjects()
{
    // roles allowed: SA(1), Admin(2), Teacher(3)
    $role = (int)$this->session->userdata('loggedin_role_id');
    if (!in_array($role, [1,2,3], true)) {
        return access_denied();
    }

    $this->load->model('Pace_model');
    $this->load->model('Student_model');

    $this->data['title']      = 'Assign Subjects';
    $this->data['main_menu']  = 'pace';
    $this->data['sub_page']   = 'teacher/assign_subjects';

    $student_id = (int)$this->input->get('student_id');
    $year       = (int)($this->input->get('year') ?: date('Y'));

    // Students list (teacher → only theirs)
    if ($role === 3) {
        $this->data['students'] = $this->Student_model
            ->get_my_students((int)$this->session->userdata('loggedin_userid'));
    } else {
        $this->data['students'] = $this->Pace_model->get_all_students();
    }

    // values needed by the view
    $this->data['selected_id']           = $student_id;
    $this->data['year']                  = $year;
    $this->data['mandatory_list']        = []; // <— what the view expects
    $this->data['optional_list']         = []; // <— what the view expects
    $this->data['selected_optional_ids'] = []; // <— what the view expects

    if ($student_id > 0) {
        // 1) Mandatory chips
        $man_ids = $this->Pace_model->get_mandatory_subject_ids_for_student($student_id);
        if ($man_ids) {
            $this->data['mandatory_list'] = $this->db->select('id, name')
                ->from('subject')
                ->where_in('id', $man_ids)
                ->order_by('name', 'ASC')
                ->get()->result_array();
        }

        // 2) Optional checkbox list
        $this->data['optional_list'] = $this->Pace_model->get_optional_subjects_for_student($student_id);

        // 3) Previously saved selection (for pre-checking checkboxes)
        $this->data['selected_optional_ids'] =
            $this->Pace_model->get_assigned_subject_ids_for_student($student_id);
    }

    $this->load->view('layout/index', $this->data);
}

// === Save Assign Subjects (mandatory + chosen optional) ===
public function assign_subjects_save()
{
    // CSRF protected form
    if (!$this->input->post()) show_404();

    $student_id = (int)$this->input->post('student_id');
    $year       = (int)$this->input->post('year');
    $electives  = (array)$this->input->post('subject_ids'); // optional IDs selected

    $this->load->model('Pace_model');

    // always include mandatory for this learner
    $mandatory = $this->Pace_model->get_mandatory_subject_ids_for_student($student_id);

    // union (unique)
    $final_ids = array_values(array_unique(array_map('intval', array_merge($mandatory, $electives))));

    // purge + insert for this student + year (or session if your table uses it)
    if ($this->db->field_exists('year','student_assigned_subjects')) {
        $this->db->where('student_id',$student_id)->where('year',$year)->delete('student_assigned_subjects');
    } else {
        // session-aware version
        $this->db->where('student_id',$student_id)->where('session_id',(int)get_session_id())->delete('student_assigned_subjects');
    }

    foreach ($final_ids as $sid) {
        $row = ['student_id'=>$student_id, 'subject_id'=>(int)$sid];
        if ($this->db->field_exists('year','student_assigned_subjects')) {
            $row['year'] = $year;
        } else {
            $row['session_id'] = (int)get_session_id();
        }
        $this->db->insert('student_assigned_subjects', $row);
    }

    // back to assign page (or straight to order page – your call)
    $this->session->set_flashdata('success','Subjects saved (mandatory included).');
    redirect('pace/assign_subjects?student_id='.$student_id.'&year='.$year);
}

// === MULTI-SUBJECT ASSIGN: GRID LOADER ===
// application/controllers/Pace.php
// REPLACE ONLY THIS METHOD

public function load_assign_grid()
{
    // Some proxies strip the AJAX header — allow GET/POST anyway.
    // Still return pure JSON (no layout).
    $this->output->set_content_type('application/json');

    $student_id = (int)$this->input->post_get('student_id', true);
    $term       = (int)$this->input->post_get('term', true);

    if ($student_id <= 0 || $term <= 0) {
        $out = [
            'status'  => 0,
            'message' => 'Missing student or term',
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ];
        return $this->output->set_output(json_encode($out));
    }

    // Student’s subjects (year-aware inside the model)
    $subjects = $this->pace_model->get_subjects_for_assign_grid($student_id);

    // 🔒 Show ONLY PACEs that Finance has ISSUED (pulled from student_assign_paces), scoped to the selected term
    $paceMap = $this->pace_model->get_issued_pace_numbers_for_subjects(array_column($subjects, 'id'), $student_id, $term);

    // Already-assigned for THIS TERM (drives the "already" badge / disabled state)
    $existing = $this->pace_model->get_existing_assignments($student_id, $term);

    $html = $this->load->view('pace/partials/_assign_grid', [
        'subjects' => $subjects,   // keep all subject cards; empty cards will say "No PACE numbers..."
        'paceMap'  => $paceMap,    // issued-only list per subject
        'existing' => $existing,   // term-scoped "already"
        'term'     => $term,
    ], true);

    $out = [
        'status' => 1,
        'html'   => $html,
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
    ];
    return $this->output->set_output(json_encode($out));
}



public function batch_assign()
{
    $this->output->set_content_type('application/json');

    $student_id = (int)$this->input->post('student_id', true);
    $term       = (int)$this->input->post('term', true);
    $items      = $this->input->post('items');

    if ($student_id <= 0 || $term <= 0 || empty($items) || !is_array($items)) {
        $out = [
            'status'  => 0,
            'message' => 'Missing student, term, or items.',
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ];
        return $this->output->set_output(json_encode($out));
    }

    $changed = (int)$this->pace_model->mark_issued_as_assigned($student_id, $term, $items);
    if ($changed < 0) $changed = 0; // never show -1 to users

    $out = [
        'status'   => 1,
        'inserted' => $changed,
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
    ];
    return $this->output->set_output(json_encode($out));
}


/* ======================================================================
 * NEW: Pre-invoice Order Review Flow (edit/check before invoice)
 * ====================================================================== */

// List the pre-invoice order batches (uses DB view v_pace_orders_batches)

// View/Edit a single pre-invoice order
public function orders_batch_edit($id)
{
    if (!$this->is_finance_or_admin()) return access_denied();

    $order = $this->po->get_order((int)$id);
    if (!$order) show_404();

    if (!$this->po->can_edit($order)) {
        set_alert('error', 'This order can no longer be edited.');
        return redirect('pace/orders_batches_pre');
    }

    $this->data['order'] = $order;

    // fetch items (whatever method currently used)
    $this->data['items'] = $this->po->get_order_items((int)$id);

    // --- NEW: hydrate missing/zero prices from subject_pace → product.sales_price ---
    foreach ($this->data['items'] as &$it) {
        $qty   = (float)($it['qty'] ?? 0);
        $price = (float)($it['unit_price'] ?? $it['price'] ?? 0);

        if ($price <= 0) {
            $subjectId = (int)$it['subject_id'];
            $paceNo    = (int)($it['pace_no'] ?? $it['pace_number'] ?? 0);
            $branchId  = (int)$order['branch_id'];

            // use model helper to resolve product.sales_price via subject_pace
            $price = (float)$this->po->resolve_price($subjectId, $paceNo, $branchId);

            $it['unit_price'] = $price;   // view expects unit_price
            $it['price']      = $price;   // legacy alias, just in case
        }

        $it['line_total'] = number_format($qty * (float)$it['unit_price'], 2, '.', '');
    }
    unset($it);
    // --- END hydrate ---

    $this->data['title']     = 'Edit PACE Order';
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/orders_batches/edit';

    $this->load->view('layout/index', $this->data);
}


// Save edits (optionally mark as checked)
// Save edits (optionally CHECK & INVOICE in one click)
public function orders_batch_update()
{
    if (!$this->is_finance_or_admin()) return access_denied();

    $order_id = (int)$this->input->post('order_id');
    $items    = $this->input->post('items');

    // NEW: when the green button "Check & Invoice" is clicked
    $do_check_invoice = (bool)$this->input->post('btn_check_invoice');
    $checker_note     = (string)$this->input->post('checker_note');

    if ($order_id <= 0 || !is_array($items)) {
        set_alert('error', 'Nothing to save.');
        return redirect('pace/orders_batches_pre');
    }

    $this->db->trans_start();

    foreach ($items as $itemId => $row) {
        $item_id = (int)$itemId;
        $qty     = isset($row['qty']) ? (int)$row['qty'] : 0;
        $redo    = !empty($row['is_redo']) ? 1 : 0;
        $desc    = isset($row['description']) ? trim($row['description']) : '';

        if ($qty <= 0) {
            $this->po->delete_item($order_id, $item_id);
            continue;
        }
        $this->po->save_item_qty($order_id, $item_id, $qty, $redo, $desc);
    }

    // If normal "Mark Checked" was pressed (legacy button)
    if ($this->input->post('mark_checked')) {
        $this->po->mark_checked($order_id, $checker_note);
    }

    $this->db->trans_complete();

    // ===== NEW: Check & Invoice one-shot =====
    if ($do_check_invoice) {
        // 1) Mark the order CHECKED
        $this->po->mark_checked($order_id, $checker_note);

        // 2) Create a FINAL (non-draft) invoice
        //    Requires Pace_orders_model::create_invoice_from_order($orderId, $finalize=true)
        $invoice_id = $this->pow->check_and_invoice($order_id);

        if ($invoice_id) {
            set_alert('success', 'Order checked and Invoice #'.$invoice_id.' created.');
            redirect('pace/invoice_view/' . $invoice_id);
            return;
        }

        set_alert('error', 'Order checked, but the invoice could not be created.');
        redirect('pace/orders_batch_edit/' . $order_id);
        return;
    }
    // ===== END NEW =====

    // Legacy single action: just create a (draft) invoice from the order
    if ($this->input->post('create_invoice')) {
        // If you keep this path as draft, leave second arg off or pass false.
        $invoice_id = $this->po->create_invoice_from_order($order_id, false);
        if ($invoice_id) {
            set_alert('success', 'Invoice #'.$invoice_id.' created.');
            return redirect('pace/invoices');
        }
        set_alert('error', 'Could not create invoice.');
        return redirect('pace/orders_batches_pre');
    }

    set_alert('success', 'Order updated.');
    return redirect('pace/orders_batch_edit/' . $order_id);
}


// Mark checked (without editing)
public function orders_batch_check($id)
{
    if (!$this->is_finance_or_admin()) return access_denied();

    $order = $this->po->get_order((int)$id);
    if (!$order) show_404();
    if (!$this->po->can_edit($order)) {
        set_alert('error', 'Cannot check this order at its current stage.');
        return redirect('pace/orders_batches_pre');
    }
    $this->po->mark_checked((int)$id, get_loggedin_user_id(), true, null);
    set_alert('success', 'Order marked as CHECKED. You can now create the invoice.');
    return redirect('pace/orders_batches_pre');
}

// Uncheck (only before invoice exists)
public function orders_batch_uncheck($id)
{
    if (!$this->is_finance_or_admin()) return access_denied();

    $order = $this->po->get_order((int)$id);
    if (!$order) show_404();
    if (!empty($order['invoice_id'])) {
        set_alert('error', 'Cannot uncheck after an invoice has been created.');
        return redirect('pace/orders_batches_pre');
    }
    $this->po->mark_checked((int)$id, get_loggedin_user_id(), false, null);
    set_alert('success', 'Order unchecked.');
    return redirect('pace/orders_batches_pre');
}

// Create an invoice from a checked pre-invoice order
public function orders_batch_create_invoice($id)
{
    if (!$this->is_finance_or_admin()) return access_denied();

    $order = $this->po->get_order((int)$id);
    if (!$order) show_404();

    if ((int)$order['is_checked'] !== 1) {
        set_alert('error', 'This order must be CHECKED by Finance/Admin before creating an invoice.');
        return redirect('pace/orders_batches_pre');
    }
    if (!empty($order['invoice_id'])) {
        set_alert('error', 'Invoice already created for this order.');
        return redirect('pace/orders_batches_pre');
    }

// 1) Create invoice header
$this->db->insert('hs_academy_invoices', [
    'branch_id'  => (int)$order['branch_id'],
    'student_id' => (int)$order['student_id'],
    'session_id' => (int)$order['session_id'],
    'status'     => '3',                
    'total'      => 0.00,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),      // keep header fresh
]);

    $invoice_id = (int)$this->db->insert_id();

    // 2) Create invoice items from order items
    $items = $this->po->get_order_items((int)$id);
    $total = 0.00;
    foreach ($items as $it) {
        $line_total = (float)$it['qty'] * (float)$it['unit_price'];
        $total += $line_total;

        $this->db->insert('hs_academy_invoice_items', [
            'invoice_id'  => $invoice_id,
            'sap_id'      => (int)$it['sap_id'],
            'subject_id'  => (int)$it['subject_id'],
            'pace_number' => $it['pace_number'],
            'pace_no'     => (int)$it['pace_no'],
            'qty'         => (int)$it['qty'],
            'unit_price'  => (float)$it['unit_price'],
            'line_total'  => $line_total,
            'is_redo'     => (int)$it['is_redo'],
            'description' => $it['description'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    // 3) Update invoice total and link back to order
$this->db->where('id', $invoice_id)->update('hs_academy_invoices', ['total' => $total, 'updated_at' => date('Y-m-d H:i:s')]);
$this->po->link_invoice((int)$id, $invoice_id);

// NEW: reflect order header as billed once invoice exists
$this->db->where('id', (int)$id)->update('pace_orders', [
    'status'     => 'billed',
    'updated_at' => date('Y-m-d H:i:s'),
]);
}

public function orders_batches_pre()
{
    // If this check sends you to a 404, comment it out temporarily to verify the page.
    if (!$this->is_finance_or_admin()) {
        // return access_denied(); // <- uncomment again later
    }

    $this->load->model('Pace_orders_model', 'po');

    $this->data['title']     = 'PACE Order Batches (Pre-Invoice)';
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/orders_batches/index_simple'; // minimal, no JS
    $this->data['batches']   = $this->po->get_batches_pre();       // <- correct method

    $this->load->view('layout/index', $this->data);
}

// /pace/invoice_mark_issued/1011
public function invoice_mark_issued($invoice_id = null)
{
    if (!$invoice_id || !ctype_digit((string)$invoice_id)) show_404();
    if (!is_superadmin_loggedin() && !is_admin_loggedin()) access_denied();

    $this->load->model('Pace_order_workflow_model', 'pow');

    $ok = $this->pow->mark_invoice_issued(
        (int)$invoice_id,
        get_loggedin_branch_id(),
        get_loggedin_user_id()
    );

    set_alert($ok ? 'success' : 'error', $ok ? 'Invoice marked as ISSUED.' : 'Could not mark invoice as issued.');
    redirect(base_url('pace/orders_batches?status=all'));
}


/* Buttons in the Pre-Invoice list (operate on the order first, sync invoice if linked) */
public function orders_batch_mark_paid($order_id)
{
    if (!$this->is_finance_or_admin()) return access_denied();
    $ok = $this->po->order_mark_paid((int)$order_id);
    set_alert($ok ? 'success' : 'error', $ok ? 'Order marked PAID.' : 'Could not mark order paid.');
    redirect('pace/orders_batches_pre');
}

public function orders_batch_mark_issued($order_id)
{
    if (!$this->is_finance_or_admin()) return access_denied();
    $ok = $this->po->order_mark_issued((int)$order_id);
    set_alert($ok ? 'success' : 'error', $ok ? 'Order marked ISSUED.' : 'Could not mark order issued.');
    redirect('pace/orders_batches_pre');
}

// mark a pre-invoice order as paid
public function orders_mark_paid($order_id = null)
{
    if (!$order_id || !is_numeric($order_id)) {
        show_404();
    }

    // permissions: tweak to your helpers/roles
    if (!is_superadmin_loggedin() && !is_admin_loggedin()) {
        access_denied();
    }

    $this->load->model('Pace_order_workflow_model', 'pow');

    $ok = $this->pow->mark_order_paid((int)$order_id, get_loggedin_user_id());

    if ($ok) {
        set_alert('success', 'Order marked as PAID.');
    } else {
        set_alert('error', 'Could not mark order as paid (already paid or not found).');
    }

    // send back to the batches list (adjust if your listing URL differs)
    redirect(base_url('pace/orders_batches?status=all'));
}

public function check_and_bill($order_id)
{
    if (!is_loggedin()) show_error('Unauthorized', 401);
    $this->load->model('Pace_orders_model', 'pom');

    [$ok, $res] = $this->pom->check_and_bill((int)$order_id, (int)get_loggedin_user_id());
    if (!$ok) {
        $this->session->set_flashdata('error', $res ?: 'Failed to bill order');
    } else {
        $this->session->set_flashdata('success', 'Order checked and invoice #'.$res.' created');
    }
    redirect(site_url('pace/orders_batches'));
}

// -----------------------------------------------------------------------------
// helper to choose the first existing table name from a list
private function pickTable(array $candidates)
{
    foreach ($candidates as $t) {
        if ($this->db->table_exists($t)) return $t;
    }
    return null;
}

// -----------------------------------------------------------------------------
// /pace/invoice_view/{id}
// ======= NEW: Single invoice view (campus guard) =======
public function invoice_view($invoice_id = 0)
{
    // allow only campus roles (same guard you already use elsewhere)
    $role_id = (int) $this->session->userdata('loggedin_role_id');
    $allowed = [1, 2, 4, 8]; // superadmin, admin, principal, finance (adjust if needed)
    if (!in_array($role_id, $allowed, true)) {
        return access_denied();
    }

    $invoice_id = (int) $invoice_id;
    $branch_id  = (int) get_loggedin_branch_id();

    // --- Header (strict campus guard) ---
    $inv = $this->db->select("inv.*, CONCAT_WS(' ', st.first_name, st.last_name) AS student_name", false)
        ->from('hs_academy_invoices AS inv')
        ->join('student AS st', 'st.id = inv.student_id', 'left')
        ->where('inv.id', $invoice_id)
        ->where('inv.branch_id', $branch_id)
        ->get()->row_array();

    if (!$inv) {
        return access_denied(); // or show_404();
    }

    // --- Lines (with subject + pace number) ---
    $items = $this->db->select("hii.*, subj.name AS subject_name, sap.pace_number", false)
        ->from('hs_academy_invoice_items AS hii')
        ->join('student_assign_paces AS sap', 'sap.id = hii.sap_id', 'left')
        ->join('subject AS subj', 'subj.id = hii.subject_id', 'left')
        ->where('hii.invoice_id', $invoice_id)
        ->order_by('subj.name', 'ASC')
        ->get()->result_array();

    // keep existing layout flow
    $this->data['title']     = 'Invoice #'.$invoice_id;
    $this->data['main_menu'] = 'pace';
    $this->data['sub_page']  = 'pace/invoice_view';
    $this->data['invoice']   = $inv;
    $this->data['items']     = $items;

    $this->load->view('layout/index', $this->data);
}

// -----------------------------------------------------------------------------
// POST /pace/invoice_mark_paid/{id}
public function invoice_mark_paid($id)
{
    $id   = (int)$id;
    $now  = date('Y-m-d H:i:s');
    $tbl  = $this->pickTable(['hs_academy_invoices','invoices','invoice']);
    if (!$tbl) show_error('Invoice table not found', 500);

    // normalise: map label→numeric or string depending on column type
    $row  = $this->db->query("SHOW COLUMNS FROM {$tbl} LIKE 'status'")->row_array();
    $isInt = $row && stripos((string)$row['Type'], 'int') !== false;
    $paidVal = $isInt ? 1 : 'paid';

    $upd = ['status' => $paidVal, 'updated_at' => $now];
    if ($this->db->field_exists('paid_at', $tbl)) $upd['paid_at'] = $now;

    $this->db->where('id', $id)->update($tbl, $upd);

    // if you already have a model method that stamps SAP etc., call it here:
    if (method_exists($this->Pace_orders_model, 'invoice_mark_paid')) {
        $this->Pace_orders_model->invoice_mark_paid($id);
    }

    redirect('pace/invoice_view/'.$id);
}

}
