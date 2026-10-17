document.querySelectorAll('.booking-submit').forEach(form => {
    form.addEventListener('submit', () => {
        if (form.checkValidity()) {
            const button = form.querySelector('button[type=submit], button:not([type])');
            if (button) {
                button.disabled = true;
                button.textContent = 'Foglalás mentése…';
            }
        }
    });

    const slots = form.querySelector('.slots');
    const guestHeading = form.querySelector('#booking-guest');
    const selectedSlotNote = form.querySelector('.selected-slot-note');
    if (!slots || !guestHeading) {
        return;
    }

    const showSelectedSlot = input => {
        if (!selectedSlotNote) {
            return;
        }
        const slot = input.closest('.slot');
        const time = slot?.querySelector('strong')?.textContent?.trim();
        const staff = slot?.querySelector('small')?.textContent?.trim();
        if (time && staff) {
            selectedSlotNote.textContent = `Kiválasztva: ${time} · ${staff}`;
            selectedSlotNote.hidden = false;
        }
    };
    const selectedInput = slots.querySelector('input[name="slot"]:checked');
    if (selectedInput) {
        showSelectedSlot(selectedInput);
    }

    let pointerSelection = false;
    slots.addEventListener('pointerdown', () => { pointerSelection = true; });
    slots.addEventListener('change', event => {
        if (!(event.target instanceof HTMLInputElement) || event.target.name !== 'slot') {
            return;
        }
        showSelectedSlot(event.target);
        const shouldAdvance = pointerSelection && window.matchMedia('(max-width: 640px)').matches;
        pointerSelection = false;
        if (shouldAdvance) {
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            guestHeading.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        }
    });
});
