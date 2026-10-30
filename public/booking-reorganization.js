document.querySelectorAll('a[href="#booking-reorganize"]').forEach(link => {
    link.addEventListener('click', () => { document.getElementById('booking-reorganize').open = true; });
});
if (location.hash === '#booking-reorganize' && document.getElementById('booking-reorganize')) document.getElementById('booking-reorganize').open = true;

document.querySelectorAll('[data-reorganization]').forEach(form => {
    const shift = form.querySelector('[data-reorganization-shift]');
    const swap = form.querySelector('[data-reorganization-swap]');
    const date = form.querySelector('[data-swap-date]');
    const list = form.querySelector('[data-swap-candidates]');
    const status = form.querySelector('[data-swap-status]');
    const submit = form.querySelector('[data-reorganization-preview]');
    let controller;
    let sequence = 0;
    let loading = false;
    const update = () => {
        const swapping = form.querySelector('[name=mode]:checked')?.value === 'swap';
        shift.hidden = swapping;
        shift.disabled = swapping;
        swap.hidden = !swapping;
        swap.disabled = !swapping;
        submit.disabled = swapping && (loading || !list.querySelector('input:checked'));
    };
    const refresh = async () => {
        const current = ++sequence;
        controller?.abort();
        list.replaceChildren();
        if (!date.value || !date.checkValidity()) {
            status.textContent = 'Válassz érvényes napot a foglalásokhoz.';
            loading = false;
            update();
            return;
        }
        controller = new AbortController();
        loading = true;
        status.textContent = 'Foglalások keresése…';
        update();
        try {
            const url = new URL(form.dataset.optionsUrl);
            url.searchParams.set('date', date.value);
            const response = await fetch(url, {signal:controller.signal,cache:'no-store',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok || response.redirected) throw new Error('options');
            const result = await response.json();
            if (current !== sequence) return;
            result.options.forEach(option => {
                const label = document.createElement('label');
                label.className = 'swap-candidate';
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'swap_booking_id';
                input.value = option.id;
                const copy = document.createElement('span');
                const title = document.createElement('strong');
                title.textContent = `${option.time} · ${option.name}`;
                const description = document.createElement('small');
                description.textContent = option.description;
                copy.append(title, description);
                label.append(input, copy);
                list.append(label);
            });
            status.textContent = result.options.length ? 'Válassz egy másik vendéget a cseréhez.' : 'Ezen a napon nincs másik cserélhető foglalás.';
        } catch (error) {
            if (error.name !== 'AbortError' && current === sequence) status.textContent = 'Nem sikerült betölteni a foglalásokat. Próbáld újra a Nap foglalásainak betöltése gombbal.';
        } finally {
            if (current === sequence) { loading = false; update(); }
        }
    };
    form.addEventListener('change', update);
    date.addEventListener('input', () => { sequence++; controller?.abort(); list.replaceChildren(); loading = false; update(); });
    date.addEventListener('change', refresh);
    document.getElementById(date.getAttribute('form')).addEventListener('submit', event => { event.preventDefault(); refresh(); });
    update();
});

document.querySelectorAll('[data-reorganization-save]').forEach(button => {
    button.form.addEventListener('submit', () => { if (button.form.checkValidity()) button.disabled = true; });
});
