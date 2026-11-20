document.addEventListener('DOMContentLoaded', () => {
    const syncButtons = [];
    const isDark = () => document.body.classList.contains('ea-dark-scheme');

    for (const menu of document.querySelectorAll('.dropdown-settings')) {
        const light = menu.querySelector('[data-ea-color-scheme="light"]');
        const dark = menu.querySelector('[data-ea-color-scheme="dark"]');
        if (!light || !dark) continue;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary admin-theme-toggle';
        const sync = () => {
            button.textContent = isDark() ? 'Светлая тема' : 'Тёмная тема';
        };
        button.addEventListener('click', () => {
            (isDark() ? light : dark).click();
            sync();
        });
        menu.before(button);
        syncButtons.push(sync);
        sync();
    }

    if (syncButtons.length) {
        new MutationObserver(() => syncButtons.forEach(sync => sync()))
            .observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }
});
