<?php
$role     = (int)$this->session->userdata('loggedin_role_id');
$is_admin = in_array($role, [1,2,4,6,8], true); // super admin, admin, accounts, reception

// Pre-selected values provided by controller (student + term only for multi-grid)
$sel_student = isset($selected_student) ? $selected_student : $this->input->get('student_id');
$sel_term    = isset($selected_term)    ? $selected_term    : ($this->input->get('term') ?: 1);

// tiny helper to read either arrays or objects safely
$g = function($row, $key) {
    if (is_array($row))   return isset($row[$key]) ? $row[$key] : null;
    if (is_object($row))  return isset($row->$key) ? $row->$key : null;
    return null;
};
?>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default">
      <div class="panel-heading"><h3 class="panel-title">Assign PACEs</h3></div>
      <div class="panel-body">

        <!-- MULTI-SUBJECT GRID :: filters + actions -->
        <form action="javascript:void(0)" class="form-inline" style="margin-bottom:15px;">
          <select name="student_id" id="student_id" class="form-control" required>
            <option value="">Select Student</option>
            <?php foreach ((array)$students as $s): ?>
              <?php $sid = (int)$g($s,'id'); ?>
              <option value="<?= $sid; ?>" <?= ((string)$sel_student===(string)$sid?'selected':''); ?>>
                <?= html_escape(trim($g($s,'first_name').' '.$g($s,'last_name'))); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- send NUMERIC values (1..4) but show Q1..Q4 as labels -->
          <select name="term" id="term" class="form-control">
            <?php for ($q=1; $q<=4; $q++): ?>
              <option value="<?= $q; ?>" <?= ((string)$sel_term===(string)$q?'selected':''); ?>><?= 'Q'.$q; ?></option>
            <?php endfor; ?>
          </select>

          <button id="btnLoadGrid" class="btn btn-default" type="button">Load Grid</button>
          <button id="btnAssignSelected" class="btn btn-primary" type="button" disabled>Assign Selected</button>
        </form>

        <!-- Grid will be injected here -->
        <div id="assignGridWrap">
          <div class="text-muted">Select a student and quarter, then click <strong>Load Grid</strong>.</div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var URL_LOAD  = "<?= base_url('pace/load_assign_grid'); ?>";
    var URL_SAVE  = "<?= base_url('pace/batch_assign'); ?>";
  var CSRF_NAME = "<?= $this->security->get_csrf_token_name(); ?>";
  var CSRF_HASH = "<?= $this->security->get_csrf_hash(); ?>";

  var $wrap = $('#assignGridWrap');
  var $btnAssign = $('#btnAssignSelected');

  function msg(type, title, text){
    if (window.Swal && Swal.fire) Swal.fire({icon:type,title:title||'',text:text||''});
    else alert((title?title+': ':'')+(text||''));
  }

  // Load grid as GET (no CSRF needed for read-only)
  $('#btnLoadGrid').on('click', function(){
    var sid  = $('#student_id').val();
    var term = $('#term').val();
    if(!sid){ return msg('warning','Select a student'); }

    $btnAssign.prop('disabled', true);
    $.ajax({
      url: URL_LOAD,
      type: 'GET',
      data: {student_id: sid, term: term},
      dataType: 'json',
      headers: {'X-Requested-With':'XMLHttpRequest'}, // belt & braces
      success: function(res){
        if (typeof res === 'string') { try { res = JSON.parse(res); } catch(e){} }
        if (res && res[CSRF_NAME]) CSRF_HASH = res[CSRF_NAME];
        if (res && res.status) {
          $wrap.html(res.html || '');
          $btnAssign.prop('disabled', false);
        } else {
          msg('error','Could not load grid', (res && res.message) ? res.message : '');
        }
      },
      error: function(xhr){
        // Show real cause to speed up debugging
        var detail = 'HTTP '+xhr.status+' '+(xhr.statusText||'')+
                     (xhr.responseText ? '\n'+xhr.responseText.substring(0,240) : '');
        console.error('load_assign_grid failed:', detail);
        msg('error','Network error','Grid load failed\n'+detail);
      }
    });
  });

  // Save stays POST (protected) using the routed URL
  $('#btnAssignSelected').on('click', function(){
    var sid  = $('#student_id').val();
    var term = $('#term').val();
    var items = [];
    $wrap.find('.pace-check:checked:not(:disabled)').each(function(){
      items.push({subject_id: $(this).data('subject'), pace_no: $(this).data('pace')});
    });
    if(!items.length){ return msg('info','No PACEs selected'); }

    var payload = {student_id:sid, term:term, items:items};
    payload[CSRF_NAME] = CSRF_HASH;

    var $btn = $(this).prop('disabled', true);
    $.ajax({
      url: URL_SAVE,
      type: 'POST',
      data: payload,
      dataType: 'json',
      success: function(res){
        if (typeof res === 'string') { try { res = JSON.parse(res); } catch(e){} }
        if (res && res[CSRF_NAME]) CSRF_HASH = res[CSRF_NAME];
        if (res && res.status) {
          msg('success','Saved', (res.inserted||0)+' PACEs assigned');
          $('#btnLoadGrid').trigger('click');
        } else {
          msg('error','Save failed', (res && res.message) ? res.message : '');
          $btn.prop('disabled', false);
        }
      },
      error: function(xhr){
        var detail = 'HTTP '+xhr.status+' '+(xhr.statusText||'');
        console.error('batch_assign failed:', detail, xhr.responseText);
        msg('error','Network error','Save failed\n'+detail);
        $btn.prop('disabled', false);
      }
    });
  });

  // select-all per subject stays the same...
  $('#assignGridWrap').on('change', '.check-all-subject', function(){
    var sid = $(this).data('subject');
    var on  = $(this).is(':checked');
    $('.pace-check[data-subject="'+sid+'"]:not(:disabled)',$('#assignGridWrap')).prop('checked', on);
  });

  <?php if (!empty($sel_student)): ?>$('#btnLoadGrid').trigger('click');<?php endif; ?>
})();
</script>

