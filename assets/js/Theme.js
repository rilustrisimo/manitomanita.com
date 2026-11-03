var Theme = {
    disableTabStateRestore:false,
    selectLiveSearchAutoStart:0,
    userid: null,
    action: null,
    batchcount: 0,
    grpuserids: [],

    init:function( $ ) {
        this._initHamburgerMenu();
        //this._initMobileMenu();
        this._initScrollTop( $ );
        this.initSelectpicker();
        this.initResponsiveTables();
        this._initTabsStateFromHash( $ );
        this.initPopupModal($);
        this.acfFormScripts($);
        this.productSlick($);
        this.mobileChecker($);
        this.buttonsMobile($);
        this.shopScripts($);
        this.acfSubmitFix($);
        this.initTours($);
        this.initPromos($);
        this.initCardsUsers($);
        this.proScripts($);
        this.initAffiliateCap($);
        this.initTracking($);
        this.initAdSense($);
        this.initSponsors($);
        this.initEmailChecks($);
        this.initFormAlerts($);
        this.initTurnstile($);
        this.initGiftStatus($);
        this.initQA($);
        this.initRepeat($);
        this.initMyWishlist($);
        this.initSupport($);
        this.initAccount($);
        this.initHome($);
        this.initPasswordToggles($);

        $( '[data-toggle="tooltip"]' ).tooltip();

        this.FormValidationHelper.init();

        $('.counter').counterUp({
            delay: 10,
            time: 1000
        });

        $(window).load(function(){
            Theme.removeOverlay($);
        });
    },

    /**
     * Legacy second redirect for links with class 'aff-link' + data-url. The theme no longer outputs
     * that markup (all groups get the plain affiliate link), so this only affects old cached HTML.
     */
    afflinkFunction: function($){

        function getCookie(name) {
            let nameEq = name + "=";
            let ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(nameEq) === 0) return c.substring(nameEq.length, c.length);
            }
            return null;
        }
    
        // Function to set a cookie
        function setCookie(name, value, days) {
            let date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000)); // Cookie expires in `days`
            let expires = "expires=" + date.toUTCString();
            document.cookie = name + "=" + value + "; " + expires + "; path=/";
        }

        // Function to create and submit a form for redirecting to a URL in the same tab
        function redirectToUrlInSameTab(url) {
            let form = $('<form>', {
                action: url,
                method: 'GET', // or 'POST' if needed
                target: '_self' // Opens in the same tab
            }).appendTo('body'); // Append form to the body temporarily
            form.submit(); // Submit the form
            form.remove(); // Clean up by removing the form
        }
    
        // Click event for anchor tags with class 'aff-link'
        // Delegated and namespaced so repeated calls (every popup / cards reload) don't stack handlers.
        // Phones/tablets: let the link open normally (no second redirect), so iOS/Android hand the
        // product link to the Shopee/Lazada app and it opens the right product page.
        var isTouchDevice = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent) ||
            (navigator.maxTouchPoints > 1 && /Macintosh/.test(navigator.userAgent)); // iPadOS

        $(document).off('click.mmAffLink').on('click.mmAffLink', 'a.aff-link', function(event) {
            if (isTouchDevice) return;

            event.preventDefault(); // Prevent default anchor behavior
    
            let href = $(this).attr('href');
            let dataUrl = $(this).data('url');

    
            // Check if the cookie exists
            if (getCookie('affiliateClick')) {
                // Only open the href in the same tab if the cookie exists
                window.open(href, '_blank');
            } else {
                // Open data-url in a new tab and href in the same tab, and set the cookie if it doesn't exist
                window.open(href, '_blank');
                redirectToUrlInSameTab(dataUrl);
    
                // Set cookie to expire in 1 day
                setCookie('affiliateClick', 'true', 1);
            }
        });
    },

    proScripts: function($){
        Theme.proPurchaseButton($);
        Theme.makeProBtn($);
        Theme.proActionsScripts($);
        Theme.tryProPop($);
    },

    proActionsScripts: function($){
        if($('#pro-actions-btn').length > 0){
            $('#pro-actions-btn').click(function(e){
                e.preventDefault();

                var gid = $('input#groupid-val').val();
                if (Theme.isVerified(gid)) {
                    Theme.userid = gid;
                    $('#pro-actions.pop-container').css('display', 'flex');
                    Theme.popClose($);
                    Theme.getProListActions($);
                    return;
                }

                Theme.openPasswordField($);
            });
        }
    },

    openPasswordField: function($) {
        var box = '#input-password';
        var unamef = 'Moderator Password';
        var notice = $('#input-pw .notices');
        var userid = $('input#groupid-val');
        notice.hide();
        $('.header-details').hide();


        $.fancybox.open({
            src: box,
            type: 'inline',
            animationDuration : 600,
            animationEffect   : 'slide-in-out',
            btnTpl : {
                smallBtn:
                '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}">' +
                '<i class="fas fa-times"></i>' +
                "</button>",
            },
            afterShow : function( instance, current ) {
                $('.header-details').html(unamef);
                notice.html('<a href="javascript:;" id="reset-pass">Forgot Password?</a>.');
                notice.show();
                $('.header-details').show();

                Theme.moveCloseButton($, current);
                $('input[name="user-data"]').val($('input#groupid-val').val());

                Theme.ProInitPasswordScripts($);

                $('#reset-pass').unbind();
                $('#reset-pass').click(function(){
                    Theme.generateNewPassword($, userid.val());
                });
            }
        });
    },

    ProInitPasswordScripts: function($){
        var submitbtn = $('#input-pw input[type="submit"]');
        var pw = $('#input-pw input[name="password"]'); // by name: the show/hide toggle changes its type
        var userid = $('input#groupid-val');
        var notice = $('#input-pw .notices');

        submitbtn.unbind(); // clear binds

        submitbtn.click(function(e){
            e.preventDefault();

            if(pw.val().length == 0){
                notice.text('Please supply your password to continue.');
                return false;
            }

            Theme.initShowOverlay($); // initiate overlay

            $.ajax ({
                url: $('#ajax-url').val(),
                type: 'POST',
                dataType: 'JSON',
                data: {
                    // the value of data.action is the part AFTER 'wp_ajax_' in
                    // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                    action: 'verify_password',
                    // ANY other properties of data are passed to your_function()
                    // in the PHP global $_REQUEST (or $_POST in this case)
                    password : pw.val(),
                    uid : userid.val(),
                    remember : $('#input-pw input[name="remember"]').is(':checked') ? '1' : '0'
                    },
                beforeSend : function()    {           

                },
                success: function (resp) {
                    if(resp.success){
                        if(resp.data[0]){
                            $.fancybox.close();
                            Theme.userid = userid.val(); //set global user id
                            Theme.markVerified(Theme.userid);

                            $('#pro-actions.pop-container').css('display', 'flex');
                            Theme.popClose($);
                            
                            Theme.getProListActions($);

                            Theme.removeOverlay($);
                        }else{
                            notice.html('Incorrect Password for '+resp.data[1]+'. Reset and Generate New Password <a href="javascript:;" id="reset-pass">Here</a>.');
                            notice.show();

                            $('#reset-pass').unbind();
                            $('#reset-pass').click(function(){
                                Theme.generateNewPassword($, userid.val());
                            });

                            Theme.removeOverlay($);
                        }
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    // this error case means that the ajax call, itself, failed, e.g., a syntax error
                    // in your_function()
                    console.log('Request failed: ' + thrownError.message) ;
                    Theme.removeOverlay($);
                    },
            });


        });
    },

    getProListActions: function($){
        const groupid = $('input#groupid-val');

        $.ajax ({
            url: $('#ajax-url').val(),
            type: 'POST',
            dataType: 'JSON',
            data: {
                // the value of data.action is the part AFTER 'wp_ajax_' in
                // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                action: 'get_pro_list',
                // ANY other properties of data are passed to your_function()
                // in the PHP global $_REQUEST (or $_POST in this case)
                gid : groupid.val()
                },
            beforeSend : function()    {           

            },
            success: function (resp) {
                if(resp.success){
                    console.log(resp);

                    $('#pro-actions .pop-container__content').html(resp.data);
                    Theme.initProButtons($)
                }else{
                    $('#pro-actions .pop-container__content').html($('<p>').text(resp.data || 'Please enter the moderator password again.'));
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                // this error case means that the ajax call, itself, failed, e.g., a syntax error
                // in your_function()
                console.log('Request failed: ' + thrownError.message) ;
                Theme.removeOverlay($);
                },
        });
    },

    initProButtons: function($){
        var gid = $('input#groupid-val').val();

        $('#pro-actions').off('click.mmPro').on('click.mmPro', '[data-btn]', function(e){
            e.preventDefault();

            var btn = $(this).attr('data-btn'),
                panel = $('#pro-actions .mm-prot'),
                credits = parseInt(panel.attr('data-credits'), 10) || 0;

            // Shuffling with broken exclusion rules would fail: send the organizer to fix them first.
            if ((btn === 'shuffle' || btn === 'reshuffle') && panel.attr('data-ex-ok') === '0') {
                Theme.confirmSheet({
                    title: 'Fix your exclusions first',
                    body: 'With the current members, your exclusion rules make a fair shuffle impossible. This usually happens after someone was removed. Remove a rule (or add members), then shuffle.',
                    icon: 'fa-solid fa-user-slash',
                    confirm: 'Open exclusions',
                    onConfirm: function(done){ done(); Theme.proExclusions($, gid); }
                });
                return;
            }

            if (btn === 'members') Theme.proMembers($, gid);
            if (btn === 'themes') Theme.proThemes($, gid);

            if (btn === 'reveal') {
                var hiding = /^Hide/.test($(this).find('strong').text());
                Theme.confirmSheet(hiding ? {
                    title: 'Hide the reveal?',
                    body: 'The list of who gave to whom will be hidden from the group page again.',
                    icon: 'fa-solid fa-eye-slash',
                    confirm: 'Hide the reveal',
                    onConfirm: function(done){
                        Theme.proApi('reveal', { group_id: gid, op: 'off' }).then(function(){ done(); Theme.toast('Reveal hidden'); setTimeout(function(){ $('#reload').submit(); }, 700); })
                            .catch(function(err){ done(); Theme.toast(err.message, 'error'); });
                    }
                } : {
                    title: 'Reveal who gave to whom?',
                    points: [
                        'Everyone who opens the group page will see <b>all pairs</b>.',
                        'Do this <b>after the gift exchange</b>, so nobody\u2019s surprise is spoiled.',
                        'You can hide it again anytime.'
                    ],
                    check: 'The gift exchange is done',
                    icon: 'fa-solid fa-masks-theater',
                    confirm: 'Reveal to everyone',
                    onConfirm: function(done){
                        Theme.proApi('reveal', { group_id: gid, op: 'on' }).then(function(){ done(); Theme.toast('Revealed! Everyone can see the pairs now.'); setTimeout(function(){ $('#reload').submit(); }, 900); })
                            .catch(function(err){ done(); Theme.toast(err.message, 'error'); });
                    }
                });
            }
            if (btn === 'exclusions') Theme.proExclusions($, gid);
            if (btn === 'autodraw') Theme.proAutoDraw($, gid);
            if (btn === 'gtheme') Theme.proGroupTheme($, gid);
            if (btn === 'comod') Theme.proCoOrganizer($, gid);
            if (btn === 'repeat') Theme.startRepeat($, gid);
            if (btn === 'gifts') Theme.proGifts($, gid);

            if (btn === 'nudge-wishlist' || btn === 'nudge-match') {
                var type = btn === 'nudge-wishlist' ? 'wishlist' : 'match',
                    who = $(this).find('small').text();
                Theme.confirmSheet({
                    title: type === 'wishlist' ? 'Remind members to add a wishlist?' : 'Remind members to open their match?',
                    points: [
                        Theme.esc(who) + '.',
                        'Each gets one friendly email with a button to the group, sent from Manito Manita on your behalf.',
                        'Members who never confirmed their email are skipped.',
                        'You can send this reminder <b>once a day</b>.'
                    ],
                    icon: 'fa-solid fa-envelope',
                    confirm: 'Send reminder',
                    onConfirm: function(done){
                        Theme.proApi('nudge', { group_id: gid, type: type }).then(function(r){
                            done();
                            Theme.toast(r.sent ? 'Reminder sent to ' + r.sent + ' ' + (r.sent === 1 ? 'member' : 'members') : (r.message || 'Nobody needed this reminder'));
                            Theme.getProListActions($);
                        }).catch(function(err){
                            done();
                            Theme.toast(err.message, 'error');
                        });
                    }
                });
            }
            if (btn === 'matches') Theme.proMatches($, gid);
            if (btn === 'export') {
                Theme.confirmSheet({
                    title: 'Download the group spreadsheet?',
                    points: [
                        'Includes names, emails, <b>matches</b>, wishlists and <b>home addresses / contact details</b>.',
                        'Keep the file private and don\u2019t share it in group chats.',
                        'Delete it once the gift exchange is over.'
                    ],
                    check: 'I\u2019ll keep members\u2019 personal details private',
                    icon: 'fa-solid fa-file-arrow-down',
                    confirm: 'Download spreadsheet',
                    onConfirm: function(done){ done(); Theme.proExport($, gid, panel.attr('data-group-name') || 'group'); }
                });
            }

            if (btn === 'shuffle') {
                var unconfirmed = parseInt(panel.attr('data-unconfirmed'), 10) || 0;
                var pts = [
                    'Everyone gets their match <b>by email right away</b>.',
                    '<b>No one can join</b> after the shuffle unless you unshuffle (' + credits + ' ' + (credits === 1 ? 'credit' : 'credits') + ' left).',
                    'Exclusions you set are respected.'
                ];
                if (unconfirmed) pts.push('<b>' + unconfirmed + ' ' + (unconfirmed === 1 ? 'member hasn\u2019t' : 'members haven\u2019t') + ' confirmed their email</b> and may not receive it (they can still see it on the group page).');
                Theme.confirmSheet({
                    title: 'Shuffle the group now?',
                    points: pts,
                    check: 'Everyone who should be in the group has joined',
                    icon: 'fa-solid fa-shuffle',
                    confirm: 'Shuffle now',
                    onConfirm: function(done){ done(); Theme.initMatching($, gid); }
                });
            }

            if (btn === 'unshuffle') {
                Theme.confirmSheet({
                    title: 'Unshuffle the group?',
                    points: [
                        '<b>Everyone\u2019s current match is cancelled.</b>',
                        'The group opens again so <b>more people can join</b>.',
                        'Members get an email telling them <b>not to buy a gift yet</b>.',
                        'Gift status is cleared. Shuffle again when everyone is in.'
                    ],
                    credit: credits,
                    check: 'I understand everyone\u2019s match will be cancelled',
                    icon: 'fa-solid fa-rotate-left',
                    confirm: 'Unshuffle (use 1 credit)',
                    danger: true,
                    onConfirm: function(done){
                        Theme.proApi('unshuffle-group', { group_id: gid }).then(function(){
                            done();
                            Theme.toast('Group unshuffled. New members can join now.');
                            setTimeout(function(){ $('#reload').submit(); }, 900); // Join opens again
                        }).catch(function(err){
                            done();
                            Theme.toast(err.message, 'error');
                        });
                    }
                });
            }

            if (btn === 'reshuffle') {
                Theme.confirmSheet({
                    title: 'Reshuffle now?',
                    points: [
                        '<b>Same members, new matches</b> for everyone.',
                        'Everyone gets their new match <b>by email right away</b>.',
                        'Anyone who already bought a gift for their current match will need a new one.',
                        'The group stays closed to new members (use Unshuffle for that).'
                    ],
                    credit: credits,
                    check: 'No one has bought a gift for their current match yet',
                    icon: 'fa-solid fa-shuffle',
                    confirm: 'Reshuffle (use 1 credit)',
                    danger: true,
                    onConfirm: function(done){
                        Theme.proApi('unshuffle-group', { group_id: gid, notify: false }).then(function(){
                            done();
                            Theme.initMatching($, gid); // shuffles again and reloads the dashboard
                        }).catch(function(err){
                            done();
                            Theme.toast(err.message, 'error');
                        });
                    }
                });
            }

            if (btn === 'add-credits') {
                $('#pro-credits').css('display', 'flex'); Theme.lockScroll($);
                Theme.popClose($);
                Theme.track('view_promotion', { creative_slot: 'credits', promotion_name: 'unshuffle_credits' });
            }
        });

        $('#pro-actions').off('click.mmProBack').on('click.mmProBack', '.mm-prov__back', function(e){
            e.preventDefault();
            $('#pro-actions .pop-container__content').html('<p class="mm-prot__loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading PRO tools&hellip;</p>');
            Theme.getProListActions($); // fresh counts after edits / removals
        });
    },

    /* ---------- Small UI helpers (PRO tools) ---------- */

    esc: function(v){
        return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    },

    /**
     * Simple message sheet with one OK button (body is trusted HTML).
     */
    infoSheet: function($, title, body, icon){
        $('#mm-info').remove();
        var box = $('<div id="mm-info" class="pop-container mm-upsell mm-confirm" role="dialog" aria-modal="true">' +
            '<div class="pop-container__inner mm-upsell__inner"><div class="mm-info__icon"><i></i></div><h2></h2><p class="mm-upsell__lead"></p>' +
            '<div class="mm-upsell__actions"><button type="button" class="mm-upsell__btn mm-upsell__btn--dark">OK, got it</button></div></div></div>');
        box.find('.mm-info__icon i').attr('class', icon || 'fa-solid fa-circle-info');
        box.find('h2').text(title);
        box.find('.mm-upsell__lead').html(body);
        box.appendTo('body').css('display', 'flex');
        box.on('click', function(e){ if (e.target === this) box.remove(); });
        box.find('button').on('click', function(){ box.remove(); }).trigger('focus');
        if (window.FontAwesome && FontAwesome.dom && FontAwesome.dom.i2svg) FontAwesome.dom.i2svg({ node: box[0] });
    },

    toast: function(msg, type){
        var $ = jQuery, wrap = $('#mm-toasts');
        if (!wrap.length) wrap = $('<div id="mm-toasts" aria-live="polite"></div>').appendTo('body');

        var t = $('<div class="mm-toast"></div>').addClass(type ? 'mm-toast--' + type : '').text(msg).appendTo(wrap);
        setTimeout(function(){ t.addClass('is-in'); }, 10);
        setTimeout(function(){ t.removeClass('is-in'); setTimeout(function(){ t.remove(); }, 300); }, 4000);
    },

    /**
     * Confirm dialog. o = {
     *   title, body (HTML, escape user data), points: [HTML,...] (what will happen),
     *   credit: number of unshuffle credits before this action (shows the meter),
     *   check: 'I understand...' (must be ticked before confirming), confirm, danger, icon, onConfirm(done)
     * }
     */
    confirmSheet: function(o){
        var $ = jQuery;
        $('#mm-confirm').remove();

        var box = $('<div id="mm-confirm" class="pop-container mm-upsell mm-confirm" role="alertdialog" aria-modal="true">' +
            '<div class="pop-container__inner mm-upsell__inner"><div class="mm-confirm__icon"><i></i></div><h2></h2><div class="mm-confirm__body"></div>' +
            '<div class="mm-upsell__actions"><button type="button" class="mm-upsell__btn mm-confirm__ok"></button>' +
            '<button type="button" class="mm-upsell__btn mm-upsell__btn--ghost mm-confirm__no">Cancel</button></div></div></div>');

        var body = box.find('.mm-confirm__body'), ok = box.find('.mm-confirm__ok');

        box.find('.mm-confirm__icon').toggleClass('is-danger', !!o.danger)
            .find('i').attr('class', o.icon || (o.danger ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-question'));
        box.find('h2').text(o.title);

        if (o.body) body.append($('<p class="mm-upsell__lead"></p>').html(o.body));
        if (o.points && o.points.length) {
            var ul = $('<ul class="mm-confirm__points"></ul>');
            o.points.forEach(function(p){ ul.append($('<li></li>').html(p)); });
            body.append(ul);
        }
        if (typeof o.credit === 'number') body.append(Theme.creditMeter(o.credit));
        if (o.extra) body.append($('<div class="mm-confirm__extra"></div>').html(o.extra));
        if (o.check) {
            body.append('<label class="mm-confirm__check"><input type="checkbox"> <span></span></label>');
            body.find('.mm-confirm__check span').text(o.check);
        }

        ok.text(o.confirm || 'Continue').addClass(o.danger ? 'mm-upsell__btn--danger' : 'mm-upsell__btn--dark');
        if (o.check) {
            ok.prop('disabled', true);
            body.find('.mm-confirm__check input').on('change', function(){ ok.prop('disabled', !this.checked); });
        }

        box.appendTo('body').css('display', 'flex');
        if (window.FontAwesome && FontAwesome.dom && FontAwesome.dom.i2svg) FontAwesome.dom.i2svg({ node: box[0] });

        box.on('click', function(e){ if (e.target === this) box.remove(); });
        box.find('.mm-confirm__no').on('click', function(){ box.remove(); });
        ok.on('click', function(){
            $(this).prop('disabled', true).text('Please wait\u2026');
            o.onConfirm(function(){ box.remove(); });
        });
        (o.check ? box.find('.mm-confirm__no') : ok).trigger('focus');
    },

    /**
     * "Unshuffle credits 3 -> 2" meter for confirm sheets.
     */
    creditMeter: function(left){
        var after = Math.max(0, left - 1), dots = '';
        for (var i = 0; i < Math.max(left, 3); i++) dots += '<i class="' + (i < after ? 'is-on' : (i < left ? 'is-use' : '')) + '"></i>';

        return '<div class="mm-credit">' +
            '<div class="mm-credit__row"><span>Unshuffle credits</span><b>' + left + ' &rarr; ' + after + ' left</b></div>' +
            '<div class="mm-credit__dots">' + dots + '</div>' +
            (after === 0
                ? '<p class="mm-credit__warn">This is your <b>last unshuffle</b>. After this you can add 3 more for &#8369;99.</p>'
                : '<p>This uses <b>1 credit</b>. Credits don\u2019t expire.</p>') +
            '</div>';
    },

    proApi: function(path, body){
        return fetch('/wp-json/custom-webhook/v1/' + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); }).then(function(d){
            if (!d || d.success === false || d.code) throw new Error((d && d.message) || 'Something went wrong. Please try again.');
            return d;
        });
    },

    /**
     * Show a PRO tool inside the PRO Actions sheet (with a back button to the tool list).
     */
    proView: function($, title, html){
        var content = $('#pro-actions .pop-container__content');
        content.html('<div class="mm-prov"><button type="button" class="mm-prov__back"><i class="fa-solid fa-chevron-left"></i> PRO tools</button>' +
            '<h3 class="mm-prov__title">' + Theme.esc(title) + '</h3><div class="mm-prov__body">' + html + '</div></div>');
        $('#pro-actions .mm-upsell__inner').scrollTop(0);
        return content.find('.mm-prov__body');
    },

    proLoading: '<p class="mm-prot__loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading&hellip;</p>',

    proSearch: function(body, placeholder){
        body.find('.mm-prov__search input').attr('placeholder', placeholder).on('input', function(){
            var q = jQuery.trim(jQuery(this).val()).toLowerCase(), shown = 0;
            body.find('.mm-prov__row').each(function(){
                var hit = !q || (jQuery(this).attr('data-name') || '').indexOf(q) !== -1;
                jQuery(this).prop('hidden', !hit);
                if (hit) shown++;
            });
            body.find('.mm-prov__none').prop('hidden', shown > 0);
        });
    },

    /* ---------- Members manager ---------- */

    proMembers: function($, gid){
        var body = Theme.proView($, 'Members', Theme.proLoading);

        Theme.proApi('members', { group_id: gid }).then(function(d){
            var matched = d.group.matched, esc = Theme.esc, rows = '';

            d.users.forEach(function(u){
                var name = u.name || u.screen;
                rows += '<li class="mm-prov__row" data-name="' + esc((name + ' ' + u.screen + ' ' + u.email).toLowerCase()) + '">' +
                    '<span class="mm-avatar" aria-hidden="true">' + esc(name.charAt(0).toUpperCase()) + '</span>' +
                    '<span class="mm-prov__txt"><strong>' + esc(name) + '</strong>' +
                    '<small>' + esc(u.screen) + (u.email ? ' &middot; ' + esc(u.email) : '') + '</small>' +
                    '<small>' + (u.wishes ? u.wishes + (u.wishes === 1 ? ' wish' : ' wishes') : 'No wishlist yet') +
                    (u.confirmed ? '' : ' <span class="mm-prov__badge">Email not confirmed</span>') + '</small></span>' +
                    '<span class="mm-prov__acts">' +
                    '<button type="button" class="mm-prov__mini" data-edit="' + u.ID + '"><i class="fa-solid fa-pen"></i> Edit</button>' +
                    (matched ? '' : '<button type="button" class="mm-prov__mini mm-prov__mini--danger" data-remove="' + u.ID + '" data-label="' + esc(name) + '"><i class="fa-solid fa-user-xmark"></i> Remove</button>') +
                    '</span></li>';
            });

            body.html(
                '<div class="mm-prov__bar"><label class="mm-prov__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" autocomplete="off"></label>' +
                '<button type="button" class="mm-prov__mini" data-copy-emails><i class="fa-regular fa-copy"></i> Copy emails</button></div>' +
                '<p class="mm-prov__meta">' + d.users.length + ' ' + (d.users.length === 1 ? 'member' : 'members') +
                (matched ? ' &middot; members can&rsquo;t be removed after the shuffle (unshuffle first)' : '') + '</p>' +
                '<ul class="mm-prov__list">' + (rows || '<li class="mm-prov__empty">No members yet. Share the group link to invite people.</li>') + '</ul>' +
                '<p class="mm-prov__none" hidden>No member matches that search.</p>'
            );

            Theme.proSearch(body, 'Search name, screen name or email');

            body.find('[data-copy-emails]').on('click', function(){
                var list = d.users.map(function(u){ return u.email; }).filter(Boolean).join(', ');
                if (navigator.clipboard && list) {
                    navigator.clipboard.writeText(list).then(function(){ Theme.toast('Emails copied'); });
                } else {
                    Theme.toast(list || 'No emails yet');
                }
            });

            body.find('[data-edit]').on('click', function(){
                Theme.action = 'edit-member';
                Theme.userid = $(this).attr('data-edit');
                Theme.initCustomPopup($);
            });

            body.find('[data-remove]').on('click', function(){
                var row = $(this).closest('.mm-prov__row'), id = $(this).attr('data-remove'), label = $(this).attr('data-label');
                Theme.confirmSheet({
                    title: 'Remove ' + label + '?',
                    points: [
                        'They\u2019re removed from the group and <b>won\u2019t be part of the shuffle</b>.',
                        'Their wishlist and comments are removed with them.',
                        '<b>This can\u2019t be undone.</b> They can join again with the group link.'
                    ],
                    check: 'Remove ' + label + ' from the group',
                    confirm: 'Remove member',
                    danger: true,
                    onConfirm: function(done){
                        Theme.proApi('trash-user', { user_id: id }).then(function(){
                            done();
                            row.remove();
                            Theme.toast(label + ' was removed');
                            Theme.ajaxCardUsers($);
                        }).catch(function(err){
                            done();
                            Theme.toast(err.message, 'error');
                        });
                    }
                });
            });
        }).catch(function(err){
            body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>');
        });
    },

    /* ---------- Exclusions (who can't get whom) ---------- */

    proExclusions: function($, gid){
        var body = Theme.proView($, 'Exclusions', Theme.proLoading);

        function render(d){
            var esc = Theme.esc, opts = '<option value="">Choose</option>', rules = '';

            d.members.forEach(function(m){
                opts += '<option value="' + m.ID + '">' + esc(m.name) + (m.screen && m.screen !== m.name ? ' (' + esc(m.screen) + ')' : '') + '</option>';
            });

            d.rules.forEach(function(r){
                rules += '<li class="mm-prov__row mm-prov__match">' +
                    '<span><strong>' + esc(r[0].name) + '</strong><small>' + esc(r[0].screen) + '</small></span>' +
                    '<i class="fa-solid fa-arrows-left-right" aria-label="won\u2019t get each other"></i>' +
                    '<span><strong>' + esc(r[1].name) + '</strong><small>' + esc(r[1].screen) + '</small></span>' +
                    '<button type="button" class="mm-prov__mini mm-prov__mini--danger" data-a="' + r[0].ID + '" data-b="' + r[1].ID + '" aria-label="Remove rule"><i class="fa-solid fa-xmark"></i></button></li>';
            });

            body.html(
                (d.health && !d.health.ok ? '<div class="mm-prov__alert"><i class="fa-solid fa-triangle-exclamation"></i><span>' + d.health.message + '</span></div>' : '') +
                '<p class="mm-prov__meta">Pick two people who shouldn\u2019t get each other, like couples or family. It works both ways. ' +
                'Everyone must still have at least 2 people they could get, so the shuffle stays a surprise.' +
                (d.matched ? ' <b>Already shuffled:</b> rules apply the next time you shuffle (after an unshuffle).' : '') + '</p>' +
                '<div class="mm-prov__pair"><select data-ex-a>' + opts + '</select><span>&amp;</span><select data-ex-b>' + opts + '</select></div>' +
                '<button type="button" class="mm-upsell__btn mm-upsell__btn--dark" data-ex-add>Add rule</button>' +
                '<h4 class="mm-prot__group">' + d.rules.length + ' ' + (d.rules.length === 1 ? 'rule' : 'rules') + '</h4>' +
                (rules ? '<ul class="mm-prov__list">' + rules + '</ul>' : '<p class="mm-prov__empty">No rules yet. Anyone can get anyone (except themselves).</p>')
            );

            body.find('[data-ex-add]').on('click', function(){
                var a = body.find('[data-ex-a]').val(), b = body.find('[data-ex-b]').val(), btn = $(this);
                if (!a || !b || a === b) { Theme.toast('Pick two different members', 'error'); return; }

                btn.prop('disabled', true).text('Saving\u2026');
                Theme.proApi('exclusions', { group_id: gid, op: 'add', a: a, b: b }).then(function(r){
                    render(r);
                    Theme.toast('Rule added');
                }).catch(function(err){
                    btn.prop('disabled', false).text('Add rule');
                    Theme.toast(err.message, 'error');
                });
            });

            body.find('[data-a]').on('click', function(){
                var a = $(this).attr('data-a'), b = $(this).attr('data-b'),
                    names = $(this).closest('.mm-prov__row').find('strong').map(function(){ return $(this).text(); }).get();
                Theme.confirmSheet({
                    title: 'Remove this rule?',
                    body: Theme.esc(names[0] || '') + ' and ' + Theme.esc(names[1] || '') + ' could get each other in the next shuffle.',
                    icon: 'fa-solid fa-user-slash',
                    confirm: 'Remove rule',
                    danger: true,
                    onConfirm: function(done){
                        Theme.proApi('exclusions', { group_id: gid, op: 'remove', a: a, b: b }).then(function(r){
                            done();
                            render(r);
                            Theme.toast('Rule removed');
                        }).catch(function(err){ done(); Theme.toast(err.message, 'error'); });
                    }
                });
            });
        }

        Theme.proApi('exclusions', { group_id: gid, op: 'list' }).then(render).catch(function(err){
            body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>');
        });
    },

    /* ---------- Auto-shuffle (scheduled) ---------- */

    proAutoDraw: function($, gid){
        var body = Theme.proView($, 'Auto-shuffle', Theme.proLoading);

        function render(d){
            var now = new Date(Date.now() + 60 * 60 * 1000), pad = function(n){ return (n < 10 ? '0' : '') + n; },
                min = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':00';

            body.html(
                '<p class="mm-prov__meta">Pick when the group should be shuffled. Everyone gets their match by email at that time, and you get a confirmation. ' +
                'Members can keep joining until then.</p>' +
                (d.label ? '<div class="mm-prov__sched"><i class="fa-solid fa-calendar-check"></i><span>Scheduled for <b>' + Theme.esc(d.label) + '</b></span></div>' : '') +
                '<label class="mm-prov__field">Shuffle on<input type="datetime-local" data-ad-at min="' + min + '" value="' + Theme.esc(d.at || '') + '"></label>' +
                '<button type="button" class="mm-upsell__btn mm-upsell__btn--dark" data-ad-save>' + (d.label ? 'Update schedule' : 'Schedule auto-shuffle') + '</button>' +
                (d.label ? '<button type="button" class="mm-upsell__btn mm-upsell__btn--ghost mm-prov__gap" data-ad-clear>Cancel auto-shuffle</button>' : '') +
                '<p class="mm-prov__meta mm-prov__gap">Needs at least 3 members at that time (now ' + d.members + '). Times are Philippine time.</p>'
            );

            body.find('[data-ad-save]').on('click', function(){
                var at = body.find('[data-ad-at]').val();
                if (!at) { Theme.toast('Pick a date and time', 'error'); return; }

                var when = new Date(at).toLocaleString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                Theme.confirmSheet({
                    title: 'Schedule the auto-shuffle?',
                    points: [
                        'On <b>' + Theme.esc(when) + '</b> the group is shuffled automatically.',
                        'Everyone gets their match by email and <b>no one can join after that</b>.',
                        'It needs at least 3 members then. You\u2019ll get an email with the result.',
                        'You can change or cancel it any time before then.'
                    ],
                    icon: 'fa-solid fa-calendar-check',
                    confirm: 'Schedule',
                    onConfirm: function(done){
                        Theme.proApi('autodraw', { group_id: gid, op: 'set', at: at }).then(function(r){
                            done();
                            render(r);
                            Theme.toast('Auto-shuffle scheduled');
                        }).catch(function(err){
                            done();
                            Theme.toast(err.message, 'error');
                        });
                    }
                });
            });

            body.find('[data-ad-clear]').on('click', function(){
                Theme.confirmSheet({
                    title: 'Cancel the auto-shuffle?',
                    body: 'The group won\u2019t be shuffled automatically. You can shuffle it yourself from PRO Actions, or schedule a new time.',
                    icon: 'fa-solid fa-calendar-xmark',
                    confirm: 'Cancel auto-shuffle',
                    danger: true,
                    onConfirm: function(done){
                        Theme.proApi('autodraw', { group_id: gid, op: 'clear' }).then(function(r){
                            done();
                            render(r);
                            Theme.toast('Auto-shuffle cancelled');
                        }).catch(function(err){ done(); Theme.toast(err.message, 'error'); });
                    }
                });
            });
        }

        Theme.proApi('autodraw', { group_id: gid, op: 'get' }).then(render).catch(function(err){
            body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>');
        });
    },

    /* ---------- Gift status board ---------- */

    proGifts: function($, gid){
        var body = Theme.proView($, 'Gift status', Theme.proLoading);

        Theme.proApi('gifts', { group_id: gid }).then(function(d){
            var esc = Theme.esc, rows = '', labels = { '': 'Not yet', bought: 'Bought', given: 'Given' };

            d.members.forEach(function(m){
                rows += '<li class="mm-prov__row" data-name="' + esc((m.name + ' ' + m.screen).toLowerCase()) + '">' +
                    '<span class="mm-avatar" aria-hidden="true">' + esc((m.name || '?').charAt(0).toUpperCase()) + '</span>' +
                    '<span class="mm-prov__txt"><strong>' + esc(m.name) + '</strong><small>' + esc(m.screen) + (m.viewed ? '' : ' &middot; hasn\u2019t opened their match') + '</small></span>' +
                    '<span class="mm-prov__state mm-prov__state--' + (m.gift || 'none') + '">' + labels[m.gift || ''] + '</span></li>';
            });

            var pct = d.total ? Math.round((d.bought + d.given) / d.total * 100) : 0;

            body.html(
                '<div class="mm-prov__progress"><div><b>' + (d.bought + d.given) + ' of ' + d.total + '</b> have their gift ready</div>' +
                '<span><i style="width:' + pct + '%"></i></span><small>' + d.bought + ' bought &middot; ' + d.given + ' given</small></div>' +
                '<p class="mm-prov__meta">Members mark this on their See Match screen. You only see whether they\u2019re done, never who they drew.</p>' +
                '<div class="mm-prov__bar"><label class="mm-prov__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" autocomplete="off"></label></div>' +
                '<ul class="mm-prov__list">' + rows + '</ul><p class="mm-prov__none" hidden>No member matches that search.</p>'
            );
            Theme.proSearch(body, 'Search a name');
        }).catch(function(err){
            body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>');
        });
    },

    /**
     * Member: "Your gift" buttons on See Match (not yet / bought / given).
     */
    initGiftStatus: function($){
        $(document).off('click.mmGift').on('click.mmGift', '[data-mm-gift-status] button', function(){
            var btn = $(this), box = btn.closest('[data-mm-gift-status]'), status = btn.attr('data-status');

            box.find('button').attr('aria-pressed', 'false');
            btn.attr('aria-pressed', 'true');

            $.post($('#ajax-url').val(), { action: 'mm_gift_status', uid: box.attr('data-uid'), status: status, nonce: box.attr('data-nonce') })
                .done(function(resp){
                    if (resp && resp.success) {
                        Theme.toast(status === 'given' ? 'Nice! Marked as given' : (status === 'bought' ? 'Marked as bought' : 'Updated'));
                        Theme.track('gift_status', { status: status || 'none' });
                    } else {
                        Theme.toast((resp && resp.data) || 'Could not save. Please try again.', 'error');
                    }
                })
                .fail(function(){ Theme.toast('Network error. Please try again.', 'error'); });
        });
    },

    /**
     * Anonymous Q&A on See Match (markup: QA::askSection / QA::inboxSection). Each send replaces its
     * section with the fresh HTML from the server. ?qa=<member id> (from the emails) opens that
     * member's password prompt for See Match.
     */
    initQA: function($){
        $(document).off('click.mmQaChip').on('click.mmQaChip', '[data-mm-qa-chip]', function(){
            var box = $(this).closest('form').find('textarea');
            box.val($('<div>').html($(this).html()).text()).trigger('focus');
        });

        $(document).off('submit.mmQa').on('submit.mmQa', '[data-mm-qa-ask], [data-mm-qa-answer]', function(e){
            e.preventDefault();
            var form = $(this), sec = form.closest('[data-mm-qa]'), asking = form.is('[data-mm-qa-ask]');
            var text = $.trim(form.find('textarea').val()), btn = form.find('button[type=submit]');
            if (text.length < (asking ? 3 : 1)) { Theme.toast(asking ? 'Please type a question.' : 'Please type your answer.', 'error'); return; }

            btn.prop('disabled', true).addClass('is-loading');
            var data = { action: asking ? 'mm_qa_ask' : 'mm_qa_answer', uid: sec.attr('data-uid'), nonce: sec.attr('data-nonce') };
            if (asking) { data.q = text; } else { data.a = text; data.qid = form.attr('data-qid'); }

            $.post($('#ajax-url').val(), data)
                .done(function(resp){
                    if (resp && resp.success) {
                        sec.replaceWith(resp.data);
                        Theme.toast(asking ? 'Sent! We\u2019ll email you when they answer' : 'Answer sent');
                        Theme.track(asking ? 'qa_ask' : 'qa_answer', {});
                    } else {
                        btn.prop('disabled', false).removeClass('is-loading');
                        Theme.toast($('<div>').html((resp && resp.data) || 'Could not send. Please try again.').text(), 'error');
                    }
                })
                .fail(function(){ btn.prop('disabled', false).removeClass('is-loading'); Theme.toast('Network error. Please try again.', 'error'); });
        });

        var m = /[?&]qa=(\d+)/.exec(window.location.search);
        if (m) {
            setTimeout(function(){
                var btn = $('.user-action[data-action="see-match"][user-data="' + m[1] + '"]').first();
                if (btn.length) { $('html, body').animate({ scrollTop: btn.closest('.card').offset().top - 90 }, 300); btn.trigger('click'); }
            }, 900);
        }
    },

    /* ---------- Account: lost group link, reset password form, sign out on this device ---------- */

    initAccount: function($){
        var ajax = $('#ajax-url').val();

        // "Lost your group link?" forms (homepage, reset page, 404)
        $(document).off('submit.mmFind').on('submit.mmFind', '[data-mm-find]', function(e){
            e.preventDefault();
            var f = $(this), msg = f.find('.mm-find__msg'), btn = f.find('button[type="submit"]'), email = $.trim(f.find('[name="email"]').val());
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { msg.addClass('is-error').text('Please enter a valid email address.'); return; }
            btn.prop('disabled', true);
            msg.removeClass('is-error').text('Sending\u2026');
            $.post(ajax, { action: 'mm_find_groups', email: email }).done(function(r){
                msg.toggleClass('is-error', !(r && r.success)).text($('<div>').html((r && r.data) || 'Something went wrong. Please try again.').text());
                if (r && r.success) Theme.track('find_groups', { slot: f.attr('data-slot') || '' });
            }).fail(function(){ msg.addClass('is-error').text('Network error. Please try again.'); })
              .always(function(){ btn.prop('disabled', false); });
        });
        $(document).off('click.mmFindOpen').on('click.mmFindOpen', '[data-mm-find-open]', function(){
            var box = $('[data-mm-find-box]').prop('hidden', false);
            box.find('input[type="email"]').trigger('focus');
        });

        // Group page without a working link: paste the full link
        $(document).off('submit.mmPaste').on('submit.mmPaste', '[data-mm-paste-link]', function(e){
            e.preventDefault();
            var f = $(this), url = null;
            try { url = new URL($.trim(f.find('input').val())); } catch (err) {}
            var ok = url && url.hostname.replace(/^www\./, '') === window.location.hostname.replace(/^www\./, '')
                && url.pathname.indexOf('group-dashboard') !== -1 && url.searchParams.get('gid') && url.searchParams.get('pw');
            f.find('[data-mm-paste-error]').prop('hidden', !!ok);
            if (ok) { Theme.track('paste_group_link', {}); window.location.href = url.href; }
        });

        // Reset password: show/hide and a quick check before sending
        var reset = $('[data-mm-reset]');
        if (reset.length) {
            reset.find('[data-mm-pwshow]').on('click', function(){
                var ins = reset.find('input[type="password"], input[data-shown]'), show = $(this).text() === 'Show';
                ins.attr('type', show ? 'text' : 'password').attr('data-shown', show ? '1' : null);
                $(this).text(show ? 'Hide' : 'Show');
            });
            reset.on('submit', function(e){
                var a = reset.find('[name="new-pass"]').val(), b = reset.find('[name="new-pass2"]').val(), hint = reset.find('[data-mm-pwhint]');
                if (a.length < 6) { e.preventDefault(); hint.addClass('is-error').text('Please use at least 6 characters.'); return; }
                if (a !== b) { e.preventDefault(); hint.addClass('is-error').text('The two passwords don\u2019t match.'); return; }
                reset.find('button[type="submit"]').prop('disabled', true).text('Saving\u2026');
            });
        }

        // Sign out on this device (forget the remembered members/organizer here)
        var out = $('[data-mm-forget]');
        if (out.length && (window.mmVerified || []).length) out.prop('hidden', false);
        out.on('click', function(){
            $.post(ajax, { action: 'mm_forget_device' }).always(function(){
                window.mmVerified = [];
                Theme.toast('Signed out on this device');
                setTimeout(function(){ window.location.reload(); }, 600);
            });
        });
    },

    /* ---------- Ask Manito: help chat (SupportBot) + social follow tracking ---------- */

    initSupport: function($){
        // Every follow button on the site (footer, homepage, See Match, wishlist, chat)
        $(document).off('click.mmSocial').on('click.mmSocial', '[data-mm-social]', function(){
            Theme.track('social_click', { network: $(this).attr('data-mm-social'), slot: $(this).attr('data-mm-slot') || '' });
        });

        var root = $('[data-mm-support]');
        if (!root.length) return;

        var ajax = $('#ajax-url').val(), page = root.attr('data-page') || 'other',
            panel = root.find('.mm-support__panel'), fab = root.find('.mm-support__fab'),
            suggest = [], socials = [], built = false, busy = false, store = 'mm_support_v1',
            state = { cid: 0, key: '', msgs: [], social: false, at: 0 };
        try { suggest = JSON.parse(root.attr('data-suggest') || '[]'); socials = JSON.parse(root.attr('data-socials') || '[]'); } catch (e) {}
        try { var saved = JSON.parse(localStorage.getItem(store) || 'null'); if (saved && saved.at > Date.now() - 7 * 864e5) state = saved; } catch (e) {}
        var save = function(){ state.at = Date.now(); state.msgs = state.msgs.slice(-40); try { localStorage.setItem(store, JSON.stringify(state)); } catch (e) {} };
        var plain = function(h){ return $('<div>').html(h || '').text(); };

        // **bold**, "- " lists, links (site pages and our socials), line breaks
        var fmt = function(text){
            var out = [], list = [];
            var line = function(l){
                l = Theme.esc(plain(l)).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
                return l.replace(/(https?:\/\/[^\s<]+|(?:www\.)?(?:manitomanita\.com|facebook\.com|instagram\.com|tiktok\.com)\/[^\s<]*)/gi, function(u){
                    var href = /^https?:/i.test(u) ? u : 'https://' + u, ours = /manitomanita\.com/i.test(href);
                    return '<a href="' + href.replace(/[.,)]+$/, '') + '"' + (ours ? '' : ' target="_blank" rel="noopener"') + '>' + u + '</a>';
                });
            };
            String(text || '').split(/\n/).forEach(function(l){
                if (/^\s*[-*]\s+/.test(l)) { list.push('<li>' + line(l.replace(/^\s*[-*]\s+/, '')) + '</li>'); return; }
                if (list.length) { out.push('<ul>' + list.join('') + '</ul>'); list = []; }
                if ($.trim(l)) out.push('<p>' + line(l) + '</p>');
            });
            if (list.length) out.push('<ul>' + list.join('') + '</ul>');
            return out.join('');
        };

        var socialHtml = function(cls){
            return '<div class="' + cls + '">' + socials.map(function(s){
                return '<a href="' + Theme.esc(s[1]) + '" target="_blank" rel="noopener" data-mm-social="' + Theme.esc(s[0].toLowerCase()) + '" data-mm-slot="chat" aria-label="' + Theme.esc(s[0]) + '"><i class="' + Theme.esc(s[2]) + '" aria-hidden="true"></i><span>' + Theme.esc(s[0]) + '</span></a>';
            }).join('') + '</div>';
        };

        var list, input, chipsData = [];
        var scroll = function(){ list.scrollTop(list[0].scrollHeight); };
        // Suggestions live in the conversation: a "Popular questions" list under the welcome message,
        // then "Related" pills under each answer.
        var renderChips = function(){
            list.find('.mm-support__suggest').remove();
            if (!chipsData.length) return;
            var first = !state.msgs.length;
            list.append('<div class="mm-support__suggest' + (first ? ' is-first' : '') + '"><p class="mm-support__suggest-label">' + (first ? 'Popular questions' : 'Related questions') + '</p>' +
                chipsData.map(function(q){ return '<button type="button" data-mm-sq><span>' + Theme.esc(q) + '</span>' + (first ? '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>' : '') + '</button>'; }).join('') + '</div>');
            scroll();
        };
        var setChips = function(qs){ chipsData = qs || []; renderChips(); };
        var bubble = function(m, i){
            var bot = m.r === 'b';
            var html = '<div class="mm-support__msg mm-support__msg--' + (bot ? 'bot' : 'me') + '" data-i="' + i + '"><div class="mm-support__bubble">' + (bot ? fmt(m.t) : '<p>' + Theme.esc(m.t) + '</p>') + '</div>';
            if (bot && m.turn !== undefined && m.turn !== null) {
                html += '<div class="mm-support__vote"' + (m.vote ? ' data-voted="' + m.vote + '"' : '') + '>' +
                    '<button type="button" data-mm-vote="up" aria-label="Helpful"' + (m.vote === 'up' ? ' class="is-on"' : '') + '><i class="fa-regular fa-thumbs-up"></i></button>' +
                    '<button type="button" data-mm-vote="down" aria-label="Not helpful"' + (m.vote === 'down' ? ' class="is-on"' : '') + '><i class="fa-regular fa-thumbs-down"></i></button></div>';
            }
            if (bot && m.handoff) html += '<button type="button" class="mm-support__person" data-mm-handoff><i class="fa-solid fa-envelope"></i> Talk to a person</button>';
            if (m.social) html += '<div class="mm-support__social-card"><p><b>Glad that helped!</b> Follow us for gift ideas and party themes.</p>' + socialHtml('mm-support__social') + '</div>';
            return html + '</div>';
        };
        var render = function(){
            list.html('<div class="mm-support__msg mm-support__msg--bot"><div class="mm-support__bubble"><p>Hi! I\u2019m <b>Manito</b> \ud83d\udc4b Ask me anything about creating a group, joining, the shuffle, wishlists or PRO.</p></div></div>' +
                state.msgs.map(bubble).join(''));
            renderChips();
            scroll();
        };
        var maybeSocial = function(){
            if (state.social) return;
            var answered = state.msgs.filter(function(m){ return m.r === 'b' && m.turn !== undefined; }).length;
            var liked = state.msgs.some(function(m){ return m.vote === 'up'; });
            if (liked || answered >= 3) { state.social = true; state.msgs[state.msgs.length - 1].social = true; }
        };

        var ask = function(q, via){
            q = $.trim(q);
            if (!q || busy) return;
            busy = true;
            state.msgs.push({ r: 'u', t: q });
            save(); render(); setChips([]);
            list.append('<div class="mm-support__msg mm-support__msg--bot mm-support__typing"><div class="mm-support__bubble"><span></span><span></span><span></span></div></div>'); scroll();
            Theme.track('support_ask', { via: via || 'typed', page: page });

            $.post(ajax, { action: 'mm_support_ask', q: q, page: page, cid: state.cid, key: state.key }).done(function(r){
                if (r && r.success) {
                    state.cid = r.data.cid; state.key = r.data.key;
                    state.msgs.push({ r: 'b', t: r.data.answer, turn: r.data.turn, handoff: r.data.handoff });
                    maybeSocial();
                    setChips(r.data.followups && r.data.followups.length ? r.data.followups : suggest);
                } else {
                    state.msgs.push({ r: 'b', t: plain(r && r.data) || 'Something went wrong. Please try again.', handoff: true });
                    setChips(suggest);
                }
            }).fail(function(){
                state.msgs.push({ r: 'b', t: 'I couldn\u2019t connect. Please check your internet and try again.' });
                setChips(suggest);
            }).always(function(){ busy = false; save(); render(); input.trigger('focus'); });
        };

        var handoffForm = function(){
            if (panel.find('.mm-support__form').length) return;
            var lastQ = ''; state.msgs.slice().reverse().some(function(m){ if (m.r === 'u') { lastQ = m.t; return true; } return false; });
            list.append('<form class="mm-support__form" novalidate><p><b>Talk to a person</b><br>Leave your email and Rouie will reply, usually within a day.</p>' +
                '<input type="text" name="name" maxlength="60" placeholder="Your name (optional)" autocomplete="name">' +
                '<input type="email" name="email" maxlength="120" placeholder="Your email" autocomplete="email" required>' +
                '<textarea name="message" rows="3" maxlength="1500" placeholder="How can we help?">' + Theme.esc(lastQ) + '</textarea>' +
                '<input type="text" name="website" class="mm-support__hp" tabindex="-1" autocomplete="off" aria-hidden="true">' +
                '<p class="mm-support__formmsg" role="status"></p>' +
                '<div class="mm-support__formacts"><button type="button" class="mm-support__link" data-mm-handoff-cancel>Cancel</button><button type="submit" class="mm-support__send2">Send</button></div></form>');
            panel.find('.mm-support__form input[name="email"]').trigger('focus'); scroll();
        };

        var build = function(){
            if (built) return;
            built = true;
            panel.html('<div class="mm-support__head"><span class="mm-support__avatar"><i class="fa-solid fa-gift"></i></span><div><strong>Ask Manito</strong><small>Help with Manito Manita &middot; usually instant</small></div>' +
                '<button type="button" class="mm-support__close" data-mm-support-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>' +
                '<div class="mm-support__list" aria-live="polite"></div>' +
                '<div class="mm-support__bottom">' +
                    '<form class="mm-support__composer"><textarea rows="1" maxlength="500" placeholder="Type your question\u2026" aria-label="Your question"></textarea><button type="submit" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button></form>' +
                    '<p class="mm-support__note">Please don\u2019t share passwords or personal details.</p>' +
                    '<div class="mm-support__foot"><button type="button" class="mm-support__person-btn" data-mm-handoff><i class="fa-regular fa-envelope"></i> Talk to a person</button>' + socialHtml('mm-support__icons') + '</div>' +
                '</div>');
            list = panel.find('.mm-support__list'); input = panel.find('.mm-support__composer textarea');
            render();
            setChips(suggest);

            panel.on('click', '[data-mm-sq]', function(){ ask($(this).text(), 'chip'); });
            panel.on('submit', '.mm-support__composer', function(e){ e.preventDefault(); var v = input.val(); input.val('').css('height', ''); ask(v, 'typed'); });
            input.on('keydown', function(e){ if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); panel.find('.mm-support__composer').trigger('submit'); } })
                 .on('input', function(){ this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 110) + 'px'; });
            panel.on('click', '[data-mm-support-close]', function(){ toggle(false); });
            panel.on('click', '[data-mm-handoff]', function(){ handoffForm(); Theme.track('support_handoff_open', { page: page }); });
            panel.on('click', '[data-mm-handoff-cancel]', function(){ $(this).closest('form').remove(); });
            panel.on('click', '[data-mm-vote]', function(){
                var b = $(this), i = parseInt(b.closest('.mm-support__msg').attr('data-i'), 10), m = state.msgs[i], v = b.attr('data-mm-vote');
                if (!m || m.vote) return;
                m.vote = v;
                $.post(ajax, { action: 'mm_support_vote', cid: state.cid, key: state.key, turn: m.turn, vote: v });
                Theme.track('support_vote', { vote: v });
                if (v === 'down') { m.handoff = true; } else { maybeSocial(); }
                save(); render();
            });
            panel.on('submit', '.mm-support__form', function(e){
                e.preventDefault();
                var f = $(this), msg = f.find('.mm-support__formmsg'), btn = f.find('button[type="submit"]').prop('disabled', true);
                $.post(ajax, { action: 'mm_support_handoff', cid: state.cid, key: state.key, page: page, name: f.find('[name="name"]').val(), email: f.find('[name="email"]').val(), message: f.find('[name="message"]').val(), website: f.find('[name="website"]').val() })
                    .done(function(r){
                        if (r && r.success) {
                            if (r.data && r.data.cid) { state.cid = r.data.cid; state.key = r.data.key; }
                            f.remove();
                            state.msgs.push({ r: 'b', t: plain(r.data && r.data.message) || 'Thanks! We\u2019ll reply by email.' });
                            save(); render();
                            Theme.track('support_handoff', { page: page });
                        } else { msg.addClass('is-error').text(plain(r && r.data)); btn.prop('disabled', false); }
                    }).fail(function(){ msg.addClass('is-error').text('Network error. Please try again.'); btn.prop('disabled', false); });
            });
        };

        var toggle = function(open){
            if (open) { build(); panel.prop('hidden', false); root.addClass('is-open'); fab.attr('aria-expanded', 'true'); setTimeout(function(){ input.trigger('focus'); scroll(); }, 50); Theme.track('support_open', { page: page }); }
            else { panel.prop('hidden', true); root.removeClass('is-open'); fab.attr('aria-expanded', 'false'); }
        };

        $(document).off('click.mmSupport').on('click.mmSupport', '[data-mm-support-open]', function(e){ e.preventDefault(); toggle(panel.prop('hidden')); });
        $(document).off('keydown.mmSupport').on('keydown.mmSupport', function(e){ if (e.key === 'Escape' && !panel.prop('hidden')) toggle(false); });

        // "Need help?" label on the first visits only
        try { if (!localStorage.getItem('mm_support_hint')) { root.addClass('show-hint'); localStorage.setItem('mm_support_hint', '1'); setTimeout(function(){ root.removeClass('show-hint'); }, 7000); } } catch (e) {}
    },

    /* ---------- My Wishlist (template-my-wishlist.php, MyWishlist class) ---------- */

    initMyWishlist: function($){
        var root = $('.mm-wl');
        if (!root.length) return;
        var ajax = $('#ajax-url').val(), plain = function(h){ return $('<div>').html(h || '').text(); };

        // Owner: the same editor as the group wishlist
        var editor = root.find('[data-mm-wl-editor]');
        if (editor.length) Theme.initWishlistEditor($, editor);

        // Share box
        var share = root.find('[data-mm-wl-share]');
        root.find('[data-mm-wl-copy]').on('click', function(){
            var input = share.find('[data-mm-wl-link]')[0];
            input.select();
            (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject()).catch(function(){ document.execCommand('copy'); });
            Theme.toast('Link copied');
            Theme.track('wishlist_share', { method: 'copy' });
        });
        if (navigator.share && share.length) {
            root.find('[data-mm-wl-native]').prop('hidden', false).on('click', function(){
                navigator.share({ title: share.attr('data-name') + '\u2019s wishlist', url: share.attr('data-url') }).catch(function(){});
                Theme.track('wishlist_share', { method: 'native' });
            });
        }

        root.find('[data-mm-wl-logout]').on('click', function(){
            $.post(ajax, { action: 'mm_wl_logout' }).always(function(r){ window.location.href = (r && r.data && r.data.url) || '/my-wishlist/'; });
        });
        root.find('[data-mm-wl-delete]').on('click', function(){
            var b = $(this);
            Theme.confirmSheet({
                title: 'Delete your wishlist?',
                body: 'Your list and share link are removed for good. Friends who open the link will see that it\u2019s gone.',
                icon: 'fa-solid fa-trash-can', danger: true, confirm: 'Delete',
                onConfirm: function(done){
                    $.post(ajax, { action: 'mm_wl_delete', id: b.attr('data-id'), nonce: b.attr('data-nonce') }).done(function(r){
                        done();
                        if (r && r.success) window.location.href = r.data.url; else Theme.toast(plain(r && r.data && (r.data.message || r.data)), 'error');
                    }).fail(function(){ done(); Theme.toast('Network error. Please try again.', 'error'); });
                }
            });
        });

        // Landing: tabs, create (with the email code), log in, login link
        root.find('[data-mm-wl-tab]').on('click', function(){
            var t = $(this).attr('data-mm-wl-tab');
            root.find('[data-mm-wl-tab]').removeClass('is-on').attr('aria-selected', 'false');
            $(this).addClass('is-on').attr('aria-selected', 'true');
            root.find('[data-mm-wl-form]').prop('hidden', true).filter('[data-mm-wl-form="' + t + '"]').prop('hidden', false);
        });

        var create = root.find('[data-mm-wl-form="create"]');
        if (create.length) {
            var email = create.find('[name="email"]'), otp = create.find('[data-mm-wl-otp]'), cmsg = create.find('[data-mm-wl-msg]');
            var valid = function(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); };
            email.on('input change', function(){ otp.prop('hidden', !valid($.trim(email.val()))); });
            create.find('[data-mm-wl-send]').on('click', function(){
                var b = $(this).prop('disabled', true);
                cmsg.removeClass('is-error').text('Sending\u2026');
                $.post(ajax, { action: 'mm_send_otp', email: $.trim(email.val()), nonce: create.find('[name="otp_nonce"]').val(), purpose: 'wishlist' }).done(function(r){
                    if (r && r.success) { cmsg.text(plain(r.data)); create.find('[data-mm-wl-codebox]').prop('hidden', false).find('input').trigger('focus'); b.text('Resend code'); setTimeout(function(){ b.prop('disabled', false); }, 30000); }
                    else { cmsg.addClass('is-error').text(plain(r && r.data)); b.prop('disabled', false); }
                }).fail(function(){ cmsg.addClass('is-error').text('Network error. Please try again.'); b.prop('disabled', false); });
            });
            create.on('submit', function(e){
                e.preventDefault();
                var d = { action: 'mm_wl_create', name: $.trim(create.find('[name="name"]').val()), email: $.trim(email.val()), password: create.find('[name="password"]').val(), code: $.trim(create.find('[name="code"]').val()) };
                if (!d.name) { cmsg.addClass('is-error').text('Please enter your name.'); return; }
                if (!valid(d.email)) { cmsg.addClass('is-error').text('Please enter a valid email.'); return; }
                if (!d.code) { cmsg.addClass('is-error').text('Tap \u201cSend verification code\u201d and enter the code we email you.'); return; }
                if ((d.password || '').length < 6) { cmsg.addClass('is-error').text('Please use a password with at least 6 characters.'); return; }
                var btn = create.find('[type="submit"]').prop('disabled', true);
                cmsg.removeClass('is-error').text('Creating your wishlist\u2026');
                $.post(ajax, d).done(function(r){
                    if (r && r.success) { Theme.track('wishlist_create', {}); window.location.href = r.data.url; }
                    else { cmsg.addClass('is-error').text(plain(r && r.data)); btn.prop('disabled', false); }
                }).fail(function(){ cmsg.addClass('is-error').text('Network error. Please try again.'); btn.prop('disabled', false); });
            });
        }

        var login = root.find('[data-mm-wl-form="login"]');
        login.on('submit', function(e){
            e.preventDefault();
            var m = login.find('[data-mm-wl-msg]'), btn = login.find('[type="submit"]').prop('disabled', true);
            $.post(ajax, { action: 'mm_wl_login', email: $.trim(login.find('[name="email"]').val()), password: login.find('[name="password"]').val() }).done(function(r){
                if (r && r.success) window.location.href = r.data.url; else { m.addClass('is-error').text(plain(r && r.data)); btn.prop('disabled', false); }
            }).fail(function(){ m.addClass('is-error').text('Network error. Please try again.'); btn.prop('disabled', false); });
        });
        login.find('[data-mm-wl-magic]').on('click', function(){
            var m = login.find('[data-mm-wl-msg]'), em = $.trim(login.find('[name="email"]').val());
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(em)) { m.addClass('is-error').text('Type your email above first.'); return; }
            $.post(ajax, { action: 'mm_wl_magic', email: em }).done(function(r){ m.removeClass('is-error').text(plain(r && r.data)); });
        });

        // Friends: "I'll get this" (a token in this browser lets the same person undo it)
        var list = root.find('[data-mm-wl-list]');
        if (list.length) {
            var wid = list.attr('data-id'), store = 'mm_wl_res_' + wid, mine = {};
            try { mine = JSON.parse(localStorage.getItem(store) || '{}') || {}; } catch (e) {}
            var save = function(){ try { localStorage.setItem(store, JSON.stringify(mine)); } catch (e) {} };

            var paint = function(li, taken, by, own){
                var box = li.find('[data-mm-wl-res]');
                li.toggleClass('is-taken', taken);
                box.html(taken
                    ? '<span class="mm-wl-res__taken"><i class="fa-solid fa-circle-check"></i> ' + (own ? 'You\u2019re getting this' : 'Reserved' + (by ? ' by ' + Theme.esc(by) : '')) + '</span>' + (own ? ' <button type="button" class="mm-wl-link" data-mm-wl-undo>Undo</button>' : '')
                    : '<button type="button" class="mm-wl-btn" data-mm-wl-reserve><i class="fa-solid fa-hand"></i> I\u2019ll get this</button>');
            };
            list.children('li').each(function(){ var k = $(this).attr('data-key'); if (mine[k]) paint($(this), true, '', true); });

            list.on('click', '[data-mm-wl-reserve]', function(){
                var li = $(this).closest('li'), key = li.attr('data-key');
                li.find('[data-mm-wl-res]').html('<form class="mm-wl-resform"><input type="text" maxlength="40" placeholder="Your first name (optional)" aria-label="Your first name"><button type="submit" class="mm-wl-btn mm-wl-btn--dark">Reserve</button><button type="button" class="mm-wl-link" data-mm-wl-cancel>Cancel</button></form>')
                    .find('input').trigger('focus');
                li.find('.mm-wl-resform').on('submit', function(e){
                    e.preventDefault();
                    var by = $.trim($(this).find('input').val()), b = $(this).find('button[type="submit"]').prop('disabled', true);
                    $.post(ajax, { action: 'mm_wl_reserve', id: wid, key: key, by: by }).done(function(r){
                        if (r && r.success) { mine[key] = r.data.token; save(); paint(li, true, by, true); Theme.toast('Reserved! Others will see it\u2019s taken'); Theme.track('wishlist_reserve', {}); }
                        else { b.prop('disabled', false); Theme.toast(plain(r && r.data), 'error'); if (/already/.test(plain(r && r.data))) paint(li, true, '', false); }
                    }).fail(function(){ b.prop('disabled', false); Theme.toast('Network error. Please try again.', 'error'); });
                });
            });
            list.on('click', '[data-mm-wl-cancel]', function(){ paint($(this).closest('li'), false); });
            list.on('click', '[data-mm-wl-undo]', function(){
                var li = $(this).closest('li'), key = li.attr('data-key');
                $.post(ajax, { action: 'mm_wl_unreserve', id: wid, key: key, token: mine[key] }).done(function(r){
                    if (r && r.success) { delete mine[key]; save(); paint(li, false); Theme.toast('Reservation removed'); }
                    else Theme.toast(plain(r && r.data), 'error');
                });
            });
        }
    },

    /* ---------- Start a new group from this one (Repeat class) ---------- */

    /** Moderator verified: get a one-time link and open Create Group on the homepage, pre-filled. */
    startRepeat: function($, gid){
        Theme.toast('Opening the new group form\u2026');
        $.post($('#ajax-url').val(), { action: 'mm_repeat_start', gid: gid })
            .done(function(resp){
                if (resp && resp.success) {
                    // Typing the password counts as an ACF form change; nothing is lost here, so skip ACF's 'Leave site?' prompt.
                    if (window.acf && acf.unload) { acf.unload.reset(); acf.unload.disable(); }
                    Theme.track('repeat_group_start', {});
                    window.location.href = resp.data.url;
                }
                else Theme.toast($('<div>').html((resp && resp.data) || 'Please try again.').text(), 'error');
            })
            .fail(function(){ Theme.toast('Network error. Please try again.', 'error'); });
    },

    /** Homepage ?mm_repeat=<token>: Create Group with the organizer's email kept and last year's members to invite. */
    initRepeat: function($){
        // Season email button: open the old group with "Start a new group" (moderator password) ready.
        if (/[?&]mm_start=1/.test(window.location.search)) {
            setTimeout(function(){ var b = $('.repeat-btn').first(); if (b.length) { $('html, body').animate({ scrollTop: b.offset().top - 120 }, 300); b.trigger('click'); } }, 900);
        }

        var m = /[?&]mm_repeat=([A-Za-z0-9.]+)/.exec(window.location.search); // one-time token, or <gid>.<sig> from the season email
        if (!m || !$('#create-group').length) return;

        $.post($('#ajax-url').val(), { action: 'mm_repeat_prep', token: m[1] }).done(function(resp){
            if (!resp || !resp.success) {
                var e = (resp && resp.data) || 'This link has expired.';
                if (e && e.url) {
                    Theme.confirmSheet({ title: 'Already started', body: $('<div>').html(e.message).text(), icon: 'fa-solid fa-circle-check', confirm: 'Open that group', onConfirm: function(done){ done(); window.location.href = e.url; } });
                } else {
                    Theme.toast($('<div>').html(e.message || e).text(), 'error');
                }
                return;
            }
            var d = resp.data, box = $('#create-group'), form = box.find('form');

            box.find('.popup__title').html('New<span>Group</span>');
            form.find('[data-mm-repeat]').remove();
            form.prepend('<div class="mm-repeat-intro" data-mm-repeat><i class="fa-solid fa-rotate-right"></i><div><strong>Starting a new group from ' + Theme.esc(d.from) + '</strong>' +
                '<span>Enter the new group name, date, budget and a new password. This is a new free group: PRO features don\u2019t carry over.</span></div></div>');
            form.append('<input type="hidden" name="mm_repeat" value="' + Theme.esc(m[1]) + '" data-mm-repeat>');

            var name = form.find('.acf-field[data-key="field_5f55bf19de4cb"] input');
            if (name.length && !name.val()) name.val(d.name);

            var email = form.find('.acf-field[data-key="field_5f55bf2fde4cc"] input');
            email.val(d.email).prop('readonly', true).addClass('is-kept').trigger('change');
            email.closest('.acf-field').find('.mm-repeat-kept').remove();
            email.closest('.acf-input').append('<p class="mm-repeat-kept" data-mm-repeat>Same email as before. Tap <b>Send verification code</b> to check it still works.</p>');

            if (d.members.length) {
                var list = '<fieldset class="mm-repeat-invite" data-mm-repeat><legend>Invite last year\u2019s members <small>(' + d.members.length + ')</small></legend>' +
                    '<p>They get an email with the new group link and join themselves. Untick anyone you don\u2019t want to invite.</p>' +
                    '<label class="mm-repeat-invite__all"><input type="checkbox" checked data-mm-repeat-all> Select all</label><div class="mm-repeat-invite__list">';
                d.members.forEach(function(p){
                    list += '<label><input type="checkbox" name="mm_invite[]" value="' + parseInt(p.id, 10) + '" checked> ' + Theme.esc(p.name) + '</label>';
                });
                list += '</div>' + (d.skipped ? '<p class="mm-repeat-invite__note">' + d.skipped + ' member' + (d.skipped == 1 ? '' : 's') + ' without a confirmed email or who unsubscribed won\u2019t be invited.</p>' : '') + '</fieldset>';
                var anchor = form.find('[data-mm-otp-gate]');
                (anchor.length ? anchor : form.find('.acf-form-submit')).before(list);
                form.find('[data-mm-repeat-all]').on('change', function(){ form.find('input[name="mm_invite[]"]').prop('checked', this.checked); });
            }

            $.fancybox.open({
                src: '#create-group', type: 'inline', animationDuration: 600, animationEffect: 'slide-in-out',
                btnTpl: { smallBtn: '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}"><i class="fas fa-times"></i></button>' },
                afterShow: function(instance, current){ Theme.moveCloseButton($, current); }
            });
            Theme.track('repeat_group_form', { invites: d.members.length });
        });
    },

    /* ---------- Group theme (colors of the group page) ---------- */

    proGroupTheme: function($, gid){
        var body = Theme.proView($, 'Group theme', Theme.proLoading);

        function apply(key){
            document.body.className = document.body.className.replace(/\s?mm-gtheme(-[a-z]+)?/g, '');
            if (key) $('body').addClass('mm-gtheme mm-gtheme-' + key);
        }

        function render(d){
            var html = '<p class="mm-prov__meta">Pick the colors everyone sees on your group page. You can change it anytime.</p><div class="mm-gth">';
            d.themes.forEach(function(t){
                html += '<button type="button" class="mm-gth__opt' + (t.key === d.current ? ' is-on' : '') + '" data-key="' + Theme.esc(t.key) + '" style="--gth-a:' + t.accent + ';--gth-c:' + t.card + '" aria-pressed="' + (t.key === d.current) + '">' +
                    '<span class="mm-gth__card"><small>DEC</small><b>19</b><i></i></span>' +
                    '<span class="mm-gth__name">' + Theme.esc(t.label) + '</span>' +
                    (t.key === d.current ? '<span class="mm-gth__check"><i class="fa-solid fa-check"></i></span>' : '') +
                    '</button>';
            });
            body.html(html + '</div>');

            body.find('.mm-gth__opt').on('click', function(){
                var key = $(this).attr('data-key');
                body.find('.mm-gth__opt').prop('disabled', true);
                Theme.proApi('group-theme', { group_id: gid, op: 'set', theme: key }).then(function(r){
                    render(r);
                    apply(r.current);
                    Theme.toast('Theme saved. Everyone sees it on the group page');
                    Theme.track('group_theme', { theme: r.current || 'classic' });
                }).catch(function(err){ body.find('.mm-gth__opt').prop('disabled', false); Theme.toast(err.message, 'error'); });
            });
        }

        Theme.proApi('group-theme', { group_id: gid }).then(render)
            .catch(function(err){ body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>'); });
    },

    /* ---------- Co-organizer ---------- */

    proCoOrganizer: function($, gid){
        var body = Theme.proView($, 'Co-organizer', Theme.proLoading);

        function render(d){
            var html = '';
            if (d.co) {
                html += '<div class="mm-coorg-card"><span class="mm-coorg-card__av">' + Theme.esc(d.co.name.charAt(0).toUpperCase()) + '</span>' +
                    '<div><strong>' + Theme.esc(d.co.name) + '</strong><small>' + Theme.esc(d.co.email) + ' &middot; added ' + Theme.esc(d.co.added) + '</small></div></div>' +
                    '<p class="mm-prov__meta">They open <b>PRO Actions</b> with their own password and can use every tool, except changing the co-organizer.</p>';
                if (d.isCo) {
                    html += '<p class="mm-prov__meta">Only the main organizer can change or remove the co-organizer.</p>';
                } else {
                    html += '<button type="button" class="mm-upsell__btn mm-upsell__btn--dark" data-co-reset>Email them a new password</button>' +
                        '<button type="button" class="mm-upsell__btn mm-upsell__btn--ghost mm-prov__gap" data-co-remove>Remove co-organizer</button>';
                }
            } else {
                html += '<p class="mm-prov__meta">Add someone to help you run the group, like a co-worker or a family member.</p>' +
                    '<ul class="mm-coorg-how"><li><i class="fa-solid fa-envelope"></i> They get an email with their own password</li>' +
                    '<li><i class="fa-solid fa-screwdriver-wrench"></i> They can shuffle, manage members and send reminders</li>' +
                    '<li><i class="fa-solid fa-user-minus"></i> You can remove them anytime</li></ul>' +
                    '<label class="mm-prov__field">Their name<input type="text" maxlength="60" autocomplete="off" data-co-name placeholder="e.g. Ana Santos"></label>' +
                    '<label class="mm-prov__field">Their email<input type="email" maxlength="120" autocomplete="off" data-co-email placeholder="ana@example.com"></label>' +
                    '<button type="button" class="mm-upsell__btn mm-upsell__btn--dark" data-co-add>Add co-organizer</button>';
            }
            body.html(html);

            body.find('[data-co-add]').on('click', function(){
                var btn = $(this), name = $.trim(body.find('[data-co-name]').val()), email = $.trim(body.find('[data-co-email]').val());
                if (!name) { Theme.toast('Please enter their name', 'error'); return; }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { Theme.toast('Please enter a valid email', 'error'); return; }
                btn.prop('disabled', true).text('Sending\u2026');
                Theme.proApi('co-organizer', { group_id: gid, op: 'add', name: name, email: email }).then(function(r){
                    render(r);
                    Theme.toast('Added! We emailed ' + name + ' their password');
                    Theme.track('co_organizer_add', {});
                }).catch(function(err){ btn.prop('disabled', false).text('Add co-organizer'); Theme.toast($('<div>').html(err.message).text(), 'error'); });
            });

            body.find('[data-co-reset]').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true);
                Theme.proApi('co-organizer', { group_id: gid, op: 'reset' }).then(function(r){
                    render(r);
                    Theme.toast('New password sent to ' + r.co.email);
                }).catch(function(err){ btn.prop('disabled', false); Theme.toast($('<div>').html(err.message).text(), 'error'); });
            });

            body.find('[data-co-remove]').on('click', function(){
                Theme.confirmSheet({
                    title: 'Remove ' + d.co.name + '?',
                    body: 'Their password stops working right away. You can add a co-organizer again later.',
                    icon: 'fa-solid fa-user-minus',
                    danger: true,
                    confirm: 'Remove',
                    onConfirm: function(done){
                        Theme.proApi('co-organizer', { group_id: gid, op: 'remove' }).then(function(r){ done(); render(r); Theme.toast('Co-organizer removed'); })
                            .catch(function(err){ done(); Theme.toast($('<div>').html(err.message).text(), 'error'); });
                    }
                });
            });
        }

        Theme.proApi('co-organizer', { group_id: gid }).then(render)
            .catch(function(err){ body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>'); });
    },

    /* ---------- Weekly Drops (optional weekly themes) ---------- */

    proThemes: function($, gid){
        var body = Theme.proView($, 'Weekly Drops', Theme.proLoading);

        function addDays(d, n){
            var x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n);
            return x.getFullYear() + '-' + ('0' + (x.getMonth() + 1)).slice(-2) + '-' + ('0' + x.getDate()).slice(-2);
        }

        function rowHtml(t, d){
            return '<li class="mm-theme-row"><input type="date" value="' + Theme.esc(d) + '" aria-label="Start date">' +
                '<input type="text" maxlength="60" placeholder="e.g. Something sweet" value="' + Theme.esc(t) + '" aria-label="Theme">' +
                '<button type="button" class="mm-prov__mini mm-prov__mini--danger" aria-label="Remove theme"><i class="fa-solid fa-xmark"></i></button></li>';
        }

        function render(d){
            var rows = d.themes.map(function(r){ return rowHtml(r.t, r.d); }).join('');
            body.html(
                '<p class="mm-prov__meta"><b>Optional.</b> A Filipino favorite: on top of the main gift, everyone gives their Manito or Manita a small themed gift each week until the exchange.</p>' +
                '<details class="mm-drops-how" open><summary>How Weekly Drops work</summary><ol>' +
                '<li><b>Shuffle first</b>, so everyone knows who they\u2019re giving to.</li>' +
                '<li>Each week a new theme <b>drops</b> (e.g. <i>something sweet</i>). Members get an email with gift ideas, and the group page shows this week\u2019s drop.</li>' +
                '<li>Everyone leaves a <b>small gift</b> that fits the theme (usually &#8369;50 to &#8369;150), secretly: on a desk early, through a friend, or labelled with the screen name only.</li>' +
                '<li>On exchange day: the main gift, then <b>reveal</b> who gave to whom (Fun extras \u203a Reveal).</li>' +
                '</ol><p>Tips: 3 to 4 drops is plenty. Start the week after you shuffle. Clear all drops anytime to stop.</p></details>' +
                '<ul class="mm-theme-rows">' + rows + '</ul>' +
                '<button type="button" class="mm-prov__mini mm-prov__gapb" data-th-add><i class="fa-solid fa-plus"></i> Add a theme</button>' +
                '<h4 class="mm-prot__group">Quick add</h4>' +
                '<div class="mm-theme-presets">' + d.presets.map(function(p){ return '<button type="button" data-th-preset="' + Theme.esc(p) + '">' + Theme.esc(p) + '</button>'; }).join('') + '</div>' +
                '<label class="mm-prov__field">Suggested budget per weekly gift (&#8369;)<input type="number" min="0" max="5000" step="10" value="' + d.budget + '" data-th-budget></label>' +
                '<button type="button" class="mm-upsell__btn mm-upsell__btn--dark" data-th-save>Save Weekly Drops</button>'
            );

            var list = body.find('.mm-theme-rows');

            function nextDate(){
                var last = list.find('input[type="date"]').map(function(){ return this.value; }).get().filter(Boolean).sort().pop();
                return last ? addDays(last, 7) : addDays(d.today, 1);
            }

            function add(t){
                if (list.children().length >= 8) { Theme.toast('Up to 8 themes per group', 'error'); return; }
                list.append(rowHtml(t || '', nextDate()));
                if (!t) list.find('input[type="text"]').last().trigger('focus');
            }

            body.find('[data-th-add]').on('click', function(){ add(''); });
            body.find('[data-th-preset]').on('click', function(){ add($(this).attr('data-th-preset')); $(this).prop('disabled', true); });
            list.on('click', '.mm-prov__mini--danger', function(){ $(this).closest('li').remove(); });

            body.find('[data-th-save]').on('click', function(){
                var btn = $(this), themes = list.children().map(function(){
                    return { t: $.trim($(this).find('input[type="text"]').val()), d: $(this).find('input[type="date"]').val() };
                }).get();

                btn.prop('disabled', true).text('Saving\u2026');
                Theme.proApi('themes', { group_id: gid, op: 'save', themes: themes, budget: body.find('[data-th-budget]').val() }).then(function(r){
                    render(r);
                    Theme.toast(r.themes.length ? 'Weekly Drops saved' : 'Weekly Drops cleared');
                }).catch(function(err){
                    btn.prop('disabled', false).text('Save Weekly Drops');
                    Theme.toast(err.message, 'error');
                });
            });
        }

        Theme.proApi('themes', { group_id: gid, op: 'get' }).then(render).catch(function(err){
            body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>');
        });
    },

    /* ---------- Matches (hidden until revealed) ---------- */

    proMatches: function($, gid){
        var body = Theme.proView($, 'Matches',
            '<div class="mm-prov__spoiler"><i class="fa-solid fa-eye-slash"></i><p>Matches are hidden so you don&rsquo;t spoil your own surprise.</p>' +
            '<button type="button" class="mm-upsell__btn mm-upsell__btn--dark" data-reveal-matches>Reveal matches</button></div>');

        body.find('[data-reveal-matches]').on('click', function(){
            Theme.confirmSheet({
                title: 'Reveal all matches?',
                points: [
                    'Shows <b>who is buying for whom</b> for everyone in the group.',
                    'If you\u2019re also playing, <b>this spoils your own surprise</b>.',
                    'Make sure no one is looking at your screen.'
                ],
                icon: 'fa-solid fa-eye',
                confirm: 'Reveal matches',
                onConfirm: function(done){ done(); revealMatches(); }
            });
        });

        function revealMatches(){
            body.html(Theme.proLoading);

            Theme.proApi('members', { group_id: gid }).then(function(d){
                var esc = Theme.esc, rows = '';

                d.users.forEach(function(u){
                    var a = u.name || u.screen, b = u.pair_name || u.pair_screen || '\u2014';
                    rows += '<li class="mm-prov__row mm-prov__match" data-name="' + esc((a + ' ' + u.screen + ' ' + b + ' ' + u.pair_screen).toLowerCase()) + '">' +
                        '<span><strong>' + esc(a) + '</strong><small>' + esc(u.screen) + '</small></span>' +
                        '<i class="fa-solid fa-arrow-right" aria-label="buys for"></i>' +
                        '<span><strong>' + esc(b) + '</strong><small>' + esc(u.pair_screen) + '</small></span></li>';
                });

                body.html(
                    '<div class="mm-prov__bar"><label class="mm-prov__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" autocomplete="off"></label>' +
                    '<button type="button" class="mm-prov__mini" data-print><i class="fa-solid fa-print"></i> Print</button></div>' +
                    '<p class="mm-prov__meta">Each person on the left buys a gift for the person on the right.</p>' +
                    '<ul class="mm-prov__list">' + rows + '</ul><p class="mm-prov__none" hidden>No one matches that search.</p>'
                );

                Theme.proSearch(body, 'Search a name');

                body.find('[data-print]').on('click', function(){
                    var w = window.open('', '_blank');
                    if (!w) return;
                    var list = d.users.map(function(u){
                        return '<tr><td>' + esc(u.name || u.screen) + '</td><td>&rarr;</td><td>' + esc(u.pair_name || u.pair_screen) + '</td></tr>';
                    }).join('');
                    w.document.write('<!doctype html><title>' + esc(d.group.name) + ' matches</title><style>body{font-family:Arial,sans-serif;padding:24px}td{padding:8px 12px;border-bottom:1px solid #ddd}</style>' +
                        '<h2>' + esc(d.group.name) + ': matches</h2><table>' + list + '</table>');
                    w.document.close();
                    w.focus();
                    w.print();
                });
            }).catch(function(err){
                body.html('<p class="mm-prov__error">' + Theme.esc(err.message) + '</p>');
            });
        }
    },

    /* ---------- Export ---------- */

    proExport: function($, gid, groupName){
        Theme.toast('Preparing your spreadsheet\u2026');

        Theme.proApi('get-user-export', { group_id: gid }).then(function(d){
            function cell(v){ return '"' + String(v == null ? '' : v).replace(/\r?\n/g, ' ').replace(/"/g, '""') + '"'; }

            var lines = [['Name', 'Screen name', 'Email', 'Match', 'Match screen name', 'Wishlist', 'Links', 'Address & contact'].map(cell).join(',')];

            d.users.forEach(function(u){
                var links = [].concat.apply([], (u.links || []).map(function(l){ return l || []; }))
                    .map(function(l){ return l && l.link_url; }).filter(Boolean).join(' | ');
                lines.push([u.name, u.screen, u.email, u.pair_name, u.pair_screen, (u.wishlists || []).join(' | '), links, u.address_contact].map(cell).join(','));
            });

            var slug = String(groupName).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'group',
                blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' }),
                a = document.createElement('a');

            a.href = URL.createObjectURL(blob);
            a.download = slug + '-members.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function(){ URL.revokeObjectURL(a.href); }, 2000);

            Theme.toast('Spreadsheet downloaded');
        }).catch(function(err){
            Theme.toast(err.message, 'error');
        });
    },

    makeProBtn: function($){
        if($('#make-pro-btn').length > 0){
            $('#make-pro-btn').click(function(e){
                e.preventDefault();
                $('.paypal-pay').show();
                $(this).hide();
            }); 
        }
    },

    tryProPop: function($) {
        var m = /[?&]pop=pro-([a-z]+)/.exec(window.location.search);
        if (m && $('#make-pro').length) {
            var map = { drops: 'themes', exclude: 'exclude', unshuffle: 'unshuffle', gift: 'gift', qa: 'qa' };
            setTimeout(function(){ Theme.openUpsell($, map[m[1]] || 'header'); }, 900);
        }

        if ($('#pro-try').length > 0) {
            // Check if URL has the parameter '&pop=try-pro'
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('pop') === 'try-pro') {
                // Set #pro-try container to display:flex
                $('#pro-try').css('display', 'flex'); Theme.lockScroll($);
                Theme.track('view_promotion', { creative_slot: 'created', promotion_name: 'pro_intro' });
                Theme.popClose($);

                $('#try-pro-btn').click(function(e){
                    e.preventDefault();
                    $('#pro-try').hide();
                    Theme.track('select_promotion', { creative_slot: 'created', promotion_name: 'pro_intro' });

                    Theme.openUpsell($, 'welcome');
                });
            }
        }
    },

    proPurchaseButton: function($){
        $(document).off('click.mmUpsell').on('click.mmUpsell', '#go-pro-btn, [data-mm-upsell]', function(e){
            e.preventDefault();
            var reason = $(this).attr('data-mm-upsell') || 'header';
            Theme.track('select_promotion', { creative_slot: reason, promotion_name: 'pro_upgrade' });
            Theme.openUpsell($, reason);
        });

        // "Maybe later" and other plain close links inside the sheets
        $(document).off('click.mmClose').on('click.mmClose', '.pop-container [data-mm-close]', function(e){
            e.preventDefault();
            $(this).closest('.pop-container').hide();
        });

        Theme.popClose($);
    },

    /**
     * PRO upgrade sheet (#make-pro), with the headline for the moment that opened it.
     */
    openUpsell: function($, reason){
        var sheet = $('#make-pro');
        if (!sheet.length) return;

        var copy = {
            header:    ['Extra tools for your group\u2019s organizer', 'Fix mistakes, let late joiners in and keep everything organized.'],
            welcome:   ['Extra tools for your group\u2019s organizer', 'Fix mistakes, let late joiners in and keep everything organized.'],
            unshuffle: ['More people want to join?', 'Unshuffle the group to let them in, then shuffle again. 3 unshuffles included.'],
            fix:       ['Fix your member list before shuffling', 'Remove duplicates and correct misspelled names or emails.'],
            themes:    ['Make it more fun with Weekly Drops', 'An optional Filipino-style activity: a themed mini-gift each week, an email to everyone with ideas, and a reveal at the end.'],
            exclude:   ['Keep couples from getting each other', 'Add exclusions so couples or family members never get each other in the shuffle.'],
            qa:        ['Ask your match anything, anonymously', 'Find out their size, allergies or favorite things without giving yourself away. One payment unlocks it for the whole group, with no ads.'],
            gift:    ['Gift PRO to your group', 'Remove ads for everyone and unlock the organizer tools. One payment covers the whole group.']
        }[reason] || null;

        if (!copy) { reason = 'header'; copy = ['Extra tools for your group\u2019s organizer', 'Fix mistakes, let late joiners in and keep everything organized.']; }

        sheet.find('[data-mm-upsell-title]').text(copy[0]);
        sheet.find('[data-mm-upsell-lead]').text(copy[1]);
        sheet.find('[data-reason]').removeClass('is-focus').filter('[data-reason="' + reason + '"]').addClass('is-focus');
        // The reason that opened the sheet is visible: a tile, or the full list opened at that feature.
        sheet.find('.mm-upsell__all').prop('open', !sheet.find('.mm-upsell__tile.is-focus').length && sheet.find('.mm-upsell__list li.is-focus').length > 0);
        Theme.lockScroll($);

        $('.pop-container').hide();
        if ($.fancybox && $.fancybox.getInstance && $.fancybox.getInstance()) $.fancybox.close(true);
        sheet.css('display', 'flex').data('mmReason', reason);
        sheet.find('.mm-upsell__inner').scrollTop(0);

        Theme.track('view_promotion', { creative_slot: reason, promotion_name: 'pro_upgrade' });
    },

    /** While an upgrade sheet is open the page behind doesn't scroll (iOS scrolls it otherwise). */
    lockScroll: function($){
        $('html').addClass('mm-noscroll');
        if (Theme._lockObs) return;
        var check = function(){ if (!$('.mm-upsell:visible').length) $('html').removeClass('mm-noscroll'); };
        Theme._lockObs = new MutationObserver(check);
        $('.mm-upsell').each(function(){ Theme._lockObs.observe(this, { attributes: true, attributeFilter: ['style', 'class'] }); });
    },

    popClose: function($){
        // Hide the pop-container when clicking outside the inner content
        $('.pop-container').on('click', function(event) {
            // Check if the click target is the .pop-container itself
            if ($(event.target).is(this)) {
                $(this).hide(); // Hide the pop-container
            }
        });

        // Use event delegation for the close button
        $('.pop-container').on('click', '.pop-container__close a', function(event) {
            event.preventDefault(); // Prevent the default action of the link
            $(this).closest('.pop-container').hide(); // Hide the pop-container when close button is clicked
        });
    },

    initPromos: function($){
        if($('.promos').length > 0){
            if(!Cookies.get('promos')){
                $('.promos').show();
                var inOneHour = 1/24;
                Cookies.set('promos', '1', { domain: '.dev.manitomanita.com', expires: inOneHour });
            }else{
                $('.promos').hide();
            }
    
            $('.promos').click(function(){
                $('.promos').hide();
            });
        }
    },

    /**
     * Dashboard onboarding tour (spotlight + card), picked by situation:
     * organizer (just created the group), member before the shuffle, member after the shuffle.
     * Shown once per group and situation; "Show me around" ([data-mm-tour-again]) replays it.
     */
    initTours: function($){
        $('.sample-card').remove();
        if (!$('#members-list').length || !$('#groupid-val').length) return;

        var gid = $('#groupid-val').val(),
            matched = $('.mm-dash-status.is-matched').length > 0,
            isPro = $('body').hasClass('pro-version'),
            organizer = /[?&]pop=try-pro/.test(window.location.search),
            name = organizer ? 'organizer' : (matched ? 'member-after' : 'member-before'),
            key = 'mm_tour_' + name + '_' + gid;

        function steps(which){
            var shuffleTarget = isPro ? '#pro-actions-btn' : '.mm-header .shuff-btn a';
            var all = {
                // Short on purpose (the 5-step version was finished by only 29%).
                organizer: [
                    { el: '#clipboard', title: 'Share this link', text: 'Send it on Messenger or Viber. Members join, confirm their email and add a wishlist. Playing too? Join with your own name.' },
                    { el: '#members-list', title: 'Watch people join', text: 'Members appear here once they confirm their email.' },
                    { el: shuffleTarget, title: 'Shuffle when everyone is in', text: 'Everyone gets their match by email (at least 3 members).' }
                ],
                'member-before': [
                    { el: '#members-list .user-action[data-action="edit-wishlist"]', title: 'Add your wishlist', text: 'Find your name and tap Edit Wishlist. Links to Lazada or Shopee work great.' },
                    { el: '.mm-dash-status', title: 'We\u2019ll email you', text: 'When the organizer shuffles, you\u2019ll get an email with your match.' }
                ],
                'member-after': [
                    { el: '#members-list .mm-match-btn:not([disabled])', title: 'See your match', text: 'Tap See Match on your name to see their wishlist, address and gift ideas.' },
                    { el: '#members-list .mm-view-btn', title: 'Peek at wishlists', text: 'View anyone\u2019s wishlist and leave a comment.' }
                ]
            };
            return all[which] || [];
        }

        function blocked(){
            return $('.fancybox-container:visible, .pop-container:visible').length > 0;
        }

        // manual = "Show me around": start right away (wait at most ~1.5s for the member cards).
        // Automatic: wait for the cards (loaded by AJAX) and for any popup to close.
        function start(manual){
            var tries = 0, max = manual ? 6 : 40;
            (function wait(){
                var ready = $('#members-list .card.mm-member').length > 0;
                if ((!ready || (!manual && blocked())) && tries++ < max) return setTimeout(wait, 250);
                if (!manual && blocked()) return;
                Theme.tour.run($, name, steps(name), function(done){
                    try { localStorage.setItem(key, done ? 'done' : 'skipped'); } catch (e) {}
                });
            })();
        }

        $(document).off('click.mmTourAgain').on('click.mmTourAgain', '[data-mm-tour-again]', function(e){
            e.preventDefault();
            start(true);
        });

        // Starts by itself only for an organizer's first group on this browser; members use "Show me around".
        var seen = null;
        try { seen = localStorage.getItem(key) || localStorage.getItem('mm_tour_organizer_seen'); } catch (e) {}
        if (organizer && !seen) {
            try { localStorage.setItem('mm_tour_organizer_seen', '1'); } catch (e) {}
            setTimeout(function(){ start(false); }, 600);
        }
    },

    /**
     * Minimal spotlight tour. steps: [{ el, title, text }]; missing targets are skipped.
     */
    tour: {
        run: function($, name, steps, onEnd){
            steps = steps.filter(function(st){ return $(st.el).filter(':visible').length; });
            if (!steps.length) return;

            $('#mm-tour').remove();
            var idx = 0,
                root = $('<div id="mm-tour" class="mm-tour" role="dialog" aria-live="polite">' +
                    '<div class="mm-tour__spot"></div>' +
                    '<div class="mm-tour__card"><div class="mm-tour__dots"></div><h3></h3><p></p>' +
                    '<div class="mm-tour__nav"><button type="button" class="mm-tour__skip">Skip</button>' +
                    '<span><button type="button" class="mm-tour__back">Back</button>' +
                    '<button type="button" class="mm-tour__next">Next</button></span></div></div></div>').appendTo('body'),
                spot = root.find('.mm-tour__spot'),
                card = root.find('.mm-tour__card'),
                frame = null;

            root.addClass('is-instant'); // first placement without the slide-in from the corner

            Theme.track('tour_start', { tour: name });

            function place(){
                var target = $(steps[idx].el).filter(':visible').first();
                if (!target.length) return;
                var r = target[0].getBoundingClientRect(), pad = 8;
                spot.css({ top: r.top - pad, left: r.left - pad, width: r.width + pad * 2, height: r.height + pad * 2 });

                if (window.innerWidth < 640) { card.css({ top: '', left: '', bottom: '' }).addClass('is-bottom'); return; }
                card.removeClass('is-bottom');
                var cw = card.outerWidth(), ch = card.outerHeight(),
                    top = r.bottom + 16;
                if (top + ch > window.innerHeight - 12) top = Math.max(12, r.top - ch - 16);
                var left = Math.min(Math.max(12, r.left), window.innerWidth - cw - 12);
                card.css({ top: top, left: left });
            }

            function show(i){
                idx = i;
                var st = steps[idx], target = $(st.el).filter(':visible').first();
                card.find('h3').text(st.title);
                card.find('p').text(st.text);
                card.find('.mm-tour__dots').html(steps.map(function(_, k){ return '<i class="' + (k === idx ? 'is-on' : '') + '"></i>'; }).join(''));
                card.find('.mm-tour__back').prop('hidden', idx === 0);
                card.find('.mm-tour__next').text(idx === steps.length - 1 ? 'Got it' : 'Next');

                var rect = target[0].getBoundingClientRect();
                if (rect.top < 80 || rect.bottom > window.innerHeight - 160) {
                    target[0].scrollIntoView({ block: 'center', behavior: 'auto' }); // instant: no waiting on a smooth scroll
                }
                place();
                if (root.hasClass('is-instant')) setTimeout(function(){ root.removeClass('is-instant'); }, 50);
                Theme.track('tour_step', { tour: name, step: idx + 1 });
                card.find('.mm-tour__next').trigger('focus');
            }

            function end(done){
                root.remove();
                $(window).off('.mmTour');
                $(document).off('.mmTour');
                Theme.track(done ? 'tour_complete' : 'tour_skip', { tour: name, step: idx + 1 });
                if (onEnd) onEnd(done);
            }

            root.on('click', '.mm-tour__next', function(){ idx < steps.length - 1 ? show(idx + 1) : end(true); });
            root.on('click', '.mm-tour__back', function(){ if (idx > 0) show(idx - 1); });
            root.on('click', '.mm-tour__skip', function(){ end(false); });
            $(window).on('resize.mmTour scroll.mmTour', function(){
                if (frame) return;
                frame = window.requestAnimationFrame(function(){ frame = null; place(); });
            });
            $(document).on('keydown.mmTour', function(e){
                if (e.key === 'Escape') end(false);
                if (e.key === 'ArrowRight') root.find('.mm-tour__next').trigger('click');
                if (e.key === 'ArrowLeft') root.find('.mm-tour__back').trigger('click');
            });

            show(0);
        }
    },

    acfSubmitFix: function($){
        $('.acf-form input[type="submit"]').click(function(){
            setTimeout(function(){
                if($('.acf-form input[type="submit"]').hasClass('disabled')){
                    Theme.initShowOverlay($);

                    var sub = setInterval(function(){
                        if(!$('.acf-form input[type="submit"]').hasClass('disabled')){
                            Theme.removeOverlay($);
                            clearInterval(sub);
                        }
                    }, 200);
                }
            }, 100);
        });
    },

    shopScripts: function($){
        if($('.shop-menu-container'.length > 0)){
            $('.shop-menu-container').prependTo($('body'));

            $('.shop-navigation a').click(function(){
                $('.shop-menu-container').css('width', '400px');
                $('.shop-menu-container').addClass('open');
            });

            $('.shop-navigation__close, .shop-menu-container__overlay').click(function(){
                $('.shop-menu-container').css('width', '0px');
                $('.shop-menu-container').removeClass('open');
            });
        }
    },

    buttonsMobile: function($){
        if (Theme.isMobile()) {
            $('.shuff-btn a').text('Shuffle');
            $('.join-btn a').text('Joined');
        }else{
            $('.shuff-btn a').text('Shuffle Match');
            $('.join-btn a').text('Who Joined');
        }
    },

    mobileChecker: function($) {
        if (Theme.isMobile()) {
            $('body').addClass('in-mobile');
        } else {
            $('body').removeClass('in-mobile');
        }
    },

    isMobile: function() {
        if (jQuery(window).width() <= 992) {
            return true;
        } else {
            return false;
        }
    },

    productSlick: function($){
        if($('.product-list').length > 0) {

            if($('.shop-page').length == 0){
                $('.product-list').slick({
                    centerMode: true,
                    centerPadding: '60px',
                    slidesToShow: 3,
                    responsive: [
                      {
                        breakpoint: 768,
                        settings: {
                          arrows: true,
                          centerMode: true,
                          centerPadding: '40px',
                          slidesToShow: 3
                        }
                      },
                      {
                        breakpoint: 480,
                        settings: {
                          arrows: true,
                          centerMode: true,
                          centerPadding: '40px',
                          slidesToShow: 1
                        }
                      }
                    ]
                  });    
            }

              $('.image.lazy').each(function(){
                  var s = $(this).attr('data-src');

                  $(this).append('<img src="'+s+'">');
              });
        }
    },

    initMatching: function($, gids){
        Theme.initShowOverlay($);

        $.ajax ({
            url: $('#ajax-url').val(),
            type: 'POST',
            dataType: 'JSON',
            data: {
                // the value of data.action is the part AFTER 'wp_ajax_' in
                // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                action: 'execute_matching',
                // ANY other properties of data are passed to your_function()
                // in the PHP global $_REQUEST (or $_POST in this case)
                gid : gids
                },
            beforeSend : function()    {           

            },
            success: function (resp) {
                console.log(resp);
            
                if (resp.success) {
                    // Check if matching succeeded or failed
                    if (resp.data.result) {
                        $('#reload').submit(); // Refresh the page if matching succeeded
                    } else {
                        // Display failure message if matching result is false
                        alert(`An error occurred: ${resp.data.message}. Please contact the administrator.`);
                    }
                } else {
                    // Use alert to display error message from server response if error encountered
                    alert(`An error occurred: ${resp.data.error}. Please contact the administrator.`);
                }
            
                // Remove overlay in either case
                Theme.removeOverlay($);
            },
            
            error: function (xhr, ajaxOptions, thrownError) {
                // this error case means that the ajax call, itself, failed, e.g., a syntax error
                // in your_function()
                console.log('Request failed: ' + thrownError.message) ;
                Theme.removeOverlay($);
                },
        });
    },

    initCardsUsers: function($){

        if($('div.dashboard').length > 0){
            var useridstext = $('#userids').text();
            
            if(useridstext == "false"){
                return false;
            }
            
            var userids = JSON.parse(useridstext);
            
            Theme.batchcount = userids.length;
            Theme.grpuserids = userids;

            Theme.ajaxCardUsers($);

            //Theme.initDashboardScripts($);
        }
    },

    ajaxCardUsers: function($){
        //var key = Theme.batchcount - 1;

        $.ajax({
            url: $('#ajax-url').val(),
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'user_card_generate', // Corrected the action name
                userid: Theme.grpuserids, // Pass the data for the current batch
                groupid: $('#groupid').text()
            },
            beforeSend : function()   {           
               //$('.report__result').addClass('overlay');
               $('#cards-container').addClass('populating');
            },
            success: function(response) {
                //console.log(response);
                if (response.success) {
                    $('#cards-container').html(response.data[1]);
                    $('.memcount').text(response.data[2]);
                    $('.wishcount').text(response.data[3]);
                    $('.joined.card-columns').html(response.data[4]);
                    Theme.initWhoJoined($);
                    Theme.initDashboardScripts($);
                    $('#cards-container').removeClass('populating');

                    Theme.afflinkFunction($);
                    Theme.initMemberSearch($);
                    Theme.initMemberCards($);
                    /*
                    if(Theme.batchcount <= 0) {
                        Theme.initDashboardScripts($);
                        $('#cards-container').removeClass('populating');
                    }else{
                        $('#cards-container').append(response.data[1]);
                        Theme.batchcount--;
                        Theme.ajaxCardUsers($);
                    }
                   */
                } else {
                    // Handle the error
                    //$("#result").append(response.data + "<br>");
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX error
                console.error("Error processing batch:", error);
                //$("#result").append("An error occurred while processing the batch.<br>");
            }
        });
    },

    initDashboardScripts: function($){
        if($('div.dashboard').length > 0){
            $('.clip input').off('click.mmDash').on('click.mmDash', function(){
                Theme.copyToClipboard($, this);
            });

            $('.card-header h2').off('click.mmDash').on('click.mmDash', function(e){
                if (e.target !== this)
                    return;
                
                $(this).find('button').trigger('click');
            });

            $('.expand-deets').off('click.mmDash').on('click.mmDash', function(e){
                e.preventDefault();

                $(this).parents('.card-header').find('button').trigger('click');
            });

            /**** user action buttons */

            $('.user-action').off('click.mmDash').on('click.mmDash', function(e){
                e.preventDefault();

                var btn = $(this);
                var $nameEl = btn.parents('.card').find('h2 button .mm-member__name'); // not the avatar initial
                var uname = $.trim(($nameEl.length ? $nameEl : btn.parents('.card').find('h2 button')).text());
                var unamef = (uname.length > 0)?uname:'Moderator Password';
                var uid = (btn.hasClass('editgroup-btn') || btn.hasClass('shuffle-btn') || btn.hasClass('repeat-btn'))?btn.attr('group-data'):btn.attr('user-data');
                var box = (btn.hasClass('comment-btn'))?'#comment-box':'#input-password';
                var action = btn.attr('data-action');

                // Signed in on this device ("remember this device" or a password this session): no prompt.
                if (!btn.hasClass('comment-btn') && Theme.isVerified(uid)) {
                    Theme.action = action;
                    Theme.userid = uid;
                    Theme.afterVerified($);
                    return;
                }

                var notice = $('#input-pw .notices');
                notice.hide();
                $('.header-details').hide();

                $.fancybox.open({
                    src: box,
                    type: 'inline',
                    animationDuration : 600,
                    animationEffect   : 'slide-in-out',
                    btnTpl : {
                        smallBtn:
                        '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}">' +
                        '<i class="fas fa-times"></i>' +
                        "</button>",
                    },
                    afterShow : function( instance, current ) {
                        $('.header-details').html(unamef);
                        Theme.moveCloseButton($, current);
                        $('input[name="user-data"]').val(uid);
                        notice.html('<a href="javascript:;" id="reset-pass">Forgot Password?</a>.');
                        notice.show();
                        $('.header-details').show();

                        $('#reset-pass').unbind();
                        $('#reset-pass').click(function(){
                            Theme.generateNewPassword($, uid);
                        });

                        if(!btn.hasClass('comment-btn')){
                            
                            Theme.action = action; //set global last user action
                            Theme.initPasswordScripts($);
                        }
                    }
                });
            });
        }
    },

    initPasswordScripts: function($){
        var submitbtn = $('#input-pw input[type="submit"]');
        var pw = $('#input-pw input[name="password"]'); // by name: the show/hide toggle changes its type
        var userid = $('#input-pw input[name="user-data"]');
        var notice = $('#input-pw .notices');

        submitbtn.unbind(); // clear binds

        submitbtn.click(function(e){
            e.preventDefault();

            if(pw.val().length == 0){
                notice.text('Please supply your password to continue.');
                return false;
            }

            Theme.initShowOverlay($); // initiate overlay

            $.ajax ({
                url: $('#ajax-url').val(),
                type: 'POST',
                dataType: 'JSON',
                data: {
                    // the value of data.action is the part AFTER 'wp_ajax_' in
                    // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                    action: 'verify_password',
                    // ANY other properties of data are passed to your_function()
                    // in the PHP global $_REQUEST (or $_POST in this case)
                    password : pw.val(),
                    uid : userid.val(),
                    remember : $('#input-pw input[name="remember"]').is(':checked') ? '1' : '0'
                    },
                beforeSend : function()    {           

                },
                success: function (resp) {
                    if(resp.success){
                        if(resp.data[0]){
                            $.fancybox.close();
                            Theme.userid = userid.val(); //set global user id
                            Theme.markVerified(Theme.userid);

                            Theme.afterVerified($);
                        }else{
                            notice.html('Incorrect Password for '+resp.data[1]+'. Reset and Generate New Password <a href="javascript:;" id="reset-pass">Here</a>.');
                            notice.show();

                            $('#reset-pass').unbind();
                            $('#reset-pass').click(function(){
                                Theme.generateNewPassword($, userid.val());
                            });

                            Theme.removeOverlay($);
                        }
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    // this error case means that the ajax call, itself, failed, e.g., a syntax error
                    // in your_function()
                    console.log('Request failed: ' + thrownError.message) ;
                    Theme.removeOverlay($);
                    },
            });


        });
    },

    /** Members/groups signed in on this device (window.mmVerified from the dashboard, plus this visit). */
    isVerified: function(id){
        return !!id && (window.mmVerified || []).map(String).indexOf(String(id)) !== -1;
    },

    markVerified: function(id){
        window.mmVerified = window.mmVerified || [];
        if (!Theme.isVerified(id)) window.mmVerified.push(String(id));
    },

    /** After the member/organizer password (or a remembered device): run the action they tapped. */
    afterVerified: function($){
                if(Theme.action == "shuffle-group"){
                    Theme.removeOverlay($);
                    var isPro = $('body').hasClass('pro-version');
                    if (!isPro) Theme.track('view_promotion', { creative_slot: 'shuffle', promotion_name: 'pro_intro' });
                    Theme.confirmSheet({
                        title: 'Shuffle the group now?',
                        points: [
                            'Everyone gets their match <b>by email right away</b>.',
                            '<b>No one can join</b> after the shuffle.',
                            isPro ? 'You can unshuffle later from PRO Actions (uses 1 credit).' : '<b>Free groups can\u2019t undo a shuffle.</b> If late joiners might come, upgrade to PRO to be able to unshuffle.'
                        ],
                        extra: isPro ? '' : '<i class="fa-solid fa-user-slash" aria-hidden="true"></i><span><b>Couples or family in the group?</b> Keep them from getting each other with PRO exclusions. Set them up before you shuffle.</span><button type="button" data-mm-upsell="exclude">See PRO</button>',
                        check: 'Everyone who should be in the group has joined',
                        confirm: 'Shuffle now',
                        icon: 'fa-solid fa-shuffle',
                        onConfirm: function(done){ done(); Theme.initMatching($, Theme.userid); }
                    });
                }else if(Theme.action == "repeat-group"){
                    Theme.removeOverlay($);
                    Theme.startRepeat($, Theme.userid);
                }else{
                    Theme.initCustomPopup($);
                    Theme.removeOverlay($);
                }
    },

    generateNewPassword: function($, uids){
        Theme.initShowOverlay($);

        $.ajax ({
            url: $('#ajax-url').val(),
            type: 'POST',
            dataType: 'JSON',
            data: {
                // the value of data.action is the part AFTER 'wp_ajax_' in
                // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                action: 'pass_generate',
                // ANY other properties of data are passed to your_function()
                // in the PHP global $_REQUEST (or $_POST in this case)
                uid : uids
                },
            beforeSend : function()    {           

            },
            success: function (resp) {
                //console.log(resp);
                var notice = $('#input-pw .notices');

                if(resp.success && resp.data){
                    notice.html('We emailed you a link to set a new password (it works for 1 hour). Check your inbox, and your spam folder if you don\u2019t see it.');
                }else{
                    notice.html(resp && resp.data && typeof resp.data === 'string' ? resp.data : 'We couldn\u2019t send the reset link. Please try again.');
                }

                Theme.removeOverlay($);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                // this error case means that the ajax call, itself, failed, e.g., a syntax error
                // in your_function()
                console.log('Request failed: ' + thrownError.message) ;
                Theme.removeOverlay($);
                },
        });
    },

    /**
     * Hides optional affiliate CTAs (.aff-cta-optional: the members-list 'Browse gifts' strips) for 24h after the visitor clicks one of our
     * affiliate links (.mm-aff). UX only: the merchant's tracking cookie can't be read from this site.
     */
    /**
     * Moves fancybox's close button into the opened popup's title bar.
     * Only touches that popup, and keeps a single button there; the old version appended every close
     * button on the page into every .popup__title, cloning them ~6x per modal open until the page froze.
     */
    moveCloseButton: function($, current){
        var $content = (current && current.$content) ? current.$content : $(),
            $title = $content.find('.popup__title').first(),
            $btn = (current && current.$smallBtn && current.$smallBtn.length) ? current.$smallBtn : $content.children('.fancybox-button--close').last();

        if (!$title.length || !$btn.length) return;

        $title.find('.fancybox-button').not($btn).remove();
        $btn.appendTo($title);
    },

    /**
     * Adds a show/hide button to password fields in popups. Safe to call repeatedly.
     */
    initPasswordToggles: function($){
        $('.popup input[type="password"]').not('[data-mm-pw]').each(function(){
            var $input = $(this).attr('data-mm-pw', '1');

            $input.wrap('<span class="mm-pw-wrap"></span>');
            $('<button type="button" class="mm-pw-toggle" aria-label="Show password" aria-pressed="false">Show</button>').insertAfter($input);
        });

        $(document).off('click.mmPw').on('click.mmPw', '.mm-pw-toggle', function(){
            var $btn = $(this), $input = $btn.siblings('input'), show = $input.attr('type') === 'password';

            $input.attr('type', show ? 'text' : 'password').trigger('focus');
            $btn.text(show ? 'Hide' : 'Show').attr({ 'aria-pressed': show ? 'true' : 'false', 'aria-label': show ? 'Hide password' : 'Show password' });
        });

        // Popups are reused (e.g. the password prompt for each member): hide passwords again on close.
        $(document).off('afterClose.mmPw').on('afterClose.mmPw', function(){
            $('input[data-mm-pw]').attr('type', 'password');
            $('.mm-pw-toggle').text('Show').attr({ 'aria-pressed': 'false', 'aria-label': 'Show password' });
        });
    },

    /**
     * 'Find your name' filter above the member list. Shown for groups of SEARCH_MIN+ members.
     * Called after every member-list (re)load; keeps the current search term.
     */
    initMemberSearch: function($){
        var SEARCH_MIN = 6,
            $box = $('[data-mm-search]'),
            $input = $box.find('[data-mm-search-input]'),
            $count = $box.find('[data-mm-search-count]'),
            $empty = $('[data-mm-search-empty]'),
            $cards = $('#cards-container > .card'),
            fold = function(str){
                str = String(str || '').toLowerCase();
                return str.normalize ? str.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : str;
            },
            apply = function(){
                var term = fold($.trim($input.val())), shown = 0;

                $cards.each(function(){
                    var $card = $(this),
                        name = fold($card.find('.mm-member__name').text() || $card.find('.card-header h2 button').text()),
                        match = term === '' || name.indexOf(term) !== -1;

                    $card.toggleClass('is-filtered', !match);
                    if (match) shown++;
                });

                $count.text(term === '' ? $cards.length + ' members' : shown + ' of ' + $cards.length);
                $empty.prop('hidden', shown > 0);
                $empty.find('[data-mm-search-term]').text($.trim($input.val()));
            };

        if (!$box.length) return;

        $box.prop('hidden', $cards.length < SEARCH_MIN);
        if ($cards.length < SEARCH_MIN) { $cards.removeClass('is-filtered'); $empty.prop('hidden', true); return; }

        $input.off('input.mmSearch search.mmSearch').on('input.mmSearch search.mmSearch', apply);
        apply();
    },

    /**
     * Member cards: tapping a card or 'View wishlist' opens #member-popup with that member's
     * wishlist, comments and all actions. The popup's action buttons pass the tap on to the card's
     * own buttons (password prompt, comment box, leave confirmation stay exactly as before).
     */
    initMemberCards: function($){
        var lastOpen = 0;

        function openMember($card){
            if (Date.now() - lastOpen < 400) return; // the theme re-triggers the name button on header clicks
            lastOpen = Date.now();

            var $inner = $('[data-mm-member-popup]'),
                name = $.trim($card.find('.mm-member__name').text()),
                meta = $.trim($card.find('.mm-member__meta').text()),
                $match = $card.find('.mm-match-btn'),
                $leave = $card.find('.mm-leave-btn'),
                pending = null;

            var action = function(kind, label, icon, $target, extraClass){
                var disabled = !$target.length || $target.is('[disabled]');
                return $('<button type="button" class="mm-pop-action ' + (extraClass || '') + '"></button>')
                    .prop('disabled', disabled)
                    .attr('title', disabled ? ($target.attr('title') || '') : '')
                    .attr('data-kind', kind)
                    .html('<i class="' + icon + '" aria-hidden="true"></i> ' + label);
            };

            var $head = $('<div class="mm-pop-head"></div>')
                .append($card.find('.mm-avatar').first().clone())
                .append($('<div class="mm-pop-head__text"></div>')
                    .append($('<div class="mm-pop-head__name"></div>').text(name))
                    .append($('<div class="mm-pop-head__meta"></div>').text(meta)));

            var $actions = $('<div class="mm-pop-actions"></div>')
                .append(action('wish', 'Edit Wishlist', 'fas fa-gift', $card.find('.wish-btn'), 'is-dark'))
                .append(action('match', 'See Match', 'fas fa-user-friends', $match, 'is-accent'))
                .append(action('comment', 'Comment', 'far fa-comment', $card.find('.comment-btn')))
                .append(action('leave', 'Leave Group', 'fas fa-sign-out-alt', $leave, 'is-quiet'));

            $inner.empty().append($head, $actions, $card.find('.mm-member-details').children().clone());

            $inner.off('click.mmPop').on('click.mmPop', '.mm-pop-action:not(:disabled)', function(){
                var kind = $(this).data('kind');
                pending = { wish: $card.find('.wish-btn'), match: $match, comment: $card.find('.comment-btn'), leave: $leave }[kind];
                $.fancybox.close();
            });

            $.fancybox.open({
                src: '#member-popup',
                type: 'inline',
                animationDuration: 400,
                animationEffect: 'slide-in-out',
                btnTpl: { smallBtn: '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}"><i class="fas fa-times"></i></button>' },
                afterShow: function(instance, current){ Theme.moveCloseButton($, current); },
                afterClose: function(){
                    if (pending && pending.length) { var $t = pending; pending = null; setTimeout(function(){ $t.trigger('click'); }, 50); }
                }
            });
        }

        $(document).off('click.mmCard').on('click.mmCard', '#cards-container .mm-member', function(e){
            var $t = $(e.target);

            // Quick actions keep their own behaviour; other links (e.g. shop links) too.
            if ($t.closest('a, input, .user-action-btns > :not(.mm-view-btn)').length) return;

            e.preventDefault();
            openMember($(this));
        });
    },

    /**
     * GA4 events via the site's gtag (ExactMetrics). Safe when blocked or missing.
     * Links with data-mm-track="event" (+ data-mm-slot) send on click; [data-mm-promo] blocks send
     * view_promotion once when they scroll into view.
     */
    track: function(name, params){
        try {
            if (typeof window.gtag === 'function') window.gtag('event', name, params || {});
        } catch (e) {}
    },

    /**
     * Ad slots from Ads::slot ([data-mm-adsense]): the AdSense script loads only when the first
     * slot comes near the screen, and each slot's ad is created then. Slots added later (the match
     * view) are picked up too. Pages where nobody reaches a slot load no ad code.
     */
    initAdSense: function($){
        if (Theme._adsInit) return;
        Theme._adsInit = true;

        var loaded = false, queued = false, io = null;
        var fill = function(el){
            var tpl = el.querySelector('template');
            if (!tpl) return;
            el.appendChild(tpl.content.cloneNode(true));
            tpl.parentNode.removeChild(tpl);
            if (!loaded) {
                loaded = true;
                var sc = document.createElement('script');
                sc.async = true;
                sc.crossOrigin = 'anonymous';
                sc.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8648985139343614';
                document.head.appendChild(sc);
            }
            try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {}
        };
        if ('IntersectionObserver' in window) {
            io = new IntersectionObserver(function(entries){
                entries.forEach(function(en){
                    if (!en.isIntersecting) return;
                    io.unobserve(en.target);
                    fill(en.target);
                });
            }, { rootMargin: '300px 0px' });
        }
        var scan = function(){
            queued = false;
            var els = document.querySelectorAll('[data-mm-adsense]:not([data-mm-ad-seen])');
            for (var i = 0; i < els.length; i++) {
                els[i].setAttribute('data-mm-ad-seen', '1');
                if (io) io.observe(els[i]); else fill(els[i]);
            }
        };
        scan();
        if ('MutationObserver' in window) {
            new MutationObserver(function(){
                if (queued) return;
                queued = true;
                if (window.requestAnimationFrame) window.requestAnimationFrame(scan); else setTimeout(scan, 50);
            }).observe(document.body, { childList: true, subtree: true });
        }
    },

    /**
     * Sponsor cards (Ads::cardHtml). Slots on cached pages ([data-mm-spon-slot]) fetch their card so
     * rotation and end dates stay right. A card counts as viewed once at least half of it has been on
     * screen for 1 second (one beacon per card per page view; "unique" once per browser per day).
     */
    initSponsors: function($){
        if (Theme._sponInit) return;
        Theme._sponInit = true;

        var queued = false, io = null, timers = {};
        var today = new Date().toISOString().slice(0, 10);
        var send = function(el){
            if (el.getAttribute('data-mm-spon-sent')) return;
            el.setAttribute('data-mm-spon-sent', '1');
            var ad = el.getAttribute('data-mm-spon'), key = 'mmsp:' + ad + ':' + today, u = 1;
            try { if (localStorage.getItem(key)) u = 0; else localStorage.setItem(key, '1'); } catch (e) {}
            var body = new URLSearchParams({ ad: ad, v: el.getAttribute('data-v') || 'a', s: el.getAttribute('data-s') || '', u: String(u) });
            try {
                if (navigator.sendBeacon) navigator.sendBeacon('/wp-json/mm/v1/ad-view', body);
                else fetch('/wp-json/mm/v1/ad-view', { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' });
            } catch (e) {}
        };
        if ('IntersectionObserver' in window) {
            io = new IntersectionObserver(function(entries){
                entries.forEach(function(en){
                    var el = en.target, id = el.getAttribute('data-mm-spon-key');
                    if (en.intersectionRatio >= 0.5) {
                        if (!timers[id]) timers[id] = setTimeout(function(){ io.unobserve(el); send(el); }, 1000);
                    } else if (timers[id]) {
                        clearTimeout(timers[id]);
                        timers[id] = null;
                    }
                });
            }, { threshold: [0, 0.5] });
        }
        var n = 0;
        var scan = function(){
            queued = false;
            var slots = document.querySelectorAll('[data-mm-spon-slot]:not([data-mm-spon-seen])');
            for (var i = 0; i < slots.length; i++) {
                (function(slot){
                    slot.setAttribute('data-mm-spon-seen', '1');
                    fetch('/wp-json/mm/v1/ad-slot?s=' + encodeURIComponent(slot.getAttribute('data-mm-spon-slot')) + '&t=' + encodeURIComponent(slot.getAttribute('data-target') || ''), { credentials: 'same-origin' })
                        .then(function(r){ return r.json(); })
                        .then(function(d){ if (d && d.html) slot.innerHTML = d.html; })
                        .catch(function(){});
                })(slots[i]);
            }
            var cards = document.querySelectorAll('[data-mm-spon]:not([data-mm-spon-key])');
            for (var j = 0; j < cards.length; j++) {
                cards[j].setAttribute('data-mm-spon-key', 'c' + (++n));
                if (io) io.observe(cards[j]);
            }
        };
        scan();
        if ('MutationObserver' in window) {
            new MutationObserver(function(){
                if (queued) return;
                queued = true;
                if (window.requestAnimationFrame) window.requestAnimationFrame(scan); else setTimeout(scan, 50);
            }).observe(document.body, { childList: true, subtree: true });
        }
    },

    initTracking: function($){
        $(document).off('click.mmTrack').on('click.mmTrack', '[data-mm-track]', function(){
            var el = $(this);
            Theme.track(el.attr('data-mm-track'), { creative_slot: el.attr('data-mm-slot') || '' });
        });

        var promos = document.querySelectorAll('[data-mm-promo]');
        if (!promos.length || !('IntersectionObserver' in window)) return;

        var seen = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (!entry.isIntersecting) return;
                Theme.track('view_promotion', { creative_slot: entry.target.getAttribute('data-mm-promo') });
                seen.unobserve(entry.target);
            });
        }, { threshold: 0.4 });

        for (var i = 0; i < promos.length; i++) seen.observe(promos[i]);
    },

    /**
     * Homepage: sticky "Create a free group" bar on phones after the hero, and "Have a group link?".
     */
    initHome: function($){
        var hero = document.querySelector('[data-mm-hero]');
        if (!hero) return;

        Theme.initReveal();
        Theme.initCounters();

        var sticky = $('[data-mm-sticky]');

        if (sticky.length && 'IntersectionObserver' in window) {
            new IntersectionObserver(function(entries){
                var show = !entries[0].isIntersecting;
                sticky.toggleClass('is-visible', show).attr('aria-hidden', show ? 'false' : 'true');
                $('body').toggleClass('mm-sticky-on', show); // lifts the chat bubble above the bar
                sticky.find('a').attr('tabindex', show ? '0' : '-1');
            }).observe(hero);
        }

        var form = $('[data-mm-link-form]'),
            input = form.find('input'),
            error = form.find('[data-mm-link-error]');

        $('[data-mm-open-link]').off('click.mmHome').on('click.mmHome', function(){
            form.prop('hidden', false);
            input.trigger('focus');
            Theme.track('open_group_link_form', {});
        });

        form.off('submit.mmHome').on('submit.mmHome', function(e){
            e.preventDefault();

            var url = null;
            try { url = new URL($.trim(input.val())); } catch (err) {}

            var ok = url && url.hostname.replace(/^www\./, '') === window.location.hostname.replace(/^www\./, '')
                && url.pathname.indexOf('group-dashboard') !== -1
                && url.searchParams.get('gid') && url.searchParams.get('pw');

            error.prop('hidden', !!ok);
            if (ok) window.location.href = url.href;
        });
    },

    /**
     * Fade/slide [data-reveal] elements in as they scroll into view (html.mm-anim is set inline by
     * the homepage only when this can run; mm-reveal-all is its 3-second safety net).
     */
    initReveal: function(){
        window.mmRevealReady = true;

        var items = document.querySelectorAll('[data-reveal]');
        if (!items.length || !('IntersectionObserver' in window)) return;

        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (!entry.isIntersecting) return;
                var el = entry.target;
                el.classList.add('is-in');
                io.unobserve(el);
                setTimeout(function(){ el.style.setProperty('--d', '0s'); }, 1500); // no delay on later hovers
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });

        for (var i = 0; i < items.length; i++) io.observe(items[i]);
    },

    /**
     * Count [data-count] numbers up from 0 when they come into view.
     */
    initCounters: function(){
        var nums = document.querySelectorAll('[data-count]');
        if (!nums.length) return;

        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window) || !window.requestAnimationFrame) return;

        function run(el){
            var target = parseInt(el.getAttribute('data-count'), 10) || 0,
                start = null,
                duration = 1800;

            function frame(ts){
                if (!start) start = ts;
                var p = Math.min((ts - start) / duration, 1),
                    eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * eased).toLocaleString('en-US');
                if (p < 1) window.requestAnimationFrame(frame);
            }

            el.textContent = '0';
            window.requestAnimationFrame(frame);
        }

        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (!entry.isIntersecting) return;
                run(entry.target);
                io.unobserve(entry.target);
            });
        }, { threshold: 0.6 });

        for (var i = 0; i < nums.length; i++) io.observe(nums[i]);
    },

    /**
     * "Who joined" popup: member count, and a name search for bigger groups.
     */
    initWhoJoined: function($){
        var box = $('[data-mm-joined]');
        if (!box.length) return;

        var items = box.find('.mm-joined__item'),
            n = items.length,
            search = box.find('.mm-joined__search'),
            input = box.find('[data-mm-joined-search]'),
            empty = box.find('[data-mm-joined-empty]');

        box.find('[data-mm-joined-count]').html('<strong>' + n + '</strong> ' + (n === 1 ? 'member' : 'members') + ' joined &middot; the group has been shuffled');
        search.prop('hidden', n <= 6);

        input.off('input.mmJoined').on('input.mmJoined', function(){
            var q = $.trim($(this).val()).toLowerCase(), shown = 0;
            items.each(function(){
                var hit = !q || ($(this).attr('data-name') || '').indexOf(q) !== -1;
                $(this).prop('hidden', !hit);
                if (hit) shown++;
            });
            empty.prop('hidden', shown > 0);
        });
    },

    /**
     * Email quality: "Did you mean ...?" on email fields, the Create Group verification code, and the
     * "Email confirmed" notice after a member taps the link in their welcome email.
     */
    initEmailChecks: function($){
        // After joining / confirming (EmailCheck): tell the member where they stand.
        var q = window.location.search;

        // Conversion events (GA4 key events): group created / member joined, sent once then removed from the URL.
        var m = /[?&]mm_new=(new|repeat)/.exec(q), clean = function(name){
            try { var u = new URL(window.location.href); u.searchParams.delete(name); history.replaceState(null, '', u.toString()); } catch (e) {}
        };
        if (m) {
            Theme.track('create_group', { method: m[1] });
            if (m[1] === 'repeat') Theme.track('repeat_group_created', {});
            clean('mm_new');
        }
        if (/[?&]mm_confirmed=1/.test(q)) { Theme.track('join_group', {}); setTimeout(function(){ clean('mm_confirmed'); }, 800); }
        if (/[?&]mm_joined=1/.test(q)) Theme.track('join_request', {});

        if (/[?&]mm_joined=1/.test(q)) {
            setTimeout(function(){
                Theme.infoSheet($, 'Check your email to confirm', 'We sent you a confirmation email. <b>Tap the button in it to be added to the group.</b> You won\u2019t appear in the member list until you confirm. Can\u2019t find it? Check your spam or promotions folder.', 'fa-solid fa-envelope-open-text');
            }, 500);
        } else if (/[?&]mm_confirmed=1/.test(q)) {
            setTimeout(function(){
                Theme.infoSheet($, 'You\u2019re in!', 'Your email is confirmed and you\u2019re now part of the group. Find your name below and <b>add your wishlist</b> so your Secret Santa knows what to get you.', 'fa-solid fa-circle-check');
            }, 500);
        } else if (/[?&]mm_confirmed=late/.test(q)) {
            setTimeout(function(){
                Theme.infoSheet($, 'The group was already shuffled', 'Your email is confirmed, but the group was shuffled before you confirmed, so you couldn\u2019t be added yet. Ask the organizer to <b>unshuffle</b> the group, then tap the link in your email again.', 'fa-solid fa-circle-info');
            }, 500);
        }

        var common = ['gmail.com', 'yahoo.com', 'yahoo.com.ph', 'outlook.com', 'hotmail.com', 'icloud.com', 'ymail.com', 'live.com', 'me.com', 'rocketmail.com', 'msn.com', 'aol.com'];

        function distance(a, b){
            var m = [], i, j;
            for (i = 0; i <= a.length; i++) { m[i] = [i]; }
            for (j = 0; j <= b.length; j++) { m[0][j] = j; }
            for (i = 1; i <= a.length; i++) {
                for (j = 1; j <= b.length; j++) {
                    m[i][j] = Math.min(m[i - 1][j] + 1, m[i][j - 1] + 1, m[i - 1][j - 1] + (a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1));
                }
            }
            return m[a.length][b.length];
        }

        function suggest(email){
            var at = email.lastIndexOf('@');
            if (at < 1) return '';
            var domain = email.slice(at + 1).toLowerCase(), best = '', bestD = 3;
            if (!domain || common.indexOf(domain) !== -1) return '';
            common.forEach(function(c){
                var d = distance(domain, c);
                if (d < bestD) { bestD = d; best = c; }
            });
            return best ? email.slice(0, at + 1) + best : '';
        }

        $(document).off('blur.mmEmail').on('blur.mmEmail', '.acf-form input[type="email"]', function(){
            var input = $(this), field = input.closest('.acf-input'), hint = field.find('.mm-email-hint'),
                fix = suggest($.trim(input.val()));

            if (!fix) { hint.remove(); return; }
            if (!hint.length) hint = $('<p class="mm-email-hint"></p>').appendTo(field);

            hint.html('Did you mean <b></b>? <button type="button">Use this</button>');
            hint.find('b').text(fix);
            hint.find('button').on('click', function(){
                input.val(fix).trigger('input').trigger('change');
                hint.remove();
            });
        });

        // Create Group: verification code box under "Your email"
        var otp = $('[data-mm-otp]');
        if (!otp.length) return;

        var emailField = otp.closest('form').find('.acf-field[data-key="field_5f55bf2fde4cc"]'),
            emailInput = emailField.find('input'),
            box = otp.find('[data-mm-otp-box]'),
            msg = otp.find('[data-mm-otp-msg]'),
            send = otp.find('[data-mm-otp-send]'),
            resend = otp.find('[data-mm-otp-resend]'),
            sentTo = '',
            verified = false,
            timer = null,
            form = otp.closest('form'),
            submitWrap = form.find('.acf-form-submit'),
            submitBtn = submitWrap.find('input[type="submit"], button[type="submit"], .button'),
            gate = form.find('[data-mm-otp-gate]');

        if (emailField.length) otp.insertAfter(emailField);

        if (!gate.length) {
            gate = $('<p class="mm-otp__gate" data-mm-otp-gate><i class="fa-solid fa-lock" aria-hidden="true"></i> <span>Verify your email above to create your group.</span></p>').insertBefore(submitWrap);
        }

        // "Create Group" stays locked until the email is verified (the server checks again on submit).
        function lock(on){
            submitBtn.prop('disabled', on).toggleClass('is-locked', on).attr('aria-disabled', on ? 'true' : 'false');
            gate.prop('hidden', !on);
        }

        form.off('submit.mmOtp').on('submit.mmOtp', function(e){
            if (verified) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            gate.removeClass('is-shake');
            setTimeout(function(){ gate.addClass('is-shake'); }, 10);
            (send.is(':visible') ? send : box.find('input')).trigger('focus');
        });

        function looksValid(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }

        function refresh(){
            var v = $.trim(emailInput.val()).toLowerCase();
            otp.prop('hidden', !looksValid(v));
            if (sentTo && v !== sentTo) { // email changed after sending or verifying: start over
                sentTo = '';
                verified = false;
                box.prop('hidden', true);
                send.prop('hidden', false);
                msg.removeClass('is-error is-ok').text('');
                lock(true);
            }
        }

        function countdown(){
            var left = 30;
            resend.prop('disabled', true).text('Resend in ' + left + 's');
            clearInterval(timer);
            timer = setInterval(function(){
                left--;
                if (left <= 0) { clearInterval(timer); resend.prop('disabled', false).text('Resend'); }
                else { resend.text('Resend in ' + left + 's'); }
            }, 1000);
        }

        function request(btn){
            var email = $.trim(emailInput.val());
            btn.prop('disabled', true);
            msg.removeClass('is-error').text('Sending\u2026');

            $.post($('#ajax-url').val(), { action: 'mm_send_otp', email: email, nonce: otp.find('input[name="mm_otp_nonce"]').val() })
                .done(function(resp){
                    if (resp && resp.success) {
                        sentTo = email.toLowerCase();
                        send.prop('hidden', true);
                        box.prop('hidden', false);
                        box.find('input').val('').trigger('focus');
                        msg.html(resp.data);
                        countdown();
                        Theme.track('otp_sent', {});
                    } else {
                        msg.addClass('is-error').html((resp && resp.data) || 'Could not send the code. Please try again.');
                        btn.prop('disabled', false);
                    }
                })
                .fail(function(){
                    msg.addClass('is-error').text('Network error. Please try again.');
                    btn.prop('disabled', false);
                })
                .always(function(){ if (btn.is(send)) send.prop('disabled', false); });
        }

        emailInput.off('input.mmOtp change.mmOtp').on('input.mmOtp change.mmOtp', refresh);
        send.off('click.mmOtp').on('click.mmOtp', function(){ request(send); });
        resend.off('click.mmOtp').on('click.mmOtp', function(){ request(resend); });
        box.find('input').off('input.mmOtp').on('input.mmOtp', function(){
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
            if (this.value.length !== 6) return;

            var input = $(this).prop('disabled', true);
            msg.removeClass('is-error is-ok').text('Checking\u2026');

            $.post($('#ajax-url').val(), { action: 'mm_check_otp', email: $.trim(emailInput.val()), code: this.value, nonce: otp.find('input[name="mm_otp_nonce"]').val() })
                .done(function(resp){
                    if (resp && resp.success) {
                        verified = true;
                        clearInterval(timer);
                        box.prop('hidden', true);
                        msg.addClass('is-ok').html('<i class="fa-solid fa-circle-check"></i> Email verified. You can create your group now.');
                        lock(false);
                        Theme.track('otp_verified', {});
                    } else {
                        msg.addClass('is-error').html((resp && resp.data) || 'That code isn\u2019t right.');
                        input.val('');
                    }
                })
                .fail(function(){ msg.addClass('is-error').text('Network error. Please try again.'); })
                .always(function(){ input.prop('disabled', false); if (!verified) input.trigger('focus'); });
        });

        lock(true);
        refresh();
    },

    /**
     * ACF form errors: the message ACF puts at the top of the form is easy to miss in a long popup, so
     * the same messages also float over the form (with a close button) and the first problem field
     * scrolls into view.
     */
    initFormAlerts: function($){
        function hook(tries){
            if (!(window.acf && acf.addAction)) {
                if (tries < 20) setTimeout(function(){ hook(tries + 1); }, 250);
                return;
            }

            acf.addAction('validation_failure', function($form){
                setTimeout(function(){ Theme.showFormAlert($, $form); }, 50);
            });

            acf.addAction('validation_success', function(){ $('#mm-form-alert').remove(); });
            acf.addAction('validation_begin', function(){ $('#mm-form-alert').remove(); });
        }

        hook(0);
    },

    showFormAlert: function($, $form){
        $form = $($form);

        var texts = [];
        // Only real, visible errors (ACF can leave hidden notices in the form).
        $form.find('.acf-notice.-error:visible p, .acf-notice.-error:visible').each(function(){
            var t = $.trim($(this).clone().children('a, button').remove().end().text());
            if (t && texts.indexOf(t) === -1 && !$(this).find('p').length) texts.push(t);
        });

        // Drop ACF's generic summary when there is a specific message.
        var specific = texts.filter(function(t){ return !/^validation failed/i.test(t); });
        if (specific.length) texts = specific;
        if (!texts.length) return;

        $('#mm-form-alert').remove();

        var box = $('<div id="mm-form-alert" class="mm-form-alert" role="alert">' +
            '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div class="mm-form-alert__txt"></div>' +
            '<button type="button" class="mm-form-alert__close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>');

        texts.forEach(function(t){ $('<p></p>').text(t).appendTo(box.find('.mm-form-alert__txt')); });

        box.appendTo('body');
        setTimeout(function(){ box.addClass('is-in'); }, 10);

        box.find('.mm-form-alert__close').on('click', function(){ box.remove(); });
        $(document).one('afterClose.fb', function(){ box.remove(); });

        // Bring the first field with a problem into view (or the code box on Create Group).
        var target = $form.find('.acf-field.acf-error').first();
        if (!target.length && /verif|code/i.test(texts.join(' '))) target = $form.find('[data-mm-otp]');
        if (target.length && target[0].scrollIntoView) target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    },

    /**
     * Cloudflare Turnstile, rendered explicitly. The forms sit in hidden popups that Fancybox moves
     * when opening, which reloads (and breaks) a widget drawn at page load; so each time a popup opens
     * its widget is (re)rendered in place. Forms visible on the page are rendered on load.
     */
    initTurnstile: function($){
        if (!$('.mm-turnstile').length) return;

        function ready(cb, n){
            n = n || 0;
            if (window.turnstile && window.turnstile.render) return cb();
            if (n < 60) setTimeout(function(){ ready(cb, n + 1); }, 250);
        }

        function renderIn(scope){
            ready(function(){
                $(scope).find('.mm-turnstile').each(function(){
                    var el = this, old = $(el).data('mmTs');

                    if (old !== undefined) { try { window.turnstile.remove(old); } catch (e) {} }
                    el.innerHTML = '';

                    try {
                        $(el).data('mmTs', window.turnstile.render(el, {
                            sitekey: el.getAttribute('data-sitekey'),
                            appearance: 'interaction-only',
                            size: 'flexible',
                            theme: 'light',
                            'refresh-expired': 'auto'
                        }));
                    } catch (e) {}
                });
            });
        }

        if (!Theme.mmTsBound) {
            Theme.mmTsBound = true;
            // Fancybox fires "afterShow.fb" on the document once a popup's content is in place.
            $(document).on('afterShow.fb', function(e, instance, slide){
                if (slide && slide.$content) renderIn(slide.$content);
            });
        }

        $('.mm-turnstile:visible').each(function(){ renderIn($(this).parent()); });
    },

    initAffiliateCap:function($){
        var key = 'mm_aff_last_click',
            day = 24 * 60 * 60 * 1000;

        try {
            var last = parseInt(window.localStorage.getItem(key), 10);
            if (last && (Date.now() - last) < day) $('body').addClass('aff-recent');
        } catch (e) {}

        $(document).on('click', 'a.mm-aff', function(){
            try { window.localStorage.setItem(key, String(Date.now())); } catch (e) {}
        });
    },

    initCustomPopup:function($){
        var setting = {
            'edit-group': {
                title: 'EDIT GROUP<span>DETAILS</span>',
                formid: 27,
                uid: Theme.userid
            },
            'edit-member': {
                title: 'EDIT MEMBER<span>DETAILS</span>',
                formid: 42,
                uid: Theme.userid
            },
            'edit-wishlist': {
                title: 'UPDATE WISH<span>LIST</span>',
                formid: 125,
                uid: Theme.userid
            },
            'see-match': {
                title: 'SEE<span>MATCH</span>',
                formid: 9999,
                uid: Theme.userid
            },
            'leave-group': {
                title: 'LEAVE<span>GROUP</span>',
                formid: 99999,
                uid: Theme.userid
            },
        };

        $('#custom-popup .popup__title').html(setting[Theme.action].title); //set popup title
        $('#custom-popup .popup__inner').html('');

        $.fancybox.open({
            src: '#custom-popup',
            type: 'inline',
            animationDuration : 600,
            animationEffect   : 'slide-in-out',
            btnTpl : {
                smallBtn:
                '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}">' +
                '<i class="fas fa-times"></i>' +
                "</button>",
            },
            afterShow : function( instance, current ) {
                Theme.moveCloseButton($, current);
            },
            beforeShow : function( instance, current ) {
                $.ajax ({
                    url: $('#ajax-url').val(),
                    type: 'POST',
                    dataType: 'HTML',
                    data: {
                        // the value of data.action is the part AFTER 'wp_ajax_' in
                        // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                        action: 'load_popup_content',
                        // ANY other properties of data are passed to your_function()
                        // in the PHP global $_REQUEST (or $_POST in this case)
                        form : setting[Theme.action].formid,
                        uid : setting[Theme.action].uid
                        },
                    beforeSend : function()    {           
    
                    },
                    success: function (resp) {
                        if(resp){
                            var con = (resp.substr(resp.length-1, 1) === '0')?resp.substr(0, resp.length-1):resp;
                            $('#custom-popup .popup__inner').html(con); //set popup title

                            if(setting[Theme.action].formid == 125) Theme.initWishlistEditor($);

                            if((setting[Theme.action].formid != 99999) && (setting[Theme.action].formid != 9999) && (setting[Theme.action].formid != 125)){
                                acf.do_action('append', $('#custom-popup .popup__inner'));
                                Theme.acfFormScripts($);
                                Theme.initPasswordToggles($);
                                $('#custom-popup .popup__inner .acf-label').css('top', '13px');
                                $('#custom-popup .hide-dash').hide();
                            }

                            if(setting[Theme.action].formid == 99999) Theme.initLeaveGroup($, setting[Theme.action].uid);
                        }

                        Theme.afflinkFunction($);
                        //Theme.removeOverlay($);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        // this error case means that the ajax call, itself, failed, e.g., a syntax error
                        // in your_function()
                        console.log('Request failed: ' + thrownError.message) ;
                        //Theme.removeOverlay($);
                        },
                });
            }
        });
    },

    /**
     * Wishlist editor in the Edit Wishlist popup (markup: Users::generateWishlistEditor()).
     */
    initWishlistEditor: function($, $editor){
        var $form = $editor || $('#custom-popup .mm-edit__form'); // group popup, or My Wishlist (its own form)
        if (!$form.length) return;

        var cfg = $form.data('config') || {},
            $list = $form.find('[data-mm-items]'),
            itemTpl = $form.find('[data-mm-item-tpl]').html(),
            linkTpl = $form.find('[data-mm-link-tpl]').html(),
            $status = $form.find('[data-mm-status]'),
            storeOf = function(url){
                if (/(^|\.|\/\/)lazada\.com\.ph/i.test(url)) return 'Lazada';
                if (/(^|\.|\/\/)(shopee\.ph|shp\.ee|shope\.ee)/i.test(url)) return 'Shopee';
                return url ? 'Link' : 'Link';
            },
            findUrl = function(store, text){
                var s = cfg.stores && cfg.stores[store];
                if (!s) return '#';
                var search = s.search.replace('%25s', encodeURIComponent(text));
                return s.prefix ? s.prefix + encodeURIComponent(search) : search;
            },
            keywords = function(text){
                return $.trim(text.replace(/(https?:\/\/|www\.)\S+/ig, ' ').replace(/\s+/g, ' ')).split(' ').slice(0, 6).join(' ');
            },
            autosize = function(el){
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = (el.scrollHeight + 2) + 'px';
            },
            counter = function($it){
                var len = ($it.find('[data-mm-text]').val() || '').length, max = parseInt($it.find('[data-mm-text]').attr('maxlength'), 10) || 500;
                $it.find('[data-mm-chars]').text(len);
                $it.find('[data-mm-meta]').toggleClass('is-near', len > max * 0.9);
            },
            status = function(msg, type){
                $status.text(msg || '').attr('data-type', type || '');
            },
            refresh = function(){
                var $items = $list.children('[data-mm-item]');

                $items.each(function(i){
                    var $it = $(this), text = $it.find('[data-mm-text]').val() || '', kw = keywords(text);

                    $it.find('[data-mm-num]').text(i + 1);
                    counter($it);
                    $it.find('[data-mm-remove-item]').toggle($items.length > 1 || $.trim(text) !== '' || $it.find('[data-mm-link]').length > 0);
                    $it.find('[data-mm-add-link]').toggle($it.find('[data-mm-link]').length < cfg.maxLinks);

                    var $find = $it.find('[data-mm-find]');
                    $find.prop('hidden', kw.length < 3);
                    $find.find('[data-store]').each(function(){
                        $(this).attr('href', findUrl($(this).data('store'), kw));
                    });
                });

                $form.find('[data-mm-count]').text($items.length);
                $form.find('[data-mm-add-item]').toggle($items.length < cfg.maxItems);
            },
            addItem = function(text){
                var $empty = $list.children('[data-mm-item]').filter(function(){
                    return $.trim($(this).find('[data-mm-text]').val()) === '' && $(this).find('[data-mm-link]').length === 0;
                }).first();
                var $it = $empty.length ? $empty : $(itemTpl).appendTo($list);

                if (text) $it.find('[data-mm-text]').val(text);
                autosize($it.find('[data-mm-text]')[0]);
                refresh();
                return $it;
            };

        $form.find('[data-mm-url]').each(function(){
            $(this).siblings('[data-mm-store]').text(storeOf($(this).val())).attr('data-store', storeOf($(this).val()).toLowerCase());
        });

        $form.on('click', '[data-mm-add-item]', function(){
            if ($list.children('[data-mm-item]').length >= cfg.maxItems) return;
            addItem('').find('[data-mm-text]').trigger('focus');
        });

        $form.on('click', '[data-mm-remove-item]', function(){
            var $it = $(this).closest('[data-mm-item]');

            if ($list.children('[data-mm-item]').length > 1) {
                $it.remove();
            } else {
                $it.find('[data-mm-text]').val('');
                $it.find('[data-mm-link]').remove();
            }
            refresh();
        });

        $form.on('click', '[data-mm-add-link]', function(){
            var $links = $(this).closest('[data-mm-item]').find('[data-mm-links]');
            if ($links.children().length >= cfg.maxLinks) return;
            $(linkTpl).appendTo($links).find('[data-mm-url]').trigger('focus');
            refresh();
        });

        $form.on('click', '[data-mm-remove-link]', function(){
            $(this).closest('[data-mm-link]').remove();
            refresh();
        });

        $form.on('input', '[data-mm-url]', function(){
            var st = storeOf($(this).val());
            $(this).siblings('[data-mm-store]').text(st).attr('data-store', st.toLowerCase());
            $(this).closest('[data-mm-link]').removeClass('is-invalid');
        });

        $form.on('input', '[data-mm-text]', function(){
            autosize(this);
            $(this).closest('[data-mm-item]').removeClass('is-invalid').find('[data-mm-error]').text('');
            refresh();
        });

        $form.on('click', '[data-mm-suggest]', function(){
            var label = $(this).data('mm-suggest'), $btn = $(this),
                exists = $list.find('[data-mm-text]').filter(function(){
                    return $.trim($(this).val()).toLowerCase() === String(label).toLowerCase();
                }).length > 0;

            if (!exists) {
                if ($list.children('[data-mm-item]').length >= cfg.maxItems) {
                    status('You can add up to ' + cfg.maxItems + ' items.', 'error');
                    return;
                }
                var $it = addItem(label);
                $it.addClass('is-new');
                setTimeout(function(){ $it.removeClass('is-new'); }, 1200);
            }
            $btn.addClass('is-added').html('<i class="fas fa-check"></i> Added');
        });

        $form.on('submit', function(e){
            e.preventDefault();

            var data = { action: $form.find('[name="action"]').val() || 'save_wishlist', uid: $form.find('[name="uid"]').val(), nonce: $form.find('[name="nonce"]').val(), address: $form.find('[name="address"]').val(), items: [] },
                firstError = null;
            $.each(['id', 'name', 'note', 'password'], function(i, k){ var f = $form.find('[name="' + k + '"]'); if (f.length) data[k] = f.val(); });

            $list.children('[data-mm-item]').each(function(){
                var $it = $(this), text = $.trim($it.find('[data-mm-text]').val()), links = [];

                $it.find('[data-mm-url]').each(function(){
                    var v = $.trim($(this).val());
                    if (!v) return;
                    if (!/^https?:\/\//i.test(v)) v = 'https://' + v.replace(/^\/+/, '');
                    if (!/^https?:\/\/[^\s\/]+\.[^\s]{2,}/i.test(v)) {
                        $(this).closest('[data-mm-link]').addClass('is-invalid');
                        firstError = firstError || $(this);
                        return;
                    }
                    links.push(v);
                });

                if (text === '' && links.length > 0) {
                    $it.addClass('is-invalid').find('[data-mm-error]').text('Add a short description so your match knows what this link is for.');
                    firstError = firstError || $it.find('[data-mm-text]');
                }

                if (text !== '' || links.length > 0) data.items.push({ text: text, links: links });
            });

            if (firstError) {
                status('Please fix the highlighted fields.', 'error');
                firstError.trigger('focus');
                return;
            }

            var $save = $form.find('[data-mm-save]').prop('disabled', true);
            status('Saving...', 'busy');

            $.ajax({
                url: $('#ajax-url').val(),
                type: 'POST',
                dataType: 'JSON',
                data: data
            }).done(function(resp){
                if (resp && resp.success) {
                    status(resp.data.message, 'ok');
                    setTimeout(function(){ window.location.reload(); }, 700);
                } else {
                    status((resp && resp.data && resp.data.message) || 'Something went wrong. Please try again.', 'error');
                    $save.prop('disabled', false);
                }
            }).fail(function(){
                status('Could not save. Check your connection and try again.', 'error');
                $save.prop('disabled', false);
            });
        });

        $form.find('textarea').each(function(){ autosize(this); });
        $form.on('input', 'textarea[name="address"]', function(){ autosize(this); });
        refresh();
    },

    initLeaveGroup: function($, uids){
        $('.leave-btns a[data-action="false"]').click(function(e){
            e.preventDefault();
            $.fancybox.close();
        });

        $('.leave-btns a[data-action="true"]').click(function(e){
            e.preventDefault();
            $.ajax ({
                url: $('#ajax-url').val(),
                type: 'POST',
                dataType: 'JSON',
                data: {
                    // the value of data.action is the part AFTER 'wp_ajax_' in
                    // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                    action: 'user_leave_group',
                    // ANY other properties of data are passed to your_function()
                    // in the PHP global $_REQUEST (or $_POST in this case)
                    uid : uids
                    },
                beforeSend : function()    {           

                },
                success: function (resp) {
                    if(resp){
                        $('#reload').submit(); //reload
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    // this error case means that the ajax call, itself, failed, e.g., a syntax error
                    // in your_function()
                    console.log('Request failed: ' + thrownError.message) ;
                    Theme.removeOverlay($);
                    },
            });
        });
    },

    initShowOverlay: function($, parent='body') {
        var ldr = $('#base-url').val() + '/wp-content/themes/eyor-theme/assets/images/loader.gif';
        $(parent).prepend('<div class="loader-overlay"><img src="' + ldr + '"></div>');
    },

    removeOverlay: function($) {
        $('.loader-overlay').remove();
    },

    copyToClipboard: function($, elem){
        /* Get the text field */
        var copyText = document.getElementById("clipboard");
        
        /* Select the text field */
        copyText.select();
        copyText.setSelectionRange(0, 99999); /*For mobile devices*/
        
        /* Copy the text inside the text field */
        document.execCommand("copy");
        
        /* Alert the copied text */
        $('.clip .label').show();
        $('.clip .label').effect( "shake");
    },

    acfFormScripts: function($){
        $('.acf-form input, .acf-form textarea').off('focus.mmAcf').on('focus.mmAcf', function(){
            var l = $(this).parents('.acf-field').find('.acf-label');
            l.css('top', '13px');

            $(this).parents('.acf-field').find('.acf-label p').show();
        });

        $('.acf-label').off('click.mmAcf').on('click.mmAcf', function(e){
            e.preventDefault();
            var l = $(this);
            l.css('top', '13px');

            l.parent().find('input').focus();
        });

        $('.acf-form input, .acf-form textarea').off('keyup.mmAcf').on('keyup.mmAcf', function(){
            $(this).trigger('focus');
        });
    },

    initPopupModal: function($){
        var btn = ['create-group','join-group'];

        for(var i=0;i<btn.length;i++){
            $('a[href="#'+btn[i]+'"]').attr({
                'data-fancybox':'',
                'data-src' : '#'+btn[i]+'',
                'href' : 'javascript:;'
            });
        }

        $('[data-fancybox]').fancybox({
            animationDuration : 600,
            animationEffect   : 'slide-in-out',
            btnTpl : {
                smallBtn:
                '<button data-fancybox-close class="fancybox-button fancybox-button--close" title="{{CLOSE}}">' +
                '<i class="fas fa-times"></i>' +
                "</button>",
            },
            afterShow : function( instance, current ) {
                Theme.moveCloseButton($, current);
            }
        });

        $('#dee-slider').css('height', $(window).height() + 'px');
    },

    _initHamburgerMenu: function() {
        var menuTrigger = jQuery('#hamburger-menu'),
            bottomLayer = jQuery('.bottom_layer'),
            mainNav = jQuery('.main-nav-header');

        menuTrigger.on('click', function(e){
            e.preventDefault();

            if ( menuTrigger.hasClass('hamburger-menu-close') ) {
                menuTrigger.removeClass('hamburger-menu-close').addClass('hamburger-menu-open');
                bottomLayer.css('visibility', 'visible');
                //mainNav.show('fast');
            } else {
                menuTrigger.removeClass('hamburger-menu-open').addClass('hamburger-menu-close');
                bottomLayer.css('visibility', 'hidden');
                //mainNav.hide('fast');
            }
        });
    },

    initSVGanimation: function( config ) {
        // For option list check https://github.com/maxwellito/vivus
        var cfg = jQuery.extend( {
                svgSelector:'',
            }, config || {}),
            svgCfg = {
                type: 'oneByOne',
                duration: 60,
                animTimingFunction: Vivus.EASE
            };

        if ( ! cfg.svgSelector ) {
            return null;
        }

        if ( cfg.vivusOptions ) {
            jQuery.extend( svgCfg, cfg.vivusOptions );
        }

        var noWinNoFee = new Vivus(cfg.svgSelector, svgCfg);
    },

    initGoogleMap: function( cfg ) {
        if ( 'undefined' == typeof( cfg ) ) {
            return;
        }

        var mapElement = document.getElementById( cfg.element_id );

        if ( ! mapElement ) {
            return;
        }

        var jMap = jQuery( mapElement );
        jMap.height( cfg.height );

        if ( cfg.full_width ) {
            var onResizeHandler = function() {
                jMap.width( jQuery( window ).outerWidth() )
                    .offset( { left:0 } );
                if ( map ) {
                    //google.maps.event.trigger(map, 'resize');
                    if ( mapLang ) {
                        map.setCenter( mapLang );
                    }
                }
            };
            onResizeHandler();
            jQuery( window ).on( 'resize', onResizeHandler );
        }

        var mapLang = new google.maps.LatLng( parseFloat( cfg.coordinates[0] ), parseFloat( cfg.coordinates[1] ) ),
            map = new google.maps.Map( mapElement, {
                scaleControl: true,
                center: mapLang,
                zoom: cfg.zoom,
                mapTypeId: cfg.MapTypeId || google.maps.MapTypeId.ROADMAP,
                scrollwheel: false
            }),
            marker = new google.maps.Marker({
                map: map,
                position: map.getCenter()
            });

        // Registers map instance in _inited_maps collection.
        if ( ! this._inited_maps ) this._inited_maps = {};
        this._inited_maps[cfg.element_id] = map;

        if ( cfg.address ) {
            var infowindow = new google.maps.InfoWindow();
            infowindow.setContent( cfg.address );
            google.maps.event.addListener( marker, 'click', function() {
                infowindow.open( map, marker );
            });
        }

        // Fix display map in bootstrap tabs and accordion.
        if ( cfg.is_reset_map_fix_for_bootstrap_tabs_accrodion ) {
            jQuery( document ).on( 'shown.bs.collapse shown.bs.tab', '.panel-collapse, a[data-toggle="tab"]', function() {
                google.maps.event.trigger( map, 'resize' );
                map.setCenter( mapLang );
            });
        }
    },

    initStickyHeader: function() {
        if(Theme.isMobile()){
            return false;
        }

        var doc = jQuery( document ),
            headerIsSticky = false,
            headerWrap = jQuery( '.header-wrap' ),
            headerInfo = headerWrap.find( '.header__info' ),
            headerBacklog = headerWrap.find( '.header-wrap__bundle' ),
            headerWrapClassSticky = 'header-wrap--sticky-header',
            stickyBreakpoint = null,
            switchHeightDelay = 500,
            calculateHeaderInfo = function(){
                stickyBreakpoint = headerInfo.outerHeight() + switchHeightDelay;

                headerBacklog.css({
                    'min-height': headerWrap.find( '.header__content-wrap' ).outerHeight() + 'px',
                    'margin-top': stickyBreakpoint + 'px'
                });
            };
        setTimeout( calculateHeaderInfo, 10 );
        jQuery( window ).on( 'resize', calculateHeaderInfo );

        doc.on( 'scroll', function() {
            var newState = doc.scrollTop() > stickyBreakpoint;
            if ( newState != headerIsSticky ) {
                headerIsSticky = newState;

                if ( newState ) {
                    headerWrap.addClass( headerWrapClassSticky );
                    jQuery('#dee-slider').css('margin-top', '0');
                } else {
                    headerWrap.removeClass( headerWrapClassSticky );
                    jQuery('#dee-slider').css('margin-top', '-200px');
                }
            }
        });
    },

    initResponsiveTables: function() {
        jQuery(".page-single__content table").each(function(){
            jQuery(this).addClass('table');
            jQuery(this).wrapAll('<div class="table-responsive" />');
        });
        jQuery("#taxes-pay-table").each(function(){
            jQuery(this).addClass('table');
            jQuery(this).wrapAll('<div class="table-responsive" />');
        });
    },

    initFocusButtonContents: function() {
        var self = this;
        jQuery(".focus-button__item-link-content").on( "click", function(e) {
            e.preventDefault();
            console.log('clicked');
            var wrapperId = jQuery( this ).attr('href');
            jQuery(".focus-button__item").removeClass('active');
            jQuery(this).closest( ".focus-button__item" ).addClass('active');
            //jQuery('.page-single').hide();
            //jQuery('#focus-buttons__content-wrapper').show();
            jQuery( '.focus-buttons__content-wrap').hide();
            jQuery( wrapperId ).show();
            if ( jQuery( window ).width() > 768 ) {
                self._scrollToElementHelper( wrapperId, 200);
            } else {
                self._scrollToElementHelper( wrapperId, 140);
            }
            
        });
    },

    initFocusButtonScrollToContent: function( activated ) {
        var self = this;
        if ( activated ) {
            jQuery( document ).ready(function() {
                console.log( "ready!" );
                var wrapperId = jQuery( '.focus-buttons__content');
                if ( jQuery( window ).width() > 768 ) {
                    self._scrollToElementHelper( wrapperId, 200);
                } else {
                    self._scrollToElementHelper( wrapperId, 140);
                }
            });
        }
    },

    _scrollToElementHelper: function( wrapperId, offsetFix) {
        jQuery( 'html, body' ).animate({
                scrollTop: jQuery( wrapperId ).offset().top - offsetFix
        }, 2000);
    },

    /**
     * Initialization modile menu.
     * @use jquery.slicknav.js, slicknav.css
     * @return void
     */
    _initMobileMenu:function() {
        var mainBtn,
            closeClass = 'slicknav_btn--close',
            itemClass = 'slicknav_item',
            itemOpenClass = 'slicknav_item--open';

        jQuery( '#navigation' ).slicknav({
            label:'',
            prependTo:'.header__content',
            openedSymbol: '',
            closedSymbol: '',
            allowParentLinks:true,
            beforeOpen: function( target ) {
                if ( target.length ) {
                    if ( target[0] == mainBtn ) {
                        target.addClass( closeClass );
                    }else if ( target.hasClass( itemClass ) ) {
                        target.addClass( itemOpenClass );
                    }
                }
            },
            beforeClose: function( target ) {
                if( target.length ){
                    if( target[0] == mainBtn ) {
                        target.removeClass( closeClass );
                    }else if( target.hasClass( itemClass ) ) {
                        target.removeClass( itemOpenClass );
                    }
                }
            }
        });

        mainBtn = jQuery( '.slicknav_btn' );
        mainBtn = mainBtn.length ? mainBtn[0] : null;
    },

    /**
     * Initialization custom select box.
     *
     * @use bootstrap.min.js, bootstrap-select.min.js, bootstrap-select.min.css
     * @param String|jQuery elements
     * @return void
     */
    initSelectpicker: function( elements ) {
        var self = this,
            collection = elements ? jQuery( elements ) : null;

        if ( null === collection ) {
            collection = jQuery( 'select.selectpicker' )
                .add( '.widget select' ); // Use .add() to add select elements.
        }

        if ( ! collection  || collection.length < 1 ) {
            return false;
        }

        var liveAutoStartLimit =  this.selectLiveSearchAutoStart;
        if ( liveAutoStartLimit > 0 ) {
            collection.each(function() { // .not('[data-live-search]')
                if ( this.children.length >= liveAutoStartLimit  && ! this.hasAttribute( 'data-live-search' ) ) {
                    jQuery( this ).attr( 'data-live-search', true );
                }
            });
        }

        collection.each(function() {
                // var el = jQuery( this );
                // if ( el.attr('multiple') ) {
                //     el.selectpicker({selectedTextFormat:'static'});
                // } else {
                //     el.selectpicker();
                // }
                self._fixSelectpickerEmptyClass( this );
                //self._fixSelectpickerMultiSelectedText( this );
            })
            .on( 'change', function() {
                self._fixSelectpickerEmptyClass( this );
            }
        );
    },

    _fixSelectpickerEmptyClass:function( node ) {
        var el = jQuery( node ),
            isSelectpicker = el.data( 'selectpicker' ) ? true : false;
        if ( ! isSelectpicker ) {
            return;
        }
        var emptyClass = 'selectpicker--empty';
        if ( el.val() ) {
            el.selectpicker( 'setStyle', emptyClass, 'remove' );
        } else {
            el.selectpicker( 'setStyle', emptyClass, 'add' );
        }
    },

    _fixSelectpickerMultiSelectedText:function( node ) {
        var el = jQuery( node );
        if ( ! isSelectpicker ) {
            return;
        }

        if ( el.hasAttr('multiple') ) {
            el.selectpicker( {selectedTextFormat:'static'} );
        }
    },

    _initTabsStateFromHash:function( $ ) {
        if ( this.disableTabStateRestore || ! document.location.hash ) {
            return;
        }
        var hash = document.location.hash;
        if ( hash.search( 'accordion' ) < 0 ) {
            var tabLink = $( '.nav-tabs a[href="' + hash + '"]' );
            if ( tabLink.length ) {
                tabLink.tab( 'show' );
            }
        } else {
            var accordionLink = $( '.accordion__item a[href="' + hash + '"]' );
            if ( accordionLink.length ) {
                accordionLink.trigger( 'click' );
            }
        }
    },

    _makeDatepickerConfig:function( customOptions ) {
        if ( window.ThemeSDDatepickerCfg ) {
            return jQuery.extend( {}, window.ThemeSDDatepickerCfg, customOptions || {} );
        }
        return customOptions;
    },

    initResizeHandler: function( config ) {
        var cfg = jQuery.extend( {
            deviceType:'desktop',
            }, config || {});
        _sliderResizeHandler = function(){
            var windowWidth = jQuery( window ).width();
            var deviceType = cfg.deviceType;

            if (windowWidth >= 992) {
                deviceType = 'desktop';
            } else {
                deviceType = 'mobile';
            }

            var isNewValue = cfg.deviceType != deviceType;

            if ( isNewValue ) {
                if ( isNewValue ) {
                    cfg.deviceType = deviceType;
                    switch (cfg.deviceType) {
                        case 'desktop':
                            //jQuery('#eem-slider').hide();
                            jQuery('#dee-slider').show();
                            break;
                        case 'mobile':
                            jQuery('#dee-slider').show();
                            //jQuery('#eem-slider').show();
                            break;
                    }
                }
            }
        };
        jQuery( window ).on( 'resize', _sliderResizeHandler );//.trigger('resize');
    },

    /**
     * Create swiper sliders.
     *
     * @param numSlides config
     */
    makeSwiper: function( config ) {
        var cfg = jQuery.extend( {
            containerSelector:'',
            slidesNumber:4,
            navPrevSelector:'',
            navNextSelector:'',
            sliderElementSelector:'.swiper-slider',
            slideSelector: '.swiper-slide',
            widthToSlidesNumber:function( windowWidth, slidesPerView ) {
                var result = slidesPerView;
                if (windowWidth > 992) {

                } else if(windowWidth > 768) {
                    //result = Math.max(3, Math.ceil(slidesPerView / 2));
                    result = Math.ceil(slidesPerView / 2);
                } else if (windowWidth > 670) {
                    result = 2;
                } else {
                    result = 1;
                }

                return result;
            }
        }, config || {} );
        if ( ! cfg.containerSelector ) {
            return null;
        }

        var numSlides = cfg.slidesNumber,
            container = jQuery( cfg.containerSelector ),
            sliderElement = container.find( cfg.sliderElementSelector ),
            realSlidesNumber = sliderElement.find( cfg.slideSelector ).length,
            swiperCfg = {
                slidesPerView: numSlides,
                spaceBetween: 30,
                loop: numSlides < realSlidesNumber
                //,loopedSlides: 0
            };
        if ( cfg.swiperOptions ) {
            jQuery.extend( swiperCfg, cfg.swiperOptions );
        }

        var swiper = new Swiper( sliderElement, swiperCfg ),
            navButtons = null,
            naviPrev = null,
            naviNext = null;
        if( cfg.navPrevSelector ) {
            naviPrev = container.find( cfg.navPrevSelector );
            if ( naviPrev.length ) {
                naviPrev.on( 'click', function( e ) {
                    e.preventDefault();
                    swiper.slidePrev();
                });
                navButtons = jQuery( naviPrev );
            }
        }
        if ( cfg.navNextSelector ) {
            naviNext = container.find( cfg.navNextSelector );
            if ( naviNext.length ) {
                naviNext.on( 'click', function( e ) {
                    e.preventDefault();
                    swiper.slideNext();
                });
                navButtons = navButtons ? navButtons.add( naviNext ) : jQuery( naviNext );
            }
        }

        var isFirstCall = true,
        _resizeHandler = function(){
            var slidesPerView = numSlides;

            if ( cfg.widthToSlidesNumber && 'function' == typeof cfg.widthToSlidesNumber ) {
                slidesPerView = cfg.widthToSlidesNumber( jQuery( window ).width(), numSlides );
            }

            var isNewValue = swiper.params.slidesPerView != slidesPerView;

            if ( isFirstCall || isNewValue ) {
                if ( isNewValue ) {
                    swiper.params.slidesPerView = slidesPerView;
                    swiper.update();
                }

                if ( navButtons ) {
                    if ( slidesPerView < realSlidesNumber && realSlidesNumber > 1 ) {
                        navButtons.show();
                    } else {
                        navButtons.hide();
                    }
                }
                if ( isFirstCall ) {
                    isFirstCall = false;
                }
            }
        };
        jQuery( window ).on( 'resize', _resizeHandler );//.trigger('resize');
        _resizeHandler();
    },

    /**
     * Create slideshows.
     *
     * @param numSlides config
     */
    makeSlider: function( config ) {
        var cfg = jQuery.extend( {
            sliderSelector:'',
            slideTransitionType:'',
            nextSelector:'',
        }, config || {}),
        sliderCfg = {
            autoplay: true,
            dots: false,
            arrows: false,
            infinite: true,
            speed: 500,
            fade: true,
            cssEase: 'linear',
            slidesToShow: 1,
            slidesToScroll: 1,
        };
        if ( ! cfg.sliderSelector && ! cfg.slideTransitionType ) {
            return null;
        }

        if ( cfg.sliderOptions ) {
            jQuery.extend( sliderCfg, cfg.sliderOptions );
        }

        var slider = jQuery( cfg.sliderSelector ).slick( sliderCfg );

        if ( 'click' === cfg.slideTransitionType ) {
            jQuery( cfg.nextSelector ).on( 'click', function(){
                slider.slick('slickNext');
            });
        }
    },

    /**
     * Modify slideshow delay.
     *
     * @param numSlides config
     */
    //modifySliderDelay: function( slider, currentSlide, imagePauses ) {
    //    var self = this;
    //    var cfg = jQuery.extend( {
    //        mode: 'horizontal',
    //        infiniteLoop: true,
    //        auto: false,
    //        autoStart: false,
    //        autoDirection: 'next',
    //        autoHover: true,
    //        autoControls: false,
    //        pager: true,
    //        pagerType: 'full',
    //        controls: true,
    //        captions: true,
    //        speed: 500,
    //        startSlide: startSlide,
    //        onSlideAfter: function($el,oldIndex, newIndex){
    //            self( slider, currentSlide, imagePauses[newIndex] );
    //        }
    //    }, config || {});
    //
    //    setTimeout( slider.goToNextSlide(), imagePauses[currentSlide] );
    //
    //    slider.reloadSlider( cfg );
    //},

    initParallax: function( selector ) {
        if ( ! selector ) {
            selector = '.parallax-image';
        }

        jQuery( selector ).each(function() {
            var element = jQuery( this ),
                speed = element.data( 'parallax-speed' );
            element.parallax( "50%", speed ? speed : 0.4 );
        });
    },

    _initScrollTop: function( $ ) {
        var document = $( 'body, html' ),
            link = $( '.footer__arrow-top' ),
            windowHeight = $( window ).outerHeight(),
            documentHeight = $( document ).outerHeight();

        if( windowHeight >= documentHeight ) {
            link.hide();
        }

        link.on( 'click', function( e ) {
            e.preventDefault();

            document.animate({
                scrollTop: 0
            }, 800 );
        });
    },

    init_faq_question_form: function( formSelector ) {
        var form = jQuery( formSelector ),
            formContent = jQuery( '.form-block__content' ),
            formElMsgSuccess = jQuery( '.form-block__validation-success' );

        if ( form.length < 1 ) {
            return;
        }

        var noticeWrapper = form.find( '.form-block__validation-error' ),
            resetFormErrors = function() {
                form.find( '.field-error-msg' ).remove();
                noticeWrapper.html( '' );
            };

        Theme.FormValidationHelper.initTooltip();

        form.on( 'submit', function( e ) {
            //e.preventDefault();
            var dataArray = form.serializeArray(),
                formData = {};

            jQuery.each( dataArray, function( i, item ) {
                formData[item.name] = item.value;
            });

            jQuery.ajax({
                url: form.attr( 'action' ),
                data: formData,
                method:'POST',
                error:function( responseXHR ) {
                    var res = responseXHR.responseJSON ? responseXHR.responseJSON : {};
                    resetFormErrors();
                    Theme.FormValidationHelper.formReset( formSelector );

                    if ( res.field_errors ) {
                        jQuery.each( res.field_errors, function( fieldKey, message ) {
                            var el = form.find( '[name*="[' + fieldKey + ']"]' );
                            el.tooltip( 'destroy' );
                            setTimeout(function() {
                                Theme.FormValidationHelper.initTooltip( el );
                                Theme.FormValidationHelper.itemMakeInvalid( el, message );
                            }, 200 );
                        });
                    }

                    if ( res.error ) {
                        noticeWrapper.html( '<i class="fa fa-exclamation-triangle"></i>' + res.error );
                    }
                },
                success:function( res ) {
                    resetFormErrors();
                    Theme.FormValidationHelper.formReset( formSelector );
                    if ( res.message ) {
                        formContent.fadeOut( 400, function() {
                            formElMsgSuccess.html( res.message );
                        });
                    }
                    if ( res.success ) {
                        form[0].reset();
                    }
                }
            });

            return false;
        });
    },

    /**
     * Initilize sharrre buttions.
     * @param  object config
     * @return void
     */
    initSharrres: function( config ) {
        if ( ! config || typeof config != 'object' || ! config.itemsSelector ) {
            //throw 'Parameters error.';
            return;
        }

        var curlUrl = config.urlCurl ? config.urlCurl : '',
            elements = jQuery( config.itemsSelector );

        if ( elements.length < 1 ) {
            return;
        }

        var initSharreBtn = function() {
            var el = jQuery( this ),
                url = el.parent().data( 'urlshare' ),
                imageUrl = el.parent().data( 'imageshare' ),
                curId = el.data( 'btntype' ),
                curConf = {
                    urlCurl: curlUrl,
                    enableHover: false,
                    enableTracking: true,
                    url: ( '' !== url ) ? url : document.location.href,
                    share: {},
                    buttons: {
                        pinterest: {
                            media: imageUrl
                        },
                        //vk: {
                        //    image: imageUrl
                        //}
                    },
                    click: function( api, options ) {
                        api.simulateClick();
                        api.openPopup( curId );
                    }
                };

            curConf.share[curId] = true;
            el.sharrre( curConf );
        };
        elements.each( initSharreBtn );

        // To prevent jumping to the top of page on click event.
        setTimeout(function() {
            jQuery( 'a.share,a.count', config.itemsSelector ).attr( 'href', 'javascript:void(0)' );
        }, 1500 );
    },

    /**
     * Initilize Search Form in popup.
     * @use jquery.magnific-popup.min.js magnific-popup.css
     * @return void
     */
    initSerchFormPopup: function( config ) {
        var classHide = 'search-form-popup--hide',
            cfg = jQuery.extend({
                placeholder_text: 'Type in your request...'
            }, config || {});

        jQuery( '.popup-search-form' ).magnificPopup({
            type: 'inline',
            preloader: false,
            focus: '#s',
            //closeMarkup: '<button title="%title%" type="button" class="mfp-close"><i class="fa fa-times"></i></button>',
            showCloseBtn: false,
            removalDelay: 500, // Delay removal by X to allow out-animation.
            fixedContentPos: false,
            callbacks: {
                beforeOpen: function() {
                    this.st.mainClass = this.st.el.attr( 'data-effect' );
                },
                open: function() {
                    this.content.removeClass( classHide );
                    jQuery( '.mfp-close' ).on( 'click', function() {
                        jQuery.magnificPopup.close();
                    });
                },
                close: function() {
                    this.content.addClass( classHide );
                }
            },
            midClick: true // Allow opening popup on middle mouse click. Always set it to true if you don't provide alternative source.
        });

        if ( cfg.placeholder_text ) {
            jQuery( '.search-form-popup' )
                .find( '.search-field' )
                .attr( 'placeholder', cfg.placeholder_text );
        }
    },

};

/**
 * Gallery plugin.
 * Enables filtering and pagination functionalities.
 *
 * @param {jQuery|selector} container
 * @param {Oject}           config
 */
Theme.Gallery = function( container, config ) {
    if ( config ) {
        jQuery.extend( this, config );
    }

    this.cnt = jQuery( container );

    this._init();
};

Theme.Gallery.prototype = {

    paginationSl: '.pagination',
    imagesContainerSl:'.gallery__items',
    filterButtonsSl: '.gallery__navigation a',
    filterButtonActionClass: 'gallery__navigation__item-current',
    animationClass: 'animated',
    _jPager:null,

    /**
     * Settings for jPages plugin
     *
     * @see initPagination
     * @type {Object}
     */
    paginationConfig:{
        // container: '#galleryContatiner1 .gallery__items',
        perPage: 9,
        animation:'fadeIn',
        previous: '',
        next: '',
        minHeight: false
    },

    getPagerEl:function() {
        return this.paginationSl ? this.cnt.find( this.paginationSl ) : null;
    },

    getImagesContEl:function() {
        return this.cnt.find( this.imagesContainerSl );
    },

    /**
     * Initilize gallery.
     * @use jquery.swipebox.js, swipebox.css, jPages.js
     *
     * @return void
     */
    _init: function( contSelector ) {
        if ( this.cnt.length < 1 ) {
            // throw 'configuration error';
            return;
        }

        this.cnt.find( '.swipebox' ).swipebox({
            useSVG: true,
            hideBarsDelay: 0,
            loopAtEnd: true
        });

        this._initPagination();
        this._initFilter();
    },

    /**
     * Initilize gallery pagination.
     *
     * @use jPages.js
     * @return void
     */
    _initPagination:function() {
        var paginationEl = this.getPagerEl();

        if ( ! paginationEl || paginationEl.length < 1 ) {
            return;
        }

        if ( this._jPager ) {
            this._jPager.jPages( 'destroy' );
        }

        this._jPager = paginationEl.jPages(
            jQuery.extend({
                    container : this.getImagesContEl()
                },
                this.paginationConfig
            )
        );
    },

    /**
     * Initilize gallery filter.
     * @param container selector, wrap gallery
     * @param filterButtons selector
     * @return void
     */
    _initFilter:function( container, filterButtons ) {
        var filterButtonsEl = this.filterButtonsSl ? this.cnt.find( this.filterButtonsSl ) : null;
        if ( ! filterButtonsEl && ! filterButtonsEl.length ) {
            return;
        }

        var self = this,
            items = this.getImagesContEl().children();

        /**
         * Items animation use jPages animation, when pagination off.
         */
        var _itemsAnimation = function() {
            if( self._jPager ) {
                return;
            }

            var customAnimationClass = self.paginationConfig.animation;
            if( ! customAnimationClass ) {
                return;
            }

            var animationClasses = self.animationClass + ' ' + customAnimationClass;
            items.addClass( animationClasses );
            setTimeout( function() {
                items.removeClass( animationClasses );
            }, 600 );
        };

        _itemsAnimation();

        filterButtonsEl.on( 'click', function( e ) {
            e.preventDefault();
            var idFilter = jQuery( this ).data( 'filterid' ),
                btnActiveClass = self.filterButtonActionClass;

            filterButtonsEl.parent()
                .removeClass( btnActiveClass );

            jQuery( this ).parent()
                .addClass( btnActiveClass );

            if( ! idFilter ) {
                idFilter = 'all';
            }

            var filtered = idFilter == 'all' ? items : items.filter( '[data-filterid*="' + idFilter + '"]' ),
                needShow = filtered,// filtered.filter(':not(:visible)'),
                needHide = items.not( filtered );//.filter(':visible');

            if ( ! needShow.length && ! needHide.length ) {
                return; // Nothing to do.
            }

            _itemsAnimation();

            needHide.hide();
            needShow.show();

            if ( self._jPager ) {
                self._initPagination();
            }
        });
    }
};

/**
 * Form validation helper.
 * @use bootstrap.min.js, bootstrap-custom.css
 */
Theme.FormValidationHelper = {
    options: {
        itemsValidationClass: 'form-validation-item',
        emailValidationRegex: /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/,
    },

    errors: {
        requiredField: 'Fill in the required field.',
        emailInvalid: 'Email invalid.',
    },

    init: function() {
        this.initTooltip(
            jQuery( '.' + this.options.itemsValidationClass )
        );
        this.initContactForm7CustomValidtion();
    },

    /**
     * Initialization tooltips.
     * @param selector|jQuery items
     * @return void
     */
    initTooltip: function( items ) {
        if ( 'string' == typeof items ) {
            items = jQuery( items );
        }else if ( 'undefined' == typeof items ) {
            items = jQuery( '.' + this.options.itemsValidationClass );
        }

        if ( items.length < 1 ) {
            return items;
        }

        items
            .tooltip({
                trigger: 'manual',
                animation: true,
                delay: 0
            })
            .on( 'focus', function() {
                jQuery( this ).tooltip( 'destroy' );
            });
        return items;
    },

    /**
     * Form items hide tooltip.
     * @param selector wrap
     * @return void
     */
    formReset: function( wrap ) {
        var wrap = jQuery( wrap );

        if( 0 == wrap.length ) {
            return null;
        }

        wrap.find( '.' + this.options.itemsValidationClass )
            .tooltip( 'destroy' )
            .attr( 'data-original-title', '' )
            .attr( 'title', '' );
    },

    /**
     * Item show tooltip with error.
     * @parm jQuery object item
     * @parm  string title
     * @return void
     */
    itemMakeInvalid: function( item, title ) {
        item
            .attr( 'data-original-title', title )
            .tooltip( 'show' );
    },

    /**
     * Validation items.
     * @param object items
     * @return integer errors count
     */
    itemsValidation: function( items ) {
        var self = this,
            errorsCount = 0;

        jQuery.each( items, function( i, item ) {
            var item = jQuery( item ),
                itemVal = item.val(),
                itemName = item.attr( 'name' ),
                itemType = item.attr( 'type' );

            if( ! itemVal.trim() ) {
                errorsCount++;
                self.itemMakeInvalid( item, ( 'undefined' != self.errors[ 'requiredField' ] ? self.errors[ 'requiredField' ] : '' ) );
            } else if( 'email' == itemType || 'email' == itemName || item.hasClass( 'yks-mc-input-email-address' ) ) { // Change to mailchimp for wp.
                if( ! self.options.emailValidationRegex.test( itemVal ) ) {
                    errorsCount++;
                    self.itemMakeInvalid( item, ( 'undefined' != self.errors[ 'emailInvalid' ] ? self.errors[ 'emailInvalid' ] : '' ) );
                }
            }
        });

        return errorsCount;
    },

    /**
     * Initialization custom validation for plugin contact form 7.
     * @return void
     */
    initContactForm7CustomValidtion: function() {
        var self = this,
            wrapForm = jQuery( '.wpcf7' ),
            itemsValidationClass = this.options.itemsValidationClass;

        wrapForm.each(function() {
            var wrapFromId = jQuery( this ).attr( 'id' ),
                wrapFormEl = jQuery( '#' + wrapFromId );

            if( wrapFormEl.length < 1 ) {
                return;
            }

            var items = wrapFormEl
                .find( '.wpcf7-validates-as-required' )
                .addClass( itemsValidationClass );

            self.initTooltip( items );

            wrapFormEl.find( 'form' ).on( 'ajaxComplete', function( e ) {
                self.initTooltip( items );
                jQuery( this ).find( '.wpcf7-not-valid' ).each(function( i, item ) {
                    var item = jQuery( item ),
                        itemErrorText = item.siblings( '.wpcf7-not-valid-tip' ).text();

                    switch( itemErrorText ){
                        case 'Please fill in the required field.':
                            itemErrorText = 'undefined' != self.errors[ 'requiredField' ] ? self.errors[ 'requiredField' ] : '';
                            break;
                        case 'Email address seems invalid.':
                            itemErrorText = 'undefined' != self.errors[ 'emailInvalid' ] ? self.errors[ 'emailInvalid' ] : '';
                            break;
                    }

                    self.itemMakeInvalid( item, itemErrorText );
                });
            });
        });
    },

    /**
     * Initialization custom validation for plugin Easy MailChimp Forms.
     *
     * @param selector wrapFormId
     * @return void
     */
    initMailChimpCustomValidtion: function( wrapFormId ) {
        var self = this,
            itemsValidationClass = this.options.itemsValidationClass,
            wrapForm = jQuery( '#' + wrapFormId );

        if ( wrapForm.length < 1 ) {
            return;
        }

        var items = wrapForm.find( '.yks-require, input[required="required"]' )
            .addClass( itemsValidationClass );

        this.initTooltip( items );

        wrapForm.find( 'form' )
            .find( '[type="submit"], [type="image"]' )
            .on( 'click', function( e ) {
                self.initTooltip( items );
                if ( self.itemsValidation( items ) > 0 ) {
                    e.preventDefault();
                }
            });
    },

    /**
     * Initialization custom validation for forms.
     *
     * @param  selector wrapFormId
     * @return void
     */
    initValidationForm: function( wrapFormId ) {
        var self = this,
            itemsValidationClass = this.options.itemsValidationClass,
            wrapForm = jQuery( '#' + wrapFormId );

        if ( 0 == wrapForm.length ) {
            return;
        }

        this.initTooltip(
            wrapForm.find( '.' + this.options.itemsValidationClass )
        );

        wrapForm.find( 'form' ).on( 'submit', function( e ) {

            // e.preventDefault();
            self.formReset( wrapForm );

            var items = wrapForm.find( '.' + itemsValidationClass ),
                formErrors = 0;

            formErrors = self.itemsValidation( items ) ;

            // validation success
            if( 0 == formErrors ) {
                //TODO complete
            }
        });
    }
};

Theme.formatter = {
    configs:{},

    setConfig:function( format, cfg ) {
        this.configs[format] = cfg;
    },

    formatMoney:function( amount ) {
        var cfg = jQuery.extend({
            //mask: '{amount}',
            decimal_separator: '.',
            thousand_separator: ',',
            decimals: 2
        }, this.configs.money[ 'money' ] ? this.configs[ 'money' ] : {});

        var formatted = this.formatNumber( amount, cfg.decimals, 3, cfg.thousand_separator, cfg.decimal_separator );

        if ( cfg.mask ) {
            var completed = cfg.mask.replace( '{amount}', formatted );
            if ( completed != cfg.mask ) {
                return completed;
            }
        }

        return formatted;
    },

    formatNumber: function( number, decimals, th, thSep, decSep ) {
        var re = '\\d(?=(\\d{' + ( th || 3 ) + '})+' + ( decimals > 0 ? '\\D' : '$' ) + ')',
            number = parseFloat(number);
            num = number.toFixed( Math.max( 0, ~~decimals ) );

        return ( decSep ? num.replace( '.', decSep ) : num ).replace( new RegExp( re, 'g' ), '$&' + ( thSep || ',' ) );
    },

    /**
     * Allows format strings with %s and %d placeholders.
     *
     * @return String
     */
    sprintf:function() {
        var args = arguments,
            string = args[0],
            i = 1;

        return string.replace( /%((%)|s|d)/g, function( m ) {
            // m is the matched format, e.g. %s, %d
            var val = null;
            if ( m[2] ) {
                val = m[2];
            } else {
                val = args[i];
                switch ( m ) {
                    case '%d':
                        val = parseFloat( val );
                        if ( isNaN( val ) ) val = 0;
                        break;
                }
                i++;
            }
            return val;
        });
    },

    time:function( timeIn24Hours, format ) {
        if ( ! format || 'hh:ii' == format ) {
            return timeIn24Hours;
        }

        var parts = timeIn24Hours.split( ':' ),
            result = format.replace( 'ii', parts[1] ),
            h = parseInt( parts[0], 10 ),
            is12HoursFormat = format.search( 'A' ) >= 0,
            is12HoursFormatLowercase = format.search( 'a' ) >= 0,
            newHourValue = h;


        if ( is12HoursFormat || is12HoursFormatLowercase ) {
            var suffix = h >= 12 ? ' PM' : ' AM';
            result = result.replace( is12HoursFormatLowercase ? 'a' : 'A', is12HoursFormatLowercase ? suffix.toLowerCase() : suffix );
            if ( newHourValue >= 12 ) {
                newHourValue -= 12;
            }
            if ( 0 == newHourValue ) {
                newHourValue = 12;
            }
        }

        if ( format.search( 'hh' ) >= 0 ) {
            result = result.replace( 'hh', ( newHourValue < 10 ? '0' : '' ) + newHourValue );
        } else {
            result = result.replace( 'h', newHourValue );
        }

        return result;
    }
};

jQuery(function( $ ) {
    Theme.init( $ );
});
