document.querySelectorAll('[data-repeat-booking]').forEach(section => {
    const form = section.closest('form');
    const count = section.querySelector('[name=repeat_count]');
    const weeks = section.querySelector('[name=repeat_weeks]');
    const status = section.querySelector('[data-repeat-status]');
    const dates = section.querySelector('[data-repeat-dates]');
    const total = section.querySelector('[data-repeat-total]');
    const retry = section.querySelector('[data-repeat-retry]');
    const done = section.querySelector('[data-repeat-done]');
    const summary = form.querySelector('[data-repeat-summary]');
    const saveLabel = form.querySelector('[data-booking-save-label]');
    const singleLabel = saveLabel?.textContent;
    let request;
    let sequence = 0;
    const refresh = async () => {
        const current = ++sequence;
        request?.abort();
        const selected = form.querySelector('input[name=slot]:checked');
        dates.replaceChildren();
        total.textContent = '';
        retry.hidden = true;
        if (summary) { summary.hidden = count.value === '1'; summary.textContent = count.value + ' alkalom ellenőrzése…'; }
        if (saveLabel) saveLabel.textContent = count.value === '1' ? singleLabel : count.value + ' időpont ' + (form.closest('.admin') ? 'mentése' : 'véglegesítése');
        weeks.disabled = count.value === '1';
        if (count.value === '1' || !selected) {
            form.dataset.repeatReady = count.value === '1' ? 'true' : 'false';
            done.disabled = !selected;
            status.textContent = count.value === '1' ? 'Most egy alkalmat foglalsz.' : 'Először válassz szabad időpontot.';
            form.dispatchEvent(new Event('booking:repeat-updated'));
            return;
        }
        form.dataset.repeatReady = 'false';
        done.disabled = true;
        status.textContent = 'A következő alkalmak ellenőrzése…';
        form.dispatchEvent(new Event('booking:repeat-updated'));
        const target = new URL(section.dataset.previewUrl);
        const params = new URLSearchParams({service_id: form.querySelector('input[name=service_id]').value, slot: selected.value, repeat_count: count.value, repeat_weeks: weeks.value});
        form.querySelectorAll('input[name="extra_service_ids[]"]').forEach(input => params.append('extra_service_ids[]', input.value));
        target.search = params.toString();
        request = new AbortController();
        try {
            const response = await fetch(target, {signal: request.signal, cache: 'no-store', headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('preview');
            const result = await response.json();
            if (current !== sequence) return;
            result.dates.forEach(date => {
                const item = document.createElement('li');
                item.textContent = date.label + (date.available ? ' · Szabad' : ' · Nem foglalható');
                item.className = date.available ? '' : 'repeat-conflict';
                dates.append(item);
            });
            const format = value => new Intl.NumberFormat('hu-HU').format(value) + (result.price_kind === 'from' ? ' Ft-tól' : result.price_kind === 'indicative' ? ' Ft (tájékoztató ár)' : ' Ft');
            total.textContent = 'Alkalmanként: ' + format(result.price_per_visit) + ' · Összesen: ' + format(result.total);
            if (summary) summary.textContent = count.value + ' alkalom · ' + (weeks.value === '1' ? 'hetente' : weeks.value + ' hetente') + ' · Összesen: ' + format(result.total);
            status.textContent = result.available ? 'Mindegyik felsorolt alkalom szabad. A mentéssel mindet lefoglalod.' : 'Nem minden alkalom szabad. Válassz másik kezdést, ritmust vagy kevesebb alkalmat.';
            form.dataset.repeatReady = result.available ? 'true' : 'false';
            done.disabled = !result.available;
        } catch (error) {
            if (current !== sequence || error.name === 'AbortError') return;
            status.textContent = 'Az alkalmakat nem sikerült ellenőrizni. Próbáld újra, vagy válassz egy alkalmat.';
            retry.hidden = false;
        } finally {
            if (current === sequence) form.dispatchEvent(new Event('booking:repeat-updated'));
        }
    };
    count.addEventListener('change', refresh);
    weeks.addEventListener('change', refresh);
    retry.addEventListener('click', refresh);
    done.addEventListener('click', () => { section.open = false; form.dispatchEvent(new Event('booking:advance-guest')); });
    form.addEventListener('change', event => { if (event.target.name === 'slot') refresh(); });
    form.addEventListener('booking:updated', refresh);
    refresh();
});
