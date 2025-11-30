<div class="row">
    <div class="col-md-4">
        <form action="<?= base_url('term') ?>" method="post" class="form-horizontal">
            <input type="hidden" name="id" value="<?= isset($term->id) ? $term->id : '' ?>">
            <div class="form-group">
                <label class="control-label">Term Name</label>
                <input type="text" class="form-control" name="name" value="<?= isset($term->name) ? $term->name : '' ?>" required>
            </div>
            <div class="form-group">
                <label class="control-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= isset($term->start_date) ? $term->start_date : '' ?>" required>
            </div>
            <div class="form-group">
                <label class="control-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?= isset($term->end_date) ? $term->end_date : '' ?>" required>
            </div>
            <div class="form-group">
                <label class="control-label">Year</label>
                <input type="number" class="form-control" name="year" value="<?= isset($term->year) ? $term->year : date('Y') ?>" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-success"><?= isset($term->id) ? 'Update Term' : 'Add Term' ?></button>
            </div>
        </form>
    </div>

    <div class="col-md-8">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Term</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($terms as $row): ?>
                    <tr>
                        <td><?= $row->name ?></td>
                        <td><?= $row->start_date ?></td>
                        <td><?= $row->end_date ?></td>
                        <td><?= $row->year ?></td>
                        <td>
                            <a href="<?= base_url('term/edit/' . $row->id) ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="<?= base_url('term/delete/' . $row->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this term?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
