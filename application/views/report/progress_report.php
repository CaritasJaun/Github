<div class="row">
    <div class="col-md-6">
        <form method="get" action="<?= base_url('report/progress_report') ?>" class="form-inline">
            <label for="student_id" class="mr-2"><strong>Select Student:</strong></label>
            <select name="student_id" id="student_id" class="form-control" onchange="this.form.submit()" required>
                <option value="">Choose</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['student_id'] ?>" <?= ($selected_student == $s['student_id']) ? 'selected' : '' ?>>
                        <?= $s['full_name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (isset($progress_counters)): ?>
    <div class="col-md-6 text-right" style="margin-top:4px;">
        <span class="label label-default" style="margin-right:6px;">
            Assigned: <strong><?= (int)$progress_counters['assigned'] ?></strong>
        </span>
        <span class="label label-primary" style="margin-right:6px;">
            Completed: <strong><?= (int)$progress_counters['completed'] ?></strong>
        </span>
        <span class="label label-warning" title="Counts first attempts below 80%, even if later redone and passed.">
            Below 80%: <strong><?= (int)$progress_counters['below80'] ?></strong>
        </span>
    </div>
    <?php endif; ?>
</div>

<hr>

<?php if (!empty($subjects)): ?>
    <?php $this->load->view('report/progress_report_view', [
        'student_id'          => $student_id,
        'year'                => $year,
        'subjects'            => $subjects,
        'comments'            => $comments,
        'attendance'          => $attendance ?? [],
        'scriptures'          => $scriptures ?? [],
        'reading'             => $reading ?? [],
        'traits'              => $traits ?? [],
        'general_assignments' => $general_assignments ?? [],
    ]); ?>
<?php elseif (!empty($selected_student)): ?>
    <div class="alert alert-warning mt-3">No PACE data found for the selected student.</div>
<?php endif; ?>
	