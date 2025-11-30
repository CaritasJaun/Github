<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="panel panel-default">
  <div class="panel-heading"><strong>All PACE Orders (This Campus)</strong></div>
  <div class="panel-body">
    <?php if (empty($rows)): ?>
      <p>No orders found for this campus.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-bordered table-condensed">
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Subject</th>
              <th>PACE #</th>
              <th>Term</th>
              <th>Status</th>
              <th>Ordered</th>
              <th>Paid</th>
              <th>Issued</th>
              <th>Invoice</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= html_escape($r['student_name'] ?: ('#'.$r['student_id'])) ?></td>
                <td><?= html_escape($r['subject_name']) ?></td>
                <td><?= (int)$r['pace_number'] ?></td>
                <td><?= html_escape($r['term']) ?></td>
                <td><?= html_escape(ucfirst($r['status'])) ?></td>
                <td><?= html_escape($r['ordered_at']) ?></td>
                <td><?= html_escape($r['paid_at']) ?></td>
                <td><?= html_escape($r['issued_at']) ?></td>
                <td>
                  <?php if (!empty($r['invoice_id'])): ?>
                    <a href="<?= site_url('pace/invoice_view/'.(int)$r['invoice_id']) ?>">#<?= (int)$r['invoice_id'] ?></a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
