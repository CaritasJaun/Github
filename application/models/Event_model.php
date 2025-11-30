<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Event_model extends MY_Model
{
    const COLOR_CLASS_HIGHLIGHT = '#16a34a';
    const COLOR_DEFAULT         = '#0ea5e9';
    const COLOR_HOLIDAY         = '#ef4444';

    public function __construct()
    {
        parent::__construct();
    }

    /* ---------- SAVE ---------- */
    public function save($data = array())
    {
        // make sure created_by will always be a STAFF id
        $created_by_staff = $this->resolve_staff_id(get_loggedin_user_id());

        $arrayEvent = array(
            'branch_id'     => $data['branch_id'],
            'title'         => $this->input->post('title'),
            'remark'        => $this->input->post('remarks'),
            'type'          => $data['type'],
            'audition'      => $data['audition'],
            'image'         => $data['image'],
            'show_web'      => (isset($_POST['show_website']) ? 1 : 0),
            'selected_list' => $data['selected_list'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'all_day'       => isset($data['all_day']) ? (int)$data['all_day'] : 1,
            'start_time'    => isset($data['start_time']) ? $data['start_time'] : null,
            'end_time'      => isset($data['end_time']) ? $data['end_time'] : null,
            'status'        => 1,
        );

        if (isset($data['id']) && !empty($data['id'])) {
            $this->db->where('id', $data['id'])->update('event', $arrayEvent);
        } else {
            $arrayEvent['created_by'] = $created_by_staff ?: 0;
            $arrayEvent['session_id'] = get_session_id();
            $this->db->insert('event', $arrayEvent);
        }
    }

    /* ---------- FEED FOR CALENDAR ---------- */
    public function fetch_for_calendar($user_id, $role_id, $class_id, $section_id, $start, $end)
{
    // normalize dates
    $start = date('Y-m-d', strtotime($start));
    $end   = date('Y-m-d', strtotime($end));

    $this->db->reset_query();
    $this->db->from('event e');
    $this->db->where('e.status', 1);

    if (!is_superadmin_loggedin()) {
        $this->db->where('e.branch_id', get_loggedin_branch_id());
    }

    // overlap window: (start_date <= end) AND (end_date >= start)
    $this->db->where('e.start_date <=', $end);
    $this->db->where('e.end_date >=', $start);

    if ((int)$role_id === 3) {
        // get teacher classes; if none, use the class passed in by controller
        $teacherClassIDs = (array) $this->teacher_class_ids((int)$user_id);
        if (empty($teacherClassIDs) && (int)$class_id > 0) {
            $teacherClassIDs = [(int)$class_id];
        }

        // helper to build a safe REGEXP for JSON arrays (quoted or numeric)
        // matches: [9,10], ["9","10"], [,9,], [9], etc.
        $tokenRe = function ($token) {
            $token = (int) $token;
            // (^|,|\[)\s*"?9"?\s*(,|\]|$)
            return "(^|,|\\[)\\s*\"?{$token}\"?\\s*(,|\\]|$)";
        };

        $this->db->group_start()
            // everybody
            ->where('e.audition', 1)
            // or my own events
            ->or_where('e.created_by', (int)$user_id);

        // class-targeted events that include ANY of my classes
        if (!empty($teacherClassIDs)) {
            $this->db->or_group_start()
                ->where('e.audition', 2)
                ->group_start();
                    foreach ($teacherClassIDs as $cid) {
                        $re = $tokenRe($cid);
                        // use "where" with FALSE escape to keep REGEXP literal
                        $this->db->or_where("e.selected_list REGEXP '{$re}'", null, false);
                    }
                $this->db->group_end()
            ->group_end();

            // section-targeted events: tokens look like "classId-sectionId"
            $this->db->or_group_start()
                ->where('e.audition', 3)
                ->group_start();

                    // if a specific section is known, match exactly "c-s"
                    if ((int)$section_id > 0) {
                        foreach ($teacherClassIDs as $cid) {
                            $re = $tokenRe($cid . '-' . (int)$section_id);
                            $this->db->or_where("e.selected_list REGEXP '{$re}'", null, false);
                        }
                    } else {
                        // otherwise match any section for my classes ("c-" prefix)
                        foreach ($teacherClassIDs as $cid) {
                            // (^|,|\[)\s*"?9-    (anything up to , ] or end)
                            $re = "(^|,|\\[)\\s*\"?{$cid}-";
                            $this->db->or_where("e.selected_list REGEXP '{$re}'", null, false);
                        }
                    }

                $this->db->group_end()
            ->group_end();
        }

        $this->db->group_end(); // end teacher visibility group
    }

    $this->db->order_by('e.start_date', 'ASC');
    $query = $this->db->get();
    if (!$query) return [];

    $rows = $query->result_array();
    $out  = [];

    foreach ($rows as $r) {
        $all_day = isset($r['all_day']) ? ((int)$r['all_day'] === 1) : 1;

        if ($all_day) {
            $startISO = $r['start_date'];
            $endISO   = date('Y-m-d', strtotime($r['end_date'] . ' +1 day')); // allDay end is exclusive
        } else {
            $st = $r['start_time'] ?: '00:00:00';
            $et = $r['end_time']   ?: $st;
            $startISO = $r['start_date'] . 'T' . $st;
            $endISO   = $r['end_date']   . 'T' . $et;
        }

        // color & labels
        $color     = null;
        $textColor = null;

        if ($r['type'] === 'holiday') {
            $color     = '#ef4444';
            $textColor = '#ffffff';
        } else {
            $tcol = get_type_name_by_id('event_types', $r['type'], 'color');
            if (!empty($tcol)) {
                $color     = $tcol;
                $textColor = $this->autoTextColor($tcol);
            } elseif ((int)$r['audition'] === 2) {
                $color     = '#16a34a'; // highlight for class
                $textColor = '#ffffff';
            }
        }

        $typeLabel = ($r['type'] === 'holiday')
            ? translate('holiday')
            : get_type_name_by_id('event_types', $r['type']);

        $audMap = [1 => translate('everybody'), 2 => translate('class'), 3 => translate('section')];
        $audLbl = $audMap[(int)$r['audition']] ?? translate('everybody');

        $evt = [
            'id'     => (int)$r['id'],      // FullCalendar doesn’t require a non-zero id; 0 is fine
            'title'  => $r['title'],
            'start'  => $startISO,
            'end'    => $endISO,
            'allDay' => $all_day,
            'color'     => $color,
            'textColor' => $textColor,
            'extendedProps' => [
                'type'     => $typeLabel,
                'audience' => $audLbl,
                'remark'   => $r['remark'] ?? '',
            ],
        ];

        $out[] = $evt;
    }

    return $out;
}

    /* ---------- HELPERS ---------- */

    /**
     * Accept either a staff.id or a users.id and return staff.id.
     */
    private function resolve_staff_id(int $anyId): int
    {
        if ($anyId <= 0) return 0;

        // 1) try as staff.id
        $q = $this->db->select('id')->from('staff')->where('id', $anyId)->limit(1)->get();
        if ($q && $q->num_rows() === 1) {
            return (int)$q->row()->id;
        }

        // 2) try as users.id mapped via staff.login_id
        $q = $this->db->select('id')->from('staff')->where('login_id', $anyId)->limit(1)->get();
        if ($q && $q->num_rows() === 1) {
            return (int)$q->row()->id;
        }

        return 0;
    }

    /**
     * Distinct class ids assigned to a staff member.
     */
    private function teacher_class_ids(int $staff_id): array
    {
        if ($staff_id <= 0) return array();
        $ids = array();

        if ($this->db->table_exists('teacher_allocation')) {
            $q = $this->db->select('DISTINCT class_id', false)
                          ->from('teacher_allocation')
                          ->where('teacher_id', $staff_id)->get();
            if ($q) foreach ($q->result_array() as $r) if (!empty($r['class_id'])) $ids[] = (int)$r['class_id'];
        }

        if ($this->db->table_exists('class_teacher')) {
            $q = $this->db->select('DISTINCT class_id', false)
                          ->from('class_teacher')
                          ->where('staff_id', $staff_id)->get();
            if ($q) foreach ($q->result_array() as $r) if (!empty($r['class_id'])) $ids[] = (int)$r['class_id'];
        }

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

    /* legacy utilities kept for compatibility */
    private function is_all_day($start, $end): bool
    {
        $s = $this->to_dt($start);
        $e = $this->to_dt($end);
        if (!$e) return $this->looks_like_date_only($start);
        if ($this->looks_like_date_only($start) && $this->looks_like_date_only($end)) return true;
        return false;
    }
    private function looks_like_date_only($value): bool
    {
        if (empty($value)) return true;
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$value));
    }
    private function normalize_datetime($value, string $pos = 'start'): ?string
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof DateTime) return $value->format('Y-m-d H:i:s');
        $str = trim((string)$value);
        if ($this->looks_like_date_only($str)) return $pos === 'end' ? $str.' 23:59:59' : $str.' 00:00:00';
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $str)) $str .= ':00';
        $ts = strtotime($str); if ($ts === false) return null;
        return date('Y-m-d H:i:s', $ts);
    }
    private function iso8601($value, string $pos = 'start'): ?string
    {
        if ($value === null || $value === '') return null;
        $dt = $this->normalize_datetime($value, $pos);
        if (!$dt) return null;
        $ts = strtotime($dt); if ($ts === false) return null;
        return date('c', $ts);
    }
    private function to_dt($value): ?DateTime
    {
        if (empty($value)) return null;
        if ($value instanceof DateTime) return $value;
        $ts = strtotime((string)$value); if ($ts === false) return null;
        return (new DateTime())->setTimestamp($ts);
    }
}
