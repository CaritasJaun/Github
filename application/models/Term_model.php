<?php
class Term_model extends CI_Model
{
    public function get_all_terms()
    {
        return $this->db->order_by('start_date')->get('terms')->result();
    }

    public function get_term($id)
    {
        return $this->db->get_where('terms', ['id' => $id])->row();
    }

    public function save_term($data)
    {
        $insert = [
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
        ];

        if (!empty($data['id'])) {
            $this->db->where('id', $data['id'])->update('terms', $insert);
        } else {
            $this->db->insert('terms', $insert);
        }
    }
}
