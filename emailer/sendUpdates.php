<?php
include_once('../../../../wp-load.php');

$theme = new Theme();
$count = '1'; //update count
/*
$meta = array(
    array(
    'key' => 'send_count',
    'value' => '0'
    ),
    'relation' => 'AND',
    array(
        'key' => 'email',
        'value' => 'rouie',
        'compare' => 'LIKE'
        )
);
*/

$meta = array(
    array(
    'key' => 'send_count',
    'value' => '0'
    )
);

$query = $theme->createQuery('collections', $meta, -1, 'title', 'ASC');
$posts = $query->posts;

$collections = array();

foreach($posts as $p):
    $e = get_field('email', $p->ID);

    if(!in_array($e, $collections)):
        $collections[$p->ID] = $e;
    endif;
endforeach;



$tag = "welcome-update-2021";

$args = array(
    'post_type'   => 'emails',
    'posts_per_page' => -1
);

$em = get_posts( $args );

foreach($em as $epost):
    $f = get_fields($epost->ID);

    if($f['email_tag'] == $tag):
        $e = $f;
        break;
    endif;
endforeach;


foreach($collections as $id => $email):
    $message = $e['email_body'];

    $to = array($email);
    $subject = $e['email_subject'];

    if($theme->sendEmail($to, $subject, $message)):

        output($email . ' [SUCCESS!!]<br>');

        update_field('send_count', $count, $id);
    else:
        output($email . ' [FAILED!!]<br>');

        update_field('send_count', '9999', $id); //invalid email
    endif;

    sleep(10);

endforeach;


function output($str) {
    echo $str;
    ob_end_flush();
    ob_flush();
    flush();
    ob_start();
}

?>