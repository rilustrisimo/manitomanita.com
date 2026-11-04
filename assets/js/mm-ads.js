/**
 * Advertiser account (/advertiser/): email-code sign-in, card builder with live preview, PayPal
 * subscription checkout, cancel / switch billing, waitlist. Server: classes/class-ads-portal.php.
 */
(function ($) {
    'use strict';

    var cfg = window.mmAds || {}, sdkLoading = null;

    function post(action, data) {
        var fd = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) $.each(data || {}, function (k, v) { fd.append(k, v); });
        fd.append('action', action);
        if (!fd.has('nonce')) fd.append('nonce', cfg.nonce);
        if (cfg.test) fd.append('test', '1');
        return $.ajax({ url: cfg.ajax, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' });
    }

    function say($box, text, bad) {
        $box.find('[data-mma-msg]').last().html(text || '').toggleClass('is-bad', !!bad);
    }

    function fail($box) {
        return function (r) {
            var m = r && r.responseJSON ? r.responseJSON.data : (r && r.data);
            say($box, (typeof m === 'string' && m) || 'Something went wrong. Please try again.', true);
        };
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    function peso(n) {
        return '\u20b1' + Number(n || 0).toLocaleString('en-PH');
    }

    /* ---------------------------------------------------------------- Sign in */

    function initLogin() {
        var $f = $('[data-mma-login]');
        if (!$f.length) return;
        $f.on('click', '[data-mma-send]', function () {
            var email = $.trim($f.find('[name=email]').val()), $b = $(this);
            if (!/^\S+@\S+\.\S+$/.test(email)) return say($f, 'Please enter a valid email address.', true);
            $b.prop('disabled', true).text('Sending\u2026');
            post('mm_send_otp', { email: email, purpose: 'advertise', nonce: cfg.otpNonce }).done(function (r) {
                if (!r.success) return fail($f)(r);
                $f.find('[data-mma-codebox], [data-mma-signin]').prop('hidden', false);
                $f.find('[name=code]').trigger('focus');
                say($f, r.data);
            }).fail(fail($f)).always(function () { $b.prop('disabled', false).text('Email me a new code'); });
        });
        $f.on('submit', function (e) {
            e.preventDefault();
            var $b = $f.find('[data-mma-signin]').prop('disabled', true);
            post('mm_ads_login', { email: $f.find('[name=email]').val(), code: $f.find('[name=code]').val() }).done(function (r) {
                if (!r.success) return fail($f)(r);
                location.reload();
            }).fail(fail($f)).always(function () { $b.prop('disabled', false); });
        });
    }

    /* ---------------------------------------------------------------- Account basics */

    function initAccount() {
        $(document).on('click', '[data-mma-logout]', function () {
            post('mm_ads_logout', {}).always(function () { location.reload(); });
        });
        $('[data-mma-profile]').on('submit', function (e) {
            e.preventDefault();
            var $f = $(this);
            post('mm_ads_profile', new FormData(this)).done(function (r) {
                if (!r.success) return fail($f)(r);
                say($f, 'Saved.');
                setTimeout(function () { $f.prop('hidden', true); }, 800);
            }).fail(fail($f));
        });
    }

    /* ---------------------------------------------------------------- Wizard (book / edit) */

    var $bld, logoUrl = '', step = 1, steps = [1, 2, 3], mode = 'new';

    function val(n) { return $.trim($bld.find('[name="' + n + '"]').val() || ''); }

    function card(c, email) {
        var h = '<aside class="mm-spon' + (email ? ' mm-spon--email' : '') + '"><span class="mm-spon__label">Sponsored</span><div class="mm-spon__body">';
        if (c.logo) h += '<img class="mm-spon__logo" src="' + esc(c.logo) + '" alt="" width="56" height="56">';
        h += '<div class="mm-spon__text"><span class="mm-spon__brand">' + esc(c.brand || 'Your brand') + '</span>';
        h += '<strong class="mm-spon__head">' + esc(c.headline || 'Your headline') + '</strong>';
        if (c.text) h += '<span class="mm-spon__copy">' + esc(c.text) + '</span>';
        if (c.promo) h += '<span class="mm-spon__promo">Use code <b>' + esc(c.promo) + '</b></span>';
        h += '</div></div><div class="mm-spon__actions"><span class="mm-spon__btn">' + esc(c.button || 'Shop now') + '</span>';
        if (!email && c.l1) h += '<span class="mm-spon__link">' + esc(c.l1) + '</span>';
        if (!email && c.l2) h += '<span class="mm-spon__link">' + esc(c.l2) + '</span>';
        return h + '</div></aside>';
    }

    function current() {
        return {
            brand: val('c[brand]'), headline: val('c[headline]'), text: val('c[text]'), button: val('c[button]'), promo: val('c[promo]'), logo: logoUrl,
            l1: val('c[links][1][1]') ? (val('c[links][1][0]') || 'More') : '', l2: val('c[links][2][1]') ? (val('c[links][2][0]') || 'More') : ''
        };
    }

    function preview() {
        var c = current(), email = $bld.find('[data-mma-frame]').attr('data-mma-frame') === 'email';
        $bld.find('[data-mma-preview]').html(card(c, email));
        $bld.find('[data-mma-preview2]').html(card(c, false));
        $bld.find('[data-mma-thumb]').html(logoUrl ? '<img src="' + esc(logoUrl) + '" alt="">' : 'Logo');
        $bld.find('[data-mma-count]').each(function () {
            var $c = $(this), $i = $c.closest('label').find('input');
            $c.text(($i.val() || '').length + '/' + $c.attr('data-mma-count'));
        });
    }

    function placement() { return $bld.find('[name=placement]:checked'); }

    var targetOpts = null; // every target option (gift guides and occasions), filtered per placement

    /** The chosen target's option when its seats are all taken, else null. */
    function targetFull() {
        var $o = $bld.find('[name=target] option:selected');
        return ($o.val() && $o.attr('data-left') === '0') ? $o : null;
    }

    function pickPlacement() {
        var $r = placement();
        $bld.find('.mma-opt').removeClass('is-on');
        if (!$r.length) return;
        var open = $r.attr('data-open') === '1', kind = $r.attr('data-target'), $sel = $bld.find('[name=target]');
        $r.closest('.mma-opt').addClass('is-on');
        if (!targetOpts) targetOpts = $sel.find('option[data-for]').detach();
        if (kind && $sel.attr('data-for') !== $r.val()) {
            var keep = $sel.val();
            $sel.find('option[data-for]').remove();
            $sel.append(targetOpts.filter('[data-for="' + $r.val() + '"]').clone()).attr('data-for', $r.val());
            $sel.val($sel.find('option[value="' + keep + '"]').length ? keep : '');
            $bld.find('[data-mma-target-q]').text(kind === 'occasion' ? 'Which registries should it appear on?' : 'Which gift guide should it appear on?');
        }
        if (!kind) $sel.val('');
        $bld.find('[data-mma-target]').prop('hidden', !kind || !open);
        $bld.find('[data-mma-waitbox]').prop('hidden', open && !targetFull());
        say($bld, '');
        nav();
    }

    function summary() {
        var $r = placement();
        if (!$r.length) return;
        var w = +$r.attr('data-weekly'), m = +$r.attr('data-monthly'), cads = ($r.attr('data-cadences') || 'weekly').split(',');
        var tgt = $bld.find('[name=target] option:selected');
        var rows = [
            ['Placement', esc($r.attr('data-label'))],
            ['Where', esc($r.attr('data-where')) + (tgt.val() ? ' &middot; ' + esc(tgt.text()) : '')],
            ['Runs', esc($r.attr('data-season'))],
            ['Seats', esc($r.attr('data-seats'))]
        ];
        $bld.find('[data-mma-summary]').html(rows.map(function (x) { return '<div><dt>' + x[0] + '</dt><dd>' + x[1] + '</dd></div>'; }).join(''));
        $bld.find('[data-mma-price="weekly"]').html(esc(peso(w)) + '<span> / week</span>');
        $bld.find('[data-mma-price="monthly"]').html(esc(peso(m)) + '<span> / month</span>');
        var save = w ? Math.round((1 - m / (w * 52 / 12)) * 100) : 0;
        $bld.find('[data-mma-save-note]').text(cads.indexOf('monthly') < 0 ? 'Not available this close to Dec 20' : (save > 0 ? 'Save about ' + save + '%' : 'Billed once a month'));
        var $m = $bld.find('[name=cadence][value=monthly]');
        $m.prop('disabled', cads.indexOf('monthly') < 0).closest('.mma-cad__opt').toggleClass('is-off', cads.indexOf('monthly') < 0);
        if ($m.is(':disabled')) $bld.find('[name=cadence][value=weekly]').prop('checked', true);
    }

    /** Can we leave the current step? Returns an error message or ''. */
    function check(n) {
        if (n === 1) {
            var $r = placement();
            if (!$r.length) return 'Choose a placement.';
            if ($r.attr('data-open') !== '1') return 'This placement is full right now. Join the waitlist or choose another.';
            if ($r.attr('data-target') && !$bld.find('[name=target]').val()) return $r.attr('data-target') === 'occasion' ? 'Choose which registries your ad should appear on.' : 'Choose which gift guide your ad should appear on.';
            if (targetFull()) return 'All seats there are taken right now. Join the waitlist or choose another.';
        }
        if (n === 2) {
            if (!val('c[brand]')) return 'Add your brand name.';
            if (!val('c[headline]')) return 'Add a headline.';
            if (!/^https:\/\/\S+\.\S+/i.test(val('c[links][0][1]'))) return 'Add the link people should open. It must start with https://';
            if (!$.trim(val('c[button]'))) $bld.find('[name="c[button]"]').val('Shop now');
        }
        return '';
    }

    function nav() {
        var i = steps.indexOf(step), last = i === steps.length - 1;
        $bld.find('[data-step]').each(function () { $(this).prop('hidden', +$(this).attr('data-step') !== step); });
        $bld.find('.mma-progress li').each(function () {
            var n = +$(this).attr('data-tab');
            $(this).prop('hidden', steps.indexOf(n) < 0).toggleClass('is-on', n === step).toggleClass('is-done', steps.indexOf(n) > -1 && steps.indexOf(n) < i);
        });
        $bld.find('.mma-progress').prop('hidden', steps.length < 2);
        $bld.find('[data-mma-back]').prop('hidden', i <= 0);
        $bld.find('[data-mma-next]').prop('hidden', last).text(step === 1 ? 'Next: your card' : 'Next: review and pay');
        var $save = $bld.find('[data-mma-save]').prop('hidden', !last);
        if (mode === 'edit') $save.text('Save changes').prop('disabled', false);
        else $save.text('Continue to payment').prop('disabled', !$bld.find('[data-mma-agree]').is(':checked'));
        if (step === 1) $bld.find('[data-mma-next]').prop('disabled', !placement().length);
        else $bld.find('[data-mma-next]').prop('disabled', false);
        if (step === 3) summary();
        preview();
    }

    function go(n) {
        step = n;
        nav();
        $bld[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function fill(c) {
        var set = function (n, v) { $bld.find('[name="' + n + '"]').val(v || ''); };
        set('c[brand]', c.brand); set('c[headline]', c.headline); set('c[text]', c.text); set('c[button]', c.button || 'Shop now'); set('c[promo]', c.promo);
        for (var i = 0; i < 3; i++) {
            var l = (c.links || [])[i] || ['', ''];
            if (i) set('c[links][' + i + '][0]', l[0]);
            set('c[links][' + i + '][1]', l[1]);
        }
        var b = c.b || {};
        set('c[b][headline]', b.headline); set('c[b][text]', b.text); set('c[b][button]', b.button);
        if (b.headline || c.promo || (c.links || []).length > 1) $bld.find('.mma-more').prop('open', true);
        logoUrl = c.logo || '';
        $bld.find('[name=logo]').val('');
    }

    /** Open the wizard: 'new' (all steps), 'book' an unpaid ad (card + pay), 'edit' a booked ad's card. */
    function open(m, $ad) {
        mode = m;
        $bld.attr('data-mode', m).prop('hidden', false);
        $bld.find('[data-mma-pay]').prop('hidden', true).empty();
        $bld.find('[data-mma-agree]').prop('checked', false);
        say($bld, '');
        if ($ad) {
            fill(JSON.parse($ad.attr('data-c') || '{}'));
            $bld.find('[name=ad]').val($ad.attr('data-mma-ad'));
            $bld.find('[name=placement][value="' + $ad.attr('data-slot') + '"]').prop('checked', true);
        } else {
            $bld[0].reset();
            $bld.find('[name=ad]').val('');
            logoUrl = '';
            pickPlacement();
        }
        steps = m === 'new' ? [1, 2, 3] : (m === 'book' ? [2, 3] : [2]);
        $bld.find('[data-mma-wtitle]').text(m === 'new' ? 'Book a placement' : (m === 'book' ? 'Book this ad' : 'Edit your card'));
        $('.mma-booknew').prop('hidden', true);
        go(m === 'book' ? 3 : steps[0]);
    }

    function initBuilder() {
        $bld = $('[data-mma-builder]');
        if (!$bld.length) return;
        $bld.on('input change', 'input, select', function (e) {
            if (e.target.name === 'placement' || e.target.name === 'target') return pickPlacement();
            if (e.target.hasAttribute('data-mma-agree')) return nav();
            preview();
        });
        $bld.on('change', '[name=logo]', function () {
            var f = this.files && this.files[0];
            if (f && f.size > 1048576) { say($bld, 'Please use a logo under 1 MB.', true); this.value = ''; return; }
            logoUrl = f ? URL.createObjectURL(f) : logoUrl;
            preview();
        });
        $bld.on('click', '[data-mma-view]', function () {
            var v = $(this).attr('data-mma-view');
            $bld.find('[data-mma-view]').removeClass('is-on').attr('aria-selected', 'false');
            $(this).addClass('is-on').attr('aria-selected', 'true');
            $bld.find('[data-mma-frame]').attr('data-mma-frame', v);
            preview();
        });
        $bld.on('click', '[data-mma-next]', function () {
            var err = check(step);
            if (err) return say($bld, err, true);
            say($bld, '');
            go(steps[steps.indexOf(step) + 1]);
        });
        $bld.on('click', '[data-mma-back]', function () { say($bld, ''); go(steps[steps.indexOf(step) - 1]); });
        $bld.on('click', '[data-mma-close]', function () {
            $bld.prop('hidden', true);
            $('.mma-booknew').prop('hidden', false);
        });
        $('[data-mma-new]').on('click', function () { open('new'); });
        $bld.on('click', '[data-mma-wait]', function () {
            var $b = $(this).prop('disabled', true);
            post('mm_ads_wait', { placement: placement().val(), target: $bld.find('[name=target]').val() || '' })
                .done(function (r) { r.success ? say($bld, r.data.message) : fail($bld)(r); })
                .fail(fail($bld)).always(function () { $b.prop('disabled', false); });
        });
        $bld.on('submit', function (e) {
            e.preventDefault();
            for (var k = 0; k < steps.length; k++) {
                var err = check(steps[k]);
                if (err && !(mode !== 'new' && steps[k] === 1)) { go(steps[k]); return say($bld, err, true); }
            }
            var $b = $bld.find('[data-mma-save]').prop('disabled', true).text('Saving\u2026');
            if (mode !== 'edit') $bld.find('[data-mma-pay]').prop('hidden', false).html(loader('Saving your ad\u2026'));
            post('mm_ads_save', new FormData(this)).done(function (r) {
                if (!r.success) { $bld.find('[data-mma-pay]').prop('hidden', true).empty(); return fail($bld)(r); }
                $bld.find('[name=ad]').val(r.data.ad);
                if (mode === 'edit') {
                    say($bld, r.data.message);
                    setTimeout(function () { location.reload(); }, 1500);
                    return;
                }
                say($bld, '');
                checkout(r.data.ad, $bld.find('[name=cadence]:checked').val() || 'weekly', $bld);
            }).fail(fail($bld)).always(function () { if (mode === 'edit') nav(); else $b.prop('disabled', false).text('Continue to payment'); });
        });
        if (!$bld.prop('hidden')) open('new');
    }

    /* ---------------------------------------------------------------- Checkout */

    /**
     * Our own PayPal SDK under its own namespace: the site may already load PayPal (PRO checkout) with the
     * live client and a one-time-payment intent, which must never be reused for subscriptions or tests.
     */
    function sdk(clientId) {
        var ns = 'mmAdsPayPal';
        if (window[ns] && window[ns].Buttons && sdk.client === clientId) return $.Deferred().resolve(window[ns]).promise();
        if (sdkLoading && sdk.client === clientId) return sdkLoading;
        sdk.client = clientId;
        var d = $.Deferred(), s = document.createElement('script');
        s.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(clientId) + '&vault=true&intent=subscription&currency=PHP&locale=en_PH&disable-funding=paylater,venmo';
        s.setAttribute('data-namespace', ns);
        var failed = function () { sdkLoading = null; sdk.client = null; s.parentNode && s.parentNode.removeChild(s); d.reject(); }; // the next try loads it again
        s.onload = function () { window[ns] && window[ns].Buttons ? d.resolve(window[ns]) : failed(); };
        s.onerror = failed;
        document.head.appendChild(s);
        sdkLoading = d.promise();
        return sdkLoading;
    }

    function loader(text) {
        return '<div class="mma-loader" role="status"><span class="mma-spin" aria-hidden="true"></span><span>' + esc(text) + '</span></div>';
    }

    function niceDate(days) {
        var d = new Date(Date.now() + days * 86400000);
        return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function checkout(ad, cadence, $box) {
        var $pay = $box.find('[data-mma-pay]').first().prop('hidden', false).html(loader('Preparing secure checkout\u2026'));
        var $nav = $box.find('.mma-nav').prop('hidden', true);
        var back = function () { $pay.prop('hidden', true).empty(); $nav.prop('hidden', false); if ($bld && $box.is($bld)) nav(); };
        $pay[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        post('mm_ads_checkout', { ad: ad, cadence: cadence }).done(function (r) {
            if (!r.success) { back(); return fail($box)(r); }
            var d = r.data, monthly = cadence === 'monthly';
            var label = $box.find('[name=placement]:checked').attr('data-label') || 'Your ad';
            $pay.html(
                '<div class="mma-pay__head"><b><i class="fa-solid fa-lock" aria-hidden="true"></i> Secure checkout' + (d.mode === 'sandbox' ? ' <em class="mma-testbadge">Sandbox test</em>' : '') + '</b><button type="button" class="mma-link" data-mma-change>Change</button></div>' +
                '<div class="mma-pay__sum"><div><span>' + esc(label) + ' &middot; ' + (monthly ? 'Monthly' : 'Weekly') + '</span>' +
                '<small>First payment today, then every ' + (monthly ? 'month' : 'week') + ' until you cancel. Next charge ' + esc(niceDate(monthly ? 30 : 7)) + '.</small></div>' +
                '<strong>' + esc(peso(d.price)) + '<span>/' + (monthly ? 'month' : 'week') + '</span></strong></div>' +
                '<div class="mma-pay__btns" data-mma-buttons>' + loader('Loading PayPal\u2026') + '</div>' +
                '<p class="mma-pay__foot">Processed securely by PayPal. Pay with your PayPal account or any debit or credit card (no PayPal account needed). We never see your card details.</p>'
            );
            $pay.find('[data-mma-change]').on('click', back);
            sdk(d.clientId).done(function (pp) {
                var $btns = $pay.find('[data-mma-buttons]');
                pp.Buttons({
                    style: { layout: 'vertical', shape: 'pill', label: 'subscribe', height: 48 },
                    createSubscription: function (data, actions) { return actions.subscription.create({ plan_id: d.plan, custom_id: d.custom }); },
                    onApprove: function (data) {
                        $pay.html(loader('Confirming your payment\u2026'));
                        var stuck = function (msg) {
                            // Paid at PayPal but not confirmed here yet: PayPal also tells us directly, so a refresh usually shows it.
                            $pay.html('<div class="mma-done mma-done--warn"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><b>We couldn\u2019t confirm the payment yet</b><span>' + esc(msg || 'Please refresh this page in a minute.') + ' Don\u2019t pay again. Subscription ID: ' + esc(data.subscriptionID) + '</span><button type="button" class="mma-btn" onclick="location.reload()">Refresh</button></div></div>');
                        };
                        post('mm_ads_activate', { ad: ad, sub: data.subscriptionID }).done(function (a) {
                            if (!a.success) return stuck($('<div>').html(a.data || '').text());
                            $pay.html('<div class="mma-done"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><b>Payment received</b><span>' + a.data.message + '</span></div></div>');
                            setTimeout(function () { location.reload(); }, 3500);
                        }).fail(function () { stuck('Your connection dropped.'); });
                    },
                    onCancel: function () { say($box, 'Payment cancelled. You can try again whenever you\u2019re ready.'); },
                    onError: function () { say($box, 'PayPal couldn\u2019t complete the payment. Please try again.', true); }
                }).render($btns[0]).then(function () { $btns.find('.mma-loader').remove(); });
            }).fail(function () { $pay.find('[data-mma-buttons]').empty(); say($box, 'PayPal didn\u2019t load. Check your connection and try again.', true); });
        }).fail(function (x) { back(); fail($box)(x); });
    }

    /* ---------------------------------------------------------------- Ad cards */

    function initAds() {
        $(document).on('click', '[data-mma-edit]', function () { var $ad = $(this).closest('[data-mma-ad]'); open(['checkout', 'ended'].indexOf($ad.attr('data-status')) > -1 ? 'book' : 'edit', $ad); });
        $(document).on('click', '[data-mma-book]', function () { open('book', $(this).closest('[data-mma-ad]')); });
        $(document).on('click', '[data-mma-cancel]', function () {
            var $b = $(this), $ad = $b.closest('[data-mma-ad]');
            if (!$b.attr('data-sure')) {
                $b.attr('data-sure', '1').text('Yes, cancel the subscription');
                say($ad, 'No more charges after this. Your ad keeps running until the end of the period you paid for.');
                return;
            }
            $b.prop('disabled', true);
            post('mm_ads_cancel', { ad: $ad.attr('data-mma-ad') }).done(function (r) {
                if (!r.success) return fail($ad)(r);
                say($ad, r.data.message);
                setTimeout(function () { location.reload(); }, 2000);
            }).fail(fail($ad)).always(function () { $b.prop('disabled', false); });
        });
        $(document).on('click', '[data-mma-switch]', function () {
            var $b = $(this).prop('disabled', true), $ad = $b.closest('[data-mma-ad]');
            post('mm_ads_switch', { ad: $ad.attr('data-mma-ad') }).done(function (r) {
                if (!r.success) return fail($ad)(r);
                say($ad, 'Opening PayPal to confirm the change\u2026');
                location.href = r.data.redirect;
            }).fail(fail($ad)).always(function () { $b.prop('disabled', false); });
        });
    }

    /* ---------------------------------------------------------------- Results (/advertiser/?report=ID) */

    var rep = { $el: null, chart: null, timer: null, data: null };

    function n(v) { return Number(v || 0).toLocaleString('en-PH'); }

    function chg(c, invert) {
        if (c === null || c === undefined) return '<em class="mma-chg is-new">new</em>';
        if (c === 0) return '<em class="mma-chg">no change</em>';
        var good = invert ? c < 0 : c > 0;
        return '<em class="mma-chg ' + (good ? 'is-up' : 'is-down') + '">' + (c > 0 ? '\u25b2 ' : '\u25bc ') + Math.abs(c) + '%</em>';
    }

    function rcard(label, value, c, hint, invert) {
        return '<div class="mma-rcard"><span>' + label + '</span><b>' + value + '</b><small>' + (c === false ? (hint || '') : chg(c, invert) + ' <i>vs previous ' + rep.data.period.days + ' days</i>') + '</small></div>';
    }

    function panel(title, body, cls) { return '<section class="mma-rpanel' + (cls ? ' ' + cls : '') + '"><h3>' + title + '</h3>' + body + '</section>'; }

    function hbars(rows, label, val, sub) {
        if (!rows || !rows.length) return '<p class="mma-fine">No clicks yet in this period.</p>';
        var max = Math.max.apply(null, rows.map(function (r) { return val(r); })) || 1;
        return rows.map(function (r) {
            return '<div class="mma-hbar"><span>' + esc(label(r)) + '</span><i style="width:' + Math.max(3, val(r) / max * 100) + '%"></i><b>' + (sub ? sub(r) : n(val(r))) + '</b></div>';
        }).join('');
    }

    function hidden(seg) {
        return '<p class="mma-fine">Shown once there are ' + rep.data.minSeg + ' clicks in this period, so no one can be identified' + (seg && seg.n ? ' (' + seg.n + ' so far)' : '') + '.</p>';
    }

    function heatHtml(grid) {
        if (!grid) return hidden({ n: rep.data.heatN });
        var max = 1, days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        grid.forEach(function (row) { row.forEach(function (v) { if (v > max) max = v; }); });
        var h = '<div class="mma-rheat">';
        grid.forEach(function (row, d) {
            h += '<span class="mma-rheat__d">' + days[d] + '</span>';
            row.forEach(function (v, hr) { h += '<i title="' + days[d] + ' ' + hr + ':00 - ' + v + ' clicks" style="opacity:' + (v ? Math.max(0.12, v / max) : 0.05) + '"></i>'; });
        });
        h += '<span></span>';
        for (var hr = 0; hr < 24; hr++) h += '<em>' + (hr % 6 === 0 ? (hr === 0 ? '12a' : (hr === 12 ? '12p' : (hr < 12 ? hr + 'a' : (hr - 12) + 'p'))) : '') + '</em>';
        return h + '</div>';
    }

    function renderReport(d) {
        rep.data = d;
        var c = d.cards, p = d.period, owner = d.role === 'owner';
        var fmtDay = function (x) { return new Date(x + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }); };
        rep.$el.find('[data-mma-period]').text(fmtDay(p.from) + ' \u2013 ' + fmtDay(p.to) + (p.live ? ' \u00b7 updates every minute' : ''));
        var bench = d.benchmark ? 'Placement average ' + d.benchmark.ctr + '%' : 'Benchmark shows once 3+ ads run here';

        var h = '<div class="mma-rcards">' +
            rcard('Views on the site', n(c.view.value), c.view.change) +
            rcard('Unique viewers', n(c.uview.value), c.uview.change) +
            rcard('Emails sent', n(c.sent.value), c.sent.change) +
            rcard('Email opens (est.)', n(c.open.value), c.open.change) +
            rcard('Clicks', n(c.click.value), c.click.change) +
            rcard('Unique clicks', n(c.uclick.value), c.uclick.change) +
            rcard('Click-through rate', c.ctr.value === null ? '\u2013' : c.ctr.value + '%', false, bench) +
            rcard('Cost per click', c.cpc.value === null ? '\u2013' : peso(c.cpc.value), c.cpc.value === null ? false : c.cpc.change, c.cpc.value === null ? 'After the first click' : '', true) +
            rcard('Cost per 1,000 reached', c.cpm.value === null ? '\u2013' : peso(c.cpm.value), c.cpm.value === null ? false : c.cpm.change, '', true) +
            rcard('Paid in this period', peso(c.paid.value), false, 'Total since start ' + peso(d.paidAll)) +
            '</div>';

        h += panel('Reach and clicks per day', '<div class="mma-rchart"><canvas data-mma-chart></canvas></div>', 'mma-rpanel--wide');

        var slots = d.slots.length ? '<table class="mma-table mma-rtable"><thead><tr><th>Placement</th><th>Views</th><th>Emails</th><th>Clicks</th><th>CTR</th></tr></thead><tbody>' +
            d.slots.map(function (r) { return '<tr><td>' + esc(r.name) + '</td><td>' + n(r.views) + '</td><td>' + n(r.sent) + '</td><td>' + n(r.clicks) + '</td><td>' + (r.ctr === null ? '\u2013' : r.ctr + '%') + '</td></tr>'; }).join('') + '</tbody></table>' : '<p class="mma-fine">No activity yet in this period.</p>';

        var vers;
        if (d.versions.length === 2) {
            vers = '<table class="mma-table mma-rtable"><thead><tr><th>Version</th><th>Seen</th><th>Clicks</th><th>CTR</th></tr></thead><tbody>' +
                d.versions.map(function (v) { return '<tr><td><b>' + v.v + '</b> &middot; ' + esc(v.headline) + (d.winner === v.v ? ' <span class="mma-win">Winner</span>' : '') + '</td><td>' + n(v.imp) + '</td><td>' + n(v.clicks) + '</td><td>' + v.ctr + '%</td></tr>'; }).join('') + '</tbody></table>' +
                (d.winner ? '' : '<p class="mma-fine">Too early to call: we need about 300 views per version and 10 clicks.</p>') +
                (owner && d.ad.hasB ? '<div class="mma-ractions"><button type="button" class="mma-btn" data-mma-keep="a">Keep version A only</button><button type="button" class="mma-btn" data-mma-keep="b">Keep version B only</button></div>' : '');
        } else {
            vers = '<p class="mma-fine">' + (d.ad.hasB ? 'Version B hasn\u2019t been shown yet in this period.' : 'Test two headlines: add a version B under Edit card \u2192 Extras. We rotate both and show the winner here.') + '</p>';
        }

        var dev = d.devices, devRows = [['Phone', dev.phone || {}], ['Computer', dev.computer || {}]].filter(function (x) { return (x[1].view || 0) + (x[1].click || 0) > 0; });
        var devHtml = devRows.length ? hbars(devRows, function (r) { return r[0]; }, function (r) { return r[1].view || 0; }, function (r) { return n(r[1].view || 0) + ' views \u00b7 ' + n(r[1].click || 0) + ' clicks'; }) : '<p class="mma-fine">No site views yet in this period.</p>';

        h += '<div class="mma-rgrid">' +
            panel('Where it ran', slots) +
            panel('Clicks by link', hbars(d.links, function (r) { return r.name; }, function (r) { return r.clicks; }, function (r) { return n(r.clicks) + ' \u00b7 ' + r.share + '%'; })) +
            panel('Version A vs B', vers) +
            panel('Phone vs computer', devHtml) +
            panel('Who clicks <small>group budget</small>', d.budget.hidden ? hidden(d.budget) : hbars(d.budget.rows, function (r) { return r.name; }, function (r) { return r.value; }, function (r) { return r.share + '%'; })) +
            panel('Who clicks <small>group size</small>', d.size.hidden ? hidden(d.size) : hbars(d.size.rows, function (r) { return r.name; }, function (r) { return r.value; }, function (r) { return r.share + '%'; })) +
            '</div>';

        h += panel('Best days and hours <small>clicks, Philippine time</small>', heatHtml(d.heat), 'mma-rpanel--wide');

        h += '<details class="mma-rdefs"><summary>How each number is counted</summary><dl>' +
            '<dt>Views</dt><dd>Your card was at least half on screen for one full second. Scrolling past doesn\u2019t count. Bots are excluded.</dd>' +
            '<dt>Unique viewers</dt><dd>Each browser counts once per day.</dd>' +
            '<dt>Emails sent</dt><dd>Emails that included your card, counted when sent.</dd>' +
            '<dt>Email opens (est.)</dt><dd>Counted when the email&rsquo;s images load. Some apps load images automatically and others block them, so this is an estimate.</dd>' +
            '<dt>Clicks</dt><dd>Taps on your card&rsquo;s links. Bots, email link scanners and repeat clicks within 30 seconds are filtered out. Unique clicks count each person once per day.</dd>' +
            '<dt>Click-through rate</dt><dd>Clicks divided by views plus email opens.</dd>' +
            '<dt>Cost figures</dt><dd>What you paid in the period divided by clicks, or by views plus emails sent (per 1,000).</dd>' +
            '<dt>Your own analytics</dt><dd>Every link carries <code>utm_source=manitomanita</code> and <code>utm_campaign=ad-' + d.ad.id + '</code>, so visits also appear in Google Analytics, Shopify or your shop&rsquo;s stats.</dd>' +
            '</dl></details>';

        rep.$el.find('[data-mma-rbody]').html(h);
        drawChart(d);
    }

    function drawChart(d) {
        var el = rep.$el.find('[data-mma-chart]')[0];
        if (rep.chart) { rep.chart.destroy(); rep.chart = null; }
        if (!el || !window.Chart) return;
        var labels = d.daily.labels.map(function (x) { return new Date(x + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }); });
        rep.chart = new window.Chart(el, {
            data: { labels: labels, datasets: [
                { type: 'bar', label: 'Views', data: d.daily.view, backgroundColor: 'rgba(62,199,194,.45)', borderRadius: 4, yAxisID: 'y' },
                { type: 'bar', label: 'Emails sent', data: d.daily.sent, backgroundColor: 'rgba(212,175,55,.55)', borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Clicks', data: d.daily.click, borderColor: '#1b1b1b', backgroundColor: '#1b1b1b', cubicInterpolationMode: 'monotone', pointRadius: labels.length > 45 ? 0 : 3, borderWidth: 2, yAxisID: 'y2' }
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Reach' } }, y2: { position: 'right', beginAtZero: true, ticks: { precision: 0 }, grid: { drawOnChartArea: false }, title: { display: true, text: 'Clicks' } } } }
        });
    }

    function loadReport(silent) {
        var $r = rep.$el, range = $r.find('[data-mma-range]').val();
        if (!silent) $r.find('[data-mma-rbody]').addClass('is-loading');
        post('mm_ads_report', { ad: $r.attr('data-ad'), k: $r.attr('data-k') || '', range: range, from: $r.find('[data-mma-from]').val() || '', to: $r.find('[data-mma-to]').val() || '' })
            .done(function (res) {
                if (!res.success) { $r.find('[data-mma-rbody]').html('<p class="mma-msg is-bad">' + esc(res.data || 'Could not load the results.') + '</p>'); return; }
                renderReport(res.data);
                if (res.data.share) showShare(res.data.share, true);
            })
            .fail(function () { if (!silent) $r.find('[data-mma-rbody]').html('<p class="mma-msg is-bad">Network error. Please refresh.</p>'); })
            .always(function () { $r.find('[data-mma-rbody]').removeClass('is-loading'); });
    }

    function showShare(url, quiet) {
        var $b = rep.$el.find('[data-mma-sharebox]');
        if (!url) { $b.prop('hidden', true).empty(); return; }
        $b.prop('hidden', !!quiet && $b.prop('hidden')).html('<p><b>Share link</b> Anyone with this link can see these results (read only). It never shows your billing or account.</p>' +
            '<div class="mma-sharebox__row"><input type="text" readonly value="' + esc(url) + '" aria-label="Share link" data-mma-shareurl><button type="button" class="mma-btn" data-mma-copy>Copy</button><button type="button" class="mma-link mma-link--danger" data-mma-shareoff>Turn off</button></div>');
    }

    function initReport() {
        rep.$el = $('[data-mma-report]');
        if (!rep.$el.length) return;
        var $r = rep.$el;
        try { var saved = localStorage.getItem('mma_range'); if (saved && saved !== 'custom') $r.find('[data-mma-range]').val(saved); } catch (e) {}
        $r.on('change', '[data-mma-range]', function () {
            var custom = this.value === 'custom';
            $r.find('[data-mma-custom]').prop('hidden', !custom);
            try { localStorage.setItem('mma_range', this.value); } catch (e) {}
            if (!custom) loadReport();
        });
        $r.on('click', '[data-mma-apply]', function () { loadReport(); });
        $r.on('click', '[data-mma-print]', function () { window.print(); });
        $r.on('click', '[data-mma-csv]', function () {
            var q = $.param({ ad: $r.attr('data-ad'), k: $r.attr('data-k') || '', range: $r.find('[data-mma-range]').val(), from: $r.find('[data-mma-from]').val() || '', to: $r.find('[data-mma-to]').val() || '', nonce: cfg.nonce });
            location.href = cfg.csv + '&' + q;
        });
        $r.on('click', '[data-mma-share]', function () {
            var $b = $(this).prop('disabled', true);
            post('mm_ads_share', { ad: $r.attr('data-ad'), 'do': 'on' }).done(function (res) {
                if (!res.success) return say($r, res.data, true);
                showShare(res.data.url);
            }).always(function () { $b.prop('disabled', false); });
        });
        $r.on('click', '[data-mma-copy]', function () {
            var $i = $r.find('[data-mma-shareurl]'), $b = $(this);
            var ok = function () { $b.text('Copied'); setTimeout(function () { $b.text('Copy'); }, 1500); };
            if (navigator.clipboard) navigator.clipboard.writeText($i.val()).then(ok, function () { $i[0].select(); });
            else { $i[0].select(); document.execCommand('copy'); ok(); }
        });
        $r.on('click', '[data-mma-shareoff]', function () {
            post('mm_ads_share', { ad: $r.attr('data-ad'), 'do': 'off' }).done(function () { showShare(''); });
        });
        $r.on('click', '[data-mma-keep]', function () {
            var keep = $(this).attr('data-mma-keep'), $b = $(this);
            if (!$b.attr('data-sure')) { $b.attr('data-sure', '1').text('Yes, keep version ' + keep.toUpperCase() + ' only'); return; }
            post('mm_ads_variant', { ad: $r.attr('data-ad'), keep: keep }).done(function (res) {
                if (!res.success) return;
                $b.closest('.mma-rpanel').append('<p class="mma-msg">' + esc(res.data.message) + '</p>');
                setTimeout(function () { loadReport(); }, 1200);
            });
        });
        loadReport();
        rep.timer = setInterval(function () { if (!document.hidden && rep.data && rep.data.period.live) loadReport(true); }, 60000);
    }

    $(function () {
        if (!$('[data-mm-ads]').length) return;
        initLogin();
        initAccount();
        initBuilder();
        initAds();
        initReport();
        if (location.hash === '#book' && $bld && $bld.length) open('new');
    });
})(jQuery);
