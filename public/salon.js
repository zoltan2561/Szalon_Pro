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

const publicBookingFilters = document.querySelector('.public #booking-service');
if (publicBookingFilters) {
    publicBookingFilters.addEventListener('submit', () => {
        const action = new URL(publicBookingFilters.action);
        action.hash = window.matchMedia('(max-width: 640px)').matches ? 'booking-slots' : '';
        publicBookingFilters.action = action.toString();
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
const manualGuestResults = document.querySelector('[data-guest-results]');
const manualGuestFallback = document.querySelector('[data-guest-fallback]');
const manualSelectedGuest = document.querySelector('[data-selected-guest]');
const manualNewGuestFields = document.querySelector('[data-new-guest-fields]');
const manualGuestStatus = document.querySelector('[data-guest-search-status]');
if (manualGuestSearch && manualGuestSelect && manualGuestResults && manualGuestFallback && manualSelectedGuest && manualNewGuestFields && manualGuestStatus) {
    const normalizeGuest = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('hu').trim();
    const updateGuestSelection = () => {
        const selected = Boolean(manualGuestSelect.value);
        manualSelectedGuest.hidden = !selected;
        manualSelectedGuest.querySelector('[data-selected-guest-name]').textContent = selected ? manualGuestSelect.selectedOptions[0].textContent : '';
        manualNewGuestFields.hidden = selected;
        manualNewGuestFields.querySelectorAll('input').forEach(input => { input.disabled = selected; });
    };
    manualGuestFallback.hidden = true;
    updateGuestSelection();
    manualSelectedGuest.querySelector('[data-clear-guest]').addEventListener('click', () => {
        manualGuestSelect.value = '';
        manualGuestSearch.value = '';
        manualGuestResults.replaceChildren();
        manualGuestResults.hidden = true;
        manualGuestStatus.textContent = '';
        updateGuestSelection();
        manualGuestSearch.focus();
    });
    manualGuestSearch.addEventListener('input', () => {
        if (manualGuestSelect.value) {
            manualGuestSelect.value = '';
            updateGuestSelection();
        }
        const query = normalizeGuest(manualGuestSearch.value);
        const matches = query ? Array.from(manualGuestSelect.options).filter(option => option.value && normalizeGuest(option.textContent).includes(query)) : [];
        manualGuestResults.replaceChildren();
        matches.slice(0, 8).forEach(option => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'manual-guest-result';
            button.textContent = option.textContent;
            button.addEventListener('click', () => {
                manualGuestSelect.value = option.value;
                manualGuestSearch.value = '';
                manualGuestResults.replaceChildren();
                manualGuestResults.hidden = true;
                manualGuestStatus.textContent = 'Vendég kiválasztva.';
                updateGuestSelection();
                manualSelectedGuest.querySelector('[data-clear-guest]').focus();
            });
            manualGuestResults.append(button);
        });
        manualGuestResults.hidden = matches.length === 0;
        manualGuestStatus.textContent = query ? (matches.length ? `${matches.length} találat${matches.length > 8 ? ', pontosítsd a keresést' : ''}.` : 'Nincs találat. Új vendéget az alábbi mezőben adhatsz meg.') : '';
    });
}

const manualFilters = document.querySelector('[data-manual-filters]');
const manualStaff = manualFilters?.querySelector('select[name="staff_id"]');
const manualBooking = document.querySelector('.admin .booking-submit');
if (manualFilters && manualBooking) {
    let latestSearch = 0;
    const manualService = manualFilters.querySelector('select[name="service_id"]');
    const manualDate = manualFilters.querySelector('input[name="date"]');
    const manualSelection = manualBooking.querySelector('[data-manual-selection]');
    const manualGuestHeading = manualBooking.querySelector('#manual-booking-guest');
    const showManualSelection = input => {
        const slot = input.closest('.slot');
        const time = slot?.querySelector('strong')?.textContent?.trim();
        const staff = slot?.querySelector('small')?.textContent?.trim();
        if (manualSelection && time && staff) {
            manualSelection.textContent = `Kiválasztva: ${manualDate.value} · ${time} · ${staff}`;
            manualSelection.hidden = false;
        }
    };
    const selectedManualSlot = manualBooking.querySelector('input[name="slot"]:checked');
    if (selectedManualSlot) showManualSelection(selectedManualSlot);
    let pointerSlotSelection = false;
    manualBooking.addEventListener('pointerdown', event => {
        pointerSlotSelection = Boolean(event.target.closest('.slot'));
    });
    manualBooking.addEventListener('change', event => {
        if (!(event.target instanceof HTMLInputElement) || event.target.name !== 'slot') return;
        showManualSelection(event.target);
        if (pointerSlotSelection && manualGuestHeading && window.matchMedia('(max-width: 640px)').matches) {
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            manualGuestHeading.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        }
        pointerSlotSelection = false;
    });
    const refreshSlots = async (nearest = false) => {
        const search = ++latestSearch;
        const status = manualFilters.querySelector('[data-nearest-status]');
        const params = new URLSearchParams(new FormData(manualFilters));
        if (nearest) params.set('nearest', '1');
        status.textContent = 'Szabad időpontok keresése…';
        if (manualSelection) manualSelection.hidden = true;
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

document.querySelectorAll('[data-quick-sheet]').forEach(sheet => {
    const form = sheet.querySelector('form[data-quick-preview-url]');
    const service = form?.querySelector('select[name="service_id"]');
    const time = form?.querySelector('select[name="quick_time"]');
    const date = form?.querySelector('input[name="quick_date"]');
    const timeMode = form?.querySelector('[data-quick-time-mode]');
    const slotsLabel = form?.querySelector('[data-quick-slots-label]');
    const slots = form?.querySelector('[data-quick-slots]');
    const token = form?.querySelector('input[name="preview_token"]');
    const preview = form?.querySelector('[data-quick-preview]');
    const submit = form?.querySelector('button[type="submit"]');
    let latestPreview = 0;
    const refreshPreview = async () => {
        if (!form || !service || !time || !date || !timeMode || !slotsLabel || !slots || !token || !preview || !submit) return;
        const sequence = ++latestPreview;
        token.value = '';
        submit.disabled = true;
        timeMode.hidden = !!date.value;
        slotsLabel.hidden = true;
        slots.replaceChildren();
        if (!service.value) { preview.textContent = 'Először válassz szolgáltatást.'; return; }
        if (!date.checkValidity()) { preview.textContent = 'Válassz a megengedett időszakon belüli napot.'; return; }
        preview.textContent = 'Szabad időpont keresése…';
        try {
            const params = new URLSearchParams({quick_preview: '1', service_id: service.value, quick_time: time.value});
            if (date.value) params.set('quick_date', date.value);
            const response = await fetch(`${form.dataset.quickPreviewUrl}?${params}`, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            const result = await response.json();
            if (sequence !== latestPreview) return;
            if (date.value && response.ok && Array.isArray(result.slots)) {
                if (!result.slots.length) { preview.textContent = 'Erre a napra nincs szabad időpont. Válassz másik napot.'; return; }
                result.slots.forEach(slot => slots.add(new Option(slot.label, slot.token)));
                slotsLabel.hidden = false;
                token.value = slots.value;
                preview.textContent = `${result.slots.length} szabad időpont közül választhatsz.`;
                submit.disabled = false;
                return;
            }
            if (!response.ok || !result.token || !result.label) throw new Error(result.message || 'Nem sikerült megkeresni az időpontot.');
            token.value = result.token;
            preview.textContent = `Foglalható: ${result.label}`;
            submit.disabled = false;
        } catch (error) {
            if (sequence === latestPreview) preview.textContent = error.message || 'Nem sikerült megkeresni az időpontot.';
        }
    };
    service?.addEventListener('change', refreshPreview);
    time?.addEventListener('change', refreshPreview);
    date?.addEventListener('input', refreshPreview);
    slots?.addEventListener('change', () => { token.value = slots.value; });
    form?.addEventListener('submit', () => { if (form.checkValidity()) submit.disabled = true; });
    sheet.addEventListener('toggle', () => {
        if (sheet.open) refreshPreview();
        if (sheet.open && window.matchMedia('(max-width: 640px)').matches) {
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            sheet.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        }
    });
    if (sheet.open) refreshPreview();
});

const guestSearch = document.querySelector('[data-live-guests]');
const guestList = document.querySelector('[data-guest-list]');
if (guestSearch && guestList) {
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('hu').trim();
    guestSearch.addEventListener('input', () => {
        const query = normalize(guestSearch.value);
        guestList.querySelectorAll('.guest-card').forEach(card => { card.hidden = !normalize(card.textContent).includes(query); });
    });
}
