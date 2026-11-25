const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

if (document.querySelector('[data-bs-toggle], [data-bs-dismiss]')) {
    import('bootstrap/dist/js/bootstrap.bundle.min.js');
}

const charts = document.querySelectorAll('canvas[data-chart]');
if (charts.length) {
    import('chart.js/auto').then(({ default: Chart }) => {
        charts.forEach((canvas) => {
            const raw = canvas.getAttribute('data-chart');
            if (!raw) {
                return;
            }
            new Chart(canvas, JSON.parse(raw));
        });
    });
}

document.querySelectorAll('[data-theme-toggle]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('[name="theme"]');
        const next = input?.value === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', next);
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        if (!response.ok) {
            window.location.reload();
            return;
        }
        if (input) {
            input.value = next === 'dark' ? 'light' : 'dark';
        }
        const button = form.querySelector('button');
        if (button) {
            button.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', next === 'dark' ? button.dataset.labelLight : button.dataset.labelDark);
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
            }
        }
    });
});

document.querySelectorAll('form[data-autosave]').forEach((form) => {
    const status = form.querySelector('[data-autosave-status]');
    let timer = null;
    let saving = false;
    let dirty = false;

    const save = async () => {
        if (saving) {
            dirty = true;
            return;
        }
        saving = true;
        dirty = false;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                body: new FormData(form),
            });
            if (response.status === 422) {
                const payload = await response.json();
                const first = Object.values(payload.errors || {})[0];
                if (status) {
                    status.textContent = Array.isArray(first) ? first[0] : '';
                }
                return;
            }
            if (!response.ok) {
                if (status) {
                    status.textContent = status.dataset.failed || '';
                }
                return;
            }
            const payload = await response.json();
            if (payload.update_url && form.action !== payload.update_url) {
                form.action = payload.update_url;
                if (!form.querySelector('[name="_method"]')) {
                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'PUT';
                    form.appendChild(method);
                }
                if (payload.edit_url) {
                    window.history.replaceState({}, '', payload.edit_url);
                }
            }
            if (status) {
                status.textContent = payload.message || '';
            }
        } catch (error) {
            if (status) {
                status.textContent = status.dataset.failed || '';
            }
        } finally {
            saving = false;
            if (dirty) {
                save();
            }
        }
    };

    form.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(save, 1200);
    });
});

const bell = document.querySelector('#notification-bell');
if (bell) {
    const list = bell.querySelector('[data-notification-list]');
    const count = bell.querySelector('[data-notification-count]');
    const paint = (payload) => {
        if (!list || !count) {
            return;
        }
        count.textContent = String(payload.count || 0);
        count.classList.toggle('d-none', !payload.count);
        list.replaceChildren();
        if (!payload.items || payload.items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-2 py-1 text-secondary small';
            empty.textContent = bell.dataset.empty || '';
            list.appendChild(empty);
            return;
        }
        payload.items.forEach((item) => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = item.read_url;
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = bell.dataset.token || token;
            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'dropdown-item text-wrap';
            const title = document.createElement('strong');
            title.textContent = item.title || '';
            const body = document.createElement('div');
            body.className = 'small text-secondary';
            body.textContent = item.body || '';
            button.append(title, body);
            form.append(csrf, button);
            list.appendChild(form);
        });
    };
    const refresh = async () => {
        if (document.hidden || !bell.dataset.feedUrl) {
            return;
        }
        try {
            const response = await fetch(bell.dataset.feedUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) {
                paint(await response.json());
            }
        } catch (error) {
            // The next poll retries. The page stays usable.
        }
    };
    window.setInterval(refresh, 20000);
    bell.querySelector('[data-bs-toggle="dropdown"]')?.addEventListener('show.bs.dropdown', refresh);
}

const searchForm = document.querySelector('[data-search]');
const searchInput = document.querySelector('#quick-search');
const searchPanel = document.querySelector('#quick-search-panel');
if (searchForm && searchInput && searchPanel) {
    let timer = null;
    const hide = () => searchPanel.classList.add('d-none');
    const showResults = (payload) => {
        searchPanel.replaceChildren();
        const groups = payload.groups || [];
        if (groups.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'search-empty';
            empty.textContent = searchInput.dataset.empty || '';
            searchPanel.appendChild(empty);
        }
        groups.forEach((group) => {
            const label = document.createElement('div');
            label.className = 'search-label';
            label.textContent = group.label;
            searchPanel.appendChild(label);
            (group.items || []).forEach((item) => {
                const link = document.createElement('a');
                link.href = item.url;
                link.textContent = item.title;
                searchPanel.appendChild(link);
            });
        });
        searchPanel.classList.remove('d-none');
    };
    searchInput.addEventListener('input', () => {
        window.clearTimeout(timer);
        const query = searchInput.value.trim();
        if (query.length < 2) {
            hide();
            return;
        }
        timer = window.setTimeout(async () => {
            const url = new URL(searchInput.dataset.searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) {
                showResults(await response.json());
            }
        }, 250);
    });
    document.addEventListener('click', (event) => {
        if (!searchForm.contains(event.target)) {
            hide();
        }
    });
}
