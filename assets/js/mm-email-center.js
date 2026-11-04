/**
 * Manito Emails (WP Admin): campaign editor steps, live audience count, tokens, autosave, preview,
 * Gemini writing, and live progress on the campaigns list. Server: EmailCenterAdmin (AJAX mm_ec_*).
 */
(function ($) {
    'use strict';

    var cfg = window.mmEC || {};
    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
    var plain = function (h) { return $('<div>').html(h || '').text(); };

    function post(action, data) {
        return $.post(cfg.ajax, data + '&action=mm_ec_' + action + '&nonce=' + encodeURIComponent(cfg.nonce));
    }

    /* ---------- Campaigns list: live progress ---------- */
    function initList() {
        var rows = $('[data-mmec-live]');
        if (!rows.length) return;
        var tick = function () {
            if (document.hidden) return;
            var ids = rows.map(function () { return 'ids[]=' + $(this).attr('data-mmec-row'); }).get().join('&');
            post('status', ids).done(function (r) {
                if (!r || !r.success) return;
                $.each(r.data, function (id, p) {
                    var row = $('[data-mmec-row="' + id + '"]');
                    row.find('[data-mmec-chip]').html(p.chip);
                    row.find('[data-mmec-bar]').css('width', p.pct + '%');
                    row.find('[data-mmec-line]').text(p.line);
                    row.find('[data-mmec-stats]').text(p.stats);
                });
            });
        };
        setInterval(tick, 15000);
    }

    /* ---------- Editor ---------- */
    function initEditor() {
        var form = $('[data-mmec-edit]');
        if (!form.length) return;
        var id = form.attr('data-id'), locked = form.is('[data-locked]'), dirty = false, step = form.attr('data-step') || '1';

        var syncEditor = function () { if (window.tinymce && tinymce.get('mmec_body')) tinymce.get('mmec_body').save(); };
        var data = function () { syncEditor(); return form.serialize(); };
        var setBody = function (html) {
            if (window.tinymce && tinymce.get('mmec_body') && !tinymce.get('mmec_body').isHidden()) tinymce.get('mmec_body').setContent(html);
            $('#mmec_body').val(html);
            dirty = true;
        };

        if (locked) form.find('.mmec-step input, .mmec-step select, .mmec-step textarea').prop('disabled', true);

        // Steps
        var show = function (s) {
            step = String(s);
            form.find('[data-mmec-stepfield]').val(step);
            form.find('[data-mmec-steps] li').removeClass('is-on').filter('[data-s="' + step + '"]').addClass('is-on');
            form.find('.mmec-step').removeClass('is-on').filter('[data-step="' + step + '"]').addClass('is-on');
            form.find('[data-mmec-prev]').prop('hidden', step === '1');
            form.find('[data-mmec-next]').prop('hidden', step === '4');
            if (step === '3') preview();
        };
        form.on('click', '[data-mmec-steps] li', function () { show($(this).attr('data-s')); });
        form.on('click', '[data-mmec-next]', function () { show(Math.min(4, +step + 1)); });
        form.on('click', '[data-mmec-prev]', function () { show(Math.max(1, +step - 1)); });

        // Fields that only apply to some choices
        var conds = function () {
            var ex = form.find('[name="audience[exchange]"]').val();
            var src = form.find('[name="audience[source][]"]:checked').map(function () { return this.value; }).get();
            form.find('[data-mmec-if]').each(function () {
                var c = $(this).attr('data-mmec-if'), on;
                if (c.indexOf('exchange=') === 0) on = ex === c.split('=')[1];
                else on = src.indexOf(c) >= 0;
                $(this).prop('hidden', !on);
            });
        };

        // Live audience count
        var countTimer = null;
        var count = function () {
            var box = form.find('[data-mmec-count]').html('<span class="spinner is-active" style="float:none"></span> Counting&hellip;');
            post('audience', data()).done(function (r) {
                if (!r || !r.success) { box.text(plain(r && r.data) || 'Could not count.'); return; }
                var d = r.data, h = '<strong>' + Number(d.count).toLocaleString() + '</strong> people will get this email<br><span>' + esc(plain(d.summary)) + '</span>';
                var sk = Object.keys(d.skipped || {});
                if (sk.length) h += '<ul>' + sk.map(function (k) { return '<li>' + Number(d.skipped[k]).toLocaleString() + ' left out: ' + esc(k) + '</li>'; }).join('') + '</ul>';
                if (d.samples && d.samples.length) h += '<p class="mmi-muted">For example: ' + d.samples.map(function (s) { return esc(s.email) + ' (' + esc(s.type) + (s.group ? ', ' + esc(s.group) : '') + ')'; }).join(' · ') + '</p>';
                box.html(h);
            }).fail(function () { box.text('Network error.'); });
        };
        form.on('change input', '[data-mmec-aud]', function () { conds(); dirty = true; clearTimeout(countTimer); countTimer = setTimeout(count, 600); });

        // Content helpers
        var lastField = null;
        form.on('focus', '[data-mmec-c]', function () { lastField = this; });
        form.on('input change', '[data-mmec-c], #mmec_body', function () { dirty = true; lens(); });
        var lens = function () {
            var v = plain(form.find('[name="content[subject]"]').val() || '');
            form.find('[data-mmec-len="subject"]').text('(' + v.length + ' characters' + (v.length > 60 ? ', a bit long' : '') + ')');
        };
        form.on('click', '[data-mmec-token]', function () {
            var t = $(this).attr('data-mmec-token');
            var ed = window.tinymce && tinymce.get('mmec_body');
            if (lastField && lastField.type === 'text') {
                var el = lastField, s = el.selectionStart || el.value.length;
                el.value = el.value.slice(0, s) + t + el.value.slice(el.selectionEnd || s);
                $(el).trigger('input');
            } else if (ed && !ed.isHidden()) { ed.execCommand('mceInsertContent', false, t); dirty = true; }
            else { var ta = $('#mmec_body'); ta.val(ta.val() + t); dirty = true; }
        });
        $(document).on('tinymce-editor-init', function (e, ed) { if (ed.id === 'mmec_body') ed.on('change keyup', function () { dirty = true; }); });

        // Autosave
        if (!locked) {
            setInterval(function () {
                if (!dirty) return;
                dirty = false;
                post('autosave', data()).done(function (r) {
                    if (r && r.success) { form.find('[data-mmec-saved]').text('Saved ' + r.data.saved); tested(r.data.tested); }
                });
            }, 20000);
        }

        // Preview
        var tested = function (ok) {
            form.find('[data-mmec-tested]').toggleClass('is-ok', !!ok).toggleClass('is-no', !ok).text(ok ? '✓ Tested, ready to schedule' : 'Send a test of this version before scheduling');
            form.find('[data-mmec-schedule]').prop('disabled', !ok || locked);
        };
        var filledAs = false;
        var preview = function () {
            var frame = form.find('[data-mmec-frame]');
            post('preview', data() + '&as=' + encodeURIComponent(form.find('[data-mmec-as]').val() || '')).done(function (r) {
                if (!r || !r.success) return;
                frame.attr('srcdoc', r.data.html);
                form.find('[data-mmec-psubj]').text(r.data.subject || '(no subject)');
                form.find('[data-mmec-ppre]').text(r.data.preheader || '');
                var w = r.data.warnings || [];
                form.find('[data-mmec-warn]').html(w.length ? '<ul>' + w.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' : '');
                tested(r.data.tested);
                if (!filledAs && r.data.samples) {
                    filledAs = true;
                    var sel = form.find('[data-mmec-as]');
                    r.data.samples.forEach(function (s) { sel.append($('<option>').val(s.ref).text(s.label)); });
                }
            });
        };
        form.on('change', '[data-mmec-as]', preview);
        form.on('click', '[data-mmec-w]', function () {
            form.find('[data-mmec-w]').removeClass('is-on');
            $(this).addClass('is-on');
            form.find('[data-mmec-frame]').css('width', $(this).attr('data-mmec-w') + 'px');
        });

        // Gemini
        form.on('click', '[data-mmec-ai-go]', function () {
            var b = $(this), mode = b.attr('data-mmec-ai-go'), out = form.find('[data-mmec-ai-out]');
            var brief = form.find('[data-mmec-brief]').val() || '';
            if (mode === 'write' && $.trim(brief).length < 8) { out.html('<p class="mmec-ai__err">Describe what the email is for first.</p>'); return; }
            form.find('[data-mmec-ai-go]').prop('disabled', true);
            out.html('<p><span class="spinner is-active" style="float:none"></span> Writing&hellip;</p>');
            post('ai', data() + '&mode=' + mode + '&brief=' + encodeURIComponent(brief) + '&tone=' + encodeURIComponent(form.find('[data-mmec-tone]').val()) + '&lang=' + encodeURIComponent(form.find('[data-mmec-lang]').val()))
                .done(function (r) {
                    if (!r || !r.success) { out.html('<p class="mmec-ai__err">' + esc(plain(r && r.data) || 'Try again.') + '</p>'); return; }
                    var d = r.data;
                    var html = '<p><strong>Pick a subject:</strong></p>' + (d.subjects || []).map(function (s) { return '<button type="button" class="mmec-subj" data-s="' + esc(s) + '">' + esc(s) + '</button>'; }).join('');
                    html += '<p><strong>Message</strong></p><div class="mmec-ai__text">' + (d.paragraphs || []).map(function (p) { return '<p>' + esc(p) + '</p>'; }).join('') + '</div>';
                    html += '<button type="button" class="button button-primary" data-mmec-ai-use>Use this text</button>';
                    out.html(html).data('ai', d);
                })
                .fail(function () { out.html('<p class="mmec-ai__err">Network error.</p>'); })
                .always(function () { form.find('[data-mmec-ai-go]').prop('disabled', false); });
        });
        form.on('click', '.mmec-subj', function () {
            form.find('[name="content[subject]"]').val($(this).attr('data-s')).trigger('input');
            $(this).siblings('.mmec-subj').css('border-color', '');
            $(this).css('border-color', '#2a9d99');
        });
        form.on('click', '[data-mmec-ai-use]', function () {
            var d = form.find('[data-mmec-ai-out]').data('ai') || {};
            if (d.preheader) form.find('[name="content[preheader]"]').val(d.preheader);
            if (d.eyebrow) form.find('[name="content[eyebrow]"]').val(d.eyebrow);
            if (d.title) form.find('[name="content[title]"]').val(d.title);
            if (d.button) form.find('[name="content[button_label]"]').val(d.button);
            if (!form.find('[name="content[subject]"]').val() && d.subjects && d.subjects[0]) form.find('[name="content[subject]"]').val(d.subjects[0]);
            setBody((d.paragraphs || []).map(function (p) { return '<p>' + esc(p) + '</p>'; }).join(''));
            lens();
        });

        conds();
        lens();
        if (!locked) count();
        show(step);
    }

    $(function () { initList(); initEditor(); });
})(jQuery);
