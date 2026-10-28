document.querySelectorAll('form[data-auto-filter]').forEach(form => {
    const kind = form.dataset.autoFilter;
    const status = document.createElement('span');
    status.setAttribute('role', 'status');
    status.className = 'muted';
    form.append(status);
    let sequence = 0;
    let timer;
    let activeRequest;

    const bindCalendarTracks = root => {
        root.querySelectorAll('[data-calendar-create]').forEach(track => {
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
    };

    const refresh = async current => {
        if (!form.checkValidity()) return;
        const date = form.querySelector('input[name="date"]');
        if (kind === 'calendar' && !date?.value) return;
        const target = new URL(form.action);
        target.search = new URLSearchParams(new FormData(form)).toString();
        activeRequest?.abort();
        activeRequest = new AbortController();
        status.textContent = 'Frissítés…';
        try {
            const response = await fetch(target, {headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: activeRequest.signal});
            if (!response.ok) throw new Error('Filter request failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (current !== sequence) return;
            if (kind === 'calendar') {
                const oldOverview = document.querySelector('.calendar-overview');
                const newOverview = page.querySelector('.calendar-overview');
                const newForm = newOverview?.querySelector('form[data-auto-filter="calendar"]');
                const oldHeading = document.querySelector('.calendar-page-heading');
                const newHeading = page.querySelector('.calendar-page-heading');
                if (!oldOverview || !newOverview || !newForm || !oldHeading || !newHeading) throw new Error('Calendar response incomplete');
                newForm.replaceWith(form);
                oldOverview.replaceWith(newOverview);
                oldHeading.replaceWith(newHeading);
                bindCalendarTracks(newOverview);
            } else {
                const oldResults = document.querySelector('[data-auto-filter-results]');
                const newResults = page.querySelector('[data-auto-filter-results]');
                if (!oldResults || !newResults) throw new Error('Filter response incomplete');
                oldResults.replaceWith(newResults);
                if (kind === 'statistics') {
                    const oldHeading = document.querySelector('.page-heading');
                    const newHeading = page.querySelector('.page-heading');
                    if (oldHeading && newHeading) oldHeading.replaceWith(newHeading);
                }
            }
            history.replaceState(null, '', target);
            document.title = page.title || document.title;
            status.textContent = 'A lista frissült.';
        } catch (error) {
            if (current === sequence && error.name !== 'AbortError') status.textContent = 'Nem sikerült frissíteni. A korábbi adatok láthatók; próbáld újra a gombbal.';
        }
    };

    const queue = delay => {
        const current = ++sequence;
        clearTimeout(timer);
        activeRequest?.abort();
        timer = setTimeout(() => refresh(current), delay);
    };
    form.addEventListener('change', event => {
        if (event.target instanceof HTMLInputElement && event.target.name === 'date'
            || event.target instanceof HTMLSelectElement && ['status', 'period'].includes(event.target.name)) queue(0);
    });
    form.querySelector('input[name="date"]')?.addEventListener('input', () => {
        ++sequence;
        clearTimeout(timer);
        activeRequest?.abort();
        status.textContent = 'A korábbi adatok láthatók, amíg kiválasztod a dátumot.';
    });
    form.querySelector('input[name="q"]')?.addEventListener('input', () => queue(400));
});
