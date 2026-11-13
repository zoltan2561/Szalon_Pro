(() => {
    const form = document.querySelector('.admin-settings');
    const preview = document.querySelector('[data-appearance-preview]');
    if (!form || !preview) return;

    const value = name => form.elements[name]?.value ?? '';
    const channel = hex => {
        const normalized = parseInt(hex, 16) / 255;
        return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
    };
    const luminance = color => {
        const hex = color.replace('#', '');
        return 0.2126 * channel(hex.slice(0, 2)) + 0.7152 * channel(hex.slice(2, 4)) + 0.0722 * channel(hex.slice(4, 6));
    };
    const contrast = (first, second) => {
        const a = luminance(first);
        const b = luminance(second);
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    };
    const isColor = color => /^#[0-9a-f]{6}$/i.test(color);
    const onColor = background => (contrast(background, '#ffffff') >= contrast(background, '#000000') ? '#ffffff' : '#000000');
    const eyebrow = preview.querySelector('[data-preview-eyebrow]');
    const title = preview.querySelector('[data-preview-title]');
    const cta = preview.querySelector('[data-preview-cta]');
    const warning = preview.querySelector('[data-preview-warning]');
    const labels = {ink: 'A fő szövegszín', muted: 'A másodlagos szöveg'};

    const render = () => {
        const colors = {
            accent: value('accent'), paper: value('appearance[paper]'), surface: value('appearance[surface]'),
            ink: value('appearance[ink]'), muted: value('appearance[muted]'), line: value('appearance[line]'),
        };
        if (!Object.values(colors).every(isColor)) return;
        preview.style.setProperty('--preview-accent', colors.accent);
        preview.style.setProperty('--preview-on-accent', onColor(colors.accent));
        preview.style.setProperty('--preview-paper', colors.paper);
        preview.style.setProperty('--preview-surface', colors.surface);
        preview.style.setProperty('--preview-ink', colors.ink);
        preview.style.setProperty('--preview-muted', colors.muted);
        preview.style.setProperty('--preview-line', colors.line);
        preview.style.setProperty('--preview-radius', `${Number(value('appearance[radius]')) || 8}px`);
        preview.style.setProperty('--preview-font', value('font') === 'sans' ? "'DM Sans', Arial, sans-serif" : "'Playfair Display', Georgia, serif");
        if (eyebrow) eyebrow.textContent = value('appearance[eyebrow]');
        if (cta) cta.textContent = value('appearance[cta_label]') || 'Időpontot foglalok';
        if (title && form.elements.tagline) title.textContent = form.elements.tagline.value.split('\n')[0].trim();

        const problems = ['ink', 'muted'].filter(key => [colors.paper, colors.surface].some(background => contrast(colors[key], background) < 4.5));
        if (warning) {
            warning.hidden = problems.length === 0;
            warning.textContent = problems.length
                ? `${problems.map(key => labels[key]).join(' és ')} kontrasztja a háttérhez még nem éri el a 4,5:1 arányt, ezért a mentés elutasítja. Válassz sötétebb szöveget vagy világosabb hátteret.`
                : '';
        }
    };

    form.addEventListener('input', render);
    form.addEventListener('change', render);
    render();
})();
