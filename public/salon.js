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

document.querySelectorAll('[data-confirm-cancel]').forEach(button => {
    button.addEventListener('click', event => {
        if (!window.confirm('Biztosan lemondod ezt a foglalást?')) event.preventDefault();
    });
});

if (window.matchMedia('(min-width: 768px)').matches) {
    document.querySelectorAll('.weekly-hours').forEach(section => { section.open = true; });
}

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

const manualGuestSearch = document.querySelector('[data-guest-search]');
const manualGuestSelect = document.querySelector('#manual-guest');
if (manualGuestSearch && manualGuestSelect) {
    const normalizeGuest = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('hu').trim();
    manualGuestSearch.addEventListener('input', () => {
        const query = normalizeGuest(manualGuestSearch.value);
        Array.from(manualGuestSelect.options).forEach(option => {
            option.hidden = Boolean(option.value) && !option.selected && !normalizeGuest(option.textContent).includes(query);
        });
    });
}

const manualFilters = document.querySelector('[data-manual-filters]');
const manualStaff = manualFilters?.querySelector('select[name="staff_id"]');
const manualBooking = document.querySelector('.admin .booking-submit');
if (manualFilters && manualBooking) {
    let latestSearch = 0;
    const manualService = manualFilters.querySelector('select[name="service_id"]');
    const manualDate = manualFilters.querySelector('input[name="date"]');
    const refreshSlots = async (nearest = false) => {
        const search = ++latestSearch;
        const status = manualFilters.querySelector('[data-nearest-status]');
        const params = new URLSearchParams(new FormData(manualFilters));
        if (nearest) params.set('nearest', '1');
        status.textContent = 'Szabad időpontok keresése…';
        manualBooking.querySelector('.slots-field').replaceChildren();
        manualBooking.querySelector('button[type="submit"], button:not([type])').disabled = true;
        try {
            const response = await fetch(`${manualFilters.action || location.pathname}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Availability request failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (search !== latestSearch) return;
            const newSlots = page.querySelector('.admin .booking-submit .slots-field');
            const newDate = page.querySelector('[data-manual-filters] input[name="date"]');
            const newStaff = page.querySelector('[data-manual-filters] select[name="staff_id"]');
            const newService = page.querySelector('.admin .booking-submit input[name="service_id"]');
            const newSummary = page.querySelector('.booking-grid > aside');
            const currentSlots = manualBooking.querySelector('.slots-field');
            if (!newSlots || !newDate || !newService || !newSummary || !currentSlots) throw new Error('Availability response incomplete');
            currentSlots.replaceWith(newSlots);
            manualDate.value = newDate.value;
            if (manualStaff && newStaff) { manualStaff.innerHTML = newStaff.innerHTML; manualStaff.value = newStaff.value; }
            manualBooking.querySelector('input[name="service_id"]').value = newService.value;
            document.querySelector('.booking-grid > aside').replaceWith(newSummary);
            manualBooking.querySelector('button[type="submit"], button:not([type])').disabled = !newSlots.querySelector('input[name="slot"]');
            status.textContent = newSlots.querySelector('input[name="slot"]') ? `Szabad időpontok: ${newDate.value}.` : 'Erre a napra nincs szabad időpont.';
        } catch (error) {
            if (search === latestSearch) status.textContent = 'Nem sikerült betölteni az időpontokat. Használd a Szabad időpontok mutatása gombot.';
        }
    };
    manualService.addEventListener('change', () => refreshSlots());
    manualDate.addEventListener('change', () => refreshSlots());
    manualStaff?.addEventListener('change', () => refreshSlots(true));
    manualFilters.addEventListener('submit', event => {
        if (event.submitter?.name === 'nearest') { event.preventDefault(); refreshSlots(true); }
    });
}

const guestSearch = document.querySelector('[data-live-guests]');
const guestList = document.querySelector('[data-guest-list]');
if (guestSearch && guestList) {
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('hu').trim();
    guestSearch.addEventListener('input', () => {
        const query = normalize(guestSearch.value);
        guestList.querySelectorAll('.guest-card').forEach(card => { card.hidden = !normalize(card.textContent).includes(query); });
    });
}
