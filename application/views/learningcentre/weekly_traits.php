<?php
// expects: $students, $selected_student, $term, $week_no, $traits_def, $scores
?>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading"><strong>Weekly Traits</strong></div>
      <div class="panel-body">
        <form method="get" action="<?= base_url('weekly_traits') ?>" class="form-inline mb-15">
          <label class="mr-5"><strong>Student:</strong></label>
          <select name="student_id" class="form-control mr-10" onchange="this.form.submit()">
            <?php foreach ($students as $s): ?>
              <option value="<?=$s['student_id']?>" <?=($selected_student==$s['student_id'])?'selected':''?>>
                <?=$s['full_name']?>
              </option>
            <?php endforeach; ?>
          </select>

          <label class="mr-5"><strong>Term:</strong></label>
          <select name="term" class="form-control mr-10" onchange="this.form.submit()">
            <?php for($i=1;$i<=4;$i++): ?>
              <option value="<?=$i?>" <?=($term==$i)?'selected':''?>>T<?=$i?></option>
            <?php endfor; ?>
          </select>

          <label class="mr-5"><strong>Week:</strong></label>
          <select name="week_no" class="form-control mr-10" onchange="this.form.submit()">
            <?php for($w=1;$w<=11;$w++): ?>
              <option value="<?=$w?>" <?=($week_no==$w)?'selected':''?>>Week <?=$w?></option>
            <?php endfor; ?>
          </select>
        </form>

        <?php if (!$selected_student): ?>
          <div class="alert alert-info">Choose a student to start.</div>
        <?php else: ?>
          <?php foreach ($traits_def as $catKey => $cat): ?>
            <h4 style="margin-top:20px;"><?=$cat['label']?></h4>
            <table class="table table-bordered table-condensed">
              <thead>
                <tr>
                  <th style="width:60%;">Trait</th>
                  <th style="width:40%;">Score (1=Needs Improvement → 4=Excellent)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cat['items'] as $key => $label): ?>
                  <tr>
                    <td><?=html_escape($label)?></td>
                    <td>
                      <select class="form-control trait-input"
                              data-key="<?=$key?>"
                              data-category="<?=$catKey?>">
                        <option value=""></option>
                        <?php for($v=1;$v<=4;$v++): ?>
                          <option value="<?=$v?>" <?= (isset($scores[$key]) && (int)$scores[$key]===$v) ? 'selected' : '' ?>><?=$v?></option>
                        <?php endfor; ?>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var base = "<?=base_url('weekly_traits/save')?>";
  var student_id = "<?= (int)$selected_student ?>";
  var term = "<?= (int)$term ?>";
  var week_no = "<?= (int)$week_no ?>";

  function postCell($el) {
    var payload = {
      student_id: student_id,
      term: term,
      week_no: week_no,
      category: $el.data('category'),
      trait_key: $el.data('key'),
      score: $el.val()
    };
    $.post(base, payload).fail(function(){
      alert('Save failed. Please try again.');
    });
  }
  $('.trait-input').on('change', function(){ postCell($(this)); });
})();
</script>
