<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
// ---- status helpers: accept numeric codes or strings (draft|paid|issued|billed|redo)
$rawStatus = $invoice['status'] ?? 0;

if (is_numeric($rawStatus)) {
    $status_code = (int)$rawStatus;  // 0=draft, 1=paid, 2=issued, 3=billed, 4=redo
} else {
    $s = strtolower(trim((string)$rawStatus));
    $map = ['draft' => 0, 'paid' => 1, 'issued' => 2, 'billed' => 3, 'redo' => 4];
    $status_code = $map[$s] ?? 0;
}

$STATUS_LABEL = [0 => 'Draft', 1 => 'Paid', 2 => 'Issued', 3 => 'Billed', 4 => 'Redo'];
$status_label = $STATUS_LABEL[$status_code] ?? ucfirst((string)$rawStatus);

/* Button rules:
 * - Mark PAID should be available until it is actually PAID
 * - Mark ISSUED should be available only before ISSUED/BILLED
 */
$can_paid   = ($status_code !== 1);     // allow paying from Draft/Billed/Issued-if-you-keep-it
$can_issued = ($status_code < 2);       // hide after Issued or Billed
$csrf_name  = $this->security->get_csrf_token_name();
$csrf_hash  = $this->security->get_csrf_hash();
?>

<!-- printable content wrapper -->
<div id="print-area">

<div class="panel panel-default">
  <div class="panel-heading clearfix">
    <strong>Invoice #<?= (int)$invoice['id'] ?></strong>

    <div class="pull-right">
      <a href="<?= site_url('pace/orders_batches?invoice_id='.(int)$invoice['id']) ?>"
         class="btn btn-info btn-xs">Back</a>

          <!-- Existing buttons -->
          <button type="button" class="btn btn-success btn-xs" id="exportCsvBtn">Export CSV</button>

          <!-- Clean print (popup) -->
          <button type="button" class="btn btn-primary btn-xs" id="printCleanBtn">Print </button>
        </div>

    </div>
  </div>

  <div class="panel-body">
    <div class="row" style="margin-bottom:10px">
      <div class="col-sm-6">
        <p><strong>Student:</strong>
          <?= html_escape($invoice['student_name'] ?: ('#'.(int)$invoice['student_id'])) ?></p>
        <p><strong>Status:</strong> <?= html_escape($status_label) ?></p>
      </div>
      <div class="col-sm-6 text-right">
        <p><strong>Total:</strong> <?= number_format((float)($invoice['total'] ?? 0), 2) ?></p>
        <p><strong>Created:</strong> <?= html_escape($invoice['created_at'] ?? '') ?></p>
        <p><strong>Updated:</strong> <?= html_escape($invoice['updated_at'] ?? '') ?></p>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-bordered table-condensed">
        <thead>
          <tr>
            <th>#</th>
            <th>Subject</th>
            <th>PACE #</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Line Total</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="6" class="text-center text-muted">No items on this invoice.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $it): ?>
            <?php
              $subj = $it['subject_name'] ?? $it['subject'] ?? '';
              $qty  = (int)($it['qty'] ?? $it['quantity'] ?? 1);
              $unit = (float)($it['unit_price'] ?? $it['price'] ?? 0);
              $line = (float)($it['line_total'] ?? $it['total'] ?? ($qty * $unit));
            ?>
            <tr>
              <td><?= (int)$it['id'] ?></td>
              <td><?= html_escape($subj) ?></td>
              <td><?= (int)($it['pace_number'] ?? $it['pace_no'] ?? 0) ?></td>
              <td><?= $qty ?></td>
              <td><?= number_format($unit, 2) ?></td>
              <td><?= number_format($line, 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="5" class="text-right">Total</th>
            <th><?= number_format((float)($invoice['total'] ?? 0), 2) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

</div><!-- /#print-area -->

<style>
/* Optional: cleaner print */
@media print {
  .panel-heading .btn,
  .btn,
  .panel-heading .pull-right a { display: none !important; }
  .panel { border: none; box-shadow: none; }
  table { page-break-inside: auto; }
  tr    { page-break-inside: avoid; page-break-after: auto; }
}
</style>

<!-- Print: left-aligned "letter" layout (no site chrome, no centered content) -->
<style>
@media print {
  @page { size: A4; margin: 18mm 18mm 18mm 18mm; }

  body * { visibility: hidden !important; }
  #print-area, #print-area * { visibility: visible !important; }

  html, body { width: 210mm; }
  #print-area {
    position: static !important;
    width: 190mm !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  #print-area .text-right { text-align: left !important; }

  #print-area .table-responsive { width: 100% !important; margin: 8mm 0 0 0 !important; }
  #print-area table { width: 100% !important; border-collapse: collapse !important; margin: 0 !important; }
  #print-area th, #print-area td { border: 1px solid #999 !important; padding: 6px 8px !important; }
  #print-area thead th { border-bottom: 2px solid #000 !important; }

  a[href]:after { content: none !important; }

  #print-area .panel { border: 0 !important; box-shadow: none !important; }
  #print-area .panel-heading { border: 0 !important; padding: 0 0 8px 0 !important; }
}
a[href]:after { content: none; }
</style>

<script>
(function () {
  // CSV Export (existing)
  var btn = document.getElementById('exportCsvBtn');
  if (btn) {
    btn.addEventListener('click', function () {
      var table = document.querySelector('.panel .table');
      if (!table) return;

      var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
      var csv = rows.map(function (row) {
        var cells = Array.prototype.slice.call(row.children);
        return cells.map(function (cell) {
          var text = (cell.innerText || '').replace(/\s+/g, ' ').trim();
          if (text.indexOf('"') !== -1) { text = text.replace(/"/g, '""'); }
          if (text.indexOf(',') !== -1 || text.indexOf('"') !== -1) {
            text = '"' + text + '"';
          }
          return text;
        }).join(',');
      }).join('\r\n');

      var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'invoice_<?= (int)$invoice['id'] ?>.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });
  }

  // Clean print popup — STRIPS BUTTONS/HEADER ACTIONS BEFORE PRINT
  var cleanBtn = document.getElementById('printCleanBtn');
  if (cleanBtn) {
    cleanBtn.addEventListener('click', function () {
      var src = document.getElementById('print-area');
      if (!src) return;

      // Clone and strip controls
      var clone = src.cloneNode(true);
      // Remove header actions and any btns within the printable area
      Array.prototype.slice.call(
        clone.querySelectorAll('.panel-heading .pull-right, .btn, a.btn')
      ).forEach(function (el) { el.parentNode.removeChild(el); });

      var sanitizedHTML = clone.innerHTML;

      var html = `
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice #<?= (int)$invoice['id'] ?></title>
<style>
  @page { size: A4; margin: 18mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; margin: 0; }
  .wrap { width: 190mm; margin: 0; }
  .text-right { text-align: right; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #999; padding: 6px 8px; }
  thead th { border-bottom: 2px solid #000; }
  a[href]:after { content: none !important; }
</style>
</head>
<body>
  <div class="wrap">
    ${sanitizedHTML}
  </div>
  <script>
    window.onload = function () {
      window.print();
      setTimeout(function(){ window.close(); }, 300);
    };
  <\/script>
</body>
</html>`;

      var w = window.open('', '_blank');
      if (!w) { alert('Please allow popups to print this invoice.'); return; }
      w.document.open();
      w.document.write(html);
      w.document.close();
    });
  }
})();
</script>
