<?php
// includes/concierge-widget.php — floating chat widget, included on public pages.
require_once __DIR__ . '/../config/ai-concierge.php';
?>
<div id="conciergeWidget">
  <button id="conciergeToggle" aria-label="Open AI Concierge">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
    <span>Ask Concierge</span>
  </button>

  <div id="conciergePanel" class="concierge-panel" style="display:none;">
    <div class="concierge-header">
      <div>
        <strong>Aureum Concierge</strong>
        <div style="font-size:0.74rem;opacity:0.7;">Ask about rooms, rates &amp; services</div>
      </div>
      <button id="conciergeClose" aria-label="Close">&times;</button>
    </div>
    <div id="conciergeMessages" class="concierge-messages">
      <div class="concierge-msg concierge-msg-bot">Good day. I'm the Aureum Concierge — ask me about room rates, amenities, or our services, and I'll do my best to help.</div>
    </div>
    <?php if (!AI_CONCIERGE_CONFIGURED): ?>
      <div class="stub-banner" style="margin:0 14px 10px;font-size:0.76rem;">AI Concierge needs an Anthropic API key in <code>config/ai-concierge.php</code> to respond live.</div>
    <?php endif; ?>
    <form id="conciergeForm" class="concierge-input-row">
      <input type="text" id="conciergeInput" placeholder="Type your question…" autocomplete="off">
      <button type="submit" aria-label="Send">➤</button>
    </form>
  </div>
</div>

<style>
#conciergeWidget { position: fixed; bottom: 24px; right: 24px; z-index: 999; font-family: var(--font-body); }
#conciergeToggle {
  display: flex; align-items: center; gap: 8px;
  background: var(--emerald-deep); color: var(--linen);
  border: none; border-radius: 999px; padding: 14px 20px;
  font-size: 0.86rem; font-weight: 600; cursor: pointer;
  box-shadow: var(--shadow-lift);
}
#conciergeToggle svg { width: 18px; height: 18px; color: var(--brass-light); }
#conciergeToggle:hover { background: var(--emerald); }

.concierge-panel {
  position: absolute; bottom: 64px; right: 0;
  width: 340px; max-height: 480px;
  background: #fff; border-radius: var(--radius-md);
  box-shadow: var(--shadow-lift); overflow: hidden;
  display: flex; flex-direction: column;
  border: 1px solid var(--line);
}
.concierge-header {
  background: var(--emerald-deep); color: var(--linen);
  padding: 16px 18px; display: flex; justify-content: space-between; align-items: center;
}
.concierge-header button {
  background: none; border: none; color: rgba(246,243,236,0.7);
  font-size: 1.3rem; cursor: pointer; line-height: 1;
}
.concierge-messages { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; max-height: 320px; }
.concierge-msg { padding: 10px 13px; border-radius: 10px; font-size: 0.86rem; line-height: 1.5; max-width: 85%; }
.concierge-msg-bot { background: var(--marble); align-self: flex-start; }
.concierge-msg-user { background: var(--emerald); color: var(--linen); align-self: flex-end; }
.concierge-msg-error { background: #f7e6e3; color: var(--status-cancelled); align-self: flex-start; font-size: 0.8rem; }
.concierge-input-row { display: flex; border-top: 1px solid var(--line); padding: 10px; gap: 8px; }
.concierge-input-row input { flex: 1; border: 1px solid var(--line); border-radius: 999px; padding: 9px 14px; font-size: 0.86rem; }
.concierge-input-row button { background: var(--brass); border: none; border-radius: 50%; width: 36px; height: 36px; cursor: pointer; color: var(--emerald-deep); }
.concierge-typing { font-size: 0.8rem; color: var(--clay); padding: 0 14px 8px; }

@media (max-width: 500px) {
  .concierge-panel { width: calc(100vw - 32px); right: -8px; }
}
</style>

<script>
(function() {
  const toggle = document.getElementById('conciergeToggle');
  const panel = document.getElementById('conciergePanel');
  const closeBtn = document.getElementById('conciergeClose');
  const form = document.getElementById('conciergeForm');
  const input = document.getElementById('conciergeInput');
  const messages = document.getElementById('conciergeMessages');

  toggle.addEventListener('click', () => { panel.style.display = panel.style.display === 'none' ? 'flex' : 'none'; });
  closeBtn.addEventListener('click', () => { panel.style.display = 'none'; });

  function addMessage(text, cls) {
    const div = document.createElement('div');
    div.className = 'concierge-msg ' + cls;
    div.textContent = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    addMessage(text, 'concierge-msg-user');
    input.value = '';

    const typing = document.createElement('div');
    typing.className = 'concierge-typing';
    typing.textContent = 'Concierge is typing…';
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;

    try {
      const res = await fetch(BASE_URL + '/api/ai-concierge-chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
      });
      const data = await res.json();
      typing.remove();

      if (data.success) {
        addMessage(data.reply, 'concierge-msg-bot');
      } else {
        addMessage(data.message || 'Sorry, I could not respond just now.', 'concierge-msg-error');
      }
    } catch (err) {
      typing.remove();
      addMessage('Connection error. Please try again.', 'concierge-msg-error');
    }
  });
})();
</script>
