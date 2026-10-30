/**
 * widget.js — embeddable chat widget. Drop on any site.
 *
 * Embed:
 *   <script>
 *     window.AGENT = {
 *       host: 'https://agent.example.com', // where this folder lives, no trailing slash
 *       slug: 'resume',                    // which agent to load
 *       title: 'Ask About Kristian',       // optional override
 *       greeting: '...',                   // optional override
 *       accent: '#e8a020'                  // optional override
 *     };
 *   </script>
 *   <script src="https://agent.example.com/widget.js" defer></script>
 */
(function () {
    'use strict';
    if (window.__agentLoaded) return;
    window.__agentLoaded = true;

    var cfg = window.AGENT || {};
    var HOST = (cfg.host || '').replace(/\/+$/, '');
    var SLUG = cfg.slug || '';

    if (!HOST || !SLUG) {
        console.error('[agent] window.AGENT.host and .slug are required');
        return;
    }

    var ENDPOINT = HOST + '/api.php';
    var TITLE = cfg.title || 'Assistant';
    var GREETING = cfg.greeting || 'Hi! How can I help?';
    var ACCENT = cfg.accent || '#1a1a1a';
    var CHIPS = Array.isArray(cfg.chips) ? cfg.chips : [];

    // --- Mount + shadow root for full CSS isolation ---
    var container = document.createElement('div');
    container.id = 'agent-root';
    container.style.cssText = 'all: initial;';
    document.body.appendChild(container);
    var shadow = container.attachShadow({ mode: 'open' });

    shadow.innerHTML = [
        '<style>',
        '  :host, * { box-sizing: border-box; }',
        '  .root { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }',
        '  .launcher {',
        '    position: fixed; bottom: 24px; right: 24px;',
        '    width: 60px; height: 60px; border-radius: 50%;',
        '    background: ' + ACCENT + '; color: #fff; border: none; cursor: pointer;',
        '    box-shadow: 0 6px 20px rgba(0,0,0,0.18);',
        '    display: flex; align-items: center; justify-content: center;',
        '    z-index: 2147483646; transition: transform 0.18s ease;',
        '  }',
        '  .launcher:hover { transform: scale(1.06); }',
        '  .launcher svg { width: 26px; height: 26px; }',
        '  .backdrop {',
        '    position: fixed; inset: 0; background: rgba(0,0,0,0.35);',
        '    z-index: 2147483647; display: none;',
        '    align-items: flex-end; justify-content: flex-end; padding: 24px;',
        '  }',
        '  .backdrop.open { display: flex; }',
        '  .modal {',
        '    width: 400px; max-width: 100%; height: 600px; max-height: 90vh;',
        '    background: #fff; border-radius: 14px;',
        '    display: flex; flex-direction: column; overflow: hidden;',
        '    box-shadow: 0 24px 60px rgba(0,0,0,0.32);',
        '  }',
        '  .header {',
        '    padding: 14px 18px; background: ' + ACCENT + '; color: #fff;',
        '    display: flex; justify-content: space-between; align-items: center;',
        '  }',
        '  .header h3 { margin: 0; font-size: 15px; font-weight: 600; }',
        '  .close { background: transparent; border: none; color: #fff; cursor: pointer;',
        '    font-size: 22px; padding: 0 4px; line-height: 1; opacity: 0.85; }',
        '  .close:hover { opacity: 1; }',
        '  .messages { flex: 1; overflow-y: auto; padding: 16px;',
        '    display: flex; flex-direction: column; gap: 10px; background: #fafafa; }',
        '  .msg { max-width: 85%; padding: 10px 14px; border-radius: 14px;',
        '    font-size: 14px; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }',
        '  .msg.user { align-self: flex-end; background: ' + ACCENT + '; color: #fff; border-bottom-right-radius: 4px; }',
        '  .msg.assistant { align-self: flex-start; background: #fff; color: #1a1a1a;',
        '    border: 1px solid #ececec; border-bottom-left-radius: 4px; }',
        '  .msg.loading { color: #888; font-style: italic; }',
        '  .chips { padding: 0 16px 8px; display: flex; flex-wrap: wrap; gap: 6px; background: #fafafa; }',
        '  .chip { font-size: 12px; padding: 6px 12px; border-radius: 18px; border: 1px solid #ddd;',
        '    background: #fff; color: #444; cursor: pointer; }',
        '  .chip:hover { border-color: ' + ACCENT + '; color: ' + ACCENT + '; }',
        '  .input-area { padding: 12px; border-top: 1px solid #ececec; display: flex; gap: 8px; background: #fff; }',
        '  .input { flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px;',
        '    font-size: 14px; outline: none; font-family: inherit; }',
        '  .input:focus { border-color: ' + ACCENT + '; }',
        '  .send { padding: 10px 16px; background: ' + ACCENT + '; color: #fff;',
        '    border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; }',
        '  .send:disabled { opacity: 0.5; cursor: not-allowed; }',
        '  .footer { padding: 6px 12px; text-align: center; font-size: 11px; color: #aaa; background: #fff; }',
        '  @media (max-width: 480px) {',
        '    .backdrop { padding: 0; }',
        '    .modal { width: 100%; height: 100%; max-height: 100%; border-radius: 0; }',
        '  }',
        '</style>',
        '<div class="root">',
        '  <button class="launcher" id="launcher" aria-label="Open assistant">',
        '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">',
        '      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        '    </svg>',
        '  </button>',
        '  <div class="backdrop" id="backdrop">',
        '    <div class="modal" role="dialog">',
        '      <div class="header"><h3 id="title"></h3>',
        '        <button class="close" id="close" aria-label="Close">&times;</button></div>',
        '      <div class="messages" id="messages"></div>',
        '      <div class="chips" id="chips"></div>',
        '      <div class="input-area">',
        '        <input class="input" id="input" type="text" placeholder="Ask a question..." autocomplete="off"/>',
        '        <button class="send" id="send">Send</button>',
        '      </div>',
        '      <div class="footer">Powered by Claude</div>',
        '    </div>',
        '  </div>',
        '</div>'
    ].join('\n');

    var $ = function (id) { return shadow.getElementById(id); };
    $('title').textContent = TITLE;

    var launcher = $('launcher'), backdrop = $('backdrop'), closeBtn = $('close');
    var messagesEl = $('messages'), chipsEl = $('chips'), input = $('input'), sendBtn = $('send');

    var history = [], sending = false;

    function addMessage(role, content, opts) {
        opts = opts || {};
        var div = document.createElement('div');
        div.className = 'msg ' + role + (opts.loading ? ' loading' : '');
        div.textContent = content;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return div;
    }

    addMessage('assistant', GREETING);

    // Render chips if provided
    if (CHIPS.length) {
        CHIPS.forEach(function (label) {
            var b = document.createElement('button');
            b.className = 'chip';
            b.textContent = label;
            b.addEventListener('click', function () {
                input.value = label;
                send();
            });
            chipsEl.appendChild(b);
        });
    } else {
        chipsEl.style.display = 'none';
    }

    function openModal() { backdrop.classList.add('open'); setTimeout(function(){input.focus();}, 50); }
    function closeModal() { backdrop.classList.remove('open'); }

    launcher.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
    });

    function send() {
        if (sending) return;
        var text = input.value.trim();
        if (!text) return;
        input.value = '';
        addMessage('user', text);
        history.push({ role: 'user', content: text });

        sending = true;
        sendBtn.disabled = true;
        var loadingEl = addMessage('assistant', 'Thinking...', { loading: true });

        fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: SLUG, messages: history })
        })
        .then(function (res) { return res.json().catch(function(){return {};}); })
        .then(function (data) {
            loadingEl.remove();
            var reply = '';
            if (data && Array.isArray(data.content)) {
                reply = data.content
                    .filter(function (b) { return b.type === 'text'; })
                    .map(function (b) { return b.text; })
                    .join('\n');
            }
            if (!reply) {
                reply = (data && data.error) ? ('Error: ' + (data.error.message || data.error)) : 'Sorry, no response.';
            }
            addMessage('assistant', reply);
            history.push({ role: 'assistant', content: reply });
        })
        .catch(function () {
            loadingEl.remove();
            addMessage('assistant', 'Connection error. Please try again.');
        })
        .then(function () {
            sending = false;
            sendBtn.disabled = false;
            input.focus();
        });
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); send(); }
    });
})();
