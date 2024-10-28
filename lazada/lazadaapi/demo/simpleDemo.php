<?php
    include "../LazopSdk.php";

    $c = new LazopClient('https://api.lazada.com.ph/rest', '105827', 'r8ZMKhPxu1JZUCwTUBVMJiJnZKjhWeQF');
    $request = new LazopRequest('/marketing/bonus/offer/list/get', 'GET');
    $request->addApiParam('userToken', 'e3e9758263c04b7095ba4f44c4ed6557');
    $request->addApiParam('limit', '10');
    $request->addApiParam('page', '1');
    
    
    $json = json_decode($c->execute($request));

    foreach($json->result->data as $offer){
        $offerid = $offer->offerId;

        $request = new LazopRequest('/marketing/offers/link/get', 'GET');
        $request->addApiParam('userToken', 'e3e9758263c04b7095ba4f44c4ed6557');
        $request->addApiParam('offerId', $offerid);

        var_dump($c->execute($request));
    }

?>