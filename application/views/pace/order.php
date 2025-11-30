<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();
$role_id  = (int)$this->session->userdata('loggedin_role_id');
?>

<div class="panel panel-default">
  <div class="panel-heading"><strong>Order PACEs</strong></div>
  <div class="panel-body">

    <!-- Symmetry + tidy layout for subject tiles -->
    <style>
      .pace-grid{
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(340px,1fr));
        gap:16px;
        align-items:stretch;
        padding-left:6px; /* pull first column slightly inward */
      }
      .pace-card{
        display:flex;
        flex-direction:column;
        border:1px solid #e5e5e5;
        border-radius:10px;
        background:#fff;
        min-height:260px; /* consistent height */
      }
      .pace-card__head{
        background:#39a5e0; /* keep theme color if different */
        color:#fff;
        padding:8px 12px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        min-height:44px;
        border-top-left-radius:10px;
        border-top-right-radius:10px;
      }
      .pace-card__body{
        padding:10px 12px;
        flex:1;
        overflow:auto;    /* long lists scroll inside card */
      }
      .pace-numbers{
        display:grid;
        grid-template-columns:repeat(6, 1fr); /* nice columns for numbers */
        gap:8px 10px;
        align-content:start;
      }
      .pace-n{ display:flex; gap:6px; align-items:center; }
      @media (max-width:1200px){
        .pace-grid{ grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); }
      }
      @media (max-width:768px){
        .pace-grid{ grid-template-columns:1fr; }
      }
    </style>

    <!-- Filters -->
    <form method="get" class="form-inline" action="<?= site_url('pace/order'); ?>" style="margin-bottom:12px">
      <div class="form-group">
        <label>Student&nbsp;</label>
        <select name="student_id" class="form-control input-sm" required>
          <option value="">-- select --</option>
          <?php foreach ((array)$students as $s): ?>
            <option value="<?= (int)$s['id']; ?>"
              <?= (isset($student_id) && (int)$student_id === (int)$s['id']) ? 'selected' : ''; ?>>
              <?= html_escape($s['first_name'].' '.$s['last_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-left:8px">
        <label>Term&nbsp;</label>
        <select name="term" class="form-control input-sm" required style="min-width:120px">
          <option value="">-- select --</option>
          <?php for ($q=1; $q<=4; $q++): ?>
            <option value="<?= $q; ?>" <?= (!empty($term) && (string)$term === (string)$q) ? 'selected' : ''; ?>>
              <?= $q; ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>

      <?php if ($role_id === 3): ?>
        <div class="checkbox" style="margin-left:12px">
          <label>
            <input type="checkbox" name="show_all" value="1" <?= !empty($show_all) ? 'checked' : ''; ?>>
            Show all paces
          </label>
        </div>
      <?php endif; ?>

      <button class="btn btn-sm btn-warning" style="margin-left:8px">Filter</button>
    </form>

    <?php if (!empty($student_id) && !empty($term)): ?>

      <?php if (!empty($subjects)): ?>

      <!-- Multi-subject order form -->
      <form method="post" action="<?= site_url('pace/order_save'); ?>" id="orderForm" style="margin-bottom:18px">
        <input type="hidden" name="<?= $csrfName; ?>" value="<?= $csrfHash; ?>">
        <input type="hidden" name="student_id" value="<?= (int)$student_id; ?>">
        <input type="hidden" name="term" value="<?= html_escape($term); ?>">

        <div class="pace-grid">
          <?php foreach ($subjects as $sub): $sid=(int)$sub['id']; ?>
            <div class="pace-card">
              <div class="pace-card__head">
                <strong><?= html_escape($sub['name']); ?></strong>
                <label class="small mb-0" style="margin:0">
                  <input type="checkbox" class="js-check-all" data-target="sub-<?= $sid; ?>"> all
                </label>
              </div>
              <div class="pace-card__body">
                <?php if (!empty($available[$sid])): ?>
                  <div class="pace-numbers" id="sub-<?= $sid; ?>">
                    <?php foreach ($available[$sid] as $n): ?>
                      <label class="pace-n">
                        <input type="checkbox" name="pace[<?= $sid; ?>][]" value="<?= (int)$n; ?>"
                               class="sub-<?= $sid; ?>"> <?= (int)$n; ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <em>No available PACEs.</em>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px">Place Order</button>
      </form>

      <?php else: ?>
        <p><em>This learner has no enrolled subjects.</em></p>
      <?php endif; ?>

      <!-- Pipeline snapshot -->
      <?php if (!empty($orders)): ?>
      <h4>Current Orders (this student)</h4>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-condensed">
          <thead>
            <tr>
              <th>#</th>
              <th>Subject</th>
              <th>PACE #</th>
              <th>Redo</th>
              <th>Status</th>
              <th>Ordered</th>
              <th>Paid</th>
              <th>Issued</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><?= (int)$o['id']; ?></td>
                <td><?= html_escape($o['subject_name'] ?? $o['subject_id']); ?></td>
                <td><?= (int)$o['pace_number']; ?></td>
                <td><?= !empty($o['is_redo']) ? '<span class="label label-danger">Redo</span>' : ''; ?></td>
                <td><?= ucfirst($o['status']); ?></td>
                <td><?= html_escape($o['ordered_at']); ?></td>
                <td><?= html_escape($o['paid_at']); ?></td>
                <td><?= html_escape($o['issued_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <p><em>Select a student and term to begin.</em></p>
    <?php endif; ?>

  </div>
</div>

<script>
// "check all" per subject
document.addEventListener('change', function(e){
  if (e.target.classList.contains('js-check-all')) {
    var cls = e.target.getAttribute('data-target');
    document.querySelectorAll('input.'+cls).forEach(function(cb){ cb.checked = e.target.checked; });
  }
});
</script>
