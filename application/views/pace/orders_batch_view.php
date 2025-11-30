<?php defined('BASEPATH') OR exit('No direct script access allowed');
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();

/* ---------- SAFETY / NORMALIZATION ---------- */
// allow either $inv or $invoice from controller
$inv   = (isset($inv) && is_array($inv)) ? $inv
       : ((isset($invoice) && is_array($invoice)) ? $invoice : []);
$items = (isset($items) && is_array($items)) ? $items : [];

// header fields with fallbacks
$inv_id       = (int)($inv['id'] ?? 0);
$inv_no       = ($inv['invoice_no'] ?? ($inv_id ?: '—'));
$inv_status   = ucfirst((string)($inv['status'] ?? 'draft'));
$created_at   = (string)($inv['created_at'] ?? '');
$student_name = (string)($inv['student_name'] ?? ($inv['student'] ?? ($inv['student_id'] ?? '')));

// if there is no invoice, stop rendering early
if ($inv_id <= 0) {
    echo '<div class="alert alert-warning m-3">Invoice not found.</div>';
    return;
}
?>
<div class="panel panel-default">
  <div class="panel-heading">
    <strong>Invoice #<?= (int)$inv_id; ?></strong>
    <div class="pull-right">
      <a href="<?= site_url('pace/orders_batches?group=invoice'); ?>" class="btn btn-xs btn-default">Back</a>

      <!-- Mark PAID (POST) -->
      <form action="<?= site_url('pace/invoice_mark_paid/'.(int)$inv_id); ?>" method="post" style="display:inline">
        <input type="hidden" name="<?= $csrfName; ?>" value="<?= $csrfHash; ?>">
        <button type="submit" class="btn btn-xs btn-success">Mark Invoice PAID</button>
      </form>

      <!-- Mark ISSUED (POST) -->
      <form action="<?= site_url('pace/invoice_mark_issued/'.(int)$inv_id); ?>" method="post" style="display:inline">
        <input type="hidden" name="<?= $csrfName; ?>" value="<?= $csrfHash; ?>">
        <button type="submit" class="btn btn-xs btn-primary">Mark Invoice ISSUED</button>
      </form>
    </div>
  </div>

  <div class="panel-body">
    <p>
      <b>Student:</b> <?= html_escape($student_name); ?> &nbsp;|
      <b>Status:</b> <?= html_escape($inv_status); ?> &nbsp;|
      <b>Created:</b> <?= html_escape($created_at); ?>
    </p>

    <div class="table-responsive">
      <table class="table table-bordered table-striped table-condensed">
        <thead>
          <tr>
            <th>#</th>
            <th>Subject</th>
            <th>PACE #</th>
            <th>Status</th>
            <th>Ordered</th>
            <th>Paid</th>
            <th>Issued</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($items)): foreach ($items as $it): ?>
            <?php
              $id         = (int)($it['id'] ?? 0);
              $subject    = (string)($it['subject_name'] ?? ($it['subject_id'] ?? ''));
              $paceNo     = $it['pace_number'] ?? $it['pace_no'] ?? $it['book_number'] ?? $it['number'] ?? $it['item_no'] ?? '';
              $iStatus    = (string)($it['status'] ?? '');
              $orderedAt  = (string)($it['ordered_at'] ?? $it['created_at'] ?? '');
              $paidAt     = (string)($it['paid_at'] ?? '');
              $issuedAt   = (string)($it['issued_at'] ?? '');
            ?>
            <tr>
              <td><?= $id; ?></td>
              <td><?= html_escape($subject); ?></td>
              <td><?= html_escape($paceNo); ?></td>
              <td><?= html_escape(ucfirst($iStatus)); ?></td>
              <td><?= html_escape($orderedAt); ?></td>
              <td><?= html_escape($paidAt); ?></td>
              <td><?= html_escape($issuedAt); ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="7"><em>No items on this invoice.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<script>
(function(){
  // CSRF support (and refresh if server returns a new token)
  var CSRF_NAME = '<?= $csrfName ?>';
  var CSRF_HASH = '<?= $csrfHash ?>';

  document.addEventListener('click', function(e){
    var b = e.target.closest('.js-mark');
    if(!b) return;

    var id = b.getAttribute('data-id');
    var to = b.getAttribute('data-to');

    var fd = new FormData();
    fd.append('invoice_id', id);
    fd.append('to', to);
    fd.append(CSRF_NAME, CSRF_HASH);

    fetch('<?= site_url('pace/orders_batch_mark'); ?>', { // keep your endpoint
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, // <-- REQUIRED for is_ajax_request()
      body: fd
    })
    .then(async function(r){
      var txt = await r.text();
      var d;
      try { d = JSON.parse(txt); } catch(e){ throw new Error(txt.substring(0,300) || 'Invalid response'); }
      if (d && d[CSRF_NAME]) CSRF_HASH = d[CSRF_NAME];
      if (d && d.ok) { location.reload(); }
      else { alert(d.msg || 'Failed'); }
    })
    .catch(function(err){
      alert('Network/Server error: ' + (err && err.message ? err.message : 'Unable to mark invoice'));
      console.error(err);
    });
  });
})();
</script>
