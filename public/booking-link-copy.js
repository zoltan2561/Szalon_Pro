document.querySelectorAll('[data-booking-link-save]').forEach(panel => {
    const button = panel.querySelector('[data-copy-booking-link]');
    const link = panel.querySelector('[data-booking-management-link]');
    const status = panel.querySelector('[role="status"]');
    const fallback = panel.querySelector('[data-booking-link-fallback]');
    const value = panel.querySelector('[data-booking-link-value]');
    if (!button || !link || !status || !fallback || !value) return;

    button.hidden = false;
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            if (!navigator.clipboard?.writeText) throw new Error('clipboard-unavailable');
            await navigator.clipboard.writeText(link.href);
            fallback.hidden = true;
            status.textContent = 'A kezelési linket a vágólapra másoltuk. Mentsd el egy biztonságos helyre.';
        } catch {
            fallback.hidden = false;
            value.focus();
            value.select();
            status.textContent = 'A másolást a böngésző nem engedte. Másold ki a kijelölt linket, vagy nyisd meg a Foglalásom kezelése oldalt és mentsd el.';
        } finally {
            button.disabled = false;
        }
    });
});
