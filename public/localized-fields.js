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
        let picker = null;
        new MutationObserver(() => { text.disabled = native.disabled; if (picker) picker.disabled = native.disabled; }).observe(native, { attributes: true, attributeFilter: ['disabled'] });
        if (type === 'date' && typeof native.showPicker === 'function') {
            picker = document.createElement('button');
            picker.type = 'button';
            picker.className = 'localized-field-picker';
            picker.textContent = 'Naptár';
            picker.setAttribute('aria-label', `${accessibleName}: naptár megnyitása`);
            picker.disabled = native.disabled;
            picker.addEventListener('click', () => { if (!native.disabled) native.showPicker(); });
            wrapper.append(picker);
        }
    });
})();
