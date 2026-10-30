(() => {
    const tablist = document.querySelector('[data-settings-tabs]');
    const form = document.querySelector('.admin-settings');
    if (!tablist || !form) return;

    const tabs = Array.from(tablist.querySelectorAll('a[href^="#"]'));
    const panels = tabs.map(tab => document.getElementById(tab.hash.slice(1)));
    if (!tabs.length || panels.some(panel => !panel || !form.contains(panel))) return;

    tablist.setAttribute('role', 'tablist');
    tablist.setAttribute('aria-label', 'Beállítások szakaszai');
    tabs.forEach((tab, index) => {
        tab.id = `settings-tab-${index}`;
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', panels[index].id);
        panels[index].setAttribute('role', 'tabpanel');
        panels[index].setAttribute('aria-labelledby', tab.id);
    });

    const selectTab = index => {
        tabs.forEach((tab, tabIndex) => {
            const selected = tabIndex === index;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            panels[tabIndex].hidden = !selected;
        });
        document.querySelectorAll('[data-settings-extra]').forEach(extra => {
            extra.hidden = extra.dataset.settingsExtra !== panels[index].id;
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => {
            event.preventDefault();
            selectTab(index);
        });
        tab.addEventListener('keydown', event => {
            let next = null;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next === null) return;
            event.preventDefault();
            selectTab(next);
            tabs[next].focus();
        });
    });

    const focusableField = field => {
        if (field.classList.contains('localized-field-native')) {
            return field.closest('.localized-field')?.querySelector('.localized-field-text') || field;
        }
        if (field.classList.contains('upload-control-native')) {
            return field.previousElementSibling?.querySelector('button') || field;
        }

        return field;
    };
    const panelForField = field => panels.findIndex(panel => panel.contains(field));
    let activeIndex = tabs.findIndex(tab => tab.hash === window.location.hash);
    let serverErrorField = null;
    try {
        const errors = JSON.parse(form.dataset.settingsErrors || '[]');
        const fields = Array.from(form.elements).filter(field => field.name);
        for (const error of errors) {
            serverErrorField = fields.find(field => {
                const name = field.name.replace(/\[([^\]]*)\]/g, (_, key) => key ? `.${key}` : '');
                return name === error || name.startsWith(`${error}.`) || error.startsWith(`${name}.`);
            });
            if (serverErrorField && panelForField(serverErrorField) >= 0) break;
        }
    } catch (_) {
        serverErrorField = null;
    }
    if (serverErrorField && panelForField(serverErrorField) >= 0) activeIndex = panelForField(serverErrorField);
    selectTab(activeIndex < 0 ? 0 : activeIndex);
    if (serverErrorField) {
        const field = focusableField(serverErrorField);
        field.focus();
        field.scrollIntoView({block: 'center', behavior: 'instant'});
    }

    const validationMessage = form.querySelector('[data-settings-validation]');
    let showingInvalid = false;
    form.addEventListener('invalid', event => {
        event.preventDefault();
        if (showingInvalid || !(event.target instanceof HTMLElement)) return;
        showingInvalid = true;
        const index = panelForField(event.target);
        if (index >= 0) selectTab(index);
        const field = focusableField(event.target);
        field.setAttribute('aria-invalid', 'true');
        if (validationMessage) {
            validationMessage.textContent = 'Ellenőrizd a kijelölt mezőt, majd próbáld újra.';
            validationMessage.hidden = false;
        }
        field.focus();
        field.scrollIntoView({block: 'center', behavior: 'instant'});
        window.setTimeout(() => { showingInvalid = false; }, 0);
    }, true);
    form.addEventListener('input', event => {
        if (event.target instanceof HTMLElement) event.target.removeAttribute('aria-invalid');
        if (validationMessage) validationMessage.hidden = true;
    });
    let dirty = false;
    const dirtyNotice = form.querySelector('[data-settings-dirty]');
    const markDirty = () => {
        dirty = true;
        form.classList.add('has-changes');
        if (dirtyNotice) dirtyNotice.hidden = false;
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    if (serverErrorField) markDirty();
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
