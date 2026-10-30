(() => {
    const definitions = {
        date: { placeholder: 'ÉÉÉÉ. HH. NN.', label: 'Dátum', pattern: /^(\d{4})[.\-/]\s*(\d{1,2})[.\-/]\s*(\d{1,2})\.?$/ },
        time: { placeholder: 'ÓÓ:PP', label: 'Időpont', pattern: /^(\d{1,2}):(\d{2})$/ },
        'datetime-local': { placeholder: 'ÉÉÉÉ. HH. NN. ÓÓ:PP', label: 'Dátum és idő', pattern: /^(\d{4})[.\-/]\s*(\d{1,2})[.\-/]\s*(\d{1,2})\.?\s+(\d{1,2}):(\d{2})$/ },
    };
    const pad = value => String(value).padStart(2, '0');
    const format = (type, value) => {
        if (!value) return '';
        if (type === 'time') return value.slice(0, 5);
        const [date, time] = value.split('T');
        const [year, month, day] = date.split('-');
        return `${year}. ${month}. ${day}.${type === 'datetime-local' ? ` ${time.slice(0, 5)}` : ''}`;
    };
    const parse = (type, value) => {
        const match = value.trim().match(definitions[type].pattern);
        if (!match) return null;
        if (type === 'time') return `${pad(match[1])}:${match[2]}`;
        const date = `${match[1]}-${pad(match[2])}-${pad(match[3])}`;
        return type === 'date' ? date : `${date}T${pad(match[4])}:${match[5]}`;
    };
    let pickerId = 0;
    const addDatePicker = (native, text, wrapper, accessibleName) => {
        const id = `date-picker-${++pickerId}`;
        const dialog = document.createElement('dialog');
        dialog.className = 'localized-date-dialog';
        dialog.id = id;
        dialog.setAttribute('aria-labelledby', `${id}-title`);
        const heading = document.createElement('h2');
        heading.id = `${id}-title`;
        heading.textContent = accessibleName;
        const navigation = document.createElement('div');
        navigation.className = 'localized-date-navigation';
        const button = (label, action) => {
            const control = document.createElement('button');
            control.type = 'button';
            control.textContent = label;
            control.addEventListener('click', action);
            return control;
        };
        const previous = button('←', () => changeMonth(-1));
        previous.setAttribute('aria-label', 'Előző hónap');
        const next = button('→', () => changeMonth(1));
        next.setAttribute('aria-label', 'Következő hónap');
        const month = document.createElement('select');
        month.setAttribute('aria-label', 'Hónap');
        const year = document.createElement('select');
        year.setAttribute('aria-label', 'Év');
        const dateValue = date => `${String(date.getUTCFullYear()).padStart(4, '0')}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
        const dateFrom = value => new Date(`${value}T12:00:00Z`);
        const makeDate = (year, month, day) => {
            const date = new Date(0);
            date.setUTCFullYear(year, month, day);
            date.setUTCHours(12, 0, 0, 0);
            return date;
        };
        const monthFormatter = new Intl.DateTimeFormat('hu', {month: 'long', timeZone: 'UTC'});
        const dayFormatter = new Intl.DateTimeFormat('hu', {dateStyle: 'full', timeZone: 'UTC'});
        for (let index = 0; index < 12; index++) {
            month.add(new Option(monthFormatter.format(makeDate(2026, index, 1)), String(index)));
        }
        navigation.append(previous, month, year, next);
        const table = document.createElement('table');
        table.className = 'localized-date-grid';
        const caption = table.createCaption();
        caption.className = 'sr-only';
        caption.setAttribute('aria-live', 'polite');
        const week = table.createTHead().insertRow();
        ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'].forEach(name => {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = name.slice(0, 2);
            cell.setAttribute('aria-label', name);
            week.append(cell);
        });
        const days = table.createTBody();
        const actions = document.createElement('div');
        actions.className = 'localized-date-actions';
        let view;
        let today;
        const allowed = value => (!native.min || value >= native.min) && (!native.max || value <= native.max);
        const selectDate = value => {
            native.value = value;
            native.dispatchEvent(new Event('localized:sync'));
            dialog.close();
            native.dispatchEvent(new Event('input', {bubbles: true}));
            native.dispatchEvent(new Event('change', {bubbles: true}));
        };
        const todayButton = button('Ma', () => selectDate(today));
        actions.append(todayButton);
        if (!text.required) actions.append(button('Törlés', () => selectDate('')));
        actions.append(button('Mégse', () => dialog.close()));
        dialog.append(heading, navigation, table, actions);
        document.body.append(dialog);
        const render = focusDate => {
            const currentYear = view.getUTCFullYear();
            const currentMonth = view.getUTCMonth();
            year.value = String(currentYear);
            month.value = String(currentMonth);
            caption.textContent = `${currentYear}. ${monthFormatter.format(view)}`;
            const lastDay = makeDate(currentYear, currentMonth + 1, 0).getUTCDate();
            const offset = (view.getUTCDay() + 6) % 7;
            previous.disabled = currentYear <= 1 && currentMonth === 0 || !!native.min && dateValue(makeDate(currentYear, currentMonth, 0)) < native.min;
            next.disabled = currentYear >= 9999 && currentMonth === 11 || !!native.max && dateValue(makeDate(currentYear, currentMonth + 1, 1)) > native.max;
            Array.from(month.options).forEach(option => {
                const index = Number(option.value);
                option.disabled = !!native.min && dateValue(makeDate(currentYear, index + 1, 0)) < native.min || !!native.max && dateValue(makeDate(currentYear, index, 1)) > native.max;
            });
            days.replaceChildren();
            let row;
            for (let cell = 0; cell < Math.ceil((offset + lastDay) / 7) * 7; cell++) {
                if (cell % 7 === 0) row = days.insertRow();
                const container = row.insertCell();
                const day = cell - offset + 1;
                if (day < 1 || day > lastDay) continue;
                const date = makeDate(currentYear, currentMonth, day);
                const value = dateValue(date);
                const control = button(String(day), () => selectDate(value));
                control.dataset.date = value;
                control.setAttribute('aria-label', dayFormatter.format(date));
                control.setAttribute('aria-pressed', String(value === native.value));
                if (value === today) control.setAttribute('aria-current', 'date');
                control.disabled = !allowed(value);
                control.tabIndex = -1;
                container.append(control);
            }
            const preferred = focusDate || native.value || today;
            const active = days.querySelector(`[data-date="${preferred}"]:not(:disabled)`) || days.querySelector('button:not(:disabled)');
            if (active) {
                active.tabIndex = 0;
                if (focusDate) active.focus({preventScroll: true});
            }
        };
        const changeMonth = delta => {
            view = makeDate(view.getUTCFullYear(), view.getUTCMonth() + delta, 1);
            render();
        };
        month.addEventListener('change', () => { view = makeDate(view.getUTCFullYear(), Number(month.value), 1); render(); });
        year.addEventListener('change', () => {
            view = makeDate(Number(year.value), view.getUTCMonth(), 1);
            if (native.min && dateValue(makeDate(view.getUTCFullYear(), view.getUTCMonth() + 1, 0)) < native.min) view = dateFrom(native.min);
            if (native.max && dateValue(view) > native.max) view = dateFrom(native.max);
            view = makeDate(view.getUTCFullYear(), view.getUTCMonth(), 1);
            render();
        });
        days.addEventListener('keydown', event => {
            const value = event.target.dataset.date;
            if (!value) return;
            const date = dateFrom(value);
            const offsets = {ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7, Home: -((date.getUTCDay() + 6) % 7), End: 6 - ((date.getUTCDay() + 6) % 7)};
            let target;
            if (Object.hasOwn(offsets, event.key)) target = makeDate(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate() + offsets[event.key]);
            else if (event.key === 'PageUp' || event.key === 'PageDown') {
                const delta = (event.key === 'PageUp' ? -1 : 1) * (event.shiftKey ? 12 : 1);
                target = makeDate(date.getUTCFullYear(), date.getUTCMonth() + delta, 1);
                target.setUTCDate(Math.min(date.getUTCDate(), makeDate(target.getUTCFullYear(), target.getUTCMonth() + 1, 0).getUTCDate()));
            } else return;
            event.preventDefault();
            if (target.getUTCFullYear() < 1 || target.getUTCFullYear() > 9999 || !allowed(dateValue(target))) return;
            view = makeDate(target.getUTCFullYear(), target.getUTCMonth(), 1);
            render(dateValue(target));
        });
        const picker = button('Naptár', () => {
            if (native.disabled || native.readOnly) return;
            const now = new Date();
            today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
            let value = native.value || today;
            if (native.min && value < native.min) value = native.min;
            if (native.max && value > native.max) value = native.max;
            view = dateFrom(value);
            const selectedYear = view.getUTCFullYear();
            const firstYear = native.min ? dateFrom(native.min).getUTCFullYear() : Math.max(1, Math.min(1900, selectedYear - 100));
            const lastYear = native.max ? dateFrom(native.max).getUTCFullYear() : Math.min(9999, Math.max(now.getFullYear() + 100, selectedYear + 100));
            year.replaceChildren();
            for (let number = firstYear; number <= lastYear; number++) year.add(new Option(String(number), String(number)));
            view = makeDate(selectedYear, view.getUTCMonth(), 1);
            todayButton.disabled = !allowed(today);
            render();
            dialog.showModal();
            picker.setAttribute('aria-expanded', 'true');
            render(value);
        });
        picker.className = 'localized-field-picker';
        picker.setAttribute('aria-label', `${accessibleName}: naptár megnyitása`);
        picker.setAttribute('aria-haspopup', 'dialog');
        picker.setAttribute('aria-controls', id);
        picker.setAttribute('aria-expanded', 'false');
        picker.disabled = native.disabled;
        dialog.addEventListener('close', () => picker.setAttribute('aria-expanded', 'false'));
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
        });
        wrapper.append(picker);
        return picker;
    };
    document.querySelectorAll('input[type="date"], input[type="time"], input[type="datetime-local"]').forEach(native => {
        const type = native.type;
        const definition = definitions[type];
        const accessibleName = native.getAttribute('aria-label') || Array.from(native.labels ?? []).map(label => label.textContent.trim().replace(/\s+/g, ' ')).join(' ') || definition.label;
        const wrapper = document.createElement('span');
        wrapper.className = `localized-field localized-field-${type}`;
        const text = document.createElement('input');
        text.type = 'text';
        text.className = 'localized-field-text';
        text.inputMode = 'text';
        text.placeholder = definition.placeholder;
        text.autocomplete = 'off';
        text.value = format(type, native.value);
        text.required = native.required;
        text.disabled = native.disabled;
        if (native.id) { text.id = native.id; native.removeAttribute('id'); }
        text.setAttribute('aria-label', `${accessibleName} (${definition.placeholder})`);
        native.required = false;
        native.setAttribute('aria-hidden', 'true');
        native.tabIndex = -1;
        native.classList.add('localized-field-native');
        native.before(wrapper);
        wrapper.append(text, native);
        const validate = () => {
            const raw = text.value.trim();
            const value = raw ? parse(type, raw) : '';
            native.setCustomValidity('');
            native.value = value ?? '';
            const valid = !raw || (value !== null && native.value === value && native.validity.valid);
            const message = valid ? '' : `Érvényes ${definition.label.toLowerCase()} szükséges: ${definition.placeholder}.`;
            text.setCustomValidity(message);
            native.setCustomValidity(message);
            return valid;
        };
        let syncingText = false;
        text.addEventListener('input', () => {
            validate();
            syncingText = true;
            native.dispatchEvent(new Event('input', { bubbles: true }));
            syncingText = false;
        });
        text.addEventListener('change', () => {
            if (validate()) {
                text.value = format(type, native.value);
                native.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        native.addEventListener('input', () => { if (!syncingText) { text.value = format(type, native.value); text.setCustomValidity(''); native.setCustomValidity(''); } });
        native.addEventListener('localized:sync', () => { text.value = format(type, native.value); text.setCustomValidity(''); native.setCustomValidity(''); });
        const picker = type === 'date' ? addDatePicker(native, text, wrapper, accessibleName) : null;
        new MutationObserver(() => { text.disabled = native.disabled; if (picker) picker.disabled = native.disabled; }).observe(native, { attributes: true, attributeFilter: ['disabled'] });
    });
})();
