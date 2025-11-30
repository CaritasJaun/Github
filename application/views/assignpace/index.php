<div class="panel">
    <header class="panel-heading">
        <div class="panel-title">Assign PACEs</div>
    </header>
    <div class="panel-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Grade</th>
                    <th>Subject</th>
                    <th>PACE Number</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paces)): foreach ($paces as $p): ?>
                    <tr>
                        <td><?= $p->grade ?></td>
                        <td><?= $p->subject ?></td>
                        <td><?= $p->pace_number ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" class="text-center">No PACEs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
