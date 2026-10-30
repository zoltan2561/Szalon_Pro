document.querySelectorAll('.public [data-availability-filter]').forEach(filter => {
    const booking = document.querySelector('.public [data-availability-submit]');
    const status = filter.querySelector('[data-availability-status]');
    const date = filter.querySelector('input[name="date"]');
    const service = filter.querySelector('select[name="service_id"]');
    const staff = filter.querySelector('select[name="staff_id"]');
    const isBookingForm = filter.id === 'booking-service';
    if (!booking || !status || !date) return;

    const button = booking.querySelector('button[type="submit"], button:not([type])');
    const slotList = booking.querySelector('.slots');
    if (!button || !slotList) return;

    let sequence = 0;
    let timer = null;
    let controller = null;

    const invalidate = message => {
        sequence++;
        clearTimeout(timer);
        controller?.abort();
        button.disabled = true;
        slotList.replaceChildren();
        const selectedNote = booking.querySelector('.selected-slot-note');
        if (selectedNote) selectedNote.hidden = true;
        status.hidden = false;
        status.textContent = message;
        booking.setAttribute('aria-busy', 'true');
        booking.dispatchEvent(new Event('booking:updated'));
    };

    const schedule = event => {
        const origin = document.activeElement;
        const step = event.target.name;
        invalidate('Szabad időpontok keresése…');
        if (!date.value || !date.checkValidity()) {
            status.textContent = 'Adj meg érvényes napot a szabad időpontok kereséséhez.';
            booking.removeAttribute('aria-busy');
            return;
        }
        const current = sequence;
        timer = setTimeout(async () => {
            let loaded = false;
            controller = new AbortController();
            const url = new URL(filter.action);
            url.search = new URLSearchParams(new FormData(filter)).toString();
            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store',
                    signal: controller.signal,
                });
                if (response.status === 429) throw new Error('limit');
                if (!response.ok || response.redirected) throw new Error('response');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (current !== sequence) return;
                const freshBooking = page.querySelector('.public [data-availability-submit]');
                const revision = booking.querySelector('input[name="revision"]');
                if (revision && (!freshBooking || freshBooking.querySelector('input[name="revision"]')?.value !== revision.value)) {
                    throw new Error('changed');
                }
                const freshSlots = freshBooking?.querySelector('.slots');
                if (!freshSlots) throw new Error('response');
                if (isBookingForm) {
                    const freshFilter = page.querySelector('[data-availability-filter]');
                    const freshStaff = freshFilter?.querySelector('select[name="staff_id"]');
                    const freshService = freshBooking.querySelector('input[name="service_id"]');
                    const freshSummary = page.querySelector('.booking-summary');
                    const currentSummary = document.querySelector('.booking-summary');
                    const freshDate = freshBooking.querySelector('.booking-date-label');
                    const currentDate = booking.querySelector('.booking-date-label');
                    if (!freshStaff || !freshService || !freshSummary || !currentSummary || !freshDate || !currentDate || !staff) throw new Error('response');
                    const selectedStaff = freshStaff.value;
                    staff.replaceChildren(...Array.from(freshStaff.children));
                    staff.value = selectedStaff;
                    booking.querySelector('input[name="service_id"]').value = freshService.value;
                    currentSummary.replaceChildren(...Array.from(freshSummary.childNodes));
                    currentDate.textContent = freshDate.textContent;
                    const review = booking.querySelector('[data-booking-review]');
                    const freshReview = freshBooking.querySelector('[data-booking-review]');
                    if (review && freshReview) Object.assign(review.dataset, freshReview.dataset);
                    const explanation = booking.querySelector('[data-availability-explanation]');
                    const freshExplanation = freshBooking.querySelector('[data-availability-explanation]');
                    if (explanation && freshExplanation) explanation.textContent = freshExplanation.textContent;
                }
                freshSlots.querySelectorAll('input[name="slot"]:checked').forEach(input => { input.checked = false; });
                slotList.replaceChildren(...Array.from(freshSlots.childNodes));
                slotList.className = freshSlots.className;
                const hasSlots = Boolean(slotList.querySelector('input[name="slot"]'));
                button.disabled = !hasSlots;
                status.textContent = hasSlots ? 'A szabad időpontok frissültek. Válassz időpontot.' : 'Erre a napra nincs szabad időpont. Válassz másik napot.';
                loaded = true;
            } catch (error) {
                if (current !== sequence || error.name === 'AbortError') return;
                status.textContent = error.message === 'limit'
                    ? 'Túl sok keresés történt. Várj egy kicsit, majd használd a Szabad időpontok gombot.'
                    : error.message === 'changed'
                        ? 'A foglalás időközben megváltozott vagy már nem módosítható. Nyisd meg újra a kezelőoldalt.'
                        : 'Nem sikerült frissíteni az időpontokat. Használd a Szabad időpontok gombot.';
            } finally {
                if (current === sequence) {
                    booking.removeAttribute('aria-busy');
                    booking.dispatchEvent(new CustomEvent('booking:updated', {detail: loaded ? {step, origin} : null}));
                }
            }
        }, 350);
    };

    date.addEventListener('input', () => {
        invalidate('A dátum megváltozott. Válaszd ki az új napot.');
        booking.removeAttribute('aria-busy');
    });
    date.addEventListener('change', schedule);
    service?.addEventListener('change', schedule);
    staff?.addEventListener('change', schedule);
    booking.addEventListener('click', event => {
        const nextDay = event.target.closest('[data-next-available]');
        if (!nextDay) return;
        event.preventDefault();
        date.value = new URL(nextDay.href).searchParams.get('date');
        date.dispatchEvent(new Event('localized:sync'));
        date.dispatchEvent(new Event('change', {bubbles: true}));
    });
});
