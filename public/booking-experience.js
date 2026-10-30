document.querySelectorAll('.booking-submit').forEach(form => {
    const review = form.querySelector('[data-booking-review]');
    if (!review) return;
    const submit = form.querySelector('button[type="submit"], button:not([type])');
    const guestFields = form.querySelector('[data-booking-guest-fields]');
    let pointerSelection = false;
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
        if (submit) submit.disabled = !selected || form.getAttribute('aria-busy') === 'true';
        if (guestFields) guestFields.hidden = !selected;
    };
    form.addEventListener('pointerdown', event => { pointerSelection = Boolean(event.target.closest('.slot')); });
    form.addEventListener('change', event => {
        if (event.target.name !== 'slot') return;
        update();
        if (pointerSelection && guestFields && window.matchMedia('(max-width: 640px)').matches) {
            guestFields.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
        }
        pointerSelection = false;
    });
    form.addEventListener('booking:updated', update);
    update();
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
