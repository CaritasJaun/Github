<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Parents_model extends MY_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    // moderator parents all information
    public function save($data, $getBranch = array())
    {
// in save()
$inser_data1 = array(
    'branch_id'        => $this->application_model->get_branch_id(),
    'name'             => $data['name'],
    'relation'         => $data['relation'],
    'father_name'      => $data['father_name'],
    'mother_name'      => $data['mother_name'],
    'father_email'     => isset($data['father_email']) ? $data['father_email'] : null,
    'mother_email'     => isset($data['mother_email']) ? $data['mother_email'] : null,
    'father_mobileno'  => isset($data['father_mobileno']) ? $data['father_mobileno'] : null,
    'mother_mobileno'  => isset($data['mother_mobileno']) ? $data['mother_mobileno'] : null,
    'email'            => isset($data['email']) ? $data['email'] : null,    // ← add this line
    'address'          => $data['address'],
    'city'             => $data['city'],
    'state'            => $data['state'],
    'photo'            => $this->uploadImage('parent'),
    'facebook_url'     => $data['facebook'],
    'linkedin_url'     => $data['linkedin'],
    'twitter_url'      => $data['twitter'],
);

        
        if (!isset($data['parent_id']) && empty($data['parent_id'])) {
            // save employee information in the database
            $this->db->insert('parent', $inser_data1);
            $parent_id = $this->db->insert_id();
            // save guardian login credential information in the database
            if ($getBranch['grd_generate'] == 1) {
                $username = $getBranch['grd_username_prefix'] . $parent_id;
                $password = $getBranch['grd_default_password'];
            } else {
                $username = $data['username'];
                $password = $data['password'];
            }

            $inser_data2 = array(
                'username' => $username,
                'role' => 6,
                'active' => 1,
                'user_id' => $parent_id,
                'password' => $this->app_lib->pass_hashed($password),
            );
            $this->db->insert('login_credential', $inser_data2);
            
            // send account activate email
            $emailData = array(
                'name' => $data['name'],
                'username' => $username,
                'password' => $password,
                'user_role' => 6,
                'email' => $data['email'],
            );
            $this->email_model->sentStaffRegisteredAccount($emailData);
            return $parent_id;
        } else {
            $this->db->where('id', $data['parent_id']);
            $this->db->update('parent', $inser_data1);
            // update login credential information in the database
            $this->db->where(array('role' => 6, 'user_id' => $data['parent_id']));
            $this->db->update('login_credential', array('username' => $data['username']));
        }

        if ($this->db->affected_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

   public function getSingleParent($id)
{
    $this->db->select('parent.*,login_credential.role as role_id,login_credential.active,login_credential.username,login_credential.id as login_id, roles.name as role');
    $this->db->from('parent');
    $this->db->join('login_credential', 'login_credential.user_id = parent.id and login_credential.role = "6"', 'left'); // ← inner → left
    $this->db->join('roles', 'roles.id = login_credential.role', 'left');
    $this->db->where('parent.id', $id);
    if (!is_superadmin_loggedin()) {
        $this->db->where('parent.branch_id', get_loggedin_branch_id());
    }
    $query = $this->db->get();
    if ($query->num_rows() == 0) {
        show_404();
    }
    return $query->row_array();
}

    public function childsResult($parent_id)
    {
        $this->db->select('s.id,s.photo, CONCAT_WS(" ",s.first_name, s.last_name) as fullname,c.name as class_name,se.name as section_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'inner');
        $this->db->join('login_credential as l', 'l.user_id = s.id and l.role = 7', 'inner');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id=se.id', 'left');
        $this->db->where('s.parent_id', $parent_id);
        $this->db->where('l.active', 1);
        $this->db->where('e.session_id', get_session_id());
        return $this->db->get()->result_array();
    }

    // get parent all details
   public function getParentList($branchID = null, $active = 1)
{
    $this->db->select('parent.*, COALESCE(login_credential.active, parent.active) as active'); // keep "active" for the view
    $this->db->from('parent');
    // LEFT JOIN so parents without accounts are still listed
    $this->db->join('login_credential',
        'login_credential.user_id = parent.id and login_credential.role = "6"',
        'left'
    );

    // filter on parent's own active flag
    if ($active !== null) {
        $this->db->where('parent.active', (int)$active);
    }
    if (!empty($branchID)) {
        $this->db->where('parent.branch_id', $branchID);
    }

    $this->db->group_by('parent.id'); // avoid dupes if a parent has multiple logins (shouldn’t, but safe)
    $this->db->order_by('parent.id', 'ASC');
    return $this->db->get()->result();
}
    
    public function getStudentsForAttach($branch_id, $parent_id)
{
    $branch_id = (int)$branch_id;
    $parent_id = (int)$parent_id;

    $this->db->select('s.id, s.register_no, CONCAT_WS(" ", s.first_name, s.last_name) AS full_name');
    $this->db->from('student AS s');
    $this->db->where('s.branch_id', $branch_id);
    // Exclude students already linked to this parent
    $this->db->group_start()
        ->where('s.parent_id IS NULL', null, false)
        ->or_where('s.parent_id !=', $parent_id)
    ->group_end();
    $this->db->order_by('s.first_name', 'asc');
    return $this->db->get()->result_array();
}
public function attachChildren($parent_id, array $student_ids)
{
    $parent_id = (int)$parent_id;
    if ($parent_id <= 0 || empty($student_ids)) return false;

    // Normalize IDs
    $ids = array_values(array_unique(array_map('intval', $student_ids)));
    if (empty($ids)) return false;

    $this->db->trans_start();
    // Link by setting the same parent_id (this is what makes them siblings in Ramom)
    $this->db->where_in('id', $ids)->update('student', ['parent_id' => $parent_id]);
    $this->db->trans_complete();

    return $this->db->trans_status();
}

}
