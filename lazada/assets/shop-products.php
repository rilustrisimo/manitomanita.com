<?php

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

$dir = 'wp-content/themes/eyor-theme/lazada/assets/categories';

$ignored = array('.', '..', '.svn', '.htaccess','.DS_Store');

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

if(isset($_GET['cat'])){
    $showcat = $_GET['cat'];
    $showcat = urldecode($showcat);
    $showcat = str_replace("\\", '', $showcat);
}

$menus = shuffle_assoc($menus);

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

<div class="col-md-12">
    <div class="row">
        <div class="col-md-12">
            <div class="shop-navigation"><a href="javascript:;"><i class="fas fa-bars"></i> Show Categories</a></div>
            <div class="shop-menu-container" style="width:0px;">
                <div class="shop-menu-container__overlay"></div>
                <div class="shop-navigation__close"><a href="javascript:;"><i class="fas fa-times"></i></a></div>
                <ul id="shop-menu">
                    <?php 
                        $m = 0;

                        foreach($menus as $filename => $catname){
                            $ac = ($m==0)?'active':'';
                            echo '<li><a href="'.get_permalink(77).'?cat='.
                            urlencode($filename).'" class="'.$ac.'">'.$catname.'</a></li>';
                            $m++;
                        }
                    ?>
                </ul>
            </div>
            <!--
            <div id="shop-menu-mobile">
                <div class="menu-btn"><i class="fas fa-bars"></i></div>
                <ul>
                    <?php 
                        $m = 0;

                        foreach($menus as $filename => $catname){
                            $ac = ($m==0)?'active':'';
                            echo '<li><a href="#'.$filename.'" class="'.$ac.'">'.$catname.'</a></li>';
                            $m++;
                        }
                    ?>
                </ul>
            </div>
                    -->
        </div>
        <div class="col-md-12 shop-page" id="products-container">
            <div class="shop-page__inner">
            <?php 
                if(!isset($_GET['cat'])):
                    foreach($menus as $filename => $catname){
                        get_template_part('lazada/assets/categories/'.$filename);
                        break;
                    }
                else:
                    get_template_part('lazada/assets/categories/'.$showcat);
                endif;
            ?>
            </div>
        </div>
    </div>
</div>
<!-- cdnjs -->
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery.lazy/1.7.9/jquery.lazy.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery.lazy/1.7.9/jquery.lazy.plugins.min.js"></script>

<script>
var $ = jQuery;

$(document).ready(function(){
    $('#products-container .lazy').Lazy({
        // your configuration goes here
        scrollDirection: 'vertical',
        effect: 'fadeIn',
        visibleOnly: true,
        onError: function(element) {
            console.log('error loading ' + element.data('src'));
        }
    });
});
</script>