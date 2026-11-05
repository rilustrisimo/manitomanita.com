/**
 * Manito Insights (WP Admin): tabs, period picker, KPI cards with comparisons, Chart.js charts,
 * tables and the live panel. Loaded only on the Insights page and the WP Dashboard (widget).
 * The live endpoint refreshes every 60s, only while the tab is visible.
 */
(function ($) {
    'use strict';

    var cfg = window.mmInsights || {}, charts = [], state = { view: 'overview', period: '30d', from: '', to: '' }, liveTimer = null;

    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
    var num = function (n, d) { return Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: d || 0, minimumFractionDigits: d || 0 }); };
    var peso = function (n) { return '\u20b1' + num(n, n % 1 ? 2 : 0); };
    var dur = function (s) { s = Math.round(s || 0); return s >= 60 ? Math.floor(s / 60) + 'm ' + (s % 60) + 's' : s + 's'; };

    function post(view, extra) {
        return $.post(cfg.ajax, $.extend({ action: 'mm_insights', nonce: cfg.nonce, view: view, period: state.period, from: state.from, to: state.to }, extra || {}));
    }

    function change(c, invert) {
        if (c === null || c === undefined) return '<span class="mmi-chg is-new">new</span>';
        if (c === 0) return '<span class="mmi-chg">0%</span>';
        var good = invert ? c < 0 : c > 0;
        return '<span class="mmi-chg ' + (good ? 'is-up' : 'is-down') + '">' + (c > 0 ? '\u25b2 ' : '\u25bc ') + Math.abs(c) + '%</span>';
    }

    function card(label, value, chg, sub) {
        return '<div class="mmi-card"><span class="mmi-card__label">' + label + '</span><strong class="mmi-card__value">' + value + '</strong>' +
            '<span class="mmi-card__foot">' + (chg !== false ? change(chg) + ' <em>vs previous period</em>' : (sub || '')) + '</span></div>';
    }

    function kpiCards(k, keys) {
        return '<div class="mmi-cards">' + keys.map(function (m) {
            var x = k[m]; if (!x) return '';
            var v = m === 'revenue' ? peso(x.value) : (x.suffix ? x.value + x.suffix : num(x.value));
            return card(x.label, v, x.suffix ? false : x.change, x.suffix ? 'of new members' : '');
        }).join('') + '</div>';
    }

    function table(title, cols, rows, empty) {
        var h = '<div class="mmi-panel"><h3>' + title + '</h3>';
        if (!rows || !rows.length) return h + '<p class="mmi-empty">' + (empty || 'No data yet for this period.') + '</p></div>';
        h += '<table class="mmi-table"><thead><tr>' + cols.map(function (c) { return '<th' + (c.num ? ' class="num"' : '') + '>' + c.label + '</th>'; }).join('') + '</tr></thead><tbody>';
        rows.forEach(function (r) { h += '<tr>' + cols.map(function (c) { return '<td' + (c.num ? ' class="num"' : '') + '>' + c.get(r) + '</td>'; }).join('') + '</tr>'; });
        return h + '</tbody></table></div>';
    }

    function bars(title, items, fmt) {
        var h = '<div class="mmi-panel"><h3>' + title + '</h3>';
        if (!items || !items.length) return h + '<p class="mmi-empty">No data yet.</p></div>';
        var max = Math.max.apply(null, items.map(function (i) { return i.value; })) || 1;
        return h + items.map(function (i) {
            return '<div class="mmi-bar"><span class="mmi-bar__name">' + esc(i.name) + '</span><span class="mmi-bar__track"><i style="width:' + Math.max(2, i.value / max * 100) + '%"></i></span><b>' + (fmt ? fmt(i.value) : num(i.value)) + '</b></div>';
        }).join('') + '</div>';
    }

    function objBars(obj) { return Object.keys(obj || {}).map(function (k) { return { name: k, value: obj[k] }; }); }

    var palette = ['#2a9d99', '#d4af37', '#1b1b1b', '#e5509f', '#7aa2ff', '#34c38f'];
    function line(id, labels, sets, opts) {
        if (!window.Chart) return;
        var el = document.getElementById(id); if (!el) return;
        charts.push(new Chart(el, {
            type: (opts && opts.type) || 'line',
            data: { labels: labels.map(function (l) { return l.length === 10 ? l.slice(5) : l; }), datasets: sets.map(function (s, i) {
                return { label: s.label, data: s.data, borderColor: palette[i % 6], backgroundColor: (i === 0 && !(opts && opts.type === 'bar')) ? 'rgba(42,157,153,.12)' : palette[i % 6], fill: i === 0 && !(opts && opts.type === 'bar'), tension: .3, pointRadius: labels.length > 45 ? 0 : 2, borderWidth: 2, yAxisID: s.axis || 'y' };
            }) },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } },
                scales: $.extend({ y: { beginAtZero: true, ticks: { precision: 0 } } }, (opts && opts.y2) ? { y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } : {}) }
        }));
    }

    function chartBox(id, title) { return '<div class="mmi-panel mmi-panel--chart"><h3>' + title + '</h3><div class="mmi-chart"><canvas id="' + id + '"></canvas></div></div>'; }
    function note(t) { return '<p class="mmi-note">' + t + '</p>'; }
    function gError(g) { return g && g.error ? '<p class="mmi-warn">Google data unavailable: ' + esc(g.error) + '</p>' : ''; }

    var setupHtml = '<div class="mmi-panel mmi-setup"><h3>Connect Google Analytics and Search Console</h3><ol>' +
        '<li>In <b>Google Cloud Console</b> create a project (e.g. "Manito Insights"), then enable <b>Google Analytics Data API</b> and <b>Google Search Console API</b>.</li>' +
        '<li>Go to <b>IAM &amp; Admin \u2192 Service accounts \u2192 Create</b>, then <b>Keys \u2192 Add key \u2192 JSON</b>. A .json file downloads.</li>' +
        '<li>In <b>GA4 \u2192 Admin \u2192 Property access management</b>, add the service account email as <b>Viewer</b>. Copy the numeric <b>Property ID</b> (Admin \u2192 Property details).</li>' +
        '<li>In <b>Search Console \u2192 Settings \u2192 Users and permissions</b>, add the same email (Restricted).</li>' +
        '<li>Upload the .json file in Hostinger File Manager <b>outside public_html</b>, then add to wp-config.php:<pre>define( \'MM_GOOGLE_SA_JSON\', \'/home/YOUR_USER/manito-insights.json\' );\ndefine( \'GA4_PROPERTY_ID\', \'123456789\' );\ndefine( \'MM_GSC_SITE\', \'sc-domain:manitomanita.com\' );</pre></li></ol></div>';

    /* ---------------- Views ---------------- */

    var views = {
        overview: function (d) {
            var h = kpiCards(d.kpis, ['groups', 'members_joined', 'confirm_rate', 'purchase', 'revenue', 'affiliate_click']);
            if (d.google && d.google.kpis && !d.google.kpis.error) {
                var g = d.google.kpis;
                h += '<div class="mmi-cards">' + card('Visits (GA4)', num(g.sessions.value), g.sessions.change) + card('People (GA4)', num(g.users.value), g.users.change) + card('Engagement rate', num(g.engagement.value * 100, 1) + '%', g.engagement.change) + card('Avg. visit', dur(g.avg_duration.value), g.avg_duration.change) + '</div>';
            }
            if (d.scorecard) {
                var money = { pro_revenue: 1, sponsor: 1, rev_per_1k: 1 };
                h += '<div class="mmi-panel"><h3>Business scorecard <small>five engines, vs previous period</small></h3><div class="mmi-cards">' + Object.keys(d.scorecard).map(function (k) {
                    var x = d.scorecard[k];
                    return card(esc(x.engine) + ' &middot; ' + esc(x.label), money[k] ? peso(x.value) : num(x.value), x.change);
                }).join('') + '</div></div>';
            }
            if (d.hints && d.hints.length) h += '<div class="mmi-hints">' + d.hints.map(function (x) { return '<p>\ud83d\udca1 ' + x + '</p>'; }).join('') + '</div>';
            h += '<div class="mmi-grid">' + chartBox('mmi-c1', 'Activity per day') + '<div class="mmi-panel"><h3>Live activity <small data-mmi-at></small></h3><div data-mmi-feed><p class="mmi-empty">Loading\u2026</p></div></div></div>';
            if (d.google && d.google.sources && !d.google.sources.error) h += '<div class="mmi-grid">' + bars('Top sources (visits)', d.google.sources) + '<div class="mmi-panel" data-mmi-rt><h3>On the site right now</h3><p class="mmi-empty">Loading\u2026</p></div></div>';
            return { html: h, after: function () {
                line('mmi-c1', d.chart.labels, [{ label: 'Members joined', data: d.chart.data.members_joined }, { label: 'Groups created', data: d.chart.data.groups }, { label: 'Affiliate clicks', data: d.chart.data.affiliate_click, axis: 'y2' }, { label: 'PRO sales', data: d.chart.data.purchase }], { y2: true });
            } };
        },
        growth: function (d) {
            var g = d.groups;
            var h = kpiCards(d.kpis, ['groups', 'members_joined', 'members_confirmed', 'confirm_rate', 'shuffle', 'repeat_group']);
            h += '<div class="mmi-cards">' + card('Average group size', num(g.avg_size, 1) + ' members', false, 'groups created in this period') + '</div>';
            h += chartBox('mmi-c2', 'Groups and members per day');
            var link = function (r) { return '<a href="' + esc(r.link) + '" target="_blank" rel="noopener">' + esc(r.name || '(no name)') + ' \u2197</a>' + (r.pro ? ' <span class="mmi-pro">PRO</span>' : ''); };
            h += '<div class="mmi-grid">' + table('Biggest groups (created in this period)', [{ label: 'Group', get: link }, { label: 'Members', num: 1, get: function (r) { return num(r.members); } }], g.biggest) +
                table('Exchanges in the next 14 days', [{ label: 'Group', get: link }, { label: 'Date', get: function (r) { return esc(r.date); } }, { label: 'Shuffled', get: function (r) { return r.shuffled ? '\u2713' : '<span class="mmi-muted">not yet</span>'; } }], g.upcoming, 'No exchanges in the next 14 days.') + '</div>';
            h += '<div class="mmi-grid">' + table('Newest groups', [{ label: 'Group', get: link }, { label: 'Created', get: function (r) { return esc(r.created); } }, { label: 'Members', num: 1, get: function (r) { return num(r.members); } }], g.newest) +
                table('Most affiliate clicks', [{ label: 'Group', get: link }, { label: 'Clicks', num: 1, get: function (r) { return num(r.clicks); } }], g.clicks) + '</div>';
            return { html: h, after: function () {
                line('mmi-c2', d.chart.labels, [{ label: 'Members joined', data: d.chart.data.members_joined }, { label: 'Members confirmed', data: d.chart.data.members_confirmed }, { label: 'Groups created', data: d.chart.data.groups }, { label: 'Shuffles', data: d.chart.data.shuffle }]);
            } };
        },
        revenue: function (d) {
            var f = d.features;
            var h = kpiCards(d.kpis, ['revenue', 'purchase']);
            h += '<div class="mmi-cards">' + card('PRO groups (all time)', num(f.pro_groups), false, 'of ' + num(f.groups) + ' groups (' + (f.groups ? num(f.pro_groups / f.groups * 100, 1) : 0) + '%)') + '</div>';
            h += note('Sales are counted from Nov 2026 onward, when exact payment logging started.');
            h += chartBox('mmi-c3', 'Revenue (\u20b1) and sales per day');
            if (d.site_funnel && d.site_funnel.steps) {
                var sf = d.site_funnel.steps, top0 = sf[0].count || 0;
                h += '<div class="mmi-grid"><div class="mmi-panel"><h3>PRO funnel (site log, not blocked by ad blockers)</h3>' + sf.map(function (s, i) {
                    var pct = top0 ? Math.round(s.count / top0 * 100) : 0, prev = i ? sf[i - 1].count : 0;
                    return '<div class="mmi-funnel"><span>' + esc(s.step) + '</span><span class="mmi-bar__track"><i style="width:' + Math.max(2, pct) + '%"></i></span><b>' + num(s.count) + '</b><em>' + (i && prev ? Math.round(s.count / prev * 100) + '% of previous' : num(s.groups) + ' groups') + '</em></div>';
                }).join('') + (d.site_funnel.since ? '' : '<p class="mmi-empty">Steps are logged from today on.</p>') + '</div>' + bars('What opened the PRO sheet (site log)', objBars(d.site_funnel.reasons)) + '</div>';
            }
            if (d.funnel && d.funnel.steps) {
                var top = d.funnel.steps[0].users || 0;
                h += '<div class="mmi-grid"><div class="mmi-panel"><h3>PRO funnel (people, GA4)</h3>' + d.funnel.steps.map(function (s, i) {
                    var pct = top ? Math.round(s.users / top * 100) : 0, prev = i ? d.funnel.steps[i - 1].users : 0;
                    return '<div class="mmi-funnel"><span>' + esc(s.step) + '</span><span class="mmi-bar__track"><i style="width:' + Math.max(2, pct) + '%"></i></span><b>' + num(s.users) + '</b><em>' + (i && prev ? Math.round(s.users / prev * 100) + '% of previous' : '') + '</em></div>';
                }).join('') + '</div>' + bars('What opened the PRO sheet', d.funnel.slots) + '</div>';
            } else if (d.funnel && d.funnel.error) { h += gError(d.funnel); }
            else { h += note('Connect Google (Traffic & SEO tab) to see the PRO funnel: sheet \u2192 checkout \u2192 paid.'); }
            h += bars('PRO feature adoption (groups using it)', [{ name: 'Exclusions', value: f.exclusions }, { name: 'Weekly Drops', value: f.weekly_drops }, { name: 'Anonymous Q&A (members asked)', value: f.qa }, { name: 'Co-organizer', value: f.co_organizer }, { name: 'Group theme', value: f.group_theme }, { name: 'Reveal shown', value: f.revealed }]);
            return { html: h, after: function () { line('mmi-c3', d.chart.labels, [{ label: 'Revenue \u20b1', data: d.chart.data.revenue }, { label: 'Sales', data: d.chart.data.purchase, axis: 'y2' }], { y2: true, type: 'bar' }); } };
        },
        traffic: function (d) {
            if (!d.configured) return { html: setupHtml };
            var t = d.google || {}, s = d.seo || {}, h = gError(t);
            if (t.kpis && !t.kpis.error) {
                var k = t.kpis;
                h += '<div class="mmi-cards">' + card('Visits', num(k.sessions.value), k.sessions.change) + card('People', num(k.users.value), k.users.change) + card('New people', num(k.new_users.value), k.new_users.change) + card('Page views', num(k.views.value), k.views.change) + card('Engagement rate', num(k.engagement.value * 100, 1) + '%', k.engagement.change) + card('Avg. visit', dur(k.avg_duration.value), k.avg_duration.change) + '</div>';
                h += chartBox('mmi-c4', 'Visits and people per day');
                h += '<div class="mmi-grid">' + bars('Channels', t.channels) + bars('Sources / medium', t.sources) + '</div>';
                h += '<div class="mmi-grid">' + bars('Devices', t.devices) + bars('Countries', t.countries) + '</div>';
                h += table('Top pages', [{ label: 'Page', get: function (r) { return esc(r.path); } }, { label: 'Views', num: 1, get: function (r) { return num(r.views); } }, { label: 'People', num: 1, get: function (r) { return num(r.users); } }, { label: 'Time / person', num: 1, get: function (r) { return dur(r.time); } }], t.pages);
            }
            h += '<h2 class="mmi-h2">Google Search</h2>' + gError(s);
            if (s.kpis) {
                h += note('Search Console data for ' + esc(s.range[0]) + ' to ' + esc(s.range[1]) + ' (Google publishes it 2\u20133 days late).');
                var q = s.kpis;
                h += '<div class="mmi-cards">' + card('Clicks from Google', num(q.clicks.value), q.clicks.change) + card('Impressions', num(q.impressions.value), q.impressions.change) + card('Click-through rate', q.ctr.value + '%', q.ctr.change) + card('Avg. position', q.position.value, q.position.change === null ? null : -q.position.change) + '</div>';
                h += chartBox('mmi-c5', 'Clicks and impressions per day');
                var qcols = [{ label: 'Search', get: function (r) { return esc(r.key); } }, { label: 'Clicks', num: 1, get: function (r) { return num(r.clicks); } }, { label: 'Impr.', num: 1, get: function (r) { return num(r.impressions); } }, { label: 'CTR', num: 1, get: function (r) { return r.ctr + '%'; } }, { label: 'Position', num: 1, get: function (r) { return r.position; } }];
                h += '<div class="mmi-grid">' + table('Top searches', qcols, s.queries) + table('Pages ranking on Google', [{ label: 'Page', get: function (r) { return esc(r.key.replace(/^https?:\/\/[^/]+/, '') || '/'); } }].concat(qcols.slice(1)), s.pages) + '</div>';
            }
            return { html: h, after: function () {
                if (t.trend) line('mmi-c4', t.trend.labels, [{ label: 'Visits', data: t.trend.sessions }, { label: 'People', data: t.trend.users }]);
                if (s.trend) line('mmi-c5', s.trend.labels, [{ label: 'Clicks', data: s.trend.clicks }, { label: 'Impressions', data: s.trend.impressions, axis: 'y2' }], { y2: true });
            } };
        },
        registries: function (d) {
            var sum = function (o) { return Object.keys(o || {}).reduce(function (a, k) { return a + o[k]; }, 0); };
            var h = '<div class="mmi-cards">' + card('Lists created', num(sum(d.created)), false, 'in this period') + card('Gifts reserved', num(sum(d.reserved)), false, 'in this period (counted from Nov 4, 2026)') +
                card('Lists (all time)', num(d.total), false, num(d.with_res) + ' with reserved gifts') + card('Events in the next 30 days', num(d.upcoming), false, 'lists with a date coming up') +
                card('Cash gift QR', num(d.cash), false, 'lists with a GCash/Maya/bank QR') + card('People with 2+ lists', num(d.multi), false, 'same email, several occasions') + '</div>';
            h += '<div class="mmi-grid">' + bars('Lists created by occasion (this period)', objBars(d.created)) + bars('Gifts reserved by occasion (this period)', objBars(d.reserved)) + '</div>';
            h += '<div class="mmi-grid">' + bars('All lists by occasion (all time)', objBars(d.all)) + bars('Affiliate clicks from lists and registry guides', objBars(d.clicks)) + '</div>';
            return { html: h };
        },
        sponsors: function (d) {
            var h = '<div class="mmi-cards">' + card('Ad revenue', peso(d.revenue), false, 'paid in this period (live only)') + card('Monthly recurring', peso(d.mrr), false, 'from live subscriptions') + card('Live ads', num(d.live), false, 'right now') + '</div>';
            h += bars('Seats in use (of 3)', (d.seats || []).map(function (x) { return { name: x.name, value: x.live }; }));
            h += table('Ads', [
                { label: 'Ad', get: function (r) { return '<a href="' + esc(r.report) + '" target="_blank" rel="noopener">' + esc(r.brand || '(no brand)') + ' \u2197</a><br><small class="mmi-muted">' + esc(r.adv) + '</small>'; } },
                { label: 'Placement', get: function (r) { return esc(r.place); } },
                { label: 'Status', get: function (r) { return esc(r.status); } },
                { label: 'Views', num: 1, get: function (r) { return num(r.views); } },
                { label: 'Emails', num: 1, get: function (r) { return num(r.sent); } },
                { label: 'Clicks', num: 1, get: function (r) { return num(r.clicks); } },
                { label: 'CTR', num: 1, get: function (r) { return r.ctr === null ? '\u2013' : r.ctr + '%'; } },
                { label: 'Paid', num: 1, get: function (r) { return peso(r.paid); } }
            ], d.rows, 'No ads booked yet.');
            return { html: h };
        },
        affiliate: function (d) {
            var f = d.features, total = d.chart.data.affiliate_click.reduce(function (a, b) { return a + b; }, 0);
            var h = '<div class="mmi-cards">' + card('Affiliate clicks', num(total), false, 'in this period') + card('My Wishlists (all time)', num(f.my_wishlists), false, num(f.reservations) + ' with reserved gifts') + card('Support chats', num((f.chats.new || 0) + (f.chats.pending || 0) + (f.chats.resolved || 0)), false, num(f.chats.pending || 0) + ' pending, ' + num(f.chats.new || 0) + ' new') + '</div>';
            h += chartBox('mmi-c6', 'Affiliate clicks per day');
            h += '<div class="mmi-grid">' + bars('Clicks by store', objBars(d.by.store)) + bars('Clicks by placement', objBars(d.by.placement)) + '</div>';
            var g = d.groups || [];
            h += table('Groups with the most clicks', [{ label: 'Group', get: function (r) { return '<a href="' + esc(r.link) + '" target="_blank" rel="noopener">' + esc(r.name) + ' \u2197</a>'; } }, { label: 'Clicks', num: 1, get: function (r) { return num(r.clicks); } }], g);
            if (d.events && !d.events.error) {
                var ev = d.events, pick = function (n, l) { return { name: l, value: ev[n] ? ev[n].count : 0 }; };
                h += '<div class="mmi-grid">' + bars('Feature usage (GA4 events)', [pick('wishlist_create', 'My Wishlist created'), pick('wishlist_reserve', 'Gifts reserved'), pick('qa_ask', 'Anonymous questions'), pick('qa_answer', 'Answers'), pick('support_ask', 'Chat questions'), pick('support_handoff', 'Talk to a person'), pick('find_groups', 'Lost-link emails'), pick('repeat_group_created', 'Groups from an old one')]) +
                    bars('Engagement (GA4 events)', [pick('social_click', 'Social follows'), pick('wishlist_cta', 'My Wishlist promo clicks'), pick('tour_start', 'Tours started'), pick('tour_complete', 'Tours finished'), pick('gift_status', 'Gift status updates'), pick('create_group_click', 'Create group clicks')]) + '</div>';
            } else { h += gError(d.events); }
            return { html: h, after: function () { line('mmi-c6', d.chart.labels, [{ label: 'Affiliate clicks', data: d.chart.data.affiliate_click }], { type: 'bar' }); } };
        }
    };

    function render() {
        var body = $('[data-mmi-body]');
        body.addClass('is-loading');
        post(state.view).done(function (r) {
            charts.forEach(function (c) { c.destroy(); }); charts = [];
            if (!r || !r.success) { body.html('<p class="mmi-warn">' + esc((r && r.data) || 'Could not load.') + '</p>'); return; }
            var v = views[state.view](r.data);
            body.html('<p class="mmi-range">' + esc(r.data.period.from) + ' \u2192 ' + esc(r.data.period.to) + ' <span>compared with ' + esc(r.data.period.prevFrom) + ' \u2192 ' + esc(r.data.period.prevTo) + '</span></p>' + v.html);
            if (v.after) v.after();
            live();
        }).fail(function () { body.html('<p class="mmi-warn">Network error. Please refresh.</p>'); })
          .always(function () { body.removeClass('is-loading'); });
    }

    function live() {
        if (document.hidden) return;
        post('live').done(function (r) {
            if (!r || !r.success) return;
            var d = r.data;
            $('[data-mmi-active]').text(d.google && !d.google.error ? num(d.google.active) : '\u2013');
            $('[data-mmi-at]').text('updated ' + d.at);
            var icons = { group_created: '\ud83c\udf81', repeat_group: '\ud83d\udd01', member_confirmed: '\u2705', purchase: '\ud83d\udcb3', affiliate_click: '\ud83d\uded2', shuffle: '\ud83d\udd00', unshuffle: '\u21a9\ufe0f', season_email: '\ud83d\udce7', support_handoff: '\ud83d\udcac' };
            $('[data-mmi-feed]').html(d.feed.length ? '<ul class="mmi-feed">' + d.feed.map(function (e) {
                return '<li><span>' + (icons[e.type] || '\u2022') + '</span><div><b>' + esc(e.label) + '</b>' + (e.amount ? ' ' + peso(e.amount) : '') + (e.group ? ' <em>' + esc(e.group) + '</em>' : '') + (e.meta ? ' <em>' + esc(e.meta.replace('|', ' \u00b7 ')) + '</em>' : '') + '<small>' + esc(e.when) + '</small></div></li>';
            }).join('') + '</ul>' : '<p class="mmi-empty">Events appear here as they happen (from now on).</p>');
            var rt = $('[data-mmi-rt]');
            if (rt.length && d.google && !d.google.error) {
                rt.html('<h3>On the site right now: ' + num(d.google.active) + '</h3>' + (d.google.pages || []).map(function (p) { return '<div class="mmi-bar"><span class="mmi-bar__name">' + esc(p.name) + '</span><b>' + num(p.value) + '</b></div>'; }).join(''));
            }
            var w = $('[data-mmi-widget]');
            if (w.length) widget(w, d);
        });
    }

    function widget(w, d) {
        w.html('<div class="mmi-wcards">' +
            '<div><b>' + num(d.today.groups) + '</b><span>groups today</span></div>' +
            '<div><b>' + num(d.today.members) + '</b><span>members today</span></div>' +
            '<div><b>' + peso(d.today.revenue) + '</b><span>PRO today</span></div>' +
            '<div><b>' + num(d.today.clicks) + '</b><span>affiliate clicks</span></div>' +
            '<div><b>' + (d.google && !d.google.error ? num(d.google.active) : '\u2013') + '</b><span>on the site now</span></div></div>' +
            '<p><a class="button button-primary" href="' + esc(cfg.page) + '">Open Manito Insights</a> <small>updated ' + esc(d.at) + '</small></p>');
    }

    $(function () {
        var root = $('[data-mmi]'), w = $('[data-mmi-widget]');
        if (!root.length && !w.length) return;

        if (root.length) {
            try { var saved = localStorage.getItem('mmi_period'); if (saved) state.period = saved; } catch (e) {}
            root.find('[data-mmi-period]').val(state.period).on('change', function () {
                state.period = this.value;
                root.find('[data-mmi-custom]').prop('hidden', state.period !== 'custom');
                if (state.period !== 'custom') { try { localStorage.setItem('mmi_period', state.period); } catch (e) {} render(); }
            });
            root.find('[data-mmi-apply]').on('click', function () { state.from = root.find('[data-mmi-from]').val(); state.to = root.find('[data-mmi-to]').val(); render(); });
            root.find('[data-mmi-tab]').on('click', function () {
                root.find('[data-mmi-tab]').removeClass('is-on').attr('aria-selected', 'false');
                $(this).addClass('is-on').attr('aria-selected', 'true');
                state.view = $(this).attr('data-mmi-tab');
                render();
            });
            root.find('[data-mmi-export]').on('click', function () {
                var btn = $(this).prop('disabled', true).text('Preparing\u2026');
                post('export').done(function (r) {
                    if (!r || !r.success) { window.alert('Export failed: ' + ((r && r.data) || 'unknown error')); return; }
                    var url = URL.createObjectURL(new Blob([r.data.markdown], { type: 'text/markdown;charset=utf-8' }));
                    var a = $('<a>').attr({ href: url, download: r.data.filename }).appendTo('body');
                    a[0].click();
                    a.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                }).fail(function () { window.alert('Export failed. Please try again.'); })
                    .always(function () { btn.prop('disabled', false).text('Export for AI'); });
            });
            if (state.period === 'custom') state.period = '30d';
            render();
        } else {
            live();
        }

        liveTimer = setInterval(live, 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) live(); });
    });
})(jQuery);
