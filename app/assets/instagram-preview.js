document.querySelectorAll('[data-instagram-preview]').forEach((preview) => {
    const template = preview.querySelector('template');
    const summary = preview.querySelector('summary');
    const load = () => {
        summary.textContent = preview.open ? 'Скрыть афишу' : 'Показать афишу';
        if (preview.open && template.isConnected) {
            template.replaceWith(template.content.cloneNode(true));
        }
    };

    preview.addEventListener('toggle', load);
    load();
});
