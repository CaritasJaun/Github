<div class="panel">
    <div class="panel-heading">
        <h3 class="panel-title">Welcome, <?= $this->session->userdata('name'); ?>!</h3>
    </div>
    <div class="panel-body">
        <div class="row text-center">
            <div class="col-md-3 col-sm-6">
                <div class="widget">
                    <div class="widget-icon bg-primary"><i class="fas fa-book"></i></div>
                    <div class="widget-content">
                        <h4 class="widget-title">My Subjects</h4>
                        <span><?= count($this->db->where('student_id', get_loggedin_user_id())->get('enroll')->result()); ?></span>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="widget">
                    <div class="widget-icon bg-success"><i class="fas fa-clipboard-check"></i></div>
                    <div class="widget-content">
                        <h4 class="widget-title">Completed PACEs</h4>
                        <span>
                            <?= $this->db
                                ->where('student_id', get_loggedin_user_id())
                                ->where('status', 'completed')
                                ->count_all_results('student_pace_assign'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="widget">
                    <div class="widget-icon bg-warning"><i class="fas fa-hourglass-half"></i></div>
                    <div class="widget-content">
                        <h4 class="widget-title">Pending PACEs</h4>
                        <span>
                            <?= $this->db
                                ->where('student_id', get_loggedin_user_id())
                                ->where('status !=', 'completed')
                                ->count_all_results('student_pace_assign'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="widget">
                    <div class="widget-icon bg-info"><i class="fas fa-calendar-alt"></i></div>
                    <div class="widget-content">
                        <h4 class="widget-title">Upcoming Events</h4>
                        <span><?= $this->db->where('branch_id', get_loggedin_branch_id())->count_all_results('event'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <h4 class="text-center">Stay focused and finish strong!</h4>
        <p class="text-center text-muted">Your progress is being tracked. Keep pressing on with your PACEs.</p>
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

</script>

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
✅ 2. Add Bootstrap Modal to Your View (e.g. admin.php, teacher.php)
Paste this just before your </body> tag:

html
Copy
Edit
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
</script>
