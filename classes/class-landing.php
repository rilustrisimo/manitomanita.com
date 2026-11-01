<?php
/**
 * * Landing Class. Search landing pages defined in code (no WP page needed): one tool page per search
 * term ("monito monita generator", "kris kringle generator philippines", "secret santa generator")
 * plus gift guides. Rendered by template-landing.php with mm-home.css.
 *
 * Each page gets its own <title>, description, canonical, Open Graph, FAQ + breadcrumb JSON-LD, and is
 * added to Yoast's page sitemap. Yoast's own head output is switched off on these URLs.
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
 * Class Landing
 */
class Landing {
    const UPDATED = '2026-11-02';

    protected static $hooked  = false;
    protected static $current = null;

    /**
     * slug => page. 'type' => tool (generator page) | guide (gift ideas with shop buttons).
     */
    public static function pages() {
        return array(
            'monito-monita-generator' => array(
                'type'    => 'tool',
                'title'   => 'Monito Monita Generator: Draw Names Online for Free | Manito Manita',
                'desc'    => 'Free Monito Monita (Manito Manita) generator for your office, family or barkada. Draw names online, share wishlists and keep every match secret. No sign-up.',
                'eyebrow' => 'Monito Monita &middot; Manito Manita',
                'h1'      => 'Monito Monita generator: <span>draw names online, free</span>',
                'lead'    => 'Planning your Monito Monita this Christmas? Create a group, share one link, and everyone privately sees who their Monito or Monita is, with wishlists and gift ideas within your budget.',
                'intro'   => array(
                    'title' => 'What is Monito Monita?',
                    'body'  => '<p><strong>Monito Monita</strong> (also spelled <strong>Manito Manita</strong>) is the Filipino version of Secret Santa. Each person secretly draws the name of someone in the group and gives them a gift, usually at the Christmas party. Many offices and schools in the Philippines make it a weekly game in December, with themes like <em>something sweet</em>, <em>something soft</em> or <em>something red</em>, then reveal who was whose Monito or Monita on the last day.</p><p>Drawing names on paper means someone always gets themselves, or the papers get lost. Doing it online keeps it fair, private and quick, even when some of your group are working from home.</p>',
                ),
                'faq'     => array(
                    array( 'Is the Monito Monita generator free?', 'Yes. Creating a group, inviting everyone, shuffling names, wishlists, gift ideas and email notifications are free, with no limit on members.' ),
                    array( 'Monito Monita or Manito Manita: which is correct?', 'Both are used in the Philippines for the same tradition. Manito (male) and Manita (female) come from the Spanish word for little brother or sister. You will hear both spellings in offices and schools.' ),
                    array( 'Can we do weekly themes?', 'Yes. Set your themes and dates in your group details and share them with everyone. A dedicated weekly-theme mode with reminders is coming for PRO groups.' ),
                    array( 'How much should the gift budget be?', 'Most offices in the Philippines set a minimum of ₱300 to ₱500 per gift. You set the spending minimum when you create the group, and gift ideas are picked within it.' ),
                    array( 'Do members need to download an app?', 'No. Everyone joins from the group link on any phone or computer, with their name, a screen name, their email and a password for that group.' ),
                ),
                'related' => array( 'monito-monita-themes', 'kris-kringle-generator-philippines', 'exchange-gift-ideas-500-pesos' ),
            ),

            'kris-kringle-generator-philippines' => array(
                'type'    => 'tool',
                'title'   => 'Kris Kringle Generator (Philippines): Free Online Name Draw | Manito Manita',
                'desc'    => 'Draw names for your office or barkada Kris Kringle online. Free, private and fair: wishlists, gift ideas within your peso budget and email notifications.',
                'eyebrow' => 'Kris Kringle &middot; Philippines',
                'h1'      => 'Kris Kringle generator for your <span>office or barkada</span>',
                'lead'    => 'Run your Kris Kringle without the paper slips. Everyone joins from one link, adds a wishlist, and finds out who they are buying for, privately, on their phone.',
                'intro'   => array(
                    'title' => 'Kris Kringle in the Philippines',
                    'body'  => '<p>In the Philippines, <strong>Kris Kringle</strong> is what many offices call their Christmas gift exchange. It is the same idea as Secret Santa and Monito Monita: everyone draws a name, buys a gift within the agreed budget, and the giver is revealed at the party.</p><p>For a smooth office Kris Kringle, agree on the budget and the exchange date early, ask everyone to add a wishlist, and shuffle once everyone has joined. With an online draw, nobody gets their own name, and remote teammates can join too.</p>',
                ),
                'faq'     => array(
                    array( 'Is Kris Kringle the same as Secret Santa?', 'Yes. Kris Kringle, Secret Santa and Monito Monita are different names for the same gift exchange, where each person secretly gives a gift to someone they drew.' ),
                    array( 'What is a typical Kris Kringle budget in the Philippines?', 'Office exchanges usually set ₱300, ₱500 or ₱1,000 as the minimum. Choose an amount everyone is comfortable with and set it as the group&rsquo;s spending minimum.' ),
                    array( 'Can couples avoid getting each other?', 'Yes, with PRO. The organizer adds exclusions, like a couple or siblings, and the shuffle makes sure they never get each other.' ),
                    array( 'What if someone joins late?', 'Shuffle once everyone has joined. If someone joins after the shuffle, a PRO organizer can unshuffle the group to let them in, then shuffle again.' ),
                    array( 'Is it free?', 'Yes, free forever with no limit on members. PRO is an optional ₱199 one-time add-on for organizers who want extra tools.' ),
                ),
                'related' => array( 'monito-monita-generator', 'exchange-gift-ideas-500-pesos', 'secret-santa-generator' ),
            ),

            'secret-santa-generator' => array(
                'type'    => 'tool',
                'title'   => 'Free Secret Santa Generator: Draw Names Online in 2 Minutes | Manito Manita',
                'desc'    => 'Free online Secret Santa generator. Draw names, share wishlists and email everyone their match privately. No sign-up, no app, unlimited members.',
                'eyebrow' => 'Secret Santa generator',
                'h1'      => 'Free Secret Santa generator: <span>draw names online in 2 minutes</span>',
                'lead'    => 'Create a group, share one link, and everyone gets their Secret Santa match privately, with wishlists and gift ideas in one place. Works for families, friends, offices and remote teams.',
                'intro'   => array(
                    'title' => 'How an online Secret Santa draw works',
                    'body'  => '<p>Pulling names from a hat is fun until someone draws their own name, or the organizer accidentally sees everyone&rsquo;s match. An online <strong>Secret Santa generator</strong> fixes both: the draw is random, nobody gets themselves, and each person only ever sees their own match.</p><p>Manito Manita adds the parts that make a gift exchange easier: everyone keeps a wishlist with links, gets gift ideas within the budget, and receives an email when names are drawn and a reminder before the exchange.</p>',
                ),
                'faq'     => array(
                    array( 'Is this Secret Santa generator really free?', 'Yes. Groups, the name draw, wishlists, gift ideas and emails are free, with no limit on members and no sign-up.' ),
                    array( 'Does everyone need an account?', 'No. Members open the group link and join with a name, a screen name, their email and a password for that group. Nothing to download.' ),
                    array( 'Can I stop couples drawing each other?', 'Yes. PRO groups can add exclusions so couples or family members never get each other.' ),
                    array( 'Can we use it for a remote or virtual Secret Santa?', 'Yes. Everyone joins online and gets their match by email, so it works the same for remote teams and family abroad. Add your address in your profile so your Secret Santa can ship the gift.' ),
                    array( 'Is it the same as Kris Kringle or Monito Monita?', 'Yes. Kris Kringle (Philippines, Australia, Ireland) and Monito Monita (Philippines) are other names for Secret Santa.' ),
                ),
                'related' => array( 'virtual-secret-santa', 'kris-kringle-generator-philippines', 'monito-monita-generator' ),
            ),

            'exchange-gift-ideas-500-pesos' => array(
                'type'    => 'guide',
                'budget'  => 500,
                'title'   => '30 Exchange Gift Ideas Under ₱500 (Philippines, ' . gmdate( 'Y' ) . ') | Manito Manita',
                'desc'    => 'Exchange gift ideas worth ₱500 or less for your Kris Kringle, Monito Monita or office Christmas party, with quick links to shop each idea on Lazada and Shopee.',
                'eyebrow' => 'Gift guide &middot; under ₱500',
                'h1'      => 'Exchange gift ideas <span>under ₱500</span>',
                'lead'    => 'Stuck on what to give for your Kris Kringle or Monito Monita? Here are practical, crowd-pleasing gifts that fit a ₱500 budget, with one-tap links to shop each idea within budget.',
                'intro'   => array(
                    'title' => 'How to pick a ₱500 exchange gift',
                    'body'  => '<p>Start with your match&rsquo;s wishlist. If they don&rsquo;t have one, go for something <strong>useful, consumable or cozy</strong>: things people use every day, snacks, or small comforts. Avoid very personal items (perfume, clothing sizes) unless you know their taste, and stick to the group&rsquo;s spending minimum so the exchange feels fair.</p>',
                ),
                'groups'  => array(
                    array( 'For anyone', array(
                        array( 'Insulated tumbler', 'tumbler' ),
                        array( 'Scented candle set', 'scented candle gift set' ),
                        array( 'Mini bluetooth speaker', 'mini bluetooth speaker' ),
                        array( 'Desk organizer', 'desk organizer' ),
                        array( 'Power bank', 'power bank 10000mah' ),
                        array( 'Cozy throw blanket', 'throw blanket' ),
                    ) ),
                    array( 'For the office', array(
                        array( 'Planner or notebook set', 'planner 2027' ),
                        array( 'Mug warmer', 'mug warmer' ),
                        array( 'Desk plant (succulent)', 'succulent plant pot' ),
                        array( 'Cable organizer kit', 'cable organizer' ),
                        array( 'Laptop stand', 'laptop stand' ),
                        array( 'Wireless mouse', 'wireless mouse' ),
                    ) ),
                    array( 'For her', array(
                        array( 'Skincare gift set', 'skincare gift set' ),
                        array( 'Lip tint set', 'lip tint set' ),
                        array( 'Tote bag', 'canvas tote bag' ),
                        array( 'Hair claw clip set', 'claw clip set' ),
                        array( 'Jewelry organizer', 'jewelry organizer' ),
                        array( 'Bath and body set', 'bath and body gift set' ),
                    ) ),
                    array( 'For him', array(
                        array( 'Wallet', 'mens wallet' ),
                        array( 'Grooming kit', 'mens grooming kit' ),
                        array( 'Phone stand', 'phone stand' ),
                        array( 'Car organizer', 'car organizer' ),
                        array( 'Sports water bottle', 'sports water bottle' ),
                        array( 'Card game', 'card game adults' ),
                    ) ),
                    array( 'For foodies', array(
                        array( 'Chocolate gift box', 'chocolate gift box' ),
                        array( 'Coffee sampler', 'coffee beans gift' ),
                        array( 'Tea gift set', 'tea gift set' ),
                        array( 'Snack hamper', 'snack gift box' ),
                        array( 'Mini waffle maker', 'mini waffle maker' ),
                        array( 'Spice or sauce set', 'hot sauce set' ),
                    ) ),
                ),
                'faq'     => array(
                    array( 'Is ₱500 a good budget for a Kris Kringle?', 'Yes. ₱300 to ₱500 is the most common range for office exchanges in the Philippines: enough for something useful without stretching anyone&rsquo;s budget.' ),
                    array( 'What if my match has no wishlist?', 'Pick something useful or consumable from this list, like a tumbler, snacks or a desk organizer. On Manito Manita you can also see gift ideas picked from their wishlist once they add one.' ),
                    array( 'Should I include the shipping fee in the budget?', 'Most groups count only the item price. Check with your organizer, and order early so the gift arrives before the exchange.' ),
                ),
                'related' => array( 'exchange-gift-ideas-300-pesos', 'exchange-gift-ideas-1000-pesos', 'kris-kringle-generator-philippines' ),
            ),

            'exchange-gift-ideas-300-pesos' => array(
                'type'    => 'guide',
                'budget'  => 300,
                'title'   => '24 Exchange Gift Ideas Under ₱300 (Philippines, ' . gmdate( 'Y' ) . ') | Manito Manita',
                'desc'    => 'Budget-friendly exchange gift ideas under ₱300 for your Kris Kringle, Monito Monita or school Christmas party, with quick links to shop on Lazada and Shopee.',
                'eyebrow' => 'Gift guide &middot; under ₱300',
                'h1'      => 'Exchange gift ideas <span>under ₱300</span>',
                'lead'    => 'A ₱300 budget still goes a long way. These small, thoughtful gifts work for classmates, officemates and barkada, with one-tap links to shop each idea within budget.',
                'intro'   => array(
                    'title' => 'Making a ₱300 gift feel special',
                    'body'  => '<p>At ₱300, think <strong>small but useful</strong>: everyday items, treats and cute desk pieces. Nice wrapping and a short handwritten note make a small gift feel much bigger. Check your match&rsquo;s wishlist first, then pick something that fits their routine.</p>',
                ),
                'groups'  => array(
                    array( 'Useful everyday', array(
                        array( 'Reusable straw set', 'metal straw set' ),
                        array( 'Mini fan', 'mini portable fan' ),
                        array( 'Phone ring holder', 'phone ring holder' ),
                        array( 'Pouch organizer', 'pouch organizer' ),
                        array( 'Umbrella (compact)', 'compact umbrella' ),
                        array( 'Lunch bag', 'insulated lunch bag' ),
                    ) ),
                    array( 'Cute and fun', array(
                        array( 'Desk mini figure', 'cute desk figure' ),
                        array( 'Keychain plushie', 'plush keychain' ),
                        array( 'Sticker pack', 'aesthetic sticker pack' ),
                        array( 'Fidget toy', 'fidget toy' ),
                        array( 'Puzzle cube', 'speed cube' ),
                        array( 'Mug with design', 'cute mug' ),
                    ) ),
                    array( 'Self-care', array(
                        array( 'Sheet mask set', 'sheet mask set' ),
                        array( 'Hand cream', 'hand cream' ),
                        array( 'Lip balm set', 'lip balm set' ),
                        array( 'Scented sachet', 'scented sachet' ),
                        array( 'Bath bombs', 'bath bomb set' ),
                        array( 'Hair ties set', 'hair tie set' ),
                    ) ),
                    array( 'Treats', array(
                        array( 'Assorted chocolates', 'chocolate assorted' ),
                        array( 'Local delicacies box', 'pasalubong box' ),
                        array( 'Instant coffee sampler', 'coffee sampler 3 in 1' ),
                        array( 'Cookies in a jar', 'cookies jar' ),
                        array( 'Gourmet tuyo or spread', 'gourmet tuyo' ),
                        array( 'Candy jar', 'candy jar' ),
                    ) ),
                ),
                'faq'     => array(
                    array( 'Is ₱300 enough for an exchange gift?', 'Yes. ₱300 is a common budget for school and barkada exchanges. Pick something useful or a treat, and add a nice wrap or note.' ),
                    array( 'What are good ₱300 gifts for officemates?', 'Desk items (mug, mini fan, organizer), snacks or coffee, and self-care items like hand cream are safe choices that most people use.' ),
                    array( 'Where can I see what my match wants?', 'If your group uses Manito Manita, open your match to see their wishlist and gift ideas within your budget.' ),
                ),
                'related' => array( 'exchange-gift-ideas-500-pesos', 'exchange-gift-ideas-1000-pesos', 'kris-kringle-generator-philippines' ),
            ),

            'exchange-gift-ideas-1000-pesos' => array(
                'type'    => 'guide',
                'budget'  => 1000,
                'title'   => '24 Exchange Gift Ideas Under ₱1,000 (Philippines, ' . gmdate( 'Y' ) . ') | Manito Manita',
                'desc'    => 'Exchange gift ideas worth ₱1,000 or less for office Kris Kringle and family Monito Monita: gadgets, home, self-care and more, with links to shop on Lazada and Shopee.',
                'eyebrow' => 'Gift guide &middot; under ₱1,000',
                'h1'      => 'Exchange gift ideas <span>under ₱1,000</span>',
                'lead'    => 'Got a bigger budget for your Kris Kringle or family exchange? These gifts feel premium without going over ₱1,000, with one-tap links to shop each idea within budget.',
                'intro'   => array(
                    'title' => 'What ₱1,000 can get your match',
                    'body'  => '<p>With ₱1,000 you can go for <strong>small gadgets, quality home items or a nice self-care set</strong>. If your match has a wishlist, this budget often covers one of the items on it. Otherwise, choose something they will use daily.</p>',
                ),
                'groups'  => array(
                    array( 'Gadgets', array(
                        array( 'Wireless earbuds', 'wireless earbuds' ),
                        array( 'Smartwatch (basic)', 'smart watch' ),
                        array( 'Bluetooth speaker', 'bluetooth speaker' ),
                        array( 'Ring light', 'ring light' ),
                        array( 'Mechanical keyboard', 'mechanical keyboard' ),
                        array( 'Fast charger and cable', 'fast charger' ),
                    ) ),
                    array( 'Home and kitchen', array(
                        array( 'Air fryer liner and tools set', 'air fryer accessories' ),
                        array( 'Aroma diffuser', 'aroma diffuser' ),
                        array( 'Electric kettle', 'electric kettle' ),
                        array( 'Bedside lamp', 'bedside lamp' ),
                        array( 'Coffee press', 'french press' ),
                        array( 'Plant with pot', 'indoor plant pot' ),
                    ) ),
                    array( 'Self-care and style', array(
                        array( 'Perfume mini set', 'perfume mini set' ),
                        array( 'Skincare bundle', 'skincare set' ),
                        array( 'Massage gun (mini)', 'mini massage gun' ),
                        array( 'Crossbody bag', 'crossbody bag' ),
                        array( 'Watch', 'watch' ),
                        array( 'Sunglasses', 'polarized sunglasses' ),
                    ) ),
                    array( 'Experiences and hobbies', array(
                        array( 'Board game', 'board game' ),
                        array( 'Art kit', 'art set' ),
                        array( 'Yoga mat', 'yoga mat' ),
                        array( 'Book bundle', 'book bundle' ),
                        array( 'Gaming accessory', 'gaming mouse' ),
                        array( 'Instant camera film', 'instax film' ),
                    ) ),
                ),
                'faq'     => array(
                    array( 'Is ₱1,000 a common exchange gift budget?', 'Yes, especially for office Kris Kringle and family exchanges. Many groups set ₱500 for officemates and ₱1,000 for family or close friends.' ),
                    array( 'What is a safe ₱1,000 gift if I barely know my match?', 'Wireless earbuds, a bluetooth speaker, an aroma diffuser or a skincare set are popular choices most people appreciate.' ),
                    array( 'Should I stay exactly at the budget?', 'Aim close to the group&rsquo;s spending minimum so the exchange feels fair, but don&rsquo;t go far over it.' ),
                ),
                'related' => array( 'exchange-gift-ideas-500-pesos', 'exchange-gift-ideas-300-pesos', 'monito-monita-generator' ),
            ),

            'monito-monita-themes' => array(
                'type'    => 'list',
                'title'   => '50+ Monito Monita Themes for Your Weekly Gift Exchange | Manito Manita',
                'desc'    => 'Fun Monito Monita (Manito Manita) weekly theme ideas for the office, school or family: something sweet, something soft, something red and 50 more, plus how to run it.',
                'eyebrow' => 'Monito Monita themes',
                'h1'      => '50+ Monito Monita themes <span>for your weekly exchange</span>',
                'lead'    => 'In many Filipino offices and schools, Monito Monita runs every week in December, each with its own theme. Pick your favorites from the list below, then set up your group in 2 minutes.',
                'intro'   => array(
                    'title' => 'How weekly Monito Monita themes work',
                    'body'  => '<p>Everyone draws a name at the start. Then, once a week until the Christmas party, each person secretly gives their Monito or Monita a small gift that fits <strong>that week&rsquo;s theme</strong>. On the last day, everyone gives their main gift and the givers are revealed.</p><p>Tips: keep weekly gifts small (₱50 to ₱150), announce all the themes and dates at the start, and mix easy themes (<em>something sweet</em>) with funny ones (<em>something that starts with your initial</em>).</p>',
                ),
                'lists'   => array(
                    array( 'Classic themes', array( 'Something sweet', 'Something soft', 'Something red', 'Something green', 'Something round', 'Something hot', 'Something cold', 'Something long', 'Something useful', 'Something you can drink' ) ),
                    array( 'Funny themes', array( 'Something that starts with your initial', 'Something pink and fluffy', 'Something that makes noise', 'Something you would never buy yourself', 'Something under ₱50', 'Something that smells good (or bad)', 'Something from the year you were born', 'Something you can wear on your head', 'Something that glows', 'Something weird from the palengke' ) ),
                    array( 'Thoughtful themes', array( 'Something handmade', 'Something nostalgic', 'Something for self-care', 'Something that reminds you of them', 'Something to read', 'Something for their desk', 'Something for a rainy day', 'Something that grows', 'Something local or Filipino-made', 'A letter or note' ) ),
                    array( 'Food themes', array( 'Something salty', 'Something sour', 'Something spicy', 'Something crunchy', 'Something from your province', 'Something you can bake', 'Something for breakfast', 'Something for merienda', 'Something in a jar', 'Something imported' ) ),
                    array( 'Office and school themes', array( 'Something for work', 'Something for the commute', 'Something for coffee breaks', 'Something blue (like Monday)', 'Something to survive deadlines', 'Something for the weekend', 'Something tech', 'Something for your lunch', 'Something that keeps you awake', 'Something for the holidays' ) ),
                ),
                'faq'     => array(
                    array( 'How many themes do we need?', 'One per week, so usually three or four themes from late November to the Christmas party, plus the main gift on the last day.' ),
                    array( 'How much should weekly gifts cost?', 'Keep them small, around ₱50 to ₱150, and save the main budget (often ₱300 to ₱500) for the final gift.' ),
                    array( 'How do we keep the giver secret every week?', 'Leave gifts on desks early, ask a friend to hand them over, or label them with the receiver&rsquo;s screen name only.' ),
                    array( 'Can Manito Manita remind everyone of the weekly theme?', 'A weekly theme mode with reminders is coming for PRO groups. For now, add the themes to your group details so everyone can see them.' ),
                ),
                'related' => array( 'monito-monita-generator', 'exchange-gift-ideas-300-pesos', 'exchange-gift-ideas-500-pesos' ),
            ),

            'virtual-secret-santa' => array(
                'type'    => 'tool',
                'title'   => 'Virtual Secret Santa for Remote Teams and Family Abroad (Free) | Manito Manita',
                'desc'    => 'Run a virtual Secret Santa for remote teams or family in different countries. Free online name draw, wishlists, addresses for shipping and email notifications.',
                'eyebrow' => 'Virtual Secret Santa',
                'h1'      => 'Virtual Secret Santa <span>for remote teams and family abroad</span>',
                'lead'    => 'Everyone in different places? Draw names online, share wishlists with links that ship anywhere, and let each person see their match privately, no meeting needed.',
                'intro'   => array(
                    'title' => 'How to run a virtual Secret Santa',
                    'body'  => '<p>A <strong>virtual Secret Santa</strong> works like the usual one, except gifts are shipped or sent online. Agree on a budget and a shipping deadline, ask everyone to add a wishlist with links to stores that deliver to them, and add an address so their Secret Santa knows where to send it.</p><p>Plan a short video call for the reveal: everyone opens their gift on camera and guesses who their Secret Santa was. For family abroad, digital gifts like e-gift cards or subscriptions avoid shipping delays.</p>',
                ),
                'faq'     => array(
                    array( 'How do people send gifts in a virtual Secret Santa?', 'Most groups order online and ship directly to the receiver&rsquo;s address, or send a digital gift like an e-gift card. Members can add their address to their profile for their Secret Santa.' ),
                    array( 'Does it work across countries?', 'Yes. The name draw, wishlists and emails work anywhere. Just agree on a budget that makes sense for everyone and a shipping deadline.' ),
                    array( 'Do teammates need an account?', 'No. They join from the group link with a name, screen name, email and a password for that group.' ),
                    array( 'Is it free?', 'Yes, free forever with no limit on members. PRO adds organizer tools like exclusions and reminders.' ),
                ),
                'related' => array( 'secret-santa-generator', 'kris-kringle-generator-philippines', 'monito-monita-themes' ),
            ),
        );
    }

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'template_redirect', array( __CLASS__, 'redirects' ), -1 );
        add_action( 'template_redirect', array( __CLASS__, 'route' ), 0 );
        add_filter( 'wpseo_sitemap_page_content', array( __CLASS__, 'sitemap' ) );
    }

    /**
     * Old URLs that moved (301 keeps their search ranking). /gift-ideas/ was the old Lazada feed.
     */
    public static function redirects() {
        $moved = array( 'gift-ideas' => 'exchange-gift-ideas-500-pesos' );
        $slug = self::slugFromRequest();

        if ( isset( $moved[ $slug ] ) ) {
            wp_redirect( self::url( $moved[ $slug ] ), 301 );
            exit;
        }
    }

    public static function slugFromRequest() {
        return trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    }

    public static function current() {
        return self::$current;
    }

    public static function isActive() {
        return self::$current !== null;
    }

    public static function url( $slug ) {
        return home_url( '/' . $slug . '/' );
    }

    /**
     * Serve /<slug>/ when it is one of our landing pages (WordPress would otherwise 404).
     */
    public static function route() {
        $slug = self::slugFromRequest();
        $pages = self::pages();

        if ( ! isset( $pages[ $slug ] ) ) return;

        self::$current = $pages[ $slug ] + array( 'slug' => $slug );

        global $wp_query;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        status_header( 200 );

        // Our own head tags instead of Yoast's (no WP post behind these URLs).
        add_filter( 'wpseo_frontend_presenters', '__return_empty_array', 999 );
        add_filter( 'wpseo_json_ld_output', '__return_false', 999 );
        // Yoast swaps WordPress's <title> for its own; with Yoast off here we print the title ourselves.
        remove_action( 'wp_head', '_wp_render_title_tag', 1 );
        add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
        add_filter( 'body_class', function ( $c ) { $c[] = 'mm-landing-page'; return $c; } );

        include get_template_directory() . '/template-landing.php';
        exit;
    }

    public static function head() {
        $p = self::$current;
        $url = self::url( $p['slug'] );
        $img = class_exists( 'SEO' ) ? SEO::imageUrl() : '';
        $desc = html_entity_decode( $p['desc'], ENT_QUOTES, 'UTF-8' );
        $title = html_entity_decode( $p['title'], ENT_QUOTES, 'UTF-8' );

        echo '<title>' . esc_html( $title ) . '</title>' . "\n";
        echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        echo '<meta name="robots" content="index, follow, max-image-preview:large">' . "\n";
        echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:locale" content="en_PH">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:site_name" content="Manito Manita">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url( $img ) . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

        $faq = array_map( function ( $q ) {
            return array( '@type' => 'Question', 'name' => html_entity_decode( $q[0], ENT_QUOTES, 'UTF-8' ),
                'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( $q[1], ENT_QUOTES, 'UTF-8' ) ) );
        }, $p['faq'] );

        $graph = array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array( '@type' => 'WebPage', '@id' => $url, 'url' => $url, 'name' => $title, 'description' => $desc, 'inLanguage' => 'en-PH',
                    'dateModified' => self::UPDATED, 'isPartOf' => array( '@id' => home_url( '/' ) . '#website' ) ),
                array( '@type' => 'BreadcrumbList', 'itemListElement' => array(
                    array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
                    array( '@type' => 'ListItem', 'position' => 2, 'name' => wp_strip_all_tags( html_entity_decode( $p['h1'], ENT_QUOTES, 'UTF-8' ) ), 'item' => $url ),
                ) ),
                array( '@type' => 'FAQPage', 'mainEntity' => $faq ),
            ),
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    /**
     * Add the landing pages to Yoast's page-sitemap.xml.
     */
    public static function sitemap( $content ) {
        foreach ( array_keys( self::pages() ) as $slug ) {
            $content .= '<url><loc>' . esc_url( self::url( $slug ) ) . '</loc><lastmod>' . self::UPDATED . 'T00:00:00+08:00</lastmod></url>' . "\n";
        }
        return $content;
    }
}

Landing::init();
