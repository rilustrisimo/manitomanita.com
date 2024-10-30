<?php
/**
 * Template part for displaying page content in Group Dashboard
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Eyorsogood_Design
 */

?>
<?php 
$group = new Groups();
$users = new Users();
$fields = $group->getGroupDetails($group->getGroupId());
$pro = (isset($fields['pro']))?$fields['pro']:false;

$groupdate = strtotime($fields['gift_exchange_date']); 
//$u = $users->getAllUsersPerGroup2($group->getGroupId());
$u = array();
echo '<div style="display:none;" id="userids">'.json_encode($u).'</div>';
echo '<div style="display:none;" id="groupid">'.$group->getGroupId().'</div>';
echo '<input type="hidden" id="groupid-val" value="'.$group->getGroupId().'" />';
?>
<div class="container-fluid dashboard">
    <div class="container dashboard__inner">
        <div class="col-md-12">
            <div class="dashboard__notice"><?php echo $users->addCommentToUser(); ?></div>
        </div>
        <div class="col-md-12">
            <div class="row">
                <div class="col-12 col-sm-4 col-md-4">
                    <div class="dashboard__ed">
                        <h2>Exchange<span>Date</span></h2>
                        <div class="month"><?php echo date('F', $groupdate); ?></div>
                        <div class="day"><?php echo date('d', $groupdate); ?></div>
                        <div class="year"><?php echo date('Y', $groupdate); ?></div>
                    </div>
                </div>
                <div class="col-12 col-sm-8 col-md-8">
                    <div class="dashboard__gd">
                        <div class="row">
                            <div class="col-10 col-sm-10 col-md-10">
                                <h2>Group<span>Name</span></h2>
                                <div class="big"><?php echo $fields['group_name']; ?></div>
                            </div>
                            <div class="col-2 col-sm-2 col-md-2 edit-button"><a href="#edit-details" class="user-action editgroup-btn" group-data="<?php echo $group->getGroupId(); ?>" data-action="edit-group">EDIT DETAILS <i class="far fa-edit"></i></a></div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <h2>Group<span>Moderator</span></h2>
                                <div class="big"><?php echo $fields['your_name']; ?></div>
                            </div>
                        </div>
                        <div class="row metrics">
                            <div class="col-md-6">
                                <h2>Spending<span>Minimum</span></h2>
                                <div class="bigger"><?php echo $fields['spending_minimum']; ?></div>
                            </div>
                            <div class="col-md-3">
                                <h2>MEMBER<span>S</span></h2>
                                <div class="bigger memcount">0</div>
                            </div>
                            <div class="col-md-3">
                                <h2>WISH<span>LISTS</span></h2>
                                <div class="bigger wishcount">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard__ma">
                        <div class="header-dash">
                            <div class="row">
                                <?php $creds = $group->getGroupCredentials(); ?>
                                <div class="col-12 col-sm-12 col-md-6"><h2>MEMBERS<span>AREA</span></h2></div>
                                <div class="col-12 col-sm-12 col-md-6 clip"><i class="fas fa-link"></i> <input type="text" name="clipboard" id="clipboard" value="<?php echo get_permalink(get_the_ID()).'?gid='.$creds[0].'&pw='.$creds[1]; ?>"><div class="label">Copied to clipboard</div></div>
                            </div>
                        </div>
                        <div class="body-dash">
                            <div class="accordion" id="members-list">
                                <!-- SAMPLE CARD -->
                                <div class="card sample-card" style="display:none;">
                                    <div class="card-header" id="heading99999">
                                    <h2 class="mb-99999">
                                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse99999" aria-expanded="false" aria-controls="collapse99999">
                                        <svg class="svg-inline--fa fa-chevron-circle-right fa-w-16" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-circle-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8zm113.9 231L234.4 103.5c-9.4-9.4-24.6-9.4-33.9 0l-17 17c-9.4 9.4-9.4 24.6 0 33.9L285.1 256 183.5 357.6c-9.4 9.4-9.4 24.6 0 33.9l17 17c9.4 9.4 24.6 9.4 33.9 0L369.9 273c9.4-9.4 9.4-24.6 0-34z"></path></svg> Sample Member                                      </button>
                                        <div class="action-btns">
                                            <a href="#" class="expand-deets"><svg class="svg-inline--fa fa-stream fa-w-16" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="stream" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M16 128h416c8.84 0 16-7.16 16-16V48c0-8.84-7.16-16-16-16H16C7.16 32 0 39.16 0 48v64c0 8.84 7.16 16 16 16zm480 80H80c-8.84 0-16 7.16-16 16v64c0 8.84 7.16 16 16 16h416c8.84 0 16-7.16 16-16v-64c0-8.84-7.16-16-16-16zm-64 176H16c-8.84 0-16 7.16-16 16v64c0 8.84 7.16 16 16 16h416c8.84 0 16-7.16 16-16v-64c0-8.84-7.16-16-16-16z"></path></svg> Expand Details</a>
                                            <a href="#" class="user-action comment-btn"><svg class="svg-inline--fa fa-comment fa-w-16" aria-hidden="true" focusable="false" data-prefix="far" data-icon="comment" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 32C114.6 32 0 125.1 0 240c0 47.6 19.9 91.2 52.9 126.3C38 405.7 7 439.1 6.5 439.5c-6.6 7-8.4 17.2-4.6 26S14.4 480 24 480c61.5 0 110-25.7 139.1-46.3C192 442.8 223.2 448 256 448c141.4 0 256-93.1 256-208S397.4 32 256 32zm0 368c-26.7 0-53.1-4.1-78.4-12.1l-22.7-7.2-19.5 13.8c-14.3 10.1-33.9 21.4-57.5 29 7.3-12.1 14.4-25.7 19.9-40.2l10.6-28.1-20.6-21.8C69.7 314.1 48 282.2 48 240c0-88.2 93.3-160 208-160s208 71.8 208 160-93.3 160-208 160z"></path></svg> Comment Member</a>
                                        </div>
                                        
                                    </h2>
                                    </div>

                                    <div id="collapse99999" class="collapse" aria-labelledby="heading99999" data-parent="#members-list">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h2>Wish<span>List</span></h2>
                                                <ul class="wishlist-list">
                                                    <div class="placeholder"><svg class="svg-inline--fa fa-gifts fa-w-20" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="gifts" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" data-fa-i2svg=""><path fill="currentColor" d="M240.6 194.1c1.9-30.8 17.3-61.2 44-79.8C279.4 103.5 268.7 96 256 96h-29.4l30.7-22c7.2-5.1 8.9-15.1 3.7-22.3l-9.3-13c-5.1-7.2-15.1-8.9-22.3-3.7l-32 22.9 11.5-30.6c3.1-8.3-1.1-17.5-9.4-20.6l-15-5.6c-8.3-3.1-17.5 1.1-20.6 9.4l-19.9 53-19.9-53.1C121 2.1 111.8-2.1 103.5 1l-15 5.6C80.2 9.7 76 19 79.2 27.2l11.5 30.6L58.6 35c-7.2-5.1-17.2-3.5-22.3 3.7l-9.3 13c-5.1 7.2-3.5 17.2 3.7 22.3l30.7 22H32c-17.7 0-32 14.3-32 32v352c0 17.7 14.3 32 32 32h168.9c-5.5-9.5-8.9-20.3-8.9-32V256c0-29.9 20.8-55 48.6-61.9zM224 480c0 17.7 14.3 32 32 32h160V384H224v96zm224 32h160c17.7 0 32-14.3 32-32v-96H448v128zm160-288h-20.4c2.6-7.6 4.4-15.5 4.4-23.8 0-35.5-27-72.2-72.1-72.2-48.1 0-75.9 47.7-87.9 75.3-12.1-27.6-39.9-75.3-87.9-75.3-45.1 0-72.1 36.7-72.1 72.2 0 8.3 1.7 16.2 4.4 23.8H256c-17.7 0-32 14.3-32 32v96h192V224h15.3l.7-.2.7.2H448v128h192v-96c0-17.7-14.3-32-32-32zm-272 0c-2.7-1.4-5.1-3-7.2-4.8-7.3-6.4-8.8-13.8-8.8-19 0-9.7 6.4-24.2 24.1-24.2 18.7 0 35.6 27.4 44.5 48H336zm199.2-4.8c-2.1 1.8-4.5 3.4-7.2 4.8h-52.6c8.8-20.3 25.8-48 44.5-48 17.7 0 24.1 14.5 24.1 24.2 0 5.2-1.5 12.6-8.8 19z"></path></svg> No wishlist provided yet.</div>                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h2>Comment<span>s</span></h2>
                                                <ul class="comments-list">
                                                    <div class="placeholder"><svg class="svg-inline--fa fa-comment-dots fa-w-16" aria-hidden="true" focusable="false" data-prefix="far" data-icon="comment-dots" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M144 208c-17.7 0-32 14.3-32 32s14.3 32 32 32 32-14.3 32-32-14.3-32-32-32zm112 0c-17.7 0-32 14.3-32 32s14.3 32 32 32 32-14.3 32-32-14.3-32-32-32zm112 0c-17.7 0-32 14.3-32 32s14.3 32 32 32 32-14.3 32-32-14.3-32-32-32zM256 32C114.6 32 0 125.1 0 240c0 47.6 19.9 91.2 52.9 126.3C38 405.7 7 439.1 6.5 439.5c-6.6 7-8.4 17.2-4.6 26S14.4 480 24 480c61.5 0 110-25.7 139.1-46.3C192 442.8 223.2 448 256 448c141.4 0 256-93.1 256-208S397.4 32 256 32zm0 368c-26.7 0-53.1-4.1-78.4-12.1l-22.7-7.2-19.5 13.8c-14.3 10.1-33.9 21.4-57.5 29 7.3-12.1 14.4-25.7 19.9-40.2l10.6-28.1-20.6-21.8C69.7 314.1 48 282.2 48 240c0-88.2 93.3-160 208-160s208 71.8 208 160-93.3 160-208 160z"></path></svg> Say something for me.</div>                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    </div>

                                    <div class="user-action-btns">
                                        <a href="#" class="user-action wish-btn" data-action="edit-wishlist"><svg class="svg-inline--fa fa-gift fa-w-16" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="gift" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M32 448c0 17.7 14.3 32 32 32h160V320H32v128zm256 32h160c17.7 0 32-14.3 32-32V320H288v160zm192-320h-42.1c6.2-12.1 10.1-25.5 10.1-40 0-48.5-39.5-88-88-88-41.6 0-68.5 21.3-103 68.3-34.5-47-61.4-68.3-103-68.3-48.5 0-88 39.5-88 88 0 14.5 3.8 27.9 10.1 40H32c-17.7 0-32 14.3-32 32v80c0 8.8 7.2 16 16 16h480c8.8 0 16-7.2 16-16v-80c0-17.7-14.3-32-32-32zm-326.1 0c-22.1 0-40-17.9-40-40s17.9-40 40-40c19.9 0 34.6 3.3 86.1 80h-86.1zm206.1 0h-86.1c51.4-76.5 65.7-80 86.1-80 22.1 0 40 17.9 40 40s-17.9 40-40 40z"></path></svg><!-- <i class="fas fa-gift"></i> --> Edit Wishlist</a>
                                        <a href="javascript:;" disabled="" class=""><svg class="svg-inline--fa fa-user-friends fa-w-20" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="user-friends" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" data-fa-i2svg=""><path fill="currentColor" d="M192 256c61.9 0 112-50.1 112-112S253.9 32 192 32 80 82.1 80 144s50.1 112 112 112zm76.8 32h-8.3c-20.8 10-43.9 16-68.5 16s-47.6-6-68.5-16h-8.3C51.6 288 0 339.6 0 403.2V432c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48v-28.8c0-63.6-51.6-115.2-115.2-115.2zM480 256c53 0 96-43 96-96s-43-96-96-96-96 43-96 96 43 96 96 96zm48 32h-3.8c-13.9 4.8-28.6 8-44.2 8s-30.3-3.2-44.2-8H432c-20.4 0-39.2 5.9-55.7 15.4 24.4 26.3 39.7 61.2 39.7 99.8v38.4c0 2.2-.5 4.3-.6 6.4H592c26.5 0 48-21.5 48-48 0-61.9-50.1-112-112-112z"></path></svg><!-- <i class="fas fa-user-friends"></i> --> See Match</a>
                                        <a href="javascript:;" class="user-action leave-btn" data-action="leave-group"><svg class="svg-inline--fa fa-sign-out-alt fa-w-16" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="sign-out-alt" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M497 273L329 441c-15 15-41 4.5-41-17v-96H152c-13.3 0-24-10.7-24-24v-96c0-13.3 10.7-24 24-24h136V88c0-21.4 25.9-32 41-17l168 168c9.3 9.4 9.3 24.6 0 34zM192 436v-40c0-6.6-5.4-12-12-12H96c-17.7 0-32-14.3-32-32V160c0-17.7 14.3-32 32-32h84c6.6 0 12-5.4 12-12V76c0-6.6-5.4-12-12-12H96c-53 0-96 43-96 96v192c0 53 43 96 96 96h84c6.6 0 12-5.4 12-12z"></path></svg><!-- <i class="fas fa-sign-out-alt"></i> --> Leave Group</a>
                                    </div>
                                </div>
                                <!-- SAMPLE CARD END-->

                            <div id="cards-container">

                                <!-- CARDS HERE-->
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12 text-center" style="padding: 35px 15px;">
        <!-- ADS and PROMOTIONS -->
        <?php if(!$pro): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
        <!-- Manito Manita Dashboard -->
        <ins class="adsbygoogle"
            style="display:block"
            data-ad-client="ca-pub-8648985139343614"
            data-ad-slot="3637831254"
            data-ad-format="auto"
            data-full-width-responsive="true"></ins>
        <script>
            (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
        <?php endif; ?>
<!--
            <style>
                .show-mobile { display: none; }
                .hide-mobile { display: block; }
                .in-mobile .show-mobile { display: block; }
                .in-mobile .hide-mobile { display: none; }
            </style>
            
            <a rel="nofollow" target="_blank" class="show-mobile" href="https://invol.co/aff_m?offer_id=101166&aff_id=189674&url=https%3A%2F%2Fwww.lazada.com.ph%2F12-12%2F&campaign_id=49371&source=campaign"><img style="max-width: 350px;" src="https://img.involve.asia/rpss/campaigns_banners/1607333098-bJV4ILW1LuSzzHUMw969G7iUA85osrXw.png"></a>
            <a rel="nofollow" target="_blank" class="hide-mobile" href="https://invol.co/aff_m?offer_id=101166&aff_id=189674&url=https%3A%2F%2Fpages.lazada.com.ph%2Fwow%2Fgcp%2Froute%2Flazada%2Fph%2Fupr_1000345_lazada%2Fchannel%2Fph%2Fupr-router%2Frender%2F%3Fspm%3Da2o4l.home.top.dbr6.239e359dC5xNDp%26hybrid%3D1%26data_prefetch%3Dtrue%26wh_pid%3D%252Flazada%252Fmegascenario%252Fph%252Fd12%252FD122020_Cluster_Electronics%26scm%3D1003.4.icms-zebra-5000377-2587038.OTHER_6038629752_7052874%26prefetch_replace%3D1&campaign_id=49669&source=campaign"><img src="https://img.involve.asia/rpss/campaigns_banners/1607676754-T8WtJ70dLEuiRUO4kOaugorPnwUKLJ7s.png"></a>
            
            <a rel="nofollow" target="_blank" class="hide-mobile" href="https://invol.co/aff_m?offer_id=101166&aff_id=189674&url=https%3A%2F%2Fpages.lazada.com.ph%2Fwow%2Fgcp%2Froute%2Flazada%2Fph%2Fupr_1000345_lazada%2Fchannel%2Fph%2Fupr-router%2Frender%2F%3Fspm%3Da2o4l.home.top.dbr1.239e359dqXDTt6%26hybrid%3D1%26data_prefetch%3Dtrue%26wh_pid%3D%252Flazada%252Fmegascenario%252Fph%252F12-12-after-party-payday%252FQNr8R2NZTS%26scm%3D1003.4.icms-zebra-5000377-2587038.OTHER_6039111683_7070362%26prefetch_replace%3D1&campaign_id=49782&source=campaign"><img src="https://img.involve.asia/rpss/campaigns_banners/1608023116-fSHSju9Trzm9SnEu1zzmknvAGUc2lnNN.jpg"></a>
-->        
        </div>

        <!-- ADS and PROMOTIONS END -->
        <!--                                             
        <div class="col-md-12 text-center advertisement" style="padding-top: 35px;">   
            <h1>MANITO<span>MANITA</span> SPONSORED CHARITY</h1>
            <a target="_blank" href="https://www.facebook.com/IslaKrayola/"><img style="width: 750px;" src="https://dev.manitomanita.com/wp-content/uploads/2020/12/isla-logo.png"></a>
        </div>
                                                        -->
    </div>
</div>
<input type="hidden" id="group-matched" value="<?php echo get_field('matched', $group->getGroupId()); ?>">
<?php if(!get_field('matched', $group->getGroupId())): ?>
<div id="join-group" class="popup">
    <div class="popup__title">Join<span>Group</span></div>
	<div class="popup__divider"></div>
    <div class="popup__inner"><?php $group->createAcfForm(42, 'users', 'Join Group', 'group-dashboard/?gid='.$_SESSION['groupid'].'&pw='.$_SESSION['grouppw']); ?></div>
</div>
<?php endif; ?>
<div id="input-password" class="popup">
	<div class="popup__title">Supply<span>Password</span></div>
	<div class="popup__divider"></div>
    <div class="popup__inner">
		<form action="#" method="POST" id="input-pw" class="acf-form">
            <input type="hidden" name="user-data" value="">
            <div class="pw-field acf-field"><div class="acf-label"><label>Your Password</label></div><input type="password" name="password" required><div class="notices" style="display:none;"></div></div>
            <div class="pw-submit acf-form-submit"><input type="submit" name="submit" value="Submit" class="button"></div>
        </form>
	</div>
</div>
<div id="comment-box" class="popup">
	<div class="popup__title">Comment<span>Member</span></div>
	<div class="popup__divider"></div>
    <div class="popup__inner">
		<form action="<?php echo get_permalink(get_the_ID()).'?gid='.$_SESSION['groupid'].'&pw='.$_SESSION['grouppw']; ?>" method="POST" id="comment-user" class="acf-form">
            <input type="hidden" name="user-data" value="">
            <div class="com-field acf-field"><div class="acf-label"><label>Your Comment</label></div><textarea name="comment" required></textarea></div>
            <div class="acf-field" data-type="recaptcha"><div id="comment-grecaptcha"></div></div>
            <div class="com-submit acf-form-submit"><input type="submit" name="submit" value="Submit" class="button"></div>
        </form>
	</div>
</div>
<div id="who-joined-box" class="popup">
	<div class="popup__title">Who<span>Joined</span></div>
	<div class="popup__divider"></div>
    <div class="popup__inner">
		<?php if(get_field('matched', $group->getGroupId())): ?>
        <div class="row">
            <div class="col-md-12 joined card-columns">
                
            </div>
        </div>
        <?php endif;?>
	</div>
</div>
<div id="custom-popup" class="popup">
	<div class="popup__title"></div>
	<div class="popup__divider"></div>
    <div class="popup__inner"></div>
</div>
<form action="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";?>" method="POST" id="reload">
    <input type="hidden" name="reload" value="1">
</form>
<!--
<div class="container-fluid shop-container">
    <div class="container">
        <div class="row">
            <?php //get_template_part('lazada/assets/shop-products', 'single');?>
        </div>
    </div>
</div>
-->