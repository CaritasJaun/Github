<?php
// expects $parentslist (array of rows) and $branch_id already set by controller
?>
<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading">
        <div class="panel-btn">
          <a href="<?= base_url('parents/add'); ?>" class="btn btn-default btn-circle">
            <i class="fas fa-plus"></i> <?= translate('add_parent'); ?>
          </a>
        </div>
        <h4 class="panel-title">
          <i class="fas fa-users"></i> <?= translate('parents_list'); ?>
        </h4>
      </header>

      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-condensed" id="parentsTable">
            <thead>
              <tr>
                <th>#</th>
                <th><?= translate('name'); ?></th>
                <th><?= translate('relation'); ?></th>
                <th><?= translate('father_name'); ?></th>
                <th><?= translate('mother_name'); ?></th>
                <th><?= translate('phone'); ?></th>
                <th><?= translate('email'); ?></th>
                <th><?= translate('address'); ?></th>
                <th><?= translate('action'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i = 1;
              if (!empty($parentslist)):
                foreach ($parentslist as $p):
                  // pick any available phone/email
                  $phone = $p->father_mobileno ?: $p->mother_mobileno;
                  $email = $p->father_email    ?: $p->mother_email;
              ?>
                <tr>
                  <td><?= $i++; ?></td>
                  <td><?= html_escape($p->name); ?></td>
                  <td><?= html_escape($p->relation); ?></td>
                  <td><?= html_escape($p->father_name); ?></td>
                  <td><?= html_escape($p->mother_name); ?></td>
                  <td><?= html_escape($phone ?: 'N/A'); ?></td>
                  <td><?= html_escape($email ?: 'N/A'); ?></td>
                  <td><?= html_escape($p->address ?: ''); ?></td>
                  <td>
                    <a href="<?= base_url('parents/profile/' . (int)$p->id); ?>" class="btn btn-default btn-xs">
                      <i class="fas fa-id-card"></i> <?= translate('profile'); ?>
                    </a>
                  </td>
                </tr>
              <?php
                endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
  // If DataTables JS is loaded globally, enhance the table.
  if (typeof $.fn.DataTable !== 'undefined') {
    $('#parentsTable').DataTable({
      pageLength: 25,
      order: [[1, 'asc']]
    });
  }
</script>
