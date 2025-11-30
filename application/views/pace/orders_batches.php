<?php defined('BASEPATH') OR exit('No direct script access allowed');
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();
?>
<div class="panel panel-default">
  <div class="panel-heading">
    <strong>PACE Order Batches</strong>
    <div class="pull-right">
  <a href="<?= site_url('pace/orders_batches?group=invoice'); ?>" class="btn btn-xs btn-default <?= ($group==='invoice' ? 'active' : ''); ?>">Per Invoice</a>
  <a href="<?= site_url('pace/orders_batches?group=student'); ?>" class="btn btn-xs btn-default <?= ($group==='student' ? 'active' : ''); ?>">Per Student</a>
  <a href="<?= site_url('pace/orders_batches?group=day');     ?>" class="btn btn-xs btn-default <?= ($group==='day'     ? 'active' : ''); ?>">Per Day</a>
</div>
</div>

<div class="panel-body">

  <?php if ($group === 'student'): ?>
    <!-- ========== Per STUDENT (aggregate all activity for a learner) ========== -->
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-condensed">
        <thead>
          <tr>
            <th>Student</th>
            <th class="text-center">Ordered</th>
            <th class="text-center">Billed/Checked</th>
            <th class="text-center">Paid</th>
            <th class="text-center">Issued</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
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
                 href="<?= site_url('spc/index?student_id='.(int)$r['student_id']); ?>">SPC / Issue</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6"><em>No student activity found.</em></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($group === 'invoice'): ?>
    <!-- ========== Per INVOICE (already billed; show Paid/Issued) ========== -->
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-condensed">
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Created</th>
            <th>Student</th>
            <th class="text-center">Total</th>
            <th class="text-center">Ordered</th>
            <th class="text-center">Billed/Checked</th>
            <th class="text-center">Paid</th>
            <th class="text-center">Issued</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['invoice_id']; ?></td>
            <td><?= html_escape($r['created_date']); ?></td>
            <td><?= html_escape($r['student_name']); ?></td>
            <td class="text-center"><?= (int)$r['total']; ?></td>
            <td class="text-center"><?= (int)$r['ordered_cnt']; ?></td>
            <td class="text-center"><?= (int)$r['ordered_cnt']; ?></td> <!-- Billed == Ordered for invoice -->
            <td class="text-center"><?= (int)$r['paid_cnt']; ?></td>
            <td class="text-center"><?= (int)$r['issued_cnt']; ?></td>
            <td>
              <a class="btn btn-xs btn-info" href="<?= site_url('pace/orders_batch_view/'.$r['invoice_id']); ?>">View</a>
              <button class="btn btn-xs btn-success js-mark" data-id="<?= (int)$r['invoice_id']; ?>" data-to="paid">Mark PAID</button>
              <button class="btn btn-xs btn-primary js-mark" data-id="<?= (int)$r['invoice_id']; ?>" data-to="issued">Mark ISSUED</button>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="9"><em>No invoices found.</em></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <!-- ========== Per DAY (aggregate) ========== -->
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-condensed">
        <thead>
          <tr>
            <th>Date</th>
            <th class="text-center">Total</th>
            <th class="text-center">Ordered</th>
            <th class="text-center">Billed/Checked</th>
            <th class="text-center">Paid</th>
            <th class="text-center">Issued</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
          <tr>
            <td><?= html_escape($r['order_date']); ?></td>
                <td class="text-center"><?= (int)$r['total']; ?></td>
                <td class="text-center"><?= (int)$r['ordered_cnt']; ?></td>
                <td class="text-center"><?= (int)$r['billed_cnt']; ?></td>
                <td class="text-center"><?= (int)$r['paid_cnt']; ?></td>
                <td class="text-center"><?= (int)$r['issued_cnt']; ?></td>
            <td>
              <a class="btn btn-xs btn-info" href="<?= site_url('pace/orders_batches?group=invoice&on='.$r['order_date']); ?>">View</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6"><em>No batches found.</em></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>


  </div>
</div>

<script>
document.addEventListener('click', function(e){
  var b = e.target.closest('.js-mark');
  if(!b) return;
  var id = b.getAttribute('data-id');
  var to = b.getAttribute('data-to');
  if(!id || !to) return;

  var fd = new FormData();
  fd.append('invoice_id', id);
  fd.append('to', to);
  fd.append('<?= $csrfName ?>', '<?= $csrfHash ?>');

  fetch('<?= site_url('pace/orders_batch_mark'); ?>', {method:'POST', credentials:'same-origin', body:fd})
    .then(r => r.json()).then(d => {
      if(d && d.ok){ location.reload(); }
      else { alert(d.msg || 'Failed'); }
    }).catch(() => alert('Network error'));
});
</script>
