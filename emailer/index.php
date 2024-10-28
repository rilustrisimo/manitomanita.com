<?php
include_once('../../../../wp-load.php');

$theme = new Theme();
/*
$query = $theme->createQuery('users');
$posts = $query->posts;

$collections = array();

foreach($posts as $p):
    $e = get_field('email', $p->ID);

    if(!in_array($e, $collections)):
        $collections[] = $e;
    endif;
endforeach;

*/

/*

echo '---Saving Emails Start---<br>';
foreach($collections2 as $email):

    // Create post object
    $my_post = array(
        'post_title'    => $email,
        'post_type'  => 'collections',
        'post_status'   => 'publish'
    );
    
    // Insert the post into the database
    $post_id = wp_insert_post( $my_post );

    update_field('email', $email, $post_id);
    update_field('send_count', '0', $post_id);

    output($email . ' [SUCCESS!!]<br>');

endforeach;

echo '---Saving Emails End---<br>';

*/

function output($str) {
    echo $str;
    ob_end_flush();
    ob_flush();
    flush();
    ob_start();
}

?>