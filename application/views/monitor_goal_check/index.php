<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading">
        <h4 class="panel-title"><i class="fas fa-check-square"></i> Monitor Goal Check Administration</h4>
      </header>
      <div class="panel-body">

<!-- ================================================================== -->
<!--         MONITOR  GOAL  CARD  –  FILTER BAR                         -->
<!-- ================================================================== -->
<div id="monitorGoalCard">

    <!-- STUDENT SELECT ------------------------------------------------ -->
    <div class="form-group mb-2">
        <label class="control-label mb-0">Select Student</label><br>
        <select name="student_id" id="student_id"
                class="form-control form-control-sm"
                style="width:auto;min-width:160px"
                onchange="loadGoalCheck();">
            <option value="">Select</option>
            <?php if (!empty($students)): ?>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id']; ?>">
                        <?= $s['fullname'] ?? ($s['first_name'] . ' ' . $s['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option disabled>No students available</option>
            <?php endif; ?>
        </select>
    </div>

    <!-- TERM RADIO ----------------------------------------------------- -->
    <div class="form-group mb-2">
        <label class="control-label mb-0">Term</label><br>
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <label class="radio-inline me-2">
                <input type="radio"
                       name="term_id"
                       value="<?= $i; ?>"
                       onchange="loadGoalCheck();">
                Term&nbsp;#<?= $i; ?>
            </label>
        <?php endfor; ?>
    </div>

    <!-- DISPLAY OPTIONS ------------------------------------------------ -->
    <div class="form-group mb-2">
        <label class="control-label mb-0 d-block">Display Options</label>
        <label class="me-2"><input type="checkbox" id="toggle_week"> Show Day Notes</label>
    </div>

    <!--  TABLE PLACE-HOLDER ------------------------------------------- -->
    <div id="goalTableArea">
        <div class="alert alert-info text-center">
            Please choose a student and term.
        </div>
    </div>

</div><!-- /#monitorGoalCard -->

      </div>
    </section>
  </div>
</div>

<script>
function loadGoalCheck() {
    const student = $('#student_id').val();
    const term    = $('input[name=term_id]:checked').val();

    if (!student || !term) {
        $('#goalTableArea').html(
            '<div class="alert alert-info text-center">Choose student & term.</div>'
        );
        return;
    }

    $.post(
        '<?= base_url('monitor_goal_check/load_goal_check_table'); ?>',
        {
            student_id : student,
            term_id    : term,
            '<?= $this->security->get_csrf_token_name(); ?>' :
            '<?= $this->security->get_csrf_hash(); ?>'
        },
        function (html) {
            $('#goalTableArea').html(html);
        }
    ).fail(function (xhr) {
        $('#goalTableArea').html(
            '<div class="alert alert-danger text-center">Server error (' +
            xhr.status + '). Check PHP log.</div>'
        );
        console.error(xhr.responseText);
    });
}

$(function () {
    if ($('#student_id').val() && $('input[name=term_id]:checked').length) {
        loadGoalCheck();
    }
});
</script>
