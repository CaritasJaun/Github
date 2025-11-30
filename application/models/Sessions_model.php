<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Sessions_model extends MY_Model
{
    public function searchStudentActiveSession($student_id, $session_id = null)
    {
        $session_id = empty($session_id) ? get_session_id() : $session_id;
        $this->db->select('*')->from('enroll');
        $this->db->where('student_id', $student_id);
        $this->db->where('session_id', $session_id);
        $this->db->order_by('id');
        $query = $this->db->get();
        return $query->row();
    }
}	
