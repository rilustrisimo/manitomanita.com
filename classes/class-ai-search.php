<?php
/**
 * AISearch Class. How the site does in AI search (ChatGPT, Perplexity, Gemini, Copilot, Claude...):
 *
 * 1. Visits from AI assistants: GA4 sessions whose source is an AI assistant (people who clicked a link to us
 *    in an AI answer), by assistant, landing page and day. Shown in Insights > Traffic and in the export.
 * 2. AI crawlers: requests from AI bots (user agent), counted per day, bot and page in their own small table
 *    (wp_mm_ai_hits). Only requests that reach WordPress are counted: a page served from the Hostinger/LiteSpeed
 *    cache (5 min) never runs PHP, so real numbers are higher. Paths only (no query strings: group links carry passwords).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class AISearch {
    const DB_VERSION = 1;
    const KEEP_DAYS  = 400;
    /** GA4 session sources that are AI assistants (partial, case-insensitive). */
    const SOURCES = 'chatgpt|openai|perplexity|gemini\.google|bard\.google|copilot|claude\.ai|anthropic|deepseek|meta\.ai|grok|you\.com|phind|poe\.com|mistral';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'install' ), 0 );
        add_action( 'init', array( __CLASS__, 'track' ), 0 ); // before SEO::llms (init 1) answers /llms.txt and exits
        add_action( 'mm_insights_daily', array( __CLASS__, 'cleanup' ) );
    }

    public static function table() {
        global $wpdb;

        return $wpdb->prefix . 'mm_ai_hits';
    }

    public static function install() {
        if ( (int) get_option( 'mm_ai_hits_db' ) >= self::DB_VERSION ) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( 'CREATE TABLE ' . self::table() . " (
            d date NOT NULL,
            bot varchar(40) NOT NULL,
            path varchar(150) NOT NULL,
            n int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (d,bot,path),
            KEY d (d)
        ) " . $wpdb->get_charset_collate() . ';' );
        update_option( 'mm_ai_hits_db', self::DB_VERSION, false );
    }

    public static function cleanup() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE d < %s', wp_date( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) );
    }

    /**
     * AI bots by user agent: token => array( name, kind ). Kind: answer = fetched because a person asked the
     * assistant just now; search = building the assistant's search index; training = collecting pages for models.
     * More specific tokens first (Claude-User before ClaudeBot).
     */
    public static function bots() {
        return array(
            'ChatGPT-User'         => array( 'ChatGPT (a person asked)', 'answer' ),
            'OAI-SearchBot'        => array( 'ChatGPT search', 'search' ),
            'GPTBot'               => array( 'OpenAI GPTBot', 'training' ),
            'Perplexity-User'      => array( 'Perplexity (a person asked)', 'answer' ),
            'PerplexityBot'        => array( 'Perplexity search', 'search' ),
            'Claude-User'          => array( 'Claude (a person asked)', 'answer' ),
            'Claude-SearchBot'     => array( 'Claude search', 'search' ),
            'ClaudeBot'            => array( 'Anthropic ClaudeBot', 'training' ),
            'MistralAI-User'       => array( 'Mistral (a person asked)', 'answer' ),
            'DuckAssistBot'        => array( 'DuckDuckGo AI answers', 'search' ),
            'meta-externalfetcher' => array( 'Meta AI (a person asked)', 'answer' ),
            'meta-externalagent'   => array( 'Meta AI crawler', 'training' ),
            'Applebot'             => array( 'Applebot (Siri, Apple Intelligence)', 'search' ),
            'Amazonbot'            => array( 'Amazonbot (Alexa)', 'search' ),
            'Bytespider'           => array( 'ByteDance Bytespider', 'training' ),
            'CCBot'                => array( 'Common Crawl', 'training' ),
        );
    }

    /** Bot token for a user agent, or ''. */
    public static function botOf( $ua ) {
        $ua = (string) $ua;
        if ( $ua === '' ) return '';
        foreach ( self::bots() as $token => $info ) {
            if ( stripos( $ua, $token ) !== false ) return $token;
        }

        return '';
    }

    /** Page path without the query string; WordPress internals grouped. */
    public static function pathOf( $uri ) {
        $path = (string) parse_url( (string) $uri, PHP_URL_PATH );
        if ( $path === '' ) $path = '/';
        if ( preg_match( '~^/(wp-admin|wp-json|wp-content|wp-includes|wp-login|xmlrpc)~', $path, $m ) ) return '/' . $m[1] . '/*';

        return substr( strtolower( $path ), 0, 150 );
    }

    public static function track() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) return;
        $bot = self::botOf( $_SERVER['HTTP_USER_AGENT'] ?? '' );
        if ( ! $bot ) return;
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (d, bot, path, n) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE n = n + 1', wp_date( 'Y-m-d' ), $bot, self::pathOf( $_SERVER['REQUEST_URI'] ?? '/' ) ) );
    }

    /** Crawler visits in a period: totals, by bot, by kind, top pages, per day. */
    public static function crawlers( $from, $to ) {
        global $wpdb;
        $t = self::table();
        $bots = self::bots();
        $byBot = $byKind = $days = array();
        $total = 0;
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT bot, SUM(n) n FROM $t WHERE d BETWEEN %s AND %s GROUP BY bot ORDER BY n DESC", $from, $to ) ) as $r ) {
            $info = $bots[ $r->bot ] ?? array( $r->bot, 'training' );
            $byBot[] = array( 'name' => $info[0], 'value' => (int) $r->n, 'kind' => $info[1] );
            $byKind[ $info[1] ] = ( $byKind[ $info[1] ] ?? 0 ) + (int) $r->n;
            $total += (int) $r->n;
        }
        $pages = array();
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT path, SUM(n) n, SUM(CASE WHEN bot IN ('ChatGPT-User','Perplexity-User','Claude-User','MistralAI-User','meta-externalfetcher') THEN n ELSE 0 END) asked FROM $t WHERE d BETWEEN %s AND %s GROUP BY path ORDER BY n DESC LIMIT 15", $from, $to ) ) as $r ) {
            $pages[] = array( 'path' => $r->path, 'hits' => (int) $r->n, 'asked' => (int) $r->asked );
        }
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT d, SUM(n) n FROM $t WHERE d BETWEEN %s AND %s GROUP BY d", $from, $to ) ) as $r ) $days[ $r->d ] = (int) $r->n;
        $since = (string) $wpdb->get_var( "SELECT MIN(d) FROM $t" );
        $labels = $series = array();
        for ( $d = strtotime( $from ); $d <= strtotime( $to ); $d += DAY_IN_SECONDS ) {
            $k = gmdate( 'Y-m-d', $d );
            $labels[] = $k;
            $series[] = $days[ $k ] ?? 0;
        }
        $kinds = array( 'answer' => 'A person asked an AI (live fetch)', 'search' => 'AI search indexing', 'training' => 'AI training crawlers' );

        return array(
            'total'  => $total,
            'bots'   => $byBot,
            'kinds'  => array_map( function ( $k, $l ) use ( $byKind ) { return array( 'name' => $l, 'value' => $byKind[ $k ] ?? 0 ); }, array_keys( $kinds ), $kinds ),
            'pages'  => $pages,
            'trend'  => array( 'labels' => $labels, 'hits' => $series ),
            'since'  => $since,
        );
    }

    /** Readable assistant name for a GA4 session source. */
    public static function assistant( $source ) {
        $s = strtolower( (string) $source );
        $map = array( 'chatgpt' => 'ChatGPT', 'openai' => 'ChatGPT', 'perplexity' => 'Perplexity', 'gemini' => 'Gemini', 'bard' => 'Gemini', 'copilot' => 'Copilot', 'claude' => 'Claude', 'anthropic' => 'Claude', 'deepseek' => 'DeepSeek', 'meta.ai' => 'Meta AI', 'grok' => 'Grok', 'you.com' => 'You.com', 'phind' => 'Phind', 'poe.com' => 'Poe', 'mistral' => 'Mistral' );
        foreach ( $map as $k => $name ) {
            if ( strpos( $s, $k ) !== false ) return $name;
        }

        return $source;
    }

    /** Visits from AI assistants (GA4), this period vs the previous one. Null when Google isn't connected. */
    public static function referrals( $p ) {
        if ( ! class_exists( 'InsightsGoogle' ) || ! InsightsGoogle::configured() ) return null;
        $filter = array( 'dimensionFilter' => array( 'filter' => array( 'fieldName' => 'sessionSource', 'stringFilter' => array( 'matchType' => 'PARTIAL_REGEXP', 'value' => self::SOURCES, 'caseSensitive' => false ) ) ) );
        $range = function ( $a, $b ) { return array( array( 'startDate' => $a, 'endDate' => $b ) ); };
        $metrics = array( array( 'name' => 'sessions' ), array( 'name' => 'engagedSessions' ) );

        $src = InsightsGoogle::ga( array_merge( array( 'dateRanges' => $range( $p[0], $p[1] ), 'dimensions' => array( array( 'name' => 'sessionSource' ) ), 'metrics' => $metrics, 'limit' => 50 ), $filter ) );
        if ( ! empty( $src['error'] ) ) return array( 'error' => $src['error'] );
        $by = array();
        $now = $engaged = 0;
        foreach ( InsightsGoogle::rows( $src ) as $r ) {
            $name = self::assistant( $r['d'][0] );
            $by[ $name ] = ( $by[ $name ] ?? 0 ) + $r['m'][0];
            $now += $r['m'][0];
            $engaged += $r['m'][1];
        }
        arsort( $by );

        $prevRes = InsightsGoogle::ga( array_merge( array( 'dateRanges' => $range( $p[2], $p[3] ), 'metrics' => array( array( 'name' => 'sessions' ) ) ), $filter ) );
        $prev = 0;
        foreach ( InsightsGoogle::rows( $prevRes ) as $r ) $prev += $r['m'][0];

        $allRes = InsightsGoogle::ga( array( 'dateRanges' => $range( $p[0], $p[1] ), 'metrics' => array( array( 'name' => 'sessions' ) ) ) );
        $all = 0;
        foreach ( InsightsGoogle::rows( $allRes ) as $r ) $all += $r['m'][0];

        $land = InsightsGoogle::ga( array_merge( array( 'dateRanges' => $range( $p[0], $p[1] ), 'dimensions' => array( array( 'name' => 'landingPage' ), array( 'name' => 'sessionSource' ) ), 'metrics' => array( array( 'name' => 'sessions' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ), 'limit' => 15 ), $filter ) );
        $pages = array_map( function ( $r ) { return array( 'path' => $r['d'][0], 'assistant' => self::assistant( $r['d'][1] ), 'visits' => $r['m'][0] ); }, InsightsGoogle::rows( $land ) );

        $trendRes = InsightsGoogle::ga( array_merge( array( 'dateRanges' => $range( $p[0], $p[1] ), 'dimensions' => array( array( 'name' => 'date' ) ), 'metrics' => array( array( 'name' => 'sessions' ) ), 'orderBys' => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ), 'limit' => 400 ), $filter ) );
        $days = array();
        foreach ( InsightsGoogle::rows( $trendRes ) as $r ) $days[ substr( $r['d'][0], 0, 4 ) . '-' . substr( $r['d'][0], 4, 2 ) . '-' . substr( $r['d'][0], 6, 2 ) ] = $r['m'][0];
        $labels = $series = array();
        for ( $d = strtotime( $p[0] ); $d <= strtotime( $p[1] ); $d += DAY_IN_SECONDS ) {
            $k = gmdate( 'Y-m-d', $d );
            $labels[] = $k;
            $series[] = $days[ $k ] ?? 0;
        }

        return array(
            'visits'    => array( 'value' => $now, 'prev' => $prev, 'change' => Insights::change( $now, $prev ) ),
            'share'     => $all ? round( $now / $all * 100, 2 ) : 0,
            'engaged'   => $now ? round( $engaged / $now * 100, 1 ) : 0,
            'by'        => array_map( function ( $k, $v ) { return array( 'name' => $k, 'value' => $v ); }, array_keys( $by ), array_values( $by ) ),
            'pages'     => $pages,
            'trend'     => array( 'labels' => $labels, 'visits' => $series ),
        );
    }

    /** Everything for the Traffic tab. */
    public static function summary( $p ) {
        return array( 'referrals' => self::referrals( $p ), 'crawlers' => self::crawlers( $p[0], $p[1] ) );
    }

    /** Markdown section for the Insights export. */
    public static function exportMd( $p, $table, $n ) {
        $s = self::summary( $p );
        $md = array( '## 7b. AI search (ChatGPT, Perplexity, Gemini, Copilot, Claude...)' );
        $r = $s['referrals'];
        $md[] = '### Visits from AI assistants (GA4: people who clicked a link to us in an AI answer)';
        if ( $r === null ) {
            $md[] = "_Google not connected._\n";
        } elseif ( ! empty( $r['error'] ) ) {
            $md[] = '_Unavailable: ' . $r['error'] . "_\n";
        } else {
            $md[] = '- Visits: ' . $n( $r['visits']['value'] ) . ' (previous period ' . $n( $r['visits']['prev'] ) . '), ' . $r['share'] . '% of all visits, ' . $r['engaged'] . '% engaged.';
            $md[] = '';
            $md[] = $r['by'] ? $table( array( 'Assistant', 'Visits' ), array_map( function ( $x ) use ( $n ) { return array( $x['name'], $n( $x['value'] ) ); }, $r['by'] ) ) : "_None in this period._\n";
            if ( $r['pages'] ) $md[] = $table( array( 'Landing page', 'Assistant', 'Visits' ), array_map( function ( $x ) use ( $n ) { return array( preg_replace( '/([?&]pw=)[^&]*/', '$1***', $x['path'] ), $x['assistant'], $n( $x['visits'] ) ); }, $r['pages'] ) );
        }
        $c = $s['crawlers'];
        $md[] = '### AI crawlers (requests that reached WordPress; cached pages are not counted, so real numbers are higher' . ( $c['since'] ? '; counted since ' . $c['since'] : '' ) . ')';
        $md[] = '- Total requests: ' . $n( $c['total'] );
        $md[] = '';
        if ( $c['bots'] ) {
            $md[] = $table( array( 'Bot', 'Kind', 'Requests' ), array_map( function ( $x ) use ( $n ) { return array( $x['name'], $x['kind'], $n( $x['value'] ) ); }, $c['bots'] ) );
            $md[] = $table( array( 'Page', 'Requests', 'Of which a person asked an AI' ), array_map( function ( $x ) use ( $n ) { return array( $x['path'], $n( $x['hits'] ), $n( $x['asked'] ) ); }, $c['pages'] ) );
        } else {
            $md[] = "_None in this period._\n";
        }

        return implode( "\n", $md );
    }
}

AISearch::init();
