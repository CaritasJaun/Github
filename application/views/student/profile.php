<?php
$widget   = (is_superadmin_loggedin() ? 3 : 4);
$branchID = $student['branch_id'];
$getParent = $this->student_model->get('parent', array('id' => $student['parent_id']), true);
$parent    = $getParent;

if (empty($student['previous_details'])) {
    $previous_details = ['school_name' => '', 'qualification' => '', 'remarks' => ''];
} else {
    $previous_details = json_decode($student['previous_details'], true);
}

/* ---- projection fallbacks ---- */
$proj_year      = isset($proj_year) ? $proj_year : date('Y');
$proj_labels    = isset($proj_labels) ? $proj_labels : [];
$proj_committed = isset($proj_committed) ? $proj_committed : [];
$proj_actual    = isset($proj_actual) ? $proj_actual : [];

/* ===== Robust role detection ===== */
$role_id  = (int)(
    $this->session->userdata('loggedin_role_id')
 ?? $this->session->userdata('role_id')
 ?? $this->session->userdata('role')
 ?? 0
);

$role_slug = strtolower(trim((string)(
    $this->session->userdata('role_slug')
 ?? $this->session->userdata('role')
 ?? $this->session->userdata('role_name')
 ?? $this->session->userdata('loggedin_role')
 ?? $this->session->userdata('loggedin_role_name')
 ?? $this->session->userdata('user_role')
 ?? $this->session->userdata('group')
 ?? ''
)));

$IS_SUPER   = function_exists('is_superadmin_loggedin') ? is_superadmin_loggedin()
            : (strpos($role_slug, 'super') !== false || in_array($role_id, [1], true));

$IS_ADMIN   = function_exists('is_admin_loggedin') ? is_admin_loggedin()
            : (strpos($role_slug, 'admin')  !== false || in_array($role_id, [2], true));

$IS_PARENT  = function_exists('is_parent_loggedin') ? is_parent_loggedin()
            : (strpos($role_slug, 'parent') !== false || in_array($role_id, [6], true));

$IS_STUDENT = function_exists('is_student_loggedin') ? is_student_loggedin()
            : (strpos($role_slug, 'student')!== false || in_array($role_id, [7], true));

$IS_TEACHER = function_exists('is_teacher_loggedin') ? is_teacher_loggedin()
            : (strpos($role_slug, 'teacher')!== false
            ||  strpos($role_slug, 'staff')  !== false
            ||  strpos($role_slug, 'faculty')!== false
            ||  in_array($role_id, [3,4,5,10], true));

/* ---- Parent-but-also-teacher fix: teacher/admin/super wins ---- */
$is_parent_only = $IS_PARENT && !($IS_TEACHER || $IS_ADMIN || $IS_SUPER);

/* Optional force/debug via URL: ?ppforce=1  (remove later if you want) */
$__force = (string)$this->input->get('ppforce') === '1';

/* View / Edit policy */
$can_view_proj = $__force || (($IS_SUPER || $IS_ADMIN || $IS_TEACHER || $IS_STUDENT) && !$is_parent_only);
$can_edit_proj = $__force || (($IS_TEACHER || $IS_STUDENT) && !$is_parent_only);

/* Permission fallbacks (if your install uses fine-grained perms) */
if (function_exists('get_permission') && !$can_view_proj) {
    foreach ([
        ['pace_management', 'is_view'],
        ['projection_planner', 'is_view'],
        ['student_assign_paces', 'is_view'],
        ['assign_subjects', 'is_view'],
    ] as $p) {
        if (get_permission($p[0], $p[1])) { $can_view_proj = true; break; }
    }
}

/* Respect controller override for editability */
if (!isset($proj_can_edit)) {
    $proj_can_edit = $can_edit_proj;
}

/* Quarter selection from controller, default All(0) */
$termSel = isset($SELECTED_TERM) ? (int)$SELECTED_TERM : 0;

/* Always emit an HTML comment so you can verify quickly in View Source */
echo "<!-- PP debug: role_id={$role_id} slug={$role_slug} parent_only=" . ($is_parent_only?1:0) .
     " view=" . ($can_view_proj?1:0) . " edit=" . ($proj_can_edit?1:0) . " -->";
?>

<div class="row appear-animation" data-appear-animation="<?=$global_config['animations'] ?>" data-appear-animation-delay="100">
	<div class="col-md-12 mb-lg">
		<div class="profile-head">
			<div class="col-md-12 col-lg-4 col-xl-3">
				<div class="image-content-center user-pro">
					<div class="preview">
						<img src="<?php echo get_image_url('student', $student['photo']);?>">
					</div>
				</div>
			</div>
			<div class="col-md-12 col-lg-5 col-xl-5">
				<h5><?=$student['first_name'] . ' ' . $student['last_name']?></h5>
				<p><?=translate('student')?> / <?=$student['category_name']?></p>
				<ul>
					<li><div class="icon-holder" data-toggle="tooltip" data-original-title="<?=translate('guardian_name')?>"><i class="fas fa-users"></i></div> <?=(!empty($getParent['name']) ? $getParent['name'] : 'N/A'); ?></li>
					<?php if (!empty($student['birthday'])) { ?>
					<li><div class="icon-holder" data-toggle="tooltip" data-original-title="<?=translate('birthday')?>"><i class="fas fa-birthday-cake"></i></div> <?=_d($student['birthday'])?></li>
					<?php } ?>
					<li><div class="icon-holder" data-toggle="tooltip" data-original-title="<?=translate('class')?>"><i class="fas fa-school"></i></div> <?=$student['class_name'] . ' ('.$student['section_name'] . ')'?></li>
					<li><div class="icon-holder" data-toggle="tooltip" data-original-title="<?=translate('mobile_no')?>"><i class="fas fa-phone-volume"></i></div> <?=(!empty($student['mobileno']) ? $student['mobileno'] : 'N/A'); ?></li>
					<li><div class="icon-holder" data-toggle="tooltip" data-original-title="<?=translate('email')?>"><i class="far fa-envelope"></i></div> <?=(!empty($student['email']) ? $student['email'] : 'N/A'); ?></li>
					<li><div class="icon-holder" data-toggle="tooltip" data-original-title="<?=translate('present_address')?>"><i class="fas fa-home"></i></div> <?=(!empty($student['current_address']) ? $student['current_address'] : 'N/A'); ?></li>
				</ul>
			</div>
		</div>
	</div>

<?php if ($student['active'] == 0) {
	$getDisableReason = $this->student_model->getDisableReason($student['id']);
	$disableReason = '-';
	$disableDate = '-';
	$disableNote = '-';
	if (!empty($getDisableReason )) {
		$disableReason = $getDisableReason->reason;
		$disableDate = _d($getDisableReason->date);
		$disableNote = $getDisableReason->note;
	}
	?>
	<div class="col-md-offset-2 col-md-8">
		<section class="panel pg-fw">
		    <div class="panel-body">
		        <h5 class="chart-title mb-xs text-danger"><i class="fas fa-lock"></i> <?php echo translate('student') . " " . translate('deactivated') ?></h5>
		        <div class="mt-lg">
		        	<h4 class="mt-lg"><i class="far fa-check-circle"></i> <?php echo translate('active') . " " . translate('deactivate_reason') ?></h4>
		        	<ul class="stu-disabled">
		        		<li>
		        			<div class="main-r">
			        			<div class="r-1"><?php echo translate('deactivate_reason')?> :</div>
			        			<div><?php echo $disableReason; ?></div>
		        			</div>
		        		</li>
		        		<li>
		        			<div class="main-r">
			        			<div class="r-1"><?php echo translate('date')?> :</div>
			        			<div><?php echo $disableDate; ?></div>
		        			</div>
		        		</li>
		        		<li>
		        			<div class="main-r">
			        			<div class="r-1"><?php echo translate('note')?> :</div>
			        			<div><?php echo $disableNote; ?></div>
		        			</div>
		        		</li>
		        	</ul>
		        	<h4 class="mt-lg"><i class="fas fa-list"></i> <?php echo translate('deactivated') . " " . translate('history') ?></h4>
					<div class="table-responsive mb-md mt-md">
						<table class="table table-bordered table-hover table-condensed mb-none">
							<thead>
								<tr>
									<th width="50">#</th>
									<th><?=translate('deactivate_reason')?></th>
									<th><?=translate('date')?></th>
									<th width="360"><?=translate('note')?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$count = 1;
								$this->db->order_by('id', 'asc');
								$this->db->where(array('student_id' => $student['id']));
								$historys = $this->db->get('disable_reason_details')->result();
									if (count($historys)) {
										foreach($historys as $history):
											?>
									<tr>
										<td><?php echo $count++;?></td>
										<td><?php echo get_type_name_by_id('disable_reason', $history->reason_id); ?></td>
										<td><?php echo _d($history->date); ?></td>
										<td><?php echo $history->note; ?></td>
									</tr>
								<?php
									endforeach;
								} else {
									echo '<tr><td colspan="4"><h5 class="text-danger text-center">' . translate('no_information_available') . '</td></tr>';
								}
								?>
							</tbody>
						</table>
					</div>
		        </div>
		    </div>
		</section>
	</div>
<?php } ?>
	<div class="col-md-12">
<?php
// ---- vars for the card ----
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();

$ov   = isset($overview) ? $overview : [];
$prog = (float)($ov['progress_percent'] ?? 0);
$assigned  = (int)($ov['assigned_total'] ?? 0);
$completed = (int)($ov['completed_total'] ?? 0);
$below80   = (int)($ov['below80_total'] ?? 0);
$risk = $ov['risk_flag'] ?? 'ok';
$badgeClass = ($risk==='risk'?'label-danger':($risk==='watch'?'label-warning':'label-success'));

$STUDENT_ID = (int)($student['id'] ?? $this->input->get('student_id'));

// IMPORTANT: controller should set $SELECTED_TERM; keep 0 as fallback.
$termSel = isset($SELECTED_TERM) ? (int)$SELECTED_TERM : 0;

// term metrics
$tm = isset($term_metrics) && is_array($term_metrics) ? $term_metrics : [];
$tm_days     = (int)   ($tm['days_absent']       ?? 0);
$tm_merits   = (int)   ($tm['merits']            ?? 0);
$tm_demerits = (int)   ($tm['demerits']          ?? 0);
$tm_pages    = (float) ($tm['avg_pages_per_day'] ?? 0);
?>

<!-- ====================== OVERVIEW (Quarter-Aware) ====================== -->
<div class="panel panel-default">
  <div class="panel-heading" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <strong>Overview</strong>
      <span class="label <?= $badgeClass; ?>" style="margin-left:10px;"><?= strtoupper($risk); ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <label style="margin:0;">Quarter</label>
      <select
        name="q"
        id="js-term"
        class="form-control input-sm"
        style="width:auto;"
        onchange="(function(el){var u=location.href.split('?')[0]; location.href = u + '?q=' + encodeURIComponent(el.value);})(this)">
        <option value="0" <?= ($termSel===0) ? 'selected' : '' ?>>All</option>
        <option value="1" <?= ($termSel===1) ? 'selected' : '' ?>>Q1</option>
        <option value="2" <?= ($termSel===2) ? 'selected' : '' ?>>Q2</option>
        <option value="3" <?= ($termSel===3) ? 'selected' : '' ?>>Q3</option>
        <option value="4" <?= ($termSel===4) ? 'selected' : '' ?>>Q4</option>
      </select>
    </div>
  </div>

  <div class="panel-body">
    <div class="row" style="display:flex;gap:20px;align-items:flex-start;">
      <!-- Metrics -->
      <div class="col-md-4">
        <div style="font-size:14px; margin-bottom:8px;">Overall Progress</div>
        <div class="progress" style="height:18px;margin-bottom:10px;">
          <div class="progress-bar" role="progressbar"
              aria-valuenow="<?= $prog ?>" aria-valuemin="0" aria-valuemax="100"
              style="width: <?= $prog ?>%;">
            <?= $prog ?>%
          </div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <span class="label label-primary">Assigned: <?= (int)$assigned ?></span>
          <span class="label label-success">Completed: <?= (int)$completed ?></span>
          <span class="label label-default">Below 80%: <?= (int)$below80 ?></span>
        </div>

        <div style="margin-top:10px; display:flex; flex-direction:column; gap:8px; align-items:flex-start;">
          <?php if ($termSel): ?>
            <span class="label label-default" style="font-size:13px; padding:6px 10px;">Term <?= (int)$termSel ?></span>
          <?php endif; ?>
          <span class="label label-default" style="font-size:13px; padding:6px 10px;">Absent: <strong><?= $tm_days ?></strong></span>
          <span class="label label-default" style="font-size:13px; padding:6px 10px;">Merits: <strong><?= $tm_merits ?></strong></span>
          <span class="label label-default" style="font-size:13px; padding:6px 10px;">Demerits: <strong><?= $tm_demerits ?></strong></span>
          <span class="label label-default" style="font-size:13px; padding:6px 10px;">Avg Pages/Day: <strong><?= number_format($tm_pages, 1) ?></strong></span>
        </div>
      </div>

      <!-- Notes -->
      <div class="col-md-8">
        <div class="row">
          <div class="col-sm-6" style="margin-bottom:10px;">
            <label>Pain Points</label>
            <textarea class="form-control js-ov" data-field="pain_points" rows="3"
              placeholder="e.g. Vocabulary, time management, test anxiety"><?= htmlspecialchars($ov['pain_points'] ?? '') ?></textarea>
          </div>
          <div class="col-sm-6" style="margin-bottom:10px;">
            <label>Merits</label>
            <textarea class="form-control js-ov" data-field="merits" rows="3"
              placeholder="e.g. Helpful, diligent, scripture memory"><?= htmlspecialchars($ov['merits'] ?? '') ?></textarea>
          </div>
          <div class="col-sm-6" style="margin-bottom:10px;">
            <label>Involvement</label>
            <textarea class="form-control js-ov" data-field="involvement" rows="3"
              placeholder="e.g. Choir, soccer, chapel participation"><?= htmlspecialchars($ov['involvement'] ?? '') ?></textarea>
          </div>
          <div class="col-sm-6" style="margin-bottom:10px;">
            <label>Achievements</label>
            <textarea class="form-control js-ov" data-field="achievements" rows="3"
              placeholder="e.g. AASC medal, improved averages, leadership award"><?= htmlspecialchars($ov['achievements'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  // Safer echoes (properly quoted + casted)
  var CSRF_NAME    = <?= json_encode($csrfName) ?>;
  var CSRF_HASH    = <?= json_encode($csrfHash) ?>;
  var STUDENT_ID   = <?= (int)$student['id'] ?>;
  var SELECTED_TERM = <?= (int)$termSel ?>;

  function saveField(field, value) {
    var data = {};
    data[CSRF_NAME] = CSRF_HASH;
    data.student_id = STUDENT_ID;
    data.field      = field;
    data.value      = value;
    data.term       = SELECTED_TERM;

    return $.ajax({
      type: 'POST',
      url: <?= json_encode(base_url('student/save_overview_field')) ?>,
      data: data,
      dataType: 'json'
    }).done(function (res) {
      if (res && res[CSRF_NAME]) CSRF_HASH = res[CSRF_NAME];
      if (!(res && (res.success === true || res.success === 1 || res.success === '1'))) {
        alert('Save failed.');
      }
    }).fail(function () { alert('Network error.'); });
  }

  $('.js-ov').on('blur', function () {
    var $t = $(this);
    saveField($t.data('field'), $t.val());
  });
})();
</script>

<?php echo "<!-- PP dbg role_id={$role_id} slug={$role_slug} parent_only=" . (!empty($is_parent_only)?1:0) . " -->"; ?>

<?php if (true /* TEMP: force visible while we debug */): ?>


<?php
/* =================== Projection Planner: role gating + safe defaults =================== */
$role_raw = strtolower((string)(
    $this->session->userdata('role')
 ?? $this->session->userdata('role_name')
 ?? $this->session->userdata('loggedin_role')
 ?? ''
));
$role_id  = (int) (
    $this->session->userdata('loggedin_role_id')
 ?? $this->session->userdata('role_id')
 ?? 0
);

$IS_SUPER   = function_exists('is_superadmin_loggedin') ? (bool)is_superadmin_loggedin() : ($role_id === 1 || $role_raw === 'superadmin');
$IS_ADMIN   = function_exists('is_admin_loggedin')      ? (bool)is_admin_loggedin()      : ($role_id === 2 || $role_raw === 'admin');
$IS_PARENT  = function_exists('is_parent_loggedin')     ? (bool)is_parent_loggedin()     : ($role_id === 6 || $role_raw === 'parent');
$looks_like_teacher = (strpos($role_raw,'teacher') !== false || strpos($role_raw,'staff') !== false);
$IS_TEACHER = ($looks_like_teacher || in_array($role_id, [3,4,5,10], true));
$IS_STUDENT = function_exists('is_student_loggedin') ? (bool)is_student_loggedin() : ($role_id === 7 || $role_raw === 'student');

$is_parent_only = $IS_PARENT && !($IS_SUPER || $IS_ADMIN || $IS_TEACHER);
$can_view_proj  = !$is_parent_only && ($IS_SUPER || $IS_ADMIN || $IS_TEACHER || $IS_STUDENT);
$proj_can_edit  = isset($proj_can_edit) ? (bool)$proj_can_edit : ($IS_TEACHER || $IS_STUDENT);

/* fallbacks so the block never fatals */
$proj_year   = isset($proj_year) ? $proj_year : date('Y');
$proj_rows   = (isset($proj_rows) && is_array($proj_rows)) ? $proj_rows : [];
$proj_pace_options_map = isset($proj_pace_options_map) && is_array($proj_pace_options_map) ? $proj_pace_options_map : [];
$proj_progress_map     = isset($proj_progress_map) && is_array($proj_progress_map) ? $proj_progress_map : [];
$proj_completed_map    = isset($proj_completed_map) && is_array($proj_completed_map) ? $proj_completed_map : [];

/* CSRF */
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();

/* debug hint (view source to see) */
echo "<!-- PP dbg role_id={$role_id} role={$role_raw} view=" . ($can_view_proj?1:0) . " edit=" . ($proj_can_edit?1:0) . " -->";
?>

<?php if ($can_view_proj): ?>
<!-- ====================== PROJECTION PLANNER (12 PACE slots) ====================== -->
<div class="panel panel-default" id="projection-planner">
  <div class="panel-heading" style="display:flex;align-items:center;justify-content:space-between;">
    <div class="panel-title">Projection Planner — <?= html_escape($proj_year); ?></div>
    <?php if ($proj_can_edit): ?>
      <button type="button" class="btn btn-primary btn-xs" id="btnPPsave">
        <i class="fas fa-save"></i> Save
      </button>
    <?php endif; ?>
  </div>

  <div class="panel-body">
    <?php
  $rows     = $proj_rows;
  $readonly = !$proj_can_edit;

  // ---- RECALL: load committed grid from DB and normalize to 12 slots ----
  $proj_committed_map = [];
  $grid__ = $this->Projection_model->get_projection_grid((int)$student['id'], (int)$proj_year);
  if (is_array($grid__)) {
      foreach ($grid__ as $sid__ => $row__) {
          $sid__ = (int)$sid__;
          $arr__ = [];
          for ($ii__ = 1; $ii__ <= 12; $ii__++) {
              $k__ = 'p' . $ii__;
              $arr__[$ii__ - 1] = (isset($row__[$k__]) && $row__[$k__] !== '') ? (int)$row__[$k__] : '';
          }
          $proj_committed_map[$sid__] = $arr__;
      }
  }
?>

    <?php if (empty($rows)): ?>
      <div class="alert alert-info text-center">
        <i class="fas fa-info-circle"></i> No subjects found for this student.
      </div>
    <?php else: ?>

    <form id="ppForm">
      <input type="hidden" name="student_id" value="<?= (int)($student['id'] ?? 0); ?>">
      <input type="hidden" name="year" value="<?= (int)$proj_year; ?>">
      <input type="hidden" name="<?= $csrfName; ?>" value="<?= $csrfHash; ?>">

      <div class="table-responsive">
        <table class="table table-bordered table-condensed">
          <thead>
            <tr>
              <th style="min-width:200px;">Subject</th>
              <?php for ($i=1;$i<=12;$i++): ?>
                <th style="width:80px; text-align:center;">P<?= $i; ?></th>
              <?php endfor; ?>
              <th style="width:160px;">Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $sid       = (int)($r['subject_id'] ?? 0);
              $sname     = (string)($r['subject_name'] ?? '');
               $plist_raw = isset($r['pacelist']) && is_array($r['pacelist']) ? array_values($r['pacelist']) : [];
  // ---- RECALL override: if DB has committed values for this subject, use them ----
  if (isset($proj_committed_map[$sid]) && is_array($proj_committed_map[$sid])) {
      $plist_raw = array_values($proj_committed_map[$sid]);
  }
  $plist = [];
  for ($i=0;$i<12;$i++) { $plist[$i] = $plist_raw[$i] ?? ''; }
              $opts      = $proj_pace_options_map[$sid] ?? [];
              $prog      = (int)($proj_progress_map[$sid] ?? 0);
              $completed = (int)($proj_completed_map[$sid] ?? 0);
            ?>
            <tr data-sid="<?= $sid; ?>">
              <td><strong><?= html_escape($sname); ?></strong></td>

              <?php for ($i=0;$i<12;$i++):
                $val  = $plist[$i];
                $name = "projections[{$sid}][p".($i+1)."]";
              ?>
              <td>
                <?php if ($readonly): ?>
                  <input type="text" class="form-control input-sm" value="<?= ($val !== '' ? (int)$val : '') ?>" readonly>
                <?php else: ?>
                  <?php if (!empty($opts)): ?>
                    <select class="form-control input-sm js-pace" name="<?= $name; ?>">
                      <option value=""></option>
                      <?php foreach ($opts as $o): $o = (int)$o; ?>
                        <option value="<?= $o; ?>" <?= ($val !== '' && (int)$val === $o) ? 'selected' : '' ?>><?= $o; ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <input type="number" class="form-control input-sm js-pace" name="<?= $name; ?>" value="<?= ($val !== '' ? (int)$val : '') ?>" min="0">
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <?php endfor; ?>

              <td>
                <div class="progress" style="margin:0;">
                  <div class="progress-bar" role="progressbar"
                       aria-valuenow="<?= $prog; ?>" aria-valuemin="0" aria-valuemax="100"
                       style="width: <?= $prog; ?>%;">
                    <?= $prog; ?>%
                  </div>
                </div>
                <small class="text-muted">
                  Completed: <span class="js-done"><?= $completed; ?></span>
                </small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php
// compute initial totals from server so it also shows in read-only views
$__totPlanned = 0;
$__totCompleted = 0;
foreach (($rows ?? []) as $__r) {
    $__plist = is_array($__r['pacelist'] ?? null) ? array_values($__r['pacelist']) : [];
    $__plannedRow = 0;
    foreach ($__plist as $__pv) { if ($__pv !== '' && $__pv !== null) $__plannedRow++; }
    $__totPlanned += $__plannedRow;
    $__sid = (int)($__r['subject_id'] ?? 0);
    $__totCompleted += (int)($proj_completed_map[$__sid] ?? 0);
}
$__pacesLeft = max(0, $__totPlanned - $__totCompleted);
?>

<?php
  // Recall saved Pages Planner meta (defaults preserved)
  $pp_avg_pages  = 27;
  $pp_weeks_left = 10;
  $__meta = $this->Projection_model->get_projection_meta((int)$student['id'], (int)$proj_year);
  if (is_array($__meta)) {
      if (isset($__meta['avg_pages_per_pace']) && $__meta['avg_pages_per_pace'] !== '')
          $pp_avg_pages = (int)$__meta['avg_pages_per_pace'];
      if (isset($__meta['weeks_left']) && $__meta['weeks_left'] !== '')
          $pp_weeks_left = (int)$__meta['weeks_left'];
  }
?>

<hr>

<div class="row" id="ppSummary" style="margin-top:10px;">
  <div class="col-sm-6">
    <div class="well well-sm" style="margin-bottom:10px;">
      <strong>PACE Summary</strong>
      <div style="margin-top:6px; display:flex; gap:10px; flex-wrap:wrap;">
        <span class="label label-primary">Planned: <span id="ppPlanned"><?= (int)$__totPlanned ?></span></span>
        <span class="label label-success">Completed: <span id="ppCompleted"><?= (int)$__totCompleted ?></span></span>
        <span class="label label-default">Paces Left: <span id="ppLeft"><?= (int)$__pacesLeft ?></span></span>
      </div>
    </div>
  </div>

  <div class="col-sm-6">
    <div class="well well-sm" style="margin-bottom:10px;">
      <strong>Pages Planner</strong>
      <div class="row" style="margin-top:6px;">
        <div class="col-xs-6">
          <label style="margin-bottom:2px;">Avg pages / PACE</label>
          <input ... id="ppAvgPages" name="planner_avg_pages"  value="<?=$pp_avg_pages?>">
        </div>
        <div class="col-xs-6">
          <label style="margin-bottom:2px;">Weeks Left</label>
          <input ... id="ppWeeks"    name="planner_weeks_left" value="<?=$pp_weeks_left?>">
        </div>
      </div>

      <div style="margin-top:10px; line-height:1.7;">
        <div>PACES LEFT × Avg pages = <strong>Total Pages</strong> → <span id="ppTotalPages">0</span></div>
        <div><em>Total Pages ÷ (Weeks × 5 days)</em> = <strong>Pages / Day</strong> → <span id="ppPagesPerDay">0</span></div>
        <small class="text-muted">Assumes 5 school days per week.</small>
      </div>
    </div>
  </div>
</div>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php /* --- JS for Projection Planner (manual Save only) --- */ ?>
<script>
(function(){
  var READONLY   = <?= !empty($readonly) ? 'true' : 'false' ?>;
  var CSRF_NAME  = <?= json_encode($this->security->get_csrf_token_name()) ?>;

  // --- Pages Planner calculator (runs always) ---
  function ppCompute() {
    // Planned = non-empty P slots; Completed = sum of ".js-done" per row
    var planned = 0, completed = 0;
    document.querySelectorAll('#projection-planner tbody tr').forEach(function(tr){
      var rowPlanned = 0;
      tr.querySelectorAll('select.js-pace, input.js-pace').forEach(function(inp){
        var v = inp.value;
        if (v !== '' && v != null) rowPlanned++;
      });
      planned += rowPlanned;

      var doneEl = tr.querySelector('.js-done');
      var doneVal = parseInt((doneEl && doneEl.textContent) ? doneEl.textContent : '0', 10) || 0;
      completed += doneVal;
    });
    var left = Math.max(0, planned - completed);

    // Update badges
    var eP = document.getElementById('ppPlanned');   if (eP) eP.textContent = String(planned);
    var eC = document.getElementById('ppCompleted'); if (eC) eC.textContent = String(completed);
    var eL = document.getElementById('ppLeft');      if (eL) eL.textContent = String(left);

    // Totals
    var avg   = parseInt((document.getElementById('ppAvgPages')||{value:'0'}).value || '0', 10) || 0;
    var weeks = parseInt((document.getElementById('ppWeeks')   ||{value:'0'}).value || '0', 10) || 0;
    var total = left * avg;
    var days  = weeks * 5;
    var ppd   = days > 0 ? Math.ceil(total / days) : 0;

    var tpEl  = document.getElementById('ppTotalPages');  if (tpEl) tpEl.textContent  = String(total);
    var pdEl  = document.getElementById('ppPagesPerDay'); if (pdEl) pdEl.textContent  = String(ppd);
  }

  // Bind changes for grid + planner inputs
  document.querySelectorAll('#projection-planner select.js-pace, #projection-planner input.js-pace')
    .forEach(function(el){ el.addEventListener('change', ppCompute); el.addEventListener('input', ppCompute); });
  var _avg = document.getElementById('ppAvgPages'); if (_avg) _avg.addEventListener('input', ppCompute);
  var _wk  = document.getElementById('ppWeeks');    if (_wk)  _wk.addEventListener('input', ppCompute);

  // Initial calculation after recall
  ppCompute();

  // --- Save button (unchanged) ---
  var btn = document.getElementById('btnPPsave');
  if (!READONLY && btn) {
    btn.addEventListener('click', function(){
      var form = document.getElementById('ppForm');
      if (!form) return;

      var fd   = new FormData(form);
      var csrf = form.querySelector('[name="'+CSRF_NAME+'"]');
      if (csrf) fd.set(CSRF_NAME, csrf.value);

      fetch(<?= json_encode(site_url('student/save_projection')); ?>, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res && res[CSRF_NAME] && csrf) csrf.value = res[CSRF_NAME];
        if (res && (res.status === true || res.status === 1 || res.status === '1')) {
          if (window.Swal && Swal.fire) Swal.fire({icon:'success', title:'Saved', timer:1200, showConfirmButton:false});
        } else {
          var msg = (res && res.message) ? res.message : 'Save failed.';
          if (window.Swal && Swal.fire) Swal.fire({icon:'error', title:'Oops', text: msg});
        }
      })
      .catch(function(){
        if (window.Swal && Swal.fire) Swal.fire({icon:'error', title:'Error', text:'Network or server error.'});
      });
    });
  }
})();
</script>
<?php endif; /* $can_view_proj */ ?>


		<div class="panel-group" id="accordion">
            <!-- student profile information user Interface -->
			<div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
                        <div class="auth-pan">
                            <button class="btn btn-default btn-circle" <?php echo $student['active'] == 0 ? 'disabled' : '' ?> id="authentication_btn">
                                <?php if ($student['active'] == 1) { ?><i class="fas fa-unlock-alt"></i> <?=translate('authentication')?> <?php } else { ?><i class="fas fa-lock"></i> <?=translate('deactivated')?> <?php } ?>
                            </button>
                        </div>
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#profile">
							<i class="fas fa-user-edit"></i> <?=translate('basic_details')?>
						</a>
					</h4>
				</div>
				<div id="profile" class="accordion-body collapse <?=($this->session->flashdata('profile_tab') == 1 ? 'in' : ''); ?>">
					<?php echo form_open_multipart($this->uri->uri_string()); ?>
					<input type="hidden" name="student_id" value="<?php echo $student['id']; ?>" id="student_id">
					<div class="panel-body">
						<!-- academic details-->
						<div class="headers-line">
							<i class="fas fa-school"></i> <?=translate('academic_details')?>
						</div>
<?php
$roll = $this->student_fields_model->getStatus('roll', $branchID);
$admission_date = $this->student_fields_model->getStatus('admission_date', $branchID);
$v = (2 + floatval($roll['status']) + floatval($admission_date['status']));
$div = floatval(12 / $v);
?>
						<div class="row">
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('academic_year')?> <span class="required">*</span></label>
									<?php
										$arrayYear = array("" => translate('select'));
										$years = $this->db->get('schoolyear')->result();
										foreach ($years as $year){
											$arrayYear[$year->id] = $year->school_year;
										}
										echo form_dropdown("year_id", $arrayYear, set_value('year_id', $student['session_id']), "class='form-control' id='academic_year_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"><?=form_error('year_id')?></span>
								</div>
							</div>

							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('register_no')?> <span class="required">*</span></label>
									<input type="text" class="form-control" name="register_no" value="<?=set_value('register_no', $student['register_no'])?>" />
									<span class="error"><?=form_error('register_no')?></span>
								</div>
							</div>
<?php if ($roll['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('roll')?><?php echo $roll['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="roll" value="<?=set_value('roll', $student['roll'])?>" />
									<span class="error"><?=form_error('roll')?></span>
								</div>
							</div>
<?php } if ($admission_date['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('admission_date')?><?php echo $admission_date['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="far fa-calendar-alt"></i></span>
										<input type="text" class="form-control" name="admission_date"
										value="<?=set_value('admission_date', $student['admission_date'])?>" data-plugin-datepicker data-plugin-options='{ "todayHighlight" : true }' />
									</div>
									<span class="error"><?=form_error('admission_date')?></span>
								</div>
							</div>
<?php } ?>
						</div>
<?php
	$category = $this->student_fields_model->getStatus('category', $branchID);
	if (is_superadmin_loggedin()) {
		$v = (3 + floatval($category['status']));
	} else {
		$v = (2 + floatval($category['status']));
	}
	$div = floatval(12 / $v);
?>
						<div class="row mb-md">
							<?php if (is_superadmin_loggedin()): ?>
							<div class="col-md-<?php echo $div; ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
									<?php
										$arrayBranch = $this->app_lib->getSelectList('branch');
										echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id', $student['branch_id']), "class='form-control' id='branch_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
									?>
									<span class="error"><?=form_error('branch_id')?></span>
								</div>
							</div>
							<?php endif; ?>
							<div class="col-md-<?php echo $div; ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
									<?php
										$arrayClass = $this->app_lib->getClass($branchID);
										echo form_dropdown("class_id", $arrayClass, set_value('class_id', $student['class_id']), "class='form-control' id='class_id'
										onchange='getSectionByClass(this.value,0)' data-plugin-selectTwo data-width=".'100%'." data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"><?=form_error('class_id')?></span>
								</div>
							</div>
							<div class="col-md-<?php echo $div; ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
									<?php
										$arraySection = $this->app_lib->getSections(set_value('class_id', $student['class_id']), true);
										echo form_dropdown("section_id", $arraySection, set_value('section_id', $student['section_id']), "class='form-control' id='section_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"><?=form_error('section_id')?></span>
								</div>
							</div>
<?php if ($category['status']) { ?>
							<div class="col-md-<?php echo $div; ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('category')?><?php echo $category['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<?php
										$arrayCategory = $this->app_lib->getStudentCategory($branchID);
										echo form_dropdown("category_id", $arrayCategory, set_value('category_id', $student['category_id']), "class='form-control'
										data-plugin-selectTwo data-width='100%' id='category_id' data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"><?=form_error('category_id')?></span>
								</div>
							</div>
<?php } ?>
						</div>

						<!-- student details -->
						<div class="headers-line mt-md">
							<i class="fas fa-user-check"></i> <?=translate('student_details')?>
						</div>
<?php
$last_name = $this->student_fields_model->getStatus('last_name', $branchID);
$gender = $this->student_fields_model->getStatus('gender', $branchID);
$v = (1 + floatval($last_name['status']) + floatval($gender['status']));
$div = floatval(12 / $v);
?>
						<div class="row">
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('first_name')?> <span class="required">*</span></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="fas fa-user-graduate"></i></span>
										<input type="text" class="form-control" name="first_name" value="<?=set_value('first_name', $student['first_name'])?>"/>
									</div>
									<span class="error"><?=form_error('first_name')?></span>
								</div>
							</div>
<?php if ($last_name['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('last_name')?><?php echo $last_name['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="fas fa-user-graduate"></i></span>
										<input type="text" class="form-control" name="last_name" value="<?=set_value('last_name', $student['last_name'])?>" />
									</div>
									<span class="error"><?=form_error('last_name')?></span>
								</div>
							</div>
<?php } if ($gender['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('gender')?><?php echo $gender['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<?php
										$arrayGender = array(
											'male' => translate('male'),
											'female' => translate('female')
										);
										echo form_dropdown("gender", $arrayGender, set_value('gender', $student['gender']), "class='form-control'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"><?=form_error('gender')?></span>
								</div>
							</div>
<?php } ?>
						</div>

						<div class="row">
<?php
$blood_group = $this->student_fields_model->getStatus('blood_group', $branchID);
$birthday = $this->student_fields_model->getStatus('birthday', $branchID);
$v = floatval($blood_group['status']) + floatval($birthday['status']);
$div = ($v == 0) ? 12 : floatval(12 / $v);
	if ($blood_group['status']) {
?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('blood_group')?><?php echo $blood_group['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<?php
										$bloodArray = $this->app_lib->getBloodgroup();
										echo form_dropdown("blood_group", $bloodArray, set_value("blood_group", $student['blood_group']), "class='form-control populate' data-plugin-selectTwo
										data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"><?=form_error('blood_group')?></span>
								</div>
							</div>
<?php } if ($birthday['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('birthday')?><?php echo $birthday['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="fas fa-birthday-cake"></i></span>
										<input type="text" class="form-control" name="birthday" value="<?=set_value('birthday', $student['birthday'])?>" data-plugin-datepicker
										data-plugin-options='{ "startView": 2 }' />
									</div>
									<span class="error"><?=form_error('birthday')?></span>
								</div>
							</div>
<?php } ?>
						</div>

						<div class="row">
<?php
$mother_tongue = $this->student_fields_model->getStatus('mother_tongue', $branchID);
$religion = $this->student_fields_model->getStatus('religion', $branchID);
$caste = $this->student_fields_model->getStatus('caste', $branchID);
$v = floatval($mother_tongue['status']) + floatval($religion['status']) + floatval($caste['status']);
$div = ($v == 0) ? 12 : floatval(12 / $v);
	if ($mother_tongue['status']) {
?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('mother_tongue')?><?php echo $mother_tongue['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="mother_tongue" value="<?=set_value('mother_tongue', $student['mother_tongue'])?>" />
									<span class="error"><?=form_error('mother_tongue')?></span>
								</div>
							</div>
<?php } if ($religion['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('religion')?><?php echo $religion['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="religion" value="<?=set_value('religion', $student['religion'])?>" />
									<span class="error"><?=form_error('religion')?></span>
								</div>
							</div>
<?php } if ($caste['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('caste')?><?php echo $caste['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="caste" value="<?=set_value('caste', $student['caste'])?>" />
									<span class="error"><?=form_error('caste')?></span>
								</div>
							</div>
<?php } ?>
						</div>

						<div class="row">
<?php
$student_mobile_no = $this->student_fields_model->getStatus('student_mobile_no', $branchID);
$student_email = $this->student_fields_model->getStatus('student_email', $branchID);
$city = $this->student_fields_model->getStatus('city', $branchID);
$state = $this->student_fields_model->getStatus('state', $branchID);

$v = floatval($student_mobile_no['status']) + floatval($student_email['status']) + floatval($city['status'])  + floatval($state['status']);
$div = ($v == 0) ? 12 : floatval(12 / $v);
if ($student_mobile_no['status']) {
?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('mobile_no')?><?php echo $student_mobile_no['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="fas fa-phone-volume"></i></span>
										<input type="text" class="form-control" name="mobileno" value="<?=set_value('mobileno', $student['mobileno'])?>" />
									</div>
									<span class="error"><?=form_error('mobileno')?></span>
								</div>
							</div>
<?php } if ($student_email['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('email')?><?php echo $student_email['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="far fa-envelope-open"></i></span>
										<input type="text" class="form-control" name="email" id="email" value="<?=set_value('email', $student['email'])?>" />
									</div>
									<span class="error"><?=form_error('email')?></span>
								</div>
							</div>
<?php } if ($city['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('city')?><?php echo $city['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="city" value="<?=set_value('city', $student['city'])?>" />
									<span class="error"><?=form_error('city')?></span>
								</div>
							</div>
<?php } if ($state['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('state')?><?php echo $state['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="state" value="<?=set_value('state', $student['state'])?>" />
									<span class="error"><?=form_error('state')?></span>
								</div>
							</div>
<?php } ?>
						</div>

						<div class="row">
<?php
$present_address = $this->student_fields_model->getStatus('present_address', $branchID);
$permanent_address = $this->student_fields_model->getStatus('permanent_address', $branchID);
$v = floatval($present_address['status']) + floatval($permanent_address['status']);
$div = ($v == 0) ? 12 : floatval(12 / $v);

if ($present_address['status']) {
?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('present_address')?><?php echo $present_address['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<textarea name="current_address" rows="2" class="form-control" aria-required="true"><?=set_value('current_address', $student['current_address'])?></textarea>
									<span class="error"><?=form_error('current_address')?></span>
								</div>
							</div>
<?php } if ($permanent_address['status']) { ?>
							<div class="col-md-<?php echo $div ?> mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('permanent_address')?><?php echo $permanent_address['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<textarea name="permanent_address" rows="2" class="form-control" aria-required="true"><?=set_value('permanent_address', $student['permanent_address'])?></textarea>
									<span class="error"><?=form_error('permanent_address')?></span>
								</div>
							</div>
<?php } ?>
						</div>

						<!--custom fields details-->
						<div class="row" id="customFields">
							<?php echo render_custom_Fields('student', $student['branch_id'], $student['id']); ?>
						</div>

						<div class="row">
<?php
$student_photo = $this->student_fields_model->getStatus('student_photo', $branchID);
if ($student_photo['status']) {
?>
							<div class="col-md-12 mb-sm">
								<div class="form-group">
									<label for="input-file-now"><?=translate('profile_picture')?><?php echo $student_photo['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="file" name="user_photo" class="dropify" data-default-file="<?=get_image_url('student', $student['photo'])?>" />
									<input type="hidden" name="old_user_photo" value="<?php echo $student['photo']; ?>" />
								</div>
								<span class="error"><?=form_error('user_photo')?></span>
							</div>
<?php } ?>
						</div>

						<!-- login details -->
						<div class="headers-line mt-md">
							<i class="fas fa-user-lock"></i> <?=translate('login_details')?>
						</div>

						<div class="row mb-md">
							<div class="col-md-12 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('username')?> <span class="required">*</span></label>
									<div class="input-group">
										<span class="input-group-addon"><i class="far fa-user"></i></span>
										<input type="text" class="form-control" name="username" id="username" value="<?=set_value('username', $student['username'])?>" />
									</div>
									<span class="error"><?=form_error('username')?></span>
								</div>
							</div>
						</div>

						<!--guardian details-->
						<div class="headers-line">
							<i class="fas fa-user-tie"></i> <?=translate('guardian_details')?>
						</div>
						<div class="row mb-md">
							<div class="col-md-12 mb-md">
								<label class="control-label"><?=translate('guardian')?> <span class="required">*</span></label>
								<div class="form-group">
									<?php
										$arrayParent = $this->app_lib->getSelectByBranch('parent', $branchID);
										echo form_dropdown("parent_id", $arrayParent, set_value('parent_id', $student['parent_id']), "class='form-control' id='parent_id'
										data-plugin-selectTwo ");
									?>
									<span class="error"><?=form_error('parent_id')?></span>
								</div>
							</div>
						</div>

					<?php if (moduleIsEnabled('transport')) { ?>
						<!-- transport details -->
						<div class="headers-line">
							<i class="fas fa-bus-alt"></i> <?=translate('transport_details')?>
						</div>

						<div class="row mb-md">
							<div class="col-md-6 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('transport_route')?></label>
									<?php
										$arrayRoute = $this->app_lib->getSelectByBranch('transport_route', $branchID);
										echo form_dropdown("route_id", $arrayRoute, set_value('route_id', $student['route_id']), "class='form-control' id='route_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
								</div>
							</div>
							<div class="col-md-6 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('vehicle_no')?></label>
									<?php
										$arrayVehicle = $this->app_lib->getVehicleByRoute(set_value('route_id', $student['route_id']));
										echo form_dropdown("vehicle_id", $arrayVehicle, set_value('vehicle_id', $student['vehicle_id']), "class='form-control' id='vehicle_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
								</div>
							</div>
						</div>
					<?php } ?>
					<?php if (moduleIsEnabled('hostel')) { ?>
						<!-- hostel details -->
						<div class="headers-line">
							<i class="fas fa-hotel"></i> <?=translate('hostel_details')?>
						</div>

						<div class="row mb-md">
							<div class="col-md-6 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('hostel_name')?></label>
									<?php
										$arrayHostel = $this->app_lib->getSelectByBranch('hostel', $branchID);
										echo form_dropdown("hostel_id", $arrayHostel, set_value('hostel_id', $student['hostel_id']), "class='form-control' id='hostel_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
								</div>
							</div>
							<div class="col-md-6 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('room_name')?></label>
									<?php
										$arrayRoom = $this->app_lib->getRoomByHostel(set_value('hostel_id', $student['hostel_id']));
										echo form_dropdown("room_id", $arrayRoom, set_value('room_id', $student['room_id']), "class='form-control' id='room_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
								</div>
							</div>
						</div>
					<?php } ?>
<?php
$previous_school_details = $this->student_fields_model->getStatus('previous_school_details', $branchID);
if ($previous_school_details['status']) {
?>
						<!-- previous school details -->
						<div class="headers-line">
							<i class="fas fa-bezier-curve"></i> <?=translate('previous_school_details')?>
						</div>
						<div class="row">
							<div class="col-md-6 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('school_name')?><?php echo $previous_school_details['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="school_name" value="<?=set_value('school_name', $previous_details['school_name'])?>" />
									<span class="error"><?=form_error('school_name')?></span>
								</div>
							</div>
							<div class="col-md-6 mb-sm">
								<div class="form-group">
									<label class="control-label"><?=translate('qualification')?><?php echo $previous_school_details['required'] == 1 ? ' <span class="required">*</span>' : ''; ?></label>
									<input type="text" class="form-control" name="qualification" value="<?=set_value('qualification', $previous_details['qualification'])?>" />
									<span class="error"><?=form_error('qualification')?></span>
								</div>
							</div>
						</div>
						<div class="row mb-lg">
							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label"><?=translate('remarks')?></label>
									<textarea name="previous_remarks" rows="2" class="form-control"><?=set_value('previous_remarks', $previous_details['remarks'])?></textarea>
								</div>
							</div>
						</div>
<?php } ?>
					</div>

					<div class="panel-footer">
						<div class="row">
							<div class="col-md-offset-9 col-md-3">
								<button type="submit" name="update" value="1" class="btn btn-default btn-block"><?=translate('update')?></button>
							</div>
						</div>
					</div>
					</form>
				</div>
			</div>
<?php if (get_permission('collect_fees', 'is_view')) { ?>
			<!-- student fees report user Interface -->
            <div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<?php if (get_permission('collect_fees', 'is_add')) { ?>
						<div class="auth-pan">
							<a href="<?php echo base_url('fees/invoice/' . $student['enrollid']);?>" class="btn btn-default btn-circle btn-collect-fees">
								<i class="fas fa-dollar-sign"></i> <?=translate('collect_fees')?>
							</a>
						</div>
						<?php } ?>
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#fees">
							<i class="fas fa-money-check"></i> <?=translate('fees')?>
						</a>
					</h4>
				</div>
				<div id="fees" class="accordion-body collapse">
					<div class="panel-body">
						<div class="table-responsive mt-md mb-md">
							<table class="table table-bordered table-condensed table-hover mb-none tbr-top">
								<thead>
									<tr class="text-dark">
										<th>#</th>
										<th><?=translate("fees_type")?></th>
										<th><?=translate("due_date")?></th>
										<th><?=translate("status")?></th>
										<th><?=translate("amount")?></th>
										<th><?=translate("discount")?></th>
										<th><?=translate("fine")?></th>
										<th><?=translate("paid")?></th>
										<th><?=translate("balance")?></th>
									</tr>
								</thead>
								<tbody>
									<?php
										$count = 1;
										$total_fine = 0;
										$total_discount = 0;
										$total_paid = 0;
										$total_balance = 0;
										$total_amount = 0;
										$allocations = $this->fees_model->getInvoiceDetails($student['enrollid']);
										if (!empty($allocations)) {
										foreach ($allocations as $fee) {
											$deposit = $this->fees_model->getStudentFeeDeposit($fee['allocation_id'], $fee['fee_type_id']);
											$type_discount = $deposit['total_discount'];
											$type_fine = $deposit['total_fine'];
											$type_amount = $deposit['total_amount'];
											$balance = $fee['amount'] - ($type_amount + $type_discount);
											$total_discount += $type_discount;
											$total_fine += $type_fine;
											$total_paid += $type_amount;
											$total_balance += $balance;
											$total_amount += $fee['amount'];

										?>
									<tr>
										<td><?php echo $count++;?></td>
										<td><?=$fee['name']?></td>
										<td><?=_d($fee['due_date'])?></td>
										<td><?php
											$status = 0;
											$labelmode = '';
											if($type_amount == 0) {
												$status = translate('unpaid');
												$labelmode = 'label-danger-custom';
											} elseif($balance == 0) {
												$status = translate('total_paid');
												$labelmode = 'label-success-custom';
											} else {
												$status = translate('partly_paid');
												$labelmode = 'label-info-custom';
											}
											echo "<span class='label ".$labelmode." '>".$status."</span>";
										?></td>
										<td><?php echo currencyFormat($fee['amount']);?></td>
										<td><?php echo currencyFormat($type_discount);?></td>
										<td><?php echo currencyFormat($type_fine);?></td>
										<td><?php echo currencyFormat($type_amount);?></td>
										<td><?php echo currencyFormat($balance);?></td>
									</tr>
									<?php } } else {
										echo '<tr><td colspan="9"><h5 class="text-danger text-center">' . translate('no_information_available') . '</td></tr>';
									} ?>
								</tbody>
								<tfoot>
									<tr class="text-dark">
										<th></th>
										<th></th>
										<th></th>
										<th></th>
										<th><?php echo currencyFormat($total_amount); ?></th>
										<th><?php echo currencyFormat($total_discount); ?></th>
										<th><?php echo currencyFormat($total_fine); ?></th>
										<th><?php echo currencyFormat($total_paid); ?></th>
										<th><?php echo currencyFormat($total_balance); ?></th>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>
			</div>
<?php } ?>
<?php if (get_permission('student_promotion', 'is_view')) { ?>
			<!-- student promotion history Interface -->
            <div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#promotion">
							<i class="fas fa-arrow-trend-up"></i> <?=translate('promotion_history')?>
						</a>
					</h4>
				</div>
				<div id="promotion" class="accordion-body collapse">
					<div class="panel-body">
						<div class="table-responsive mb-md">
							<table class="table table-bordered table-hover table-condensed mb-none">
								<thead>
									<tr>
										<th width="50">#</th>
										<th><?=translate('from_class') . " / " . translate('section')?></th>
										<th><?=translate('from_session')?></th>
										<th><?=translate('promoted_class') . " / " . translate('section')?></th>
										<th><?=translate('promoted_session')?></th>
										<th><?=translate('due_amount')?></th>
										<th><?=translate('promoted_date')?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$label_leave = "<span class='text-danger'><b>" . translate('leave') . "</b></span>";
									$count = 1;
									$this->db->where(array('student_id' => $student['id']));
									$this->db->order_by('id', 'asc');
									$historys = $this->db->get('promotion_history')->result();
										if (count($historys)) {
											foreach($historys as $history):
												?>
										<tr>
											<td><?php echo $count++;?></td>
											<td><?php echo get_type_name_by_id('class', $history->pre_class) . " (" . get_type_name_by_id('section', $history->pre_section) . ")"; ?></td>
											<td><?php echo get_type_name_by_id('schoolyear', $history->pre_session, 'school_year'); ?></td>
											<td><?php echo get_type_name_by_id('class', $history->pro_class) . " (" . get_type_name_by_id('section', $history->pro_section) . ")"; ?></td>
											<td><?php echo $history->is_leave == 1 ? $label_leave : get_type_name_by_id('schoolyear', $history->pro_session, 'school_year'); ?></td>
											<td><?php echo currencyFormat($history->prev_due); ?></td>
											<td><?php echo _d($history->date);?></td>

										</tr>
									<?php
										endforeach;
									} else {
										echo '<tr><td colspan="7"><h5 class="text-danger text-center">' . translate('no_information_available') . '</td></tr>';
									}
									?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
<?php } ?>
			<!-- student book issued and return report user Interface -->
            <div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#books">
							<i class="fas fa-book-reader"></i> <?=translate('book_issue')?>
						</a>
					</h4>
				</div>
				<div id="books" class="accordion-body collapse">
					<div class="panel-body">
						<div class="table-responsive mt-md mb-md">
							<table class="table table-bordered table-hover table-condensed mb-none">
								<thead>
									<tr>
										<th width="50">#</th>
										<th><?=translate('book_title')?></th>
										<th><?=translate('date_of_issue')?></th>
										<th><?=translate('date_of_expiry')?></th>
										<th><?=translate('fine')?></th>
										<th><?=translate('status')?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$count = 1;
									$this->db->order_by('id', 'desc');
									$this->db->where(array('session_id' => get_session_id(), 'role_id' => 7, 'user_id' => $student['id']));
									$book_result = $this->db->get('book_issues')->result_array();
										if (count($book_result)) {
											foreach($book_result as $book):
												?>
										<tr>
											<td><?php echo $count++;?></td>
											<td><?php echo get_type_name_by_id('book', $book['book_id'], 'title');?></td>
											<td><?php echo _d($book['date_of_issue']);?></td>
											<td><?php echo _d($book['date_of_expiry']);?></td>
											<td>
												<?php
												if(empty($book['fine_amount'])){
													echo currencyFormat(0);
												} else {
													echo currencyFormat($book['fine_amount']);
												}
												?>
											</td>
											<td>
												<?php
												if($book['status'] == 0)
													echo '<span class="label label-warning-custom">' . translate('pending') . '</span>';
												if ($book['status'] == 1)
													echo '<span class="label label-success-custom">' . translate('issued') . '</span>';
												if($book['status'] == 2)
													echo '<span class="label label-danger-custom">' . translate('rejected') . '</span>';
												if($book['status'] == 3)
													echo '<span class="label label-primary-custom">' . translate('returned') . '</span>';
												?>
											</td>
										</tr>
									<?php
										endforeach;
									}else{
										echo '<tr><td colspan="6"><h5 class="text-danger text-center">' . translate('no_information_available') . '</td></tr>';
									}
									?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

            <div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#exam_result">
							<i class="fas fa-flask"></i> <?=translate('PACE Scores')?>
						</a>
					</h4>
				</div>
				<div id="exam_result" class="accordion-body collapse">
    <div class="panel-body">
        <?php
        $studentID = $student['id'];
        $this->db->select('s.*, subj.name as subject_name');
        $this->db->from('student_assign_paces as s');
        $this->db->join('subject as subj', 'subj.id = s.subject_id', 'left');
        $this->db->where('s.student_id', $studentID);
        $this->db->where('s.session_id', get_session_id());
        $this->db->order_by('subj.name ASC, s.pace_number ASC');
        $paces = $this->db->get()->result_array();
        ?>

        <?php if (!empty($paces)) : ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped mt-sm">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>PACE #</th>
                            <th>Term</th>
                            <th>First Attempt (%)</th>
                            <th>Second Attempt (%)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paces as $p) :
                            $status = '';
                            $s1 = (int)$p['first_attempt_score'];
                            $s2 = (int)$p['second_attempt_score'];
                            if ($s1 >= 80 || $s2 >= 80) {
                                $status = 'Pass';
                            } elseif ($s1 > 0 || $s2 > 0) {
                                $status = 'Retake';
                            } else {
                                $status = 'In Progress';
                            }
                        ?>
                        <tr>
                            <td><?= $p['subject_name'] ?></td>
                            <td><?= $p['pace_number'] ?></td>
                            <td><?= strtoupper($p['term']) ?></td>
                            <td><?= $s1 > 0 ? $s1 . '%' : '-' ?></td>
                            <td><?= $s2 > 0 ? $s2 . '%' : '-' ?></td>
                            <td><?= $status ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-subl text-center">
                <i class="fas fa-exclamation-triangle"></i> No PACE Test Scores Available
            </div>
        <?php endif; ?>
    </div>
</div>

            <!-- student parent information user Interface -->
			<div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#parent">
							<i class="fas fa-users"></i> <?=translate('parent_information')?>
						</a>
					</h4>
				</div>
				<div id="parent" class="accordion-body collapse">
					<div class="panel-body">
						<div class="table-responsive mt-md mb-md">
							<table class="table table-striped table-bordered table-condensed mb-none">
								<tbody>
									<tr>
										<th><?=translate('guardian_name')?></th>
										<td><?php echo $getParent['name']?></td>
										<th><?=translate('relation')?></th>
										<td><?php echo $getParent['relation']?></td>
									</tr>
									<tr>
										<th><?=translate('father_name')?></th>
										<td><?php echo $getParent['father_name']?></td>
										<th><?=translate('mother_name')?></th>
										<td><?php echo $getParent['mother_name']?></td>
									</tr>
                                    <tr>
                                        <td><?=translate('Father Mobile No')?></td>
                                        <td><?php echo html_escape($parent['father_mobileno'] ?? ''); ?></td>

                                        <td><?=translate('Mother Mobile No')?></td>
                                        <td><?php echo html_escape($parent['mother_mobileno'] ?? ''); ?></td>
                                    </tr>
									<tr>
                                        <td><?=translate('Father Email')?></td>
                                        <td><?php echo html_escape($parent['father_email'] ?? ''); ?></td>

                                        <td><?=translate('Mother Email')?></td>
                                        <td><?php echo html_escape($parent['mother_email'] ?? ''); ?></td>
                                 </tr>
									<tr class="quick-address">
										<th><?=translate('address')?></th>
										<td colspan="3" height="80px;"><?php echo $getParent['address']?></td>
									</tr>
									<tr>
										<th><?=translate('guardian_picture')?></th>
										<td colspan="3"><img class="img-border" width="100" height="100" src="<?=get_image_url('parent', $getParent['photo'])?>"></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

            <!-- sibling information Interface -->
			<div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#sibling">
							<i class="fa-solid fa-people-carry-box"></i> <?=translate('sibling_information')?>
						</a>
					</h4>
				</div>
				<div id="sibling" class="accordion-body collapse">
					<div class="panel-body">
						<div class="table-responsive mt-md mb-md">
							<table class="table table-bordered table-condensed table-hover">
								<thead>
									<tr>
										<th class="no-sort" width="80"><?=translate('photo')?></th>
										<th><?=translate('name')?></th>
										<th><?=translate('register_no')?></th>
										<th><?=translate('gender')?></th>
										<th><?=translate('class')?></th>
										<th><?=translate('section')?></th>
										<th><?=translate('roll')?></th>
										<th><?=translate('mobile_no')?></th>
									</tr>
									<tbody>
									<?php
									$getSiblingList = $this->student_model->getSiblingList($student['parent_id'], $student['id']);
									if (count($getSiblingList)) {
										foreach ($getSiblingList as $key => $row) {
										?>
										<tr>
											<td>
												<img class="img-border" width="70" height="70" src="<?php echo get_image_url('student', $row->photo) ?>">
											</td>
											<td><?php echo $row->fullname; ?></td>
											<td><?php echo $row->register_no; ?></td>
											<td><?php echo translate($row->gender) ?></td>
											<td><?php echo $row->class_name; ?></td>
											<td><?php echo $row->section_name; ?></td>
											<td><?php echo $row->roll; ?></td>
											<td><?php echo $row->mobileno; ?></td>
										</tr>
									<?php } } else {
										echo '<tr><td colspan="8"><h5 class="text-danger text-center">' . translate('no_information_available') . '</td></tr>';
									} ?>
									</tbody>
								</thead>
							</table>
						</div>
					</div>
				</div>
			</div>

            <!-- student parent information user Interface -->
			<div class="panel panel-accordion">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion" href="#documents">
							<i class="fas fa-folder-open"></i> <?=translate('documents')?>
						</a>
					</h4>
				</div>
				<div id="documents" class="accordion-body collapse">
                    <div class="panel-body">
                        <div class="text-right mb-sm">
                            <a href="javascript:void(0);" onclick="mfp_modal('#addStaffDocuments')" class="btn btn-circle btn-default mb-sm">
                                <i class="fas fa-plus-circle"></i> <?php echo translate('add') . " " . translate('document'); ?>
                            </a>
                        </div>
                        <div class="table-responsive mb-md">
                            <table class="table table-bordered table-hover table-condensed mb-none">
                            <thead>
                                <tr>
                                    <th><?php echo translate('sl'); ?></th>
                                    <th><?php echo translate('title'); ?></th>
                                    <th><?php echo translate('document') . " " . translate('type'); ?></th>
                                    <th><?php echo translate('file'); ?></th>
                                    <th><?php echo translate('remarks'); ?></th>
                                    <th><?php echo translate('created_at'); ?></th>
                                    <th><?php echo translate('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 1;
                                $this->db->where('student_id', $student['id']);
                                $documents = $this->db->get('student_documents')->result();
                                if (count($documents)) {
                                    foreach($documents as $row):
                                    	?>
                                <tr>
                                    <td><?php echo $count++?></td>
                                    <td><?php echo $row->title; ?></td>
                                    <td><?php echo $row->type; ?></td>
                                    <td><?php echo $row->file_name; ?></td>
                                    <td><?php echo $row->remarks; ?></td>
                                    <td><?php echo _d($row->created_at); ?></td>
                                    <td class="min-w-c">
                                        <a href="<?php echo base_url('student/documents_download?file=' . $row->enc_name); ?>" class="btn btn-default btn-circle icon" data-toggle="tooltip" data-original-title="<?=translate('download')?>">
                                            <i class="fas fa-cloud-download-alt"></i>
                                        </a>
                                        <a href="javascript:void(0);" class="btn btn-circle icon btn-default" onclick="editDocument('<?=$row->id?>', 'student')">
                                            <i class="fas fa-pen-nib"></i>
                                        </a>
                                        <?php echo btn_delete('student/document_delete/' . $row->id); ?>
                                    </td>
                                </tr>
                                <?php
                                    endforeach;
                                }else{
                                    echo '<tr> <td colspan="7"> <h5 class="text-danger text-center">' . translate('no_information_available') . '</h5> </td></tr>';
                                }
                                ?>
                            </tbody>
                            </table>
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- login authentication and account inactive modal -->
<div id="authentication_modal" class="zoom-anim-dialog modal-block modal-block-primary mfp-hide">
	<section class="panel">
		<header class="panel-heading">
			<h4 class="panel-title">
				<i class="fas fa-unlock-alt"></i> <?=translate('authentication')?>
			</h4>
		</header>
		<?php echo form_open('student/change_password', array('class' => 'frm-submit')); ?>
        <div class="panel-body">
        	<input type="hidden" name="student_id" value="<?=$student['id']?>">
            <div class="form-group">
	            <label for="password" class="control-label"><?=translate('password')?> <span class="required">*</span></label>
	            <div class="input-group">
	                <input type="password" class="form-control password" name="password" autocomplete="off" />
	                <span class="input-group-addon">
	                    <a href="javascript:void(0);" id="showPassword" ><i class="fas fa-eye"></i></a>
	                </span>
	            </div>
	            <span class="error"></span>
                <div class="checkbox-replace mt-lg">
                    <label class="i-checks">
                        <input type="checkbox" name="authentication" id="cb_authentication">
                        <i></i> <?=translate('login_authentication_deactivate')?>
                    </label>
                </div>
            </div>

			<div id="disableReason" style="display: none;">
				<div class="form-group">
					<label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
					<input type="text" class="form-control" name="date" value="<?=set_value('date', date('Y-m-d'))?>" data-plugin-datepicker data-plugin-options='{ "todayHighlight" : true }' />
					<span class="error"></span>
				</div>
	            <div class="form-group">
		            <label for="password" class="control-label"><?=translate('disable_reason')?> <span class="required">*</span></label>
					<?php
					$resultReason = $this->db->where('branch_id', $branchID)->get('disable_reason')->result();
					$arrayReason = array('' => translate('select'));
					foreach ($resultReason as $key => $value) {
						$arrayReason[$value->id] = $value->name;
					}
					echo form_dropdown("reason_id", $arrayReason, set_value('reason_id'), "class='form-control'
					data-plugin-selectTwo data-width='100%' id='reasonID' data-minimum-results-for-search='Infinity' ");
					?>
		            <span class="error"></span>
	            </div>
				<div class="form-group mb-lg">
					<label class="control-label"><?=translate('note')?></label>
					<textarea name="note" rows="2" class="form-control" aria-required="true"><?=set_value('note')?></textarea>
				</div>
			</div>
        </div>
        <footer class="panel-footer">
            <div class="text-right">
                <button type="submit" class="btn btn-default mr-xs" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing"><?=translate('update')?></button>
                <button class="btn btn-default modal-dismiss"><?=translate('close')?></button>
            </div>
        </footer>
        <?php echo form_close(); ?>
	</section>
</div>

<!-- Documents Details Add Modal -->
<div id="addStaffDocuments" class="zoom-anim-dialog modal-block modal-block-primary mfp-hide">
    <section class="panel">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="fas fa-plus-circle"></i> <?php echo translate('add') . " " . translate('document'); ?></h4>
        </div>
        <?php echo form_open_multipart('student/document_create', array('class' => 'form-horizontal frm-submit-data')); ?>
            <div class="panel-body">
                <input type="hidden" name="patient_id" value="<?php echo $student['id']; ?>">
                <div class="form-group mt-md">
                    <label class="col-md-3 control-label"><?php echo translate('title'); ?> <span class="required">*</span></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="document_title" id="adocument_title" value="" />
                        <span class="error"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-3 control-label"><?php echo translate('document') . " " . translate('type'); ?> <span class="required">*</span></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="document_category" id="adocument_category" value="" />
                        <span class="error"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-3 control-label"><?php echo translate('document') . " " . translate('file'); ?> <span class="required">*</span></label>
                    <div class="col-md-9">
                        <input type="file" name="document_file" class="dropify" data-height="110" data-default-file="" id="adocument_file" />
                        <span class="error"></span>
                    </div>
                </div>
                <div class="form-group mb-md">
                    <label class="col-md-3 control-label"><?php echo translate('remarks'); ?></label>
                    <div class="col-md-9">
                        <textarea class="form-control valid" rows="2" name="remarks"></textarea>
                    </div>
                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="submit" id="docsavebtn" class="btn btn-default" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
                            <i class="fas fa-plus-circle"></i> <?php echo translate('save'); ?>
                        </button>
                        <button class="btn btn-default modal-dismiss"><?php echo translate('cancel'); ?></button>
                    </div>
                </div>
            </footer>
        <?php echo form_close(); ?>
    </section>
</div>

<!-- Documents Details Edit Modal -->
<div id="editDocModal" class="zoom-anim-dialog modal-block modal-block-primary mfp-hide">
    <section class="panel">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="far fa-edit"></i> <?php echo translate('edit') . " " . translate('document'); ?></h4>
        </div>
        <?php echo form_open_multipart('student/document_update', array('class' => 'form-horizontal frm-submit-data')); ?>
            <div class="panel-body">
                <input type="hidden" name="document_id" id="edocument_id" value="">
                <div class="form-group mt-md">
                    <label class="col-md-3 control-label"><?php echo translate('title'); ?> <span class="required">*</span></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="document_title" id="edocument_title" value="" />
                        <span class="error"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-3 control-label"><?php echo translate('document') . " " . translate('type'); ?> <span class="required">*</span></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="document_category" id="edocument_category" value="" />
                        <span class="error"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-3 control-label"><?php echo translate('document') . " " . translate('file'); ?> <span class="required">*</span></label>
                    <div class="col-md-9">
                        <input type="file" name="document_file" class="dropify" data-height="120" data-default-file="">
                        <input type="hidden" name="exist_file_name" id="exist_file_name" value="">
                    </div>
                </div>
                <div class="form-group mb-md">
                    <label class="col-md-3 control-label"><?php echo translate('remarks'); ?></label>
                    <div class="col-md-9">
                        <textarea class="form-control valid" rows="2" name="remarks" id="edocuments_remarks"></textarea>
                    </div>
                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="submit" class="btn btn-default" id="doceditbtn" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
                            <i class="fas fa-plus-circle"></i> <?php echo translate('update'); ?>
                        </button>
                        <button class="btn btn-default modal-dismiss"><?php echo translate('cancel'); ?></button>
                    </div>
                </div>
            </footer>
        <?php echo form_close(); ?>
    </section>
</div>

<script type="text/javascript">
	var authenStatus = "<?=$student['active']?>";
</script>
<?php endif; /* $can_view_proj */ ?>