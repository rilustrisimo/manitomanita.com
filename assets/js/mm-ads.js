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

    /* ---------------------------------------------------------------- Builder */

    var $bld, logoUrl = '';

    function card(c) {
        var h = '<aside class="mm-spon"><span class="mm-spon__label">Sponsored</span><div class="mm-spon__body">';
        if (c.logo) h += '<img class="mm-spon__logo" src="' + esc(c.logo) + '" alt="" width="56" height="56">';
        h += '<div class="mm-spon__text"><span class="mm-spon__brand">' + esc(c.brand || 'Your brand') + '</span>';
        h += '<strong class="mm-spon__head">' + esc(c.headline || 'Your headline') + '</strong>';
        if (c.text) h += '<span class="mm-spon__copy">' + esc(c.text) + '</span>';
        if (c.promo) h += '<span class="mm-spon__promo">Use code <b>' + esc(c.promo) + '</b></span>';
        h += '</div></div><div class="mm-spon__actions"><span class="mm-spon__btn">' + esc(c.button || 'Shop now') + '</span>';
        if (c.l1) h += '<span class="mm-spon__link">' + esc(c.l1) + '</span>';
        if (c.l2) h += '<span class="mm-spon__link">' + esc(c.l2) + '</span>';
        return h + '</div></aside>';
    }

    function preview() {
        var v = function (n) { return $.trim($bld.find('[name="' + n + '"]').val() || ''); };
        $bld.find('[data-mma-preview]').html(card({
            brand: v('c[brand]'), headline: v('c[headline]'), text: v('c[text]'), button: v('c[button]'), promo: v('c[promo]'), logo: logoUrl,
            l1: v('c[links][1][1]') ? (v('c[links][1][0]') || 'More') : '', l2: v('c[links][2][1]') ? (v('c[links][2][0]') || 'More') : ''
        }));
        $bld.find('[data-mma-count]').each(function () {
            var $c = $(this), $i = $c.closest('label').find('input');
            $c.text(($i.val() || '').length + '/' + $c.attr('data-mma-count'));
        });
    }

    function pickPlacement() {
        var $r = $bld.find('[name=placement]:checked');
        if (!$r.length) return;
        var open = $r.attr('data-open') === '1', cads = ($r.attr('data-cadences') || 'weekly').split(',');
        $bld.find('.mma-opt').removeClass('is-on');
        $r.closest('.mma-opt').addClass('is-on');
        $bld.find('[data-mma-target]').prop('hidden', !$r.attr('data-target'));
        $bld.find('[data-mma-wait]').prop('hidden', open);
        $bld.find('[data-mma-save]').prop('disabled', !open);
        $bld.find('[data-mma-price="weekly"]').text(peso($r.attr('data-weekly')) + '/week');
        $bld.find('[data-mma-price="monthly"]').text(peso($r.attr('data-monthly')) + '/month');
        var $m = $bld.find('[name=cadence][value=monthly]');
        $m.prop('disabled', cads.indexOf('monthly') < 0).closest('label').toggleClass('is-off', cads.indexOf('monthly') < 0);
        if ($m.is(':disabled') && $m.is(':checked')) $bld.find('[name=cadence][value=weekly]').prop('checked', true);
    }

    function fill(c) {
        var set = function (n, val) { $bld.find('[name="' + n + '"]').val(val || ''); };
        set('c[brand]', c.brand); set('c[headline]', c.headline); set('c[text]', c.text); set('c[button]', c.button || 'Shop now'); set('c[promo]', c.promo);
        for (var i = 0; i < 3; i++) {
            var l = (c.links || [])[i] || ['', ''];
            if (i) set('c[links][' + i + '][0]', l[0]);
            set('c[links][' + i + '][1]', l[1]);
        }
        var b = c.b || {};
        set('c[b][headline]', b.headline); set('c[b][text]', b.text); set('c[b][button]', b.button);
        logoUrl = c.logo || '';
        $bld.find('[name=logo]').val('');
    }

    function initBuilder() {
        $bld = $('[data-mma-builder]');
        if (!$bld.length) return;
        $bld.on('input change', 'input, select', preview);
        $bld.on('change', '[name=placement]', pickPlacement);
        $bld.on('change', '[name=logo]', function () {
            var f = this.files && this.files[0];
            if (f && f.size > 1048576) { say($bld, 'Please use a logo under 1 MB.', true); this.value = ''; return; }
            logoUrl = f ? URL.createObjectURL(f) : '';
            preview();
        });
        $bld.on('click', '[data-mma-wait]', function () {
            var $b = $(this).prop('disabled', true);
            post('mm_ads_wait', { placement: $bld.find('[name=placement]:checked').val(), target: $bld.find('[name=target]').val() || '' })
                .done(function (r) { r.success ? say($bld, r.data.message) : fail($bld)(r); })
                .fail(fail($bld)).always(function () { $b.prop('disabled', false); });
        });
        $bld.on('submit', function (e) {
            e.preventDefault();
            var $b = $bld.find('[data-mma-save]').prop('disabled', true).text('Saving…');
            var bookAfter = $bld.attr('data-book') !== '0';
            post('mm_ads_save', new FormData(this)).done(function (r) {
                if (!r.success) return fail($bld)(r);
                $bld.find('[name=ad]').val(r.data.ad);
                say($bld, r.data.message);
                if (bookAfter) checkout(r.data.ad, $bld.find('[name=cadence]:checked').val() || 'weekly', $bld);
                else setTimeout(function () { location.reload(); }, 1200);
            }).fail(fail($bld)).always(function () { $b.prop('disabled', false).text('Save and continue to payment'); });
        });
        preview();
    }

    /** Edit or book an existing ad: the builder opens with its card (placement stays as booked). */
    function openExisting($ad, book) {
        var c = JSON.parse($ad.attr('data-c') || '{}');
        fill(c);
        $bld.find('[name=ad]').val($ad.attr('data-mma-ad'));
        $bld.attr('data-book', book ? '1' : '0');
        $bld.find('[data-mma-places]').prop('hidden', true);
        $bld.find('[data-mma-billing]').prop('hidden', !book);
        var cads = ($ad.attr('data-cadences') || 'weekly').split(',');
        $bld.find('[name=cadence][value=monthly]').prop('disabled', cads.indexOf('monthly') < 0);
        $bld.find('[data-mma-save]').prop('disabled', false).text(book ? 'Save and continue to payment' : 'Save changes');
        preview();
        $('#book').text(book ? 'Book this ad' : 'Edit your card')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
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
        $(document).on('click', '[data-mma-edit]', function () { openExisting($(this).closest('[data-mma-ad]'), false); });
        $(document).on('click', '[data-mma-book]', function () { openExisting($(this).closest('[data-mma-ad]'), true); });
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
        if (location.hash === '#book' && $bld && $bld.length) $bld[0].scrollIntoView();
    });
})(jQuery);
