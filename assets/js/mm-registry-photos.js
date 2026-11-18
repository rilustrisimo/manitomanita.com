/**
 * Registry gift pictures (RegistryPhotos, classes/class-registry-photos.php).
 * Owner editor: "Add your own photo" per gift. The photo is shrunk in the browser (max 1600px JPEG) before
 * upload; the server saves small WebP copies and returns a token, which Theme.initMyWishlist sends with the
 * item on Save. Guests: tap an owner photo to see it larger.
 */
(function ($) {
    'use strict';

    var ajax = function () { return $('#ajax-url').val() || '/wp-admin/admin-ajax.php'; };
    var MAX_SIDE = 1600;

    /** A smaller JPEG of the chosen photo (falls back to the original file if the browser can't decode it). */
    function shrink(file) {
        var d = $.Deferred();
        if (!window.FileReader || !document.createElement('canvas').getContext) return d.resolve(file).promise();
        var img = new Image(), url = (window.URL || window.webkitURL).createObjectURL(file);
        img.onload = function () {
            var w = img.naturalWidth, h = img.naturalHeight, s = Math.min(1, MAX_SIDE / Math.max(w, h));
            if (s === 1 && file.size < 700000) { URL.revokeObjectURL(url); return d.resolve(file); }
            var c = document.createElement('canvas');
            c.width = Math.round(w * s); c.height = Math.round(h * s);
            var ctx = c.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); // transparent PNGs get a white background
            ctx.drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            if (!c.toBlob) return d.resolve(file);
            c.toBlob(function (b) { d.resolve(b && b.size < file.size ? b : file); }, 'image/jpeg', 0.85);
        };
        img.onerror = function () { URL.revokeObjectURL(url); d.resolve(file); };
        img.src = url;
        return d.promise();
    }

    function initEditor() {
        $(document).on('click', '[data-mm-photo-add]', function () {
            $(this).closest('[data-mm-photo]').find('[data-mm-photo-file]').val('').trigger('click');
        });

        $(document).on('change', '[data-mm-photo-file]', function () {
            var file = this.files && this.files[0], box = $(this).closest('[data-mm-photo]'), form = box.closest('form');
            if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) { hint(box, 'Please use a JPG, PNG or WebP photo.', true); return; }
            box.addClass('is-busy');
            hint(box, 'Uploading…');
            shrink(file).then(function (blob) {
                var fd = new FormData();
                fd.append('action', 'mm_wl_photo');
                fd.append('id', form.find('[name="id"]').val());
                fd.append('nonce', form.find('[name="nonce"]').val());
                fd.append('photo', blob, 'photo.jpg');
                return $.ajax({ url: ajax(), type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' });
            }).done(function (r) {
                if (!r || !r.success) { hint(box, $('<div>').html((r && r.data) || 'The photo didn’t upload. Please try again.').text(), true); return; }
                box.find('[data-mm-img]').val(r.data.img);
                box.find('[data-mm-photo-shop], [data-mm-photo-img]').remove();
                $('<img alt="" data-mm-photo-img>').attr('src', r.data.small).appendTo(box.find('[data-mm-photo-pic]'));
                box.addClass('has-photo').find('[data-mm-photo-rm]').prop('hidden', false);
                box.find('[data-mm-photo-label]').text('Change photo');
                hint(box, 'Added. Tap Save list to keep it.');
            }).fail(function () {
                hint(box, 'The photo didn’t upload. Check your connection and try again.', true);
            }).always(function () { box.removeClass('is-busy'); });
        });

        $(document).on('click', '[data-mm-photo-rm]', function () {
            var box = $(this).closest('[data-mm-photo]');
            box.find('[data-mm-img]').val('');
            box.find('[data-mm-photo-img]').remove();
            box.removeClass('has-photo');
            $(this).prop('hidden', true);
            box.find('[data-mm-photo-label]').text('Add your own photo');
            hint(box, 'Removed. Tap Save list to keep this change.');
        });
    }

    function hint(box, text, error) {
        box.find('[data-mm-photo-hint]').text(text).toggleClass('is-error', !!error);
    }

    /* ---------- Guests: tap a photo to see it larger ---------- */
    function initZoom() {
        $(document).on('click', '[data-mm-wl-zoom]', function (e) {
            e.preventDefault();
            var alt = $(this).find('img').attr('alt') || '';
            var box = $('<div class="mm-wl-zoom" role="dialog" aria-modal="true" aria-label="Photo"><button type="button" class="mm-wl-zoom__x" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>');
            $('<img>').attr({ src: $(this).attr('href'), alt: alt }).appendTo(box);
            if (alt) $('<p class="mm-wl-zoom__cap">').text(alt).appendTo(box);
            box.appendTo('body');
            $('body').addClass('mm-wl-zoom-open');
            var close = function () { box.remove(); $('body').removeClass('mm-wl-zoom-open'); $(document).off('keydown.mmwlzoom'); };
            box.on('click', close);
            $(document).on('keydown.mmwlzoom', function (k) { if (k.key === 'Escape') close(); });
            box.find('.mm-wl-zoom__x').trigger('focus');
        });
    }

    /* ---------- Shop pictures not looked up yet: fetch as they scroll near, a few at a time ---------- */
    function initShopPics() {
        var imgs = $('img[data-mm-wl-pic]');
        if (!imgs.length) return;
        var queue = [], running = 0, MAX = 3;

        function next() {
            while (running < MAX && queue.length) load(queue.shift());
        }
        function load(img) {
            running++;
            img._tries = (img._tries || 0) + 1;
            $.getJSON(img.getAttribute('data-mm-wl-pic')).done(function (r) {
                if (r && r.wait && img._tries < 8) {
                    // Server is at its lookup limit: try again when it frees up (the tile shows meanwhile).
                    setTimeout(function () { queue.push(img); next(); }, r.wait * 1000);
                } else if (r && r.u) {
                    img.removeAttribute('data-mm-wl-pic');
                    img.src = r.u;
                } else {
                    $(img).remove();
                }
            }).fail(function () {
                if (img._tries < 3) setTimeout(function () { queue.push(img); next(); }, 5000);
            }).always(function () { running--; next(); });
        }
        function want(img) { queue.push(img); next(); }

        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    io.unobserve(e.target);
                    want(e.target);
                });
            }, { rootMargin: '400px 0px' });
            imgs.each(function () { io.observe(this); });
        } else {
            imgs.each(function () { want(this); });
        }
    }

    $(function () {
        if (!$('.mm-wl').length) return;
        initEditor();
        initZoom();
        initShopPics();
    });
})(jQuery);
