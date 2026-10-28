document.querySelectorAll('.admin-settings input[type="file"]').forEach(input => {
    const label = input.closest('label');
    if (!label) return;
    const fieldName = label.textContent.trim().replace(/\s+/g, ' ');
    const control = document.createElement('span');
    control.className = 'upload-control';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button secondary small';
    button.textContent = input.multiple ? 'Képek kiválasztása' : 'Kép kiválasztása';
    button.setAttribute('aria-label', `${fieldName}: ${button.textContent.toLowerCase()}`);
    const status = document.createElement('span');
    status.className = 'upload-control-status';
    status.setAttribute('aria-live', 'polite');
    status.textContent = 'Nincs kiválasztott kép.';
    control.append(button, status);
    input.before(control);
    input.classList.add('upload-control-native');
    input.setAttribute('aria-hidden', 'true');
    input.tabIndex = -1;
    button.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const files = Array.from(input.files || []);
        status.textContent = files.length ? files.map(file => file.name).join(', ') : 'Nincs kiválasztott kép.';
    });
});
