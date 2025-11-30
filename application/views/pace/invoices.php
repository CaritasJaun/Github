<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="panel panel-default">
  <div class="panel-heading"><strong>Invoices</strong></div>
  <div class="panel-body">
    <?php if (empty($invoices)): ?>
      <p>No invoices found for this campus.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-bordered table-condensed">
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Status</th>
              <th>Total</th>
              <th>Created</th>
              <th>Updated</th>
              <th>Open</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $inv): ?>
              <tr>
                <td><?= (int)$inv['id'] ?></td>
                <td><?= html_escape($inv['student_name'] ?: ('#'.$inv['student_id'])) ?></td>
                <td><?= html_escape(ucfirst($inv['status'])) ?></td>
                <td><?= number_format((float)$inv['total'], 2) ?></td>
                <td><?= html_escape($inv['created_at']) ?></td>
                <td><?= html_escape($inv['updated_at']) ?></td>
                <td><a class="btn btn-xs btn-primary" href="<?= site_url('pace/invoice_view/'.(int)$inv['id']) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
