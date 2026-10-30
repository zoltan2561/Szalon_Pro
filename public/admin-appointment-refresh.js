document.querySelectorAll('[data-admin-reschedule]').forEach(section => {
    const filters = section.querySelector('[data-reschedule-filter]');
    const date = filters?.querySelector('input[name="date"]');
    const postForm = section.querySelector('input[name="action"][value="move"]')?.form;
    const submit = postForm?.querySelector('button[type="submit"], button:not([type])');
    const status = section.querySelector('[data-reschedule-status]');
    if (!filters || !date || !postForm || !submit || !status) return;

    let latestSearch = 0;
    const clearSlots = message => {
        postForm.querySelector('.slots-field')?.replaceChildren();
        submit.disabled = true;
        status.textContent = message;
    };
    date.addEventListener('input', () => {
        latestSearch++;
        clearSlots('Válassz érvényes napot a szabad időpontokhoz.');
    });
    const refresh = async () => {
        const origin = document.activeElement;
        const search = ++latestSearch;
        if (!date.value || !date.checkValidity()) {
            clearSlots('Válassz érvényes napot a szabad időpontokhoz.');
            return;
        }
        clearSlots('Szabad időpontok keresése…');
        try {
            const url = new URL(filters.action || window.location.href, window.location.href);
            url.search = new URLSearchParams(new FormData(filters)).toString();
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Availability request failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (search !== latestSearch) return;
            const newSection = page.querySelector('[data-admin-reschedule]');
            const newForm = newSection?.querySelector('input[name="action"][value="move"]')?.form;
            const newSlots = newForm?.querySelector('.slots-field');
            const currentSlots = postForm.querySelector('.slots-field');
            if (!newSlots || !currentSlots) throw new Error('Availability response incomplete');
            if (newForm.querySelector('input[name="revision"]')?.value !== postForm.querySelector('input[name="revision"]')?.value) {
                status.textContent = 'A foglalás időközben változott. Töltsd újra az oldalt.';
                return;
            }
            currentSlots.replaceWith(newSlots);
            const hasSlots = Boolean(newSlots.querySelector('input[name="slot"]'));
            submit.disabled = !hasSlots;
            status.textContent = hasSlots ? 'A szabad időpontok frissültek.' : 'Erre a napra nincs szabad időpont.';
            postForm.dispatchEvent(new CustomEvent('booking:updated', {detail:{step:'date',origin}}));
        } catch (error) {
            if (search === latestSearch) status.textContent = 'Nem sikerült frissíteni az időpontokat. Próbáld újra a Szabad időpontok gombbal.';
        }
    };
    date.addEventListener('change', refresh);
    filters.addEventListener('submit', event => {
        event.preventDefault();
        refresh();
    });
});
