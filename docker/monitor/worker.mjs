async function probe(url, health = false) {
    try {
        const response = await fetch(url, { signal: AbortSignal.timeout(8000), cache: 'no-store' });
        if (health && response.status === 200) {
            return (await response.json()).status === 'ok';
        }
        await response.body?.cancel();
        return response.status === 200;
    } catch {
        return false;
    }
}

async function notify(env, message) {
    try {
        const reply = await fetch(`https://api.telegram.org/bot${env.BOT_TOKEN}/sendMessage`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chat_id: env.CHAT_ID, text: message }),
            signal: AbortSignal.timeout(8000),
        });
        return reply.ok && (await reply.json()).ok === true;
    } catch {
        return false;
    }
}

async function report(base, token, status) {
    try {
        const response = await fetch(base + '/healthz/report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
            body: JSON.stringify(status),
            redirect: 'manual',
            signal: AbortSignal.timeout(8000),
        });
        await response.body?.cancel();
        return response.status === 204;
    } catch {
        return false;
    }
}

export default {
    async scheduled(_event, env) {
        const base = env.SITE_URL.replace(/\/$/, '');
        const site = await probe(base + '/') && await probe(base + '/healthz', true);
        const importHealthy = site ? await probe(base + '/healthz/import', true) : null;
        const checks = [['site', 'Сайт', site]];
        if (importHealthy !== null) checks.push(['import', 'Импорт событий', importHealthy]);
        const state = await env.MONITOR_STATE.get('afisha-status', { type: 'json' }) ?? {};
        let deliveryFailed = false;

        for (const [key, label, healthy] of checks) {
            const item = state[key] ??= { failures: 0, alerted: false };
            if (key === 'site' && !healthy) item.lastFailureAt = Math.floor(Date.now() / 1000);
            item.failures = healthy ? 0 : Math.min(item.failures + 1, 2);
            if ((!healthy && item.failures === 2 && !item.alerted) || (healthy && item.alerted)) {
                const status = healthy ? 'работа восстановлена' : 'сбой';
                if (await notify(env, `Афиша РБ: ${label} — ${status}.\n${base}`)) item.alerted = !healthy;
                else deliveryFailed = true;
            }
        }

        await env.MONITOR_STATE.put('afisha-status', JSON.stringify(state));
        if (env.AFISHA_MONITOR_REPORT_TOKEN) {
            const delivered = await report(base, env.AFISHA_MONITOR_REPORT_TOKEN, {
                siteHealthy: site,
                importHealthy,
                lastSiteFailureAt: state.site.lastFailureAt ?? null,
            });
            if (!delivered && site) console.error('Не удалось передать отчёт в админку');
        }
        if (deliveryFailed) throw new Error('Не удалось доставить уведомление Telegram');
    },
};
