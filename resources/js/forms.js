const initialized = new WeakSet();

export function initializeForms(scope = document) {
    const forms = [...(scope.matches?.('[data-sb-enquiry-form]') ? [scope] : []), ...scope.querySelectorAll('[data-sb-enquiry-form]')];
    for (const form of forms) {
        if (initialized.has(form)) continue;
        initialized.add(form);
        let sending = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (sending || !form.reportValidity()) return;
            sending = true;
            const button = form.querySelector('[type="submit"]');
            const errors = form.querySelector('[data-sb-form-errors]');
            button.disabled = true;
            form.setAttribute('aria-busy', 'true');
            errors.hidden = true;
            form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const result = await response.json();
                if (response.ok && result.accepted) {
                    const message = document.createElement('p');
                    message.setAttribute('role', 'status');
                    message.tabIndex = -1;
                    message.textContent = result.message;
                    form.replaceWith(message);
                    message.focus();
                    return;
                }
                const messages = Object.values(result.errors || {}).flat();
                errors.textContent = messages.length ? messages.join(' ') : result.message || 'The enquiry could not be sent. Your entries are retained; reload the page if the form has changed.';
                errors.hidden = false;
                for (const key of Object.keys(result.errors || {})) {
                    const name = key.startsWith('fields.') ? `fields[${key.slice(7)}]` : key;
                    const input = form.elements.namedItem(name);
                    input?.setAttribute?.('aria-invalid', 'true');
                }
                const target = form.querySelector('[aria-invalid="true"]') || errors;
                if (target === errors) target.tabIndex = -1;
                target.focus();
            } catch {
                errors.textContent = 'The connection was interrupted. Your entries are retained. Try sending again.';
                errors.hidden = false;
                errors.tabIndex = -1;
                errors.focus();
            } finally {
                sending = false;
                button.disabled = false;
                form.removeAttribute('aria-busy');
            }
        });
    }
}

if (typeof document !== 'undefined') {
    initializeForms();
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) initializeForms(node); }))).observe(document.documentElement, { childList: true, subtree: true });
}
