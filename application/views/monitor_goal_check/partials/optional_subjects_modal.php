<div class="modal fade" id="optSubjModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="optSubjForm">
        <div class="modal-header">
          <h4 class="modal-title">Select Optional Subjects</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="student_id" value="<?= (int)$student_id; ?>">

          <?php if (empty($subjects)): ?>
            <p class="text-muted mb-0">No optional subjects are assigned to this class/section.</p>
          <?php else: ?>
            <div class="row">
              <?php foreach ($subjects as $s): ?>
                <div class="col-sm-6 col-md-4 mb-2">
                  <label class="checkbox-inline">
                    <input type="checkbox" name="subject_ids[]" value="<?= (int)$s['id']; ?>" <?= ($s['selected'] ? 'checked' : ''); ?>>
                    <strong><?= html_escape($s['abbreviation']); ?></strong> — <?= html_escape($s['name']); ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  var $m = $('#optSubjModal');
  $('#optSubjForm').on('submit', function(e){
    e.preventDefault();
    var data = $(this).serializeArray();
    data.push({name: '<?= $this->security->get_csrf_token_name(); ?>', value: '<?= $this->security->get_csrf_hash(); ?>'});

    $.post('<?= site_url('monitor_goal_check/save_optionals'); ?>', data, function(res){
      if (res && res['<?= $this->security->get_csrf_token_name(); ?>']) {
        window.CSRF_HASH = res['<?= $this->security->get_csrf_token_name(); ?>'];
      }
      if (res && res.success) {
        $m.modal('hide');
        if (window.reloadGoalCheckTable) window.reloadGoalCheckTable(); else location.reload();
      } else {
        alert(res && res.message ? res.message : 'Save failed');
      }
    }, 'json');
  });
})();
</script>
