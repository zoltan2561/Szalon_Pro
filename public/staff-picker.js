(() => {
    if (!window.HTMLDialogElement || !HTMLDialogElement.prototype.showModal) return;
    const mobile = window.matchMedia('(max-width: 767px), (pointer: coarse)');
    document.querySelectorAll('[data-staff-picker]').forEach((select, index) => {
        const booking = document.querySelector('.booking-submit');
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'staff-picker-trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        const dialog = document.createElement('dialog');
        dialog.id = `staff-picker-${index}`;
        dialog.className = 'staff-picker-dialog';
        dialog.setAttribute('aria-labelledby', `${dialog.id}-title`);
        trigger.setAttribute('aria-controls', dialog.id);
        const header = document.createElement('div');
        header.className = 'staff-picker-heading';
        const title = document.createElement('h2');
        title.id = `${dialog.id}-title`;
        title.textContent = 'Kit választasz?';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'staff-picker-close';
        close.textContent = 'Bezárás';
        close.addEventListener('click', () => dialog.close());
        header.append(title, close);
        const hint = document.createElement('p');
        hint.className = 'staff-picker-hint';
        hint.textContent = 'A kiválasztott szolgáltatásokat végző szakemberek.';
        const choices = document.createElement('div');
        choices.className = 'staff-picker-options';
        choices.setAttribute('role', 'group');
        choices.setAttribute('aria-label', 'Szakemberek');
        dialog.append(header, hint, choices);
        select.after(trigger);
        document.body.append(dialog);
        const update = () => {
            const enabled = mobile.matches;
            const busy = booking?.getAttribute('aria-busy') === 'true';
            select.hidden = enabled;
            select.toggleAttribute('aria-hidden', enabled);
            if (enabled) select.setAttribute('aria-hidden', 'true');
            trigger.hidden = !enabled;
            trigger.disabled = select.disabled || busy;
            trigger.textContent = select.selectedOptions[0]?.label || 'Válassz szakembert';
            trigger.setAttribute('aria-label', `Szakember: ${trigger.textContent}`);
            if (dialog.open && (!enabled || busy || select.disabled)) dialog.close();
        };
        trigger.addEventListener('click', () => {
            choices.replaceChildren();
            Array.from(select.options).filter(option => !option.hidden && !option.disabled).forEach(option => {
                const choice = document.createElement('button');
                choice.type = 'button';
                choice.className = 'staff-picker-option';
                choice.textContent = option.label;
                choice.dataset.value = option.value;
                choice.setAttribute('aria-pressed', String(option.selected));
                choice.addEventListener('click', () => {
                    select.value = option.value;
                    dialog.close('selected');
                    trigger.focus({preventScroll: true});
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                });
                choices.append(choice);
            });
            dialog.returnValue = '';
            dialog.showModal();
            trigger.setAttribute('aria-expanded', 'true');
            (choices.querySelector('[aria-pressed="true"]') || choices.querySelector('button') || close).focus({preventScroll: true});
        });
        dialog.addEventListener('close', () => {
            trigger.setAttribute('aria-expanded', 'false');
            if (dialog.returnValue !== 'selected' && !trigger.hidden && !trigger.disabled) trigger.focus({preventScroll: true});
        });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
        });
        choices.addEventListener('keydown', event => {
            const buttons = Array.from(choices.querySelectorAll('button'));
            const current = buttons.indexOf(document.activeElement);
            let next;
            if (event.key === 'ArrowDown') next = (current + 1) % buttons.length;
            else if (event.key === 'ArrowUp') next = (current - 1 + buttons.length) % buttons.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = buttons.length - 1;
            else return;
            event.preventDefault();
            buttons[next]?.focus();
        });
        new MutationObserver(mutations => {
            if (mutations.every(mutation => mutation.type === 'attributes' && mutation.target === select && mutation.attributeName === 'hidden')) return;
            if (dialog.open) dialog.close();
            update();
        }).observe(select, {childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'selected']});
        select.addEventListener('change', update);
        booking?.addEventListener('booking:updated', update);
        mobile.addEventListener('change', update);
        update();
    });
})();
