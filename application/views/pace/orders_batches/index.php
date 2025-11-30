<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
// Expecting $rows from controller (one row per invoice) and optional $group flag
$title = isset($group) && $group === 'invoice'
    ? 'PACE Order Batches'
    : 'PACE Order Batches (Pre-Invoice)';
?>
<div class="panel panel-default">
  <div class="panel-heading">
    <strong><?= html_escape($title) ?></strong>
  </div>

  <div class="panel-body">
    <div class="table-responsive">
      <table class="table table-striped table-bordered table-condensed">
        <thead>
          <tr>
            <th style="width:90px">Invoice #</th>
            <th>Created</th>
            <th>Student</th>
            <th style="width:80px" class="text-center">Total</th>
            <th style="width:90px" class="text-center">Ordered</th>
            <th style="width:70px" class="text-center">Paid</th>
            <th style="width:70px" class="text-center">Issued</th>
            <th style="width:90px" class="text-center">Checked</th>
            <th style="width:240px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center">No batches.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $invId   = (int)$r['invoice_id'];
              $total   = (int)$r['total'];
              $ordered = (int)$r['ordered_cnt'];
              $paid    = (int)$r['paid_cnt'];
              $issued  = (int)$r['issued_cnt'];
              $checked = !empty($r['checked']) || (isset($r['is_checked']) && $r['is_checked']); // tolerate both
              $canPaid   = ($paid   < $total);    // enable if any not yet paid
              $canIssued = ($issued < $total);    // enable if any not yet issued
            ?>
            <tr>
              <td><?= $invId ?: '-' ?></td>
              <td><?= html_escape($r['created_date'] ?? '') ?></td>
              <td><?= html_escape($r['student_name'] ?? '') ?></td>
              <td class="text-center"><?= $total ?></td>
              <td class="text-center"><?= $ordered ?></td>
              <td class="text-center"><?= $paid ?></td>
              <td class="text-center"><?= $issued ?></td>
              <td class="text-center">
                <?php if ($checked): ?>
                  <span class="label label-default">Yes</span>
                <?php else: ?>
                  <span class="label label-warning">No</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="btn btn-info btn-xs"
                   href="<?= site_url('pace/orders_batch_view/'.$invId) ?>">View</a>

                <!-- New: quick actions -->
                <button class="btn btn-success btn-xs js-batch-mark"
                        data-to="paid" data-id="<?= $invId ?>"
                        <?= $canPaid ? '' : 'disabled' ?>>Mark PAID</button>

                <button class="btn btn-warning btn-xs js-batch-mark"
                        data-to="issued" data-id="<?= $invId ?>"
                        <?= $canIssued ? '' : 'disabled' ?>>Mark ISSUED</button>

                <a class="btn btn-default btn-xs"
                   href="<?= site_url('pace/invoice_view/'.$invId) ?>">Invoice</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function ($) {
  function postMark(invoiceId, to, $btn) {
    if (!invoiceId || !to) return;

    var wasText = $btn.text();
    $btn.prop('disabled', true).text('Please wait…');

    $.post('<?= site_url('pace/orders_batch_mark'); ?>', {
      invoice_id: invoiceId,
      to: to,
      '<?= $this->security->get_csrf_token_name(); ?>':
      '<?= $this->security->get_csrf_hash(); ?>'
    }, function (res) {
      if (res && (res.ok || res.success)) {
        location.reload();
      } else {
        alert((res && (res.msg || res.error)) ? (res.msg || res.error) : 'Update failed.');
        $btn.prop('disabled', false).text(wasText);
      }
    }, 'json').fail(function () {
      alert('Request failed.');
      $btn.prop('disabled', false).text(wasText);
    });
  }

  $(document).on('click', '.js-batch-mark', function () {
    var $btn = $(this);
    var id   = $btn.data('id');
    var to   = $btn.data('to');
    var msg  = (to === 'paid')
      ? 'Mark this entire invoice as PAID?'
      : 'Mark this entire invoice as ISSUED?';
    if (confirm(msg)) postMark(id, to, $btn);
  });
})(jQuery);
</script>
