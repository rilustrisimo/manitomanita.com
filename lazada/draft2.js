var $ = jQuery;
var data = [];

$('.lzd-site-menu-root-item').each(function() {
    var subitemclass = $(this).attr('id');
    var parent = $(this).find('span').text();
    var temp = [];

    temp[temp.length] = parent;


    $('.' + subitemclass + ' .lzd-site-menu-sub-item > a').each(function() {
        temp[temp.length] = $(this).attr('href');
    });

    data[data.length] = temp;
});

var obj = Object.assign({}, data);

$("<a />", {
    "download": "lazada.json",
    "href" : "data:application/json," + encodeURIComponent(JSON.stringify(obj))
}).prependTo("body").click(function() {
    $(this).remove();
})[0].click();
