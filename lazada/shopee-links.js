var jq = document.createElement('script');
jq.src = "https://code.jquery.com/jquery-3.4.1.min.js";
document.getElementsByTagName('head')[0].appendChild(jq);


setTimeout(function(){
    var $ = jQuery;
    var data = [];
    
    $('.home-category-list__category-grid').each(function() {
        var parent = $(this).find('.vvKCN3').text();
        var temp = [];
    
        temp[temp.length] = parent;
    
    
        $(this).each(function() {
            temp[temp.length] = $(this).attr('href');
        });
    
        data[data.length] = temp;
    });
    
    var obj = Object.assign({}, data);
    
    $("<a />", {
        "download": "shopee.json",
        "href" : "data:application/json," + encodeURIComponent(JSON.stringify(obj))
    }).prependTo("body").click(function() {
        $(this).remove();
    })[0].click();
}, 2000);

