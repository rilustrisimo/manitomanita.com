<?php

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

$dir = 'wp-content/themes/eyor-theme/lazada/assets/categories';

$ignored = array('.', '..', '.svn', '.htaccess');

$files = array();    
foreach (scandir($dir) as $file) {
    if (in_array($file, $ignored)) continue;
    $files[$file] = filemtime($dir . '/' . $file);
}

asort($files);
$files = array_keys($files);

$menus = array();

foreach($files as $cat) {
    $name = str_replace('.php', '', $cat);
    $filename = $name;

    $name = str_replace('cat-', '', $name);
    $name = str_replace('-', ' ', $name);

    $menus[$filename] = ucwords($name);
}

function shuffle_assoc($list) { 
    if (!is_array($list)) return $list; 

    $keys = array_keys($list); 
    shuffle($keys); 
    $random = array(); 
    foreach ($keys as $key) { 
        $random[$key] = $list[$key]; 
    }
    return $random; 
} 

?>

<div class="container" id="product-page-single">
    <div class="row">
        <div class="col-md-12" id="products-container">
            <h1>GIFT IDEAS <span>AT LAZADA</span></h1>
            <a href="<?php echo get_permalink(77);?>" class="shop-btn" target="_blank">SEE MORE</a>
            <?php 
                foreach(shuffle_assoc($menus) as $filename => $catname){
                    get_template_part('lazada/assets/categories/'.$filename);
                    break;
                }
            ?>
        </div>
    </div>
</div>
<script>
var $ = jQuery;

$(document).ready(function(){
    /*
    $('#products-container .lazy').Lazy({
        // your configuration goes here
        scrollDirection: 'vertical',
        effect: 'fadeIn',
        visibleOnly: true,
        onError: function(element) {
            console.log('error loading ' + element.data('src'));
        }
    });
*/
    //checkHeightItems();
});

function checkHeightItems(){
    setTimeout(function(){
        var h = [];

        $('#products-container .product-item').each(function(){
            h[h.length] = $(this).height();
        });

        $('#products-container .product-item').css('height', (Math.max.apply(null, h)+30)+'px');
    }, 1000);
}
</script>