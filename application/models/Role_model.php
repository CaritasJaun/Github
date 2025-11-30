<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Role_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    function getRoleList()
    {
        $this->db->select('*');
        $this->db->where_not_in('id', array(1,6,7));
        $r = $this->db->get('roles')->result_array();
        return $r;  
    }

    function getModulesList()
    {
        $this->db->order_by('sorted', 'ASC');
        return $this->db->get('permission_modules')->result_array(); 
    }

    // role save and update function
    public function save_roles($data)
    {
        $insertData = array(
            'name' => $data['role'],
            'prefix' => strtolower(str_replace(' ', '', $data['role'])),
        );

        if (!isset($data['id']) && empty($data['id'])) {
            $insertData['is_system'] = 0;
            $this->db->insert('roles', $insertData);
        } else {
            $this->db->where('id', $data['id']);
            $this->db->update('roles', $insertData);
        }
    }

    // check permissions function (dedupe-proof)
public function check_permissions($module_id = '', $role_id = '')
{
    $sql = "
        SELECT  p.*,
                sp.staff_privileges_id,
                sp.is_add, sp.is_edit, sp.is_view, sp.is_delete
        FROM permission AS p
        LEFT JOIN (
            SELECT  permission_id,
                    role_id,
                    MAX(id)        AS staff_privileges_id,
                    MAX(is_add)    AS is_add,
                    MAX(is_edit)   AS is_edit,
                    MAX(is_view)   AS is_view,
                    MAX(is_delete) AS is_delete
            FROM staff_privileges
            GROUP BY permission_id, role_id
        ) AS sp
          ON sp.permission_id = p.id
         AND sp.role_id       = ?
        WHERE p.module_id     = ?
        ORDER BY p.id ASC";
        
    return $this->db->query($sql, [$role_id, $module_id])->result_array();
}
}