<div class="row">
    <div class="col-md-6 offset-md-3">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">Set Term Start Dates</h4>
            </header>
            <div class="panel-body">
                <form action="<?= base_url('monitor_goal_check/save_term_date'); ?>" method="post">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>"
                           value="<?= $this->security->get_csrf_hash(); ?>" />
                    <div class="form-group">
                        <label for="term_id">Select Term</label>
                        <select name="term_id" class="form-control" required>
                            <option value="">Select Term</option>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                            <option value="4">Term 4</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>

                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary">
                            Save Term Date
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <?php if (!empty($term_dates)): ?>
        <div class="mt-4 col-md-12">
            <h4 class="text-center">Allocated Term Dates</h4>
            <table class="table table-bordered">
<thead>
<tr>
    <th>Term</th>
    <th>Start Date</th>
    <th>End Date (Editable)</th>
    <th>Covered Weeks</th>
    <th>Weeks in Term</th>
</tr>
</thead>
                <tbody>
                    <?php foreach ($term_dates as $term): 
                        $start_date = new DateTime($term['start_date']);

                        if (!empty($term['end_date'])) {
                            $end_date = new DateTime($term['end_date']);
                        } else {
                            $end_date = (clone $start_date)->modify('+10 weeks'); // default to 11-week term
                        }

                        $start_week = $start_date->format("W");
                        $end_week = $end_date->format("W");
                    ?>
                    <tr>
    <td>Term <?= $term['term_id'] ?></td>
    <td><?= $term['start_date_obj']->format('Y-m-d') ?></td>
    <td>
        <form action="<?= base_url('monitor_goal_check/update_term_end_date'); ?>" method="post" class="form-inline d-flex align-items-center">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>" />
            <input type="hidden" name="term_id" value="<?= $term['term_id'] ?>">
            <input type="date" name="end_date" value="<?= $term['end_date_obj']->format('Y-m-d') ?>" class="form-control form-control-sm mr-2" required>
            <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
        </form>
    </td>
    <td>Week <?= $term['start_week'] ?> – Week <?= $term['end_week'] ?></td>
    <td><?= $term['total_weeks'] ?></td>
</tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
