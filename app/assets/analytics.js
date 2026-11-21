const panel = document.querySelector('[data-analytics-container]');
const settings = document.querySelector('[data-analytics-settings]');
const accept = panel.querySelector('[data-analytics-accept]');
const decline = panel.querySelector('[data-analytics-decline]');
const storageKey = 'afisha-analytics-consent';
let choice;
let containerLoaded = false;

try {
    choice = localStorage.getItem(storageKey);
} catch {
    // Visitors can still choose for the current page when storage is unavailable.
}

function loadContainer() {
    if (containerLoaded) {
        return;
    }

    const consentDefaults = {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    };
    window.dataLayer ||= [];
    function gtag() {
        window.dataLayer.push(arguments);
    }
    gtag('consent', 'default', consentDefaults);
    gtag('consent', 'update', {analytics_storage: 'granted'});
    window.dataLayer.push({'gtm.start': Date.now(), event: 'gtm.js'});

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(panel.dataset.analyticsContainer);
    document.head.append(script);
    containerLoaded = true;
}

function saveChoice(value) {
    choice = value;
    try {
        localStorage.setItem(storageKey, choice);
    } catch {}
    panel.hidden = true;
    settings.focus();

    if (choice === 'granted') {
        loadContainer();
    } else if (containerLoaded) {
        window.location.reload();
    }
}

settings.hidden = false;
settings.addEventListener('click', () => {
    panel.hidden = false;
    accept.focus();
});
accept.addEventListener('click', () => saveChoice('granted'));
decline.addEventListener('click', () => saveChoice('denied'));

if (choice === 'granted') {
    loadContainer();
} else if (choice !== 'denied') {
    panel.hidden = false;
}

function trackTicketClick(event) {
    if (choice !== 'granted' || !containerLoaded) {
        return;
    }
    const link = event.target.closest('a[data-ticket-event-id]');
    if (link) {
        window.dataLayer.push({
            event: 'ticket_click',
            event_id: link.dataset.ticketEventId,
            ticket_placement: link.dataset.ticketPlacement,
        });
    }
}

document.addEventListener('click', trackTicketClick);
document.addEventListener('auxclick', event => {
    if (event.button === 1) {
        trackTicketClick(event);
    }
});
