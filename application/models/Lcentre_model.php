class Lcentre_model extends CI_Model
{
    /* 2-a  student list (unchanged) */
    public function get_all_students()
    {
        return $this->db->select('id,register_no,firstname,lastname')
                        ->order_by('register_no')
                        ->get('student')->result_array();
    }

    /* 2-b  progress data pulled straight from student_pace_assign */
    public function get_progress_data($student_id, $quarters = [])
    {
        $this->db->select('
            subj.name         AS subject_name,
            subj.grade        AS grade,
            a.pace_number     AS pace,
            a.term,                      /* Q1…Q4 */
            COALESCE(a.score,0) AS score
        ');
        $this->db->from('student_pace_assign a');
        $this->db->join('subject subj','subj.id = a.subject_id');
        $this->db->where('a.student_id',$student_id);

        if ($quarters) {          // e.g. ['Q1','Q2']
            $this->db->where_in('a.term',$quarters);
        }

        $this->db->order_by('subj.id,a.pace_number');
        $rows = $this->db->get()->result_array();

        /* bucket rows by Subject + Grade */
        $out = [];
        foreach ($rows as $r){
            $k = "{$r['subject_name']} Grade {$r['grade']}";
            $out[$k][] = [
                'pace'  => (int)$r['pace'],
                'term'  => $r['term'],     // Qx
                'score' => (float)$r['score'],
            ];
        }
        return $out;
    }

    /* 2-c  assign-PACE with stock check */
    public function assign_pace($data)   // $data = student_id, subject_id, pace, term
    {
        // (i) inventory check
        if(!$this->inventory_model->check_and_reserve(
               $data['subject_id'],$data['pace_number'])) return false;

        // (ii) create row
        $data['status'] = 'assigned';
        $this->db->insert('student_pace_assign',$data);
        return true;
    }

    /* 2-d  save / update score */
    public function save_score($student_id,$subject_id,$pace,$term,$score,$grader_id)
    {
        $row = $this->db->get_where('student_pace_assign',[
            'student_id'  => $student_id,
            'subject_id'  => $subject_id,
            'pace_number' => $pace
        ])->row_array();

        $payload = [
            'term'        => $term,
            'score'       => $score,
            'status'      => 'completed',
            'scored_date' => date('Y-m-d'),
            'grader_id'   => $grader_id
        ];

        if($row){
            $this->db->where('id',$row['id'])->update('student_pace_assign',$payload);
        }else{
            $payload += [
                'student_id'=>$student_id,
                'subject_id'=>$subject_id,
                'pace_number'=>$pace
            ];
            $this->db->insert('student_pace_assign',$payload);
        }
    }
}
