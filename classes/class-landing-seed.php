<?php
/**
 * * LandingSeed Class. ONE-TIME migration: creates the landing and legal pages as real WordPress pages
 * (template "Landing", ACF fields from group_mm_landing.json, Yoast title/description), from the
 * content below. Runs once on the first request after deploy, then records LandingSeed::OPTION.
 *
 * Remove this file (and its entry in Theme::$classes) once the pages are confirmed in WP admin.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class LandingSeed {
    const OPTION  = 'mm_landing_pages_migrated';
    const UPDATED = '2026-11-02';

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
                    array( 'Can we do weekly themes?', 'Yes. With PRO, Manito Manita mode lets the organizer set a theme for each week. Everyone sees this week&rsquo;s theme on the group page and gets an email with matching gift ideas, and you can reveal who gave to whom at the end.' ),
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
                    array( 'Can Manito Manita remind everyone of the weekly theme?', 'Yes, with PRO. Manito Manita mode emails every member on each theme&rsquo;s start date with the theme and gift ideas within the weekly budget, shows this week&rsquo;s theme on the group page, and lets you reveal who gave to whom after the exchange.' ),
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

            'privacy-policy' => array(
                'type'    => 'legal',
                'title'   => 'Privacy Policy | Manito Manita',
                'desc'    => 'How Manito Manita collects, uses and protects your information, in line with the Philippine Data Privacy Act of 2012.',
                'eyebrow' => 'Legal',
                'h1'      => 'Privacy Policy',
                'updated' => '2026-11-02',
                'lead'    => 'We keep this simple: we collect only what a gift exchange needs, we never sell your personal information, and you can ask us to delete it anytime.',
                'sections' => array(
                    array( 'Who we are', '<p>Manito Manita (<a href="https://manitomanita.com">manitomanita.com</a>) is an online gift exchange organizer operated by Rouie Ilustrisimo in the Philippines (&ldquo;we&rdquo;, &ldquo;us&rdquo;). For privacy questions or requests, contact our Data Protection Officer at <a href="mailto:rouie@manitomanita.com">rouie@manitomanita.com</a>.</p><p>We process personal information in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>, its implementing rules, and the issuances of the National Privacy Commission (NPC).</p>' ),
                    array( 'What we collect', '<p><strong>When you create a group:</strong> group name, gift exchange date, spending minimum, your name, your email and an organizer password (stored only as a secure hash).</p><p><strong>When you join a group:</strong> your full name, screen name, email and a password (stored only as a secure hash). Optionally: your wishlist (items, notes and links), your address and contact details for gift delivery, comments you post, and whether you have bought or given your gift.</p><p><strong>Automatically:</strong> technical information such as your IP address, browser and device type, and pages visited, used for security, spam prevention and statistics.</p><p><strong>Payments:</strong> if you buy PRO or credits, PayPal processes your payment. We receive the order ID, amount and status, never your card or bank details.</p>' ),
                    array( 'How we use it', '<ul><li>To run your group: shuffle names, show each member their own match, display wishlists, and send emails such as email confirmation, your match, reminders and password resets.</li><li>To provide PRO features you or your group paid for.</li><li>To keep the service safe: verify emails, block spam and bots, prevent abuse and fix problems.</li><li>To improve the service with aggregated statistics.</li><li>To show gift ideas, including ideas generated from your wishlist text.</li><li>To show ads to free groups.</li></ul><p>Our bases for processing are your <strong>consent</strong> (for example, optional details and reminder emails), the <strong>performance of the service you requested</strong>, our <strong>legitimate interests</strong> in running a safe and reliable service, and <strong>legal obligations</strong>.</p>' ),
                    array( 'Who can see your information', '<ul><li><strong>Your group:</strong> members of your group can see your screen name and wishlist. Anyone with the group link can open the group page, so share the link only with your group.</li><li><strong>Your Secret Santa:</strong> only the person who drew you sees your address and contact details.</li><li><strong>The organizer of a PRO group:</strong> can see members&rsquo; names, emails and matches and export the group&rsquo;s data to run the exchange.</li><li><strong>After the exchange:</strong> if the organizer turns on the reveal, members of the group can see who gave to whom.</li></ul><p>We do <strong>not sell or rent</strong> your personal information.</p>' ),
                    array( 'Service providers', '<p>Like most websites, we rely on trusted service providers who process information on our behalf and only for the purposes below:</p><ul><li><strong>Hosting and storage</strong>, to run the website and keep your group&rsquo;s data.</li><li><strong>Email delivery</strong>, to send confirmation, match and reminder emails.</li><li><strong>Payment processing</strong>, for PRO and credits. Card and bank details are handled by the payment provider, never by us.</li><li><strong>Security and spam protection</strong>, to keep bots and abuse out of our forms.</li><li><strong>Analytics and advertising</strong>, to understand usage and show ads to free groups.</li><li><strong>Gift idea suggestions</strong>, which may use an AI service on your wishlist text with links, emails and phone numbers removed first.</li><li><strong>Online stores and affiliate partners</strong>, only when you click a shopping link.</li></ul><p>These providers are bound to protect your information and may only use it to provide their service to us. Some may process data outside the Philippines with appropriate safeguards. Contact us if you would like more details.</p>' ),
                    array( 'Cookies and similar technologies', '<p>We use a session cookie to keep you signed in to a group, small browser storage entries to remember things like whether you have seen the tour, and cookies from our analytics and advertising providers. Stores you visit through our shopping links may set their own cookies. You can block or delete cookies in your browser settings; parts of the site may not work without the session cookie.</p>' ),
                    array( 'How long we keep it', '<p>We keep group and member information while the group exists, so members can come back to their wishlists and matches. You can ask us to delete your information or your group at any time. We keep payment records as long as required for accounting and legal purposes. We may delete inactive groups after a reasonable period.</p>' ),
                    array( 'How we protect it', '<p>We use HTTPS encryption, store passwords only as secure hashes, check permissions on the server for organizer tools, verify payments with PayPal, and limit who can access our systems. No method of transmission or storage is completely secure, but we work to protect your information and will notify you and the NPC of any personal data breach as required by law.</p>' ),
                    array( 'Your rights', '<p>Under the Data Privacy Act you have the right to be informed, to access your personal information, to object, to correct it, to have it erased or blocked, to data portability, and to claim damages. You also have the right to file a complaint with the <strong>National Privacy Commission</strong> (<a href="https://privacy.gov.ph" rel="noopener" target="_blank">privacy.gov.ph</a>).</p><p>To exercise your rights, email <a href="mailto:rouie@manitomanita.com">rouie@manitomanita.com</a> from the email address you used, and tell us your group name. You can also correct most information yourself in your group, and unsubscribe from reminder emails with the link in each reminder.</p>' ),
                    array( 'Children', '<p>Manito Manita is meant for people aged 13 and over. Members under 18 should use it with the permission of a parent, guardian or teacher, for example when a school group is organized by an adult. If you believe a child gave us information without permission, contact us and we will delete it.</p>' ),
                    array( 'Changes to this policy', '<p>We may update this policy as the service changes. We will post the new version on this page with a new &ldquo;Last updated&rdquo; date, and for significant changes we will give notice on the site.</p>' ),
                ),
                'faq'     => array(),
                'related' => array(),
            ),

            'terms' => array(
                'type'    => 'legal',
                'title'   => 'Terms of Use | Manito Manita',
                'desc'    => 'The terms for using Manito Manita, the free online Secret Santa, Kris Kringle and Monito Monita organizer, including PRO purchases.',
                'eyebrow' => 'Legal',
                'h1'      => 'Terms of Use',
                'updated' => '2026-11-02',
                'lead'    => 'The short version: Manito Manita is free to use, PRO is optional, you are responsible for your group and what you post, and the gift exchange itself is between you and your group.',
                'sections' => array(
                    array( 'Agreement', '<p>These Terms are an agreement between you and Manito Manita, operated by Rouie Ilustrisimo in the Philippines (&ldquo;we&rdquo;, &ldquo;us&rdquo;). By creating or joining a group, or otherwise using <a href="https://manitomanita.com">manitomanita.com</a>, you agree to these Terms and to our <a href="https://manitomanita.com/privacy-policy/">Privacy Policy</a>. If you do not agree, please do not use the service.</p>' ),
                    array( 'The service', '<p>Manito Manita helps groups organize gift exchanges such as Secret Santa, Kris Kringle and Monito Monita: creating groups, inviting members, shuffling names, sharing wishlists, sending emails and showing gift ideas. The core service is <strong>free</strong>. Optional paid features (&ldquo;PRO&rdquo;) are available for groups.</p><p>We may improve, change or discontinue features. If we discontinue a paid feature you already bought, we will give you a fair alternative or a refund for that feature.</p>' ),
                    array( 'Who can use it', '<p>You must be at least 13 years old. If you are under 18, you may use Manito Manita only with the permission of a parent, guardian or teacher. You must give accurate information and use your own email address.</p>' ),
                    array( 'Groups, links and passwords', '<ul><li>Anyone with a group link can open that group&rsquo;s page. Share the link only with people in your group.</li><li>Keep your organizer and member passwords private. You are responsible for activity done with your password.</li><li>The <strong>organizer</strong> is responsible for inviting members, the group details, and using organizer tools fairly. If you add other people&rsquo;s information, you must have their permission.</li></ul>' ),
                    array( 'Your content', '<p>You keep ownership of what you post (wishlists, comments, group details). You give us a limited, non-exclusive, royalty-free license to store, display and process that content only to operate and improve the service for you and your group.</p><p>You agree not to post or use the service for anything that is illegal, harmful, hateful, harassing, sexually explicit, misleading, or that infringes someone else&rsquo;s rights; to send spam; to collect other people&rsquo;s information; or to interfere with the service or its security. We may remove content or suspend groups and accounts that break these rules.</p>' ),
                    array( 'The gift exchange is between you', '<p>Manito Manita only helps you organize. <strong>We are not a party to your gift exchange.</strong> We do not buy, sell, ship or guarantee any gift, and we are not responsible for gifts not given, late or lost deliveries, the value or quality of gifts, purchases you make in stores, or disagreements between members. Please settle those within your group.</p>' ),
                    array( 'Affiliate links and ads', '<p>Some links to stores such as Lazada, Shopee and SHEIN are affiliate links. If you buy through them, we may earn a commission <strong>at no extra cost to you</strong>. This helps keep Manito Manita free. Purchases are between you and the store, under the store&rsquo;s terms. Free groups may also see ads.</p>' ),
                    array( 'PRO and credits', '<ul><li>PRO is a <strong>one-time payment per group</strong> (currently &#8369;199). It is not a subscription. Unshuffle credits are sold in packs (currently &#8369;99 for 3).</li><li>Payments are processed by PayPal. Prices may change in the future, but changes do not affect purchases you already made.</li><li>PRO and credits belong to the group they were bought for, cannot be transferred to another group, and have no cash value. Anyone may buy PRO for a group, for example as a gift to the organizer.</li><li><strong>Refunds:</strong> if PRO or credits did not work as described, email us within <strong>14 days</strong> of purchase with your PayPal receipt and we will fix the problem or refund you. Otherwise, because digital features are delivered immediately, purchases are non-refundable except where required by law.</li></ul>' ),
                    array( 'Availability and disclaimers', '<p>We work hard to keep Manito Manita running smoothly, but the service is provided <strong>&ldquo;as is&rdquo; and &ldquo;as available&rdquo;</strong>, without warranties of any kind, to the fullest extent allowed by law. We do not guarantee that the service will be uninterrupted or error-free, that emails will always be delivered (they may land in spam), or that gift ideas, prices or store availability are accurate.</p>' ),
                    array( 'Limitation of liability', '<p>To the fullest extent allowed by law, we are not liable for indirect, incidental, special or consequential damages, or for lost profits, data or goodwill, arising from your use of the service. Our total liability for any claim relating to the service is limited to the amount you paid us for the affected group in the 12 months before the claim, or &#8369;1,000 if you paid nothing. Nothing in these Terms limits liability that cannot be limited by law.</p>' ),
                    array( 'Your responsibility to us', '<p>If you break these Terms or the law while using Manito Manita, or if your content causes a claim against us, you agree to cover the reasonable costs and damages that result, to the extent allowed by law.</p>' ),
                    array( 'Ending use', '<p>You can stop using Manito Manita at any time and ask us to delete your information. We may suspend or end access for groups or users that break these Terms, abuse the service, or put other users at risk.</p>' ),
                    array( 'Changes and governing law', '<p>We may update these Terms. We will post the new version here with a new &ldquo;Last updated&rdquo; date; continuing to use the service means you accept the updated Terms. These Terms are governed by the laws of the Republic of the Philippines. We prefer to resolve concerns informally first, so please contact us at <a href="mailto:rouie@manitomanita.com">rouie@manitomanita.com</a>. Any dispute that cannot be resolved informally will be brought before the proper courts of the Philippines.</p>' ),
                ),
                'faq'     => array(),
                'related' => array(),
            ),
        );
    }

    public static function init() {
        add_action( 'wp_loaded', array( __CLASS__, 'maybeMigrate' ) );
    }

    public static function maybeMigrate() {
        if ( get_option( self::OPTION ) || ! function_exists( 'update_field' ) ) return;
        if ( get_transient( 'mm_landing_migrating' ) ) return;
        set_transient( 'mm_landing_migrating', 1, 300 );

        $ids = array();
        $menu = 10;

        foreach ( self::pages() as $slug => $p ) {
            $content = '';
            if ( $p['type'] === 'legal' ) {
                foreach ( $p['sections'] as $n => $sec ) {
                    $content .= '<h2>' . ( $n + 1 ) . '. ' . esc_html( $sec[0] ) . "</h2>\n" . $sec[1] . "\n\n";
                }
            } else {
                $content = $p['intro']['body'];
            }

            // Reuse an existing page with this slug (any status, e.g. WordPress's draft privacy page).
            $existing = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );

            $post = array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_name'    => $slug,
                'post_title'   => wp_strip_all_tags( html_entity_decode( $p['h1'], ENT_QUOTES, 'UTF-8' ) ),
                'post_content' => $content,
                'menu_order'   => $menu++,
            );

            if ( $existing ) {
                $post['ID'] = (int) $existing[0];
                $id = wp_update_post( $post, true );
            } else {
                $id = wp_insert_post( $post, true );
            }

            if ( is_wp_error( $id ) || ! $id ) continue;

            $ids[ $slug ] = (int) $id;
            update_post_meta( $id, '_wp_page_template', 'template-landing.php' );
            update_post_meta( $id, '_yoast_wpseo_title', html_entity_decode( $p['title'], ENT_QUOTES, 'UTF-8' ) );
            update_post_meta( $id, '_yoast_wpseo_metadesc', html_entity_decode( $p['desc'], ENT_QUOTES, 'UTF-8' ) );

            update_field( 'field_mm_land_type', $p['type'], $id );
            update_field( 'field_mm_land_eyebrow', $p['eyebrow'], $id );
            update_field( 'field_mm_land_h1', $p['h1'], $id );
            update_field( 'field_mm_land_lead', $p['lead'], $id );
            update_field( 'field_mm_land_updated', str_replace( '-', '', $p['updated'] ?? self::UPDATED ), $id );
            if ( ! empty( $p['intro']['title'] ) ) update_field( 'field_mm_land_intro_title', $p['intro']['title'], $id );
            if ( ! empty( $p['budget'] ) ) update_field( 'field_mm_land_budget', (int) $p['budget'], $id );

            if ( ! empty( $p['groups'] ) ) {
                update_field( 'field_mm_land_groups', array_map( function ( $g ) {
                    return array(
                        'field_mm_land_group_title' => $g[0],
                        'field_mm_land_group_ideas' => implode( "\n", array_map( function ( $i ) { return $i[0] . ' | ' . $i[1]; }, $g[1] ) ),
                    );
                }, $p['groups'] ), $id );
            }

            if ( ! empty( $p['lists'] ) ) {
                update_field( 'field_mm_land_lists', array_map( function ( $l ) {
                    return array( 'field_mm_land_list_title' => $l[0], 'field_mm_land_list_items' => implode( "\n", $l[1] ) );
                }, $p['lists'] ), $id );
            }

            if ( ! empty( $p['faq'] ) ) {
                update_field( 'field_mm_land_faq', array_map( function ( $q ) {
                    return array( 'field_mm_land_faq_q' => $q[0], 'field_mm_land_faq_a' => $q[1] );
                }, $p['faq'] ), $id );
            }

            if ( $slug === 'privacy-policy' ) update_option( 'wp_page_for_privacy_policy', (int) $id );
        }

        // Second pass: related pages, now that every page has an ID.
        foreach ( self::pages() as $slug => $p ) {
            if ( empty( $ids[ $slug ] ) || empty( $p['related'] ) ) continue;
            $rel = array_values( array_filter( array_map( function ( $r ) use ( $ids ) { return $ids[ $r ] ?? 0; }, $p['related'] ) ) );
            update_field( 'field_mm_land_related', $rel, $ids[ $slug ] );
        }

        if ( count( $ids ) === count( self::pages() ) ) {
            update_option( self::OPTION, array( 'time' => time(), 'ids' => $ids ), false );
        }
        delete_transient( 'mm_landing_migrating' );
    }
}

LandingSeed::init();
