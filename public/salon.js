const moreButton = document.querySelector('[data-open-more]');
const moreSheet = document.querySelector('#admin-more-menu');
if (moreButton && moreSheet instanceof HTMLDialogElement) {
    moreButton.addEventListener('click', () => {
        moreSheet.showModal();
        moreButton.setAttribute('aria-expanded', 'true');
    });
    moreSheet.addEventListener('close', () => {
        moreButton.setAttribute('aria-expanded', 'false');
        moreButton.focus();
    });
    moreSheet.addEventListener('click', event => {
        if (event.target === moreSheet) {
            const box = moreSheet.getBoundingClientRect();
            if (event.clientY < box.top || event.clientX < box.left || event.clientX > box.right) {
                moreSheet.close();
            }
        }
    });
}

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

document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));

const settingsForm = document.querySelector('.admin-settings');
if (settingsForm) {
    const rights = settingsForm.querySelector('input[name="content_rights"]');
    const uploads = Array.from(settingsForm.querySelectorAll('input[type="file"]'));
    const updateRightsRequirement = () => {
        if (rights) {
            rights.required = settingsForm.dataset.contentRightsAccepted !== '1' || uploads.some(input => input.files.length > 0);
        }
    };
    uploads.forEach(input => input.addEventListener('change', updateRightsRequirement));
    updateRightsRequirement();
}
