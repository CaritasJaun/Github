(function () {
  "use strict";

  function initFab() {
    try {
      if (document.getElementById('eaAiToggle')) return;

      // ---------- CSS (very high z-index; sits above WhatsApp) ----------
      var css =
        ".ea-ai-fab{position:fixed;right:20px;bottom:96px;z-index:2147483647;display:block}" +
        ".ea-ai-fab button{min-width:56px;height:56px;padding:0 14px;border:none;border-radius:28px;box-shadow:0 4px 12px rgba(0,0,0,.2);background:#1f2937;color:#fff;font-weight:600;cursor:pointer;letter-spacing:.5px}" +
        ".ea-ai-panel{position:fixed;right:20px;bottom:160px;width:360px;max-width:90vw;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.18);overflow:hidden;display:none;z-index:2147483647}" +
        ".ea-ai-head{background:#111827;color:#fff;padding:10px 12px;font-weight:600;font-size:14px}" +
        ".ea-ai-body{padding:10px}" +
        ".ea-ai-log{height:240px;overflow:auto;font-size:14px;background:#f9fafb;padding:8px;border-radius:8px}" +
        ".ea-ai-row{display:flex;gap:6px;margin-top:8px}" +
        ".ea-ai-row input{flex:1;padding:8px;border:1px solid #e5e7eb;border-radius:8px}" +
        ".ea-ai-row button{padding:8px 12px;border:none;background:#111827;color:#fff;border-radius:8px;cursor:pointer}" +
        "@media (max-width:600px){.ea-ai-fab{bottom:88px}.ea-ai-panel{right:10px;width:94vw;bottom:150px}}";
      var st = document.createElement('style');
      st.type = 'text/css';
      st.appendChild(document.createTextNode(css));
      document.head.appendChild(st);

      // ---------- FAB ----------
      var fab = document.createElement('div');
      fab.className = 'ea-ai-fab';
      fab.innerHTML = '<button id="eaAiToggle" title="How to">HOW&nbsp;TO</button>';
      document.body.appendChild(fab);

      // ---------- Panel ----------
      var panel = document.createElement('div');
      panel.className = 'ea-ai-panel';
      panel.id = 'eaAiPanel';
      panel.innerHTML =
        '<div class="ea-ai-head">EduAssist AI - How to</div>' +
        '<div class="ea-ai-body">' +
          '<div id="eaAiLog" class="ea-ai-log"></div>' +
          '<div class="ea-ai-row">' +
            '<input id="eaAiInput" type="text" placeholder="Ask EduAssist AI...">' +
            '<button id="eaAiSend" type="button">Ask</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(panel);

      // ---------- Behaviour ----------
      var toggle = document.getElementById('eaAiToggle');
      var log    = document.getElementById('eaAiLog');
      var input  = document.getElementById('eaAiInput');
      var send   = document.getElementById('eaAiSend');

      // Greet once on first open
var greeted = false;
toggle.addEventListener('click', function () {
  var isOpen = (panel.style.display === 'block');
  panel.style.display = isOpen ? 'none' : 'block';
  if (!isOpen && !greeted) {
    line('EduAssist AI', 'Hi, how are you today? How can I be of assistance?');
    greeted = true;
  }
});

      function line(who, text) {
        var el = document.createElement('div');
        el.style.margin = '6px 0';
        el.innerHTML = '<b>' + who + ':</b> ' + String(text || '').replace(/\n/g, '<br>');
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
      }

      function buildUrl(path) {
        var b = (typeof window.base_url === 'string' && window.base_url) ? window.base_url : '/';
        if (b.substr(-1) !== '/') b += '/';
        return b + path.replace(/^\/+/, '');
      }

      async function ask(q) {
        try {
          var params = new URLSearchParams();
          params.append('message', q);

          // CSRF from meta tags (created by ai_boot.php)
          try {
            var n = document.querySelector('meta[name="csrf-name"]');
            var h = document.querySelector('meta[name="csrf-hash"]');
            if (n && h) params.append(n.getAttribute('content'), h.getAttribute('content'));
          } catch (e) {}
          
          try {
            var ctx = document.querySelector('meta[name="ea-context"]');
            if (ctx && ctx.content) params.append('context', ctx.content);
            } catch (e) {}

          // Call your CI endpoint (Assistants or simple chat). Default to ai/assist.
          var res = await fetch(buildUrl('ai/assist'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
          }).then(function (r) { return r.json(); })
            .catch(function (e) { return { answer: '(network error) ' + e }; });

          if (res && res.raw && res.raw.error) {
            try {
              var e = res.raw;
              if (/OpenAI|assistant|key|Missing/i.test(JSON.stringify(e))) {
                res.answer = "⚠️ OpenAI Assistant not configured. Set API key & Assistant ID in application/config/openai.php.";
              }
            } catch (_) {}
          }
          line('EduAssist AI', (res && res.answer) ? res.answer : '(no answer)');
        } catch (e) {
          line('EduAssist AI', '(unexpected error) ' + e);
        }
      }

      send.addEventListener('click', function () {
        var q = (input.value || '').trim();
        if (!q) return;
        line('You', q);
        input.value = '';
        ask(q);
      });
      input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send.click(); });
    } catch (e) {
      if (window.console && console.warn) console.warn('EA AI FAB init skipped:', e);
    }
  }

  // Run after everything else, so your charts/widgets initialize first
  if (document.readyState === 'complete') {
    setTimeout(initFab, 0);
  } else {
    window.addEventListener('load', function () { setTimeout(initFab, 0); });
  }
})();
