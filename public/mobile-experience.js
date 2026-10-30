document.querySelectorAll('.booking-salon-details').forEach(details => {
    if (window.matchMedia('(max-width: 640px)').matches) details.open = false;
});

document.addEventListener('click', event => {
    if (event.target.closest('a[href="#booking-terms"]')) {
        const details = document.querySelector('.booking-salon-details');
        if (details) details.open = true;
    }
});

document.querySelectorAll('.booking-page .booking-submit').forEach(form => {
    const steps = document.querySelector('[data-booking-steps]');
    const filter = document.getElementById('booking-service');
    const review = form.querySelector('[data-booking-review]');
    const selection = form.querySelector('[data-current-selection]');
    if (!steps || !filter || !review || !selection) return;
    let activeStep = location.hash === '#booking-slots' ? 2 : 1;
    const render = () => {
        const selected = form.querySelector('input[name=slot]:checked');
        if (!selected && activeStep === 3) activeStep = 2;
        steps.querySelectorAll('[data-booking-step]').forEach(link => {
            const step = Number(link.dataset.bookingStep);
            if (step === activeStep) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
            link.classList.toggle('is-complete', step < activeStep);
            if (step === 3) link.setAttribute('aria-disabled', String(!selected));
        });
        selection.hidden = !selected;
        if (selected) {
            const tile = selected.closest('.slot');
            selection.querySelector('[data-selection-slot]').textContent = `${tile.querySelector('strong').textContent.trim()} · ${tile.querySelector('small').textContent.trim()}`;
            selection.querySelector('[data-selection-date]').textContent = review.dataset.date;
        }
    };
    filter.addEventListener('focusin', () => { activeStep = 1; render(); });
    form.addEventListener('focusin', event => {
        if (event.target.closest('.slots-field')) activeStep = 2;
        else if (event.target.closest('[data-booking-guest-fields]')) activeStep = 3;
        render();
    });
    form.addEventListener('change', event => {
        if (event.target.name === 'slot') activeStep = 3;
        render();
    });
    form.addEventListener('booking:updated', render);
    steps.addEventListener('click', event => {
        const link = event.target.closest('[data-booking-step]');
        if (!link) return;
        activeStep = Number(link.dataset.bookingStep);
        render();
    });
    let scrollPending = false;
    window.addEventListener('scroll', () => {
        if (scrollPending) return;
        scrollPending = true;
        requestAnimationFrame(() => {
            scrollPending = false;
            const selected = form.querySelector('input[name=slot]:checked');
            const guestTop = form.querySelector('[data-booking-guest-fields]').getBoundingClientRect().top;
            const slotsTop = document.getElementById('booking-slots').getBoundingClientRect().top;
            const visibleStep = selected && guestTop <= 160 ? 3 : slotsTop <= 160 ? 2 : 1;
            if (activeStep !== visibleStep) { activeStep = visibleStep; render(); }
        });
    }, {passive: true});
    render();
});
