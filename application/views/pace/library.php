<div class="panel">
    <header class="panel-heading"><h4>PACE Library</h4></header>
    <div class="panel-body">

        <!-- choose subject -->
        <form method="get" class="mb-lg">
            <select name="subject_id" onchange="this.form.submit()" class="form-control">
                <option value="">Select Subject</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s->id ?>" <?= $current==$s->id?'selected':'';?>>
                        <?= html_escape($s->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($current): ?>
        <!-- add new pace -->
        <form method="post" action="<?= base_url('subjectpace/save') ?>" class="mb-lg form-inline">
            <input type="hidden" name="subject_id" value="<?= $current ?>">
            <input type="number" name="pace_number" placeholder="PACE #" class="form-control" required>
            <input type="text"   name="title" placeholder="Optional title" class="form-control">
            <button class="btn btn-primary">Add</button>
            <?= csrf_token() ?>
        </form>

        <!-- list -->
        <table class="table table-bordered">
            <thead><tr><th>#</th><th>PACE number</th><th>Title</th><th width="50"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= $r->id ?></td>
                    <td><?= $r->pace_number ?></td>
                    <td><?= html_escape($r->title) ?></td>
                    <td>
                        <a href="<?= base_url('subjectpace/delete/'.$r->id.'/'.$current) ?>"
                           class="btn btn-danger btn-xs"
                           onclick="return confirm('Delete?')">
                           <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
