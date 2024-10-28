<?php
require_once( dirname(__FILE__) . '/../../../wp-load.php' );
if(!isset($_GET['gid'])) die();

$users = new Users();

$getusers = $users->getAllUsersPerGroup2($_GET['gid']);

?>

<?php foreach($getusers as $u): ?>
<div class="card"><?php echo get_field('name', $u); ?></div>
<?php endforeach; ?>
