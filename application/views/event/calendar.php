<?php $csrf_name = $this->security->get_csrf_token_name(); $csrf_hash = $this->security->get_csrf_hash(); ?>
<style>
  .calendar-wrap { display:grid; grid-template-columns: 320px 1fr; gap:18px; }
  .card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
  .btn { display:inline-block; padding:8px 14px; border-radius:8px; border:1px solid #e5e7eb; background:#0ea5e9; color:#fff; cursor:pointer; }
  .btn:disabled{opacity:.6; cursor:not-allowed;}
  .form-row { margin-bottom:10px; }
  .form-row label { display:block; font-weight:600; margin-bottom:4px; }
  .form-row input[type="text"], .form-row input[type="date"] { width:100%; border:1px solid #e5e7eb; border-radius:8px; padding:8px; }
  #calendar { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:10px; }
  .hint { font-size:12px; color:#6b7280; }
</style>

<div class="calendar-wrap">
  <div class="card">
    <h4 style="margin:0 0 10px 0;"><?= translate('add') . ' ' . translate('event'); ?></h4>
    <form id="eventForm">
      <input type="hidden" name="<?= $csrf_name ?>" value="<?= $csrf_hash ?>">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="type" value="general">

      <div class="form-row">
        <label><?= translate('title'); ?></label>
        <input type="text" name="title" required>
      </div>

      <div class="form-row">
        <label><?= translate('remarks'); ?></label>
        <input type="text" name="remarks" placeholder="<?= translate('optional'); ?>">
      </div>

      <div class="form-row">
        <label><?= translate('date_of_start'); ?></label>
        <input type="date" name="start_date" required>
      </div>

      <div class="form-row">
        <label><?= translate('date_of_end'); ?></label>
        <input type="date" name="end_date">
      </div>

      <div class="form-row">
        <label><input type="checkbox" name="show_website" value="1"> <?= translate('show_on_website'); ?></label>
      </div>

      <div class="form-row">
        <label><input type="checkbox" name="highlight_class" value="1" id="hlClass"> <?= translate('highlight'); ?> (<?= translate('class'); ?> — <?= translate('green'); ?>)</label>
      </div>

      <div class="form-row" id="classPicker" style="display:none">
        <label><?= translate('class'); ?> ID</label>
        <input type="text" name="class_id" value="<?= (int)($my_class_id ?? 0) ?>" placeholder="e.g. 3">
        <div class="hint"><?= translate('replace_with_dropdown_later'); ?></div>
      </div>

      <div class="form-row">
        <button type="submit" class="btn" id="btnSave"><?= translate('save'); ?></button>
      </div>
      <div class="hint" id="msg"></div>
    </form>
  </div>

  <div class="card">
    <div id="calendar"></div>
  </div>
</div>

<script>
(function () {
  // ---- server-provided context ----
  const CSRF_NAME = '<?= $csrf_name ?>';
  let   CSRF_HASH = '<?= $csrf_hash ?>';

  // teacher context (0 if not a teacher or not assigned)
  const MY_CLASS = <?= (int)($my_class_id ?? 0) ?>;
  const MY_SEC   = <?= (int)($my_section_id ?? 0) ?>;

  // ---- helpers / safe DOM refs ----
  const $form   = document.getElementById('eventForm');   // may not exist on dashboard
  const $btn    = document.getElementById('btnSave');
  const $msg    = document.getElementById('msg');
  const $hl     = document.getElementById('hlClass');
  const $classP = document.getElementById('classPicker');

  if ($hl && $classP) {
    $hl.addEventListener('change', () => {
      $classP.style.display = $hl.checked ? 'block' : 'none';
    });
  }

  // Always return integers (never empty strings)
  function getClassId() {
    if (Number.isInteger(MY_CLASS) && MY_CLASS > 0) return MY_CLASS;
    const $input = document.querySelector('input[name="class_id"]');
    const v = $input ? parseInt($input.value, 10) : 0;
    return Number.isFinite(v) && v > 0 ? v : 0;
  }
  function getSectionId() {
    return Number.isInteger(MY_SEC) && MY_SEC > 0 ? MY_SEC : 0;
  }

  // ---- FullCalendar ----
  let calendar;
  document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('calendar');
    if (!el) return;

    calendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      height: 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,listWeek'
      },
      // IMPORTANT: dashboard uses this endpoint
      events: {
        url: '<?= base_url('ajax/get_events_list'); ?>',
        method: 'GET',
        extraParams() {
          // FullCalendar adds start/end automatically; we only add our filters
          const params = {};
          params[CSRF_NAME] = CSRF_HASH;   // harmless on GET; OK if server checks token
          params.class_id   = getClassId();
          params.section_id = getSectionId();
          return params;
        },
        failure(err) {
          console.error('Calendar feed failed:', err);
          if ($msg) $msg.textContent = 'Could not load events.';
        }
      },
      eventSourceSuccess(json) {
        // quick visibility in devtools
        console.log('Calendar feed returned', Array.isArray(json) ? json.length : json, 'items');
        return json;
      }
    });

    calendar.render();
  });

  // ---- quick-add form (if present on the page) ----
  if ($form) {
    $form.addEventListener('submit', function (e) {
      e.preventDefault();
      if ($btn) $btn.disabled = true;
      if ($msg) $msg.textContent = '<?= translate('saving'); ?>...';

      const fd = new FormData($form);
      fd.set(CSRF_NAME, CSRF_HASH);

      fetch('<?= site_url('event/save_quick'); ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      })
        .then(r => r.json())
        .then(res => {
          if (res && res[CSRF_NAME]) CSRF_HASH = res[CSRF_NAME]; // rotate CSRF if sent back
          if (res && res.success) {
            if ($msg) $msg.textContent = '<?= translate('saved'); ?>.';
            $form.reset();
            if ($classP) $classP.style.display = 'none';
            if (calendar) calendar.refetchEvents();
          } else {
            if ($msg) $msg.textContent = (res && res.error)
              ? ('Error: ' + res.error)
              : '<?= translate('save_failed'); ?>';
          }
        })
        .catch(() => { if ($msg) $msg.textContent = 'Network error.'; })
        .finally(() => { if ($btn) $btn.disabled = false; });
    });
  }
})();
</script>
