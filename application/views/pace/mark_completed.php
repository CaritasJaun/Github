<div class="panel">
    <div class="panel-heading">
        <h4 class="panel-title">Mark PACEs as Completed</h4>
    </div>
    <div class="panel-body">
        <form method="get" class="form-inline mb-md">
            <label class="mr-sm">Student:</label>
            <select name="student_id" class="form-control mr-sm" required>
                <option value="">Select</option>
                <?php foreach ($students as $stu): ?>
                    <option value="<?= $stu->id ?>" <?= $selected_student == $stu->id ? 'selected' : '' ?>>
                        <?= $stu->first_name . ' ' . $stu->last_name ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="mr-sm">Term:</label>
            <select name="term" class="form-control mr-sm">
                <option value="">All</option>
                <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q): ?>
                    <option value="<?= $q ?>" <?= $selected_term == $q ? 'selected' : '' ?>><?= $q ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Load</button>
        </form>

        <?php if (!empty($pace_assignments)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Pace Number</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Mark as Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pace_assignments as $pace): ?>
                            <tr>
                                <td><?= html_escape($pace->pace_number) ?></td>
                                <td><?= html_escape($pace->term) ?></td>
                                <td id="status-<?= $pace->id ?>"><?= ucfirst(html_escape($pace->status)) ?></td>
                                <td>
                                    <?php if ($pace->status != 'completed'): ?>
                                        <button type="button" class="btn btn-success btn-sm" onclick="markComplete(<?= $pace->id ?>)">Mark Completed</button>
                                    <?php else: ?>
                                        <span class="text-success">✔ Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($selected_student): ?>
            <div class="alert alert-info">No PACEs found for this student in the selected term.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function markComplete(id) {
    if (!confirm("Mark this PACE as completed?")) return;
    fetch("<?= base_url('pace/update_status') ?>", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&status=completed`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById(`status-${id}`).innerText = 'Completed';
        } else {
            alert("Failed to update status.");
        }
    })
    .catch(() => alert("Something went wrong. Please try again."));
}
</script>
