{{-- Global command palette (Ctrl/⌘ + K).
     Additive, zero-backend: its index is built client-side from the already
     permission-filtered sidebar, so it can only surface links the user may see. --}}
<div id="cmdk" class="cmdk-overlay" role="dialog" aria-modal="true" aria-label="{{ __('Command palette') }}">
    <div class="cmdk-panel" role="document">
        <div class="cmdk-search">
            <i class="icon-magnifier" aria-hidden="true"></i>
            <input id="cmdk-input" class="cmdk-input" type="text" role="combobox" aria-expanded="true"
                   aria-controls="cmdk-list" aria-autocomplete="list"
                   placeholder="{{ __('Search pages — e.g. leave, payroll, employee') }}"
                   autocomplete="off" spellcheck="false" aria-label="{{ __('Search pages') }}">
            <kbd class="cmdk-kbd">Esc</kbd>
        </div>
        <ul id="cmdk-list" class="cmdk-list" role="listbox" aria-label="{{ __('Results') }}"></ul>
        <div id="cmdk-empty" class="cmdk-empty" hidden>{{ __('No matching pages') }}</div>
        <div class="cmdk-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> {{ __('navigate') }}</span>
            <span><kbd>↵</kbd> {{ __('open') }}</span>
            <span><kbd>Esc</kbd> {{ __('close') }}</span>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var overlay = document.getElementById('cmdk');
    if (!overlay) {
        return;
    }

    var input = document.getElementById('cmdk-input');
    var list = document.getElementById('cmdk-list');
    var emptyEl = document.getElementById('cmdk-empty');
    var triggers = document.querySelectorAll('#cmdk-trigger, .cmdk-trigger-mobile');
    var RECENT_KEY = 'samriddhi.cmdk.recent';

    var index = [];
    var results = [];
    var activeIdx = 0;
    var lastFocused = null;

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ---- Build the searchable index from the permission-filtered sidebar DOM.
    // Because the server already renders only the menu links this user can access,
    // the palette inherits that gating for free and stays in sync with the menu.
    function buildIndex() {
        var out = [];
        var seen = {};

        document.querySelectorAll('.sidebar-menu > li').forEach(function (li) {
            var topLink = li.querySelector(':scope > a');
            if (!topLink) {
                return;
            }

            var labelSpan = topLink.querySelector('span');
            var groupLabel = (labelSpan ? labelSpan.textContent : topLink.textContent).trim();
            var iconEl = topLink.querySelector('i');
            var icon = iconEl ? iconEl.className : 'icon-doc';
            var submenu = li.querySelector(':scope > ul');

            // A top-level item that is itself a real page (no submenu).
            var topHref = topLink.getAttribute('href');
            if (topHref && topHref !== '#' && !submenu) {
                add(out, seen, groupLabel, groupLabel, topHref, icon);
            }

            // Child pages inside a submenu.
            if (submenu) {
                submenu.querySelectorAll('a[href]').forEach(function (a) {
                    var href = a.getAttribute('href');
                    if (href && href !== '#') {
                        add(out, seen, a.textContent.trim(), groupLabel, href, icon);
                    }
                });
            }
        });

        return out;
    }

    function add(out, seen, label, group, href, icon) {
        if (!label || seen[href]) {
            return;
        }
        seen[href] = true;
        out.push({ label: label, group: group, href: href, icon: icon });
    }

    // ---- Recently visited (localStorage, capped, current page excluded on open).
    function getRecents() {
        try {
            return JSON.parse(localStorage.getItem(RECENT_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    function recordVisit() {
        try {
            var titleEl = document.querySelector('.page-title h1');
            var label = (titleEl ? titleEl.textContent : document.title).trim();
            var url = location.pathname + location.search;
            if (!label) {
                return;
            }
            var recents = getRecents().filter(function (r) { return r.url !== url; });
            recents.unshift({ label: label, url: url });
            localStorage.setItem(RECENT_KEY, JSON.stringify(recents.slice(0, 6)));
        } catch (e) { /* storage unavailable — non-fatal */ }
    }

    // ---- Filtering / ranking.
    function filter(query) {
        var q = query.trim().toLowerCase();

        if (q === '') {
            var here = location.pathname + location.search;
            var recentHrefs = getRecents()
                .filter(function (r) { return r.url !== here; })
                .map(function (r) {
                    var match = index.filter(function (it) { return it.href === r.url || it.href.indexOf(r.url) !== -1; })[0];
                    return match ? Object.assign({}, match, { recent: true }) : null;
                })
                .filter(Boolean);

            var recentSet = {};
            recentHrefs.forEach(function (it) { recentSet[it.href] = true; });
            var rest = index.filter(function (it) { return !recentSet[it.href]; });
            return recentHrefs.concat(rest);
        }

        var scored = [];
        index.forEach(function (it) {
            var label = it.label.toLowerCase();
            var group = it.group.toLowerCase();
            var score = -1;
            if (label.indexOf(q) === 0) {
                score = 0;
            } else if (label.indexOf(q) !== -1) {
                score = 1;
            } else if (group.indexOf(q) !== -1) {
                score = 2;
            }
            if (score !== -1) {
                scored.push({ it: it, score: score });
            }
        });
        scored.sort(function (a, b) { return a.score - b.score || a.it.label.localeCompare(b.it.label); });
        return scored.map(function (s) { return s.it; });
    }

    function render() {
        list.innerHTML = '';
        if (!results.length) {
            emptyEl.hidden = false;
            input.removeAttribute('aria-activedescendant');
            return;
        }
        emptyEl.hidden = true;

        results.forEach(function (item, i) {
            var li = document.createElement('li');
            li.className = 'cmdk-item' + (i === activeIdx ? ' is-active' : '');
            li.id = 'cmdk-opt-' + i;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', i === activeIdx ? 'true' : 'false');

            var groupHtml = (item.group && item.group !== item.label)
                ? '<span class="cmdk-item-group">' + escapeHtml(item.group) + '</span>' : '';
            var tagHtml = item.recent ? '<span class="cmdk-item-tag">' + @json(__('Recent')) + '</span>' : '';

            li.innerHTML =
                '<i class="' + escapeHtml(item.icon) + '" aria-hidden="true"></i>' +
                '<span class="cmdk-item-label">' + escapeHtml(item.label) + '</span>' +
                groupHtml + tagHtml;

            li.addEventListener('click', function () { go(item.href); });
            li.addEventListener('mousemove', function () { setActive(i); });
            list.appendChild(li);
        });

        input.setAttribute('aria-activedescendant', 'cmdk-opt-' + activeIdx);
    }

    function setActive(i) {
        if (i === activeIdx) {
            return;
        }
        activeIdx = i;
        Array.prototype.forEach.call(list.children, function (el, idx) {
            var on = idx === activeIdx;
            el.classList.toggle('is-active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', 'cmdk-opt-' + activeIdx);
    }

    function move(delta) {
        if (!results.length) {
            return;
        }
        var next = (activeIdx + delta + results.length) % results.length;
        setActive(next);
        var el = list.children[next];
        if (el) {
            el.scrollIntoView({ block: 'nearest' });
        }
    }

    function go(href) {
        if (href) {
            window.location.href = href;
        }
    }

    function refresh() {
        results = filter(input.value);
        activeIdx = 0;
        render();
    }

    function open() {
        if (overlay.classList.contains('cmdk-open')) {
            return;
        }
        if (!index.length) {
            index = buildIndex();
        }
        lastFocused = document.activeElement;
        overlay.classList.add('cmdk-open');
        document.body.classList.add('cmdk-lock');
        input.value = '';
        refresh();
        input.focus();
    }

    function close() {
        overlay.classList.remove('cmdk-open');
        document.body.classList.remove('cmdk-lock');
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function toggle() {
        overlay.classList.contains('cmdk-open') ? close() : open();
    }

    // ---- Wiring.
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            toggle();
            return;
        }
        if (!overlay.classList.contains('cmdk-open')) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            move(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            move(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (results[activeIdx]) {
                go(results[activeIdx].href);
            }
        }
    });

    input.addEventListener('input', refresh);

    overlay.addEventListener('mousedown', function (e) {
        if (e.target === overlay) {
            close();
        }
    });

    Array.prototype.forEach.call(triggers, function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            open();
        });
        // Show the platform-correct shortcut hint in the topbar trigger.
        var kbd = trigger.querySelector('kbd');
        if (kbd && /Mac|iPod|iPhone|iPad/.test(navigator.platform)) {
            kbd.textContent = '⌘K';
        }
    });

    recordVisit();
})();
</script>
@endpush
