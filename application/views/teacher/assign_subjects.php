<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
// --- safe fallbacks so the view works with different controller var names ---
$students  = $students ?? ($all_students ?? ($studentlist ?? []));
$selected_id = $selected_id ?? ($student_id ?? (int)($this->input->get('student_id') ?? 0));
$year        = $year        ?? ($session_year ?? (int)($this->input->get('year') ?? date('Y')));

// lists default to arrays to avoid notices
$mandatory_list         = $mandatory_list         ?? [];
$optional_list          = $optional_list          ?? [];
$selected_optional_ids  = $selected_optional_ids  ?? [];
?>


<section class="panel">
  <header class="panel-heading">
    <h4>Assign Subjects</h4>
  </header>

  <div class="panel-body">
    <style>
      /* layout + small left shift so columns sit inside the panel */
      .push-left{ margin-left:30px; }

      .subject-grid{
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(260px,1fr));
        gap:8px 16px;
        align-items:start;
      }
      .subject-item label{ display:flex; gap:8px; align-items:flex-start; margin:0; line-height:1.3; }
      .subject-item input[type="checkbox"]{ margin-top:2px; }
      .subject-item span{ word-break:break-word; white-space:normal; }

      .pill{
        display:inline-block; background:#eef3ff; border:1px solid #cfe0ff; color:#335; padding:6px 10px;
        border-radius:16px; font-size:12px; margin:4px 6px 4px 0;
      }
      .muted{ color:#777; }

      @media (max-width: 992px){ .subject-grid{ grid-template-columns:repeat(2,1fr); } }
      @media (max-width: 576px){ .subject-grid{ grid-template-columns:1fr; } }
    </style>

    <!-- Filters -->
    <form method="get" action="<?= site_url('pace/assign_subjects') ?>" class="form-inline mb-md push-left">
      <div class="form-group">
        <label>Student</label>
        <select name="student_id" class="form-control ml-sm" required>
          <option value="">Select…</option>
          <?php foreach ((array)$students as $s): ?>
            <?php
              // Normalize student row from either shape
              $id    = isset($s['id']) ? (int)$s['id'] : (int)($s['student_id'] ?? 0);
              $name  = isset($s['full_name'])
                        ? $s['full_name']
                        : trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
              $grade = $s['grade_name'] ?? ($s['class_name'] ?? '');
              $sel   = ((int)($selected_id ?? 0) === $id) ? 'selected' : '';
            ?>
            <option value="<?= $id ?>" <?= $sel ?>>
              <?= html_escape($name) ?><?= $grade ? ' ('.html_escape($grade).')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group ml-md">
        <label>Year</label>
        <input type="number" name="year" class="form-control"
               value="<?= (int)($year ?? date('Y')) ?>" min="2000" max="2100">
      </div>

      <button type="submit" class="btn btn-primary ml-md">Load</button>
    </form>

    <?php
      // Expected from controller:
      // $mandatory_list, $optional_list, $selected_optional_ids, $selected_id, $year
      $hasStudent = (int)($selected_id ?? 0) > 0;
    ?>

    <?php if ($hasStudent): ?>

      <?php if (!empty($mandatory_list) || !empty($optional_list)): ?>

        <form method="post" action="<?= site_url('pace/assign_subjects_save') ?>" class="push-left" style="margin-top:6px;">
          <!-- CSRF (added) -->
          <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>"
       value="<?= $this->security->get_csrf_hash(); ?>">
          <!-- /CSRF -->

          <input type="hidden" name="student_id" value="<?= (int)$selected_id ?>">
          <input type="hidden" name="year" value="<?= (int)($year ?? date('Y')) ?>">

          <?php if (!empty($mandatory_list)): ?>
            <div class="mb-md">
              <strong>Mandatory subjects</strong>
              <div class="mt-sm">
                <?php foreach ($mandatory_list as $m): ?>
                  <span class="pill"><?= html_escape($m['name']) ?></span>
                <?php endforeach; ?>
              </div>
              <div class="muted" style="margin-top:6px;">Mandatory subjects are automatically included.</div>
            </div>
          <?php endif; ?>

          <?php if (!empty($optional_list)): ?>
            <div class="mb-sm"><strong>Optional subjects</strong></div>
            <div class="subject-grid">
              <?php $pre = array_map('intval', (array)($selected_optional_ids ?? [])); ?>
              <?php foreach ($optional_list as $opt): ?>
                <div class="subject-item">
                  <label class="checkbox">
                    <input type="checkbox"
                           name="subject_ids[]"
                           value="<?= (int)$opt['id'] ?>"
                           <?= in_array((int)$opt['id'], $pre, true) ? 'checked' : '' ?>>
                    <span><?= html_escape($opt['name']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-success mt-md">Save</button>
        </form>

      <?php else: ?>
        <div class="alert alert-info push-left">No subjects found for this grade.</div>
      <?php endif; ?>

    <?php elseif (isset($selected_id)): ?>
      <div class="alert alert-info push-left">Choose a student to load subjects.</div>
    <?php endif; ?>

  </div>
</section>
