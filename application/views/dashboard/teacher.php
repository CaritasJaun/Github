<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
  $csrf_name = $this->security->get_csrf_token_name();
  $csrf_hash = $this->security->get_csrf_hash();
 
?>
<div class="row">
    <!-- My Students Overview -->
    <div class="col-md-6">
        <div class="widget widget-stats bg-orange">
            <div class="stats-icon"><i class="fas fa-users"></i></div>
            <div class="stats-info">
                <h4>My Students</h4>
                <p><?= $my_students_count ?? 0 ?></p>
                <p class="text-white">No students assigned.</p>
            </div>
        </div>
    </div>

    <!-- Student Profile Selector -->
    <div class="col-md-6">
        <div class="widget widget-stats bg-purple">
            <div class="stats-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stats-info">
                <h4>Student Profile</h4>
                <?php if (!empty($my_students)): ?>
                    <div class="form-group mt-sm">
                        <select class="form-control" id="student_select">
                            <option value="">-- Select Student --</option>
<?php foreach ($my_students as $stu): 
      // Always prefer the real student.id; fall back to id if that’s what your query returns
      $sid = (int)($stu['student_id'] ?? $stu['id'] ?? 0);
?>
    <option value="<?= $sid ?>">
        <?= htmlspecialchars(trim(($stu['first_name'] ?? '') . ' ' . ($stu['last_name'] ?? ''))) ?>
    </option>
<?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <p class="text-white">No students available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-md">
    <!-- Pending PACE Scores (grouped by student) -->
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title">Pending PACE Scores</h4>
            </div>
            <div class="panel-body">
                <ul class="list-group">
                <?php if (!empty($pending_scores) && is_array($pending_scores)): ?>
                    <?php
                    $grouped = [];
                    foreach ($pending_scores as $row) {
                        $sid = (int)($row['student_id'] ?? 0);
                        if (!isset($grouped[$sid])) {
                            $grouped[$sid] = [
                                'name'  => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                                'items' => [],
                            ];
                        }
                        $grouped[$sid]['items'][] = $row;
                    }
                    ?>
                    <?php foreach ($grouped as $sid => $bundle): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($bundle['name']) ?></strong>
                                    <span class="badge badge-warning ml-2"><?= count($bundle['items']) ?> pending</span>
                                </div>
                                <a href="<?= base_url('spc/index?student_id=' . $sid) ?>" class="btn btn-sm btn-warning" target="_blank">
                                    Open SPC
                                </a>
                            </div>

                            <div class="mt-2">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:45%;">Subject</th>
                                            <th style="width:20%;">PACE #</th>
                                            <th style="width:35%;">Assigned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bundle['items'] as $score): ?>
                                            <?php
                                                $assigned_date = isset($score['assigned_date']) ? new DateTime($score['assigned_date']) : null;
                                                $now           = new DateTime();
                                                $interval      = $assigned_date ? $assigned_date->diff($now)->days : 0;
                                                $is_overdue    = $interval > 15;
                                            ?>
                                            <tr class="<?= $is_overdue ? 'overdue-score' : '' ?>" <?= $is_overdue ? 'style="background:#fff3cd;"' : '' ?>>
                                                <td><?= htmlspecialchars($score['subject_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($score['pace_number'] ?? '') ?></td>
                                                <td><?= $assigned_date ? $assigned_date->format('d M Y') : 'N/A' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item">No pending scores</li>
                <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title">Calendar</h4></div>
            <div class="panel-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" role="dialog" aria-labelledby="eventDetailsLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="eventDetailsLabel">Event Details</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- AJAX will load event details here -->
      </div>
    </div>
  </div>
</div>

<!-- Event Details (shared lightweight modal) -->
<div class="zoom-anim-dialog modal-block modal-block-primary mfp-hide" id="modal">
  <section class="panel">
    <header class="panel-heading">
      <h4 class="panel-title"><i class="fas fa-info-circle"></i> <?= translate('event_details') ?></h4>
    </header>
    <div class="panel-body">
      <div class="table-responsive">
        <table class="table table-bordered table-condensed text-dark tbr-top" id="ev_table"></table>
      </div>
    </div>
    <footer class="panel-footer">
      <div class="text-right">
        <button class="btn btn-default modal-dismiss"><?= translate('close') ?></button>
      </div>
    </footer>
  </section>

</body>
</div>




<script>
$(function () {
  // expose CSRF for AJAX (POST to details)
  var CSRF_NAME = '<?= $csrf_name ?>';
  var CSRF_HASH = '<?= $csrf_hash ?>';

  $('#calendar').fullCalendar({
    header: { left: 'prev,next today', center: 'title', right: 'month,agendaWeek,agendaDay' },
    editable: false,
    eventLimit: true,

    events: {
      url: base_url + 'ajax/get_events_list',
      type: 'GET',
      data: function () { return {}; },
      error: function () { alert('Could not load events.'); }
    },

    // keep per-event colors (optional but harmless)
    eventRender: function (event, element) {
      var c = event.backgroundColor || event.color;
      if (c) element.css({ 'background-color': c, 'border-color': event.borderColor || c });
    },

    eventClick: function (event) {
      var payload = { id: event.id };
      payload[CSRF_NAME] = CSRF_HASH;

      $.ajax({
        url: base_url + 'event/getDetails',
        type: 'POST',
        data: payload,
        dataType: 'html',
        success: function (resp) {
          // allow accidental JSON envelope
          try { var j = JSON.parse(resp); if (j && j.html) resp = j.html; } catch (e) {}

          if (!resp || !resp.trim()) {
            resp = '<tbody><tr><td><?= translate("no_information_available") ?></td></tr></tbody>';
          }

          if ($('#ev_table').length) {
            $('#ev_table').html(resp);
            if (typeof mfp_modal === 'function') {
              mfp_modal('#modal');
            } else if ($.magnificPopup) {
              $.magnificPopup.open({ items: { src: '#modal' }, type: 'inline', closeBtnInside: true });
            }
          } else if ($('#eventDetailsModal .modal-body').length) {
            $('#eventDetailsModal .modal-body').html(resp);
            $('#eventDetailsModal').modal('show');
          } else {
            alert('<?= translate("event_details") ?>:\n\n' + $('<div>').html(resp).text());
          }
        },
        error: function (xhr) {
          var msg = '<tbody><tr><td>Error ' + xhr.status + ' — <?= translate("could_not_load") ?>.</td></tr></tbody>';
          if ($('#ev_table').length) {
            $('#ev_table').html(msg);
            if (typeof mfp_modal === 'function') { mfp_modal('#modal'); }
            else if ($.magnificPopup) { $.magnificPopup.open({ items: { src: '#modal' }, type: 'inline' }); }
          } else {
            alert('<?= translate("could_not_load") ?>');
          }
        }
      });
    }
  });

  // student profile jump
  $('#student_select').off('change').on('change', function () {
  var id = $(this).val();
  if (id) window.location.href = "<?= site_url('student/profile'); ?>/" + id;
    });
});


</script>
<?php $this->load->view('partials/ai_boot'); ?>