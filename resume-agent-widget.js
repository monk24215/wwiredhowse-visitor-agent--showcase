/**
 * resume-agent-widget.js
 * Self-contained chat widget for embeddable resume Q&A.
 * No dependencies. Shadow DOM isolates styles from host site.
 *
 * Embed:
 *   <script>
 *     window.RESUME_AGENT = {
 *       proxyUrl: 'https://your-domain.com/resume-agent-proxy.php',
 *       name:     'Ask About Kristian',
 *       greeting: "Hi — I can answer questions about Kristian's background, experience, and projects.",
 *       accent:   '#1a1a1a' // optional, defaults to near-black
 *     };
 *   </script>
 *   <script src="https://your-domain.com/resume-agent-widget.js" defer></script>
 */
(function () {
    'use strict';

    if (window.__resumeAgentLoaded) return;
    window.__resumeAgentLoaded = true;

    var cfg = window.RESUME_AGENT || {};
    var PROXY_URL = cfg.proxyUrl || '';
    var AGENT_NAME = cfg.name || 'Resume Assistant';
    var GREETING = cfg.greeting || 'Hi! Ask me anything about my background and experience.';
    var ACCENT = cfg.accent || '#1a1a1a';

    if (!PROXY_URL) {
        console.error('[resume-agent] window.RESUME_AGENT.proxyUrl is required');
        return;
    }

    // ---- Mount point + shadow root ----
    var container = document.createElement('div');
    container.id = 'resume-agent-root';
    container.style.cssText = 'all: initial;';
    document.body.appendChild(container);
    var shadow = container.attachShadow({ mode: 'open' });

    shadow.innerHTML = [
        '<style>',
        '  :host, * { box-sizing: border-box; }',
        '  .ra-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }',
        '  .ra-launcher {',
        '    position: fixed; bottom: 24px; right: 24px;',
        '    width: 60px; height: 60px; border-radius: 50%;',
        '    background: ' + ACCENT + '; color: #fff; border: none; cursor: pointer;',
        '    box-shadow: 0 6px 20px rgba(0,0,0,0.18);',
        '    display: flex; align-items: center; justify-content: center;',
        '    z-index: 2147483646; transition: transform 0.18s ease;',
        '  }',
        '  .ra-launcher:hover { transform: scale(1.06); }',
        '  .ra-launcher svg { width: 26px; height: 26px; }',
        '  .ra-backdrop {',
        '    position: fixed; inset: 0; background: rgba(0,0,0,0.35);',
        '    z-index: 2147483647; display: none;',
        '    align-items: flex-end; justify-content: flex-end; padding: 24px;',
        '  }',
        '  .ra-backdrop.open { display: flex; }',
        '  .ra-modal {',
        '    width: 400px; max-width: 100%; height: 600px; max-height: 90vh;',
        '    background: #fff; border-radius: 14px;',
        '    display: flex; flex-direction: column; overflow: hidden;',
        '    box-shadow: 0 24px 60px rgba(0,0,0,0.32);',
        '  }',
        '  .ra-header {',
        '    padding: 14px 18px; background: ' + ACCENT + '; color: #fff;',
        '    display: flex; justify-content: space-between; align-items: center;',
        '  }',
        '  .ra-header h3 { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: 0.2px; }',
        '  .ra-close {',
        '    background: transparent; border: none; color: #fff; cursor: pointer;',
        '    font-size: 22px; padding: 0 4px; line-height: 1; opacity: 0.85;',
        '  }',
        '  .ra-close:hover { opacity: 1; }',
        '  .ra-messages {',
        '    flex: 1; overflow-y: auto; padding: 16px;',
        '    display: flex; flex-direction: column; gap: 10px; background: #fafafa;',
        '  }',
        '  .ra-msg {',
        '    max-width: 85%; padding: 10px 14px; border-radius: 14px;',
        '    font-size: 14px; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word;',
        '  }',
        '  .ra-msg.user { align-self: flex-end; background: ' + ACCENT + '; color: #fff; border-bottom-right-radius: 4px; }',
        '  .ra-msg.assistant { align-self: flex-start; background: #fff; color: #1a1a1a; border: 1px solid #ececec; border-bottom-left-radius: 4px; }',
        '  .ra-msg.loading { color: #888; font-style: italic; }',
        '  .ra-input-area {',
        '    padding: 12px; border-top: 1px solid #ececec; display: flex; gap: 8px; background: #fff;',
        '  }',
        '  .ra-input {',
        '    flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px;',
        '    font-size: 14px; outline: none; font-family: inherit;',
        '  }',
        '  .ra-input:focus { border-color: ' + ACCENT + '; }',
        '  .ra-send {',
        '    padding: 10px 16px; background: ' + ACCENT + '; color: #fff;',
        '    border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500;',
        '  }',
        '  .ra-send:disabled { opacity: 0.5; cursor: not-allowed; }',
        '  .ra-footer { padding: 6px 12px; text-align: center; font-size: 11px; color: #aaa; background: #fff; }',
        '  @media (max-width: 480px) {',
        '    .ra-backdrop { padding: 0; }',
        '    .ra-modal { width: 100%; height: 100%; max-height: 100%; border-radius: 0; }',
        '  }',
        '</style>',
        '<div class="ra-wrap">',
        '  <button class="ra-launcher" id="ra-launcher" aria-label="Open resume assistant">',
        '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">',
        '      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        '    </svg>',
        '  </button>',
        '  <div class="ra-backdrop" id="ra-backdrop">',
        '    <div class="ra-modal" role="dialog" aria-label="Resume assistant">',
        '      <div class="ra-header">',
        '        <h3>' + escapeHtml(AGENT_NAME) + '</h3>',
        '        <button class="ra-close" id="ra-close" aria-label="Close">&times;</button>',
        '      </div>',
        '      <div class="ra-messages" id="ra-messages"></div>',
        '      <div class="ra-input-area">',
        '        <input class="ra-input" id="ra-input" type="text" placeholder="Ask a question..." autocomplete="off"/>',
        '        <button class="ra-send" id="ra-send">Send</button>',
        '      </div>',
        '      <div class="ra-footer">Powered by Claude</div>',
        '    </div>',
        '  </div>',
        '</div>'
    ].join('\n');

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var $ = function (id) { return shadow.getElementById(id); };
    var launcher = $('ra-launcher');
    var backdrop = $('ra-backdrop');
    var closeBtn = $('ra-close');
    var messagesEl = $('ra-messages');
    var input = $('ra-input');
    var sendBtn = $('ra-send');

    var history = [];
    var sending = false;

    function addMessage(role, content, opts) {
        opts = opts || {};
        var div = document.createElement('div');
        div.className = 'ra-msg ' + role + (opts.loading ? ' loading' : '');
        div.textContent = content;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return div;
    }

    addMessage('assistant', GREETING);

    function openModal() {
        backdrop.classList.add('open');
        setTimeout(function () { input.focus(); }, 50);
    }
    function closeModal() {
        backdrop.classList.remove('open');
    }

    launcher.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) closeModal();
    });
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

        fetch(PROXY_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: history })
        })
        .then(function (res) { return res.json(); })
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
