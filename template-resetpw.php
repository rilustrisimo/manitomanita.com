<?php
/**
 * Template Name: Reset Password
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

get_header();

$group = new Groups();
$updated = "";

if(isset($_POST['new-pass']) && isset($_POST['uid'])):
    $updated = $group->update_password();
endif; 

if ( have_posts() ) : ?>
	<?php while ( have_posts() ) { the_post(); ?>
		<div class="page-single">
			<main class="page-single__content resetpw-page" role="main">
                <div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <?php echo (strlen($updated) > 0)?'<h1><span>'.$updated.'</span></h1>':''; ?>
                            <?php if(!(strlen($updated) > 0)): ?>
                                <?php if(isset($_GET['uid']) && isset($_GET['gpw'])): ?>
                                    <?php if($group->getGroupPassword($_GET['uid']) == trim($_GET['gpw'])): ?>
                                        <h1>Change Password For <span><?php echo get_the_title($_GET['uid']); ?></span></h1>
                                        <form action="./?uid=<?php echo $_GET['uid']; ?>&gpw=<?php echo $_GET['gpw']; ?>" method="POST" name="reset-pass" id="reset-pass">
                                            <input name="uid" type="hidden" value="<?php echo $_GET['uid']; ?>">
                                            <input name="new-pass" type="password" placeholder="New Password">
                                            <input type="submit" class="button btn" value="Update Password">
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
			</main>
		</div>
	<?php } ?>
<?php else :
	get_template_part( 'templates/content', 'none' );
endif;

get_footer();
