<?php
defined('BASEPATH') or exit('No direct script access allowed');
?>
<div class="row">
    <!-- Total Students -->
    <div class="col-md-4">
        <div class="widget widget-stats bg-blue">
            <div class="stats-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stats-info">
                <h4>Total Students</h4>
                <p><?= $student_count ?></p>

                <!-- 🔽 Add the selector inside here -->
                <div class="form-group mt-xs">
                    <select class="form-control" id="student_selector_widget">
                        <option value="">-- Select Student --</option>
                        <?php
                            $students = $this->db->select('student.id, first_name, last_name')
                                ->from('student')
                                ->join('enroll', 'enroll.student_id = student.id')
                                ->where('student.branch_id', get_loggedin_branch_id())
                                ->get()->result_array();
                            foreach ($students as $stu):
                        ?>
                            <option value="<?= base_url('student/profile/' . $stu['id']) ?>">
                                <?= $stu['first_name'] . ' ' . $stu['last_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Teachers -->
    <div class="col-md-4">
        <div class="widget widget-stats bg-purple">
            <div class="stats-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="stats-info">
                <h4>Total Teachers</h4>
                <p><?= $teacher_count ?></p>
            </div>
        </div>
    </div>

    <!-- Total Parents -->
    <div class="col-md-4">
        <div class="widget widget-stats bg-green">
            <div class="stats-icon"><i class="fas fa-user-friends"></i></div>
            <div class="stats-info">
                <h4>Total Parents</h4>
                <p><?= $parent_count ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-md">
    <!-- Income vs Expense Pie -->
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title">Income vs Expense of the Month</h4>
            </div>
            <div class="panel-body">
                <div id="incomeExpensePie" style="height:300px;"></div>
            </div>
        </div>
    </div>

    <!-- Student by Grade Donut -->
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title">Student Quantity by Class</h4>
            </div>
            <div class="panel-body">
                <div id="studentByClassDonut" style="height:300px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Monthly Admissions Chart -->
    <div class="col-md-12">
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title">Monthly Admissions</h4>
            </div>
            <div class="panel-body">
                <div id="monthlyAdmissionChart" style="height:280px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Calendar Widget -->
    <div class="col-md-8">
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title">Calendar</h4>
            </div>
            <div class="panel-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <!-- Birthday Reminders -->
    <div class="col-md-4">
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title">Today Birthdays</h4>
            </div>
            <div class="panel-body">
                <ul class="list-group">
                    <?php
                    $birthdays = get_today_birthdays($school_id);
                    if (empty($birthdays)) {
                        echo '<li class="list-group-item">No birthdays today</li>';
                    } else {
                        foreach ($birthdays as $b) {
                            echo '<li class="list-group-item">' .
                                htmlspecialchars($b->first_name ?? '') . ' ' .
                                htmlspecialchars($b->last_name ?? '') . ' - ' .
                                htmlspecialchars(ucfirst($b->role ?? '')) .
                                '</li>';
                        }
                    }
                    ?>
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
    // Income vs Expense Pie
    var incomeExpenseChart = echarts.init(document.getElementById('incomeExpensePie'));
    incomeExpenseChart.setOption({
        tooltip: { trigger: 'item' },
        series: [{
            type: 'pie',
            radius: '70%',
            data: [
                { value: <?= $income_vs_expense['income'] ?? 0 ?>, name: 'Income' },
                { value: <?= $income_vs_expense['expense'] ?? 0 ?>, name: 'Expense' }
            ]
        }]
    });

    // Student By Class Donut
    var studentDonut = echarts.init(document.getElementById('studentByClassDonut'));
    studentDonut.setOption({
        tooltip: {},
        series: [{
            type: 'pie',
            radius: ['40%', '70%'],
            data: <?= json_encode(array_values($student_by_class)) ?>
        }]
    });

    // Monthly Admissions (Fixed Version)
    var monthlyChart = echarts.init(document.getElementById('monthlyAdmissionChart'));
    monthlyChart.setOption({
        xAxis: {
            type: 'category',
            data: ["Jan", "Feb", "Mar", "Apr"]
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            data: [5, 10, 8, 15],
            type: 'line',
            smooth: true
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
 $(document).ready(function () {
        $('#student_selector_widget').on('change', function () {
            const url = $(this).val();
            if (url !== '') {
                window.location.href = url;
            }
        });
    });

</script>

<?php $this->load->view('partials/ai_boot'); ?>