<?php
$option = get_option('easyling');
if ($option['status'] == Easyling::STATUS_INSTALLED):
    ?>
    <div class="updated" id="link-service">
        <p>
            Easyling is current not linked with this Wordpress site
            <a href="<?php echo get_admin_url(null, '', 'admin') ?>admin.php?page=easyling&link=1">Link Service</a>
        </p>
    </div>
<?php endif; ?>
<!-- update messages -->
<?php if (!empty($messages)): ?>
    <div class="updated" id="link-service">
        <p>
            <?php
            foreach ($messages as $msg):
                echo $msg;
            endforeach;
            ?>
        </p>
    </div>
<?php endif; ?>
<!-- end of update messages -->
<?php
if (!$option['popup_shown']):
    ?>
    <div id="modal-info" class="modal-content">
        <h3>Thank you for using Easyling!</h3>
        <p>In order to be able to use the Plugin you will need</p>
        <ul>
            <li>an active Easyling.com account</li>
            <li>a project set up on Easyiling.com</li>
        </ul>
        <p>If you do not yet have an Easyling account, you can create one for free by clicking here: <a href="http://www.easyling.com/website-owners/?utm_source=easyling-wp-plugin-admin&utm_medium=admin-link&utm_campaign=easyling-wp" target="_blank">Create an Easyling Account</a></p>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery('#modal-info').modal();
        });
    </script>
    <?php
    $option['popup_shown'] = true;
    update_option('easyling', $option);
endif;
?>

