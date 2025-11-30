<?php
// Expected vars: $subjects (id,name), $paceMap[subject_id] => [paceNums], $existing[subject_id][paceNum] = true, $term
$h = function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
?>
<?php if (empty($subjects)): ?>
  <div class="alert alert-warning">No subjects found for this student.</div>
<?php else: ?>
  <div class="row">
    <?php foreach ($subjects as $s): ?>
      <?php
        $sid   = (int)$s['id'];
        $name  = $h($s['name']);
        $pnums = isset($paceMap[$sid]) ? $paceMap[$sid] : [];
      ?>
      <div class="col-md-4">
        <div class="panel panel-default">
          <div class="panel-heading" style="display:flex;align-items:center;justify-content:space-between;">
            <strong><?= $name; ?></strong>
            <?php if (!empty($pnums)): ?>
              <label class="checkbox-inline" style="margin:0;">
                <input type="checkbox" class="check-all-subject" data-subject="<?= $sid; ?>"> Select all
              </label>
            <?php endif; ?>
          </div>
          <div class="panel-body" style="max-height:340px;overflow:auto;padding:10px;">
            <?php if (empty($pnums)): ?>
              <div class="text-muted small">No PACE numbers configured for this subject.</div>
            <?php else: ?>
              <table class="table table-condensed" style="margin:0;">
                <thead>
                  <tr><th style="width:60px;">PACE #</th><th>Assign</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pnums as $pn): ?>
                  <?php
                    $pn  = (int)$pn;
                    $isAssigned = !empty($existing[$sid][$pn]);
                  ?>
                  <tr>
                    <td><?= $pn; ?></td>
                    <td>
                      <input type="checkbox"
                             class="pace-check"
                             data-subject="<?= $sid; ?>"
                             data-pace="<?= $pn; ?>"
                             <?= $isAssigned ? 'checked disabled' : ''; ?>>
                      <?php if ($isAssigned): ?>
                        <span class="label label-default" style="margin-left:6px;">already</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
