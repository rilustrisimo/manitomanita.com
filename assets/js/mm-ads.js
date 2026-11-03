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
        return '₱' + Number(n || 0).toLocaleString('en-PH');
    }

    /* ---------------------------------------------------------------- Sign in */

    function initLogin() {
        var $f = $('[data-mma-login]');
        if (!$f.length) return;
        $f.on('click', '[data-mma-send]', function () {
            var email = $.trim($f.find('[name=email]').val()), $b = $(this);
            if (!/^\S+@\S+\.\S+$/.test(email)) return say($f, 'Please enter a valid email address.', true);
            $b.prop('disabled', true).text('Sending…');
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

    function pickPlacement() {
        var $r = placement();
        $bld.find('.mma-opt').removeClass('is-on');
        if (!$r.length) return;
        var open = $r.attr('data-open') === '1';
        $r.closest('.mma-opt').addClass('is-on');
        $bld.find('[data-mma-target]').prop('hidden', !$r.attr('data-target') || !open);
        $bld.find('[data-mma-waitbox]').prop('hidden', open);
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
        $bld.find('[data-mma-price="weekly"]').text(peso(w) + ' / week');
        $bld.find('[data-mma-price="monthly"]').text(peso(m) + ' / month');
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
            if ($r.attr('data-target') && !$bld.find('[name=target]').val()) return 'Choose which gift guide your ad should appear on.';
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
            if (e.target.name === 'placement') return pickPlacement();
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
            var $b = $bld.find('[data-mma-save]').prop('disabled', true).text('Saving…');
            post('mm_ads_save', new FormData(this)).done(function (r) {
                if (!r.success) return fail($bld)(r);
                $bld.find('[name=ad]').val(r.data.ad);
                if (mode === 'edit') {
                    say($bld, r.data.message);
                    setTimeout(function () { location.reload(); }, 1500);
                    return;
                }
                say($bld, '');
                $b.prop('hidden', true);
                $bld.find('[data-mma-back]').prop('hidden', true);
                checkout(r.data.ad, $bld.find('[name=cadence]:checked').val() || 'weekly', $bld);
            }).fail(fail($bld)).always(function () { if (mode === 'edit') nav(); else $b.prop('disabled', false).text('Continue to payment'); });
        });
        if (!$bld.prop('hidden')) open('new');
    }

    /* ---------------------------------------------------------------- Checkout */

    function sdk(clientId) {
        if (window.paypal && window.paypal.Buttons) return $.Deferred().resolve().promise();
        if (sdkLoading) return sdkLoading;
        var d = $.Deferred(), s = document.createElement('script');
        s.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(clientId) + '&vault=true&intent=subscription&currency=PHP&locale=en_PH&disable-funding=paylater,venmo';
        s.onload = function () { d.resolve(); };
        s.onerror = function () { d.reject(); };
        document.head.appendChild(s);
        sdkLoading = d.promise();
        return sdkLoading;
    }

    function checkout(ad, cadence, $box) {
        var $pay = $box.find('[data-mma-pay]').first().prop('hidden', false).html('<p class="mma-fine">Opening secure checkout…</p>');
        post('mm_ads_checkout', { ad: ad, cadence: cadence }).done(function (r) {
            if (!r.success) { $pay.prop('hidden', true).empty(); return fail($box)(r); }
            var d = r.data;
            sdk(d.clientId).done(function () {
                $pay.html('<p class="mma-pay__sum">' + peso(d.price) + ' ' + (cadence === 'monthly' ? 'per month' : 'per week') + ', renews until you cancel</p><div data-mma-buttons></div>');
                window.paypal.Buttons({
                    style: { layout: 'vertical', shape: 'pill', label: 'subscribe' },
                    createSubscription: function (data, actions) { return actions.subscription.create({ plan_id: d.plan, custom_id: d.custom }); },
                    onApprove: function (data) {
                        $pay.html('<p class="mma-fine">Confirming your payment…</p>');
                        post('mm_ads_activate', { ad: ad, sub: data.subscriptionID }).done(function (a) {
                            if (!a.success) return fail($box)(a);
                            say($box, a.data.message);
                            setTimeout(function () { location.reload(); }, 2200);
                        }).fail(fail($box));
                    },
                    onError: function () { say($box, 'PayPal couldn’t complete the payment. Please try again.', true); }
                }).render($pay.find('[data-mma-buttons]')[0]);
            }).fail(function () { say($box, 'PayPal didn’t load. Check your connection and try again.', true); });
        }).fail(function (x) { $pay.prop('hidden', true).empty(); fail($box)(x); });
    }

    /* ---------------------------------------------------------------- Ad cards */

    function initAds() {
        $(document).on('click', '[data-mma-edit]', function () { var $ad = $(this).closest('[data-mma-ad]'); open(['checkout', 'ended', 'rejected'].indexOf($ad.attr('data-status')) > -1 ? 'book' : 'edit', $ad); });
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
                say($ad, 'Opening PayPal to confirm the change…');
                location.href = r.data.redirect;
            }).fail(fail($ad)).always(function () { $b.prop('disabled', false); });
        });
    }

    $(function () {
        if (!$('[data-mm-ads]').length) return;
        initLogin();
        initAccount();
        initBuilder();
        initAds();
        if (location.hash === '#book' && $bld && $bld.length) open('new');
    });
})(jQuery);
