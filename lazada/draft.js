var jq = document.createElement('script');
jq.src = "https://code.jquery.com/jquery-3.4.1.min.js";
document.getElementsByTagName('head')[0].appendChild(jq);

setTimeout(function(){
    var $ = jQuery;

    $('body').prepend('<a href="#" id="mm-button">generate now</a>');
    
    $('#mm-button').click(function(){
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

        //ajax start
        $.ajax({
            url: 'https://dev.manitomanita.com/wp-content/themes/eyor-theme/lazada/fetch.php',
            type: 'POST',
            dataType: 'JSON',
            data: {
                urls: data,
            },
            success: function(resp) {
                console.log(resp);
            },
            error: function(xhr, ajaxOptions, thrownError) {
                // this error case means that the ajax call, itself, failed, e.g., a syntax error
                // in your_function()
                console.log('Request failed: ' + thrownError.message);
            },
        });
    });    
}, 2000);
