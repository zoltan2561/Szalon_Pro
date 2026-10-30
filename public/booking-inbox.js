(() => {
    const inboxes = [...document.querySelectorAll('[data-booking-inbox]')];
    if (!inboxes.length || document.body.hasAttribute('data-demo-readonly')) return;
    let pending;
    let sequence = 0;
    const refresh = async () => {
        if (document.hidden || inboxes.some(inbox => inbox.open)) return;
        const current = ++sequence;
        pending?.abort();
        pending = new AbortController();
        try {
            const response = await fetch(inboxes[0].dataset.inboxUrl, {headers: {'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'}, cache:'no-store', signal:pending.signal});
            if (!response.ok) return;
            const result = await response.json();
            if (current !== sequence || inboxes.some(inbox => inbox.open) || !Number.isInteger(result.count) || typeof result.html !== 'string') return;
            inboxes.forEach(inbox => {
                const badge = inbox.querySelector('[data-inbox-count]');
                badge.textContent = result.count > 99 ? '99+' : String(result.count);
                badge.hidden = !result.count;
                inbox.querySelector('summary').setAttribute('aria-label', `Új foglalások: ${result.count} olvasatlan`);
                inbox.querySelector('[data-inbox-panel]').innerHTML = result.html;
            });
        } catch (_) {
            // Keep the last successfully rendered inbox during a background refresh failure.
        }
    };
    inboxes.forEach(inbox => {
        inbox.addEventListener('toggle', () => {
            if (inbox.open) inboxes.filter(other => other !== inbox).forEach(other => { other.open = false; });
        });
        inbox.addEventListener('keydown', event => {
            if (event.key === 'Escape' && inbox.open) {
                inbox.open = false;
                inbox.querySelector('summary').focus();
            }
        });
    });
    document.addEventListener('click', event => inboxes.forEach(inbox => {
        if (inbox.open && !inbox.contains(event.target)) inbox.open = false;
    }));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.setInterval(refresh, 60000);
})();
