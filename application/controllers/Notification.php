<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notification extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Used for listing; "mark read" actions are schema-agnostic in this controller
        $this->load->model('notification_model');
    }

    /** Build a safe absolute URL from a possibly-relative path. */
    private function notif_safe_url($u)
    {
        $u = trim((string)$u);
        if ($u === '') return site_url('dashboard');
        if (preg_match('#^(https?:)?//#i', $u)) return $u;   // absolute
        return site_url(ltrim($u, '/'));                     // relative -> absolute
    }

    /** Mark ONE notification as read (supports schemas with status or is_read). */
    private function mark_row_read($id, $user_id)
    {
        $id      = (int)$id;
        $user_id = (int)$user_id;
        if ($id <= 0 || $user_id <= 0) return false;

        $table     = 'notifications';
        $hasIsRead = $this->db->field_exists('is_read', $table);
        $hasStatus = $this->db->field_exists('status',  $table);

        $data = ['read_at' => date('Y-m-d H:i:s')];
        if ($hasIsRead) $data['is_read'] = 1;
        if ($hasStatus) $data['status']  = 'read';

        return (bool)$this->db->where('id', $id)
                              ->where('receiver_id', $user_id)
                              ->update($table, $data);
    }

    /** Mark ALL notifications as read for the user (handles legacy NULL / '' / 'new'). */
    private function mark_all_rows_read($user_id)
{
    $user_id   = (int)$user_id;
    if ($user_id <= 0) return false;

    $table     = 'notifications';
    $hasIsRead = $this->db->field_exists('is_read', $table);
    $hasStatus = $this->db->field_exists('status',  $table);

    if (!$hasIsRead && !$hasStatus) return false;

    $data = ['read_at' => date('Y-m-d H:i:s')];
    if ($hasIsRead) $data['is_read'] = 1;
    if ($hasStatus) $data['status']  = 'read';

    // Unconditional: mark ALL for this receiver as read
    return (bool) $this->db->where('receiver_id', $user_id)
                           ->update($table, $data);
}

/** AJAX: mark all as read */

    // -------- Pages / Endpoints --------

    /** List UNREAD notifications only. */
    public function index()
    {
        $user_id = (int)get_loggedin_user_id();

        $this->data['title']   = translate('notifications');
        $this->data['main_menu'] = 'notification';
        $this->data['sub_page']  = 'notification/index';

        // only UNREAD so items disappear after you open / clear them
        $this->data['notifications'] = $this->notification_model
            ->get_notifications_for_user($user_id, true, 200);

        // avoid notices if layout expects this
        if (!isset($this->data['headerelements'])) {
            $this->data['headerelements'] = ['css' => [], 'js' => []];
        }

        $this->load->view('layout/index', $this->data);
    }

    /** Open one notification: mark read, then redirect to its target (or back to list). */
    public function open($id = 0)
    {
        $user_id  = (int)get_loggedin_user_id();
        $id       = (int)$id;
        $fallback = $this->input->get('return') ?: site_url('notification');

        if ($id <= 0 || $user_id <= 0) return redirect($fallback);

        $row = $this->db->where('id', $id)
                        ->where('receiver_id', $user_id)
                        ->get('notifications')->row_array();

        if (!$row) return redirect($fallback);

        $this->mark_row_read($id, $user_id);

        $target = $this->notif_safe_url($row['url'] ?? '');
        return redirect($target ?: $fallback);
    }

    /** AJAX: mark one as read. */
    public function mark_read($id = 0)
    {
        $user_id = (int)$this->session->userdata('loggedin_user_id');
        $ok = $this->mark_row_read((int)$id, $user_id);

        $this->output->set_content_type('application/json')
                     ->set_output(json_encode(['ok' => (bool)$ok]));
    }

    /** AJAX: mark all as read. */
   public function clear()
{
    // use the same helper used everywhere else
    $user_id = (int) get_loggedin_user_id();
    $ok = $this->mark_all_rows_read($user_id);

    $this->output->set_content_type('application/json')
                 ->set_output(json_encode(['ok' => (bool)$ok]));
}
}
