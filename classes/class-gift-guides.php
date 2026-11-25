<?php
/**
 * GiftGuides Class. Gift idea pages for the searches people make right before buying: "something soft"
 * style Monito Monita themes, office / kids / teacher / funny exchange gifts, and a Monito Monita gift
 * ideas hub that links them all. Every idea has Shopee and Lazada buttons through Involve (Affiliate::anchor,
 * placement guide-<slug>), so these pages earn on the clicks they get.
 *
 * Pages are created once by Landing::seed (type "guide", template-landing.php); edits in WP Admin are kept.
 * Ideas: "Label | store search words | max price" (the price is optional; the page budget is the default).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class GiftGuides {
    const HUB = 'monito-monita-gift-ideas';

    /** Theme name (as written on the themes page) => page slug. */
    public static function themes() {
        return array(
            'something soft'  => 'something-soft-gift-ideas',
            'something hard'  => 'something-hard-gift-ideas',
            'something long'  => 'something-long-gift-ideas',
            'something round' => 'something-round-gift-ideas',
            'something sweet' => 'something-sweet-gift-ideas',
            'something red'   => 'something-red-gift-ideas',
            'something green' => 'something-green-gift-ideas',
            'something shiny' => 'something-shiny-gift-ideas',
            'something useful' => 'something-useful-gift-ideas',
            'something wet'   => 'something-wet-gift-ideas',
        );
    }

    /** Link for a theme on the themes page, or '' when it has no published page. */
    public static function themeUrl( $name ) {
        $key = strtolower( trim( wp_strip_all_tags( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) ) ) );
        $map = self::themes();
        if ( ! isset( $map[ $key ] ) ) return '';
        $page = get_page_by_path( $map[ $key ] );

        return ( $page && $page->post_status === 'publish' ) ? get_permalink( $page ) : '';
    }

    /** Extra display settings for a page (heading over the ideas, line under it), by slug. */
    public static function display( $slug ) {
        $p = self::pages();

        return isset( $p[ $slug ] ) ? array_intersect_key( $p[ $slug ], array_flip( array( 'heading', 'sub' ) ) ) : array();
    }

    public static function pages() {
        static $pages = null;
        if ( $pages !== null ) return $pages;

        return $pages = self::themePages() + self::audiencePages();
    }

    /**
     * "Something ___" weekly themes. Weekly gifts are small, so ideas are split under ₱100 and ₱100 to ₱200.
     */
    protected static function themePages() {
        $slugs = array_values( self::themes() );
        $t = array(
            // slug suffix => array( theme, kw, lead, body paragraph, under-100 ideas, 100-200 ideas, extra FAQ )
            'soft' => array( 'Something soft', 'something soft gift ideas',
                'Cozy, squishy and easy to wrap. Soft gifts are the easiest Monito Monita theme to get right, even for someone you barely know.',
                'Think about what they&rsquo;d touch every day: socks for an air-conditioned office, a small pillow for the commute, a face towel for the gym bag. Plush toys work for kids and for anyone who keeps stuff on their desk.',
                "Fuzzy socks | fuzzy socks\nPlush keychain | plush keychain\nScrunchie set | scrunchies set\nMarshmallows | marshmallows",
                "Small plush toy | small plush toy\nNeck pillow | travel neck pillow\nIndoor slippers | soft indoor slippers\nFace towel set | microfiber face towel\nThrow pillow cover | throw pillow cover",
                array( 'Does food count as something soft?', 'Yes, most groups accept it: marshmallows, mamon, ensaymada or soft cookies all count and are always welcome.' ) ),
            'hard' => array( 'Something hard', 'something hard gift ideas',
                'Mugs, tumblers, hard candy and notebooks with a hard cover. Easy to find, and most of them get used every day.',
                'The easy route is anything ceramic, metal or hardbound. Hard candy is the classic joke answer that still makes everyone happy, and a mug or small tumbler is the safe pick for a coworker.',
                "Hard candy | hard candy\nBallpen set | ballpen set\nBottle opener keychain | bottle opener keychain\nPhone ring holder | phone ring holder",
                "Ceramic mug | ceramic mug\nSmall stainless tumbler | stainless tumbler\nHardbound notebook | hardbound notebook\nPhone case | phone case\nPuzzle cube | puzzle cube",
                array( 'What is a funny something hard gift?', 'Hard candy, a hard-boiled egg-shaped stress toy, or a &ldquo;hard&rdquo; puzzle. Keep it office-safe and add a short note so the joke lands.' ) ),
            'long' => array( 'Something long', 'something long gift ideas',
                'Charging cables, umbrellas, scarves and Pocky. Long gifts are easy to find, and a 2-meter cable is one of the most useful gifts under ₱200.',
                'Most long things are also practical: a long charging cable for the bed, a folding umbrella for the rainy season, a lanyard for the office ID. For something sweet and long, biscuit sticks and long candy canes are cheap and fun to wrap.',
                "Biscuit sticks (Pocky) | pocky\nLanyard | lanyard\nColored pencils | colored pencils set\nChopsticks set | chopsticks set",
                "2-meter charging cable | 2m charging cable\nFolding umbrella | folding umbrella\nScarf | scarf\nLong socks | long socks\nSelfie stick | selfie stick",
                array( 'What can I give for something long under ₱100?', 'Biscuit sticks like Pocky, a lanyard, colored pencils, a chopsticks set or a long candy cane all fit under ₱100.' ) ),
            'round' => array( 'Something round', 'something round gift ideas',
                'Stress balls, cookies in a round tin, coasters and yo-yos. Round is an easy theme with lots of cheap, cute options.',
                'Round snacks are the fastest answer: cookies, chocolate balls or a round tin of butter cookies. For something that lasts longer, a stress ball, coaster set or a round mini clock fits on any desk.',
                "Stress ball | stress ball\nBath bomb | bath bomb\nYo-yo | yoyo\nRound chocolates | chocolate balls",
                "Butter cookies in a tin | butter cookies tin\nCoaster set | coaster set\nMini desk clock | mini alarm clock\nCompact mirror | compact mirror\nRound coin purse | round coin purse",
                array( 'Is fruit okay for something round?', 'Yes. An orange, an apple or a small bag of grapes is a classic round gift, and it fits any budget.' ) ),
            'sweet' => array( 'Something sweet', 'something sweet gift ideas',
                'Chocolates, polvoron, pastillas and cookies. The most loved Monito Monita theme, and the easiest to shop for on the way to work.',
                'Local sweets feel more thoughtful than a random chocolate bar: polvoron, pastillas, yema or a jar of cookies. If you know they&rsquo;re on a diet, go for honey or dark chocolate instead.',
                "Chocolate bar | chocolate bar\nPastillas | pastillas\nGummy candies | gummy candy\nYema | yema candy",
                "Chocolate gift box | chocolate gift box\nPolvoron box | polvoron\nCookies in a jar | cookies in jar\nHoney | pure honey\nMallows and choco set | chocolate snack box",
                array( 'What if my Monito or Monita is diabetic or on a diet?', 'Go for sugar-free candies, dark chocolate, honey or sweet-smelling things like a scented candle, which most groups accept as &ldquo;sweet&rdquo;.' ) ),
            'red' => array( 'Something red', 'something red gift ideas',
                'A red mug, a red tumbler, red socks or a lip tint. Red is a Christmas favorite, so there are plenty of options in December.',
                'Pick something they can use and make the color the fun part: a red mug or tumbler, a red pouch for chargers, a red scarf for the cold office. Red lip tint is a safe pick for most women, and red socks work for anyone.',
                "Red ballpens | red ballpen\nRed lip tint | red lip tint\nRed socks | red socks\nRed scrunchies | red scrunchie",
                "Red mug | red mug\nRed tumbler | red tumbler\nRed pouch | red pouch\nRed scarf | red scarf\nRed notebook | red notebook",
                array( 'Can I give red food for something red?', 'Yes: strawberries, apples, red velvet cookies or a pack of red candies all count.' ) ),
            'green' => array( 'Something green', 'something green gift ideas',
                'Succulents, matcha treats, green tea and green tumblers. Fresh, a little different, and perfect for the Christmas colors.',
                'A small succulent is the classic green gift: cheap, cute and alive. Matcha snacks or green tea are an easy food option, and a green tumbler or pouch is something they&rsquo;ll keep using after the party.',
                "Matcha snacks | matcha snack\nGreen tea bags | green tea\nGreen highlighters | green highlighter\nMini succulent | succulent",
                "Succulent in a pot | succulent pot\nGreen tumbler | green tumbler\nMatcha powder | matcha powder\nGreen pouch | green pouch\nGreen mug | green mug",
                array( 'Is money okay for something green?', 'Some barkadas joke about it, but most offices prefer a real gift. If your group agrees, a crisp bill in a nice envelope is a fun twist.' ) ),
            'shiny' => array( 'Something shiny', 'something shiny gift ideas',
                'Fairy lights, earrings, lip gloss and holographic stickers. Shiny gifts look more expensive than they are.',
                'Fairy lights are the crowd-pleaser: everyone decorates their desk or room in December. Small earrings or a metallic tumbler are good for someone you know a little better, and glitter pens are perfect for students.',
                "Glitter pens | glitter pens\nLip gloss | lip gloss\nHolographic stickers | holographic stickers\nMetal keychain | metal keychain",
                "Fairy lights | fairy lights\nEarrings | earrings\nMetallic tumbler | metallic tumbler\nCompact mirror | compact mirror\nHolographic pouch | holographic pouch",
                array( 'What shiny gift works for men?', 'A metal keychain, a stainless tumbler, a metal bottle opener or LED fairy lights for their desk.' ) ),
            'useful' => array( 'Something useful', 'something useful gift ideas',
                'Cable clips, a mini fan, a lunch bag or an eco bag. Useful gifts are the ones people actually thank you for after the party.',
                'Think about their day: the commute (mini fan, umbrella), the office (desk organizer, cable clips), lunch (lunch bag, reusable straw set). When in doubt, something useful under ₱200 beats something cute they&rsquo;ll never open.',
                "Cable clips | cable clips organizer\nAlcohol spray bottle | alcohol spray bottle\nReusable straw set | reusable straw set\nPhone stand | phone stand",
                "Mini handheld fan | mini fan\nLunch bag | lunch bag\nDesk organizer | desk organizer\nFoldable eco bag | foldable eco bag\nTumbler brush set | bottle cleaning brush",
                array( 'What is the most useful gift under ₱200?', 'A mini fan, a long charging cable, a lunch bag or a desk organizer. People use them every day.' ) ),
            'wet' => array( 'Something wet', 'something wet gift ideas',
                'Wet wipes, sheet masks, facial mist and drinks. A classic funny theme, with plenty of office-safe answers.',
                'Keep it light and useful: wet wipes and hand sanitizer for the commute, sheet masks or facial mist for self-care, or a canned coffee or juice to drink on the spot. A short funny note makes the theme land.',
                "Wet wipes | wet wipes\nSheet masks | sheet mask\nHand sanitizer gel | hand sanitizer gel\nCanned coffee | canned coffee",
                "Facial mist | facial mist\nBody mist | body mist\nShower gel | shower gel\nMini humidifier | mini humidifier\nWater bottle | water bottle",
                array( 'Is something wet okay for the office?', 'Yes, as long as the gift is office-safe: wipes, drinks, sheet masks or a facial mist. Keep jokes for the note, not the gift.' ) ),
        );

        $pages = array();
        foreach ( $t as $k => $v ) {
            list( $name, $kw, $lead, $tip, $under100, $under200, $extra ) = $v;
            $slug = 'something-' . $k . '-gift-ideas';
            $low = strtolower( $name );
            $others = array_values( array_diff( $slugs, array( $slug ) ) );
            $i = array_search( $slug, $slugs, true );
            $pages[ $slug ] = array(
                'type'    => 'guide',
                'budget'  => 200,
                'kw'      => $kw,
                'title'   => ucwords( $low ) . ' Gift Ideas for Monito Monita (Under ₱200, 2026) | Manito Manita',
                'desc'    => ucfirst( $low ) . ' gift ideas for your weekly Monito Monita or Manito Manita theme, under ₱100 and ₱200, with one-tap links to shop each idea on Shopee and Lazada.',
                'eyebrow' => 'Monito Monita theme &middot; ' . $low,
                'h1'      => ucfirst( $low ) . ' gift ideas <span>for Monito Monita</span>',
                'lead'    => $lead,
                'intro'   => 'What to give for &ldquo;' . $low . '&rdquo;',
                'body'    => '<p><strong>' . ucfirst( $low ) . '</strong> is one of the most common weekly themes for Monito Monita (also called Manito Manita) in Filipino offices, schools and families. Weekly gifts are usually small, around &#8369;50 to &#8369;150, so the ideas below stay under &#8369;200.</p>'
                    . '<p>' . $tip . '</p>'
                    . '<p>More themes: <a href="/monito-monita-themes/">50+ Monito Monita themes</a>. Shopping for the main gift? See <a href="/' . self::HUB . '/">Monito Monita gift ideas by budget</a>.</p>',
                'heading' => ucfirst( $low ) . ' gift ideas',
                'sub'     => 'Each button opens Shopee or Lazada already filtered to the price in that section.',
                'groups'  => array(
                    array( 'Under &#8369;100', self::cap( $under100, 100 ) ),
                    array( '&#8369;100 to &#8369;200', self::cap( $under200, 200 ) ),
                ),
                'faq'     => array(
                    array( 'How much should a ' . $low . ' gift cost?', 'Weekly Monito Monita gifts are usually &#8369;50 to &#8369;150. Save the bigger budget for the main gift on the last day.' ),
                    $extra,
                    array( 'Can Manito Manita remind everyone of this week&rsquo;s theme?', 'Yes, with PRO Weekly Drops: every member gets an email on each theme&rsquo;s start date with the theme and gift ideas within the weekly budget.' ),
                ),
                // Hub, then the next two themes in the list.
                'related' => array( self::HUB, $others[ $i % count( $others ) ], $others[ ( $i + 1 ) % count( $others ) ] ),
            );
        }

        return $pages;
    }

    /** Adds the price cap to every "Label | words" line. */
    protected static function cap( $lines, $max ) {
        return implode( "\n", array_map( function ( $l ) use ( $max ) { return trim( $l ) . ' | ' . $max; }, explode( "\n", $lines ) ) );
    }

    protected static function audiencePages() {
        $free = array( 'Can Manito Manita pick gift ideas for my match?', 'Yes. Once names are shuffled, your match screen shows their wishlist plus gift ideas within your group&rsquo;s budget, with links to Shopee and Lazada.' );
        $p = array();

        $p[ self::HUB ] = array(
            'type'    => 'guide',
            'budget'  => 500,
            'kw'      => 'monito monita gift ideas',
            'title'   => 'Monito Monita Gift Ideas 2026: By Budget and Weekly Theme | Manito Manita',
            'desc'    => 'Monito Monita (Manito Manita) gift ideas for 2026: weekly theme gifts under ₱150 and main gifts under ₱300, ₱500 and ₱1,000, with links to shop each idea on Shopee and Lazada.',
            'eyebrow' => 'Monito Monita &middot; Gift ideas',
            'h1'      => 'Monito Monita gift ideas <span>for every budget</span>',
            'lead'    => 'Small gifts for each weekly theme, and the main gift for the Christmas party. Every idea opens Shopee or Lazada already filtered to your budget.',
            'intro'   => 'How to shop for your Monito or Monita',
            'body'    => '<p><strong>Monito Monita</strong> (or <strong>Manito Manita</strong>) is the Filipino Secret Santa: you draw a name, give small gifts each week based on a theme, then give a main gift at the Christmas party. That means two kinds of shopping: cheap, fun weekly gifts, and one gift that fits the group budget.</p>'
                . '<p>Start with your match&rsquo;s wishlist if they have one. If not, think about their day: the commute, their desk, what they eat for merienda. Useful and consumable gifts almost always land well.</p>'
                . '<p><strong>Weekly themes:</strong> <a href="/something-soft-gift-ideas/">something soft</a>, <a href="/something-hard-gift-ideas/">something hard</a>, <a href="/something-long-gift-ideas/">something long</a>, <a href="/something-round-gift-ideas/">something round</a>, <a href="/something-sweet-gift-ideas/">something sweet</a>, <a href="/something-red-gift-ideas/">something red</a>, <a href="/something-green-gift-ideas/">something green</a>, <a href="/something-shiny-gift-ideas/">something shiny</a>, <a href="/something-useful-gift-ideas/">something useful</a>, <a href="/something-wet-gift-ideas/">something wet</a>, and <a href="/monito-monita-themes/">50+ more themes</a>.</p>'
                . '<p><strong>By budget:</strong> <a href="/exchange-gift-ideas-300-pesos/">under &#8369;300</a>, <a href="/exchange-gift-ideas-500-pesos/">under &#8369;500</a>, <a href="/exchange-gift-ideas-1000-pesos/">under &#8369;1,000</a>. <strong>By person:</strong> <a href="/office-exchange-gift-ideas/">officemates</a>, <a href="/exchange-gift-ideas-for-kids/">kids</a>, <a href="/exchange-gift-ideas-for-teachers/">teachers</a>, <a href="/funny-exchange-gift-ideas/">funny gifts</a>.</p>',
            'heading' => 'Monito Monita gift ideas by budget',
            'sub'     => 'Each button opens Shopee or Lazada already filtered to the price in that section.',
            'groups'  => array(
                array( 'Weekly theme gifts, under &#8369;150', "Fuzzy socks | fuzzy socks | 150\nChocolate bar | chocolate bar | 150\nMini succulent | succulent | 150\nCable clips | cable clips organizer | 150\nSheet masks | sheet mask | 150\nPocky | pocky | 150" ),
                array( 'Main gift under &#8369;300', "Ceramic mug | ceramic mug | 300\nNeck pillow | travel neck pillow | 300\nScented candle | scented candle | 300\nPlanner | planner | 300\nCoffee sampler | coffee sampler | 300\nFairy lights | fairy lights | 300" ),
                array( 'Main gift under &#8369;500', "Insulated tumbler | insulated tumbler | 500\nMini bluetooth speaker | mini bluetooth speaker | 500\nDesk organizer | desk organizer | 500\nSkincare gift set | skincare gift set | 500\nWallet | wallet | 500\nChocolate gift box | chocolate gift box | 500" ),
                array( 'Main gift under &#8369;1,000', "Wireless earbuds | wireless earbuds | 1000\nPower bank | power bank | 1000\nPerfume | perfume | 1000\nLaptop backpack | laptop backpack | 1000\nAir pot or kettle | electric kettle | 1000\nSmart LED lamp | desk lamp | 1000" ),
            ),
            'faq'     => array(
                array( 'What is a good Monito Monita gift?', 'Something from their wishlist first. Otherwise, a useful everyday item within the budget: a tumbler, mug, desk organizer, snacks, skincare or a small gadget.' ),
                array( 'How much do Monito Monita gifts cost?', 'Weekly theme gifts are usually &#8369;50 to &#8369;150, and the main gift follows the group budget, most often &#8369;300 to &#8369;500 in offices and schools.' ),
                array( 'Is it Monito Monita or Manito Manita?', 'Both are used. Monito Monita and Manito Manita are the same Filipino Secret Santa tradition; the spelling depends on the region and family.' ),
                $free,
            ),
            'related' => array( 'monito-monita-themes', 'exchange-gift-ideas-500-pesos', 'office-exchange-gift-ideas' ),
        );

        $p['office-exchange-gift-ideas'] = array(
            'type'    => 'guide',
            'budget'  => 500,
            'kw'      => 'office exchange gift ideas',
            'title'   => 'Office Exchange Gift Ideas for Coworkers (Under ₱500, 2026) | Manito Manita',
            'desc'    => 'Exchange gift ideas for officemates and coworkers under ₱500: desk, coffee break and commute gifts that are safe for anyone, with links to shop on Shopee and Lazada.',
            'eyebrow' => 'Office &middot; Coworkers',
            'h1'      => 'Office exchange gift ideas <span>for coworkers</span>',
            'lead'    => 'Drew someone from another department? These gifts are useful, neutral and fine to open in front of the whole office.',
            'intro'   => 'How to pick a gift for a coworker',
            'body'    => '<p>Office Kris Kringle and Monito Monita are tricky because you often don&rsquo;t know your match well, and the gift is opened in front of everyone. The safest picks are things people use at work every day: something for the desk, the coffee break or the commute.</p>'
                . '<p>Avoid very personal gifts (clothes, perfume) unless you know their taste. If your office has a wishlist on Manito Manita, start there. Most offices set &#8369;300 to &#8369;500 for the main gift.</p>',
            'heading' => 'Gifts for officemates under &#8369;500',
            'groups'  => array(
                array( 'For the desk', "Desk organizer | desk organizer\nLaptop stand | laptop stand\nWireless mouse | wireless mouse\nDesk plant (succulent) | succulent pot\nMouse pad with wrist rest | mouse pad wrist rest\nMonitor light bar | monitor light bar" ),
                array( 'For coffee breaks', "Insulated tumbler | insulated tumbler\nMug warmer | mug warmer\nCoffee sampler | coffee sampler\nTea gift set | tea gift set\nSnack box | snack box\nFrench press | french press" ),
                array( 'For the commute', "Power bank | power bank\nFolding umbrella | folding umbrella\nNeck pillow | travel neck pillow\nMini fan | mini fan\nLunch bag | insulated lunch bag\nEarphones | earphones" ),
            ),
            'faq'     => array(
                array( 'What is a safe exchange gift for a coworker?', 'A tumbler, a desk organizer, a power bank, a coffee or tea set, or snacks. They&rsquo;re useful for anyone and fine to open in front of the office.' ),
                array( 'What should I avoid giving a coworker?', 'Clothing sizes, strong perfume, anything too personal or a joke that could embarrass them in front of the office.' ),
                array( 'How do we run the office exchange online?', 'Create a free group, share the link in your team chat, and everyone joins and adds a wishlist. The organizer shuffles once and each person sees only their own match.' ),
                $free,
            ),
            'related' => array( self::HUB, 'exchange-gift-ideas-500-pesos', 'funny-exchange-gift-ideas' ),
        );

        $p['exchange-gift-ideas-for-kids'] = array(
            'type'    => 'guide',
            'budget'  => 300,
            'kw'      => 'exchange gift ideas for kids',
            'title'   => 'Exchange Gift Ideas for Kids: Class Christmas Party Under ₱300 | Manito Manita',
            'desc'    => 'Exchange gift ideas for kids and the class Christmas party, by age, under ₱300: toys, school supplies and crafts, with links to shop on Shopee and Lazada.',
            'eyebrow' => 'Kids &middot; Class party',
            'h1'      => 'Exchange gift ideas <span>for kids</span>',
            'lead'    => 'For the class Christmas party or the cousins&rsquo; Monito Monita. Ideas by age that kids actually play with, under &#8369;300.',
            'intro'   => 'Picking a gift for a child you don&rsquo;t know',
            'body'    => '<p>Class exchanges usually set &#8369;100 to &#8369;300, and you often don&rsquo;t know the child well. Pick something that works for any kid that age: art and craft sets, puzzles, books or school supplies with a fun design.</p>'
                . '<p>Avoid small parts for kids under 4, and anything that needs batteries that aren&rsquo;t included. Gender-neutral gifts are safest when you don&rsquo;t know the child.</p>',
            'heading' => 'Gift ideas for kids under &#8369;300',
            'groups'  => array(
                array( 'Ages 3 to 6', "Coloring book with crayons | coloring book crayons\nPlay dough set | play dough set\nWooden puzzle | wooden puzzle kids\nStory book | story book kids\nBubble toy | bubble machine toy" ),
                array( 'Ages 7 to 10', "Art set | art set kids\nBuilding blocks | building blocks\nScience kit | science kit kids\nCard game | uno cards\nWater bottle for school | kids water bottle" ),
                array( 'Ages 11 to 13', "Journal with gel pens | journal gel pens\nPuzzle cube | puzzle cube\nDIY bracelet kit | bracelet making kit\nMini bluetooth speaker | mini bluetooth speaker\nSling bag | kids sling bag" ),
            ),
            'faq'     => array(
                array( 'What is a good class exchange gift?', 'Art sets, coloring books, puzzles, building blocks, card games or a fun water bottle. They work for most kids and fit &#8369;100 to &#8369;300.' ),
                array( 'Is it okay to give school supplies?', 'Yes, if they&rsquo;re fun: gel pens, a cute pencil case or a journal feel like a gift, while plain notebooks feel like homework.' ),
                array( 'Can parents or teachers run the class draw online?', 'Yes. A teacher or class parent creates a free group, shares the link with parents, and each family joins for their child.' ),
            ),
            'related' => array( self::HUB, 'exchange-gift-ideas-300-pesos', 'exchange-gift-ideas-for-teachers' ),
        );

        $p['exchange-gift-ideas-for-teachers'] = array(
            'type'    => 'guide',
            'budget'  => 500,
            'kw'      => 'gift ideas for teachers',
            'title'   => 'Christmas Gift Ideas for Teachers Philippines (Under ₱500) | Manito Manita',
            'desc'    => 'Christmas and exchange gift ideas for teachers under ₱500: classroom, self-care and food gifts teachers actually use, with links to shop on Shopee and Lazada.',
            'eyebrow' => 'Teachers &middot; Faculty exchange',
            'h1'      => 'Gift ideas <span>for teachers</span>',
            'lead'    => 'For the faculty Monito Monita, or a thank-you from the class. Gifts teachers actually use, under &#8369;500.',
            'intro'   => 'What teachers really want',
            'body'    => '<p>Teachers get a lot of mugs in December. What they use is things for long days on their feet and a lot of checking: good pens, a planner, a tumbler that keeps coffee hot, and a bit of self-care.</p>'
                . '<p>For a class gift, pool the budget and give one nicer gift with a card signed by the students. For the faculty exchange, the ideas below work for any teacher.</p>',
            'heading' => 'Gifts for teachers under &#8369;500',
            'groups'  => array(
                array( 'For the classroom', "Gel pen set | gel pen set\nTeacher planner | teacher planner\nStamp set | teacher stamp set\nDesk organizer | desk organizer\nRechargeable lamp | rechargeable desk lamp" ),
                array( 'For self-care', "Insulated tumbler | insulated tumbler\nFoot massager | foot massager\nHand cream set | hand cream set\nScented candle | scented candle\nThroat lozenges set | throat lozenges" ),
                array( 'Food gifts', "Coffee sampler | coffee sampler\nTea gift set | tea gift set\nChocolate gift box | chocolate gift box\nCookies in a jar | cookies in jar\nHoney | pure honey" ),
            ),
            'faq'     => array(
                array( 'What is a good Christmas gift for a teacher?', 'A tumbler, good pens, a planner, self-care items or a coffee or tea set. A handwritten note from the student makes any gift better.' ),
                array( 'How much should a gift for a teacher cost?', 'Anything from &#8369;150 to &#8369;500 is common. For a class gift, students can pool money for one nicer gift.' ),
                array( 'Can we run a faculty Monito Monita online?', 'Yes. Create a free group, share the link in the faculty group chat, and everyone joins and adds a wishlist before the shuffle.' ),
            ),
            'related' => array( self::HUB, 'exchange-gift-ideas-for-kids', 'office-exchange-gift-ideas' ),
        );

        $p['funny-exchange-gift-ideas'] = array(
            'type'    => 'guide',
            'budget'  => 300,
            'kw'      => 'funny exchange gift ideas',
            'title'   => 'Funny Exchange Gift Ideas That Are Still Useful (Under ₱300) | Manito Manita',
            'desc'    => 'Funny exchange gift ideas for the office, barkada or family Monito Monita, under ₱300. Office-safe gifts that get a laugh and still get used, with links to Shopee and Lazada.',
            'eyebrow' => 'Funny &middot; Office-safe',
            'h1'      => 'Funny exchange gift ideas <span>that still get used</span>',
            'lead'    => 'Get the laugh when they open it, and still give something they&rsquo;ll keep. Office-safe picks under &#8369;300.',
            'intro'   => 'How to give a funny gift without it falling flat',
            'body'    => '<p>The best funny gifts are useful things with a twist: a mug with a funny line, a giant pencil, a toilet-paper-shaped tissue holder. Everyone laughs, and the gift doesn&rsquo;t end up in a drawer.</p>'
                . '<p>Keep it safe for the whole room: no jokes about weight, age or love life. If you&rsquo;re unsure, make the gift normal and put the joke in the card.</p>',
            'heading' => 'Funny gifts under &#8369;300',
            'groups'  => array(
                array( 'Desk and office', "Funny mug | funny mug\nGiant pencil | giant pencil\nStress toy | squishy stress toy\nFunny socks | funny socks\nTiny desk punching bag | desk punching bag" ),
                array( 'For the barkada', "Party card game | party card game\nWhoopee cushion | whoopee cushion\nBanana phone case | funny phone case\nKaraoke microphone | wireless karaoke microphone\nFunny sticker pack | funny stickers" ),
            ),
            'faq'     => array(
                array( 'What is a funny but useful exchange gift?', 'A funny mug, silly socks, a stress toy, a party card game or a giant pencil. They get a laugh and still get used.' ),
                array( 'Is a funny gift okay for the office?', 'Yes, if anyone could open it in front of the boss. Avoid jokes about looks, age or relationships.' ),
                $free,
            ),
            'related' => array( self::HUB, 'office-exchange-gift-ideas', 'monito-monita-themes' ),
        );

        return $p;
    }
}
