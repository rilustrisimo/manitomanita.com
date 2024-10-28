<?php

$data = file_get_contents(dirname(__FILE__).'/assets/lazada.json');

$lazada = json_decode($data, true);
$products = array();

$c = 0;
foreach($lazada as $category){
    $temp = array();
    $prodlist = array();

    foreach($category as $item){
        $append = 'https:'.$item;

        if (filter_var($append, FILTER_VALIDATE_URL) === FALSE) {
            $temp['category'] = $item;
        }else{
            //echo '<br>'.$append;

            //get the content and extract the script and then the json string
            $content = getPageContentHtml($append);
            $regexp = "/<script[\s\S]*?>[\s\S]*?<\/script>/";

            preg_match_all($regexp, $content, $matches, PREG_PATTERN_ORDER);
            $rawscript = findLongestStringFromArray($matches[0]);

            $patterns = array("/<[^>]*script>/", "/window.pageData=/");
            $j = preg_replace($patterns, '', $rawscript);

            $data = json_decode($j, true);

            $itemcount = 0;

            if(isset($data['mods']['listItems'])):
                foreach($data['mods']['listItems'] as $product) {
                    $prodDetails = array();

                    $prodDetails['name'] = $product['name'];
                    $prodDetails['productUrl'] = $product['productUrl'];
                    $prodDetails['image'] = $product['image'];
                    $prodDetails['originalPriceShow'] = (isset($product['originalPriceShow']))?$product['originalPriceShow']:'';
                    $prodDetails['priceShow'] = $product['priceShow'];
                    $prodDetails['ratingScore'] = $product['ratingScore'];
                    $prodDetails['review'] = $product['review'];

                    $prodlist[] = $prodDetails;

                    $itemcount++;

                    if($itemcount == 10){
                        break;
                    }
                }
            endif;

            sleep(30);
        }
    }

    $temp['products'] = $prodlist;
    $products[] = $temp;

    $c++;

    //var_dump($products);
    generateShopContents($products);

    $products = array();//reset products

    //if($c == 4){
        //die;
    //}
    
    break;
}

function generateShopContents($products){
    $content = '';

    foreach($products as $category){
        $cat_name = $category['category'];
        $prod_list = $category['products'];

        $filePath = '/home/eyorlthz/public_html/manitomanita/wp-content/themes/eyor-theme/lazada/assets/categories/cat-'.strtolower(str_replace(" ", "-", $cat_name)).'.php';
        
        $content .= '<h1 class="cat-title">'.$cat_name.'</h1>';
        $content .= '<div class="row product-list" id="cat-'.strtolower(str_replace(" ", "-", $cat_name)).'">';

        foreach($prod_list as $prod_details){
            //$deeplink = "https://c.lazada.com.ph/t/c.bzLQ"; //lazada adsense
            $deeplink = "https://invol.co/aff_m?offer_id=101166&aff_id=189674&source=deeplink_generator_v2"; //involve asia
            $urlmod = urlencode("https:".$prod_details['productUrl']);

            $content .= '<div class="col-md-3 col-sm-6 col-12 product-item" item-price="'.str_replace('₱', '', $prod_details['priceShow']).'" item-rating="'.$prod_details['ratingScore'].'">';
            $content .= '<a href="'.$deeplink.'&url='.$urlmod.'" target="_blank">';
            //$content .= '<div class="image lazy" style="background:url('.$prod_details['image'].');">';
            $content .= '<div class="image lazy" data-src="'.$prod_details['image'].'">';
            $content .= '</div>';
            $content .= '<div class="details">';
            $content .= '<div class="name">';
            $content .= shorten_text($prod_details['name'], 50);
            $content .= '</div>';
            $content .= '<div class="price">';
            $content .= $prod_details['priceShow'].'<span>'.$prod_details['originalPriceShow'].'</span>';
            $content .= '</div>';
            $content .= '<div class="rating">';
            $content .= '<span class="fa fa-star checked"></span> '.round($prod_details['ratingScore'], 2).' / 5';
            $content .= '</div>';
            $content .= '</div>';
            $content .= '</a>';
            $content .= '</div>';
        }

        $content .= '</div>';

        if(count($prod_list) > 0){
            file_put_contents($filePath, $content);
            echo $cat_name.'.... printed<br>';
        }
        
        echo $cat_name.'.... done<br>';
        echo count($prod_list).'.... products<br>';
    }
}

function shorten_text($text, $max_length = 140, $cut_off = '...', $keep_word = false)
{
    if(strlen($text) <= $max_length) {
        return $text;
    }

    if(strlen($text) > $max_length) {
        if($keep_word) {
            $text = substr($text, 0, $max_length + 1);

            if($last_space = strrpos($text, ' ')) {
                $text = substr($text, 0, $last_space);
                $text = rtrim($text);
                $text .=  $cut_off;
            }
        } else {
            $text = substr($text, 0, $max_length);
            $text = rtrim($text);
            $text .=  $cut_off;
        }
    }

    return $text;
}

function getPageContentHtml($url){

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
        "Accept: */*",
        "Accept-Encoding: gzip, deflate",
        "Cache-Control: no-cache",
        "Connection: keep-alive",
        "Host: www.lazada.com.ph",
        "Postman-Token: cb41d4f9-25e8-4e4c-8e0c-9ccf023c74cc,0659178b-c468-4aec-a5a1-1427b2109b7f",
        "User-Agent: PostmanRuntime/7.19.0",
        "cache-control: no-cache"
    ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return "cURL Error #:" . $err;
    } else {
        return $response;
    }
}

function findLongestStringFromArray($array = array()) {
 
    if(!empty($array)){
        $lengths = array_map('strlen', $array);
        $maxLength = max($lengths);
        $key = array_search($maxLength, $lengths);
        return $array[$key];
    }
}
?>
