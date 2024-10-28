<?php
    include "/home/eyorlthz/public_html/manitomanita/wp-content/themes/eyor-theme/lazada/lazadaapi/LazopSdk.php";

    $c = new LazopClient('https://api.lazada.com.ph/rest', '105827', 'r8ZMKhPxu1JZUCwTUBVMJiJnZKjhWeQF');
    $request = new LazopRequest('/marketing/bonus/offer/list/get', 'GET');
    $request->addApiParam('userToken', 'e3e9758263c04b7095ba4f44c4ed6557');
    $request->addApiParam('limit', '1');
    $request->addApiParam('page', '1');
    
    
    $json = json_decode($c->execute($request));
    $offersfinal = array();
    $cnt = 0;

    foreach($json->result->data as $offer){
        $offerid = $offer->offerId;
        $offerimg = (isset($offer->previewUrl))?$offer->previewUrl:null;

        
        $comrate = (isset($offer->bonusComRate))?str_replace("%", "", $offer->bonusComRate):0;
        $comrate = (float)$comrate;

        if(($comrate >= 10) || ($cnt == 0)){
            if(!is_null($offerimg)){
                $request = new LazopRequest('/marketing/offers/link/get', 'GET');
                $request->addApiParam('userToken', 'e3e9758263c04b7095ba4f44c4ed6557');
                $request->addApiParam('offerId', $offerid);
        
                $offerjson = json_decode($c->execute($request));
                $offerlink = $offerjson->result->data->originalUrl;

                $offersfinal[] = array(
                    'img' => $offerimg,
                    'link' => $offerlink
                );

                $cnt++;
            }
        }
    }

    $involveLink = 'https://invol.co/aff_m?offer_id=101166&aff_id=189674&property_id=133170&source=deeplink_generator_v2&url=';

    $content = "";
    $content .= '<h2>GREAT VALUE <span>DEALS</span></h2>';
    $content .= '<div class="row product-list">';

    foreach($offersfinal as $off){
        $content .= "<div class='col-md-12 col-sm-12 col-12 product-item' style='margin-top: 15px;'>";
        $content .= "<a href='".$involveLink.urlencode($off['link'])."' target='_blank'><img src='".$off['img']."'></a>";
        $content .= "</div>";
    }

    $content .= '</div>';

    $currdir = '/home/eyorlthz/public_html/manitomanita/wp-content/themes/eyor-theme/lazada';
    $filePath = $currdir.'/assets/offers-content.php';

    if(count($offersfinal) > 0){
        file_put_contents($filePath, $content);
        echo '.... printed<br>';
    }

?>