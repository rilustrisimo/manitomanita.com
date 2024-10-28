<?php
require_once( dirname(__FILE__) . '/../../../wp-load.php' );
$users = new Users();

$getusers = $users->getAllUsersPerGroup($_GET['gid']);

var_dump($getusers);
?>