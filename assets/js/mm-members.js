/**
 * Members list on the group page (MemberList): loads a member's wishlist and comments when their card is
 * opened (prefetched on hover / touch), "Show all N members" for big groups, and search across all rows.
 * Theme.js opens the popup and triggers 'mm:member-open' with the card and the popup body.
 */
jQuery(function ($) {
    var ajaxUrl = $('#ajax-url').val(), pending = {};

    function load($card) {
        var uid = String($card.attr('data-mm-member') || ''), $d = $card.find('.mm-member-details');
        if (!uid || !$d.length) return $.Deferred().reject('').promise();
        if ($d.attr('data-mm-details') !== '0') return $.Deferred().resolve().promise();
        if (pending[uid]) return pending[uid];

        pending[uid] = $.post(ajaxUrl, { action: 'mm_member_detail', uid: uid, gid: $.trim($('#groupid').text()), key: $('#mm-cards-key').val() })
            .then(function (r) {
                if (!r || !r.success) return $.Deferred().reject(r && r.data).promise();
                $d.html(r.data.html).attr('data-mm-details', '1');
                $card.find('.mm-member__meta').text(r.data.meta);
            })
            .always(function () { delete pending[uid]; });

        return pending[uid];
    }

    // Start loading as soon as the finger or mouse is on a card, so the popup usually opens already filled.
    $(document).on('pointerenter touchstart', '#cards-container .mm-member', function () { load($(this)); });

    $(document).on('mm:member-open', function (e, $card, $inner) {
        if ($card.find('.mm-member-details').attr('data-mm-details') === '1') return;
        var uid = $card.attr('data-mm-member');
        $inner.attr('data-mm-for', uid).append('<div class="mm-detail mm-detail--loading" aria-busy="true"><span></span><span></span><span></span></div>');

        load($card).then(function () {
            if ($inner.attr('data-mm-for') !== uid) return; // another card was opened meanwhile
            $inner.find('.mm-detail--loading').replaceWith($card.find('.mm-member-details').children().clone());
            $inner.find('.mm-pop-head__meta').text($card.find('.mm-member__meta').text());
        }, function (why) {
            if ($inner.attr('data-mm-for') !== uid) return;
            $inner.find('.mm-detail--loading').replaceWith('<p class="mm-detail__error">' + (why === 'reload'
                ? 'Please <a href="javascript:location.reload()">reload the page</a> to see this wishlist.'
                : 'Could not load this wishlist. <a href="#" data-mm-retry>Try again</a>') + '</p>');
        });
    });

    $(document).on('click', '[data-mm-member-popup] [data-mm-retry]', function (e) {
        e.preventDefault();
        var $inner = $(this).closest('[data-mm-member-popup]'), uid = $inner.attr('data-mm-for');
        $(this).closest('.mm-detail__error').remove();
        $(document).trigger('mm:member-open', [$('#cards-container .mm-member[data-mm-member="' + uid + '"]'), $inner]);
    });

    // Big groups: the first 40 rows, then everyone on tap. A search always looks through all rows.
    $(document).on('click', '#cards-container [data-mm-more]', function () {
        $('#cards-container').addClass('is-all');
        $(this).remove();
    });
    $(document).on('input search', '[data-mm-search-input]', function () {
        $('#cards-container').toggleClass('is-searching', $.trim($(this).val()) !== '');
    });
});
