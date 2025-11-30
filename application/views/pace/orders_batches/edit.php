    <?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading">
        <h4>Edit Order #<?= (int)$order['id']; ?> — <?= html_escape($order['student_name'] ?: ('Student ID '.$order['student_id'])) ?></h4>
        <p class="text-muted">Adjust quantities / prices. Set Qty to <b>0</b> to remove a line.</p>
      </header>
      <div class="panel-body">
        <form action="<?= base_url('pace/orders_batch_update') ?>" method="post">
          <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
          <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

          <div class="table-responsive">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Subject</th>
                  <th>PACE #</th>
                  <th style="width:110px" class="text-right">Qty</th>
                  <th style="width:130px" class="text-right">Unit Price</th>
                  <th style="width:130px" class="text-right">Line Total</th>
                  <th style="width:80px">Redo?</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <?php $i=1; foreach ($items as $it): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= html_escape($it['subject_name'] ?? $it['subject'] ?? '') ?></td>
                  <td><?= (int)$it['pace_number'] ?: (int)$it['pace_no'] ?></td>
                  <td class="text-right">
                    <input type="hidden" name="items[<?= (int)$it['id'] ?>][item_id]" value="<?= (int)$it['id'] ?>">
                    <input type="number" min="0" name="items[<?= (int)$it['id'] ?>][qty]" value="<?= (int)$it['qty'] ?>" class="form-control text-right js-qty">
                  </td>
                  <td class="text-right">
                    <input type="text"
                           class="form-control text-right js-price"
                           value="<?= number_format((float)($it['unit_price'] ?? $it['price'] ?? 0), 2) ?>"
                           readonly disabled>
                  </td>
                  <td class="text-right">
                    <span class="js-total">
                      <?= number_format((float)($it['line_total'] ?? ((float)($it['qty'] ?? 0) * (float)($it['unit_price'] ?? $it['price'] ?? 0))), 2) ?>
                    </span>
                  </td>
                  <td><input type="checkbox" name="items[<?= (int)$it['id'] ?>][is_redo]" value="1" <?= (int)$it['is_redo'] ? 'checked' : '' ?>></td>
                  <td><input type="text" name="items[<?= (int)$it['id'] ?>][description]" value="<?= html_escape($it['description']) ?>" class="form-control"></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="row">
            <div class="col-md-6">
              <input type="text" name="checker_note" class="form-control" placeholder="Optional checker note (saved on Mark Checked)">
            </div>
            <div class="col-md-6 text-right">
              <button type="submit"
        name="btn_check_invoice"
        value="1"
        class="btn btn-success">
    Check & Invoice
</button>
              <a href="<?= base_url('pace/orders_batches') ?>" class="btn btn-default">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </section>
  </div>
</div>

<script>
(function () {
  function recalcRow(tr) {
    var qty   = parseFloat($(tr).find('.js-qty').val()) || 0;
    var price = parseFloat(String($(tr).find('.js-price').val()).replace(/,/g,'')) || 0;
    var total = (qty * price).toFixed(2);
    $(tr).find('.js-total').text(total);
  }

  $(document).on('input change', '.js-qty', function () {
    recalcRow($(this).closest('tr'));
  });

  $('.js-qty').each(function () {
    recalcRow($(this).closest('tr'));
  });
})();
</script>
