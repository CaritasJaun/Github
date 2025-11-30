
<?php
defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="panel">
    <!-- title + “Add” button -->
    <header class="panel-heading">
        <div class="panel-title pull-left">PACE Library</div>
        <div class="pull-right">
            <a href="javascript:void(0)" class="btn btn-sm btn-primary"
               data-toggle="modal" data-target="#paceModal"
               onclick="openPaceModal();">
                + Add
            </a>
        </div>
        <div class="clearfix"></div>
    </header>

    <div class="panel-body">
        <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success">
        <?= $this->session->flashdata('success'); ?>
    </div>
<?php endif; ?>

<?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger">
        <?= $this->session->flashdata('error'); ?>
    </div>
<?php endif; ?>

        <!-- BULK 12-PACE generator -->
        <form class="form-inline mb-md" method="post"
              action="<?= base_url('subjectpace/bulk') ?>">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
            <label class="mr-sm">Bulk&nbsp;12&nbsp;PACEs:</label>

            <input type="number" name="grade_bulk" class="form-control mr-sm"
                   placeholder="Grade" min="1" required>

            <select name="subject_id_bulk" class="form-control mr-sm" required>
                <option value="">Subject</option>
                <?php foreach ($subjects as $sid=>$name): ?>
                    <option value="<?= $sid ?>"><?= html_escape($name) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="number" name="start_number" class="form-control mr-sm"
                   placeholder="Starting #" required>

            <button class="btn btn-info">Generate</button>
        </form>

        <!-- grid -->
        <table class="table table-bordered table-hover">
            <thead>
            <tr>
                <th width="40">#</th>
                <th>Grade</th>
                <th>Subject</th>
                <th>PACE #</th>
                <th width="90">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows): $i=1; foreach ($rows as $r): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $r->grade ?></td>
                    <td><?= html_escape($r->subject) ?></td>
                    <td><?= $r->pace_number ?></td>
                    <td>
                        <button class="btn btn-xs btn-info"
                                onclick="openPaceModal(
                                  '<?= $r->id ?>',
                                  '<?= $r->grade ?>',
                                  '<?= $r->subject_id ?>',
                                  '<?= $r->pace_number ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a class="btn btn-xs btn-danger"
                           href="<?= base_url('subjectpace/delete/'.$r->id) ?>"
                           onclick="return confirm('Delete this PACE?');">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center text-muted">No PACEs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- modal (add / edit) -->
<div class="modal fade" id="paceModal">
    <div class="modal-dialog">
        <form class="modal-content" method="post"
              action="<?= base_url('subjectpace/save') ?>">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
            <input type="hidden" name="id" id="pace_id">


            <div class="modal-header">
                <h5 class="modal-title">PACE</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label>Grade *</label>
                    <input type="number" min="1" id="grade"
                           name="grade" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Subject *</label>
                    <select id="subject_id" name="subject_id"
                            class="form-control" required>
                        <option value="">Select</option>
                        <?php foreach ($subjects as $sid=>$name): ?>
                            <option value="<?= $sid ?>"><?= html_escape($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>PACE&nbsp;Number *</label>
                    <input type="number" id="pace_number"
                           name="pace_number" class="form-control" required>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaceModal(id='', grade='', subject='', pace='') {
    $('#pace_id').val(id);
    $('#grade').val(grade);
    $('#subject_id').val(subject);
    $('#pace_number').val(pace);
    $('#paceModal').modal('show');
}
</script>
