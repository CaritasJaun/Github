<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="panel panel-default">
  <div class="panel-heading">
    <strong>PACE Order Batches</strong>
    <div class="pull-right">
      <a href="<?= site_url('pace/orders_batches?group=invoice'); ?>"
         class="btn btn-xs btn-default <?= ($group==='invoice' ? 'active' : ''); ?>">Per Invoice</a>
      <a href="<?= site_url('pace/orders_batches?group=student'); ?>"
         class="btn btn-xs btn-default <?= ($group==='student' ? 'active' : ''); ?>">Per Student</a>
      <a href="<?= site_url('pace/orders_batches?group=day'); ?>"
         class="btn btn-xs btn-default <?= ($group==='day' ? 'active' : ''); ?>">Per Day</a>
    </div>
  </div>

  <div class="panel-body">
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-condensed">
        <thead>
          <tr>
            <th style="width: 50%;">Student</th>
            <th class="text-center">Ordered</th>
            <th class="text-center">Billed/Checked</th>
            <th class="text-center">Paid</th>
            <th class="text-center">Issued</th>
            <th style="width: 220px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted">No student activity yet.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= html_escape($r['student_name']); ?></td>
              <td class="text-center"><?= (int)$r['ordered_cnt']; ?></td>
              <td class="text-center"><?= (int)$r['billed_cnt']; ?></td>
              <td class="text-center"><?= (int)$r['paid_cnt']; ?></td>
              <td class="text-center"><?= (int)$r['issued_cnt']; ?></td>
              <td>
                <a class="btn btn-xs btn-primary"
                   href="<?= site_url('pace/orders_batches?group=invoice&student_id='.(int)$r['student_id']); ?>">
                   Open Orders
                </a>
                <a class="btn btn-xs btn-default"
                   href="<?= site_url('pace/orders_batches?group=invoice&student_id='.(int)$r['student_id']); ?>">
                   View Invoices
                </a>
                <a class="btn btn-xs btn-success"
                   href="<?= site_url('spc/index?student_id='.(int)$r['student_id']); ?>">
                   SPC / Issue
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
