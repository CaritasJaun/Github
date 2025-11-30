<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notification_model extends CI_Model
{
    private $table = 'notifications';
    private $hasIsRead = null; // cache schema detection
    private $hasStatus = null;

    private function detectSchema()
    {
        if ($this->hasIsRead === null) {
            $this->hasIsRead = $this->db->field_exists('is_read', $this->table);
            $this->hasStatus = $this->db->field_exists('status',  $this->table);
        }
    }

    /**
     * Fetch notifications for a user.
     * $only_unread = true returns only unread based on available schema.
     */
    public function get_notifications_for_user($receiver_id, $only_unread = true, $limit = 10)
    {
        $this->detectSchema();

        $this->db->from($this->table);
        $this->db->where('receiver_id', (int)$receiver_id);

        if ($only_unread) {
            if ($this->hasIsRead) {
                $this->db->where('is_read', 0);
            } elseif ($this->hasStatus) {
                $this->db->where('status', 'unread');
            }
        }

        // prefer created_at if present, else id
        if ($this->db->field_exists('created_at', $this->table)) {
            $this->db->order_by('created_at', 'DESC');
        } else {
            $this->db->order_by('id', 'DESC');
        }

        $this->db->limit((int)$limit);
        return $this->db->get()->result_array();
    }

    /**
     * Count unread notifications for a user.
     * Uses only the column that exists (no OR mixing).
     */
    public function count_unread($receiver_id)
{
    $this->detectSchema();

    $this->db->from($this->table);
    $this->db->where('receiver_id', (int)$receiver_id);

    if ($this->hasIsRead) {
        $this->db->where('is_read', 0);
    } elseif ($this->hasStatus) {
        // treat anything not 'read' (incl. NULL, '', 'new', 'unread') as unread
        $this->db->group_start()
                 ->where('status !=', 'read')
                 ->or_where('status IS NULL', null, false)
                 ->or_where('status', '')
                 ->or_where('status', 'new')
                 ->or_where('status', 'unread')
                 ->group_end();
    } else {
        return 0;
    }

    return (int)$this->db->count_all_results();
}

public function mark_all_read($receiver_id)
{
    $this->detectSchema();

    if (!$this->hasIsRead && !$this->hasStatus) return false;

    $data = ['read_at' => date('Y-m-d H:i:s')];
    if ($this->hasIsRead) $data['is_read'] = 1;
    if ($this->hasStatus) $data['status']  = 'read';

    $this->db->where('receiver_id', (int)$receiver_id);

    if ($this->hasIsRead) $this->db->where('is_read', 0);
    if ($this->hasStatus) {
        $this->db->group_start()
                 ->where('status !=', 'read')
                 ->or_where('status IS NULL', null, false)
                 ->or_where('status', '')
                 ->or_where('status', 'new')
                 ->or_where('status', 'unread')
                 ->group_end();
    }

    return $this->db->update($this->table, $data);
}

}
