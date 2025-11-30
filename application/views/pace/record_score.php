<div class="panel">
    <header class="panel-heading"><h4>Record PACE Scores</h4></header>
    <div class="panel-body">
        <!-- filter form -->
        <form method="get">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Select</option>
                    <?php foreach ($students as $stu): ?>
                        <option value="<?= $stu->id ?>" <?= $selected_student == $stu->id ? 'selected' : ''; ?>>
                            <?= html_escape($stu->first_name . ' ' . $stu->last_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($pending): ?>
            <form method="post" action="<?= base_url('pace/record_score_save') ?>">
                <!-- ►► CSRF -->
                <input type="hidden"
                       name="<?= $this->security->get_csrf_token_name(); ?>"
                       value="<?= $this->security->get_csrf_hash(); ?>">
                <!-- /CSRF -->

                <!-- keep the filters after POST -->
                <input type="hidden" name="filter_student" value="<?= $selected_student ?>">
                <input type="hidden" name="filter_term"    value="<?= $selected_term ?>"><!-- ★ NEW LINE -->

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>PACE #</th>
                            <th width="90">Score</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending as $row): ?>
    <tr>
        <td><?= html_escape($row->subject_name) ?></td>
        <td><?= html_escape($row->pace_number) ?></td>
        <td>
            <?php
                $id = $row->id;
                $first = $row->first_attempt_score;
                $second = $row->second_attempt_score;
            ?>

            <?php if (is_null($first)): ?>
                <!-- First attempt input -->
                <input type="number" name="rows[<?= $id ?>][score]"
                       min="0" max="100"
                       class="form-control"
                       placeholder="1st Attempt">
                <input type="hidden" name="rows[<?= $id ?>][id]" value="<?= $id ?>">
            <?php elseif ($first < 80 && is_null($second)): ?>
                <!-- Show first score and input for second attempt -->
                <div>1st: <strong><?= $first ?></strong></div>
                <input type="number" name="rows[<?= $id ?>][score]"
                       min="0" max="100"
                       class="form-control mt-xs"
                       placeholder="2nd Attempt">
                <input type="hidden" name="rows[<?= $id ?>][id]" value="<?= $id ?>">
            <?php else: ?>
                <!-- Both attempts done -->
                <div>1st: <strong><?= $first ?></strong></div>
                <div>2nd: <strong><?= $second ?? '-' ?></strong></div>
                		<em class="text-muted">Both attempts recorded</em>
            			<?php endif; ?>
        			</td>
       				 <td>
          	 			<input type="text"
               				    name="rows[<?= $id ?>][remarks]"
                 			    class="form-control"
                  			    placeholder="Optional remarks">
        				</td>
   				 </tr>
		    <?php endforeach; ?>
                    </tbody>
                </table>

                <button class="btn btn-primary">Save Scores</button>
            </form>
        <?php elseif ($selected_student): ?>
            <p><em>No pending PACEs for this student.</em></p>
        <?php endif; ?>
    </div>
</div>
