<?php /* Progress Report View — GA first column grey; Days Absent header+first col grey; Reading Programme pulls from SPC */ ?>
<style>
  body { background:#fff !important; }

  .school-header { text-align:center; margin-bottom:20px; }
  .school-header img { height:80px; }
  .student-info td { padding:3px 10px; }

  table.report-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
  .report-table th, .report-table td { border:1px solid #000; padding:5px; text-align:center; font-size:11px; }
  .report-table th { background:#e0e0e0; font-weight:bold; }     /* headings bold+grey */
  .report-table tbody td:first-child { font-weight:bold; }       /* row headings bold */
  .subject-row { background:#b0b0b0; font-weight:bold; }

  /* ---- Remove global grey for all body rows; use zebra per-table instead ---- */
  /* .report-table tbody tr:not(.subject-row) td { background:#f7f7f7; }  <-- REMOVE */

  /* ============ Shared zebra pattern (Scripture / Reading / GA) ============ */
  .report-table.zebra thead .subject-row th { background:#e0e0e0 !important; }
  .report-table.zebra tbody tr:nth-child(odd)  td { background:#f5f5f5 !important; }
  .report-table.zebra tbody tr:nth-child(even) td { background:#ffffff !important; }

  /* Scriptures + Reading: bold values (except first column) */
  .report-table.scriptures-table tbody td:not(:first-child),
  .report-table.reading-table   tbody td:not(:first-child) { font-weight:700; }
  .report-table.reading-table   tbody td:not(:first-child) { text-align:center; }

  /* ===== General Assignments: FIRST COLUMN grey (overrides zebra for that col) ===== */
  .report-table.ga-table tbody td:first-child { background:#eaeaea !important; font-weight:700; }

  /* ===================== Weekly Traits (read-only) ===================== */
  .traits-table thead .subject-row th { background:#e0e0e0 !important; }
  .traits-table th:first-child,
  .traits-table td:first-child {
      background:#e0e0e0 !important;    /* first column grey */
      white-space:nowrap;
      width:68%;
  }
  .traits-legend{
    width:100%;
    text-align:center;
    font-weight:600;
    font-size:12px;
    margin:6px 0 8px;
    letter-spacing:.2px;
  }
  @media print{ .traits-legend{ font-size:11px; } }

  .traits-table td:not(:first-child) {
      background:#ffffff !important;    /* term cells white */
      width:60px;
      text-align:center;
      font-weight:bold;                 /* echoed numbers bold */
  }

  /* Spacer under Reading Programme (kept) */
  #rp-gap { width:100%; height:0; }

  @media print{
      .no-print{display:none;}
      .report-table select,
      .report-table input[type="text"],
      .report-table input[type="number"]{font-size:10px;padding:2px;width:45px;text-align:center;}
      .report-table th,.report-table td{font-size:10px;padding:4px;}
  }

  /* --- pills --- */
  .pillbar{display:flex;gap:8px;justify-content:flex-end;align-items:center;margin:6px 0 0;}
  .pill{display:inline-block;border-radius:14px;padding:4px 10px;font-size:12px;border:1px solid #ddd;}

  /* --- workflow badge styles (use your theme classes if present) --- */
  .wf-bar{display:flex;gap:12px;align-items:center;margin:12px 0;}
  .badge{display:inline-block;padding:4px 10px;border-radius:12px;border:1px solid #ddd;font-size:12px}
  .badge-success{background:#d1fae5;border-color:#10b981;}
  .badge-warning{background:#fef3c7;border-color:#f59e0b;}
  .badge-secondary{background:#f3f4f6;border-color:#9ca3af;}
</style>

<div class="school-header">
    <img src="<?= base_url('uploads/logo.png') ?>" alt="School Logo"><br>
    <h2>Caritas College</h2>
    <h4>Progress Report – <?= $year ?></h4>
    <span style="float:right; font-size:12px;">
        <strong>Grade:</strong> <?= html_escape($grade_label ?? '') ?> &nbsp;&nbsp;
        <strong>Date:</strong> <?= date('d M Y') ?>
    </span>
</div>

<?php if (!empty($parent_locked)): ?>
  <div class="alert alert-info no-print" style="margin:10px 0;">
    This progress report will be available after the principal has approved it.
  </div>
  <?php return; /* stop rendering the rest of the report for parents until approved */ ?>
<?php endif; ?>

<table class="student-info">
    <tr>
      <td colspan="3" style="padding:6px 10px;">
        <span style="font-weight:800; font-size:20px; letter-spacing:.3px;">
            <?= strtoupper(get_type_name_by_id('student', $student_id, 'first_name') . ' ' . get_type_name_by_id('student', $student_id, 'last_name')) ?>
        </span>
      </td>
    </tr>
</table>

<!-- Optional pill counters (Assigned / Completed / Below 80 by first attempt) -->
<?php if (!empty($progress_counters) && is_array($progress_counters)): ?>
  <div class="pillbar no-print" title="Below 80% counts FIRST attempt < 80, even if later redone and passed.">
    <span class="pill">Assigned: <strong><?= (int)$progress_counters['assigned'] ?></strong></span>
    <span class="pill">Completed: <strong><?= (int)$progress_counters['completed'] ?></strong></span>
    <span class="pill">Below 80%: <strong><?= (int)$progress_counters['below80'] ?></strong></span>
  </div>
<?php endif; ?>

<div id="print-button-container" class="no-print" style="text-align:right;margin-bottom:10px;display:none;">
    <button onclick="window.print()" style="padding:5px 15px;">🖨️ Print Report</button>
</div>

<table style="width:100%; margin-top:20px; font-size:11px;"> 
    <tr>
        <td><strong>Symbol Legend:</strong></td>
        <td>A+ = 98–100%</td><td>A = 95–97%</td><td>B = 90–94%</td><td>C = 85–89%</td><td>D = 80–84%</td>
    </tr>
</table>

<table class="report-table">
    <thead>
    <tr>
        <th style="width:30%;">Subject</th>
        <th style="width:10%;">Symbol</th>
        <th style="width:10%;">Avg %</th>
        <th style="width:12.5%;">Term 1</th>
        <th style="width:12.5%;">Term 2</th>
        <th style="width:12.5%;">Term 3</th>
        <th style="width:12.5%;">Term 4</th>
    </tr>
    </thead>
    <tbody>
    <tr class="subject-row">
        <td><strong>Average</strong></td><td colspan="2"></td>
        <?php for ($q=1;$q<=4;$q++):
            $term_total=0;$term_count=0;
            foreach ($subjects as $s){ if (!empty($s['paces']['Q'.$q])){ foreach ($s['paces']['Q'.$q] as $p){ $term_total+=$p['percentage']; $term_count++; } } }
            $term_avg=$term_count?round($term_total/$term_count,2):'-'; ?>
        <td><strong><?= $term_avg ?></strong></td>
        <?php endfor; ?>
    </tr>

    <?php
    // ---------- SUBJECT ORDER ----------
    // Controller already sends $subjects in subject_code ASC — keep that order.
    $orderedSubjects = array_values($subjects);
    // -----------------------------------

    foreach ($orderedSubjects as $idx => $subject): ?>
    <tr class="subject-row">
        <td><?= $subject['name'] ?></td>
        <td><?= $subject['symbol'] ?></td>
        <td><?= $subject['yearly_avg'] ?>%</td>
        <?php for ($q=1;$q<=4;$q++):
            $termKey='Q'.$q;
            $paceCount=isset($subject['paces'][$termKey])?count($subject['paces'][$termKey]):0;
            $avg=($paceCount>0)?round(array_sum(array_column($subject['paces'][$termKey],'percentage'))/$paceCount,2):'-'; ?>
            <td><?= $paceCount>0?$paceCount.' : '.$avg.'%':'-' ?></td>
        <?php endfor; ?>
    </tr>

    <?php $detailClass = ($idx % 2 === 0) ? 'grey' : 'white'; ?>
    <tr class="pace-row <?= $detailClass ?>">
        <td></td><td></td><td></td>
        <?php for ($q=1;$q<=4;$q++): $termKey='Q'.$q; ?>
        <td style="text-align:left;">
            <?php
            if (!empty($subject['paces'][$termKey])) {
                foreach ($subject['paces'][$termKey] as $pace) {
                    $display = $pace['pace_number'].': '.
                               ((isset($pace['s_score']) && $pace['s_score']!==''
                                 && $pace['s_score']!==null) ? $pace['s_score'] : $pace['percentage']).'%';
                    if (isset($pace['m_score']) && $pace['m_score']!=='') {
                        $display .= ' | '.$pace['m_score'].'% (Moderator)';
                    }
                    echo $display.'<br>';
                }
            } else { echo '-'; }
            ?>
        </td>
        <?php endfor; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Days Absent (fixed malformed markup) -->
<table style="width:44%; margin-bottom:25px; font-size:13px; border-collapse:collapse;" border="1">
    <tr style="text-align:center; background:#e0e0e0;">
        <th style="width:20%;">Term</th>
        <th>T1</th><th>T2</th><th>T3</th><th>T4</th>
    </tr>
    <tr style="text-align:center;">
        <td style="background:#eaeaea; font-weight:bold;">Days Absent</td>
        <?php $attendance=$attendance??[]; for($q=1;$q<=4;$q++){ $absent=$attendance["q$q"]??0; echo "<td>{$absent}</td>"; } ?>
    </tr>
</table>

<!-- =================== TWO COLUMNS (TOP): SCRIPTURES & READING =================== -->

<style>
  /* Section headings */
  .section-title{font-weight:700; text-transform:uppercase; margin:6px 0 6px; letter-spacing:.3px;}
  /* Shared zebra pattern */
  .report-table.zebra thead .subject-row th{ background:#e0e0e0 !important; }
  .report-table.zebra tbody tr:nth-child(odd)  td{ background:#f5f5f5 !important; }
  .report-table.zebra tbody tr:nth-child(even) td{ background:#ffffff !important; }
  /* Bold values (except first column) */
  .report-table.scriptures-table  tbody td:not(:first-child){ font-weight:700; }
  .report-table.reading-table     tbody td:not(:first-child){ font-weight:700; text-align:center; }
</style>

<div style="display:flex; gap:20px; justify-content:space-between; margin-top:10px;">
  <!-- LEFT: Scriptures + GA -->
  <div style="flex:1; min-width:0;">

    <?php
    // ------- Scripture Reading data prep (with fallbacks) -------
    $sn = $scripture_notes ?? null;
    if (!$sn && isset($scriptures) && is_array($scriptures)) {
        $sn = [1=>[],2=>[],3=>[],4=>[]];
        for ($q=1; $q<=4; $q++) {
            for ($i=1; $i<=4; $i++) {
                $val = $scriptures["q{$q}"]["s{$i}"] ?? '';
                if (is_array($val)) $val = implode(', ', array_map('strval', $val));
                $val = trim((string)$val);
                if ($val !== '') $sn[$q][] = $val;
            }
        }
    }
    if (!is_array($sn)) $sn = [1=>[],2=>[],3=>[],4=>[]];
    $flatten = function($arr){
        $out = [];
        foreach ((array)$arr as $v) {
            if (is_array($v)) {
                $allBool = true; foreach ($v as $vv){ if(!is_bool($vv)){ $allBool=false; break; } }
                if ($allBool) continue;
                $v = implode(', ', array_map('strval', $v));
            }
            $v = trim((string)$v);
            if ($v !== '') $out[] = $v;
        }
        return array_values($out);
    };
    for ($t=1; $t<=4; $t++) $sn[$t] = $flatten($sn[$t] ?? []);
    $cell = function(array $termArr, int $i){ $v = $termArr[$i-1] ?? ''; return $v === '' ? '&mdash;' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
    ?>

    <h4 class="section-title">Scripture Reading</h4>
    <table class="report-table scriptures-table zebra" style="width:100%; table-layout:fixed;">
      <thead>
        <tr class="subject-row">
          <th style="width:10%;">Term</th>
          <th>Scripture 1</th><th>Scripture 2</th><th>Scripture 3</th><th>Scripture 4</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($q=1; $q<=4; $q++): ?>
        <tr>
          <td>T<?= $q ?></td>
          <td><?= $cell($sn[$q], 1) ?></td>
          <td><?= $cell($sn[$q], 2) ?></td>
          <td><?= $cell($sn[$q], 3) ?></td>
          <td><?= $cell($sn[$q], 4) ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>

    <?php
    // ------- General Assignments data prep (robust rebuild) -------
    $studentId=(int)($student_id ?? 0);
    $sessionId=function_exists('get_session_id')?get_session_id():null;
    $raw=$general_assignments ?? ($ga ?? []);
    $terms=['Q1','Q2','Q3','Q4'];
    $itemsMissing=true;
    foreach($terms as $T){ if(!empty($raw[$T][1]['item']) || !empty($raw[strtolower($T)][1]['item'])){ $itemsMissing=false; break; } }
    if($itemsMissing){
        $tbl=null;
        foreach(['spc_general_assignments','general_assignments'] as $cand){ if($this->db->table_exists($cand)){ $tbl=$cand; break; } }
        if($tbl){
            $pctCol=$this->db->field_exists('percent',$tbl)?'percent':($this->db->field_exists('percentage',$tbl)?'percentage':null);
            $rowCol=$this->db->field_exists('row_index',$tbl)?'row_index':($this->db->field_exists('row_no',$tbl)?'row_no':($this->db->field_exists('position',$tbl)?'position':null));
            $sesCol=$this->db->field_exists('session_id',$tbl)?'session_id':($this->db->field_exists('year',$tbl)?'year':null);
            $this->db->where('student_id',$studentId);
            if($sesCol && $sessionId) $this->db->where($sesCol,$sessionId);
            $this->db->where_in('term',$terms);
            if($rowCol) $this->db->order_by($rowCol,'asc');
            $rows=$this->db->get($tbl)->result_array();
            $rebuilt=[];
            foreach($rows as $r){
                $t=strtoupper((string)$r['term']); $idx=(int)($rowCol?$r[$rowCol]:0);
                if($idx<1) $idx=(isset($rebuilt[$t])?count($rebuilt[$t]):0)+1;
                $rebuilt[$t][$idx]=[
                    'item'=>(string)($r['item'] ?? ''),
                    'percent'=>isset($pctCol,$r[$pctCol]) ? (string)(0+$r[$pctCol]) : '',
                ];
            }
            if($rebuilt) $raw=$rebuilt;
        }
    }
    $GA=[1=>[],2=>[],3=>[]];
    for($i=1;$i<=3;$i++){
        foreach($terms as $T){
            $item=''; $pct='';
            if(isset($raw[$T][$i])){ $item=(string)($raw[$T][$i]['item']??''); $pct=(string)($raw[$T][$i]['percent']??''); }
            elseif(isset($raw[strtolower($T)][$i])){ $item=(string)($raw[strtolower($T)][$i]['item']??''); $pct=(string)($raw[strtolower($T)][$i]['percent']??''); }
            elseif(isset($raw['row'.$i])){ $qk='q'.substr($T,1); $pct=(string)($raw['row'.$i][$qk]??''); }
            $GA[$i][$T]=['item'=>$item,'percent'=>$pct];
        }
    }
    $pp=function($v){ $v=trim((string)$v); return ($v==='')?'':htmlspecialchars($v).'%'; };
    ?>

    <h4 class="section-title" style="margin-top:10px;">General Assignments</h4>
    <table class="report-table ga-table zebra" style="width:100%; table-layout:fixed;">
      <thead>
        <tr class="subject-row"><th>General Assignments</th><th>T1</th><th>T2</th><th>T3</th><th>T4</th></tr>
      </thead>
      <tbody>
      <?php for($i=1;$i<=3;$i++): ?>
        <tr>
          <td style="text-align:left;">Assignment <?= $i ?> – Title</td>
          <?php foreach($terms as $T): ?><td><?= htmlspecialchars($GA[$i][$T]['item'] ?? '') ?></td><?php endforeach; ?>
        </tr>
        <tr>
          <td style="text-align:left;">%</td>
          <?php foreach($terms as $T): ?><td><?= $pp($GA[$i][$T]['percent'] ?? '') ?></td><?php endforeach; ?>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <!-- RIGHT: Reading Programme only -->
  <div style="flex:1; min-width:0;">
    <?php
    $reading = $reading ?? [];
    $needRP = false;
    for ($q = 1; $q <= 4; $q++) {
        $k = 'q' . $q;
        $w = $reading[$k]['wpm']     ?? '';
        $p = $reading[$k]['percent'] ?? ($reading[$k]['percentage'] ?? '');
        $c = $reading[$k]['comp']    ?? ($reading[$k]['comprehension'] ?? '');
        if ($w === '' || $p === '' || $c === '') { $needRP = true; break; }
    }
    if ($needRP) {
        $tbl=null;
        foreach (['spc_reading_program','reading_program','reading_programme'] as $cand) {
            if ($this->db->table_exists($cand)) { $tbl=$cand; break; }
        }
        if ($tbl) {
            $wCol  = $this->db->field_exists('wpm',$tbl) ? 'wpm' : null;
            $pCol  = $this->db->field_exists('percent',$tbl) ? 'percent'
                   : ($this->db->field_exists('percentage',$tbl) ? 'percentage'
                   : ($this->db->field_exists('score',$tbl) ? 'score' : null));
            $cCol  = $this->db->field_exists('comp',$tbl) ? 'comp'
                   : ($this->db->field_exists('comprehension',$tbl) ? 'comprehension'
                   : ($this->db->field_exists('comp_score',$tbl) ? 'comp_score' : null));
            $sesCol= $this->db->field_exists('session_id',$tbl) ? 'session_id'
                   : ($this->db->field_exists('year',$tbl) ? 'year' : null);

            $this->db->where('student_id', (int)$student_id);
            if ($sesCol && isset($sessionId) && $sessionId) $this->db->where($sesCol, $sessionId);
            $this->db->where_in('term', ['Q1','Q2','Q3','Q4']);
            $rows = $this->db->get($tbl)->result_array();

            $reb = [];
            foreach ($rows as $r) {
                $T = strtoupper((string)$r['term']); // Q1..Q4
                $k = 'q'.substr($T,1);               // q1..q4
                $reb[$k] = [
                    'wpm'     => ($wCol && array_key_exists($wCol,$r) && $r[$wCol] !== null) ? (0 + $r[$wCol]) : '',
                    'percent' => ($pCol && array_key_exists($pCol,$r) && $r[$pCol] !== null) ? (0 + $r[$pCol]) : '',
                    'comp'    => ($cCol && array_key_exists($cCol,$r) && $r[$cCol] !== null) ? (0 + $r[$cCol]) : '',
                ];
            }
            if ($reb) $reading = array_replace_recursive($reading, $reb);
        }
    }
    $fmt = function($v){ $v = trim((string)$v); return $v === '' ? '&mdash;' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
    ?>

    <h4 class="section-title">Reading</h4>
    <table class="report-table reading-table zebra" style="width:100%; table-layout:fixed;">
      <thead>
        <tr class="subject-row">
          <th style="width:10%;">Term</th>
          <th>WPM</th>
          <th>%</th>
          <th>Comp. Score</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($q=1;$q<=4;$q++):
          $k='q'.$q; $wpm=$reading[$k]['wpm']??''; $pct=$reading[$k]['percent']??''; $cmp=$reading[$k]['comp']??''; ?>
          <tr>
            <td>T<?= $q ?></td>
            <td class="rp-bold"><?= $fmt($wpm) ?></td>
            <td class="rp-bold"><?= $fmt($pct) ?></td>
            <td class="rp-bold"><?= $fmt($cmp) ?></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= TWO COLUMNS (BOTTOM): TRAITS (READ-ONLY) ================= -->

<style>
  /* First ROW (headers) and first COLUMN (labels) grey; others white + bold numbers */
  .traits-table thead .subject-row th { background:#e0e0e0 !important; }
  .traits-table th:first-child,
  .traits-table td:first-child {
      background:#e0e0e0 !important;
      white-space:nowrap;
      width:68%;
  }
  .traits-table td:not(:first-child) {
      background:#ffffff !important;
      width:60px; text-align:center; font-weight:bold;
  }
  /* Legend styling */
  .traits-legend{
      width:100%; text-align:center; font-weight:600; font-size:12px;
      margin:6px 0 8px; letter-spacing:.2px;
  }
</style>

<?php
$traits_term_avg = isset($traits_term_avg) ? $traits_term_avg : [];
$termAvg = function(string $key, int $t) use ($traits_term_avg) {
    return isset($traits_term_avg[$key][$t]) ? number_format((float)$traits_term_avg[$key][$t], 1) : '';
};

$TRAITS_DEF = [
  'work' => [
    'title' => 'Work Habits',
    'items' => [
      'follow_directions'        => 'Follows directions',
      'works_independently'      => 'Works well independently',
      'does_not_disturb_others'  => 'Does not disturb others',
      'cares_for_materials'      => 'Takes care of materials',
      'completes_work_required'  => 'Completes work required',
      'attaches_completed_work'  => 'Achieves computer assignments',
    ],
  ],
  'personal' => [
    'title' => 'Personal Traits',
    'items' => [
      'establishes_goals'        => 'Ability to establish own goals',
      'reaches_goals'            => 'Successfully reaches goals',
      'displays_flexibility'     => 'Displays flexibility',
      'shows_creativity'         => 'Shows creativity',
      'overall_progress'         => 'Shows overall progress',
      'attitude_to_computers'    => 'Attitude towards computer learning',
    ],
  ],
  'social' => [
    'title' => 'Social Traits',
    'items' => [
      'is_courteous'             => 'Is courteous',
      'gets_along_with_others'   => 'Gets along well with others',
      'exhibits_self_control'    => 'Exhibits self-control',
      'respects_authority'       => 'Shows respect for authority',
      'responds_to_correction'   => 'Responds well to correction',
      'promotes_school_spirit'   => 'Promotes school spirit',
    ],
  ],
];
?>

<!-- Legend (outside PHP) -->
<div class="traits-legend">
  1 = Needs Improvement &nbsp; | &nbsp; 2 = Satisfactory &nbsp; | &nbsp; 3 = Good &nbsp; | &nbsp; 4 = Excellent
</div>

<div style="display:flex; gap:20px; justify-content:space-between; margin-top:10px;">
  <!-- LEFT: Work + Personal (read-only) -->
  <div style="flex:1; min-width:0;">
    <table class="report-table traits-table" style="width:100%;">
      <thead>
        <tr class="subject-row">
          <th>Work Habits</th>
          <?php for($q=1;$q<=4;$q++): ?><th>T<?= $q ?></th><?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($TRAITS_DEF['work']['items'] as $key => $label): ?>
        <tr>
          <td style="text-align:left;"><?= htmlspecialchars($label) ?></td>
          <td><?= $termAvg($key, 1) ?></td>
          <td><?= $termAvg($key, 2) ?></td>
          <td><?= $termAvg($key, 3) ?></td>
          <td><?= $termAvg($key, 4) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <table class="report-table traits-table" style="width:100%;">
      <thead>
        <tr class="subject-row">
          <th>Personal Traits</th>
          <?php for($q=1;$q<=4;$q++): ?><th>T<?= $q ?></th><?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($TRAITS_DEF['personal']['items'] as $key => $label): ?>
        <tr>
          <td style="text-align:left;"><?= htmlspecialchars($label) ?></td>
          <td><?= $termAvg($key, 1) ?></td>
          <td><?= $termAvg($key, 2) ?></td>
          <td><?= $termAvg($key, 3) ?></td>
          <td><?= $termAvg($key, 4) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- RIGHT: Social Traits (read-only) -->
  <div style="flex:1; min-width:0;">
    <table class="report-table traits-table" style="width:100%;">
      <thead>
        <tr class="subject-row">
          <th>Social Traits</th>
          <?php for($q=1;$q<=4;$q++): ?><th>T<?= $q ?></th><?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($TRAITS_DEF['social']['items'] as $key => $label): ?>
        <tr>
          <td style="text-align:left;"><?= htmlspecialchars($label) ?></td>
          <td><?= $termAvg($key, 1) ?></td>
          <td><?= $termAvg($key, 2) ?></td>
          <td><?= $termAvg($key, 3) ?></td>
          <td><?= $termAvg($key, 4) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$role=$this->session->userdata('role');
$can_edit_teacher=in_array($role,['teacher','super_admin']);
$can_edit_principal=in_array($role,['principal','super_admin']);
?>

<div style="margin-top:30px; display:flex; align-items:flex-start; gap:20px;">
    <div style="flex:1;">
        <label><strong>Teacher's Comments:</strong></label><br>
        <textarea id="teacher_comment" name="teacher_comment" style="width:100%; height:40px;" <?= $can_edit_teacher ? '' : 'readonly disabled' ?>><?= htmlspecialchars($comments['teacher_comment'] ?? '') ?></textarea>
    </div>
</div>

<div style="margin-top:30px; display:flex; align-items:flex-start; gap:20px;">
    <div style="flex:1;">
        <label><strong>Principal's Comments:</strong></label><br>
        <textarea id="principal_comment" name="principal_comment" style="width:100%; height:40px;" <?= $can_edit_principal ? '' : 'readonly disabled' ?>><?= htmlspecialchars($comments['principal_comment'] ?? '') ?></textarea>
    </div>
</div>

<div class="no-print" style="margin-top:20px; text-align:left;">
    <button class="btn btn-success" id="save-comments">💾 Save Comments</button>
</div>

<!-- ───────────── NEW: Workflow status + actions (teacher/principal) ───────────── -->
<?php
$wf_status = isset($workflow['status']) ? $workflow['status'] : 'draft';
$wf_class  = ($wf_status==='completed') ? 'badge-success' : (($wf_status==='pending_principal') ? 'badge-warning' : 'badge-secondary');
?>
<div class="no-print wf-bar">
  <span class="badge <?= $wf_class ?>">Status: <?= ucfirst(str_replace('_',' ', $wf_status)) ?></span>

  <?php if (!empty($role_flags['isTeacher']) && $wf_status === 'draft'): ?>
    <button id="btnAssignToPrincipal" class="btn btn-warning btn-sm">Assign to Principal</button>
  <?php endif; ?>

  <?php if (!empty($role_flags['isPrincipal']) && $wf_status === 'pending_principal'): ?>
    <button id="btnMarkCompleted" class="btn btn-success btn-sm">Mark as Completed &amp; Notify Teacher</button>
  <?php endif; ?>

  <?php if (!empty($role_flags['isTeacher']) && $wf_status === 'completed'): ?>
    <button id="btnPrintNow" class="btn btn-primary btn-sm">Print Report</button>
  <?php endif; ?>
</div>
<!-- ────────────────────────────────────────────────────────────────────────────── -->

<div style="margin-top:60px; font-size:13px; border-top:1px solid #000; padding-top:30px;">
    <table style="width:80%; margin:0 auto; border-collapse:collapse; text-align:center;">
        <tr><td colspan="3" style="height:12px;"></td></tr>

        <tr>
            <td style="padding:6px 10px;">
                <strong>Date:</strong>
                <span style="display:inline-block; width:160px; border-bottom:1px solid #000;">&nbsp;</span>
            </td>
            <td style="padding:6px 10px;" colspan="2">
                <strong>School Starts Again:</strong>
                <span style="display:inline-block; width:220px; border-bottom:1px solid #000;">&nbsp;</span>
            </td>
        </tr>

        <tr><td colspan="3" style="height:24px;"></td></tr>

        <tr>
            <td style="padding:6px 10px;">
                <strong>Supervisor:</strong>
                <span style="display:inline-block; width:220px; border-bottom:1px solid #000;">&nbsp;</span>
            </td>
            <td style="padding:6px 10px;">
                <strong>Principal / Administrator:</strong>
                <span style="display:inline-block; width:220px; border-bottom:1px solid #000;">&nbsp;</span>
            </td>
            <td style="padding:6px 10px;">
                <strong>Parent:</strong>
                <span style="display:inline-block; width:220px; border-bottom:1px solid #000;">&nbsp;</span>
            </td>
        </tr>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const principalCommentEl = document.getElementById('principal_comment');
    const printBtnContainer  = document.getElementById('print-button-container');

    // ── NEW: print visibility controlled by workflow "completed"
    const PRINT_ALLOWED = <?= !empty($print_allowed) ? 'true' : 'false' ?>;

    const togglePrintBtn = () => {
        if (!printBtnContainer) return;
        if (PRINT_ALLOWED) { printBtnContainer.style.display = 'block'; return; }
        // fallback (legacy): allow if principal comment typed (for older flows)
        const hasText = (principalCommentEl?.value || '').trim() !== '';
        printBtnContainer.style.display = hasText ? 'block' : 'none';
    };
    togglePrintBtn();
    principalCommentEl?.addEventListener('input', togglePrintBtn);

    document.getElementById('save-comments')?.addEventListener('click', () => {
        const studentId        = <?= json_encode($student_id ?? $selected_student ?? 0) ?>;
        const teacherComment   = document.getElementById('teacher_comment')?.value || '';
        const principalComment = principalCommentEl?.value || '';

        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('teacher_comment', teacherComment);
        formData.append('principal_comment', principalComment);
        formData.append('<?= $this->security->get_csrf_token_name(); ?>', '<?= $this->security->get_csrf_hash(); ?>');

        fetch('<?= base_url("report/save_comments") ?>', { method: 'POST', body: formData })
            .then(res => res.ok ? res.json() : Promise.reject())
            .then(() => { alert('Comments saved successfully.'); togglePrintBtn(); })
            .catch(() => { alert('Failed to save comments.'); });
    });

    // ------------------- TRAITS: APPLY + SAVE -------------------
    const studentId = <?= (int)($student_id ?? $selected_student ?? 0) ?>;

    // Accept both q1..q4 and T1..T4 in the name attribute
    const NAME_RE = /^\s*traits\[(\w+)\]\[(?:q|t)(\d)\]\[([^\]]+)\]\s*$/i;

    function applySavedTraits(data) {
        const selects = document.querySelectorAll('select[name^="traits["]');
        selects.forEach(sel => {
            const name  = sel.getAttribute('name'); // traits[work][q1][abc123]
            const m = name.match(NAME_RE);
            if (!m) return;
            const group = m[1].toLowerCase();     // work | social | personal
            const qKey  = 'q' + m[2];             // normalize to q1..q4
            const hash  = m[3];

            const savedVal =
                data && data[group] && data[group][qKey] && data[group][qKey][hash];

            if (savedVal !== undefined && savedVal !== null && savedVal !== '') {
                sel.value = savedVal;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    // Load saved traits from server and apply
    (function loadTraits() {
        const url = '<?= base_url('report/get_traits') ?>?student_id=' + encodeURIComponent(studentId);
        fetch(url, { method: 'GET', credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(json => applySavedTraits(json || {}))
            .catch(err => {
                console.warn('Traits recall failed:', err);
                // Fail silently; page still usable
            });
    })();

    // SAVE traits (Work Habits / Social Traits / Personal Traits)
    const saveTraitsBtn = document.getElementById('save-traits');
    if (saveTraitsBtn) {
        saveTraitsBtn.addEventListener('click', function () {
            const selects = document.querySelectorAll('select[name^="traits["]');
            const data = {}; // { work: { q1: {hash: val}, q2: {...} }, social: {...}, personal: {...} }

            selects.forEach(sel => {
                const name  = sel.getAttribute('name');
                const m = name.match(NAME_RE);
                if (!m) return;

                const group = m[1].toLowerCase();
                const qKey  = 'q' + m[2]; // store normalized q1..q4
                const hash  = m[3];
                const val   = sel.value;

                if (!data[group]) data[group] = {};
                if (!data[group][qKey]) data[group][qKey] = {};
                data[group][qKey][hash] = val;
            });

            const fd = new FormData();
            fd.append('student_id', String(studentId));
            fd.append('traits', JSON.stringify(data));
            fd.append('<?= $this->security->get_csrf_token_name(); ?>', '<?= $this->security->get_csrf_hash(); ?>');

            fetch('<?= base_url('report/save_traits') ?>', { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(() => alert('Traits saved.'))
            .catch(err => {
                console.error(err);
                alert('Failed to save traits.');
            });
        });
    }

    // ───────────────── NEW: workflow actions ─────────────────
    const CSRF_NAME = <?= json_encode($this->security->get_csrf_token_name()); ?>;
    let   CSRF_HASH = <?= json_encode($this->security->get_csrf_hash()); ?>;
    const TERM  = <?= (int)($term ?? 1) ?>;
    const YEAR  = <?= (int)($year ?? date('Y')) ?>;

    function wfPost(url, payload){
        payload[CSRF_NAME] = CSRF_HASH;
        return fetch(url, {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
          body: new URLSearchParams(payload)
        }).then(r => r.json()).then(function(res){
          if (res && res[CSRF_NAME]) CSRF_HASH = res[CSRF_NAME];
          return res;
        });
    }

    const btnAssign = document.getElementById('btnAssignToPrincipal');
    if (btnAssign) {
      btnAssign.addEventListener('click', function(){
        btnAssign.disabled = true;
        wfPost('<?= base_url('report/assign_to_principal') ?>', {
          student_id: studentId, term: TERM, year: YEAR
        }).then(function(res){
          if (res && res.status) location.reload();
          else { alert(res && res.message ? res.message : 'Failed.'); btnAssign.disabled = false; }
        });
      });
    }

    const btnDone = document.getElementById('btnMarkCompleted');
    if (btnDone) {
      btnDone.addEventListener('click', function(){
        // ensure principal comment present
        if ((principalCommentEl?.value || '').trim() === '') {
          alert('Please add your principal comment before marking as completed.');
          return;
        }
        btnDone.disabled = true;
        wfPost('<?= base_url('report/principal_mark_complete') ?>', {
          student_id: studentId, term: TERM, year: YEAR
        }).then(function(res){
          if (res && res.status) location.reload();
          else { alert(res && res.message ? res.message : 'Failed.'); btnDone.disabled = false; }
        });
      });
    }

    const btnPrintNow = document.getElementById('btnPrintNow');
    if (btnPrintNow) btnPrintNow.addEventListener('click', () => window.print());
});
</script>
