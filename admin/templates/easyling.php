<div class="wrap">
    <h2><img src="<?php echo EASYLING_URL ?>/images/easyling-logo.png" alt="Easyling Wordpress Plugin" /></h2>    
    <div class="metabox-holder">        
        <form action="options.php" method="post" id="easyling_setting_form" name="easyling_setting_form">            
            <?php if ($easyling_status == Easyling::STATUS_INSTALLED): ?>
                <div class="postbox">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3><span>Linking Service</span></h3>
                    <div class="inside">
                        <p>
                            By linking this Wordpress Installation to an Easyling Account and project
                            you will be able to retrieve the translations.<br />
                            This is a necessary first step.
                        </p>
                        <p>
                            <a class="button-secondary" href="?page=easyling&oauth_action=consumer_key_n_secret" title="Link Service">Link it!</a>
                        </p>
                    </div>        
                </div>      
            <?php else: ?>
                <div class="postbox">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3><span>Easyling Projects</span></h3>
                    <div class="inside">
                        <p>
                            You have got the following projects available to link with your Wordpress installation:
                        </p>
                        <?php settings_fields('easyling_linking'); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Linked Project</th>
                                <td>                                                                
                                    <select name="easyling_linked_project" id="select_project">
                                        <option value="">Please Select One</option>
                                        <?php foreach ($projects as $p): ?>
                                            <?php $selected = get_option('easyling_linked_project') == $p->getProjectCode() ? 'selected="selected"' : ''; ?>
                                            <option value="<?php echo $p->getProjectCode() ?>" <?php echo $selected; ?>><?php echo $p->getName() ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="<?php echo get_admin_url() ?>admin.php?page=easyling&oauth_action=updateprojectlist" class="button-secondary">Update Project List</a><br />
                                    <span class="description">By linking the Wordpress Installation to an Easyling Project you will be able to display translated pages.</span>
                                </td>
                            </tr>
                            <?php
                            if ($linked && $pcode !== false):
                                $checked = $md['status'] == 'on' ? 'checked="checked"' : '';
                                ?>
                                <tr>
                                    <th scope="row">Multidomain Support</th>
                                    <td>
                                        <input type="hidden" name="easyling_multidomain[status]" value="off" />
                                        <label>
                                            <input type="checkbox" id="chk_multidomain" <?php echo $checked ?> name="easyling_multidomain[status]" value="on" />                                   
                                            Turn Multidomain support on
                                        </label><br />
                                        <span class="description">With Multidomain support you can use different domains to different languages. Such as: example.com and example.de for English and German versions.</span>
                                    </td>
                                </tr>
                                <?php if ($md == false || $md['status'] == 'off'): ?>
                                    <tr>
                                        <th scope="row">Available Locales</th>                                
                                        <td>    
                                            <?php
                                            foreach ($projects->get($pcode)->getProjectLanguages() as $k => $l):
                                                $checked = $project_languages[$l->getLanguageCode()]['used'] == 'on' ? 'checked="checked"' : '';
                                                ?>
                                                <input type="hidden" name="easyling_project_languages[<?php echo $l->getLanguageCode() ?>][used]" value="off" />
                                                <label style="width: 100px; display: inline-block; vertical-align: top;">
                                                    <input type="checkbox" <?php echo $checked ?> name="easyling_project_languages[<?php echo $l->getLanguageCode() ?>][used]" />&nbsp;<?php echo $l->getLanguageCode() ?>
                                                </label>
                                                <input type="text" size="2" name="easyling_project_languages[<?php echo $l->getLanguageCode() ?>][lngcode]" value="<?php echo $project_languages[$l->getLanguageCode()]['used'] == 'on' ? $project_languages[$l->getLanguageCode()]['lngcode'] : substr($l->getLanguageCode(), 0, 2); ?>" /><br />
                                            <?php endforeach; ?>                                                
                                            <span class="description">Check those Locales you want your page to be available in and provide a Language Code to it.</span>
                                        </td>    
                                    </tr>
                                    <?php
                                endif;
                            endif;
                            if ($linked && ($pcode = get_option('easyling_linked_project', false)) !== false):
                                $used = false;
                                foreach ($project_languages as $l):
                                    if ($l['used'] == 'on') {
                                        $used = true;
                                        break;
                                    }
                                endforeach;
                                if ($used):
                                    ?>
                                    <tr>
                                        <th scope="row">Retrieving translations</th>
                                        <td>  
                                            <a class="button-secondary" href="?page=easyling&transfer=1" title="Retrieve translations">Retrieve translations</a><br />
                                            <span class="description">By clicking on this button you will start downloading the translations in the background from Easyling.</span>
                                        </td>
                                    </tr>
                                    <?php
                                endif;
                            endif;
                            ?>
                        </table>                    
                        <input type="button" class="button button-primary" value="Save Changes" onclick="document['easyling_setting_form'].submit();">
                    </div>
                </div>

                <?php if ($md && $md['status'] == 'on'): ?>
                    <div class="postbox">                
                        <div class="handlediv" title="Click to toggle"><br></div>
                        <h3><span>Multidomain support</span></h3>
                        <div class="inside">
                            <p>
                                You can configure here which domain should map to which language.
                            </p>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">Linked Project</th>
                                    <td>
                                        <?php
                                        foreach ($projects->get($pcode)->getProjectLanguages() as $l):
                                            $checked = $project_languages[$l->getLanguageCode()]['used'] == 'on' ? 'checked="checked"' : '';
                                            $val = $project_languages[$l->getLanguageCode()]['used'] == 'on' && $project_languages[$l->getLanguageCode()]['domain'] ? $project_languages[$l->getLanguageCode()]['domain'] : '';
                                            ?>
                                            <input type="hidden" name="easyling_project_languages[<?php echo $l ?>][used]" value="off" />
                                            <label style="width: 100px; display: inline-block; vertical-align: top; line-height: 19px;">
                                                <input type="checkbox" <?php echo $checked ?> name="easyling_project_languages[<?php echo $l ?>][used]" />&nbsp;<?php echo $l ?>
                                            </label>
                                            <span style="line-height: 19px;">Domain name for locale:</span>
                                            <input type="text" name="easyling_project_languages[<?php echo $l ?>][domain]" value="<?php echo $val ?>" onclick="javascript:this.value='';" /><br />

                                            <?php
                                        endforeach;
                                        ?>
                                        <span class="description">You need to set which language should map to which domain.</span>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <input type="button" class="button button-primary" value="Save Changes" onclick="document['easyling_setting_form'].submit();">
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>
    <?php if ($consent === null): ?>
        <div id="modal-consent" class="modal-content">
            <h3>Please help us improve Easyling for Wordpress!</h3>
            <p>We ask you to provide us with information in case something goes wrong. 
                We can do that automatically for you but we need your approval.</p>
            <p>By checking 'I will help', you agree that when something goes wrong
                we get a notification about it automatically including the following information:</p>
            <ul>
                <li>your WP installation URL (such as example.com)</li>
                <li>the page on which the error occured (reqest URL)</li>
                <li>stacktrace of the calling piece of code that caused the error</li>            
            </ul>
            <p>We cannot use this data to harm you. We only send data to us that has something to do
                with Easyling for Wordpress.</p>
            <form action="options.php" method="post">
                <?php settings_fields('easyling_consent'); ?>
                <label style="width: 150px; display: inline-block; vertical-align: top; line-height: 19px;">I do not consent:</label>
                <input type="radio" name="easyling_consent" value="0" /><br />
                <label style="width: 150px; display: inline-block; vertical-align: top; line-height: 19px;">I will help, I consent:</label>                
                <input type="radio" name="easyling_consent" value="1" checked="checked" /><br />
                <div style="text-align: right;">
                    <?php submit_button('Save Consent Setting', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
<script type="text/javascript">
    jQuery(document).ready(function(){
        jQuery("input.button-primary").click(function(){
            jQuery('#easyling_setting_form').submit(function(){
                return false;
            });
        })
        jQuery("#select_project, #chk_multidomain").change(function(){
            jQuery('#easyling_setting_form').submit();                    
        });
<?php if ($consent === null): ?>
            jQuery(document).ready(function(){
                jQuery('#modal-consent').modal({
                    minHeight: 360,
                    minWidth: 400
                });
            })    
<?php endif; ?>
    })
</script>