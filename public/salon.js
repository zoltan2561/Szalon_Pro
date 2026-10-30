const moreButtons = document.querySelectorAll('[data-open-more]');
const moreSheet = document.querySelector('#admin-more-menu');
if (moreButtons.length && moreSheet instanceof HTMLDialogElement) {
    let activeMoreButton;
    moreButtons.forEach(button => button.addEventListener('click', () => {
        activeMoreButton = button;
        if (!moreSheet.open) moreSheet.showModal();
        moreButtons.forEach(control => control.setAttribute('aria-expanded', 'true'));
    }));
    moreSheet.addEventListener('close', () => {
        moreButtons.forEach(control => control.setAttribute('aria-expanded', 'false'));
        if (activeMoreButton?.getClientRects().length) activeMoreButton.focus({preventScroll:true});
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

document.querySelectorAll('[data-calendar-create]').forEach(track => {
    track.addEventListener('click', event => {
        if (event.target !== track || event.button !== 0) return;
        const start = Date.parse(track.dataset.calendarStart);
        if (!Number.isFinite(start)) return;
        const minutes = Math.floor((event.clientY - track.getBoundingClientRect().top) / 1.5 / 15) * 15;
        if (minutes < 0) return;
        const selected = new Date(start + minutes * 60000).toISOString().replace('.000Z', '+00:00');
        const target = new URL(track.dataset.calendarCreate);
        target.searchParams.set('calendar_start', selected);
        window.location.assign(target.toString());
    });
});

const publicBookingFilters = document.querySelector('.public #booking-service');
if (publicBookingFilters) {
    publicBookingFilters.addEventListener('submit', () => {
        const action = new URL(publicBookingFilters.action);
        action.hash = 'booking-slots';
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

    slots.addEventListener('change', event => {
        if (!(event.target instanceof HTMLInputElement) || event.target.name !== 'slot') {
            return;
        }
        showSelectedSlot(event.target);
    });
});

document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));

document.querySelectorAll('[data-confirm-cancel]').forEach(button => {
    button.addEventListener('click', event => {
        if (!window.confirm(button.dataset.confirmMessage || 'Biztosan lemondod ezt a foglalást?')) event.preventDefault();
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
        const name = manualNewGuestFields.querySelector('input[name="name"]');
        if (name) name.required = !selected;
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
                manualGuestSelect.closest('form').dispatchEvent(new Event('booking:guest-selected'));
            });
            manualGuestResults.append(button);
        });
        manualGuestResults.hidden = matches.length === 0;
        manualGuestStatus.textContent = query ? (matches.length ? `${matches.length} találat${matches.length > 8 ? ', pontosítsd a keresést' : ''}.` : 'Nincs találat. Új vendéget az alábbi mezőben adhatsz meg.') : '';
    });
}

document.querySelectorAll('[data-open-work-notes]').forEach(link => {
    link.addEventListener('click', () => {
        const notes = document.getElementById('work-notes');
        if (notes instanceof HTMLDetailsElement) notes.open = true;
    });
});

const manualFilters = document.querySelector('[data-manual-filters]');
const manualStaff = manualFilters?.querySelector('select[name="staff_id"]');
const manualBooking = document.querySelector('.admin .booking-submit');
if (manualFilters && manualBooking) {
    let latestSearch = 0;
    let availabilityRequest;
    const manualService = manualFilters.querySelector('select[name="service_id"]');
    const manualServiceLabel = manualFilters.querySelector('[data-manual-service-label]');
    const manualDate = manualFilters.querySelector('input[name="date"]');
    const manualSelection = manualBooking.querySelector('[data-manual-selection]');
    const showManualSelection = input => {
        const slot = input.closest('.slot');
        const time = slot?.querySelector('strong')?.textContent?.trim();
        const staff = slot?.querySelector('small')?.textContent?.trim();
        if (manualSelection && time && staff) {
            const dateLabel = manualBooking.querySelector('[data-slot-date] time')?.textContent.trim() || manualDate.value;
            manualSelection.textContent = `Kiválasztva: ${dateLabel} · ${time} · ${staff}`;
            manualSelection.hidden = false;
        }
    };
    const selectedManualSlot = manualBooking.querySelector('input[name="slot"]:checked');
    if (selectedManualSlot) showManualSelection(selectedManualSlot);
    manualBooking.addEventListener('change', event => {
        if (!(event.target instanceof HTMLInputElement) || event.target.name !== 'slot') return;
        showManualSelection(event.target);
    });
    const refreshSlots = async (nearest = false, step = null) => {
        const origin = document.activeElement;
        let loaded = false;
        const search = ++latestSearch;
        availabilityRequest?.abort();
        availabilityRequest = new AbortController();
        const status = manualFilters.querySelector('[data-nearest-status]');
        const params = new URLSearchParams(new FormData(manualFilters));
        if (nearest) params.set('nearest', '1');
        status.textContent = 'Szabad időpontok keresése…';
        manualBooking.setAttribute('aria-busy', 'true');
        manualBooking.querySelector('[data-calendar-slot-unavailable]')?.remove();
        if (manualSelection) manualSelection.hidden = true;
        manualBooking.querySelector('.slots-field').replaceChildren();
        manualBooking.querySelector('button[type="submit"], button:not([type])').disabled = true;
        try {
            const target = new URL(manualFilters.action || location.href);
            target.hash = '';
            target.search = params.toString();
            const response = await fetch(target, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: availabilityRequest.signal, cache: 'no-store' });
            if (!response.ok) throw new Error('Availability request failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (search !== latestSearch) return;
            const newSlots = page.querySelector('.admin .booking-submit .slots-field');
            const newDate = page.querySelector('[data-manual-filters] input[name="date"]');
            const newStaff = page.querySelector('[data-manual-filters] select[name="staff_id"]');
            const newService = page.querySelector('.admin .booking-submit input[name="service_id"]');
            const newSummary = page.querySelector('.booking-grid > aside');
            const newReview = page.querySelector('[data-booking-review]');
            const freshServices = page.querySelector('[data-selected-service-inputs]');
            const currentSlots = manualBooking.querySelector('.slots-field');
            if (!newSlots || !newDate || !newService || !newSummary || !newReview || !currentSlots) throw new Error('Availability response incomplete');
            currentSlots.replaceWith(newSlots);
            manualDate.value = newDate.value;
            manualDate.dispatchEvent(new Event('localized:sync'));
            if (manualStaff && newStaff) { manualStaff.innerHTML = newStaff.innerHTML; manualStaff.value = newStaff.value; }
            manualBooking.querySelector('input[name="service_id"]').value = newService.value;
            if (freshServices) manualBooking.querySelector('[data-selected-service-inputs]')?.replaceWith(freshServices);
            const extras = manualFilters.querySelector('[data-service-extras]');
            const freshExtras = page.querySelector('[data-service-extras]');
            if (extras && freshExtras) { freshExtras.open = extras.open; extras.replaceWith(freshExtras); }
            document.querySelector('.booking-grid > aside').replaceWith(newSummary);
            Object.assign(manualBooking.querySelector('[data-booking-review]').dataset, newReview.dataset);
            const freshLabel = page.querySelector('[data-manual-service-label]');
            if (manualServiceLabel && freshLabel) manualServiceLabel.textContent = freshLabel.textContent;
            status.textContent = newSlots.querySelector('input[name="slot"]') ? `Szabad időpontok: ${newDate.value.replaceAll('-', '. ')}.` : 'Erre a napra nincs szabad időpont.';
            loaded = true;
        } catch (error) {
            if (search === latestSearch && error.name !== 'AbortError') status.textContent = 'Nem sikerült betölteni az időpontokat. Használd a Szabad időpontok mutatása gombot.';
        } finally {
            if (search === latestSearch) {
                manualBooking.removeAttribute('aria-busy');
                manualBooking.dispatchEvent(new CustomEvent('booking:updated', {detail: loaded ? {step, origin} : null}));
            }
        }
    };
    manualService.addEventListener('change', () => {
        if (manualServiceLabel) manualServiceLabel.textContent = manualService.selectedOptions[0]?.textContent || '';
        refreshSlots(false, 'service_id');
    });
    manualDate.addEventListener('change', () => refreshSlots(false, 'date'));
    manualStaff?.addEventListener('change', () => refreshSlots(false, 'staff_id'));
    manualFilters.addEventListener('change', event => {
        if (event.target.name === 'extra_service_ids[]') refreshSlots();
    });
    manualFilters.addEventListener('submit', event => {
        event.preventDefault();
        refreshSlots(event.submitter?.name === 'nearest', 'date');
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
const guestSearchForm = document.querySelector('[data-live-guests-form]');
if (guestSearch && guestList && guestSearchForm) {
    const status = guestSearchForm.querySelector('[data-guest-list-status]');
    const sort = guestSearchForm.querySelector('[data-guest-sort]');
    let sequence = 0;
    let timer;
    const refreshGuests = async current => {
        const target = new URL(guestSearchForm.action);
        target.search = new URLSearchParams(new FormData(guestSearchForm)).toString();
        status.textContent = 'Keresés…';
        try {
            const response = await fetch(target, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Guest search failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const results = page.querySelector('[data-guest-list]');
            if (!results) throw new Error('Guest search response incomplete');
            if (current !== sequence) return;
            guestList.innerHTML = results.innerHTML;
            history.replaceState(null, '', target);
            status.textContent = 'A vendéglista frissült.';
        } catch (error) {
            if (current === sequence) status.textContent = 'Nem sikerült frissíteni a listát. Nyomd meg a Keresés gombot.';
        }
    };
    const queueGuestSearch = delay => {
        const current = ++sequence;
        clearTimeout(timer);
        timer = setTimeout(() => refreshGuests(current), delay);
    };
    guestSearch.addEventListener('input', () => queueGuestSearch(300));
    sort?.addEventListener('change', () => queueGuestSearch(0));
}

document.querySelectorAll('form[data-work-exception]').forEach(form => {
    const closed = form.querySelector('input[name="closed"]');
    const timeFields = form.querySelectorAll('input[name="start"], input[name="end"]');
    if (!closed) return;
    const update = () => { timeFields.forEach(input => { input.disabled = closed.checked; }); };
    closed.addEventListener('change', update);
    update();
});
