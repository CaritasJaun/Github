<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div id="ai-box" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;max-width:680px;margin:10px 0;">
  <div id="ai-log" style="height:260px;overflow:auto;font-size:14px;"></div>
  <div style="display:flex;gap:6px;margin-top:8px;">
    <input id="ai-input" type="text" placeholder="Ask EduAssist AI..." style="flex:1;">
    <button id="ai-send" type="button">Ask</button>
  </div>
</div>
<script>
(function(){
  const log   = document.getElementById('ai-log');
  const input = document.getElementById('ai-input');
  const send  = document.getElementById('ai-send');

  // CI CSRF (safe if protection is on)
  var csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
  var csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

  function print(who, html) {
    const div = document.createElement('div');
    div.style.margin = '6px 0';
    div.innerHTML = `<strong>${who}:</strong> ${html}`;
    log.appendChild(div); log.scrollTop = log.scrollHeight;
  }

  send.addEventListener('click', function(){
    const q = (input.value || '').trim();
    if(!q) return;
    print('You', q.replace(/\n/g,'<br>'));
    input.value = '';
    const params = new URLSearchParams();
    params.append('message', q);
    // add CSRF only if CI expects it
    params.append(csrfName, csrfHash);

    fetch('<?= site_url('ai/chat'); ?>', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: params
    }).then(r=>r.json()).then(res=>{
      // refresh CSRF if server sent a new one in response
      if(res.csrf) { csrfHash = res.csrf; }
      print('EduAssist AI', (res.answer || '(no answer)').replace(/\n/g,'<br>'));
    }).catch(e=>print('EduAssist AI (error)', String(e)));
  });
})();
</script>
