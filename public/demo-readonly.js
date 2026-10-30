(() => {
    const body = document.body;
    if (!body.hasAttribute('data-demo-readonly')) return;
    const lockForms = () => {
        document.querySelectorAll('form').forEach(form => {
            const method = (form.getAttribute('method') || 'get').toLowerCase();
            if (['get', 'dialog'].includes(method) || new URL(form.action, location.href).pathname === '/kilepes') return;
            if (!form.dataset.demoLocked) {
                form.dataset.demoLocked = 'true';
                form.addEventListener('submit', event => event.preventDefault());
            }
            form.querySelectorAll('input:not([type="hidden"]), select, textarea, button').forEach(control => {
                if (!control.disabled) control.disabled = true;
                control.setAttribute('aria-disabled', 'true');
                control.title = 'A nyilvános bemutatóban ez a művelet csak megtekinthető.';
            });
        });
    };
    lockForms();
    new MutationObserver(lockForms).observe(body, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled']});
})();
