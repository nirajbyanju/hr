/**
 * Calendar picker for <x-date-field>, supporting Bikram Sambat and Gregorian.
 *
 * Two rules drive the whole design:
 *
 *  1. The hidden input always holds an AD "YYYY-MM-DD". The visible input is a
 *     display mirror. Nothing server-side needs to know which calendar is on.
 *  2. BS<->AD conversion uses the month-length table emitted by PHP from the
 *     laravel-nepali-date package (window.__NEPALI_CALENDAR__), so the browser
 *     and the server can never disagree about how long a Nepali month is.
 *
 * No jQuery: the existing bootstrap-datepicker is initialised in 13 different
 * inline blocks with inconsistent options, and replacing that with another
 * jQuery plugin would keep the problem. This binds every field once, from here.
 *
 * Optional per-field behaviour, set as data attributes by the component:
 *
 *   data-min-from="start_date"  this field cannot precede that field's value
 *   data-max-from="end_date"    ...and cannot follow that one's
 *   data-presets='[{"label":"1 year","months":12}]'   quick-fill chips
 *
 * Both bindings resolve inside the field's own <form>, because index screens
 * routinely carry a filter form and a create form using the same field names.
 */
(function () {
    'use strict';

    var CAL = window.__NEPALI_CALENDAR__ || null;
    var AD_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    var WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    var MS_PER_DAY = 86400000;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }

    function parseParts(value) {
        var m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec((value || '').trim());
        return m ? { y: +m[1], m: +m[2], d: +m[3] } : null;
    }

    /** UTC avoids the DST edge where adding days can land on the same date twice. */
    function utc(y, m, d) { return Date.UTC(y, m - 1, d); }

    function todayAd() {
        var now = new Date();
        return fmt(now.getFullYear(), now.getMonth() + 1, now.getDate());
    }

    /** Shift an AD date by whole days, staying in AD. */
    function addDays(adString, count) {
        var p = parseParts(adString);
        if (!p) return null;

        var t = new Date(utc(p.y, p.m, p.d) + count * MS_PER_DAY);
        return fmt(t.getUTCFullYear(), t.getUTCMonth() + 1, t.getUTCDate());
    }

    /** Clamped, so 31 Jan + 1 month is 28 Feb rather than 3 March. */
    function addAdMonths(adString, count) {
        var p = parseParts(adString);
        if (!p) return null;

        var y = p.y + Math.floor((p.m - 1 + count) / 12);
        var m = ((p.m - 1 + count) % 12 + 12) % 12 + 1;

        return fmt(y, m, Math.min(p.d, new Date(Date.UTC(y, m, 0)).getUTCDate()));
    }

    function escapeHtml(text) {
        var box = document.createElement('span');
        box.textContent = text;
        return box.innerHTML;
    }

    // ---- BS <-> AD -------------------------------------------------------
    // Both directions count days from a single known correspondence
    // (1 Baisakh of the table's first year == CAL.refAd) rather than
    // reimplementing a conversion algorithm.

    function bsDaysFromEpoch(y, m, d) {
        if (!CAL || y < CAL.minBsYear || y > CAL.maxBsYear) return null;

        var days = 0;
        for (var year = CAL.minBsYear; year < y; year++) {
            var row = CAL.monthDays[year];
            if (!row) return null;
            for (var i = 0; i < 12; i++) days += row[i];
        }

        var current = CAL.monthDays[y];
        if (!current) return null;
        for (var mo = 1; mo < m; mo++) days += current[mo - 1];

        return days + (d - 1);
    }

    function bsToAd(y, m, d) {
        var offset = bsDaysFromEpoch(y, m, d);
        if (offset === null) return null;

        var ref = parseParts(CAL.refAd);
        if (!ref) return null;

        var t = new Date(utc(ref.y, ref.m, ref.d) + offset * MS_PER_DAY);
        return fmt(t.getUTCFullYear(), t.getUTCMonth() + 1, t.getUTCDate());
    }

    function adToBs(adString) {
        var ad = parseParts(adString);
        var ref = CAL && parseParts(CAL.refAd);
        if (!ad || !ref) return null;

        var offset = Math.round((utc(ad.y, ad.m, ad.d) - utc(ref.y, ref.m, ref.d)) / MS_PER_DAY);
        if (offset < 0) return null;

        for (var year = CAL.minBsYear; year <= CAL.maxBsYear; year++) {
            var row = CAL.monthDays[year];
            if (!row) return null;

            for (var mo = 0; mo < 12; mo++) {
                if (offset < row[mo]) return { y: year, m: mo + 1, d: offset + 1 };
                offset -= row[mo];
            }
        }

        return null;
    }

    function daysInMonth(system, y, m) {
        if (system === 'bs') {
            var row = CAL && CAL.monthDays[y];
            return row ? row[m - 1] : 30;
        }
        return new Date(Date.UTC(y, m, 0)).getUTCDate();
    }

    /** Weekday (0=Sun) of the 1st of the given month, in either calendar. */
    function firstWeekday(system, y, m) {
        var adFirst = system === 'bs' ? bsToAd(y, m, 1) : fmt(y, m, 1);
        var p = parseParts(adFirst);
        return p ? new Date(utc(p.y, p.m, p.d)).getUTCDay() : 0;
    }

    function monthName(system, m) {
        return system === 'bs'
            ? (CAL && CAL.months[m - 1]) || m
            : AD_MONTHS[m - 1];
    }

    /** The {y, m} a given AD date falls on, in the field's own calendar. */
    function viewOf(system, adString) {
        if (system === 'bs') {
            var bs = adToBs(adString);
            return bs ? { y: bs.y, m: bs.m } : null;
        }

        var p = parseParts(adString);
        return p ? { y: p.y, m: p.m } : null;
    }

    // ---- Field -----------------------------------------------------------

    function DateField(root) {
        this.root = root;
        this.system = root.getAttribute('data-system') === 'bs' && CAL ? 'bs' : 'ad';
        this.display = root.querySelector('[data-date-display]');
        this.hidden = root.querySelector('[data-date-value]');
        this.toggle = root.querySelector('[data-date-toggle]');
        this.mirror = root.parentNode && root.parentNode.querySelector('[data-date-mirror]');
        this.form = root.closest('form');
        this.panel = null;

        try {
            this.presets = JSON.parse(root.getAttribute('data-presets') || '[]');
        } catch (error) {
            this.presets = [];
        }

        this.bind();

        // Sync the display from the canonical value. A no-op for server-rendered
        // fields, but it is what makes a field cloned from a <template> (dynamic
        // rows) show its date — and show it in the right calendar.
        this.render();
    }

    DateField.prototype.bind = function () {
        var self = this;

        this.display.addEventListener('focus', function () { self.open(); });
        this.display.addEventListener('click', function () { self.open(); });
        if (this.toggle) {
            this.toggle.addEventListener('click', function () {
                self.panel ? self.close() : (self.display.focus(), self.open());
            });
        }

        // Typing stays allowed — a picker that forces mouse use is slower for
        // anyone entering a birth date decades in the past.
        this.display.addEventListener('change', function () { self.commitTyped(); });

        this.display.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { self.close(); }
            if (e.key === 'Enter' && self.panel) { e.preventDefault(); self.commitTyped(); self.close(); }
        });

        document.addEventListener('click', function (e) {
            if (!self.panel) return;

            // draw() replaces the panel's innerHTML, so a click on prev/next
            // destroys its own button before this handler runs. The detached
            // node is no longer inside root, which would read as an
            // outside-click and close the picker mid-navigation.
            if (!e.target.isConnected) return;

            if (!self.root.contains(e.target) && !self.panel.contains(e.target)) self.close();
        });

        // A bound field re-reads its limit every time it opens, so editing the
        // other end of the range takes effect without rebinding anything.
        ['data-min-from', 'data-max-from'].forEach(function (attribute) {
            var other = self.boundField(attribute);
            if (!other) return;

            other.addEventListener('change', function () { if (self.panel) self.draw(); });
        });
    };

    /**
     * The hidden input this field's range is limited by, if any. Null when the
     * limit is a literal ("today" / an AD date) rather than another field.
     */
    DateField.prototype.boundField = function (attribute) {
        var name = this.root.getAttribute(attribute);
        if (!name || name === 'today' || parseParts(name)) return null;

        var scope = this.form || document;
        return scope.querySelector('[data-date-value][name="' + name + '"], input[name="' + name + '"]');
    };

    /**
     * The AD date bounding this field, from either a literal or another field.
     * Mirrors Laravel's after_or_equal, which accepts both forms too.
     */
    DateField.prototype.limit = function (attribute) {
        var raw = this.root.getAttribute(attribute);

        if (!raw) return null;
        if (raw === 'today') return todayAd();
        if (parseParts(raw)) return raw;

        var other = this.boundField(attribute);
        var value = other && other.value ? other.value.trim() : '';

        // AD "YYYY-MM-DD" sorts correctly as a plain string, so comparisons
        // downstream need no date parsing at all.
        return parseParts(value) ? value : null;
    };

    /** Reconcile a hand-typed display value into the canonical hidden field. */
    DateField.prototype.commitTyped = function () {
        var typed = this.display.value.trim();

        if (typed === '') { this.setValue(''); return; }

        var parts = parseParts(typed);
        if (!parts) { this.render(); return; }   // unparseable: snap back

        var ad = this.system === 'bs'
            ? bsToAd(parts.y, parts.m, parts.d)
            : fmt(parts.y, parts.m, parts.d);

        this.setValue(ad || '');
    };

    DateField.prototype.setValue = function (adString) {
        this.hidden.value = adString || '';
        this.render();
        this.hidden.dispatchEvent(new Event('change', { bubbles: true }));
    };

    DateField.prototype.render = function () {
        var ad = this.hidden.value;

        if (!ad) {
            this.display.value = '';
            if (this.mirror) this.mirror.textContent = '';
            return;
        }

        if (this.system === 'bs') {
            var bs = adToBs(ad);
            this.display.value = bs ? fmt(bs.y, bs.m, bs.d) : ad;
            if (this.mirror) this.mirror.textContent = 'A.D. ' + ad;
        } else {
            this.display.value = ad;
        }
    };

    /** Which month the grid should show when opened. */
    DateField.prototype.cursor = function () {
        var ad = this.hidden.value || this.limit('data-min-from') || todayAd();
        var view = viewOf(this.system, ad) || viewOf(this.system, todayAd());

        return view || { y: CAL ? CAL.minBsYear : 2000, m: 1 };
    };

    DateField.prototype.open = function () {
        if (this.panel) return;

        var self = this;

        this.view = this.cursor();

        // On <body> and fixed, because these fields sit inside cards, modals and
        // .table-responsive wrappers whose overflow would otherwise clip the panel.
        this.panel = document.createElement('div');
        this.panel.className = 'date-field__panel';
        document.body.appendChild(this.panel);

        this.draw();

        this.reposition = function () { self.place(); };
        window.addEventListener('resize', this.reposition);
        window.addEventListener('scroll', this.reposition, true);
    };

    DateField.prototype.close = function () {
        if (!this.panel) return;

        window.removeEventListener('resize', this.reposition);
        window.removeEventListener('scroll', this.reposition, true);

        this.panel.remove();
        this.panel = null;
    };

    DateField.prototype.place = function () {
        if (!this.panel) return;

        var box = this.display.getBoundingClientRect();
        var top = box.bottom + 6;

        // Flip above the field when there is no room beneath it.
        if (top + this.panel.offsetHeight > window.innerHeight - 8 && box.top - 6 - this.panel.offsetHeight > 8) {
            top = box.top - 6 - this.panel.offsetHeight;
        }

        this.panel.style.top = Math.max(8, top) + 'px';
        this.panel.style.left = Math.max(8, Math.min(box.left, window.innerWidth - this.panel.offsetWidth - 8)) + 'px';
    };

    DateField.prototype.shiftMonth = function (delta) {
        var m = this.view.m + delta;
        var y = this.view.y;

        if (m < 1) { m = 12; y--; }
        if (m > 12) { m = 1; y++; }

        if (this.system === 'bs' && (y < CAL.minBsYear || y > CAL.maxBsYear)) return;
        this.view = { y: y, m: m };
        this.draw();
    };

    /** Years offered in the header, wide enough to reach a birth date. */
    DateField.prototype.years = function () {
        if (this.system === 'bs') {
            return { first: CAL.minBsYear, last: CAL.maxBsYear };
        }

        var now = new Date().getFullYear();

        return {
            first: Math.min(this.view.y, now - 100),
            last: Math.max(this.view.y, now + 15)
        };
    };

    DateField.prototype.draw = function () {
        var self = this;
        var sys = this.system;
        var y = this.view.y, m = this.view.m;
        var selected = this.hidden.value;
        var today = todayAd();
        var min = this.limit('data-min-from');
        var max = this.limit('data-max-from');

        var months = '';
        for (var mo = 1; mo <= 12; mo++) {
            months += '<option value="' + mo + '"' + (mo === m ? ' selected' : '') + '>'
                + escapeHtml(String(monthName(sys, mo))) + '</option>';
        }

        var span = this.years();
        var years = '';
        for (var yr = span.first; yr <= span.last; yr++) {
            years += '<option value="' + yr + '"' + (yr === y ? ' selected' : '') + '>' + yr + '</option>';
        }

        var html = '<div class="date-field__head">'
            + '<button type="button" class="date-field__nav" data-prev aria-label="Previous month">&#8249;</button>'
            + '<select class="date-field__select" data-month aria-label="Month">' + months + '</select>'
            + '<select class="date-field__select" data-year aria-label="Year">' + years + '</select>'
            + '<button type="button" class="date-field__nav" data-next aria-label="Next month">&#8250;</button>'
            + '</div><div class="date-field__grid">';

        WEEKDAYS.forEach(function (d) { html += '<span class="date-field__dow">' + d + '</span>'; });

        var lead = firstWeekday(sys, y, m);
        for (var i = 0; i < lead; i++) html += '<span></span>';

        var total = daysInMonth(sys, y, m);
        for (var d = 1; d <= total; d++) {
            var ad = sys === 'bs' ? bsToAd(y, m, d) : fmt(y, m, d);
            var cls = 'date-field__day';
            if (ad === selected) cls += ' is-selected';
            if (ad === today) cls += ' is-today';

            var blocked = (min && ad < min) || (max && ad > max);

            html += '<button type="button" class="' + cls + '" data-ad="' + ad + '"'
                + (blocked ? ' disabled' : '') + '>' + d + '</button>';
        }

        html += '</div><div class="date-field__foot">';

        if (!(min && today < min) && !(max && today > max)) {
            html += '<button type="button" class="date-field__link" data-today>'
                + (sys === 'bs' ? 'Aaja' : 'Today') + '</button>';
        }

        this.presets.forEach(function (preset, index) {
            html += '<button type="button" class="date-field__chip" data-preset="' + index + '">'
                + escapeHtml(preset.label) + '</button>';
        });

        html += '<button type="button" class="date-field__link date-field__link--end" data-clear>Clear</button>'
            + '</div>';

        this.panel.innerHTML = html;
        this.place();

        this.panel.querySelector('[data-prev]').onclick = function () { self.shiftMonth(-1); };
        this.panel.querySelector('[data-next]').onclick = function () { self.shiftMonth(1); };

        var monthSelect = this.panel.querySelector('[data-month]');
        var yearSelect = this.panel.querySelector('[data-year]');
        var jump = function () {
            self.view = { y: +yearSelect.value, m: +monthSelect.value };
            self.draw();
        };
        monthSelect.onchange = jump;
        yearSelect.onchange = jump;

        var todayLink = this.panel.querySelector('[data-today]');
        if (todayLink) {
            todayLink.onclick = function () { self.setValue(today); self.close(); };
        }

        this.panel.querySelector('[data-clear]').onclick = function () {
            self.setValue(''); self.close();
        };

        Array.prototype.forEach.call(this.panel.querySelectorAll('[data-preset]'), function (chip) {
            chip.onclick = function () {
                // Counted from the date this field follows, so "1 year" on an
                // end date means a year from the start, not from today.
                var base = self.limit('data-min-from') || self.hidden.value || today;
                self.setValue(addAdMonths(base, self.presets[+chip.getAttribute('data-preset')].months));
                self.close();
            };
        });

        Array.prototype.forEach.call(this.panel.querySelectorAll('[data-ad]'), function (btn) {
            btn.onclick = function () { self.setValue(btn.getAttribute('data-ad')); self.close(); };
        });

        this.panel.onkeydown = function (e) { self.onKey(e); };
    };

    /** Arrow-key navigation across the grid, in whichever calendar is shown. */
    DateField.prototype.onKey = function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            this.display.focus();

            return;
        }

        var button = event.target.closest ? event.target.closest('[data-ad]') : null;
        var from = button && button.getAttribute('data-ad');

        if (!from) return;

        var steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        var next = null;

        if (Object.prototype.hasOwnProperty.call(steps, event.key)) {
            next = addDays(from, steps[event.key]);
        } else if (event.key === 'PageUp') {
            next = addAdMonths(from, -1);
        } else if (event.key === 'PageDown') {
            next = addAdMonths(from, 1);
        } else {
            return;
        }

        event.preventDefault();

        var view = viewOf(this.system, next);
        if (!view) return;

        if (view.y !== this.view.y || view.m !== this.view.m) {
            this.view = view;
            this.draw();
        }

        var target = this.panel.querySelector('[data-ad="' + next + '"]');
        if (target) target.focus();
    };

    function init(scope) {
        Array.prototype.forEach.call(
            (scope || document).querySelectorAll('[data-date-field]'),
            function (el) { if (!el.__dateField) el.__dateField = new DateField(el); }
        );
    }

    document.addEventListener('DOMContentLoaded', function () { init(document); });

    // Exposed so modals and AJAX-inserted forms can bind their own fields.
    window.initDateFields = init;
})();
