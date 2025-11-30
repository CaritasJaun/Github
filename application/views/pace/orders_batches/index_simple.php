<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="panel panel-default">
  <div class="panel-heading"><strong>PACE Order Batches (Pre-Invoice)</strong></div>
  <div class="panel-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-bordered table-condensed mb-0">
        <thead>
          <tr>
            <th style="width:80px">Invoice #</th>
            <th style="width:160px">Created</th>
            <th>Student</th>
            <th style="width:100px" class="text-right">Total</th>
            <th style="width:90px" class="text-center">Ordered</th>
            <th style="width:90px" class="text-center">Paid</th>
            <th style="width:90px" class="text-center">Issued</th>
            <th style="width:110px" class="text-center">Checked</th>
            <th style="width:220px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($batches)): ?>
          <tr><td colspan="9" class="text-center">No batches.</td></tr>
        <?php else: foreach ($batches as $b): ?>
          <tr>
            <td><?= (int)$b['id'] ?></td>
            <td><?= html_escape($b['created_at']) ?></td>
            <td title="#<?= (int)$b['student_id'] ?>">
              <?php
                  $label = isset($b['student_name']) && trim($b['student_name']) !== ''
                      ? $b['student_name']
                      : ('Student #'.(int)$b['student_id']);
                  echo html_escape($label);
              ?>
            </td>
            <td class="text-right"><?= number_format((float)$b['total'], 2) ?></td>
            <td class="text-center"><?= (int)$b['ordered'] ?></td>
            <td class="text-center"><?= !empty($b['paid']) ? '✔' : '—' ?></td>
            <td class="text-center"><?= !empty($b['issued']) ? '✔' : '—' ?></td>
            <td class="text-center"><?= !empty($b['is_checked']) ? 'Yes' : 'No' ?></td>
            <td>
              <a class="btn btn-xs btn-default" href="<?= site_url('pace/orders_batch_edit/'.$b['id']) ?>">View</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
```
