<?php
/**
 * EmailCenterAdmin Class. WP Admin > Manito Emails: Overview, Campaigns (+ 4-step editor), Automatic emails,
 * Deliverability. Data and sending live in EmailCenter / EmailAudience / EmailDeliver / EmailAI.
 * JS: assets/js/mm-email-center.js. CSS: mm-insights.css (cards, panels) + mm-email-center.css.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class EmailCenterAdmin {
    const PAGE = 'mm-emails';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_mm_ec', array( __CLASS__, 'handle' ) );
        foreach ( array( 'audience', 'preview', 'ai', 'autosave', 'status' ) as $a ) add_action( 'wp_ajax_mm_ec_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
    }

    public static function menu() {
        add_menu_page( 'Manito Emails', 'Manito Emails', 'manage_options', self::PAGE, array( __CLASS__, 'screen' ), 'dashicons-email-alt', 3 );
    }

    public static function url( $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
    }

    public static function assets( $hook ) {
        if ( strpos( (string) $hook, self::PAGE ) === false ) return;
        $dir = get_template_directory();
        $uri = get_template_directory_uri();
        wp_enqueue_style( 'mm-insights', $uri . '/assets/csslib/mm-insights.css', array(), (string) @filemtime( $dir . '/assets/csslib/mm-insights.css' ) );
        wp_enqueue_style( 'mm-email-center', $uri . '/assets/csslib/mm-email-center.css', array( 'mm-insights' ), (string) @filemtime( $dir . '/assets/csslib/mm-email-center.css' ) );
        wp_enqueue_script( 'mm-email-center', $uri . '/assets/js/mm-email-center.js', array( 'jquery' ), (string) @filemtime( $dir . '/assets/js/mm-email-center.js' ), true );
        wp_localize_script( 'mm-email-center', 'mmEC', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mm_ec' ), 'ai' => EmailAI::enabled() ) );
    }

    protected static function guard( $ajax = true ) {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'mm_ec', 'nonce', false ) ) {
            if ( $ajax ) wp_send_json_error( 'Please refresh the page.' );
            wp_die( 'Not allowed.' );
        }
    }

    /* ------------------------------------------------------------------ Templates */

    public static function templates() {
        $season = array( 'source' => array( 'organizers' ), 'season' => 'last', 'exchange' => 'any', 'org_active' => 'no', 'gift' => 'any' );
        return array(
            'blank'   => array( 'name' => 'New campaign', 'audience' => array(), 'content' => array() ),
            'season'  => array( 'name' => 'Ready for this year? (organizers)', 'audience' => $season, 'content' => array(
                'subject' => 'Ready for this year&rsquo;s exchange, {first_name}?', 'preheader' => 'Start this year&rsquo;s group from {group_name} and invite everyone back in a few taps.', 'eyebrow' => 'Christmas is coming', 'title' => 'Ready for this year&rsquo;s exchange?',
                'body' => '<p>Hi {first_name}! Last year you organized <strong>{group_name}</strong> on Manito Manita. Thank you!</p><p>Doing it again? Tap the button to set up this year&rsquo;s group: enter the new name, date and budget, then choose which of last year&rsquo;s members to invite. It&rsquo;s free, as always.</p>',
                'button_label' => 'Start this year&rsquo;s group', 'button_url' => '{start_link}', 'card' => 0 ) ),
            'shuffle' => array( 'name' => 'Shuffle reminder push (organizers)', 'audience' => array( 'source' => array( 'organizers' ), 'season' => 'this', 'exchange' => 'upcoming', 'days' => 21, 'shuffled' => 'no', 'gift' => 'any' ), 'content' => array(
                'subject' => 'Ready to shuffle {group_name}?', 'preheader' => 'Your exchange is on {exchange_date}. Shuffle soon so everyone has time to shop.', 'eyebrow' => 'Almost time', 'title' => 'Time to draw names?',
                'body' => '<p>Hi {first_name}! <strong>{group_name}</strong> exchanges gifts on {exchange_date}, {days_left} days from now.</p><p>Shuffle a little early so everyone has time to check their match&rsquo;s wishlist and shop within the {budget} budget.</p>',
                'button_label' => 'Open your group', 'button_url' => '{group_link}' ) ),
            'sale'    => array( 'name' => 'Sale-eve gift ideas (members)', 'audience' => array( 'source' => array( 'members' ), 'season' => 'this', 'exchange' => 'upcoming', 'days' => 60, 'gift' => 'not_bought' ), 'window' => array( 18, 23 ), 'content' => array(
                'subject' => 'The sale starts at midnight: a gift for {match_name}', 'preheader' => 'Add your gift to your cart tonight, then check out when the deals open.', 'eyebrow' => 'Sale tonight', 'title' => 'Get {match_name}&rsquo;s gift on sale',
                'body' => '<p>Hi {first_name}! The big sale starts at midnight. Add your gift for {group_name} to your cart tonight, then check out when the deals open.</p>',
                'button_label' => 'See their wishlist', 'button_url' => '{group_link}', 'ideas' => 1, 'wish' => 1 ) ),
        );
    }

    /* ------------------------------------------------------------------ Actions */

    protected static function fromPost( array $d ) {
        $a = (array) wp_unslash( $_POST['audience'] ?? array() );
        if ( isset( $_POST['audience'] ) ) $d['audience'] = EmailAudience::clean( $a );
        $c = (array) wp_unslash( $_POST['content'] ?? array() );
        if ( isset( $_POST['content'] ) ) {
            foreach ( array( 'subject', 'preheader', 'eyebrow', 'title', 'button_label' ) as $k ) $d['content'][ $k ] = mb_substr( sanitize_text_field( (string) ( $c[ $k ] ?? '' ) ), 0, 200 );
            $u = trim( (string) ( $c['button_url'] ?? '' ) );
            // A token alone, or with extra parameters after it (e.g. {group_link}&pop=pro-exclude opens the PRO sheet).
            $d['content']['button_url'] = preg_match( '/^\{(group_link|start_link)\}(&[A-Za-z0-9_\-]+=[A-Za-z0-9_\-.%]*)*$/', $u ) ? $u : esc_url_raw( $u );
            $d['content']['body'] = wp_kses_post( (string) ( $c['body'] ?? '' ) );
            foreach ( array( 'ideas', 'wish', 'card' ) as $k ) $d['content'][ $k ] = empty( $c[ $k ] ) ? 0 : 1;
        }
        if ( isset( $_POST['sched'] ) ) {
            $s = (array) wp_unslash( $_POST['sched'] );
            $from = max( 0, min( 23, (int) ( $s['from'] ?? 8 ) ) );
            $to = max( $from + 1, min( 24, (int) ( $s['to'] ?? 21 ) ) );
            $d['window'] = array( $from, $to );
            $d['rate'] = max( 10, min( 500, (int) ( $s['rate'] ?? 150 ) ) );
            $dt = DateTime::createFromFormat( 'Y-m-d\TH:i', (string) ( $s['at'] ?? '' ), wp_timezone() );
            $d['send_at'] = $dt ? $dt->getTimestamp() : 0;
        }

        return $d;
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        check_admin_referer( 'mm_ec' );
        $op = sanitize_key( (string) ( $_POST['op'] ?? '' ) );
        $id = absint( $_POST['id'] ?? 0 );
        if ( $id && get_post_type( $id ) !== EmailCenter::CPT ) wp_die( 'Unknown campaign.' );
        $st = $id ? EmailCenter::status( $id ) : '';
        $back = function ( $args ) { wp_safe_redirect( self::url( $args ) ); exit; };

        if ( $op === 'new' || $op === 'duplicate' ) {
            if ( $op === 'new' ) {
                $t = self::templates()[ sanitize_key( (string) ( $_POST['template'] ?? 'blank' ) ) ] ?? self::templates()['blank'];
                $d = EmailCenter::defaults();
                $d['audience'] = EmailAudience::clean( array_merge( $d['audience'], $t['audience'] ) );
                $d['content'] = array_merge( $d['content'], $t['content'] );
                if ( ! empty( $t['window'] ) ) $d['window'] = $t['window'];
                $name = $t['name'];
            } else {
                $src = EmailCenter::get( $id );
                $d = EmailCenter::defaults();
                $d['audience'] = $src['audience'];
                $d['content'] = $src['content'];
                $d['window'] = $src['window'];
                $d['rate'] = $src['rate'];
                $name = get_the_title( $id ) . ' (copy)';
            }
            $new = wp_insert_post( array( 'post_type' => EmailCenter::CPT, 'post_status' => 'publish', 'post_title' => $name ) );
            EmailCenter::save( $new, $d );
            EmailCenter::setStatus( $new, 'draft', 'created' );
            $back( array( 'tab' => 'edit', 'id' => $new ) );
        }

        if ( $op === 'unsuppress' ) {
            EmailDeliver::unsuppress( preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['hash'] ?? '' ) ) );
            $back( array( 'tab' => 'deliverability', 'msg' => 'unsuppressed' ) );
        }
        if ( ! $id ) $back( array() );
        $d = EmailCenter::get( $id );

        switch ( $op ) {
            case 'save':
            case 'test':
            case 'schedule':
                if ( in_array( $st, array( 'sending', 'done', 'cancelled' ), true ) ) $back( array( 'tab' => 'edit', 'id' => $id, 'msg' => 'locked' ) );
                if ( isset( $_POST['name'] ) ) wp_update_post( array( 'ID' => $id, 'post_title' => mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 120 ) ?: 'Campaign' ) );
                $d = self::fromPost( $d );
                if ( $st === 'scheduled' && EmailCenter::testHash( $d ) !== $d['tested'] ) EmailCenter::setStatus( $id, 'draft', 'edited after scheduling' );
                EmailCenter::save( $id, $d );
                if ( $op === 'save' ) $back( array( 'tab' => 'edit', 'id' => $id, 'msg' => 'saved', 'step' => sanitize_key( (string) ( $_POST['step'] ?? '' ) ) ) );
                if ( $op === 'test' ) {
                    $ok = self::sendTest( $id, $d, sanitize_email( wp_unslash( (string) ( $_POST['extra'] ?? '' ) ) ) );
                    if ( $ok ) { $d['tested'] = EmailCenter::testHash( $d ); EmailCenter::save( $id, $d ); }
                    $back( array( 'tab' => 'edit', 'id' => $id, 'msg' => $ok ? 'tested' : 'test-fail', 'step' => '3' ) );
                }
                // schedule
                if ( EmailCenter::testHash( $d ) !== $d['tested'] ) $back( array( 'tab' => 'edit', 'id' => $id, 'msg' => 'needs-test', 'step' => '3' ) );
                if ( trim( $d['content']['subject'] ) === '' ) $back( array( 'tab' => 'edit', 'id' => $id, 'msg' => 'no-subject', 'step' => '2' ) );
                if ( ! $d['send_at'] || ! empty( $_POST['now'] ) ) { $d['send_at'] = time(); EmailCenter::save( $id, $d ); }
                EmailCenter::setStatus( $id, 'scheduled', wp_date( 'M j, g:i A', $d['send_at'] ) );
                $back( array( 'tab' => 'campaigns', 'msg' => 'scheduled' ) );
            case 'unschedule':
                if ( $st === 'scheduled' ) EmailCenter::setStatus( $id, 'draft', 'unscheduled' );
                $back( array( 'tab' => 'edit', 'id' => $id ) );
            case 'pause':
                if ( $st === 'sending' ) EmailCenter::setStatus( $id, 'paused', 'paused by you' );
                $back( array( 'tab' => 'campaigns' ) );
            case 'resume':
                if ( $st === 'paused' ) {
                    // Count only new bounces from here on, so an auto-pause doesn't fire again on the old ones.
                    list( $s, $b, $c ) = EmailCenter::rates( $id, array( 'raw' => 1 ) );
                    $d['ack'] = array( 's' => $s, 'b' => $b, 'c' => $c );
                    unset( $d['auto_paused'] );
                    EmailCenter::save( $id, $d );
                    EmailCenter::setStatus( $id, 'sending', 'resumed' );
                }
                $back( array( 'tab' => 'campaigns' ) );
            case 'cancel':
                if ( in_array( $st, array( 'scheduled', 'sending', 'paused' ), true ) ) {
                    global $wpdb;
                    $wpdb->query( $wpdb->prepare( 'UPDATE ' . EmailCenter::queueTable() . " SET status = 'skipped', reason = 'cancelled' WHERE campaign_id = %d AND status = 'queued'", $id ) );
                    EmailCenter::setStatus( $id, 'cancelled', 'cancelled by you' );
                }
                $back( array( 'tab' => 'campaigns' ) );
            case 'delete':
                if ( in_array( $st, array( 'draft', 'done', 'cancelled' ), true ) ) wp_delete_post( $id, true );
                $back( array( 'tab' => 'campaigns' ) );
        }
        $back( array() );
    }

    /** Test to the admin (+ one extra address), rendered as the first recipient (or a sample if none). */
    protected static function sendTest( $id, array $d, $extra = '' ) {
        $b = EmailAudience::build( $d['audience'] );
        $r = reset( $b['recipients'] );
        if ( ! $r ) {
            $gid = (int) ( get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
            $r = array( 'ref_type' => 'organizer', 'ref_id' => $gid, 'gid' => $gid );
        }
        $ok = false;
        foreach ( array_filter( array( wp_get_current_user()->user_email, is_email( $extra ) ? $extra : '' ) ) as $to ) {
            list( $subject, $o ) = EmailCenter::message( $id, $r['ref_type'], $r['ref_id'], $r['gid'], $to, 0, $d );
            $o['essential'] = true; // tests always arrive
            $ok = Emails::send( $to, '[Test] ' . $subject, $o ) || $ok;
        }

        return $ok;
    }

    /* ------------------------------------------------------------------ AJAX */

    public static function ajaxAudience() {
        self::guard();
        wp_send_json_success( EmailAudience::preview( (array) wp_unslash( $_POST['audience'] ?? array() ), absint( $_POST['id'] ?? 0 ) ) );
    }

    public static function ajaxPreview() {
        self::guard();
        $id = absint( $_POST['id'] ?? 0 );
        if ( get_post_type( $id ) !== EmailCenter::CPT ) wp_send_json_error( 'Unknown campaign.' );
        $d = self::fromPost( EmailCenter::get( $id ) ); // unsaved edits, not stored
        $real = $d;
        $b = EmailAudience::build( $d['audience'] );
        $ref = sanitize_text_field( (string) ( $_POST['as'] ?? '' ) );
        $r = null;
        foreach ( $b['recipients'] as $x ) { if ( $ref === '' || $ref === $x['ref_type'] . ':' . $x['ref_id'] ) { $r = $x; break; } }
        if ( ! $r ) {
            $gid = (int) ( get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
            $r = array( 'ref_type' => 'organizer', 'ref_id' => $gid, 'gid' => $gid, 'email' => 'sample@example.com' );
        }
        list( $subject, $o ) = EmailCenter::message( $id, $r['ref_type'], $r['ref_id'], $r['gid'], (string) ( $r['email'] ?? 'sample@example.com' ), 0, $d );
        $samples = array();
        foreach ( array_slice( $b['recipients'], 0, 8 ) as $x ) $samples[] = array( 'ref' => $x['ref_type'] . ':' . $x['ref_id'], 'label' => EmailDeliver::mask( $x['email'] ) . ' (' . $x['ref_type'] . ')' );
        wp_send_json_success( array(
            'subject'   => html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ),
            'preheader' => html_entity_decode( wp_strip_all_tags( (string) $o['preheader'] ), ENT_QUOTES, 'UTF-8' ),
            'html'      => Emails::render( $o ),
            'warnings'  => EmailCenter::spamCheck( $real['content'] ),
            'tested'    => EmailCenter::testHash( $real ) === $real['tested'],
            'samples'   => $samples,
        ) );
    }

    public static function ajaxAutosave() {
        self::guard();
        $id = absint( $_POST['id'] ?? 0 );
        if ( get_post_type( $id ) !== EmailCenter::CPT || in_array( EmailCenter::status( $id ), array( 'sending', 'done', 'cancelled' ), true ) ) wp_send_json_error( 'Locked.' );
        $d = self::fromPost( EmailCenter::get( $id ) );
        if ( EmailCenter::status( $id ) === 'scheduled' && EmailCenter::testHash( $d ) !== $d['tested'] ) EmailCenter::setStatus( $id, 'draft', 'edited after scheduling' );
        EmailCenter::save( $id, $d );
        if ( isset( $_POST['name'] ) ) wp_update_post( array( 'ID' => $id, 'post_title' => mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 120 ) ?: 'Campaign' ) );
        wp_send_json_success( array( 'saved' => wp_date( 'g:i:s A' ), 'tested' => EmailCenter::testHash( $d ) === $d['tested'] ) );
    }

    public static function ajaxAi() {
        self::guard();
        $a = EmailAudience::summary( (array) wp_unslash( $_POST['audience'] ?? array() ) );
        $c = (array) wp_unslash( $_POST['content'] ?? array() );
        $r = EmailAI::write( (string) wp_unslash( $_POST['brief'] ?? '' ), (string) ( $_POST['tone'] ?? 'friendly' ), (string) ( $_POST['lang'] ?? 'English' ), $a, $c, sanitize_key( (string) ( $_POST['mode'] ?? 'write' ) ) );
        if ( ! empty( $r['error'] ) ) wp_send_json_error( $r['error'] );
        wp_send_json_success( $r );
    }

    public static function ajaxStatus() {
        self::guard();
        $out = array();
        foreach ( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) as $id ) {
            if ( get_post_type( $id ) !== EmailCenter::CPT ) continue;
            $out[ $id ] = self::progress( $id );
        }
        wp_send_json_success( $out );
    }

    /* ------------------------------------------------------------------ Screens */

    protected static function chip( $id ) {
        $st = EmailCenter::status( $id );
        $d = EmailCenter::get( $id );
        $label = array( 'draft' => 'Draft', 'scheduled' => 'Scheduled', 'sending' => 'Sending', 'paused' => 'Paused', 'done' => 'Done', 'cancelled' => 'Cancelled' )[ $st ] ?? $st;
        if ( $st === 'sending' && ( (int) wp_date( 'G' ) < (int) $d['window'][0] || (int) wp_date( 'G' ) >= (int) $d['window'][1] ) ) $label = 'Waiting for send window';
        if ( $st === 'paused' && ! empty( $d['auto_paused'] ) ) $label = 'Paused: ' . $d['auto_paused'];

        return '<span class="mmec-chip is-' . esc_attr( $st ) . '">' . esc_html( $label ) . '</span>';
    }

    /** Progress numbers for the list and polling. */
    public static function progress( $id ) {
        $d = EmailCenter::get( $id );
        $c = EmailCenter::counts( $id );
        $total = max( (int) $d['total'], array_sum( array_intersect_key( $c, array_flip( array( 'queued', 'sending', 'sent', 'failed', 'skipped', 'bounced' ) ) ) ) );
        $done = $c['sent'] + $c['failed'] + $c['skipped'] + $c['bounced'];
        $left = $c['queued'];
        $runsLeft = $d['rate'] ? ceil( $left / $d['rate'] ) : 0;

        $stage = EmailCenter::status( $id ) === 'sending' && $left ? EmailCenter::stage( $id ) : array( '', '', '' );

        return array(
            'chip'  => self::chip( $id ),
            'stage' => trim( $stage[0] . ( $stage[2] ? ' · ' . $stage[2] : '' ) ),
            'pct'   => $total ? round( $done / $total * 100 ) : 0,
            'line'  => $total ? number_format( $c['sent'] ) . ' sent of ' . number_format( $total ) . ( $c['failed'] ? ' · ' . number_format( $c['failed'] ) . ' failed' : '' ) . ( $c['skipped'] ? ' · ' . number_format( $c['skipped'] ) . ' skipped' : '' ) . ( $c['bounced'] ? ' · ' . number_format( $c['bounced'] ) . ' bounced' : '' ) . ( $left ? ' · about ' . ( $runsLeft * 2 ) . ' min left' : '' ) : '',
            'stats' => $c['sent'] ? number_format( $c['opened'] ) . ' opens (' . round( $c['opened'] / $c['sent'] * 100 ) . '%) · ' . number_format( $c['clicked'] ) . ' clicks (' . round( $c['clicked'] / $c['sent'] * 100, 1 ) . '%)' : '',
            'live'  => in_array( EmailCenter::status( $id ), array( 'scheduled', 'sending' ), true ),
        );
    }

    protected static function form( $op, $label, $class = 'button', array $hidden = array(), $confirm = '' ) {
        $h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mmec-inline"' . ( $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : '' ) . '>' . wp_nonce_field( 'mm_ec', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="mm_ec"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
        foreach ( $hidden as $k => $v ) $h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';

        return $h . '<button type="submit" class="' . esc_attr( $class ) . '">' . $label . '</button></form>';
    }

    public static function screen() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $tab = sanitize_key( (string) ( $_GET['tab'] ?? 'overview' ) );
        echo '<div class="wrap mmi mmec"><h1 class="mmec-h1">Manito Emails</h1>';
        if ( $tab !== 'edit' ) {
            echo '<nav class="mmi-tabs mmec-tabs">';
            foreach ( array( 'overview' => 'Overview', 'campaigns' => 'Campaigns', 'automatic' => 'Automatic emails', 'deliverability' => 'Deliverability' ) as $k => $l ) {
                echo '<a href="' . esc_url( self::url( array( 'tab' => $k ) ) ) . '" class="mmi-tab' . ( $tab === $k ? ' is-on' : '' ) . '">' . esc_html( $l ) . '</a>';
            }
            echo '</nav>';
        }
        $msgs = array( 'scheduled' => array( 'success', 'Scheduled. It starts at its time and sends inside its send window.' ), 'saved' => array( 'success', 'Saved.' ), 'tested' => array( 'success', 'Test sent. Check it on your phone; you can schedule now.' ), 'test-fail' => array( 'error', 'The test could not be sent.' ), 'needs-test' => array( 'warning', 'Send a test of the latest version first.' ), 'no-subject' => array( 'warning', 'Add a subject first.' ), 'locked' => array( 'warning', 'This campaign has started, so it can&rsquo;t be edited. Duplicate it instead.' ), 'unsuppressed' => array( 'success', 'Removed from the suppression list.' ) );
        $m = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        if ( isset( $msgs[ $m ] ) ) echo '<div class="notice notice-' . $msgs[ $m ][0] . ' is-dismissible"><p>' . $msgs[ $m ][1] . '</p></div>';

        if ( $tab === 'edit' ) self::editScreen( absint( $_GET['id'] ?? 0 ) );
        elseif ( $tab === 'campaigns' ) self::campaignsScreen();
        elseif ( $tab === 'automatic' ) self::automaticScreen();
        elseif ( $tab === 'deliverability' ) self::deliverScreen();
        else self::overviewScreen();
        echo '</div>';
    }

    protected static function card( $label, $value, $sub = '', $cls = '' ) {
        return '<div class="mmi-card' . ( $cls ? ' ' . $cls : '' ) . '"><span class="mmi-card__label">' . $label . '</span><strong class="mmi-card__value">' . $value . '</strong><span class="mmi-card__foot">' . $sub . '</span></div>';
    }

    protected static function rates( $days ) {
        $t = EmailDeliver::totals( $days );
        $sent = (int) ( $t['sent'] ?? 0 );

        return array( $sent, $sent ? round( ( $t['bounce'] ?? 0 ) / $sent * 100, 2 ) : 0, $sent ? round( ( $t['complaint'] ?? 0 ) / $sent * 100, 3 ) : 0, $t );
    }

    protected static function campaigns( $statuses = array() ) {
        $q = array( 'post_type' => EmailCenter::CPT, 'post_status' => 'publish', 'posts_per_page' => 100, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' );
        if ( $statuses ) $q['meta_query'] = array( array( 'key' => '_mm_cmp_status', 'value' => $statuses, 'compare' => 'IN' ) );

        return get_posts( $q );
    }

    protected static function overviewScreen() {
        list( $s1 ) = self::rates( 1 );
        list( $s7, $b30, $c30 ) = array_merge( array( self::rates( 7 )[0] ), array_slice( self::rates( 30 ), 1, 2 ) );
        $up = self::campaigns( array( 'scheduled', 'sending', 'paused' ) );
        echo '<div class="mmi-cards">' . self::card( 'Sent today', number_format( $s1 ), 'all emails' ) . self::card( 'Sent this week', number_format( $s7 ), 'last 7 days' )
            . self::card( 'Bounce rate', $b30 . '%', 'last 30 days · keep under 2%', $b30 >= 5 ? 'is-bad' : ( $b30 >= 2 ? 'is-warn' : '' ) )
            . self::card( 'Spam complaints', $c30 . '%', 'last 30 days · keep under 0.1%', $c30 >= 0.1 ? 'is-bad' : ( $c30 >= 0.05 ? 'is-warn' : '' ) )
            . self::card( 'Scheduled or sending', number_format( count( $up ) ), 'campaigns' ) . '</div>';

        echo '<div class="mmi-panel"><h3>Next up</h3><ul class="mmec-timeline">';
        $items = array();
        foreach ( $up as $id ) { $d = EmailCenter::get( $id ); $items[] = array( max( time(), (int) $d['send_at'] ), '<a href="' . esc_url( self::url( array( 'tab' => 'edit', 'id' => $id ) ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a> ' . self::chip( $id ) ); }
        foreach ( self::jobs() as $j ) if ( $j['next'] ) $items[] = array( $j['next'], esc_html( $j['label'] ) . ' <span class="mmec-chip is-auto">Automatic</span>' );
        usort( $items, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
        foreach ( array_slice( $items, 0, 10 ) as $i ) echo '<li><time>' . esc_html( wp_date( 'D, M j · g:i A', $i[0] ) ) . '</time><span>' . $i[1] . '</span></li>';
        if ( ! $items ) echo '<li>Nothing scheduled.</li>';
        echo '</ul></div>';
        echo '<p><a class="button button-primary" href="' . esc_url( self::url( array( 'tab' => 'campaigns' ) ) ) . '">Campaigns</a> <a class="button" href="' . esc_url( self::url( array( 'tab' => 'deliverability' ) ) ) . '">Deliverability</a></p>';
    }

    protected static function campaignsScreen() {
        echo '<div class="mmec-new"><strong>New campaign:</strong> ';
        foreach ( self::templates() as $k => $t ) echo self::form( 'new', esc_html( $k === 'blank' ? 'Blank' : $t['name'] ), $k === 'blank' ? 'button button-primary' : 'button', array( 'template' => $k ) );
        echo '</div>';
        $ids = self::campaigns();
        if ( ! $ids ) { echo '<div class="mmi-panel"><p class="mmi-empty">No campaigns yet. Start from a template above.</p></div>'; return; }
        echo '<div class="mmi-panel"><table class="mmi-table mmec-list"><thead><tr><th>Campaign</th><th>Status</th><th>When</th><th>Progress</th><th>Results</th><th></th></tr></thead><tbody>';
        foreach ( $ids as $id ) {
            $d = EmailCenter::get( $id );
            $st = EmailCenter::status( $id );
            $p = self::progress( $id );
            $when = $d['send_at'] ? wp_date( 'M j, g:i A', $d['send_at'] ) : '–';
            echo '<tr data-mmec-row="' . (int) $id . '"' . ( $p['live'] ? ' data-mmec-live' : '' ) . '><td><a href="' . esc_url( self::url( array( 'tab' => 'edit', 'id' => $id ) ) ) . '"><strong>' . esc_html( get_the_title( $id ) ) . '</strong></a><br><small class="mmi-muted">' . esc_html( html_entity_decode( EmailAudience::summary( $d['audience'] ), ENT_QUOTES, 'UTF-8' ) ) . '</small></td>';
            echo '<td data-mmec-chip>' . $p['chip'] . '</td><td>' . esc_html( $when ) . '<br><small class="mmi-muted">' . (int) $d['window'][0] . ':00–' . (int) $d['window'][1] . ':00</small></td>';
            echo '<td class="mmec-prog"><div class="mmec-bar"><i style="width:' . (int) $p['pct'] . '%" data-mmec-bar></i></div><small data-mmec-line>' . esc_html( $p['line'] ) . '</small><br><small class="mmi-muted" data-mmec-stage>' . esc_html( $p['stage'] ) . '</small></td><td><small data-mmec-stats>' . esc_html( $p['stats'] ) . '</small></td><td class="mmec-acts">';
            if ( $st === 'sending' ) echo self::form( 'pause', 'Pause', 'button', array( 'id' => $id ) );
            if ( $st === 'paused' ) echo self::form( 'resume', 'Resume', 'button button-primary', array( 'id' => $id ) );
            if ( in_array( $st, array( 'scheduled', 'sending', 'paused' ), true ) ) echo self::form( 'cancel', 'Cancel', 'button-link mmec-danger', array( 'id' => $id ), 'Cancel the rest of this campaign? Emails already sent stay sent.' );
            echo self::form( 'duplicate', 'Duplicate', 'button-link', array( 'id' => $id ) );
            if ( in_array( $st, array( 'draft', 'done', 'cancelled' ), true ) ) echo self::form( 'delete', 'Delete', 'button-link mmec-danger', array( 'id' => $id ), 'Delete this campaign?' );
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    protected static function sel( $name, $cur, array $opts ) {
        $h = '<select name="' . esc_attr( $name ) . '" data-mmec-aud>';
        foreach ( $opts as $v => $l ) $h .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $cur, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';

        return $h . '</select>';
    }

    protected static function editScreen( $id ) {
        if ( get_post_type( $id ) !== EmailCenter::CPT ) { echo '<p>Campaign not found.</p>'; return; }
        $d = EmailCenter::get( $id );
        $a = $d['audience'];
        $c = $d['content'];
        $st = EmailCenter::status( $id );
        $locked = in_array( $st, array( 'sending', 'done', 'cancelled', 'paused' ), true );
        $step = sanitize_key( (string) ( $_GET['step'] ?? '1' ) ) ?: '1';
        $in = function ( $name, $val, $attrs = '' ) { return '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( html_entity_decode( (string) $val, ENT_QUOTES, 'UTF-8' ) ) . '" ' . $attrs . '>'; };

        echo '<p><a href="' . esc_url( self::url( array( 'tab' => 'campaigns' ) ) ) . '">&larr; All campaigns</a></p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mmec-edit" data-mmec-edit data-id="' . (int) $id . '" data-step="' . esc_attr( $step ) . '"' . ( $locked ? ' data-locked' : '' ) . '>';
        wp_nonce_field( 'mm_ec' );
        echo '<input type="hidden" name="action" value="mm_ec"><input type="hidden" name="id" value="' . (int) $id . '"><input type="hidden" name="step" value="' . esc_attr( $step ) . '" data-mmec-stepfield>';
        echo '<div class="mmec-head"><input type="text" name="name" class="mmec-name" value="' . esc_attr( get_the_title( $id ) ) . '" aria-label="Campaign name"> ' . self::chip( $id ) . ' <span class="mmec-saved" data-mmec-saved></span></div>';
        if ( $locked ) {
            $p = self::progress( $id );
            echo '<div class="mmi-panel mmec-running" data-mmec-row="' . (int) $id . '"' . ( $p['live'] ? ' data-mmec-live' : '' ) . '><div class="mmec-bar mmec-bar--big"><i style="width:' . (int) $p['pct'] . '%" data-mmec-bar></i></div><p data-mmec-line>' . esc_html( $p['line'] ) . '</p><p class="mmi-muted" data-mmec-stage>' . esc_html( $p['stage'] ) . '</p><p data-mmec-stats>' . esc_html( $p['stats'] ) . '</p>';
            $r = EmailCenter::reasons( $id );
            if ( $r ) { echo '<p class="mmi-muted">Not sent: '; $parts = array(); foreach ( $r as $k => $n ) $parts[] = number_format( $n ) . ' ' . esc_html( $k ); echo implode( ' · ', $parts ) . '</p>'; }
            echo '<p class="mmi-muted">This campaign has started, so its content and audience are locked. Duplicate it to make changes.</p></div>';
        }

        echo '<ol class="mmec-steps" data-mmec-steps><li data-s="1">Audience</li><li data-s="2">Content</li><li data-s="3">Preview &amp; test</li><li data-s="4">Schedule</li></ol>';

        // Step 1: audience
        $src = $a['source'];
        echo '<section class="mmi-panel mmec-step" data-step="1"><h3>Who gets it</h3><div class="mmec-grid">';
        echo '<fieldset><legend>Send to</legend><label><input type="checkbox" name="audience[source][]" value="members" data-mmec-aud' . checked( in_array( 'members', $src, true ), true, false ) . '> Group members</label><label><input type="checkbox" name="audience[source][]" value="organizers" data-mmec-aud' . checked( in_array( 'organizers', $src, true ), true, false ) . '> Organizers</label></fieldset>';
        echo '<label>Groups from' . self::sel( 'audience[season]', $a['season'], array( 'this' => 'This season (since Sep 1)', 'last' => 'Last season', 'all' => 'Any season' ) ) . '</label>';
        echo '<label>Exchange date' . self::sel( 'audience[exchange]', $a['exchange'], array( 'any' => 'Any', 'upcoming' => 'Coming up within…', 'range' => 'Between dates', 'past' => 'Already passed' ) ) . '</label>';
        echo '<label data-mmec-if="exchange=upcoming">Within (days)<input type="number" min="1" max="365" name="audience[days]" value="' . esc_attr( $a['days'] ) . '" data-mmec-aud></label>';
        echo '<label data-mmec-if="exchange=range">From<input type="date" name="audience[exchange_from]" value="' . esc_attr( $a['exchange_from'] ) . '" data-mmec-aud></label><label data-mmec-if="exchange=range">To<input type="date" name="audience[exchange_to]" value="' . esc_attr( $a['exchange_to'] ) . '" data-mmec-aud></label>';
        echo '<label>Shuffled' . self::sel( 'audience[shuffled]', $a['shuffled'], array( 'any' => 'Any', 'yes' => 'Already shuffled', 'no' => 'Not shuffled yet' ) ) . '</label>';
        echo '<label>Plan' . self::sel( 'audience[pro]', $a['pro'], array( 'any' => 'Free and PRO', 'no' => 'Free only', 'yes' => 'PRO only' ) ) . '</label>';
        echo '<label>Budget from (₱)<input type="number" min="0" name="audience[budget_min]" value="' . esc_attr( $a['budget_min'] ) . '" data-mmec-aud></label><label>Budget up to (₱)<input type="number" min="0" name="audience[budget_max]" value="' . esc_attr( $a['budget_max'] ) . '" data-mmec-aud></label>';
        echo '<label>Group size at least<input type="number" min="0" name="audience[min_size]" value="' . esc_attr( $a['min_size'] ) . '" data-mmec-aud></label>';
        echo '<label>Group created from<input type="date" name="audience[created_from]" value="' . esc_attr( $a['created_from'] ) . '" data-mmec-aud></label><label>Group created to<input type="date" name="audience[created_to]" value="' . esc_attr( $a['created_to'] ) . '" data-mmec-aud></label>';
        echo '<label data-mmec-if="members">Members&rsquo; gift' . self::sel( 'audience[gift]', $a['gift'], array( 'any' => 'Anyone', 'not_bought' => 'Not bought yet' ) ) . '</label>';
        echo '<label data-mmec-if="members">Joined from<input type="date" name="audience[joined_from]" value="' . esc_attr( $a['joined_from'] ) . '" data-mmec-aud></label><label data-mmec-if="members">Joined to<input type="date" name="audience[joined_to]" value="' . esc_attr( $a['joined_to'] ) . '" data-mmec-aud></label>';
        echo '<label data-mmec-if="organizers">Organizer this season' . self::sel( 'audience[org_active]', $a['org_active'], array( 'any' => 'Any', 'yes' => 'Made a group this season', 'no' => 'Hasn’t made one yet' ) ) . '</label>';
        echo '<label>Max 1 promo per person every (days)<input type="number" min="0" max="30" name="audience[cap_days]" value="' . esc_attr( $a['cap_days'] ) . '" data-mmec-aud></label>';
        echo '<label>Only people who opened or clicked a promo in the last (months)<input type="number" min="0" max="24" name="audience[engaged]" value="' . esc_attr( $a['engaged'] ? $a['engaged'] : '' ) . '" placeholder="everyone" data-mmec-aud></label>';
        echo '<label>Only this email (for trials)<input type="email" name="audience[only_email]" value="' . esc_attr( $a['only_email'] ) . '" placeholder="optional" data-mmec-aud></label>';
        echo '<fieldset class="mmec-risk"><legend>Bounce protection</legend><label><input type="checkbox" name="audience[old_company]" value="1" data-mmec-aud' . checked( ! empty( $a['old_company'] ), true, false ) . '> Include older company and school addresses</label><small>Off by default: work emails of people who joined before this season are the most likely to have closed. Gmail, Yahoo, Outlook and iCloud addresses are always included.</small></fieldset>';
        echo '</div><div class="mmec-count" data-mmec-count>' . ( $locked ? '<strong>' . number_format( (int) $d['total'] ) . '</strong> recipients, frozen when sending started.' : '<span class="spinner is-active"></span> Counting…' ) . '</div></section>';

        // Step 2: content
        echo '<section class="mmi-panel mmec-step" data-step="2"><div class="mmec-cols"><div class="mmec-main"><h3>Email</h3>';
        echo '<label>Subject <small data-mmec-len="subject"></small>' . $in( 'content[subject]', $c['subject'], 'maxlength="150" data-mmec-c' ) . '</label>';
        echo '<label>Preheader <small>(grey preview line in the inbox)</small>' . $in( 'content[preheader]', $c['preheader'], 'maxlength="150" data-mmec-c' ) . '</label>';
        echo '<div class="mmec-two"><label>Eyebrow <small>(small label)</small>' . $in( 'content[eyebrow]', $c['eyebrow'], 'maxlength="40" data-mmec-c' ) . '</label><label>Title' . $in( 'content[title]', $c['title'], 'maxlength="100" data-mmec-c' ) . '</label></div>';
        echo '<label class="mmec-bodylabel">Message</label>';
        wp_editor( (string) $c['body'], 'mmec_body', array( 'textarea_name' => 'content[body]', 'media_buttons' => false, 'textarea_rows' => 9, 'teeny' => true, 'quicktags' => array( 'buttons' => 'strong,em,link,ul,li' ), 'tinymce' => array( 'toolbar1' => 'bold,italic,link,unlink,bullist,numlist,undo,redo', 'toolbar2' => '' ) ) );
        echo '<p class="mmec-tokens">Personal tokens: ';
        foreach ( EmailCenter::tokenNames() as $t ) if ( ! in_array( $t, array( 'group_link', 'start_link' ), true ) ) echo '<button type="button" class="button-link" data-mmec-token="{' . esc_attr( $t ) . '}">{' . esc_html( $t ) . '}</button> ';
        echo '</p>';
        echo '<div class="mmec-two"><label>Button label' . $in( 'content[button_label]', $c['button_label'], 'maxlength="40" data-mmec-c' ) . '</label><label>Button link <small>{group_link}, {start_link} (optionally + &amp;pop=pro-exclude) or a URL</small>' . $in( 'content[button_url]', $c['button_url'], 'data-mmec-c' ) . '</label></div>';
        echo '<fieldset class="mmec-blocks"><legend>Extra blocks</legend><label><input type="checkbox" name="content[ideas]" value="1" data-mmec-c' . checked( ! empty( $c['ideas'] ), true, false ) . '> Gift ideas within their group budget</label><label><input type="checkbox" name="content[wish]" value="1" data-mmec-c' . checked( ! empty( $c['wish'] ), true, false ) . '> Their match&rsquo;s wishlist shop buttons (members of shuffled groups)</label><label><input type="checkbox" name="content[card]" value="1" data-mmec-c' . checked( ! empty( $c['card'] ), true, false ) . '> Group card (name, date, budget)</label></fieldset>';
        echo '</div><aside class="mmec-ai" data-mmec-ai><h3>Write with AI</h3>';
        if ( EmailAI::enabled() ) {
            echo '<label>What&rsquo;s the email for?<textarea rows="4" data-mmec-brief placeholder="e.g. Remind members the 12.12 sale starts at midnight and suggest gifts within their budget"></textarea></label>';
            echo '<div class="mmec-two"><label>Tone<select data-mmec-tone><option value="friendly">Friendly</option><option value="fun">Fun</option><option value="urgent">A little urgent</option></select></label><label>Language<select data-mmec-lang><option>English</option><option>Taglish</option><option>Filipino</option></select></label></div>';
            echo '<button type="button" class="button button-primary" data-mmec-ai-go="write">Write it</button><div class="mmec-ai__more"><span>Rewrite:</span> <button type="button" class="button-link" data-mmec-ai-go="shorter">Shorter</button> <button type="button" class="button-link" data-mmec-ai-go="fun">More fun</button> <button type="button" class="button-link" data-mmec-ai-go="taglish">Taglish</button> <button type="button" class="button-link" data-mmec-ai-go="grammar">Fix grammar</button></div>';
            echo '<div class="mmec-ai__out" data-mmec-ai-out></div>';
        } else {
            echo '<p class="mmi-muted">Add <code>GEMINI_API_KEY</code> (or <code>GEMINI_EMAIL_KEY</code>) to wp-config.php to write with Gemini.</p>';
        }
        echo '</aside></div></section>';

        // Step 3: preview & test
        echo '<section class="mmi-panel mmec-step" data-step="3"><h3>Preview &amp; test</h3><div class="mmec-prevbar"><span>Preview as <select data-mmec-as><option value="">First recipient</option></select></span> <span class="mmec-seg"><button type="button" class="is-on" data-mmec-w="640">Desktop</button><button type="button" data-mmec-w="390">Phone</button></span></div>';
        echo '<div class="mmec-warn" data-mmec-warn></div><div class="mmec-inbox"><b data-mmec-psubj></b><span data-mmec-ppre></span></div><iframe class="mmec-frame" title="Email preview" sandbox="" data-mmec-frame></iframe>';
        echo '<div class="mmec-test"><label>Also send the test to <input type="email" name="extra" placeholder="optional, e.g. a teammate"></label> <button type="submit" name="op" value="test" class="button button-primary"' . ( $locked ? ' disabled' : '' ) . '>Send test to ' . esc_html( wp_get_current_user()->user_email ) . '</button> <span class="mmec-tested" data-mmec-tested></span></div></section>';

        // Step 4: schedule
        $at = $d['send_at'] ? wp_date( 'Y-m-d\TH:i', $d['send_at'] ) : wp_date( 'Y-m-d\TH:i', time() + HOUR_IN_SECONDS );
        echo '<section class="mmi-panel mmec-step" data-step="4"><h3>Schedule</h3><div class="mmec-grid">';
        echo '<label>Start at (Manila)<input type="datetime-local" name="sched[at]" value="' . esc_attr( $at ) . '"></label>';
        echo '<label>Send only from<select name="sched[from]">'; for ( $h = 0; $h < 24; $h++ ) echo '<option value="' . $h . '"' . selected( (int) $d['window'][0], $h, false ) . '>' . esc_html( wp_date( 'g A', strtotime( 'today ' . $h . ':00' ) ) ) . '</option>'; echo '</select></label>';
        echo '<label>until<select name="sched[to]">'; for ( $h = 1; $h <= 24; $h++ ) echo '<option value="' . $h . '"' . selected( (int) $d['window'][1], $h, false ) . '>' . ( $h === 24 ? 'midnight' : esc_html( wp_date( 'g A', strtotime( 'today ' . $h . ':00' ) ) ) ) . '</option>'; echo '</select></label>';
        echo '<label>Emails per 2 minutes<input type="number" min="10" max="500" name="sched[rate]" value="' . (int) $d['rate'] . '"></label></div>';
        echo '<p class="mmi-muted">Outside the window, sending waits and resumes automatically. About ' . number_format( (int) $d['rate'] * 30 ) . ' emails an hour. Each person gets it once; unsubscribes, bounces and the frequency cap are checked again right before each email.</p>';
        echo '<p class="mmi-muted"><strong>Bounce protection:</strong> Gmail, Yahoo, Outlook and iCloud addresses go first, company and school emails last. Each group starts with a small test batch and waits 30 minutes for bounce reports. The campaign pauses itself if hard bounces pass ' . EmailCenter::BOUNCE_STOP . '% or spam complaints pass ' . EmailCenter::SPAM_STOP . '%, and all campaigns pause if the site&rsquo;s bounce rate today passes ' . EmailCenter::SITE_STOP . '%. A company domain with ' . EmailCenter::DOMAIN_STOP . ' bounces is skipped for the rest of the campaign. You get an email when it pauses.</p>';
        echo '<p class="mmec-sched-acts">';
        if ( ! $locked ) {
            echo '<button type="submit" name="op" value="schedule" class="button button-primary" data-mmec-schedule onclick="return confirm(\'Schedule this campaign? It sends to everyone in the audience at the chosen time.\');">Schedule</button> ';
            echo '<button type="submit" name="op" value="schedule" class="button" onclick="this.form.now.value=1;return confirm(\'Start sending now (inside the send window)?\');">Send now</button><input type="hidden" name="now" value="">';
            if ( $st === 'scheduled' ) echo ' <button type="submit" name="op" value="unschedule" class="button-link">Back to draft</button>';
        }
        echo '</p></section>';

        echo '<div class="mmec-foot"><button type="button" class="button" data-mmec-prev>&larr; Back</button> <button type="submit" name="op" value="save" class="button"' . ( $locked ? ' disabled' : '' ) . '>Save draft</button> <button type="button" class="button button-primary" data-mmec-next>Next &rarr;</button></div>';
        echo '</form>';

        if ( ! empty( $d['log'] ) ) {
            echo '<details class="mmec-log"><summary>History</summary><ul>';
            foreach ( $d['log'] as $l ) echo '<li>' . esc_html( wp_date( 'M j, g:i A', (int) $l[0] ) ) . ' · ' . esc_html( $l[1] ) . ( $l[2] ? ' · ' . esc_html( $l[2] ) : '' ) . '</li>';
            echo '</ul></details>';
        }
    }

    /** Automatic (cron) emails: label, who, hook (next run), job key (last run). */
    public static function jobs() {
        $j = get_option( 'mm_email_jobs', array() );
        $j = is_array( $j ) ? $j : array();
        $sale = '';
        if ( class_exists( 'SaleDays' ) ) {
            foreach ( SaleDays::sales() as $s ) {
                $y = (int) wp_date( 'Y' );
                foreach ( array( $y, $y + 1 ) as $yy ) {
                    $t = strtotime( $yy . '-' . $s['md'] . ' 18:00:00 ' . wp_timezone_string() ) - DAY_IN_SECONDS;
                    if ( $t > time() - 5 * HOUR_IN_SECONDS && ( $sale === '' || $t < $sale ) ) $sale = $t;
                }
            }
        }
        $list = array(
            'reminder'  => array( 'Exchange reminders (daily)', '3 days before the exchange: members of shuffled groups (match, wishlist, ideas). 2–6 days before: organizers of unshuffled groups ("Ready to shuffle?")', 'mm_exchange_reminders' ),
            'autodraw'  => array( 'Auto-shuffle (PRO)', 'Members when a scheduled shuffle runs', 'mm_auto_draw' ),
            'sale'      => array( 'Sale-eve gift email (11.11, 12.12)', 'Members of upcoming exchanges, 6 pm the day before; PRO organizers get a 9 am heads-up', 'mm_sale_batch' ),
            'drops'     => array( 'Weekly Drops theme (PRO)', 'Members of groups using Weekly Drops', 'mm_theme_emails' ),
            'registry'  => array( 'Registry summaries + guest reminders', 'List owners (daily, when new gifts are reserved); guests 3 days before', 'mm_wl_daily' ),
            'adsweekly' => array( 'Advertiser weekly results', 'Advertisers with live ads, Mondays', 'mm_ads_weekly' ),
            'insights'  => array( 'Manito Insights weekly', 'You, Mondays', 'mm_insights_weekly' ),
        );
        $out = array();
        foreach ( $list as $k => $l ) {
            $next = $k === 'sale' ? $sale : (int) wp_next_scheduled( $l[2] );
            $out[ $k ] = array( 'label' => $l[0], 'who' => $l[1], 'next' => $next ? (int) $next : 0, 'last' => (int) ( $j[ $k ]['last'] ?? 0 ), 'sent' => (int) ( $j[ $k ]['last_sent_n'] ?? 0 ), 'last_sent' => (int) ( $j[ $k ]['last_sent'] ?? 0 ) );
        }

        return $out;
    }

    protected static function automaticScreen() {
        echo '<div class="mmi-panel"><h3>Automatic emails</h3><p class="mmi-muted">These send on their own (WP-Cron). Campaigns you create are on the Campaigns tab.</p><table class="mmi-table"><thead><tr><th>Email</th><th>Who gets it</th><th>Next run</th><th>Last run</th><th>Last batch</th></tr></thead><tbody>';
        foreach ( self::jobs() as $k => $j ) {
            echo '<tr><td><strong>' . esc_html( $j['label'] ) . '</strong>';
            if ( $k === 'sale' ) {
                $on = get_option( 'mm_sale_mail', '1' ) !== '0';
                echo '<br>' . ( $on ? '<span class="mmec-chip is-sending">On</span>' : '<span class="mmec-chip is-cancelled">Off</span>' ) . ' <small><a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'mm_ec_sale_toggle' ), admin_url( 'admin-post.php' ) ), 'mm_ec_sale' ) ) . '">' . ( $on ? 'Turn off' : 'Turn on' ) . '</a></small>';
            }
            echo '</td><td>' . esc_html( $j['who'] ) . '</td><td>' . ( $j['next'] ? esc_html( wp_date( 'D, M j · g:i A', $j['next'] ) ) : '–' ) . '</td><td>' . ( $j['last'] ? esc_html( human_time_diff( $j['last'] ) ) . ' ago' : '<span class="mmi-muted">not reported yet</span>' ) . '</td><td>' . ( $j['last_sent'] ? number_format( $j['sent'] ) . ' sent · ' . esc_html( wp_date( 'M j', $j['last_sent'] ) ) : '–' ) . '</td></tr>';
        }
        echo '<tr><td><strong>Season email (organizers)</strong></td><td>Last season&rsquo;s organizers, sent when you press Send</td><td>Manual</td><td colspan="2"><a href="' . esc_url( admin_url( 'admin.php?page=mm-season-email' ) ) . '">Open Season email</a></td></tr>';
        echo '</tbody></table></div>';
    }

    protected static function deliverScreen() {
        global $wpdb;
        list( $s7, $b7, $c7 ) = self::rates( 7 );
        list( $s30, $b30, $c30, $t30 ) = self::rates( 30 );
        echo '<div class="mmi-cards">' . self::card( 'Sent (7 days)', number_format( $s7 ), 'bounce ' . $b7 . '% · complaints ' . $c7 . '%' ) . self::card( 'Sent (30 days)', number_format( $s30 ), 'failed ' . number_format( $t30['failed'] ?? 0 ) . ' · skipped ' . number_format( $t30['suppressed'] ?? 0 ) )
            . self::card( 'Bounce rate (30 days)', $b30 . '%', 'SES warns at 5%; keep under 2%', $b30 >= 5 ? 'is-bad' : ( $b30 >= 2 ? 'is-warn' : '' ) ) . self::card( 'Complaint rate (30 days)', $c30 . '%', 'SES warns at 0.1%', $c30 >= 0.1 ? 'is-bad' : ( $c30 >= 0.05 ? 'is-warn' : '' ) ) . '</div>';

        $topic = (string) get_option( 'mm_ses_topic', '' );
        echo '<div class="mmi-panel"><h3>Amazon SES feedback</h3>';
        if ( $topic !== '' ) echo '<p><span class="mmec-chip is-sending">Connected</span> Bounces and complaints arrive from <code>' . esc_html( $topic ) . '</code>.</p>';
        else echo '<p><span class="mmec-chip is-paused">Not set up</span> Until it is, the site can&rsquo;t learn which addresses bounced. Steps: (1) AWS console &rsaquo; SNS &rsaquo; Create topic (Standard, e.g. <code>manito-ses-feedback</code>). (2) Create subscription: protocol HTTPS, endpoint <code>' . esc_html( rest_url( 'mm/v1/ses-events' ) ) . '</code> (it confirms itself). (3) SES &rsaquo; Identities &rsaquo; manitomanita.com &rsaquo; Notifications &rsaquo; Feedback notifications: set Bounce and Complaint to that topic.</p>';
        echo '</div>';

        $s = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
        $where = '';
        if ( $s !== '' ) $where = is_email( $s ) ? $wpdb->prepare( ' WHERE inbox = %s', EmailDeliver::hash( $s ) ) : $wpdb->prepare( ' WHERE masked LIKE %s OR reason = %s', '%' . $wpdb->esc_like( $s ) . '%', $s );
        $rows = $wpdb->get_results( 'SELECT * FROM ' . EmailDeliver::suppressTable() . $where . ' ORDER BY t DESC LIMIT 100' );
        $byReason = $wpdb->get_results( 'SELECT reason, COUNT(*) n FROM ' . EmailDeliver::suppressTable() . ' GROUP BY reason' );
        echo '<div class="mmi-panel"><h3>Suppression list</h3><p class="mmi-muted">';
        $parts = array(); foreach ( $byReason as $r ) $parts[] = number_format( $r->n ) . ' ' . esc_html( array( 'promo_unsub' => 'unsubscribed from promos', 'bounce' => 'hard bounces', 'complaint' => 'spam complaints', 'manual' => 'blocked', 'soft' => 'with recent soft bounces (still emailed; the 3rd in 30 days blocks)' )[ $r->reason ] ?? $r->reason );
        echo $parts ? implode( ' · ', $parts ) : 'Empty so far.';
        echo '</p><form method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="deliverability"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="Full email address"> <button class="button">Search</button></form>';
        if ( $rows ) {
            echo '<table class="mmi-table"><thead><tr><th>Address</th><th>Why</th><th>Source</th><th>When</th><th></th></tr></thead><tbody>';
            foreach ( $rows as $r ) echo '<tr><td>' . esc_html( $r->masked ) . '</td><td>' . esc_html( $r->reason ) . '</td><td>' . esc_html( $r->source ) . '</td><td>' . esc_html( wp_date( 'M j, Y', strtotime( $r->t ) ) ) . '</td><td>' . self::form( 'unsuppress', 'Remove', 'button-link', array( 'hash' => $r->inbox ), 'Allow emails to this address again?' ) . '</td></tr>';
            echo '</tbody></table>';
        }
        echo '</div>';

        $alerts = array_filter( (array) get_option( ErrorAlerts::LOG, array() ), function ( $a ) { return in_array( $a['kind'] ?? '', array( 'email' ), true ); } );
        echo '<div class="mmi-panel"><h3>Recent send errors</h3>';
        if ( ! $alerts ) echo '<p class="mmi-empty">None.</p>';
        else { echo '<ul>'; foreach ( array_slice( $alerts, 0, 10 ) as $a ) echo '<li>' . esc_html( wp_date( 'M j, g:i A', (int) $a['t'] ) ) . ' · ' . esc_html( $a['msg'] ) . '</li>'; echo '</ul>'; }
        echo '</div>';
    }
}

EmailCenterAdmin::init();
add_action( 'admin_post_mm_ec_sale_toggle', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'mm_ec_sale' );
    update_option( 'mm_sale_mail', get_option( 'mm_sale_mail', '1' ) === '0' ? '1' : '0', false );
    wp_safe_redirect( EmailCenterAdmin::url( array( 'tab' => 'automatic' ) ) );
    exit;
} );
