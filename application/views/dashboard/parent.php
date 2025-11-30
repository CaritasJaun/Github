<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <!-- My Children -->
    <div class="col-md-12">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title">My Children</h4></div>
            <div class="panel-body">
                <ul class="list-group">
                    <?php if (empty($my_children)): ?>
                        <li class="list-group-item">No children linked</li>
                    <?php else: ?>
                        <?php foreach ($my_children as $child): ?>
                            <li class="list-group-item">
                                <?= $child->full_name ?> - <?= $child->class_name ?>
                                <a href="<?= base_url("student/profile/{$child->id}") ?>" class="btn btn-xs btn-primary float-right">View</a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-md">
    <!-- PACE Progress -->
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title">PACE Progress</h4></div>
            <div class="panel-body">
                <?php if (!empty($pace_progress_chart)): ?>
                    <div id="paceProgressChart" style="height:280px;"></div>
                <?php else: ?>
                    <p>No progress data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notices -->
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title">Notices</h4></div>
            <div class="panel-body">
                <ul class="list-group">
                    <?php foreach ($notices ?? [] as $notice): ?>
                        <li class="list-group-item"><?= $notice->title ?></li>
                    <?php endforeach; ?>
                </ul>
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

<script>
    var paceChart = echarts.init(document.getElementById('paceProgressChart'));
    paceChart.setOption({
        tooltip: {},
        xAxis: {
            type: 'category',
            data: <?= json_encode($pace_terms ?? []) ?>
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            name: 'Completed PACEs',
            type: 'bar',
            data: <?= json_encode($pace_counts ?? []) ?>
        }]
    });

$(document).ready(function () {
    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        editable: false,
        eventLimit: true,
        events: {
            url: base_url + 'event/get_events_list',
            type: 'GET',
            error: function () {
                alert('Could not load events.');
            }
        },
        eventClick: function(event) {
            // AJAX to load event details
            $.post(base_url + "event/getDetails", { event_id: event.id }, function(html) {
                $('#eventDetailsModal .modal-body').html(html);
                $('#eventDetailsModal').modal('show');
            });
        }
    });
});
</script>