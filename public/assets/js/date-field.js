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

    // ---- Field -----------------------------------------------------------

    function DateField(root) {
        this.root = root;
        this.system = root.getAttribute('data-system') === 'bs' && CAL ? 'bs' : 'ad';
        this.display = root.querySelector('[data-date-display]');
        this.hidden = root.querySelector('[data-date-value]');
        this.toggle = root.querySelector('[data-date-toggle]');
        this.mirror = root.parentNode && root.parentNode.querySelector('[data-date-mirror]');
        this.panel = null;

        this.bind();
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

            if (!self.root.contains(e.target)) self.close();
        });
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
        var ad = this.hidden.value || new Date().toISOString().slice(0, 10);

        if (this.system === 'bs') {
            var bs = adToBs(ad) || adToBs(new Date().toISOString().slice(0, 10));
            return bs ? { y: bs.y, m: bs.m } : { y: CAL.minBsYear, m: 1 };
        }

        var p = parseParts(ad);
        return { y: p.y, m: p.m };
    };

    DateField.prototype.open = function () {
        if (this.panel) return;

        this.view = this.cursor();
        this.panel = document.createElement('div');
        this.panel.className = 'date-field__panel';
        this.root.appendChild(this.panel);
        this.draw();
    };

    DateField.prototype.close = function () {
        if (!this.panel) return;
        this.panel.remove();
        this.panel = null;
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

    DateField.prototype.draw = function () {
        var self = this;
        var sys = this.system;
        var y = this.view.y, m = this.view.m;
        var selected = this.hidden.value;
        var todayAd = new Date().toISOString().slice(0, 10);

        var html = '<div class="date-field__head">'
            + '<button type="button" class="date-field__nav" data-prev aria-label="Previous month">&#8249;</button>'
            + '<span class="date-field__title">' + monthName(sys, m) + ' ' + y + '</span>'
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
            if (ad === todayAd) cls += ' is-today';
            html += '<button type="button" class="' + cls + '" data-ad="' + ad + '">' + d + '</button>';
        }

        html += '</div><div class="date-field__foot">'
            + '<button type="button" class="date-field__link" data-today>' + (sys === 'bs' ? 'Aaja' : 'Today') + '</button>'
            + '<button type="button" class="date-field__link" data-clear>Clear</button>'
            + '</div>';

        this.panel.innerHTML = html;

        this.panel.querySelector('[data-prev]').onclick = function () { self.shiftMonth(-1); };
        this.panel.querySelector('[data-next]').onclick = function () { self.shiftMonth(1); };
        this.panel.querySelector('[data-today]').onclick = function () {
            self.setValue(todayAd); self.close();
        };
        this.panel.querySelector('[data-clear]').onclick = function () {
            self.setValue(''); self.close();
        };

        Array.prototype.forEach.call(this.panel.querySelectorAll('[data-ad]'), function (btn) {
            btn.onclick = function () { self.setValue(btn.getAttribute('data-ad')); self.close(); };
        });
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
