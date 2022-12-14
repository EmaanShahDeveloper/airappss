<?php
if(!defined('ABSPATH')){
    exit;//Exit if accessed directly
}

class AIOWPSecurity_Spam_Menu extends AIOWPSecurity_Admin_Menu
{
    var $menu_page_slug = AIOWPSEC_SPAM_MENU_SLUG;
    
    /* Specify all the tabs of this menu in the following array */
    var $menu_tabs;

    var $menu_tabs_handler = array(
        'tab1' => 'render_tab1',
        'tab2' => 'render_tab2',
        'tab3' => 'render_tab3',
        'tab4' => 'render_tab4',
        );
    
    function __construct() 
    {
        $this->render_menu_page();
    }
    
    function set_menu_tabs() 
    {
        $this->menu_tabs = array(
		'tab1' => __('Comment Spam', 'all-in-one-wp-security-and-firewall'),
		'tab2' => __('Comment Spam IP Monitoring', 'all-in-one-wp-security-and-firewall'),
        'tab3' => __('BuddyPress', 'all-in-one-wp-security-and-firewall'),
		'tab4' => __('bbPress', 'all-in-one-wp-security-and-firewall'),
        );
    }

    /*
     * Renders our tabs of this menu as nav items
     */
    function render_menu_tabs() 
    {
        $current_tab = $this->get_current_tab();

        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->menu_tabs as $tab_key => $tab_caption ) 
        {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->menu_page_slug . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';	
        }
        echo '</h2>';
    }
    
    /*
     * The menu rendering goes here
     */
    function render_menu_page() 
    {
        echo '<div class="wrap">';
		echo '<h2>'.__('Spam Prevention', 'all-in-one-wp-security-and-firewall').'</h2>'; // Interface title
        $this->set_menu_tabs();
        $tab = $this->get_current_tab();
        $this->render_menu_tabs();
        ?>        
        <div id="poststuff"><div id="post-body">
        <?php 
        //$tab_keys = array_keys($this->menu_tabs);
        call_user_func(array($this, $this->menu_tabs_handler[$tab]));
        ?>
        </div></div>
        </div><!-- end of wrap -->
        <?php
    }
    
    function render_tab1()
    {
        global $aiowps_feature_mgr;
        global $aio_wp_security;
        if(isset($_POST['aiowps_apply_comment_spam_prevention_settings']))//Do form submission tasks
        {
            $nonce=$_REQUEST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'aiowpsec-comment-spam-settings-nonce'))
            {
                $aio_wp_security->debug_logger->log_debug("Nonce check failed on save comment spam settings!",4);
                die("Nonce check failed on save comment spam settings!");
            }

            //Save settings
			$random_20_digit_string = AIOWPSecurity_Utility::generate_alpha_numeric_random_string(20); // Generate random 20 char string for use during CAPTCHA encode/decode
            $aio_wp_security->configs->set_value('aiowps_captcha_secret_key', $random_20_digit_string);

            $aio_wp_security->configs->set_value('aiowps_enable_comment_captcha',isset($_POST["aiowps_enable_comment_captcha"])?'1':'');
            $aio_wp_security->configs->set_value('aiowps_enable_spambot_blocking',isset($_POST["aiowps_enable_spambot_blocking"])?'1':'');

            $aio_wp_security->configs->set_value('aiowps_enable_trash_spam_comments', isset($_POST['aiowps_enable_trash_spam_comments']) ? '1' : '');
            $aiowps_trash_spam_comments_after_days = '';
			if (isset($_POST['aiowps_trash_spam_comments_after_days'])) {
				if (!empty($_POST['aiowps_trash_spam_comments_after_days'])) {
					$aiowps_trash_spam_comments_after_days = sanitize_text_field($_POST['aiowps_trash_spam_comments_after_days']);
				}
				if (isset($_POST['aiowps_enable_trash_spam_comments']) && !is_numeric($aiowps_trash_spam_comments_after_days)) {
					$error = __('You entered a non numeric value for the "move spam comments to trash after number of days" field.','all-in-one-wp-security-and-firewall').' '.__('It has been set to the default value.','all-in-one-wp-security-and-firewall');
					$aiowps_trash_spam_comments_after_days = '14';//Set it to the default value for this field
					$this->show_msg_error(__('Attention!','all-in-one-wp-security-and-firewall').'&nbsp;'.htmlspecialchars($error));
				}
				$aiowps_trash_spam_comments_after_days = absint($aiowps_trash_spam_comments_after_days);
				$aio_wp_security->configs->set_value('aiowps_trash_spam_comments_after_days', $aiowps_trash_spam_comments_after_days);
			}

			//Commit the config settings
            $aio_wp_security->configs->save_config();
            
            AIOWPSecurity_Comment::trash_spam_comments();

            //Recalculate points after the feature status/options have been altered
            $aiowps_feature_mgr->check_feature_status_and_recalculate_points();

            //Now let's write the applicable rules to the .htaccess file
            $res = AIOWPSecurity_Utility_Htaccess::write_to_htaccess();

            if ($res)
            {
                $this->show_msg_updated(__('Settings were successfully saved', 'all-in-one-wp-security-and-firewall'));
            }
            else
            {
                $this->show_msg_error(__('Could not write to the .htaccess file. Please check the file permissions.', 'all-in-one-wp-security-and-firewall'));
            }
        }

        ?>
		<h2><?php _e('Comment spam settings', 'all-in-one-wp-security-and-firewall'); ?></h2>
        <form action="" method="POST">
        <?php wp_nonce_field('aiowpsec-comment-spam-settings-nonce'); ?>            

        <div class="postbox">
		<h3 class="hndle"><label for="title"><?php _e('Add CAPTCHA to comments form', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
        <div class="inside">
        <div class="aio_blue_box">
            <?php
			echo '<p>'.__('This feature will add a CAPTCHA field in the WordPress comments form.', 'all-in-one-wp-security-and-firewall').
			'<br>'.__('Adding a CAPTCHA field in the comment form is a simple way of greatly reducing spam comments from bots without using .htaccess rules.', 'all-in-one-wp-security-and-firewall').'</p>';
            ?>
        </div>
        <?php
        //Display security info badge
        $aiowps_feature_mgr->output_feature_details_badge("comment-form-captcha");
        ?>
        <table class="form-table">
            <tr valign="top">
				<th scope="row"><?php _e('Enable CAPTCHA on comment forms', 'all-in-one-wp-security-and-firewall'); ?>:</th>
                <td>
                <input id="aiowps_enable_comment_captcha" name="aiowps_enable_comment_captcha" type="checkbox"<?php if($aio_wp_security->configs->get_value('aiowps_enable_comment_captcha')=='1') echo ' checked="checked"'; ?> value="1"/>
				<label for="aiowps_enable_comment_captcha" class="description"><?php _e('Check this if you want to insert a CAPTCHA field on the comment forms.', 'all-in-one-wp-security-and-firewall'); ?></label>
                </td>
            </tr>            
        </table>
        </div></div>
            
        <div class="postbox">
		<h3 class="hndle"><label for="title"><?php _e('Block spambot comments', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
        <div class="inside">
        <div class="aio_blue_box">
            <?php
			echo '<p>'.__('A large portion of WordPress blog comment spam is mainly produced by automated bots and not necessarily by humans.', 'all-in-one-wp-security-and-firewall').
			'<br>'.__('This feature will greatly minimize the useless and unecessary traffic and load on your server resulting from spam comments by blocking all comment requests which do not originate from your domain.', 'all-in-one-wp-security-and-firewall').
			'<br>'.__('In other words, if the comment was not submitted by a human who physically submitted the comment on your site, the request will be blocked.', 'all-in-one-wp-security-and-firewall').'</p>';
            ?>
        </div>
        <?php
		$aio_wp_security->include_template('partials/non-apache-feature-notice.php');
        //Display security info badge
        $aiowps_feature_mgr->output_feature_details_badge("block-spambots");
        $blog_id = get_current_blog_id(); 
        if (is_multisite() && !is_main_site( $blog_id ))
        {
           //Hide config settings if MS and not main site
           AIOWPSecurity_Utility::display_multisite_message();
        }
        else
        {
        ?>
        <table class="form-table">
            <tr valign="top">
				<th scope="row"><?php _e('Block spambots from posting comments', 'all-in-one-wp-security-and-firewall'); ?>:</th>
                <td>
                <input id="aiowps_enable_spambot_blocking" name="aiowps_enable_spambot_blocking" type="checkbox"<?php if($aio_wp_security->configs->get_value('aiowps_enable_spambot_blocking')=='1') echo ' checked="checked"'; ?> value="1"/>
                <label for="aiowps_enable_spambot_blocking" class="description"><?php _e('Check this if you want to apply a firewall rule which will block comments originating from spambots.', 'all-in-one-wp-security-and-firewall'); ?></label>
				<span class="aiowps_more_info_anchor"><span class="aiowps_more_info_toggle_char">+</span><span class="aiowps_more_info_toggle_text"><?php _e('More info', 'all-in-one-wp-security-and-firewall'); ?></span></span>
                <div class="aiowps_more_info_body">
                        <?php 
                        echo '<p class="description">'.__('This feature will implement a firewall rule to block all comment attempts which do not originate from your domain.', 'all-in-one-wp-security-and-firewall').'</p>';
                        echo '<p class="description">'.__('A legitimate comment is one which is submitted by a human who physically fills out the comment form and clicks the submit button. For such events, the HTTP_REFERRER is always set to your own domain.', 'all-in-one-wp-security-and-firewall').'</p>';
                        echo '<p class="description">'.__('A comment submitted by a spambot is done by directly calling the comments.php file, which usually means that the HTTP_REFERRER value is not your domain and often times empty.', 'all-in-one-wp-security-and-firewall').'</p>';
						echo '<p class="description">'.__('This feature will check and block comment requests which are not referred by your domain thus greatly reducing your overall blog spam and PHP requests done by the server to process these comments.', 'all-in-one-wp-security-and-firewall').'</p>';
                        ?>
                </div>
                </td>
            </tr>            
        </table>
        <?php } //End if statement ?>
        </div></div>

		<div class="postbox">
			<h3 class="hndle"><label for="title"><?php _e('Comment processing', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="aiowps_trash_spam_comments_after_days"><?php _e('Trash spam comments', 'all-in-one-wp-security-and-firewall'); ?>:</label>
						</th>
						<td>
							<input name="aiowps_enable_trash_spam_comments" id="aiowps_enable_trash_spam_comments" type="checkbox" <?php checked($aio_wp_security->configs->get_value('aiowps_enable_trash_spam_comments'), 1); ?> value="1"/>
							<?php
							$disbled = '';
							if(!$aio_wp_security->configs->get_value('aiowps_enable_trash_spam_comments')) $disbled = "disabled";
							echo '<label for="aiowps_enable_trash_spam_comments" class="description">';
							printf(
								__('Move spam comments to trash after %s days.', 'all-in-one-wp-security-and-firewall'),
								'</label><input type="number" min="1" max="99" name="aiowps_trash_spam_comments_after_days" value="'.$aio_wp_security->configs->get_value('aiowps_trash_spam_comments_after_days').'" '.$disbled.'><label for="aiowps_enable_trash_spam_comments">'
							);
							echo '</label>';
							?>
							<span class="aiowps_more_info_anchor"><span class="aiowps_more_info_toggle_char">+</span><span class="aiowps_more_info_toggle_text"><?php _e('More info', 'all-in-one-wp-security-and-firewall'); ?></span></span>
							<div class="aiowps_more_info_body">
								<?php 
								echo '<p class="description">'.__('Enble this feature in order to move the spam comments to trash after given number of days.', 'all-in-one-wp-security-and-firewall').'</p>';
								?>
							</div>
						</td>
					</tr>            
				</table>
			</div>
		</div>

		<input type="submit" name="aiowps_apply_comment_spam_prevention_settings" value="<?php _e('Save settings', 'all-in-one-wp-security-and-firewall'); ?>" class="button-primary">
        </form>
        <?php
    }

	/**
	 * Renders the submenu's tab2 tab body.
	 *
	 * @return Void
	 */
	public function render_tab2() {
        global $aio_wp_security;
        global $aiowps_feature_mgr;
        include_once 'wp-security-list-comment-spammer-ip.php'; //For rendering the AIOWPSecurity_List_Table in tab2
        $spammer_ip_list = new AIOWPSecurity_List_Comment_Spammer_IP();

        //Do form submission tasks for auto block spam IP
        if(isset($_POST['aiowps_auto_spam_block']))
        {
            $error = '';
            $nonce=$_REQUEST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'aiowpsec-auto-block-spam-ip-nonce'))
            {
				$aio_wp_security->debug_logger->log_debug('Nonce check failed on auto block spam IPs options save.', 4);
				die('Nonce check failed on auto block spam IPs options save.');
            }

            $spam_ip_min_comments = sanitize_text_field($_POST['aiowps_spam_ip_min_comments_block']);
            if(!is_numeric($spam_ip_min_comments))
            {
                $error .= '<br />'.__('You entered a non numeric value for the minimum number of spam comments field. It has been set to the default value.','all-in-one-wp-security-and-firewall');
                $spam_ip_min_comments = '3';//Set it to the default value for this field
            }elseif(empty($spam_ip_min_comments)){
                $error .= '<br />'.__('You must enter an integer greater than zero for minimum number of spam comments field. It has been set to the default value.','all-in-one-wp-security-and-firewall');
                $spam_ip_min_comments = '3';//Set it to the default value for this field

            }

            if($error)
            {
                $this->show_msg_error(__('Attention!','all-in-one-wp-security-and-firewall').$error);
            }

            //Save all the form values to the options
            $aio_wp_security->configs->set_value('aiowps_enable_autoblock_spam_ip',isset($_POST["aiowps_enable_autoblock_spam_ip"])?'1':'');
            $aio_wp_security->configs->set_value('aiowps_spam_ip_min_comments_block',absint($spam_ip_min_comments));
            $aio_wp_security->configs->save_config();

            //Recalculate points after the feature status/options have been altered
            $aiowps_feature_mgr->check_feature_status_and_recalculate_points();

            $this->show_msg_settings_updated();
        }


        if (isset($_POST['aiowps_ip_spam_comment_search']))
        {
            $error = '';
            $nonce=$_REQUEST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'aiowpsec-spammer-ip-list-nonce'))
            {
				$aio_wp_security->debug_logger->log_debug('Nonce check failed for list spam comment IPs.', 4);
				die(__('Nonce check failed for list spam comment IPs.', 'all-in-one-wp-security-and-firewall'));
            }

            $min_comments_per_ip = sanitize_text_field($_POST['aiowps_spam_ip_min_comments']);
            if(!is_numeric($min_comments_per_ip))
            {
				$error .= '<br>'.__('You entered a non numeric value for the minimum spam comments per IP field.', 'all-in-one-wp-security-and-firewall').' '.__('It has been set to the default value.', 'all-in-one-wp-security-and-firewall');
                $min_comments_per_ip = '5';//Set it to the default value for this field
            }
            
            if($error)
            {
                $this->show_msg_error(__('Attention!','all-in-one-wp-security-and-firewall').$error);
            }
            
            //Save all the form values to the options
            $aio_wp_security->configs->set_value('aiowps_spam_ip_min_comments',absint($min_comments_per_ip));
            $aio_wp_security->configs->save_config();
			$info_msg_string = sprintf(__('Displaying results for IP addresses which have posted a minimum of %s spam comments.', 'all-in-one-wp-security-and-firewall'), $min_comments_per_ip);
            $this->show_msg_updated($info_msg_string);
            
        }
        
        if(isset($_REQUEST['action'])) //Do list table form row action tasks
        {
            if($_REQUEST['action'] == 'block_spammer_ip')
            { //The "block" link was clicked for a row in the list table
                $spammer_ip_list->block_spammer_ip_records(strip_tags($_REQUEST['spammer_ip']));
            }
        }

        ?>
        <div class="postbox">
			<h3 class="hndle"><label for="title"><?php _e('Auto block spammer IPs', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
            <div class="inside">
                <?php
                if($aio_wp_security->configs->get_value('aiowps_enable_autoblock_spam_ip')=='1' && !class_exists('Akismet')){
                    $akismet_link = '<a href="https://wordpress.org/plugins/akismet/" target="_blank">Akismet</a>';
                    $info_msg = sprintf( __('This feature has detected that %s is not active. It is highly recommended that you activate the Akismet plugin to make the most of this feature.', 'all-in-one-wp-security-and-firewall'), $akismet_link);

                    echo '<div class="aio_orange_box" id="message"><p><strong>'.$info_msg.'</strong></p></div>';
                }

                ?>
                <form action="" method="POST">
                <div class="aio_blue_box">
                    <?php
					echo '<p>'.__('This feature allows you to automatically and permanently block IP addresses which have exceeded a certain number of comments labelled as spam.', 'all-in-one-wp-security-and-firewall').'</p>'.
						'<p>'.__('Comments are usually labelled as spam either by the Akismet plugin or manually by the WP administrator when they mark a comment as "spam" from the WordPress Comments menu.', 'all-in-one-wp-security-and-firewall').'</p>'.
                        '<p><strong>'.__('NOTE: This feature does NOT use the .htaccess file to permanently block the IP addresses so it should be compatible with all web servers running WordPress.', 'all-in-one-wp-security-and-firewall').'</strong></p>';
                    ?>
                </div>
                    <?php
                    $min_block_comments = $aio_wp_security->configs->get_value('aiowps_spam_ip_min_comments_block');
                    if(!empty($min_block_comments)){
                        global $wpdb;
                        $sql = $wpdb->prepare('SELECT * FROM '.AIOWPSEC_TBL_PERM_BLOCK.' WHERE block_reason=%s', 'spam');
                        $total_res = $wpdb->get_results($sql);
                        ?>
                        <div class="aio_yellow_box">
                            <?php
                            if(empty($total_res)){
								echo '<p><strong>'.__('You currently have no IP addresses permanently blocked due to spam.', 'all-in-one-wp-security-and-firewall').'</strong></p>';
                            }else{
                                $total_count = count($total_res);
                                $todays_blocked_count = 0;
                                foreach($total_res as $blocked_item){
                                    $now = current_time( 'mysql' );
                                    $now_date_time = new DateTime($now);
                                    $blocked_date = new DateTime($blocked_item->blocked_date);
                                    if($blocked_date->format('Y-m-d') == $now_date_time->format('Y-m-d')) {
                                        //there was an IP added to permanent block list today
                                        ++$todays_blocked_count;
                                    }
                                }
								echo '<p><strong>'.__('Spammer IPs added to permanent block list today: ', 'all-in-one-wp-security-and-firewall').$todays_blocked_count.'</strong></p>'.
									'<hr><p><strong>'.__('All time total: ', 'all-in-one-wp-security-and-firewall').$total_count.'</strong></p>'.
									'<p><a class="button" href="admin.php?page='.AIOWPSEC_MAIN_MENU_SLUG.'&tab=tab3" target="_blank">'.__('View blocked IPs', 'all-in-one-wp-security-and-firewall').'</a></p>';
                            }
                            ?>
                        </div>

                    <?php
                    }
                //Display security info badge
                //$aiowps_feature_mgr->output_feature_details_badge("auto-block-spam-ip");
                    ?>
                    <?php wp_nonce_field('aiowpsec-auto-block-spam-ip-nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
						<th scope="row"><?php _e('Enable auto block of spam comment IPs', 'all-in-one-wp-security-and-firewall'); ?>:</th>
                        <td>
                            <input id="aiowps_enable_autoblock_spam_ip" name="aiowps_enable_autoblock_spam_ip" type="checkbox"<?php if($aio_wp_security->configs->get_value('aiowps_enable_autoblock_spam_ip')=='1') echo ' checked="checked"'; ?> value="1"/>
							<label for="aiowps_enable_autoblock_spam_ip" class="description"><?php _e('Check this box if you want this plugin to automatically block IP addresses which submit spam comments.', 'all-in-one-wp-security-and-firewall'); ?></label>
                        </td>
                    </tr>
                    <tr valign="top">
						<th scope="row"><label for="aiowps_spam_ip_min_comments_block"><?php _e('Minimum number of spam comments', 'all-in-one-wp-security-and-firewall'); ?>:</label></th>
                        <td><input id="aiowps_spam_ip_min_comments_block" type="text" size="5" name="aiowps_spam_ip_min_comments_block" value="<?php echo $aio_wp_security->configs->get_value('aiowps_spam_ip_min_comments_block'); ?>" />
							<span class="description"><?php _e('Specify the minimum number of spam comments for an IP address before it is permanently blocked.', 'all-in-one-wp-security-and-firewall'); ?></span>
							<span class="aiowps_more_info_anchor"><span class="aiowps_more_info_toggle_char">+</span><span class="aiowps_more_info_toggle_text"><?php _e('More info', 'all-in-one-wp-security-and-firewall'); ?></span></span>
                            <div class="aiowps_more_info_body">
                                <?php
								echo '<p class="description">'.__('Example 1: Setting this value to "1" will block ALL IP addresses which were used to submit at least one spam comment.', 'all-in-one-wp-security-and-firewall').'</p>';
								echo '<p class="description">'.__('Example 2: Setting this value to "5" will block only those IP addresses which were used to submit 5 spam comments or more on your site.', 'all-in-one-wp-security-and-firewall').'</p>';
                                ?>
                            </div>
                        </td>
                    </tr>
<!--                    <tr valign="top">-->
						<!-- <th scope="row"> --><?php //_e('Run now', 'all-in-one-wp-security-and-firewall'); ?><!--:</th>-->
						<!-- <td><input type="submit" name="aiowps_auto_spam_block_run" value=" --><?php //_e('Run spam IP blocking now', 'all-in-one-wp-security-and-firewall'); ?><!--" class="button-secondary" />-->
<!--                            <span class="description">--><?php //_e('This feature normally runs automatically whenever a comment is submitted but you can run it manually by clicking this button. (useful for older comments)', 'all-in-one-wp-security-and-firewall');?><!--</span>-->
<!--                        </td>-->
<!--                    </tr>-->

                </table>
				<input type="submit" name="aiowps_auto_spam_block" value="<?php _e('Save settings', 'all-in-one-wp-security-and-firewall'); ?>" class="button-primary">
                </form>
            </div></div>

        <div class="postbox">
		<h3 class="hndle"><label for="title"><?php _e('List spammer IP addresses', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
        <div class="inside">
            <div class="aio_blue_box">
                <?php
				echo '<p>'.__('This section displays a list of the IP addresses of the people or bots who have left spam comments on your site.', 'all-in-one-wp-security-and-firewall').
				'<br>'.__('This information can be handy for identifying the most persistent IP addresses or ranges used by spammers.', 'all-in-one-wp-security-and-firewall').
				'<br>'.__('By inspecting the IP address data coming from spammers you will be in a better position to determine which addresses or address ranges you should block by adding them to the permanent block list.', 'all-in-one-wp-security-and-firewall').
				'<br>'.__('To add one or more of the IP addresses displayed in the table below to your blacklist, simply click the "Block" link for the individual row or select more than one address using the checkboxes and then choose the "block" option from the Bulk Actions dropdown list and click the "Apply" button.', 'all-in-one-wp-security-and-firewall').'</p>';
                ?>
            </div>

        <form action="" method="POST">
        <?php wp_nonce_field('aiowpsec-spammer-ip-list-nonce'); ?>
        <table class="form-table">
            <tr valign="top">
				<th scope="row"><label for="aiowps_spam_ip_min_comments"><?php _e('Minimum number of spam comments per IP', 'all-in-one-wp-security-and-firewall'); ?>:</label></th>
                <td><input id="aiowps_spam_ip_min_comments" type="text" size="5" name="aiowps_spam_ip_min_comments" value="<?php echo $aio_wp_security->configs->get_value('aiowps_spam_ip_min_comments'); ?>" />
				<span class="description"><?php _e('This field allows you to list only those IP addresses which have been used to post X or more spam comments.', 'all-in-one-wp-security-and-firewall'); ?></span>
				<span class="aiowps_more_info_anchor"><span class="aiowps_more_info_toggle_char">+</span><span class="aiowps_more_info_toggle_text"><?php _e('More info', 'all-in-one-wp-security-and-firewall'); ?></span></span>
                <div class="aiowps_more_info_body">
                    <?php 
					echo '<p class="description">'.__('Example 1: Setting this value to "0" or "1" will list ALL IP addresses which were used to submit spam comments.', 'all-in-one-wp-security-and-firewall').'</p>';
					echo '<p class="description">'.__('Example 2: Setting this value to "5" will list only those IP addresses which were used to submit 5 spam comments or more on your site.', 'all-in-one-wp-security-and-firewall').'</p>';
                    ?>
                </div>

                </td> 
            </tr>
        </table>
		<input type="submit" name="aiowps_ip_spam_comment_search" value="<?php _e('Find IP addresses', 'all-in-one-wp-security-and-firewall'); ?>" class="button-primary">
        </form>
        </div></div>
        <div class="postbox">
		<h3 class="hndle"><label for="title"><?php _e('Spammer IP address results', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
        <div class="inside">
            <?php
            if (is_multisite() && get_current_blog_id() != 1)
            {
                    echo '<div class="aio_yellow_box">';
                    echo '<p>'.__('The plugin has detected that you are using a Multi-Site WordPress installation.', 'all-in-one-wp-security-and-firewall').'</p>
                          <p>'.__('Only the "superadmin" can block IP addresses from the main site.', 'all-in-one-wp-security-and-firewall').'</p>
                          <p>'.__('Take note of the IP addresses you want blocked and ask the superadmin to add these to the blacklist using the "Blacklist Manager" on the main site.', 'all-in-one-wp-security-and-firewall').'</p>';
                    echo '</div>';
            }
            //Fetch, prepare, sort, and filter our data...
            $spammer_ip_list->prepare_items();
            //echo "put table of locked entries here"; 
            ?>
			<form id="tables-filter" method="get">
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <input type="hidden" name="tab" value="<?php echo esc_attr($_REQUEST['tab']); ?>" />
            <!-- Now we can render the completed list table -->
            <?php $spammer_ip_list->display(); ?>
            </form>
        </div></div>
        <?php
    }
        
    
    function render_tab3()
    {
        global $aiowps_feature_mgr;
        global $aio_wp_security;
        if(isset($_POST['aiowps_save_bp_spam_settings']))//Do form submission tasks
        {
            $nonce=$_REQUEST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'aiowpsec-bp-spam-settings-nonce'))
            {
                $aio_wp_security->debug_logger->log_debug("Nonce check failed on save comment spam settings!",4);
                die("Nonce check failed on save comment spam settings!");
            }

            //Save settings
            $aio_wp_security->configs->set_value('aiowps_enable_bp_register_captcha',isset($_POST["aiowps_enable_bp_register_captcha"])?'1':'');

            //Commit the config settings
            $aio_wp_security->configs->save_config();
            
            //Recalculate points after the feature status/options have been altered
            $aiowps_feature_mgr->check_feature_status_and_recalculate_points();

            $this->show_msg_updated(__('Settings were successfully saved', 'all-in-one-wp-security-and-firewall'));
        }

        ?>
		<h2><?php _e('BuddyPress spam settings', 'all-in-one-wp-security-and-firewall'); ?></h2>
        <form action="" method="POST">
        <?php wp_nonce_field('aiowpsec-bp-spam-settings-nonce'); ?>            

        <div class="postbox">
		<h3 class="hndle"><label for="title"><?php _e('Add CAPTCHA to BuddyPress registration form', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
        <div class="inside">
        <div class="aio_blue_box">
            <?php
			echo '<p>'.__('This feature will add a simple math CAPTCHA field in the BuddyPress registration form.', 'all-in-one-wp-security-and-firewall').
			'<br>'.__('Adding a CAPTCHA field in the registration form is a simple way of greatly reducing spam signups from bots without using .htaccess rules.', 'all-in-one-wp-security-and-firewall').'</p>';
            ?>
        </div>
        <?php
        if (defined('BP_VERSION')){
            //Display security info badge
            $aiowps_feature_mgr->output_feature_details_badge("bp-register-captcha");
        ?>
        <table class="form-table">
            <tr valign="top">
				<th scope="row"><label for="aiowps_enable_bp_register_captcha"><?php _e('Enable CAPTCHA on BuddyPress registration form', 'all-in-one-wp-security-and-firewall'); ?>:</label></th>
                <td>
				<input id="aiowps_enable_bp_register_captcha" name="aiowps_enable_bp_register_captcha" type="checkbox"<?php if($aio_wp_security->configs->get_value('aiowps_enable_bp_register_captcha')=='1') echo ' checked="checked"'; ?> value="1">
				<label for="aiowps_enable_bp_register_captcha"><?php _e('Check this if you want to insert a CAPTCHA field on the BuddyPress registration forms.', 'all-in-one-wp-security-and-firewall'); ?></label>
                </td>
			</tr>
        </table>
        </div></div>
        <input type="submit" name="aiowps_save_bp_spam_settings" value="<?php _e('Save settings', 'all-in-one-wp-security-and-firewall'); ?>" class="button-primary">
        </form>
        <?php
        }else{
            $this->show_msg_error(__('BuddyPress is not active! In order to use this feature you will need to have BuddyPress installed and activated.', 'all-in-one-wp-security-and-firewall'));
        }
    }

    function render_tab4()
    {
        global $aiowps_feature_mgr;
        global $aio_wp_security;
        if(isset($_POST['aiowps_save_bbp_spam_settings']))//Do form submission tasks
        {
            $nonce=$_REQUEST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'aiowpsec-bbp-spam-settings-nonce'))
            {
                $aio_wp_security->debug_logger->log_debug("Nonce check failed on save bbp spam settings!",4);
				die('Nonce check failed on save bbPress spam settings.');
            }

            //Save settings
            $aio_wp_security->configs->set_value('aiowps_enable_bbp_new_topic_captcha',isset($_POST["aiowps_enable_bbp_new_topic_captcha"])?'1':'');

            //Commit the config settings
            $aio_wp_security->configs->save_config();
            
            //Recalculate points after the feature status/options have been altered
            $aiowps_feature_mgr->check_feature_status_and_recalculate_points();

            $this->show_msg_updated(__('Settings were successfully saved', 'all-in-one-wp-security-and-firewall'));
        }

        ?>
		<h2><?php _e('bbPress spam settings', 'all-in-one-wp-security-and-firewall'); ?></h2>
        <form action="" method="POST">
        <?php wp_nonce_field('aiowpsec-bbp-spam-settings-nonce'); ?>            

        <div class="postbox">
		<h3 class="hndle"><label for="title"><?php _e('Add CAPTCHA to bbPress new topic form', 'all-in-one-wp-security-and-firewall'); ?></label></h3>
        <div class="inside">
        <div class="aio_blue_box">
            <?php
			echo '<p>'.__('This feature will add a simple math CAPTCHA field in the bbPress new topic form.', 'all-in-one-wp-security-and-firewall').
			'<br>'.__('Adding a CAPTCHA field in this form is a simple way of greatly reducing spam submitted from bots.', 'all-in-one-wp-security-and-firewall').'</p>';
            ?>
        </div>
        <?php
		if (class_exists('bbPress')) {
            //Display security info badge
            $aiowps_feature_mgr->output_feature_details_badge("bbp-new-topic-captcha");
        ?>
        <table class="form-table">
            <tr valign="top">
				<th scope="row"><label for="aiowps_enable_bbp_new_topic_captcha"><?php _e('Enable CAPTCHA on bbPress new topic form', 'all-in-one-wp-security-and-firewall'); ?>:</label></th>
                <td>
				<input id="aiowps_enable_bbp_new_topic_captcha" name="aiowps_enable_bbp_new_topic_captcha" type="checkbox"<?php if($aio_wp_security->configs->get_value('aiowps_enable_bbp_new_topic_captcha')=='1') echo ' checked="checked"'; ?> value="1">
				<label for="aiowps_enable_bbp_new_topic_captcha"><?php _e('Check this if you want to insert a CAPTCHA field on the bbPress new topic forms.', 'all-in-one-wp-security-and-firewall'); ?></label>
                </td>
			</tr>
        </table>
        </div></div>
		<input type="submit" name="aiowps_save_bbp_spam_settings" value="<?php _e('Save settings', 'all-in-one-wp-security-and-firewall'); ?>" class="button-primary">
        </form>
        <?php
        }else{
			$this->show_msg_error(__('bbPress is not active. In order to use this feature you will need to have bbPress installed and activated.', 'all-in-one-wp-security-and-firewall'));
        }
    }
    
} //end class
