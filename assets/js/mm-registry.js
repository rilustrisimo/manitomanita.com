/**
 * My Wishlist / registry add-ons (RegistryExtras, classes/class-registry-extras.php):
 * owner share QR + download, reservation email toggle, thank-you checklist; guest "remind me" after reserving.
 * Loaded on /wishlist/<slug>/ pages only. The core list code (reserve, editor) stays in Theme.initMyWishlist,
 * which fires mm:wl-reserved / mm:wl-unreserved on .mm-wl.
 */
(function ($) {
    'use strict';

    var ajax = function () { return $('#ajax-url').val() || '/wp-admin/admin-ajax.php'; };
    var plain = function (h) { return $('<div>').html(h || '').text(); };
    var toast = function (m, t) { if (window.Theme && Theme.toast) Theme.toast(m, t); };
    var copy = function (text) {
        if (navigator.clipboard) return navigator.clipboard.writeText(text);
        var t = $('<textarea>').val(text).appendTo('body').trigger('select');
        document.execCommand('copy'); t.remove();
        return $.Deferred().resolve().promise();
    };

    /* ---------- Owner: QR code of the share link ---------- */
    function initQr(root) {
        var btn = root.find('[data-mm-wl-qr]'), box = root.find('[data-mm-wl-qrbox]'), holder = box.find('[data-mm-wl-qrcode]'), made = false;
        if (!btn.length) return;
        btn.on('click', function () {
            var open = box.prop('hidden');
            box.prop('hidden', !open);
            btn.attr('aria-expanded', open ? 'true' : 'false');
            if (open && !made) {
                if (!window.QRCode) { holder.text('QR could not load. Please refresh.'); return; }
                new QRCode(holder[0], { text: holder.attr('data-url'), width: 512, height: 512, correctLevel: QRCode.CorrectLevel.M });
                made = true;
            }
            if (open && window.Theme && Theme.track) Theme.track('wishlist_share', { method: 'qr' });
        });
        box.on('click', '[data-mm-wl-qrdl]', function () {
            var c = holder.find('canvas')[0], img = holder.find('img')[0];
            var src = c ? c.toDataURL('image/png') : (img ? img.src : '');
            if (!src) return;
            var a = document.createElement('a');
            a.href = src; a.download = ($(this).attr('data-name') || 'wishlist') + '-qr.png';
            document.body.appendChild(a); a.click(); a.remove();
        });
    }

    /* ---------- Owner: daily reservation email on/off ---------- */
    function initAlerts(root) {
        root.find('[data-mm-wl-alerts]').on('change', function () {
            var cb = $(this), on = cb.is(':checked');
            $.post(ajax(), { action: 'mm_wl_alerts', id: cb.attr('data-id'), nonce: cb.attr('data-nonce'), on: on ? 1 : 0 }).done(function (r) {
                if (r && r.success) toast(plain(r.data.message));
                else { cb.prop('checked', !on); toast(plain(r && r.data), 'error'); }
            }).fail(function () { cb.prop('checked', !on); toast('Network error. Please try again.', 'error'); });
        });
    }

    /* ---------- Owner: thank-you checklist ---------- */
    function initThanks(root) {
        var box = root.find('[data-mm-wl-got]');
        if (!box.length) return;
        var count = function () {
            var all = box.find('[data-mm-wl-thx]'), done = all.filter(':checked');
            box.find('[data-mm-wl-thxcount]').text(done.length + ' of ' + all.length + ' thanked');
        };
        box.on('change', '[data-mm-wl-thx]', function () {
            var cb = $(this), on = cb.is(':checked');
            count();
            $.post(ajax(), { action: 'mm_wl_thanked', id: box.attr('data-id'), nonce: box.attr('data-nonce'), h: cb.attr('data-mm-wl-thx'), on: on ? 1 : 0 }).done(function (r) {
                if (!r || !r.success) { cb.prop('checked', !on); count(); toast(plain(r && r.data), 'error'); }
            }).fail(function () { cb.prop('checked', !on); count(); toast('Network error. Please try again.', 'error'); });
        });
        box.on('click', '[data-mm-wl-thxmsg]', function () {
            var who = $(this).attr('data-who'), item = $(this).attr('data-item'), from = box.attr('data-for');
            var msg = (who ? 'Hi ' + who + '! ' : 'Hi! ') + 'Thank you so much for the ' + item.toLowerCase() + '. It means a lot to us, and thank you for celebrating with us!' + (from ? ' – ' + from : '');
            copy(msg).then(function () { toast('Thank-you message copied. Paste it in Messenger or Viber.'); });
        });
    }

    /* ---------- Guest: remind me before the day ---------- */
    function initRemind(root) {
        var list = root.find('[data-mm-wl-list]');
        var when = list.attr('data-remind');
        if (!list.length || !when) return;
        var wid = list.attr('data-id'), resStore = 'mm_wl_res_' + wid, doneStore = 'mm_wl_rm_' + wid;
        var read = function (k) { try { return JSON.parse(localStorage.getItem(k) || '{}') || {}; } catch (e) { return {}; } };
        var done = read(doneStore);

        var offer = function (li, token) {
            var key = li.attr('data-key');
            li.find('.mm-wl-remind').remove();
            if (!token) return;
            var wrap = $('<div class="mm-wl-remind"></div>').insertAfter(li.find('[data-mm-wl-res]'));
            if (done[key]) { wrap.html('<span><i class="fa-regular fa-bell"></i> We’ll remind you on ' + $('<i>').text(when).html() + '</span>'); return; }
            wrap.html('<button type="button" class="mm-wl-link" data-mm-wl-remind-open><i class="fa-regular fa-bell"></i> Remind me on ' + $('<i>').text(when).html() + '</button>');
            wrap.on('click', '[data-mm-wl-remind-open]', function () {
                wrap.html('<form class="mm-wl-resform"><input type="email" maxlength="120" placeholder="Your email" aria-label="Your email" required><button type="submit" class="mm-wl-btn mm-wl-btn--dark">Remind me</button></form><small>One email only, ' + $('<i>').text(when).html() + '. We won’t use it for anything else.</small>');
                wrap.find('input').trigger('focus');
            });
            wrap.on('submit', 'form', function (e) {
                e.preventDefault();
                var em = $.trim($(this).find('input').val()), b = $(this).find('button').prop('disabled', true);
                $.post(ajax(), { action: 'mm_wl_remind', id: wid, key: key, token: token, email: em }).done(function (r) {
                    if (r && r.success) { done[key] = 1; try { localStorage.setItem(doneStore, JSON.stringify(done)); } catch (e2) {} toast(plain(r.data.message)); offer(li, token); }
                    else { b.prop('disabled', false); toast(plain(r && r.data), 'error'); }
                }).fail(function () { b.prop('disabled', false); toast('Network error. Please try again.', 'error'); });
            });
        };

        var mine = read(resStore);
        list.children('li').each(function () { var k = $(this).attr('data-key'); if (mine[k]) offer($(this), mine[k]); });
        root.on('mm:wl-reserved', function (e, li, token) { offer($(li), token); });
        root.on('mm:wl-unreserved', function (e, li) {
            $(li).find('.mm-wl-remind').remove();
            delete done[$(li).attr('data-key')];
            try { localStorage.setItem(doneStore, JSON.stringify(done)); } catch (e2) {}
        });
    }

    /* ---------- Owner: tabs (Gifts / Share / Guests / Details) and the list switcher ---------- */
    function initTabs(root) {
        var nav = root.find('[data-mm-wl-tabs]');
        root.find('[data-mm-wl-switch]').on('change', function () { if (this.value) window.location.href = this.value; });
        if (!nav.length) return;
        var names = ['gifts', 'share', 'guests', 'details'];
        var show = function (t, scroll) {
            if (names.indexOf(t) < 0) t = 'gifts';
            nav.find('[data-mm-wl-tab2]').each(function () {
                var on = $(this).attr('data-mm-wl-tab2') === t;
                $(this).toggleClass('is-on', on).attr('aria-selected', on ? 'true' : 'false');
            });
            root.find('[data-mm-wl-panel]').each(function () {
                $(this).toggleClass('mm-wl-off', (' ' + $(this).attr('data-mm-wl-panel') + ' ').indexOf(' ' + t + ' ') < 0);
            });
            try { history.replaceState(null, '', '#' + t); } catch (e) {}
            if (scroll) { var top = nav.offset().top - 80; if ($(window).scrollTop() > top) $('html, body').animate({ scrollTop: top }, 200); }
        };
        nav.attr('role', 'tablist').find('[data-mm-wl-tab2]').attr('role', 'tab');
        nav.on('click', '[data-mm-wl-tab2]', function () { show($(this).attr('data-mm-wl-tab2'), true); });
        root.on('click', '[data-mm-wl-go]', function () { show($(this).attr('data-mm-wl-go'), true); });
        root.addClass('has-tabs');
        show((window.location.hash || '').replace('#', ''), false);
    }

    $(function () {
        var root = $('.mm-wl');
        if (!root.length) return;
        initTabs(root);
        initQr(root);
        initAlerts(root);
        initThanks(root);
        initRemind(root);
    });
})(jQuery);
