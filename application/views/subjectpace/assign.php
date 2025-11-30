<form method="post" action="">
    <label>Student:</label>
    <select name="student_id">
        <?php foreach ($students as $student): ?>
            <option value="<?= $student['id'] ?>"><?= $student['first_name'] ?> <?= $student['last_name'] ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>Term:</label>
    <select name="term">
        <option value="Q1">Q1</option>
        <option value="Q2">Q2</option>
        <option value="Q3">Q3</option>
        <option value="Q4">Q4</option>
    </select><br>

    <label>Choose PACEs (Subjects):</label><br>
    <?php foreach ($paces as $pace): ?>
        <label>
            <input type="checkbox" name="pace_ids[]" value="<?= $pace['id'] ?>">
            <?= $pace['name'] ?>
        </label><br>
    <?php endforeach; ?>

    <br><button type="submit">Assign PACEs</button>
</form>
?php if (!empty($assigned)): ?>
  <h5 class="mt-md">Previously Assigned PACEs</h5>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>PACE #</th>
        <th>Status</th>
        <th>Term</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($assigned as $row): ?>
      <tr>
        <td><?= html_escape($row->pace_number) ?></td>
        <td><?= html_escape(ucfirst($row->status)) ?></td>
        <td><?= html_escape($row->term) ?></td>
        <td>
          <?php if ($row->status !== 'completed'): ?>
            <a href="<?= base_url('pace/unassign/' . $row->id) ?>"
               class="btn btn-danger btn-xs"
               onclick="return confirm('Unassign this PACE?');">
               Unassign
            </a>
          <?php else: ?>
            <em class="text-muted">Completed</em>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
