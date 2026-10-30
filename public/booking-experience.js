document.querySelectorAll('.booking-submit').forEach(form => {
    const review = form.querySelector('[data-booking-review]');
    if (!review) return;
    const submit = form.querySelector('button[type="submit"], button:not([type])');
    const guestFields = form.querySelector('[data-booking-guest-fields]');
    const filter = document.querySelector(guestFields ? '#booking-service' : '[data-manual-filters]');
    let browsingSlots = false;
    const advance = (target, origin = document.activeElement, section = target?.closest('label') || target) => {
        if (!target || !section) return;
        requestAnimationFrame(() => {
            if (document.querySelector('dialog[open]') || !target.isConnected || target.disabled) return;
            if (document.activeElement !== origin && document.activeElement !== document.body) return;
            if (target.classList.contains('localized-field-native')) target = target.closest('.localized-field').querySelector('.localized-field-text');
            if (target.matches('[data-staff-picker]') && target.hidden) target = target.parentElement.querySelector('.staff-picker-trigger');
            if (!target.getClientRects().length) return;
            target.focus({preventScroll: true});
            section.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
        });
    };
    const missingGuestField = () => Array.from((guestFields || form.querySelector('[data-new-guest-fields]')).querySelectorAll('input'))
        .find(input => !input.disabled && input.getClientRects().length && !input.validity.valid);
    const advanceToSlots = origin => {
        const slot = form.querySelector('input[name="slot"]');
        if (slot) {
            const period = slot.closest('details');
            if (period) period.open = true;
            advance(slot, origin, slot.closest('.slots-field'));
        } else {
            const empty = form.querySelector('.slot-recovery');
            if (empty) {
                empty.tabIndex = -1;
                advance(empty.querySelector('a') || empty, origin, empty);
            }
        }
    };
    const advanceToReview = origin => advance(submit, origin, review);
    const advanceGuest = origin => {
        const missing = missingGuestField();
        if (missing) advance(missing, origin, guestFields && missing.name === 'name' ? guestFields : missing.closest('label'));
        else if (!form.querySelector('input[name="slot"]:checked')) advanceToSlots(origin);
        else advanceToReview(origin);
    };
    const advanceAvailability = detail => {
        if (!filter || !detail?.step || detail.step === 'extra_service_ids[]') return;
        const staff = filter.querySelector('select[name="staff_id"]');
        const date = filter.querySelector('input[name="date"]');
        if (detail.step === 'service_id') advance(staff?.options.length > 1 ? staff : date, detail.origin);
        else if (detail.step === 'staff_id') advance(date, detail.origin);
        else if (guestFields) advanceToSlots(detail.origin);
        else if (form.querySelector('#manual-guest')?.value) advanceGuest(detail.origin);
        else advance(form.querySelector('[data-guest-search]'), detail.origin);
    };
    const update = () => {
        const selected = form.querySelector('input[name="slot"]:checked');
        const tile = selected?.closest('.slot');
        const time = tile?.querySelector('strong')?.textContent.trim();
        const staff = tile?.querySelector('small')?.textContent.trim();
        review.querySelector('[data-review-service]').textContent = review.dataset.service;
        review.querySelector('[data-review-date]').textContent = review.dataset.date;
        review.querySelector('[data-review-price]').textContent = review.dataset.price;
        review.querySelector('[data-review-duration]').textContent = review.dataset.duration;
        review.querySelector('[data-review-slot]').textContent = time && staff ? `${time} · ${staff}` : 'Válassz egy szabad időpontot.';
        if (submit) submit.disabled = !selected || form.getAttribute('aria-busy') === 'true' || form.dataset.repeatReady === 'false';
        if (guestFields) guestFields.hidden = !selected;
        const extrasDone = filter?.querySelector('[data-extras-done]');
        if (extrasDone) extrasDone.disabled = form.getAttribute('aria-busy') === 'true';
    };
    form.addEventListener('change', event => {
        const input = event.target;
        if (input.name === 'slot') {
            update();
            if (!browsingSlots) advanceGuest(input);
        } else if (input instanceof HTMLInputElement && ['name', 'email', 'phone', 'terms'].includes(input.name) && input.validity.valid) {
            advanceGuest(input);
        }
    });
    form.addEventListener('keydown', event => {
        const input = event.target;
        if (input instanceof HTMLInputElement && input.name === 'slot') {
            browsingSlots = event.key.startsWith('Arrow');
            if (event.key === 'Enter') {
                event.preventDefault();
                input.checked = true;
                update();
                advanceGuest(input);
            }
            return;
        }
        if (event.key !== 'Enter' || event.isComposing || !(input instanceof HTMLInputElement) || !['search', 'text', 'email', 'tel'].includes(input.type)) return;
        event.preventDefault();
        if (input.matches('[data-guest-search]')) {
            const result = form.querySelector('[data-guest-results] button');
            if (result) result.click();
            else advance(form.querySelector('[data-new-guest-fields] input[name="name"]'), input);
        } else if (input.reportValidity()) advanceGuest(input);
    });
    form.addEventListener('keyup', () => { browsingSlots = false; });
    form.addEventListener('pointerdown', () => { browsingSlots = false; });
    form.addEventListener('booking:guest-selected', () => advanceGuest(document.activeElement));
    form.addEventListener('booking:advance-guest', () => advanceGuest(document.activeElement));
    form.addEventListener('booking:repeat-updated', update);
    form.addEventListener('booking:updated', event => { update(); advanceAvailability(event.detail); });
    filter?.addEventListener('click', event => {
        const done = event.target.closest('[data-extras-done]');
        if (!done) return;
        done.closest('details').open = false;
        advanceAvailability({step: 'service_id', origin: done});
    });
    update();
    if (!guestFields && new URLSearchParams(location.search).has('nearest')) {
        advanceAvailability({step: 'date', origin: document.activeElement});
    } else if (guestFields && location.hash === '#booking-slots') advanceToSlots(document.activeElement);
});

document.addEventListener('click', event => {
    const guestStep = event.target.closest('a[href="#booking-guest"]');
    const guestFields = document.querySelector('[data-booking-guest-fields]');
    if (guestStep && guestFields?.hidden) {
        event.preventDefault();
        const status = document.querySelector('[data-availability-status]');
        if (status) {
            status.hidden = false;
            status.textContent = 'Az adataid megadása előtt válassz egy szabad időpontot.';
        }
        document.getElementById('booking-slots')?.scrollIntoView({block: 'start'});
    }
    const day = event.target.closest('[data-week-day]');
    if (!day) return;
    const calendar = day.closest('.calendar-week-mobile');
    if (!calendar) return;
    event.preventDefault();
    calendar.querySelectorAll('[data-week-day]').forEach(link => {
        if (link === day) link.setAttribute('aria-current', 'date');
        else link.removeAttribute('aria-current');
    });
    calendar.querySelectorAll('[data-week-panel]').forEach(panel => { panel.hidden = panel.dataset.weekPanel !== day.dataset.weekDay; });
    const date = document.querySelector('.calendar-date-form input[name="date"]');
    if (date) {
        date.value = day.dataset.weekDay;
        date.dispatchEvent(new Event('localized:sync'));
    }
    const target = new URL(location.href);
    target.searchParams.set('date', day.dataset.weekDay);
    target.searchParams.set('view', 'week');
    history.replaceState(null, '', target);
});

document.querySelectorAll('.hours-row').forEach(row => {
    const closed = row.querySelector('input[type="checkbox"]');
    if (!closed) return;
    const update = () => {
        row.classList.toggle('is-closed', closed.checked);
        row.querySelectorAll('.hours-time input, .hours-time button').forEach(input => { input.disabled = closed.checked; });
    };
    closed.addEventListener('change', update);
    update();
});

document.querySelectorAll('[data-waitlist-preferences]').forEach(form => {
    const service = form.querySelector('[name="service_id"]');
    const staff = form.querySelector('[name="staff_id"]');
    const email = form.querySelector('[name="email"]');
    const phone = form.querySelector('[name="phone"]');
    if (email?.hasAttribute('aria-describedby') && phone) {
        const updateContact = () => {
            email.required = !phone.value.trim();
        };
        phone.addEventListener('input', updateContact);
        updateContact();
    }
    const update = () => {
        Array.from(staff.options).forEach(option => {
            const available = !option.value || option.dataset.services.split(',').includes(service.value);
            option.hidden = !available;
            option.disabled = !available;
        });
        if (staff.selectedOptions[0]?.disabled) staff.value = '';
    };
    service.addEventListener('change', update);
    update();
});

document.querySelectorAll('.public [data-availability-submit]:not(.booking-submit), [data-admin-reschedule] form[method="post"]').forEach(form => {
    const submit = form.querySelector('button[type="submit"], button:not([type])');
    const scope = form.querySelector('select[name="repeat_scope"]');
    let browsingSlots = false;
    const advance = (target, origin) => {
        if (!target) return;
        requestAnimationFrame(() => {
            if (document.activeElement !== origin && document.activeElement !== document.body) return;
            if (target.disabled || !target.getClientRects().length || document.querySelector('dialog[open]')) return;
            target.focus({preventScroll:true});
            (target.closest('label') || target).scrollIntoView({block:'center',behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
        });
    };
    form.addEventListener('change', event => {
        if (event.target.name === 'slot' && !browsingSlots) advance(scope || submit, event.target);
        if (event.target.name === 'repeat_scope') advance(submit, event.target);
    });
    form.addEventListener('keydown', event => {
        if (event.target.name === 'slot') browsingSlots = event.key.startsWith('Arrow');
    });
    form.addEventListener('keyup', () => { browsingSlots = false; });
    form.addEventListener('pointerdown', () => { browsingSlots = false; });
    form.addEventListener('booking:updated', event => {
        if (event.detail?.step !== 'date') return;
        const slot = form.querySelector('input[name="slot"]');
        const period = slot?.closest('details');
        if (period) period.open = true;
        advance(slot, event.detail.origin);
    });
});
