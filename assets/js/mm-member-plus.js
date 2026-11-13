/**
 * Member+ (MemberPlus): a member's own PHP 99 upgrade for their card. The panel (try a look, pay, then edit
 * look / details / email), the places it's offered (chip on each card, "your card" bar, member popup,
 * "Get yours" on other Member+ cards) and the tracking: GA4 promotion + ecommerce events, mp_try / mp_use,
 * and the site-side funnel (mm_pay_step, product 'plus').
 * Theme.js runs the card password and then triggers 'mm:member-plus' with the member id.
 */
jQuery(function ($) {
    var cfg = window.mmPlus || {}, slot = 'card_button', seen = {}, item = { item_id: 'member_plus', item_name: 'Member+', price: cfg.price || 99, quantity: 1 };

    function track(name, params) { if (window.Theme && Theme.track) Theme.track(name, params || {}); }
    function toast(msg, kind) { if (window.Theme && Theme.toast) Theme.toast(msg, kind); else alert(msg); }
    function gid() { return $.trim($('#groupid').text()); }
    function promo(name, s) { track(name, { promotion_id: 'member_plus', promotion_name: 'member_plus', creative_slot: s }); }
    function viewOnce(s) { if (!seen[s]) { seen[s] = 1; promo('view_promotion', s); } }
    function post(data) { data.nonce = cfg.nonce; return $.post(cfg.ajax, data); }
    function mine() { return (window.mmVerified || []).map(String); }
    function card(uid) { return $('#cards-container .mm-member[data-mm-member="' + uid + '"]'); }

    // Site-side funnel (Insights): sheet = panel shown locked, checkout = card/PayPal tapped.
    function step(name, detail) {
        if (!cfg.pp) return;
        var f = new FormData();
        f.append('action', 'mm_pay_step'); f.append('nonce', cfg.pp.nonce); f.append('gid', gid()); f.append('product', 'plus'); f.append('step', name); f.append('detail', detail || '');
        if (navigator.sendBeacon) navigator.sendBeacon(cfg.ajax, f); else fetch(cfg.ajax, { method: 'POST', body: f, credentials: 'same-origin' });
    }

    /* ---------------------------------------------------------------- Placements */

    function placements() {
        var $cards = $('#cards-container .mm-member');
        if (!$cards.length) return;
        if ($cards.find('.mm-plus-chip').length) viewOnce('card_button');

        // "Your card" bar: this device knows the member, and their card hasn't upgraded yet.
        $('.mp-bar').remove();
        var uid = mine().filter(function (id) { var $c = card(id); return $c.length && !$c.hasClass('is-plus'); })[0];
        if (uid) {
            var dismissed = 0;
            try { dismissed = parseInt(localStorage.getItem('mm_mp_bar') || '0', 10); } catch (e) {}
            if (Date.now() - dismissed > 7 * 864e5) {
                var name = $.trim(card(uid).find('.mm-member__name').text());
                $('<div class="mp-bar" role="note"></div>').attr('data-uid', uid)
                    .append($('<span class="mp-bar__card mm-ct mm-ct-gold mm-cf"></span>').append($('<span class="mm-avatar"></span>').text(name.charAt(0).toUpperCase())))
                    .append('<span class="mp-bar__text"><strong>Make your card stand out</strong><span>Your own color, badge and sparkle &middot; &#8369;' + (cfg.price || 99) + '</span></span>')
                    .append('<button type="button" class="mp-bar__go">✨ Member+</button><button type="button" class="mp-bar__x" aria-label="Not now">&times;</button>')
                    .insertBefore('#cards-container');
                viewOnce('your_card_bar');
            }
        }
        // GA4 user property for people who have Member+ on a card this device knows.
        if (mine().some(function (id) { return card(id).hasClass('is-plus'); })) {
            try { if (typeof window.gtag === 'function') gtag('set', 'user_properties', { member_plus: 'yes' }); } catch (e) {}
        }
    }

    var obs = document.getElementById('cards-container');
    if (obs && window.MutationObserver) {
        var t;
        new MutationObserver(function () { clearTimeout(t); t = setTimeout(placements, 150); }).observe(obs, { childList: true });
    }
    placements();

    function openFor(uid, s) {
        slot = s;
        promo('select_promotion', s);
        var $chip = card(uid).find('.mm-plus-chip');
        if ($chip.length) $chip.trigger('click'); else $(document).trigger('mm:member-plus', [uid]);
    }

    $(document).on('click', '.mm-plus-chip', function () { slot = $(this).attr('data-mm-plus-slot') || 'card_button'; promo('select_promotion', slot); });
    $(document).on('click', '.mp-bar__go', function () { openFor($(this).closest('.mp-bar').attr('data-uid'), 'your_card_bar'); });
    $(document).on('click', '.mp-bar__x', function () {
        try { localStorage.setItem('mm_mp_bar', String(Date.now())); } catch (e) {}
        $(this).closest('.mp-bar').remove();
    });

    // Member popup: the card's look in the head, a Member+ action on cards without it, "Get yours" on others'.
    $(document).on('mm:member-open', function (e, $card, $inner) {
        var uid = String($card.attr('data-mm-member')), $head = $inner.find('.mm-pop-head');
        $head.removeClass(function (i, c) { return (c.match(/(^|\s)(mm-ct\S*|mm-cf|is-plus)/g) || []).join(' '); });
        if ($card.hasClass('is-plus')) {
            $head.addClass(($card.attr('class').match(/(mm-ct\S*|mm-cf|is-plus)/g) || []).join(' '));
            if (mine().indexOf(uid) === -1) {
                $('<button type="button" class="mp-getyours">✨ Member+ &middot; Get yours</button>').insertAfter($head);
                viewOnce('others_card');
            }
        } else if ($card.find('.mm-plus-chip').length) {
            $('<button type="button" class="mm-pop-action is-plus" data-kind="plus">✨ Member+ for this card</button>')
                .appendTo($inner.find('.mm-pop-actions'))
                .on('click', function () { $.fancybox.close(); setTimeout(function () { openFor(uid, 'member_popup'); }, 350); });
            viewOnce('member_popup');
        }
    });
    $(document).on('click', '.mp-getyours', function () {
        promo('select_promotion', 'others_card');
        $.fancybox.close();
        var own = mine().filter(function (id) { var $c = card(id); return $c.length && !$c.hasClass('is-plus'); })[0];
        setTimeout(function () {
            if (own) openFor(own, 'others_card');
            else toast('Find your own card and tap ✨ Member+');
        }, 350);
    });

    /* ---------------------------------------------------------------- Panel */

    var $pop = $('#mp-popup'), $in = $pop.find('[data-mp-inner]');

    $(document).on('mm:member-plus', function (e, uid) {
        $in.html('<div class="mm-detail mm-detail--loading" aria-busy="true"><span></span><span></span><span></span></div>');
        $.fancybox.open({
            src: '#mp-popup', type: 'inline', animationDuration: 400, animationEffect: 'slide-in-out',
            btnTpl: { smallBtn: '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}"><i class="fas fa-times"></i></button>' },
            afterShow: function (instance, current) { if (window.Theme && Theme.moveCloseButton) Theme.moveCloseButton($, current); }
        });
        post({ action: 'mm_mplus_panel', uid: uid }).done(function (r) {
            if (!r || !r.success) { $in.html('<p class="mm-empty">' + ((r && r.data && r.data.message) || 'Could not open Member+.') + '</p>'); return; }
            $in.html(r.data.html);
            var $mp = $in.find('[data-mp]');
            if (r.data.plus) unlocked($mp, uid); else locked($mp, uid);
        }).fail(function () { $in.html('<p class="mm-empty">Network error. Please try again.</p>'); });
    });

    // The look tools work the same before (preview only) and after paying (saved).
    function lookTools($mp, onChange) {
        var $c = $mp.find('[data-mp-card]');
        $mp.on('click', '[data-mp-theme]', function () {
            var k = $(this).attr('data-mp-theme');
            $mp.find('[data-mp-theme]').removeClass('is-on'); $(this).addClass('is-on');
            $c.removeClass(function (i, cl) { return (cl.match(/mm-ct-\S+/g) || []).join(' '); }).addClass('mm-ct-' + k);
            onChange('theme', k);
        });
        $mp.on('click', '[data-mp-badge-key]', function () {
            $mp.find('[data-mp-badge-key]').removeClass('is-on'); $(this).addClass('is-on');
            var k = $(this).attr('data-mp-badge-key');
            $c.find('[data-mp-badge]').text(k ? $(this).text() : '');
            onChange('badge', k);
        });
        $mp.on('change', '[data-mp-frame]', function () {
            $c.toggleClass('mm-cf', this.checked);
            onChange('frame', this.checked ? '1' : '0');
        });
    }

    function current($mp) {
        return {
            theme: $mp.find('[data-mp-theme].is-on').attr('data-mp-theme') || '',
            badge: $mp.find('[data-mp-badge-key].is-on').attr('data-mp-badge-key') || '',
            frame: $mp.find('[data-mp-frame]').is(':checked') ? '1' : '0'
        };
    }

    function locked($mp, uid) {
        track('view_item', { currency: 'PHP', value: item.price, items: [item], creative_slot: slot });
        step('sheet', slot);
        lookTools($mp, function (look, value) { track('mp_try', { look: look, value_key: value }); });
        if (!cfg.pp) { $mp.find('[data-mp-pay]').html('<p class="mm-pay-off">Payments are temporarily unavailable. Please try again later.</p>'); return; }
        sdk(function () { buttons(uid); });
    }

    function sdk(cb) {
        if (window.paypal && window.paypal.Buttons) return cb();
        var s = document.querySelector('script[data-mp-sdk]');
        if (!s) {
            s = document.createElement('script');
            s.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(cfg.pp.client) + '&currency=PHP&locale=en_PH&disable-funding=paylater,venmo';
            s.setAttribute('data-mp-sdk', '1');
            document.head.appendChild(s);
        }
        s.addEventListener('load', cb);
    }

    function ppPost(data) {
        data.nonce = cfg.pp.nonce;
        return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data).toString() }).then(function (r) { return r.json(); });
    }

    function buttons(uid) {
        var overlay = document.getElementById('loader-overlay-paypal');
        function fail(msg) { if (overlay) overlay.style.display = 'none'; toast(msg || 'There was an issue processing your payment. Please try again.', 'error'); }
        var conf = {
            onClick: function (data) {
                var src = data ? data.fundingSource : '';
                track('begin_checkout', { currency: 'PHP', value: item.price, payment_type: src, items: [item] });
                step('checkout', src);
            },
            createOrder: function (data) {
                track('add_payment_info', { currency: 'PHP', value: item.price, payment_type: data && data.paymentSource ? data.paymentSource : '', items: [item] });
                return ppPost({ action: 'mm_paypal_create', gid: gid(), product: 'plus', uid: uid }).then(function (resp) {
                    if (!resp.success) throw new Error(resp.data || 'Could not start the payment.');
                    return resp.data.id;
                });
            },
            onApprove: function (data) {
                if (overlay) overlay.style.display = 'flex';
                return ppPost({ action: 'mm_paypal_capture', order: data.orderID }).then(function (resp) {
                    if (!resp.success) return fail(resp.data);
                    if (!resp.data || !resp.data.tracked) track('purchase', { transaction_id: data.orderID, currency: 'PHP', value: item.price, items: [item] });
                    var u = new URL(window.location.href);
                    u.searchParams.set('mplus', uid);
                    window.location.href = u.toString();
                }).catch(function () { fail('Network error. If you were charged, please contact us with your PayPal receipt.'); });
            },
            onError: function (err) { fail(err && err.message ? err.message : ''); }
        };
        function render(source, style, el) {
            if (!document.querySelector(el)) return false;
            var b = paypal.Buttons(Object.assign({ fundingSource: source, style: style }, conf));
            if (b.isEligible()) { b.render(el); return true; }
            return false;
        }
        var c = render(paypal.FUNDING.CARD, { layout: 'vertical', shape: 'rect', height: 48 }, '#mp-card-container');
        render(paypal.FUNDING.PAYPAL, { layout: 'vertical', shape: 'rect', color: 'gold', label: 'paypal', height: 44 }, '#mp-paypal-container');
        if (!c) $('.mp-or').hide();
    }

    function msg($scope, text, bad) { $scope.find('[data-mp-msg]').first().prop('hidden', !text).toggleClass('is-bad', !!bad).html(text || ''); }

    function unlocked($mp, uid) {
        try { if (typeof window.gtag === 'function') gtag('set', 'user_properties', { member_plus: 'yes' }); } catch (e) {}

        $mp.on('click', '[data-mp-tab]', function () {
            var k = $(this).attr('data-mp-tab');
            $mp.find('[data-mp-tab]').removeClass('is-on'); $(this).addClass('is-on');
            $mp.find('[data-mp-pane]').prop('hidden', true).filter('[data-mp-pane="' + k + '"]').prop('hidden', false);
        });

        var saving = null;
        lookTools($mp, function (look) {
            clearTimeout(saving);
            saving = setTimeout(function () {
                var data = current($mp);
                data.action = 'mm_mplus_look'; data.uid = uid;
                post(data).done(function (r) {
                    if (!r || !r.success) return toast((r && r.data && r.data.message) || 'Could not save.', 'error');
                    track('mp_use', { feature: look });
                    var $c = card(uid);
                    $c.removeClass(function (i, cl) { return (cl.match(/(^|\s)(mm-ct\S*|mm-cf|is-plus)/g) || []).join(' '); }).addClass(r.data.classes);
                    $c.find('.mm-avatar .mm-badge').remove();
                    if (r.data.badge) $c.find('.mm-avatar').first().append(r.data.badge);
                });
            }, 250);
        });

        $mp.on('submit', '[data-mp-details]', function (e) {
            e.preventDefault();
            var $f = $(this), $b = $f.find('button[type="submit"]').prop('disabled', true);
            post({ action: 'mm_mplus_details', uid: uid, name: $f.find('[name="name"]').val(), screen: $f.find('[name="screen"]').val() }).done(function (r) {
                if (!r || !r.success) return msg($f, (r && r.data && r.data.message) || 'Could not save.', true);
                msg($f, 'Saved. Everyone sees the change now.');
                track('mp_use', { feature: 'details' });
                card(uid).find('.mm-member__name').text(r.data.screen);
                $mp.find('.mp-card__name').text(r.data.screen);
            }).always(function () { $b.prop('disabled', false); });
        });

        function emailOp(data, $box) {
            data.action = 'mm_mplus_email'; data.uid = uid;
            post(data).done(function (r) {
                if (!r || !r.success) return msg($box, (r && r.data && r.data.message) || 'Could not send.', true);
                $box.replaceWith(r.data.html);
                msg($mp.find('[data-mp-emailbox]'), r.data.message);
                if (data.op === 'request') track('mp_use', { feature: 'email_request' });
            });
        }
        $mp.on('submit', '[data-mp-email]', function (e) {
            e.preventDefault();
            emailOp({ op: 'request', email: $(this).find('[name="email"]').val() }, $(this).closest('[data-mp-emailbox]'));
        });
        $mp.on('click', '[data-mp-email-op]', function () {
            emailOp({ op: $(this).attr('data-mp-email-op') }, $(this).closest('[data-mp-emailbox]'));
        });
    }

    /* ---------------------------------------------------------------- Returns */

    var q = new URLSearchParams(window.location.search), done = q.get('mplus'), mail = q.get('mp_email');
    if (done || mail) {
        q.delete('mplus'); q.delete('mp_email');
        var clean = window.location.pathname + (q.toString() ? '?' + q.toString() : '') + window.location.hash;
        try { history.replaceState(null, '', clean); } catch (e) {}
    }
    if (done && /^\d+$/.test(done)) {
        toast('✨ Member+ is on! Your card now stands out.');
        setTimeout(function () { $(document).trigger('mm:member-plus', [done]); }, 600);
    }
    if (mail) {
        toast({ ok: 'Your new email is confirmed.', taken: 'That email is now used by someone else in the group, so your email stayed the same.', expired: 'That link has expired. Open your card and ask for a new one.' }[mail] || '', mail === 'ok' ? '' : 'error');
        if (mail === 'ok') track('mp_use', { feature: 'email' });
    }
});
