<?php
/**
 * * Landing Class. Helpers for pages using the "Landing" template (template-landing.php): generator
 * pages, gift guides, theme lists and legal pages. Content lives in WordPress (editor + ACF fields
 * from includes/acf-json/group_mm_landing.json); Yoast handles titles, descriptions, social tags,
 * breadcrumbs and the sitemap. This class adds the FAQ structured data and old-URL redirects.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   2.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class Landing
 */
class Landing {
    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'template_redirect', array( __CLASS__, 'redirects' ), -1 );
        add_action( 'wp_head', array( __CLASS__, 'faqSchema' ), 40 );
        add_action( 'wp_loaded', array( __CLASS__, 'seed' ) );
    }

    /**
     * Landing pages added after the first migration: each is created once (published, with its fields
     * and Yoast title/description). A page that already has the slug is left alone, so edits in
     * WP Admin are never overwritten.
     */
    public static function newPages() {
        return array(
            'online-bunutan' => array(
                'title'   => 'Online Bunutan for Christmas Party: Free Exchange Gift Draw | Manito Manita',
                'desc'    => 'Do your exchange gift bunutan online, free. Share one link on Messenger, everyone joins and adds a wishlist, then shuffle. No paper slips, no app, no sign-up.',
                'eyebrow' => 'Bunutan &middot; Exchange gift',
                'h1'      => 'Online bunutan <span>for your Christmas party</span>',
                'lead'    => 'No more paper slips in a bowl. Share one link on Messenger or Viber, everyone joins and adds a wishlist, and each person privately sees who they drew, on their phone.',
                'intro'   => 'How to do an online bunutan for exchange gift',
                'body'    => '<p>A <strong>bunutan</strong> (or <strong>bunot</strong>) is how most Filipino groups decide who gives a gift to whom at the Christmas party: everyone writes their name on a slip, draws one, and keeps it secret until the exchange. An <strong>online bunutan</strong> does the same draw without the bowl, so it also works for remote teammates, family abroad and barkadas who can&rsquo;t meet before the party.</p>'
                    . '<p>To do your bunutan online, create a free group with your party name, the exchange date and the gift budget. Share the group link in your Messenger or Viber group chat. Each person joins with their email, confirms it, and adds a wishlist so their giver knows what to buy. When everyone is in, the organizer shuffles once, and each person sees only their own match, with gift ideas within the budget.</p>'
                    . '<p>Nobody can draw their own name, nobody sees anyone else&rsquo;s match, and there&rsquo;s no app to install. It works for an office Christmas party, a family reunion, a barkada exchange gift or a class, from 3 people to over 100.</p>',
                'faq'     => array(
                    array( 'Is online bunutan free?', 'Yes, free forever with no limit on members. PRO is an optional &#8369;199 one-time add-on for organizers who want extra tools.' ),
                    array( 'Can we do the bunutan through Messenger?', 'Yes. Share the group link in your Messenger, Viber or Teams chat. Everyone joins from the link, and each person gets their match by email and on the group page.' ),
                    array( 'Do members need to download an app or sign up?', 'No. Members join from the link with their name and email. It works in any phone or computer browser.' ),
                    array( 'What if someone draws their own name?', 'That can&rsquo;t happen. The shuffle makes sure nobody gets themselves, and each person can only see their own match.' ),
                    array( 'Can couples or family members avoid drawing each other?', 'Yes, with PRO. The organizer adds exclusions before the shuffle, and those people never get each other.' ),
                    array( 'What is bunutan in English?', 'Bunutan means &ldquo;drawing lots&rdquo;. For a Christmas party it&rsquo;s the same as Secret Santa, Kris Kringle or Monito Monita.' ),
                ),
                'related' => array( 'monito-monita-generator', 'kris-kringle-generator-philippines', 'exchange-gift-ideas-500-pesos' ),
            ),
        ) + self::registryPages();
    }

    /** Slug of each occasion's registry guide (Registries::guideUrl links to them). */
    public static function registrySlugs() {
        return array(
            'wedding'        => 'wedding-gift-registry-philippines',
            'debut'          => 'debut-wishlist',
            'baptism'        => 'baptism-gift-registry',
            'baby-shower'    => 'baby-shower-registry',
            'first-birthday' => 'first-birthday-wishlist',
            'housewarming'   => 'housewarming-gift-list',
            'graduation'     => 'graduation-gift-ideas',
            'despedida'      => 'despedida-gift-ideas',
        );
    }

    /**
     * Registry guides (type "registry"): why a registry, gift ideas by budget (Label | keyword | max price),
     * FAQ, and "Create your registry" buttons that open My Wishlist with the occasion picked.
     */
    protected static function registryPages() {
        $rel = array_values( self::registrySlugs() );
        $pick = function ( $self ) use ( $rel ) { return array_slice( array_values( array_diff( $rel, array( $self ) ) ), 0, 3 ); };
        $free = array( 'Is it free?', 'Yes. Making and sharing a registry is free, with no app to install and no limit on items or guests.' );
        $cash = array( 'Can guests give cash instead?', 'Yes. Upload your own GCash, Maya or bank QR and add a short note. Guests scan it in their own app and the money goes straight to you; Manito Manita never handles it.' );
        $dupe = array( 'How do guests avoid buying the same gift?', 'Each item has an &ldquo;I&rsquo;ll get this&rdquo; button. Once a guest taps it, others see it&rsquo;s taken. For things you need several of, like plates or diapers, guests reserve one or more pieces and everyone sees how many are left.' );
        $p = array();

        $p['wedding-gift-registry-philippines'] = array(
            'occasion' => 'wedding',
            'kw'       => 'wedding gift registry philippines',
            'title'    => 'Free Wedding Gift Registry Philippines: Share One Link with Guests | Manito Manita',
            'desc'     => 'Make a free wedding gift registry with Lazada and Shopee links, quantities and a GCash QR for cash gifts. Guests reserve gifts so nothing is doubled. No app needed.',
            'eyebrow'  => 'Wedding &middot; Gift registry',
            'h1'       => 'Free wedding gift registry <span>for Filipino couples</span>',
            'lead'     => 'List what you need for your new home, add Lazada or Shopee links, and share one link on your invitation or Messenger. Guests reserve a gift so you don&rsquo;t get three rice cookers.',
            'intro'    => 'How a wedding gift registry works in the Philippines',
            'body'     => '<p>A <strong>wedding gift registry</strong> is a list of things the couple would love to receive, shared with guests so they can pick a gift that&rsquo;s actually needed. In the Philippines it&rsquo;s still common to receive several of the same appliance, or to get gifts that don&rsquo;t fit a small condo. A registry fixes that politely.</p>'
                . '<p>Create your registry with both your names (like &ldquo;Mark &amp; Joy&rdquo;) and the wedding date. Add items with a link from Lazada, Shopee or any store, and say how many you need, like 8 plates or 2 towel sets. Mark the ones you want most. Then share the link: print it as a QR on your invitation, add it to your wedding website, or send it in the family group chat.</p>'
                . '<p>Guests open the link without signing up and tap &ldquo;I&rsquo;ll get this&rdquo;. You can see who reserved what, so thank-you notes are easy. Many couples also add a cash gift option with their own GCash or bank QR, which guests can use for the money dance or as an envelope gift.</p>',
            'groups'   => array(
                array( 'Under &#8369;1,000', "Towel set | bath towel set | 1000\nKitchen utensil set | kitchen utensil set | 1000\nGlassware set | drinking glass set | 1000\nScented candles | scented candle gift set | 1000" ),
                array( '&#8369;1,000 to &#8369;3,000', "Bedsheet set | bedsheet set queen | 3000\nDinnerware set | dinnerware set | 3000\nRice cooker | rice cooker | 3000\nNon-stick pan set | non stick pan set | 3000" ),
                array( '&#8369;3,000 and up', "Air fryer | air fryer | 6000\nStand mixer | stand mixer | 8000\nCookware set | cookware set | 7000\nVacuum cleaner | cordless vacuum cleaner | 9000" ),
            ),
            'faq'      => array(
                $free,
                array( 'Is it okay to have a wedding registry in the Philippines?', 'Yes, more and more couples share one. Guests often ask what to give, and a registry answers that kindly. Add a short note like &ldquo;Your presence is enough, but if you wish to give&hellip;&rdquo; to keep it gracious.' ),
                $dupe,
                $cash,
                array( 'Can we see who gave what?', 'Yes. Wedding registries show you who reserved each item by default, so you know who to thank. You can switch to surprise mode anytime.' ),
                array( 'How do we share it with guests?', 'Copy your link or QR code and add it to your invitation, wedding website, or Messenger and Viber group chats. Guests don&rsquo;t need an account.' ),
            ),
            'related'  => $pick( 'wedding-gift-registry-philippines' ),
        );

        $p['debut-wishlist'] = array(
            'occasion' => 'debut',
            'kw'       => 'debut wishlist',
            'title'    => 'Debut Wishlist: Free 18th Birthday Gift List to Share | Manito Manita',
            'desc'     => 'Make a free debut wishlist for your 18th birthday. Add Lazada and Shopee links and share one link with your 18 roses, candles and treasures. Guests reserve gifts, no duplicates.',
            'eyebrow'  => 'Debut &middot; 18th birthday',
            'h1'       => 'Debut wishlist <span>for your 18th birthday</span>',
            'lead'     => 'Your 18 roses, 18 candles and 18 treasures want to give you something you&rsquo;ll love. Share one list and let them reserve a gift, so you don&rsquo;t get five of the same perfume.',
            'intro'    => 'Why make a debut wishlist',
            'body'     => '<p>A Filipino <strong>debut</strong> has a lot of gift givers: the 18 treasures, the 18 candles, titos and titas, friends and classmates. Without a list, many end up guessing, and the debutante gets duplicates of the same makeup set.</p>'
                . '<p>Create your debut wishlist with your name and party date. Add the things you&rsquo;d love, with links from Lazada, Shopee or any store, and mark your top picks as Most wanted. Share the link in your family and barkada group chats, or put the QR on your invitation.</p>'
                . '<p>Each guest taps &ldquo;I&rsquo;ll get this&rdquo; so others know it&rsquo;s taken. Debut lists keep reservations a surprise by default, so you won&rsquo;t know who got what until the party. You can also add a cash gift QR.</p>',
            'groups'   => array(
                array( 'Under &#8369;1,000', "Perfume | perfume for women | 1000\nMakeup set | makeup set | 1000\nNecklace | necklace for women | 1000\nSkincare set | skincare gift set | 1000" ),
                array( '&#8369;1,000 to &#8369;3,000', "Sling bag | sling bag women | 3000\nWatch | watch for women | 3000\nWireless earbuds | wireless earbuds | 3000\nSilver bracelet | silver bracelet women | 3000" ),
                array( '&#8369;3,000 and up', "Instax camera | instax mini | 6000\nDesigner perfume | designer perfume women | 7000\nSmart watch | smart watch women | 8000\nGold jewelry | gold necklace | 10000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What do you give at a debut?', 'Jewelry, perfume, a bag, a camera or a keepsake are classic debut gifts. The 18 treasures often give something meaningful. A wishlist shows exactly what the debutante wants.' ),
                $dupe,
                array( 'Will the debutante see who reserved what?', 'Not by default. Debut wishlists keep it a surprise, so she only sees which items are taken. She can change this anytime.' ),
                $cash,
            ),
            'related'  => $pick( 'debut-wishlist' ),
        );

        $p['baptism-gift-registry'] = array(
            'occasion' => 'baptism',
            'kw'       => 'baptism gift registry',
            'title'    => 'Baptism Gift Registry for Ninongs & Ninangs: Free Binyag Gift List | Manito Manita',
            'desc'     => 'Make a free baptism (binyag) gift registry for your baby. Share one link with ninongs and ninangs, add Lazada and Shopee links, and avoid duplicate gifts.',
            'eyebrow'  => 'Baptism &middot; Binyag',
            'h1'       => 'Baptism gift registry <span>for ninongs and ninangs</span>',
            'lead'     => 'Help the ninongs and ninangs choose gifts your baby will actually use. Share one list of clothes, essentials and keepsakes, and they reserve so nothing is doubled.',
            'intro'    => 'A simple binyag gift list for godparents',
            'body'     => '<p>At a Filipino <strong>binyag</strong>, the baby often has many ninongs and ninangs, and most of them ask the parents what to give. A <strong>baptism gift registry</strong> answers everyone at once.</p>'
                . '<p>Create the registry with your baby&rsquo;s name (like &ldquo;Baby Ana&rdquo;) and the baptism date. Add what you need: clothes in the next size up, a baby bath set, feeding essentials, diapers, or a silver keepsake. Say how many you need of each, and share the link in the godparents&rsquo; group chat.</p>'
                . '<p>Godparents tap &ldquo;I&rsquo;ll get this&rdquo; and you can see who reserved what, so thank-you messages are easy. You can also add your GCash QR for those who prefer to give cash.</p>',
            'groups'   => array(
                array( 'Under &#8369;500', "Baby washcloths | baby washcloth set | 500\nBibs | baby bibs | 500\nBaby socks and mittens | baby mittens socks set | 500\nBaby toiletries | baby toiletries set | 500" ),
                array( '&#8369;500 to &#8369;1,500', "Baby clothes set | baby clothes set | 1500\nBaby blanket | baby blanket | 1500\nFeeding set | baby feeding set | 1500\nBaby bath set | baby bath set | 1500" ),
                array( '&#8369;1,500 and up', "Silver keepsake bracelet | baby silver bracelet | 3000\nBaby carrier | baby carrier | 3000\nDiaper bag | diaper bag backpack | 3000\nBaby rocker | baby rocker | 5000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What do ninongs and ninangs usually give at a binyag?', 'Baby clothes, a blanket, feeding sets, diapers, a silver bracelet or a cash gift in an envelope are the most common. A registry tells them what the baby actually needs.' ),
                $dupe,
                array( 'Can we see which ninong or ninang reserved each gift?', 'Yes. Baptism registries show who reserved what by default, so you know who to thank. You can turn it off for a surprise.' ),
                $cash,
            ),
            'related'  => $pick( 'baptism-gift-registry' ),
        );

        $p['baby-shower-registry'] = array(
            'occasion' => 'baby-shower',
            'kw'       => 'baby shower registry',
            'title'    => 'Free Baby Shower Registry Philippines: Gift List for Guests | Manito Manita',
            'desc'     => 'Make a free baby shower or gender reveal registry. Add diapers, bottles and essentials with Lazada and Shopee links, set quantities, and share one link. No duplicates.',
            'eyebrow'  => 'Baby shower &middot; Gender reveal',
            'h1'       => 'Baby shower registry <span>for everything baby needs</span>',
            'lead'     => 'Diapers, bottles, swaddles and the big things too. Share one list for your baby shower or gender reveal, and guests reserve what they&rsquo;ll bring.',
            'intro'    => 'How to make a baby shower registry',
            'body'     => '<p>Getting ready for a baby means a long shopping list. A <strong>baby shower registry</strong> lets friends and family help with the things you really need, instead of five of the same onesie.</p>'
                . '<p>Create your registry with the baby&rsquo;s name (or &ldquo;Baby Santos&rdquo;) and the shower date. Add items with links, and set how many you need: 6 packs of newborn diapers, 4 feeding bottles, 3 swaddles. Mark the big-ticket items you&rsquo;d love as Most wanted, then share the link in your group chats.</p>'
                . '<p>Guests reserve one or more pieces, and everyone sees how many are left. You can see who reserved what, which makes thank-you notes easy.</p>',
            'groups'   => array(
                array( 'Under &#8369;1,000', "Newborn diapers | diapers newborn | 1000\nSwaddle blankets | swaddle blanket | 1000\nNewborn clothes set | newborn clothes set | 1000\nBaby wipes | baby wipes | 1000" ),
                array( '&#8369;1,000 to &#8369;3,000', "Feeding bottle set | baby feeding bottle set | 3000\nBottle sterilizer | bottle sterilizer | 3000\nBaby bath tub | baby bath tub | 3000\nDiaper bag | diaper bag backpack | 3000" ),
                array( '&#8369;3,000 and up', "Baby carrier | baby carrier | 5000\nBaby monitor | baby monitor | 5000\nStroller | baby stroller | 10000\nCrib | baby crib | 10000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What should I put on a baby shower registry?', 'Start with daily essentials (diapers, wipes, bottles, swaddles, newborn clothes), then add bigger items like a stroller, carrier or crib that guests can share or one person can give.' ),
                $dupe,
                array( 'Can I use it for a gender reveal?', 'Yes. Pick &ldquo;Baby shower / Gender reveal&rdquo; as the occasion and share the link with your invitation.' ),
                $cash,
            ),
            'related'  => $pick( 'baby-shower-registry' ),
        );

        $p['first-birthday-wishlist'] = array(
            'occasion' => 'first-birthday',
            'kw'       => 'first birthday gift ideas',
            'title'    => 'First Birthday Wishlist & Gift Ideas for 1 Year Old | Manito Manita',
            'desc'     => 'Gift ideas for a 1 year old, plus a free first birthday wishlist parents can share. Add Lazada and Shopee links and let guests reserve so toys aren&rsquo;t doubled.',
            'eyebrow'  => 'First birthday &middot; 1 year old',
            'h1'       => 'First birthday wishlist <span>and gift ideas for 1 year olds</span>',
            'lead'     => 'Toys, books and clothes your little one will actually use. Share one wishlist for the first birthday, and guests reserve a gift so nothing is doubled.',
            'intro'    => 'What to give a 1 year old, and how a wishlist helps',
            'body'     => '<p>A <strong>first birthday</strong> is a big celebration in Filipino families, and every tita wants to bring a gift. A one-year-old is learning to walk, talk and play, so the best gifts help with that: push walkers, stacking toys, board books, and clothes in the next size.</p>'
                . '<p>Parents can create a <strong>first birthday wishlist</strong> with the baby&rsquo;s name and party date, add items with shop links, and share it in the family group chat. Guests tap &ldquo;I&rsquo;ll get this&rdquo;, and parents can see who reserved what for thank-you messages.</p>'
                . '<p>Shopping for a first birthday without a list? Use the ideas below; each button opens the store filtered to the budget.</p>',
            'groups'   => array(
                array( 'Under &#8369;500', "Board books | baby board books | 500\nBath toys | baby bath toys | 500\nStacking cups | stacking cups baby | 500\nSippy cup | sippy cup | 500" ),
                array( '&#8369;500 to &#8369;1,500', "Learning toys | educational toys 1 year old | 1500\nShape sorter | shape sorter toy | 1500\nBaby clothes 12 months | baby clothes 12 months | 1500\nMusical toys | musical toys baby | 1500" ),
                array( '&#8369;1,500 and up', "Push walker | baby walker push | 3000\nRide-on toy | ride on toy toddler | 4000\nActivity table | baby activity table | 3000\nPlay mat | baby play mat | 3000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What is a good gift for a 1 year old?', 'Push walkers, stacking and shape-sorting toys, board books, bath toys, and clothes for 12 to 18 months are safe, useful choices. Avoid toys with small parts.' ),
                $dupe,
                array( 'Can parents see who gave what?', 'Yes. First birthday lists show who reserved each item by default, so parents know who to thank.' ),
                $cash,
            ),
            'related'  => $pick( 'first-birthday-wishlist' ),
        );

        $p['housewarming-gift-list'] = array(
            'occasion' => 'housewarming',
            'kw'       => 'housewarming gift ideas',
            'title'    => 'Housewarming Gift Ideas & Free House Blessing Gift List | Manito Manita',
            'desc'     => 'Housewarming and house blessing gift ideas by budget, plus a free gift list you can share. Add Lazada and Shopee links so guests bring what your new home needs.',
            'eyebrow'  => 'Housewarming &middot; House blessing',
            'h1'       => 'Housewarming gift list <span>for your new home</span>',
            'lead'     => 'Moved in? Share what your new home still needs, from cookware to plants, and guests at your house blessing reserve a gift so nothing is doubled.',
            'intro'    => 'Gift ideas for a house blessing, and a list to share',
            'body'     => '<p>In the Philippines, a new home is usually celebrated with a <strong>house blessing</strong>: a priest, food, and family and friends who bring something for the house. Common gifts are kitchen items, appliances, plants and decor.</p>'
                . '<p>If it&rsquo;s your house blessing, create a <strong>housewarming gift list</strong> with your names and the date. Add what your home still needs, with shop links and quantities, and share it with your invitation. Guests reserve a gift, and you can see who to thank.</p>'
                . '<p>Visiting someone&rsquo;s new home? The ideas below are grouped by budget, and each button opens the store already filtered.</p>',
            'groups'   => array(
                array( 'Under &#8369;1,000', "Indoor plant | indoor plant | 1000\nScented candles | scented candle gift set | 1000\nThrow pillows | throw pillow | 1000\nWall clock | wall clock | 1000" ),
                array( '&#8369;1,000 to &#8369;3,000', "Cookware set | cookware set | 3000\nStorage boxes | storage box set | 3000\nElectric kettle | electric kettle | 3000\nTable lamp | table lamp | 3000" ),
                array( '&#8369;3,000 and up', "Coffee maker | coffee maker | 6000\nAir purifier | air purifier | 8000\nStand fan | stand fan | 4000\nRobot vacuum | robot vacuum | 10000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What do you bring to a house blessing in the Philippines?', 'Kitchen items, small appliances, plants, home decor or food are traditional. Some families also give rice and salt for good luck. A gift list shows what the home still needs.' ),
                $dupe,
                $cash,
            ),
            'related'  => $pick( 'housewarming-gift-list' ),
        );

        $p['graduation-gift-ideas'] = array(
            'occasion' => 'graduation',
            'kw'       => 'graduation gift ideas',
            'title'    => 'Graduation Gift Ideas Philippines by Budget + Free Wishlist | Manito Manita',
            'desc'     => 'Graduation gift ideas for college and senior high grads, grouped by budget, with Lazada and Shopee buttons. Graduates can share a free wishlist too.',
            'eyebrow'  => 'Graduation &middot; Gift ideas',
            'h1'       => 'Graduation gift ideas <span>for the new grad</span>',
            'lead'     => 'Gifts for the next step: work, review season or moving to the city. Ideas by budget, plus a free wishlist graduates can share with family.',
            'intro'    => 'What to give a graduate',
            'body'     => '<p>A <strong>graduation gift</strong> works best when it helps with what comes next. College grads starting work need a good bag, a watch or a laptop accessory. Board exam takers need a planner and something to keep them going during review. Senior high grads heading to college need dorm basics.</p>'
                . '<p>Graduating? Create a free <strong>graduation wishlist</strong> with your name and graduation date, add what you&rsquo;d love with shop links, and share it with family. Reservations stay a surprise by default.</p>',
            'groups'   => array(
                array( 'Under &#8369;1,000', "Planner | planner | 1000\nTumbler | insulated tumbler | 1000\nPower bank | power bank | 1000\nWallet | wallet | 1000" ),
                array( '&#8369;1,000 to &#8369;3,000', "Laptop backpack | laptop backpack | 3000\nWireless earbuds | wireless earbuds | 3000\nWatch | watch | 3000\nPerfume | perfume | 3000" ),
                array( '&#8369;3,000 and up', "Smart watch | smart watch | 8000\nTablet | tablet | 15000\nOffice shoes | leather shoes | 5000\nNoise-cancelling headphones | noise cancelling headphones | 8000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What is a good graduation gift in the Philippines?', 'A watch, a laptop backpack, earbuds, a planner for the board exam review, perfume, or office shoes for job hunting. Cash is also common.' ),
                $dupe,
                $cash,
            ),
            'related'  => $pick( 'graduation-gift-ideas' ),
        );

        $p['despedida-gift-ideas'] = array(
            'occasion' => 'despedida',
            'kw'       => 'despedida gift ideas',
            'title'    => 'Despedida Gift Ideas for OFWs & Friends Going Abroad | Manito Manita',
            'desc'     => 'Despedida gift ideas for an OFW, a migrating friend or a colleague moving abroad, by budget. Plus a free wishlist to share so gifts are useful and not doubled.',
            'eyebrow'  => 'Despedida &middot; Going abroad',
            'h1'       => 'Despedida gift ideas <span>for someone going abroad</span>',
            'lead'     => 'Practical gifts for the trip and the first weeks away: travel gear, power, and a taste of home. Ideas by budget, plus a list the traveller can share.',
            'intro'    => 'What to give at a despedida',
            'body'     => '<p>A <strong>despedida</strong> sends off someone leaving for work, study or a new life abroad. The best gifts are light, useful and fit in a suitcase: travel organizers, a universal adapter, a power bank, or something that reminds them of home.</p>'
                . '<p>Leaving soon? Create a free <strong>despedida wishlist</strong> with your flight date, list what you still need for the move, and share it with family, friends or officemates so nobody buys the same luggage tag.</p>',
            'groups'   => array(
                array( 'Under &#8369;1,000', "Universal adapter | universal travel adapter | 1000\nTravel organizer | travel organizer pouch | 1000\nNeck pillow | travel neck pillow | 1000\nPasalubong snacks | filipino snacks | 1000" ),
                array( '&#8369;1,000 to &#8369;3,000', "Power bank | power bank | 3000\nLuggage scale and tags | luggage scale | 1500\nWater bottle | insulated water bottle | 2000\nJacket | packable jacket | 3000" ),
                array( '&#8369;3,000 and up', "Luggage | luggage | 6000\nWireless earbuds | wireless earbuds | 4000\nSmart watch | smart watch | 8000\nCarry-on backpack | travel backpack | 4000" ),
            ),
            'faq'      => array(
                $free,
                array( 'What is a good despedida gift for an OFW?', 'Something light and useful for the trip and the first weeks: a universal adapter, power bank, travel organizer, warm jacket, or Filipino snacks for homesick days.' ),
                $dupe,
                $cash,
            ),
            'related'  => $pick( 'despedida-gift-ideas' ),
        );

        foreach ( $p as $slug => $v ) {
            $p[ $slug ] += array( 'type' => 'registry' );
        }

        return $p;
    }

    public static function seed() {
        if ( ! function_exists( 'update_field' ) ) return;
        $done = (array) get_option( 'mm_landing_seeded', array() );

        foreach ( self::newPages() as $slug => $p ) {
            if ( isset( $done[ $slug ] ) ) continue;
            if ( get_transient( 'mm_seed_' . $slug ) ) continue; // another request is creating it
            set_transient( 'mm_seed_' . $slug, 1, 120 );

            $existing = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
            $id = $existing ? (int) $existing[0] : wp_insert_post( array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_name'    => $slug,
                'post_title'   => wp_strip_all_tags( html_entity_decode( $p['h1'], ENT_QUOTES, 'UTF-8' ) ),
                'post_content' => $p['body'],
            ), true );

            if ( is_wp_error( $id ) || ! $id ) continue;

            if ( ! $existing ) {
                update_post_meta( $id, '_wp_page_template', 'template-landing.php' );
                update_post_meta( $id, '_yoast_wpseo_title', html_entity_decode( $p['title'], ENT_QUOTES, 'UTF-8' ) );
                update_post_meta( $id, '_yoast_wpseo_metadesc', html_entity_decode( $p['desc'], ENT_QUOTES, 'UTF-8' ) );
                update_post_meta( $id, '_yoast_wpseo_focuskw', $p['kw'] ?? 'online bunutan' );
                update_field( 'field_mm_land_type', $p['type'] ?? 'tool', $id );
                if ( ! empty( $p['occasion'] ) ) update_field( 'field_mm_land_occasion', $p['occasion'], $id );
                if ( ! empty( $p['groups'] ) ) {
                    update_field( 'field_mm_land_groups', array_map( function ( $g ) {
                        return array( 'field_mm_land_group_title' => html_entity_decode( $g[0], ENT_QUOTES, 'UTF-8' ), 'field_mm_land_group_ideas' => $g[1] );
                    }, $p['groups'] ), $id );
                }
                update_field( 'field_mm_land_eyebrow', $p['eyebrow'], $id );
                update_field( 'field_mm_land_h1', $p['h1'], $id );
                update_field( 'field_mm_land_lead', $p['lead'], $id );
                update_field( 'field_mm_land_intro_title', $p['intro'], $id );
                update_field( 'field_mm_land_updated', wp_date( 'Ymd' ), $id );
                update_field( 'field_mm_land_faq', array_map( function ( $q ) {
                    return array( 'field_mm_land_faq_q' => $q[0], 'field_mm_land_faq_a' => $q[1] );
                }, $p['faq'] ), $id );
                $rel = array();
                foreach ( $p['related'] as $r ) {
                    $rp = get_page_by_path( $r );
                    if ( $rp ) $rel[] = (int) $rp->ID;
                }
                if ( $rel ) update_field( 'field_mm_land_related', $rel, $id );
            }

            $done[ $slug ] = (int) $id;
            update_option( 'mm_landing_seeded', $done, true ); // autoloaded: no extra query per request
            delete_transient( 'mm_seed_' . $slug );
            $added = true;
        }

        if ( ! empty( $added ) ) do_action( 'litespeed_purge_all' ); // cached pages pick up the new footer links
    }

    public static function isActive() {
        return is_page_template( 'template-landing.php' );
    }

    /**
     * Old URLs that moved (301 keeps their search ranking). /gift-ideas/ was the old Lazada feed.
     */
    public static function redirects() {
        $moved = array( 'gift-ideas' => 'exchange-gift-ideas-500-pesos' );
        $slug = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

        if ( isset( $moved[ $slug ] ) ) {
            wp_redirect( home_url( '/' . $moved[ $slug ] . '/' ), 301 );
            exit;
        }
    }

    protected static function lines( $text ) {
        return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $text ) ) ) );
    }

    /**
     * Everything the template needs for one page, from its fields.
     */
    public static function fromPage( $id ) {
        $id = (int) $id;
        $f = function ( $name ) use ( $id ) { return function_exists( 'get_field' ) ? get_field( $name, $id ) : ''; };

        $groups = array();
        foreach ( (array) $f( 'mm_groups' ) as $g ) {
            $ideas = array();
            foreach ( self::lines( $g['ideas'] ?? '' ) as $line ) {
                $parts = array_map( 'trim', explode( '|', $line, 3 ) );
                $ideas[] = array( $parts[0], ( $parts[1] ?? '' ) !== '' ? $parts[1] : $parts[0], (int) preg_replace( '/\D/', '', $parts[2] ?? '' ) ); // label, keyword, price cap (registry guides)
            }
            if ( $ideas ) $groups[] = array( (string) ( $g['title'] ?? '' ), $ideas );
        }

        $lists = array();
        foreach ( (array) $f( 'mm_lists' ) as $l ) {
            $items = self::lines( $l['items'] ?? '' );
            if ( $items ) $lists[] = array( (string) ( $l['title'] ?? '' ), $items );
        }

        $faq = array();
        foreach ( (array) $f( 'mm_faq' ) as $q ) {
            if ( ! empty( $q['question'] ) ) $faq[] = array( (string) $q['question'], (string) ( $q['answer'] ?? '' ) );
        }

        $post = get_post( $id );

        return array(
            'id'      => $id,
            'slug'    => $post ? $post->post_name : '',
            'type'    => (string) ( $f( 'mm_type' ) ?: 'tool' ),
            'eyebrow' => (string) $f( 'mm_eyebrow' ),
            'h1'      => (string) ( $f( 'mm_h1' ) ?: get_the_title( $id ) ),
            'lead'    => (string) $f( 'mm_lead' ),
            'intro'   => array(
                'title' => (string) $f( 'mm_intro_title' ),
                'body'  => $post ? apply_filters( 'the_content', $post->post_content ) : '',
            ),
            'budget'  => (int) $f( 'mm_budget' ),
            'occasion' => class_exists( 'Registries' ) ? Registries::valid( (string) $f( 'mm_occasion' ) ) : 'any',
            'groups'  => $groups,
            'lists'   => $lists,
            'faq'     => $faq,
            'related' => array_map( 'intval', array_filter( (array) $f( 'mm_related' ) ) ),
            'updated' => (string) ( $f( 'mm_updated' ) ?: ( $post ? get_the_modified_date( 'Y-m-d', $id ) : '' ) ),
        );
    }

    /**
     * Legal pages: give each <h2> an id and build the contents list.
     */
    public static function legalContent( $html ) {
        $toc = array();
        $n = 0;
        $html = preg_replace_callback( '~<h2([^>]*)>(.*?)</h2>~is', function ( $m ) use ( &$toc, &$n ) {
            $n++;
            $toc[] = array( 's' . $n, trim( wp_strip_all_tags( preg_replace( '/^\s*\d+\.\s*/', '', $m[2] ) ) ) );
            return '<h2 id="s' . $n . '"' . $m[1] . '>' . $m[2] . '</h2>';
        }, $html );

        return array( $html, $toc );
    }

    /**
     * FAQPage structured data for landing pages with questions (Yoast covers WebPage + breadcrumbs).
     */
    public static function faqSchema() {
        if ( ! self::isActive() ) return;

        $p = self::fromPage( get_queried_object_id() );
        if ( ! $p['faq'] ) return;

        $faq = array_map( function ( $q ) {
            return array( '@type' => 'Question', 'name' => html_entity_decode( wp_strip_all_tags( $q[0] ), ENT_QUOTES, 'UTF-8' ),
                'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( wp_strip_all_tags( $q[1] ), ENT_QUOTES, 'UTF-8' ) ) );
        }, $p['faq'] );

        echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}

Landing::init();
