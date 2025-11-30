<?php 
defined('BASEPATH') or exit('No direct script access allowed');
?>
<h3 class="screen-only">Supervisor's Progress Card</h3>

<style>
/* ============ Screen tweaks ============ */
body, .content-wrapper{ background:#fff !important; }

/* Inputs look neat on screen */
.assignment-table input,
.spc-edit,
.spc-elective-name{
  font-size:12px;
}

/* Hide print-only text on screen */
.spc-print{ display:none; }

/* ============ Print ============ */
@media print {

  /* never print the screen header (the <h3> at the top) */
  h3.screen-only{ display:none !important; }

  /* page box */
  @page{ size:A4 portrait; margin:10mm 8mm 10mm 8mm; }
  html, body{ padding:0; margin:0; }

  /* no transform in print; add top padding to clear browser header URL/time */
  .print-area{
    width:auto !important;
    max-width:190mm;
    margin:0 auto;
    padding-top:18mm !important; /* adjust 12–20mm if your browser header still sits close */
  }

  /* hide app chrome */
  .screen-only, aside, .sidebar, .navbar, .header, .footer { display:none !important; }

  /* typography */
  body{ font-size:11px; line-height:1.3; }
  h2{ font-size:16px; margin:6px 0 10px; }
  .d-print-block h2{ margin:0 0 6px 0; line-height:1.15; }
  .d-print-block p{ margin:2px 0; }

  /* keep rows/headers together */
  table, tr, td, th { page-break-inside: avoid; }
  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }

  /* inputs print like text */
  input, select, textarea{
    border:none !important;
    background:transparent !important;
    box-shadow:none !important;
    outline:none !important;
    padding:0 !important;
    height:auto !important;
  }

  /* compact SPC grid cells */
  .spc-grid th,
  .spc-grid td{
    font-size:9px !important;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:clip;
    padding:2px 1px !important;
  }

  /* compact “locked value” look */
  .spc-locked-value{
    font-size:9px !important;
    min-width:32px !important;
    padding:2px 3px !important;
    border:1px solid #e5e7eb;
    border-radius:4px;
    background:#fff;
  }

  /* inputs appear as small text and stay in-column */
  .spc-grid input.spc-edit,
  .assignment-table input{
    font-size:10px !important;
    width:38px !important;
    padding:0 !important;
    margin:0 !important;
    border:none !important;
    background:transparent !important;
    box-shadow:none !important;
    -webkit-appearance:none;
    appearance:none;
  }
  
  /* In print: hide inputs, show text */
.spc-edit{ display:none !important; }
.spc-print{ display:inline !important; }
.spc-edit::placeholder{ color: transparent !important; }

  /* table borders + layout */
  table{ width:100% !important; table-layout:fixed; border-collapse:collapse; }
  .table-bordered th, .table-bordered td{ border:1px solid #000; }
  .assignment-table input{ font-size:10px; }

  /* even column widths (1 subject + 12 slots) */
.spc-grid thead th:first-child,
.spc-grid tbody td:first-child { width:18%; }
.spc-grid thead th:not(:first-child),
.spc-grid tbody td:not(:first-child){ width: calc(82% / 12); }

  /* zebra + quarter colors visible in print */
  tr.spc-zebra > td { background:#f5f5f5 !important; }
  tr[data-row="Q"] td,
  tr[data-row="NUM"] td,
  tr[data-row="S"] td,
  tr[data-row="M"] td{
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  /* General Assignments header styling */
  .assignment-table thead th{
    background:#f0f0f0 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    border-top:2px solid #000 !important;
    border-bottom:2px solid #000 !important;
    padding:8px 0;
  }
  .assignment-table tbody tr:last-child td{ border-bottom:2px solid #000 !important; }

  /* stack 1st/2nd vertically in %S row */
  .spc-grid tr[data-row="S"] td{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:2px;
    white-space:normal; /* allow the two lines to stack */
  }
  .spc-grid tr[data-row="S"] td input[data-field="first_attempt_score"]{
    margin-bottom:2px !important;
  }
  /* ==== Revert: keep 1st and 2nd attempts side-by-side in print ==== */
.spc-grid tr[data-row="S"] td{
  display: table-cell !important;   /* undo flex/column */
  white-space: nowrap !important;
  padding: 2px 1px !important;
}

.spc-grid tr[data-row="S"] td input.spc-edit{
  display: inline-block !important;
  width: 34px !important;
  margin: 0 2px !important;         /* small horizontal gap */
  padding: 0 !important;
}

.spc-grid tr[data-row="S"] td input[data-field="first_attempt_score"]{
  margin-bottom: 0 !important;      /* remove vertical spacing from stack version */
}
}


/* ===== Screen table defaults (kept neat) ===== */
.spc-locked-value{
  display:inline-block; min-width:54px; padding:6px 10px;
  border:1px solid #e5e7eb; border-radius:6px; background:#fff;
  line-height:1.2; font-size:14px;
}
.assignment-table{ border-collapse:collapse; width:100%; table-layout:fixed; margin-top:10px; }
.assignment-table th, .assignment-table td{ padding:6px; text-align:center; vertical-align:middle; border:none; }
.assignment-table thead th{ background:#f8f8f8; font-weight:700; padding:12px 0; border-top:2px solid #000; border-bottom:2px solid #000; }
.assignment-table tbody tr:last-child td{ border-bottom:2px solid #000; }

/* zebra per subject block */
tr.spc-zebra > td { background:#f7f7f7; }

/* elective header tweaks */
.spc-elective-holder{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
.spc-elective-holder strong{ font-size:10px; line-height:1; }
.spc-elective-name{ max-width:240px; padding:6px 10px; border:1px solid #ccc; border-radius:6px; }

/* two-cell subject banner inherits row color */
.spc-subject-title, .spc-subject-fill { background: inherit; }
</style>

<div class="screen-only" style="text-align:right;margin-bottom:20px;">
  <button id="printCleanBtn" class="btn btn-primary">Clean Print</button>
</div>

<?php
// helpers
function spc_term_color($t){
    $t = strtoupper(trim((string)$t));
    if ($t==='1' || $t==='Q1') return '#ccffcc';
    if ($t==='2' || $t==='Q2') return '#cce0ff';
    if ($t==='3' || $t==='Q3') return '#ffe0cc';
    if ($t==='4' || $t==='Q4') return '#f2ccff';
    return '';
}
function date_for_input($s){
    $s = trim((string)$s);
    if ($s === '') return '';
    $s = str_replace('/', '-', $s);
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : '';
}
/* ▼ NEW: tiny rank helper (used below when choosing per-slot row). Higher = further along. */
function spc_status_rank($s){
    $s = strtolower((string)$s);
    static $rank = [
        'ordered'   => 1,
        'paid'      => 2,
        'issued'    => 3,
        'assigned'  => 4,
        'redo'      => 5,
        'completed' => 6,
    ];
    return $rank[$s] ?? 0;
}
/* ▲ ONLY addition in this file */
$student_id     = $student_id     ?? '';
$student_list   = $student_list   ?? [];
$student        = $student        ?? [];
$subjects       = $subjects       ?? [];
$paces          = $paces          ?? [];
$ga             = $ga             ?? ['Q1'=>[],'Q2'=>[],'Q3'=>[],'Q4'=>[]];
$rp             = $rp             ?? ['Q1'=>[],'Q2'=>[],'Q3'=>[],'Q4'=>[]];
$elective_alias = $elective_alias ?? [];

$role = strtolower((string)($this->session->userdata('role') ?? ''));
$can_edit_m = (function_exists('is_superadmin_loggedin') && is_superadmin_loggedin())
           || (function_exists('is_admin_loggedin')      && is_admin_loggedin())
           || $role === 'moderator';

/* --- NORMALIZE KEYS COMING FROM DB (minimal, view-only) ------------------ */
// Map score_1/score_2 -> first_attempt_score/second_attempt_score
// Map terms/quarter -> term
if (!empty($paces) && is_array($paces)) {
    foreach ($paces as &$__row) {
        if (!isset($__row['first_attempt_score']) && isset($__row['score_1'])) {
            $__row['first_attempt_score'] = $__row['score_1'];
        }
        if (!isset($__row['second_attempt_score']) && isset($__row['score_2'])) {
            $__row['second_attempt_score'] = $__row['score_2'];
        }
        if (!isset($__row['term'])) {
            if (isset($__row['terms']))    $__row['term'] = $__row['terms'];
            elseif (isset($__row['quarter'])) $__row['term'] = $__row['quarter'];
        }
    }
    unset($__row);
}

/* If controller didn’t supply $subjects but we have pace rows,
   build a minimal subject list so the grid can render. */
if (empty($subjects) && !empty($paces)) {
    $__ids = [];
    foreach ($paces as $__r) {
        $sid = (int)($__r['subject_id'] ?? 0);
        if ($sid > 0) $__ids[$sid] = true;
    }
    $subjects = [];
    foreach (array_keys($__ids) as $sid) {
        $subjects[] = ['id' => $sid, 'name' => 'Subject '.$sid];
    }
    unset($__ids);
}

?>



<form method="get" action="<?= site_url('spc'); ?>" class="form-inline screen-only">
  <div class="form-group">
    <label>Select Student:&nbsp;</label>
    <select name="student_id" class="form-control" onchange="this.form.submit()">
      <option value="">-- Select --</option>
      <?php foreach ($student_list as $s): ?>
        <option value="<?= (int)$s['id']; ?>" <?= ((string)$student_id===(string)$s['id']?'selected':''); ?>>
          <?= htmlspecialchars($s['first_name'].' '.$s['last_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if (empty($student)) { ?>
  <div class="alert alert-danger">No student selected.</div>
<?php } else { ?>

<div class="print-area" id="print-area"><!-- BEGIN printable area -->


  <div class="d-print-block text-center" style="text-align:center;margin-bottom:12px;">
    <img src="<?= base_url('uploads/school_logo.png'); ?>" alt="School Logo" style="height:70px"><br>
    <h2>Supervisor's Progress Card</h2>
    <p><strong>Student Name:</strong> <?= htmlspecialchars($student['first_name'].' '.$student['last_name']); ?></p>
    <p><strong>Student ID:</strong> <?= (int)$student['id']; ?></p>
    <p><strong>Academic Year:</strong> <?= date('Y'); ?></p>
  </div>

  <?php
  
  /* Prefer rows that already have a score */
function spc_has_score($row){
    $s1 = trim((string)($row['first_attempt_score']  ?? $row['score_1'] ?? ''));
    $s2 = trim((string)($row['second_attempt_score'] ?? $row['score_2'] ?? ''));
    return ($s1 !== '' && $s1 !== '0') || ($s2 !== '' && $s2 !== '0');
}
// group paces by subject and slot (prefer rows that have scores)
$grouped = [];
foreach ($paces as $p) {
    $sid  = (int)($p['subject_id'] ?? 0);
    $slot = (int)($p['slot_index'] ?? 0); // 1..12
    if (!$sid || $slot < 1 || $slot > 12) continue;

    if (!isset($grouped[$sid][$slot])) {
        $grouped[$sid][$slot] = $p;
        continue;
    }

    $cur = $grouped[$sid][$slot];

    $candHas  = spc_has_score($p);
    $curHas   = spc_has_score($cur);
    $candRank = spc_status_rank($p['status'] ?? '');
    $curRank  = spc_status_rank($cur['status'] ?? '');

    $pick = $cur;

    // 1) Prefer a row with scores over one without
    if ($candHas && !$curHas) {
        $pick = $p;
    }
    // 2) If both have (or both don't), use higher status rank
    elseif ($candHas === $curHas && $candRank > $curRank) {
        $pick = $p;
    }
    // 3) Optional tie-breaker: if same rank, prefer the one with a later ID
    elseif ($candHas === $curHas && $candRank === $curRank) {
        $pick = ((int)($p['id'] ?? 0) >= (int)($cur['id'] ?? 0)) ? $p : $cur;
    }

    $grouped[$sid][$slot] = $pick;
}
  ?>

<table class="table table-bordered spc-grid">
    <thead>
      <tr>
        <th>Subject</th>
        <?php for ($i=1; $i<=12; $i++): ?><th><?= $i; ?></th><?php endfor; ?>
      </tr>
    </thead>
    <tbody>

    <?php 
    $zebra = false;
    foreach ($subjects as $subject):
      $zebra = !$zebra; $zebraClass = $zebra ? 'spc-zebra' : '';
      $sid = (int)$subject['id'];
      $display = array_fill(1, 12, []);
      if (isset($grouped[$sid])) {
        foreach ($grouped[$sid] as $slot => $row) $display[$slot] = $row;
      }
    ?>
<?php
$isElective = preg_match('/^Elective\s*\d+/i', (string)($subject['name'] ?? ''));
$aliasVal   = $isElective ? ($elective_alias[(int)$sid] ?? '') : '';
?>
      <!-- subject header row split into two cells: 3 + 10 (total 13) -->
<tr class="<?= $zebraClass ?>">
  <td colspan="3" class="spc-subject-title">
    <?php if ($isElective): ?>
      <div class="spc-elective-holder">
        <strong><?= htmlspecialchars($subject['name'] ?? 'Subject'); ?></strong>
        <input type="text"
               class="spc-elective-name"
               data-student="<?= (int)$student_id; ?>"
               data-subject="<?= (int)$sid; ?>"
               value="<?= htmlspecialchars($aliasVal); ?>"
               placeholder="Type elective name…">
      </div>
    <?php else: ?>
      <strong><?= htmlspecialchars($subject['name'] ?? 'Subject'); ?></strong>
    <?php endif; ?>
  </td>
  <td colspan="10" class="spc-subject-fill"></td>
</tr>

      <!-- Q -->
      <tr class="<?= $zebraClass ?>" data-row="Q">
        <td>Q</td>
        <?php for ($i=1; $i<=12; $i++): $r = $display[$i] ?? []; 
          $term = $r['term'] ?? '';
          $s1   = (int)($r['first_attempt_score']  ?? 0);
          $s2   = (int)($r['second_attempt_score'] ?? 0);
          $passed = ($s1 >= 80 || $s2 >= 80);
          $bg = $passed ? spc_term_color($term) : '';
        ?>
          <td style="background:<?= $bg; ?>">
            <span class="spc-locked-value"><?= ($term!=='' ? htmlspecialchars($term) : '—'); ?></span>
          </td>
        <?php endfor; ?>
      </tr>

      <!-- # -->
      <tr class="<?= $zebraClass ?>" data-row="NUM">
        <td>#</td>
        <?php for ($i=1; $i<=12; $i++): $r = $display[$i] ?? []; 
          $term = $r['term'] ?? '';
          $s1   = (int)($r['first_attempt_score']  ?? 0);
          $s2   = (int)($r['second_attempt_score'] ?? 0);
          $passed = ($s1 >= 80 || $s2 >= 80);
          $pnum = $r['pace_number'] ?? '';
          $bg = $passed ? spc_term_color($term) : '';
        ?>
          <td style="background:<?= $bg; ?>">
            <span class="spc-locked-value"><?= $pnum ? htmlspecialchars($pnum) : '—'; ?></span>
          </td>
        <?php endfor; ?>
      </tr>

      <!-- %S -->
      <tr class="<?= $zebraClass ?>" data-row="S">
        <td>%S</td>
        <?php for ($i=1; $i<=12; $i++): $r = $display[$i] ?? [];
          $id     = (int)($r['id'] ?? 0);
          $statusRaw = $r['status'] ?? '';
          $st     = strtolower($statusRaw);  /* <- normalize case */
          $term   = $r['term'] ?? '';
          $s1v    = $r['first_attempt_score']  ?? '';
          $s2v    = $r['second_attempt_score'] ?? '';
          $s1i    = (int)$s1v; $s2i = (int)$s2v;
          $passed = ($s1i >= 80 || $s2i >= 80);
          $bg     = $passed ? spc_term_color($term) : '';

          $enable_first  = ($st === 'assigned' && $id > 0 && $s1v === '');
          $enable_second = (in_array($st, ['assigned','redo'], true) && $id > 0 && $s2v === '' && $s1i < 80 && $s1v !== '');
        ?>
          <td style="background:<?= $bg; ?>">
            <input type="number" min="0" max="100"
                   class="spc-edit"
                   data-field="first_attempt_score"
                   data-id="<?= $id; ?>"
                   data-student="<?= (int)$student_id; ?>"
                   data-subject="<?= $sid; ?>"
                   data-slot="<?= (int)($r['slot_index'] ?? $i); ?>"
                   value="<?= htmlspecialchars($s1v); ?>"
                   placeholder="1st"
                   style="width:45px;text-align:center;"
                   <?= $enable_first ? '' : 'disabled'; ?>>
                   
<?php
  // PRINT: choose exactly one value to show
  $s1set = ($s1v !== '' && $s1v !== null);
  $s2set = ($s2v !== '' && $s2v !== null);
  $s1int = $s1set ? (int)$s1v : null;
  $s2int = $s2set ? (int)$s2v : null;

  if ($s1set && $s1int >= 80) {
      $printScore = $s1int;                 // 1st passed → show 1st
  } elseif ($s1set && $s1int < 80 && $s2set) {
      $printScore = $s2int;                 // 1st failed and 2nd exists → show 2nd
  } elseif ($s1set) {
      $printScore = $s1int;                 // only 1st exists (<80) → show 1st
  } else {
      $printScore = $s2int;                 // no 1st, show 2nd if present (or nothing)
  }
?>
<span class="spc-print"><?= ($printScore !== null ? $printScore . '%' : '') ?></span>
            
            <input type="number" min="0" max="100"
                   class="spc-edit"
                   data-field="second_attempt_score"
                   data-id="<?= $id; ?>"
                   data-student="<?= (int)$student_id; ?>"
                   data-subject="<?= $sid; ?>"
                   data-slot="<?= (int)($r['slot_index'] ?? $i); ?>"
                   value="<?= htmlspecialchars($s2v); ?>"
                   placeholder="2nd"
                   style="width:45px;text-align:center;margin-top:4px;"
                   <?= $enable_second ? '' : 'disabled'; ?>>
                   
<?php
  // PRINT: choose exactly one value to show
  $s1set = ($s1v !== '' && $s1v !== null);
  $s2set = ($s2v !== '' && $s2v !== null);
  $s1int = $s1set ? (int)$s1v : null;
  $s2int = $s2set ? (int)$s2v : null;

  if ($s1set && $s1int >= 80) {
      $printScore = $s1int;                 // 1st passed → show 1st
  } elseif ($s1set && $s1int < 80 && $s2set) {
      $printScore = $s2int;                 // 1st failed and 2nd exists → show 2nd
  } elseif ($s1set) {
      $printScore = $s1int;                 // only 1st exists (<80) → show 1st
  } else {
      $printScore = $s2int;                 // no 1st, show 2nd if present (or nothing)
  }
?>
<span class="spc-print"><?= ($printScore !== null ? $printScore . '%' : '') ?></span>

          </td>
        <?php endfor; ?>
      </tr>

      <!-- %M -->
      <tr class="<?= $zebraClass ?>" data-row="M">
        <td>%M</td>
        <?php for ($i=1; $i<=12; $i++): $r = $display[$i] ?? [];
          $id   = (int)($r['id'] ?? 0);
          $mod  = $r['moderator_score'] ?? '';
          $term = $r['term'] ?? '';
          $s1   = (int)($r['first_attempt_score']  ?? 0);
          $s2   = (int)($r['second_attempt_score'] ?? 0);
          $passed = ($s1 >= 80 || $s2 >= 80);
          $bg = $passed ? spc_term_color($term) : '';
        ?>
          <td style="background:<?= $bg; ?>">
            <input type="number" min="0" max="100"
                   class="spc-edit"
                   data-field="moderator_score"
                   data-id="<?= $id; ?>"
                   data-student="<?= (int)$student_id; ?>"
                   data-subject="<?= $sid; ?>"
                   data-slot="<?= (int)($r['slot_index'] ?? $i); ?>"
                   value="<?= htmlspecialchars($mod); ?>"
                   style="width:50px;text-align:center;"
                   <?= ($can_edit_m && $id > 0) ? '' : 'disabled'; ?>>
                   
                   <span class="spc-print"><?= ($mod !== '' ? (int)$mod . '%' : ''); ?></span>
          </td>
        <?php endfor; ?>
      </tr>

    <?php endforeach; ?>
    </tbody>
  </table>

  <h4>General Assignments (e.g. Projects; Oral Reports; Book Reports; etc.)</h4>

  <table class="table assignment-table">
    <thead>
      <tr><th colspan="3">1st Quarter</th><th colspan="3">2nd Quarter</th></tr>
      <tr><th>Date</th><th>Item</th><th>%</th><th>Date</th><th>Item</th><th>%</th></tr>
    </thead>
    <tbody>
    <?php for ($i=1; $i<=7; $i++): ?>
      <tr>
        <td><input class="ga-input" data-term="Q1" data-row="<?= $i ?>" data-field="date"    type="date"   value="<?= date_for_input($ga['Q1'][$i]['date'] ?? '') ?>"   style="width: 50%"></td>
        <td><input class="ga-input" data-term="Q1" data-row="<?= $i ?>" data-field="item"    type="text"   value="<?= htmlspecialchars($ga['Q1'][$i]['item'] ?? '') ?>"    style="width: 95%"></td>
        <td><input class="ga-input" data-term="Q1" data-row="<?= $i ?>" data-field="percent" type="number" value="<?= htmlspecialchars($ga['Q1'][$i]['percent'] ?? '') ?>" style="width: 80px"></td>

        <td><input class="ga-input" data-term="Q2" data-row="<?= $i ?>" data-field="date"    type="date"   value="<?= date_for_input($ga['Q2'][$i]['date'] ?? '') ?>"   style="width: 50%"></td>
        <td><input class="ga-input" data-term="Q2" data-row="<?= $i ?>" data-field="item"    type="text"   value="<?= htmlspecialchars($ga['Q2'][$i]['item'] ?? '') ?>"    style="width: 95%"></td>
        <td><input class="ga-input" data-term="Q2" data-row="<?= $i ?>" data-field="percent" type="number" value="<?= htmlspecialchars($ga['Q2'][$i]['percent'] ?? '') ?>" style="width: 80px"></td>
      </tr>
    <?php endfor; ?>
    <tr>
      <td colspan="3">
        <strong>READING PROGRAMME</strong><br>
        <div class="rp-box" data-term="Q1">
          W.P.M.:
          <input type="number" class="rp-field" data-key="wpm"
                 value="<?= htmlspecialchars($rp['Q1']['wpm'] ?? '') ?>" style="width:60px;">
          %:
          <input type="number" class="rp-field" data-key="percent"
                 value="<?= htmlspecialchars($rp['Q1']['percent'] ?? '') ?>" style="width:60px;">
          Comp. Score:
          <input type="number" class="rp-field" data-key="comprehension"
                 value="<?= htmlspecialchars($rp['Q1']['comprehension'] ?? '') ?>" style="width:60px;">
        </div>
      </td>

      <td colspan="3">
        <strong>READING PROGRAMME</strong><br>
        <div class="rp-box" data-term="Q2">
          W.P.M.:
          <input type="number" class="rp-field" data-key="wpm"
                 value="<?= htmlspecialchars($rp['Q2']['wpm'] ?? '') ?>" style="width:60px;">
          %:
          <input type="number" class="rp-field" data-key="percent"
                 value="<?= htmlspecialchars($rp['Q2']['percent'] ?? '') ?>" style="width:60px;">
          Comp. Score:
          <input type="number" class="rp-field" data-key="comprehension"
                 value="<?= htmlspecialchars($rp['Q2']['comprehension'] ?? '') ?>" style="width:60px;">
        </div>
      </td>
    </tr>
    </tbody>

    <thead>
      <tr><th colspan="3">3rd Quarter</th><th colspan="3">4th Quarter</th></tr>
      <tr><th>Date</th><th>Item</th><th>%</th><th>Date</th><th>Item</th><th>%</th></tr>
    </thead>
    <tbody>
    <?php for ($i=1; $i<=7; $i++): ?>
      <tr>
        <td><input class="ga-input" data-term="Q3" data-row="<?= $i ?>" data-field="date"    type="date"   value="<?= date_for_input($ga['Q3'][$i]['date'] ?? '') ?>"   style="width: 50%"></td>
        <td><input class="ga-input" data-term="Q3" data-row="<?= $i ?>" data-field="item"    type="text"   value="<?= htmlspecialchars($ga['Q3'][$i]['item'] ?? '') ?>"    style="width: 95%"></td>
        <td><input class="ga-input" data-term="Q3" data-row="<?= $i ?>" data-field="percent" type="number" value="<?= htmlspecialchars($ga['Q3'][$i]['percent'] ?? '') ?>" style="width: 80px"></td>

        <td><input class="ga-input" data-term="Q4" data-row="<?= $i ?>" data-field="date"    type="date"   value="<?= date_for_input($ga['Q4'][$i]['date'] ?? '') ?>"   style="width: 50%"></td>
        <td><input class="ga-input" data-term="Q4" data-row="<?= $i ?>" data-field="item"    type="text"   value="<?= htmlspecialchars($ga['Q4'][$i]['item'] ?? '') ?>"    style="width: 95%"></td>
        <td><input class="ga-input" data-term="Q4" data-row="<?= $i ?>" data-field="percent" type="number" value="<?= htmlspecialchars($ga['Q4'][$i]['percent'] ?? '') ?>" style="width: 80px"></td>
      </tr>
    <?php endfor; ?>
    <tr>
      <td colspan="3">
        <strong>READING PROGRAMME</strong><br>
        <div class="rp-box" data-term="Q3">
          W.P.M.:
          <input type="number" class="rp-field" data-key="wpm"
                 value="<?= htmlspecialchars($rp['Q3']['wpm'] ?? '') ?>" style="width:60px;">
          %:
          <input type="number" class="rp-field" data-key="percent"
                 value="<?= htmlspecialchars($rp['Q3']['percent'] ?? '') ?>" style="width:60px;">
          Comp. Score:
          <input type="number" class="rp-field" data-key="comprehension"
                 value="<?= htmlspecialchars($rp['Q3']['comprehension'] ?? '') ?>" style="width:60px;">
        </div>
      </td>

      <td colspan="3">
        <strong>READING PROGRAMME</strong><br>
        <div class="rp-box" data-term="Q4">
          W.P.M.:
          <input type="number" class="rp-field" data-key="wpm"
                 value="<?= htmlspecialchars($rp['Q4']['wpm'] ?? '') ?>" style="width:60px;">
          %:
          <input type="number" class="rp-field" data-key="percent"
                 value="<?= htmlspecialchars($rp['Q4']['percent'] ?? '') ?>" style="width:60px;">
          Comp. Score:
          <input type="number" class="rp-field" data-key="comprehension"
                 value="<?= htmlspecialchars($rp['Q4']['comprehension'] ?? '') ?>" style="width:60px;">
        </div>
      </td>
    </tr>
    </tbody>
  </table>
<?php } ?>

<script>
$(function () {
  $.ajaxSetup({ headers: { 'X-Requested-With': 'XMLHttpRequest' } });

  const CSRF_NAME    = '<?= $this->security->get_csrf_token_name(); ?>';
  let   CSRF_HASH    = '<?= $this->security->get_csrf_hash(); ?>';
  const STUDENT_ID   = <?= (int)$student_id ?>;
  const URL_FIELD    = '<?= site_url('spc/update_field'); ?>';
  const URL_GA       = '<?= site_url('spc/update_ga'); ?>';
  const URL_RP_SAVE  = '<?= site_url('spc/save_reading_program'); ?>';
  const URL_ELECTIVE = '<?= site_url('spc/save_elective_alias'); ?>';

  function refreshCsrf(res){ if (res && res[CSRF_NAME]) CSRF_HASH = res[CSRF_NAME]; }

  // ---------- SUCCESS CHECK (broadened) ----------
  function normalizeResponse(res){
    if (typeof res === 'string') {
      const t = res.trim();
      if (t === '' ) return {};
      // bare scalars often returned by CI echo
      if (t === '1' || t === 'true' || t.toLowerCase() === 'ok' || t.toLowerCase() === 'success') {
        return { success: true };
      }
      try { return JSON.parse(t); } catch(e) { return { raw:t }; }
    }
    return res || {};
  }
  function isOk(raw){
    const res = normalizeResponse(raw);
    refreshCsrf(res);

    if (res.error || res.success === false) return false;

    if (res.success === true || res.success === 1 || res.success === '1') return true;

    const st = (res.status || '').toString().toLowerCase();
    if (st === 'ok' || st === 'success' || st === 'saved' || st === 'true') return true;

    if (+res.affected_rows > 0 || +res.updated > 0 || +res.id > 0) return true;

    // if we got any object back without explicit error, consider it OK
    if (typeof raw === 'object') return true;

    return false;
  }
  // -----------------------------------------------

  function normalizeTerm(txt){
    txt = (txt||'').toString().trim().toUpperCase();
    if (txt==='1') return 'Q1';
    if (txt==='2') return 'Q2';
    if (txt==='3') return 'Q3';
    if (txt==='4') return 'Q4';
    return txt;
  }
  const colorMap = { Q1:'#ccffcc', Q2:'#cce0ff', Q3:'#ffe0cc', Q4:'#f2ccff' };

  // ---------------- Scores ----------------
  (function(){
    function debounce(fn, ms){ let t; return function(){ clearTimeout(t); const ctx=this, args=arguments; t=setTimeout(()=>fn.apply(ctx,args), ms); }; }

    function normalizeTerm(txt){
      txt = (txt||'').toString().trim().toUpperCase();
      if (txt==='1') return 'Q1'; if (txt==='2') return 'Q2'; if (txt==='3') return 'Q3'; if (txt==='4') return 'Q4';
      return txt;
    }
    const colorMap = { Q1:'#ccffcc', Q2:'#cce0ff', Q3:'#ffe0cc', Q4:'#f2ccff' };

    $(document).on('focus', '.spc-edit', function(){ $(this).data('prev', $(this).val()); });

    function saveScore($el){
      const id    = parseInt($el.data('id'),10) || 0;
      const field = ($el.data('field') || '').toString();
      const val   = ($el.val() || '').toString();

      // define once and reuse later
      const student = $el.data('student'),
            subject = $el.data('subject'),
            slot    = $el.data('slot');

      if (!id || !['first_attempt_score','second_attempt_score','moderator_score'].includes(field)) return;
      if (val === '' || isNaN(Number(val))) return;
      if (($el.data('lastSent') || '') === val) return;

      const payload = {
        id, field, value: val,
        student_id: student,
        subject_id: subject,
        slot: slot
      };
      payload[CSRF_NAME] = CSRF_HASH;

      $el.data('lastSent', val);
      $el.prop('disabled', true);

      $.ajax({
        url: URL_FIELD,
        method: 'POST',
        data: payload,
        dataType: 'text'
      }).done(function (txt) {
        const res = normalizeResponse(txt);
        if (!isOk(res)) {
          alert((res && res.error) || 'Save failed');
          $el.prop('disabled', false).val($el.data('prev') || '');
          $el.removeData('lastSent');
          return;
        }

        if (res.unlock_second) {
          const sel = '[data-student="'+student+'"][data-subject="'+subject+'"][data-slot="'+slot+'"][data-field="second_attempt_score"]';
          $(sel).prop('disabled', false).removeClass('disabled').val('').focus();
        }

        const colIndex = $el.closest('td').index();
        const $qRow  = $el.closest('tr').prevAll('tr[data-row="Q"]').first();
        const $numRow= $qRow.next();
        const $sRow  = $numRow.next();
        const $mRow  = $sRow.next();

        let s1 = 0, s2 = 0;
        $('[data-student="'+student+'"][data-subject="'+subject+'"][data-slot="'+slot+'"]').each(function(){
          const f = $(this).data('field'), v = parseInt($(this).val(),10) || 0;
          if (f==='first_attempt_score')  s1 = v;
          if (f==='second_attempt_score') s2 = v;
        });
        const passed   = (s1 >= 80) || (s2 >= 80);
        const termText = $qRow.find('td').eq(colIndex).find('.spc-locked-value').text()
                       || $qRow.find('td').eq(colIndex).text();
        const termKey  = normalizeTerm(termText);
        const color    = (passed && colorMap[termKey]) ? colorMap[termKey] : '';

        var rowsArr = [$qRow, $numRow, $sRow, $mRow];
        rowsArr.forEach(function(r){ r.find('td').eq(colIndex).css('background-color', color); });

        if (field === 'first_attempt_score') {
          $('[data-student="'+student+'"][data-subject="'+subject+'"][data-slot="'+slot+'"][data-field="second_attempt_score"]')
            .prop('disabled', s1 >= 80);
        }
        if (field === 'second_attempt_score') {
          $('[data-student="'+student+'"][data-subject="'+subject+'"][data-slot="'+slot+'"][data-field="first_attempt_score"]').prop('disabled', true);
          $('[data-student="'+student+'"][data-subject="'+subject+'"][data-slot="'+slot+'"][data-field="second_attempt_score"]').prop('disabled', true);
        }

        $el.prop('disabled', false);
        $el.data('prev', val);
      }).fail(function (xhr) {
        alert('Save failed');
        $el.prop('disabled', false).val($el.data('prev') || '');
        $el.removeData('lastSent');
        console.error(xhr.status, xhr.responseText);
      });
    }

    $(document).on('change', '.spc-edit', function(){ saveScore($(this)); });
    $(document).on('blur',   '.spc-edit', function(){
      const prev = String($(this).data('prev') || '');
      const now  = String($(this).val() || '');
      if (now !== prev) saveScore($(this));
    });
    $(document).on('keydown', '.spc-edit', function(e){
      if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); $(this).blur(); }
    });
    
  })();


  // ---------------- General Assignments ----------------
  $(document).on('change', '.ga-input', function () {
    const $el = $(this);
    const payload = {
      student_id: <?= (int)$student_id ?>,
      term:  $el.data('term'),
      row:   $el.data('row'),
      field: $el.data('field'),
      value: $el.val()
    };
    payload[CSRF_NAME] = CSRF_HASH;

    $.ajax({
      url: URL_GA,
      method: 'POST',
      data: payload,
      dataType: 'text'
    }).done(function (txt) {
      const res = normalizeResponse(txt);
      if (res && res.error) alert(res.error);
    }).fail(function(xhr){
      alert('Save failed');
      console.error(xhr.status, xhr.responseText);
    });
  });

  // ---------------- Reading Programme ----------------
  $(document).on('change blur', '.rp-field', function () {
    const $inp = $(this);
    const term = ($inp.closest('.rp-box').data('term') || '').toString().toUpperCase();
    const key  = ($inp.data('key') || '').toString();
    let   val  = $inp.val();
    if (!term || !key) return;
    if (key === 'wpm') val = Math.max(0, Math.min(1000, parseInt(val || 0, 10)));
    else if (key === 'percent' || key === 'comprehension') val = Math.max(0, Math.min(100, parseInt(val || 0, 10)));

    const payload = { student_id: STUDENT_ID, term: term };
    payload[key]  = val;
    payload[CSRF_NAME] = CSRF_HASH;

    $.ajax({
      url: URL_RP_SAVE,
      method: 'POST',
      data: payload,
      dataType: 'text'
    }).done(function (txt) {
      const res = normalizeResponse(txt);
      if (!isOk(res)) {
        alert((res && res.error) ? ('Reading Programme save failed: ' + res.error) : 'Reading Programme save failed');
      } else {
        $inp.val(val);
      }
    }).fail(function (xhr) {
      console.error(xhr.status, xhr.responseText);
      alert('Reading Programme save failed (network).');
    });
  });

  // ---------------- Elective alias ----------------
  $(document).off('change blur', '.spc-elective-name');

  $('.spc-elective-name').each(function () {
    $(this).data('last', ($(this).val() || '').trim());
  });

  $(document).on('change', '.spc-elective-name', function () {
    const $el    = $(this);
    const newVal = ($el.val() || '').trim();
    const last   = ($el.data('last') || '').toString();
    if (newVal === last) return;

    const payload = {
      student_id: $el.data('student'),
      subject_id: $el.data('subject'),
      name: newVal
    };
    payload[CSRF_NAME] = CSRF_HASH;

    $el.prop('disabled', true);

    $.ajax({
      url: URL_ELECTIVE,
      method: 'POST',
      data: payload,
      dataType: 'text'
    }).done(function (txt, status, xhr) {
      const res = normalizeResponse(txt);
      if (isOk(res) || xhr.status === 200) {
        $el.data('last', newVal);
      } else if (res && res.error) {
        alert(res.error);
      } else {
        alert('Save failed');
      }
    }).fail(function (xhr) {
      alert('Save failed');
      console.error('Elective save error:', xhr.status, xhr.responseText);
    }).always(function () {
      $el.prop('disabled', false);
    });
  });
});
</script>

<script>
(function(){
  // Basic print button can stay as-is; this only replaces Clean Print.
  var cleanBtn = document.getElementById('printCleanBtn');
  if (!cleanBtn) return;

  cleanBtn.addEventListener('click', function () {
    var src = document.getElementById('print-area');
    if (!src) { alert('Printable area not found.'); return; }

    // Clone printable area and strip UI-only stuff inside it
    var clone = src.cloneNode(true);
    Array.prototype.slice.call(
      clone.querySelectorAll('.screen-only, .panel-heading .pull-right, .btn, a.btn')
    ).forEach(function (el) { el.parentNode && el.parentNode.removeChild(el); });

    // Build the full HTML document to print
    var studentName = "<?= htmlspecialchars(($student['first_name'] ?? '').' '.($student['last_name'] ?? '')) ?>";
    var studentId   = "<?= (int)($student['id'] ?? 0) ?>";
    var year        = "<?= date('Y') ?>";
    var title       = 'Supervisor\'s Progress Card - ' + studentName + ' (ID ' + studentId + ') - ' + year;

    var html =
`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>${title}</title>
<style>
  @page { size: A4 portrait; margin: 10mm 8mm 10mm 8mm; }
  html, body { margin:0; padding:0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height:1.3; }
  .wrap { width: 190mm; margin: 0 auto; }

  table { width:100%; border-collapse:collapse; table-layout:fixed; }
  th, td { border:1px solid #000; padding:3px 2px; font-size:9px; }
  thead th { border-bottom:2px solid #000; font-weight:700; }
  thead { display: table-header-group; }
  tfoot { display: table-footer-group; }
  table, tr, td, th { page-break-inside: avoid; }
  a[href]:after { content: none !important; }

  input, select, textarea {
    border:none !important; background:transparent !important; box-shadow:none !important;
    outline:none !important; padding:0 !important; height:auto !important; font-size:10px !important;
  }

  .spc-grid th, .spc-grid td { white-space:nowrap; overflow:hidden; text-overflow:clip; }
.spc-grid thead th:first-child,
.spc-grid tbody td:first-child { width:18%; }
.spc-grid thead th:not(:first-child),
.spc-grid tbody td:not(:first-child){ width: calc(82% / 12); }
  .spc-locked-value{
    font-size:9px !important; min-width:32px !important; padding:2px 3px !important;
    border:1px solid #e5e7eb; border-radius:4px; background:#fff;
  }

  tr[data-row="Q"] td, tr[data-row="NUM"] td, tr[data-row="S"] td, tr[data-row="M"] td {
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  tr.spc-zebra > td { background:#f5f5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  .assignment-table thead th{
    background:#f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;
    border-top:2px solid #000 !important; border-bottom:2px solid #000 !important; padding:6px 0;
  }
    .assignment-table input{ font-size:10px !important; }
    + /* Hide inputs, show printable text in the clean-print window */
    + .spc-edit{ display:none !important; }
    + .spc-print{ display:inline !important; }
    
      /* keep 1st & 2nd attempts side-by-side */
  
  
  .spc-grid tr[data-row="S"] td { white-space:nowrap !important; }
  .spc-grid tr[data-row="S"] td input.spc-edit{
    display:inline-block !important; width:0px !important; margin:0 2px !important; padding:1 !important;
  }
</style>
</head>
<body>
  <div class="wrap">
    ${clone.innerHTML}
  </div>
  <script>
    window.addEventListener('load', function(){
      window.print();
      setTimeout(function(){ window.close(); }, 300);
    });
  <\/script>
</body>
</html>`;

    // Create a Blob URL instead of document.write (works around blockers)
    var blob = new Blob([html], {type: 'text/html'});
    var url  = URL.createObjectURL(blob);

    var w = window.open(url, '_blank', 'noopener,noreferrer,width=1024,height=768');
    if (!w) { alert('Please allow popups to print this SPC.'); URL.revokeObjectURL(url); return; }

    // Revoke the blob URL after the new window has loaded the content
    var revoke = function(){ try{ URL.revokeObjectURL(url); }catch(e){} };
    // If we can, attach to 'load' of the new window; otherwise revoke later.
    try { w.addEventListener('load', revoke, {once:true}); } catch(e){ setTimeout(revoke, 5000); }
  });
})();
</script>
