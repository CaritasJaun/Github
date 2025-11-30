<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading">
        <h4>Check Pace Orders (Pre-Invoice)</h4>
      </header>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Created</th>
                <th>Student</th>
                <th class="text-right">Total</th>
                <th class="text-right">Ordered</th>
                <th>Paid</th>
                <th>Issued</th>
                <th>Checked</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($batches)): foreach ($batches as $b): ?>
                <tr>
                  <td><?= (int)$b['id'] ?></td>
                  <td><?= html_escape($b['created_at'] ?? $b['created'] ?? '') ?></td>
                  <td><?= html_escape($b['student_name'] ?? '') ?></td>
                  <td class="text-right"><?= number_format((float)($b['grand_total'] ?? 0), 2) ?></td>
                  <td class="text-right"><?= (int)($b['ordered_qty'] ?? 0) ?></td>
                  <td>—</td>
                  <td>—</td>
                  <td><?= !empty($b['is_checked']) ? 'Yes' : 'No' ?></td>
                  <td>
                    <a href="<?= base_url('pace/orders_batch_edit/' . (int)$b['id']) ?>" class="btn btn-xs btn-success">View</a>
                    <?php if (empty($b['is_checked'])): ?>
                      <a href="<?= base_url('pace/orders_batch_mark_checked/' . (int)$b['id']) ?>" class="btn btn-xs btn-primary">Mark</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr>
                  <td colspan="9" class="text-center text-muted">No pre-invoice batches.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>
