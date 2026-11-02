<?php
/**
 * * Themes Class. "Weekly Drops" (optional PRO activity): weekly gift themes for a group, a "this week" banner on
 * the dashboard, a weekly theme email with matching gift ideas, and an end-of-exchange reveal of who
 * gave to whom (shown only on the private group page).
 *
 * Group meta:
 *   _mm_themes        array of array( 't' => title, 'd' => 'Y-m-d' start date, 'k' => shop keyword )
 *   _mm_theme_budget  weekly gift budget in pesos
 *   _mm_theme_sent    array of start dates already emailed
 *   _mm_revealed      unix time the organizer revealed the matches (empty = hidden)
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class Themes
 */
class Themes {
    const META        = '_mm_themes';
    const BUDGET_META = '_mm_theme_budget';
    const SENT_META   = '_mm_theme_sent';
    const REVEAL_META = '_mm_revealed';
    const MAX_THEMES  = 8;
    const HOOK        = 'mm_theme_emails';

    protected static $hooked = false;

    /**
     * Presets: title => shop keyword used for the gift idea links.
     */
    public static function presets() {
        return array(
            'Something sweet'                        => 'chocolate gift',
            'Something soft'                         => 'plush toy',
            'Something red'                          => 'red mug',
            'Something round'                        => 'stress ball',
            'Something useful'                       => 'desk organizer',
            'Something you can drink'                => 'coffee gift set',
            'Something hot'                          => 'mug warmer',
            'Something cold'                         => 'insulated tumbler',
            'Something that smells good'             => 'scented candle',
            'Something nostalgic'                    => 'retro candy',
            'Something for self-care'                => 'sheet mask set',
            'Something for their desk'               => 'desk plant',
            'Something that grows'                   => 'succulent plant',
            'Something local or Filipino-made'       => 'pasalubong',
            'Something that starts with your initial' => 'personalized keychain',
            'Something that glows'                   => 'led night light',
            'Something green'                        => 'green tumbler',
            'Something blue'                         => 'blue mug',
            'Something pink and fluffy'              => 'pink plush',
            'Something you can wear on your head'    => 'beanie',
            'Something handmade'                     => 'handmade gift',
            'Something to read'                      => 'book',
            'Something spicy'                        => 'chili garlic oil',
            'Something crunchy'                      => 'snack chips box',
            'Something for the commute'              => 'neck pillow',
            'Something for coffee breaks'            => 'coffee mug set',
            'A letter or note'                       => 'greeting card',
        );
    }

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( self::HOOK, array( __CLASS__, 'sendThemeEmails' ) );
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_event( time() + 600, 'hourly', self::HOOK );
    }

    /* ------------------------------------------------------------- Data */

    public static function get( $gid ) {
        $rows = get_post_meta( (int) $gid, self::META, true );
        $rows = is_array( $rows ) ? $rows : array();
        usort( $rows, function ( $a, $b ) { return strcmp( $a['d'], $b['d'] ); } );
        return $rows;
    }

    public static function budget( $gid ) {
        $b = (int) get_post_meta( (int) $gid, self::BUDGET_META, true );
        return $b > 0 ? $b : 150;
    }

    public static function today() {
        return wp_date( 'Y-m-d' );
    }

    /**
     * array( 'current' => row|null (latest theme already started), 'next' => row|null ).
     */
    public static function now( $gid ) {
        $current = null; $next = null; $today = self::today();
        foreach ( self::get( $gid ) as $row ) {
            if ( $row['d'] <= $today ) $current = $row;
            elseif ( ! $next ) $next = $row;
        }
        return array( 'current' => $current, 'next' => $next );
    }

    public static function isRevealed( $gid ) {
        return (bool) get_post_meta( (int) $gid, self::REVEAL_META, true );
    }

    protected static function keywordFor( $title ) {
        $presets = self::presets();
        if ( isset( $presets[ $title ] ) ) return $presets[ $title ];

        // Custom theme: drop the "Something" wording and search for the rest.
        $k = trim( preg_replace( '/^(something|a|an)\s+(that\s+is\s+|that\s+|you\s+can\s+|for\s+)?/i', '', $title ) );
        return $k !== '' ? $k . ' gift' : 'gift';
    }

    /* ------------------------------------------------------------- REST */

    public static function routes() {
        foreach ( array( 'themes' => 'handleThemes', 'reveal' => 'handleReveal' ) as $route => $cb ) {
            register_rest_route( 'custom-webhook/v1', '/' . $route, array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, $cb ),
                'permission_callback' => array( __CLASS__, 'canUse' ),
            ) );
        }
    }

    public static function canUse( $request ) {
        $gid = (int) $request->get_param( 'group_id' );
        if ( ! Groups::isModerator( $gid ) ) return new WP_Error( 'mm_forbidden', 'Please enter the moderator password again.', array( 'status' => 403 ) );
        if ( ! get_field( 'pro', $gid ) ) return new WP_Error( 'mm_not_pro', 'This is a PRO feature.', array( 'status' => 403 ) );
        return true;
    }

    /**
     * op: get | save (themes: [{t, d}], budget).
     */
    public static function handleThemes( $request ) {
        $gid = (int) $request->get_param( 'group_id' );
        $error = '';

        if ( $request->get_param( 'op' ) === 'save' ) {
            $rows = array();
            foreach ( (array) $request->get_param( 'themes' ) as $r ) {
                $t = trim( sanitize_text_field( (string) ( $r['t'] ?? '' ) ) );
                $d = (string) ( $r['d'] ?? '' );
                if ( $t === '' && $d === '' ) continue;
                if ( $t === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { $error = 'Each theme needs a name and a start date.'; break; }
                $rows[] = array( 't' => mb_substr( $t, 0, 60 ), 'd' => $d, 'k' => self::keywordFor( $t ) );
            }

            $dates = array_column( $rows, 'd' );
            if ( ! $error && count( $rows ) > self::MAX_THEMES ) $error = 'Up to ' . self::MAX_THEMES . ' themes per group.';
            if ( ! $error && count( $dates ) !== count( array_unique( $dates ) ) ) $error = 'Two themes start on the same date. Give each week its own date.';

            if ( ! $error ) {
                update_post_meta( $gid, self::META, $rows );
                $budget = max( 0, min( 5000, (int) $request->get_param( 'budget' ) ) );
                update_post_meta( $gid, self::BUDGET_META, $budget ?: 150 );
            }
        }

        $now = self::now( $gid );

        return new WP_REST_Response( array(
            'success' => $error === '',
            'message' => $error,
            'themes'  => array_map( function ( $r ) { return array( 't' => $r['t'], 'd' => $r['d'] ); }, self::get( $gid ) ),
            'budget'  => self::budget( $gid ),
            'presets' => array_keys( self::presets() ),
            'current' => $now['current'] ? $now['current']['t'] : '',
            'today'   => self::today(),
        ), 200 );
    }

    /**
     * op: on | off. Reveal shows every pair on the private group page.
     */
    public static function handleReveal( $request ) {
        $gid = (int) $request->get_param( 'group_id' );
        if ( ! get_field( 'matched', $gid ) ) return new WP_REST_Response( array( 'success' => false, 'message' => 'Shuffle the group first.' ), 200 );

        if ( $request->get_param( 'op' ) === 'off' ) delete_post_meta( $gid, self::REVEAL_META );
        else update_post_meta( $gid, self::REVEAL_META, time() );

        return new WP_REST_Response( array( 'success' => true, 'revealed' => self::isRevealed( $gid ) ), 200 );
    }

    /* ------------------------------------------------------------ Emails */

    /**
     * Hourly: on a theme's start date (or up to 2 days late), email members of PRO, shuffled groups.
     */
    public static function sendThemeEmails() {
        $groups = get_posts( array(
            'post_type'      => 'groups',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => self::META, 'compare' => 'EXISTS' ), array( 'key' => 'matched', 'value' => '1' ) ),
        ) );

        $today = self::today();
        $oldest = wp_date( 'Y-m-d', strtotime( '-2 days' ) );

        foreach ( $groups as $gid ) {
            if ( ! get_field( 'pro', $gid ) ) continue;

            $sent = (array) get_post_meta( $gid, self::SENT_META, true );
            foreach ( self::get( $gid ) as $row ) {
                if ( $row['d'] > $today || $row['d'] < $oldest || in_array( $row['d'], $sent, true ) ) continue;

                $sent[] = $row['d'];
                update_post_meta( $gid, self::SENT_META, $sent ); // mark first: one email per theme
                self::emailTheme( $gid, $row );
            }
        }
    }

    protected static function emailTheme( $gid, $row ) {
        $name = (string) get_field( 'group_name', $gid );
        $budget = self::budget( $gid );
        $ideas = Affiliate::giftIdeasHtml( $gid, 'email-theme', true,
            array( array( 'label' => 'Ideas for "' . $row['t'] . '"', 'keyword' => $row['k'] ) ),
            'Fits this week&rsquo;s theme', $budget );

        $next = null;
        foreach ( self::get( $gid ) as $r ) { if ( $r['d'] > $row['d'] ) { $next = $r; break; } }

        foreach ( get_posts( array( 'post_type' => 'users', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'groups', 'value' => (int) $gid ) ) ) ) as $uid ) {
            if ( ! EmailCheck::isConfirmed( $uid ) || Emails::optedOut( $uid ) ) continue;

            $pair = (int) get_field( 'pair', $uid, false );
            $pairName = $pair ? trim( (string) get_field( 'screen_name', $pair ) ) : '';

            Emails::send( (string) get_field( 'email', $uid ), 'This week&rsquo;s drop: ' . $row['t'], array(
                'preheader'   => 'Your Weekly Drop for ' . $name . ($next ? '. Next up: ' . $next['t'] : '') . '.',
                'eyebrow'     => 'Weekly Drops',
                'title'       => 'This week&rsquo;s drop: ' . $row['t'],
                'highlight'   => $pairName !== '' ? array( 'Your gift goes to', $pairName ) : array(),
                'body'        => array(
                    '<p>Leave your Manito or Manita a small gift that fits this week&rsquo;s theme, around <strong>&#8369;' . number_format( $budget ) . '</strong>. Keep it secret: leave it on their desk early, hand it over through a friend, or label it with their screen name only.</p>',
                    $next ? '<p>Next theme: <strong>' . esc_html( $next['t'] ) . '</strong>, starting ' . esc_html( wp_date( 'F j', strtotime( $next['d'] ) ) ) . '.</p>' : '<p>This is the last theme. See you at the exchange!</p>',
                ),
                'button'      => array( 'See their wishlist', Emails::groupLink( $gid ) ),
                'raw'         => $ideas,
                'unsubscribe' => $uid,
                'footer'      => 'You&rsquo;re getting this because ' . esc_html( $name ) . ' is doing Weekly Drops.',
            ) );
        }
    }

    /* ---------------------------------------------------------- Dashboard */

    /**
     * "This week" banner + timeline for a PRO group with themes.
     */
    public static function bannerHtml( $gid ) {
        $rows = self::get( $gid );
        if ( ! $rows || ! get_field( 'pro', $gid ) ) return '';

        $now = self::now( $gid );
        $today = self::today();

        $html = '<div class="mm-themes">';
        if ( $now['current'] ) {
            $html .= '<div class="mm-themes__now"><span class="mm-themes__label">This week&rsquo;s drop</span><strong>' . esc_html( $now['current']['t'] ) . '</strong>'
                . ( $now['next'] ? '<small>Next: ' . esc_html( $now['next']['t'] ) . ' &middot; ' . esc_html( wp_date( 'M j', strtotime( $now['next']['d'] ) ) ) . '</small>' : '<small>Last theme before the exchange</small>' ) . '</div>';
        } else {
            $html .= '<div class="mm-themes__now"><span class="mm-themes__label">First drop ' . esc_html( wp_date( 'M j', strtotime( $rows[0]['d'] ) ) ) . '</span><strong>' . esc_html( $rows[0]['t'] ) . '</strong><small>Suggested budget per weekly gift: &#8369;' . number_format( self::budget( $gid ) ) . '</small></div>';
        }

        $html .= '<ol class="mm-themes__line">';
        foreach ( $rows as $r ) {
            $state = ( $now['current'] && $r['d'] === $now['current']['d'] ) ? 'is-now' : ( $r['d'] < $today ? 'is-done' : '' );
            $html .= '<li class="' . $state . '"><span>' . esc_html( wp_date( 'M j', strtotime( $r['d'] ) ) ) . '</span>' . esc_html( $r['t'] ) . '</li>';
        }
        $html .= '</ol>';
        $html .= '<details class="mm-themes__how"><summary>How Weekly Drops work</summary><ol>'
            . '<li>Each week a new theme drops, like <em>something sweet</em>. Everyone gets an email.</li>'
            . '<li>Leave a small gift for your Manito or Manita that fits the theme (around &#8369;' . number_format( self::budget( $gid ) ) . ').</li>'
            . '<li>Keep it secret: leave it early, use a friend, or label it with their screen name only.</li>'
            . '<li>On exchange day, give your main gift and find out who your Secret Santa was.</li>'
            . '</ol></details></div>';

        return $html;
    }

    /**
     * After the organizer reveals: every pair, shown on the private group page only.
     */
    public static function revealHtml( $gid ) {
        if ( ! self::isRevealed( $gid ) || ! get_field( 'matched', $gid ) ) return '';

        $rows = '';
        foreach ( get_posts( array( 'post_type' => 'users', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'groups', 'value' => (int) $gid ) ) ) ) as $uid ) {
            $pair = (int) get_field( 'pair', $uid, false );
            if ( ! $pair ) continue;
            $rows .= '<li><span>' . esc_html( (string) get_field( 'screen_name', $uid ) ) . '</span><i class="fas fa-gift" aria-hidden="true"></i><span>' . esc_html( (string) get_field( 'screen_name', $pair ) ) . '</span></li>';
        }

        if ( $rows === '' ) return '';

        return '<section class="mm-reveal" aria-label="Reveal">'
            . '<div class="mm-reveal__head"><span class="mm-themes__label">The big reveal</span><h3>Here&rsquo;s who gave to whom</h3><p>Thanks for playing! Each person on the left was the Secret Santa of the person on the right.</p></div>'
            . '<ul class="mm-reveal__list">' . $rows . '</ul>'
            . '<p class="mm-reveal__cta">Had fun? <a href="' . esc_url( home_url( '/' ) ) . '" data-mm-track="create_group_click" data-mm-slot="reveal">Make your own group</a> for your next exchange. It&rsquo;s free.</p>'
            . '</section>';
    }
}

Themes::init();
