<?php
/**
 * PRO Actions sheet (#pro-actions). Groups::get_pro_list() fills .pop-container__content with the tool
 * menu; each tool opens inside the same sheet (Theme.proView) with a back button.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   2.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();
?>

<div class="pop-container mm-upsell mm-prot-sheet" id="pro-actions" role="dialog" aria-modal="true" aria-label="PRO Actions">
    <div class="pop-container__inner mm-upsell__inner">
        <div class="pop-container__close"><a href="#" aria-label="Close"><i class="fa-solid fa-xmark"></i></a></div>
        <div class="pop-container__content">
            <p class="mm-prot__loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading PRO tools&hellip;</p>
        </div>
    </div>
</div>
