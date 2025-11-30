<?php
// safe URL helper (absolute or relative)
if (!function_exists('notif_safe_url')) {
    function notif_safe_url($u) {
        $u = trim((string)$u);
        if ($u === '') return site_url('dashboard');
        if (preg_match('#^(https?:)?//#i', $u)) return $u;
        return site_url(ltrim($u, '/'));
    }
}
?>
<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading d-flex" style="display:flex;align-items:center;gap:10px;">
        <i class="fas fa-bell"></i>&nbsp;<?=
            isset($title) ? html_escape($title) : 'Notifications' ?>
        <button id="btn-clear-all" class="btn btn-warning btn-xs" style="margin-left:auto;">
          <i class="fas fa-check-double"></i> Mark all as read
        </button>
      </header>

      <div class="panel-body">
        <?php if (empty($notifications)): ?>
          <p class="text-muted">No notifications.</p>
        <?php else: ?>
          <ul class="list-group" id="notif-list-page">
            <?php foreach ($notifications as $n): ?>
              <li class="list-group-item">
                <a href="<?= site_url('notification/open/' . (int)$n['id'] . '?return=' . rawurlencode(current_url())) ?>">
                  <?= html_escape($n['message'] ?? '') ?>
                </a>
                <small class="text-muted pull-right">
                  <?= isset($n['created_at']) && $n['created_at']
                        ? date('d M Y H:i', strtotime($n['created_at']))
                        : '' ?>
                </small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<script>
(function(){
  var clearBtn = document.getElementById('btn-clear-all');
  if (!clearBtn) return;

  var csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
  var csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

  clearBtn.addEventListener('click', function(){
    var params = new URLSearchParams({[csrfName]: csrfHash});
    fetch('<?= site_url('notification/clear'); ?>', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: params
    })
    .then(r=>r.json())
    .then(function(){
  var list = document.getElementById('notif-list-page');
  if (list) {
    list.innerHTML = '';
    // add a friendly empty state
    list.insertAdjacentHTML('afterend','<p class="text-muted">No notifications.</p>');
  }
  var badge = document.querySelector('.badge-notification-count');
  if (badge) badge.textContent = '0';
});
  });
})();
</script>
