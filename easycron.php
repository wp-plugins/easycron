<?php
/*
Plugin Name: EasyCron
Plugin URI: https://www.easycron.com
Description: EasyCron helps you easily configure a cron job without the need of Wp-Cron and Cron on your server.
Version: 1.1
Author: easycron
Author URI: https://www.easycron.com
License: GPL v2
*/

define('EASYCRON_VERSION', '1.0');
define('EASYCRON_PLUGIN_URL', plugin_dir_url( __FILE__ ));

if ( is_admin() ){ // admin actions
    add_action('admin_init', 'easycron_register_settings');
    add_action('admin_menu', 'easycron_plugin_menu');
}

function easycron_plugin_menu() {
	$page = add_menu_page('EasyCron Settings', 'EasyCron', 'administrator', __FILE__, 'easycron_option_page', plugins_url('/easycron_16x16.png', __FILE__));
	add_action('admin_print_styles-'. $page, 'easycron_add_admin_styles');
}

function easycron_add_admin_styles() {
	wp_enqueue_style('easycronAdminStyle');
}

function easycron_register_settings() {
	register_setting('easycron_options', 'easycron_options', 'easycron_options_validate');
	add_settings_section('easycron_trigger_settings', 'Trigger Settings', 'easycron_section_text', 'easycron');
    add_settings_field('easycron-status', 'Status', 'easycron_input_status', 'easycron', 'easycron_trigger_settings');
    add_settings_field('easycron-api-token', 'API Token', 'easycron_input_api_token', 'easycron', 'easycron_trigger_settings');
    add_settings_field('easycron-cron-expression', 'Cron Expression', 'easycron_input_cron_expression', 'easycron', 'easycron_trigger_settings');
    add_settings_field('easycron-email-me', 'Email Me', 'easycron_input_email_me', 'easycron', 'easycron_trigger_settings');
    add_settings_field('easycron-log', 'Log', 'easycron_input_log', 'easycron', 'easycron_trigger_settings');

    add_settings_field('easycron-cron-job-id', '', 'easycron_input_cron_job_id', 'easycron', 'easycron_trigger_settings');

	//add css
	wp_register_style('easycronAdminStyle', plugins_url('style.css', __FILE__));
}

function easycron_section_text() {
    ?>
    <table class="form-table">
        <tr>
            <td>
               <div class="easycron-box">Before using EasyCron, please add <code>define('DISABLE_WP_CRON', true);</code> to your wp-config.php file to disable the WP Cron System.<br>
               </div>  
            </td>
        </tr>
    </table>
    <?php
}

function easycron_input_status() {
	$options = get_option('easycron_options');
	if (!isset($options['status']) || $options['status']==null) {
		$options['status'] = "0";
	}
	?><select id="easycron-status" style="width:150px;" name="easycron_options[status]">
		<option value="0" <?php echo ($options['status']=="0"?"selected":"")?>>Disabled</option>
		<option value="1" <?php echo ($options['status']=="1"?"selected":"")?>>Enabled</option>
	</select><?php
}

function easycron_input_api_token() {
	$options = get_option('easycron_options');
	?><input id="easycron-api-token" style="width:250px;" name="easycron_options[api-token]" size="40" type="text" value="<?php echo $options['api-token']; ?>" /> <i>You can get API token for free at <a href="https://www.easycron.com/user/token">https://www.easycron.com/user/token</a>.</i><?php
}

function easycron_input_cron_expression() {
	$options = get_option('easycron_options');
	?><input id="easycron-cron-expression" style="width:250px;" name="easycron_options[cron-expression]" size="40" type="text" value="<?php echo $options['cron-expression']; ?>" /> <i>Checkout <a href="https://www.easycron.com/faq/What-cron-expression-does-easycron-support">cron expressions supported</a>.</i><?php
}

function easycron_input_email_me() {
	$options = get_option('easycron_options');
	if (!isset($options['email-me']) || $options['email-me']==null) {
		$options['email-me'] = "0";
	}
	?><select id="easycron-email-me" style="width:150px;" name="easycron_options[email-me]">
		<option value="0" <?php echo ($options['email-me']=="0"?"selected":"")?>>Never</option>
		<option value="1" <?php echo ($options['email-me']=="1"?"selected":"")?>>If execution fails</option>
        <option value="2" <?php echo ($options['email-me']=="2"?"selected":"")?>>After execution</option>
	</select><?php
}

function easycron_input_log() {
	$options = get_option('easycron_options');
	if (!isset($options['log']) || $options['log']==null) {
		$options['log'] = "0";
	}
	?><select id="easycron-log" style="width:150px;" name="easycron_options[log]">
		<option value="0" <?php echo ($options['log']=="0"?"selected":"")?>>No</option>
		<option value="1" <?php echo ($options['log']=="10240"?"selected":"")?>>Yes</option>
	</select> <i>Log cron job's output (You can view the logs at <a href="https://www.easycron.com">https://www.easycron.com</a>.</i><?php
}

function easycron_input_cron_job_id() {
	$options = get_option('easycron_options');
	?><input id="easycron-input-cron-job-id" name="easycron_options[cron-job-id]" type="hidden" value="<?php echo $options['cron-job-id']; ?>" /><?php
}

function easycron_connect($action, $easycron_settings) {
    $settings_array = array();
    foreach ($easycron_settings as $key => $value) {
        $settings_array[] = $key . '=' . urlencode($value);
    }
    $settings_str = implode('&', $settings_array);
    $url = 'https://www.easycron.com/rest/' . $action . '?' . $settings_str;
    $result = wp_remote_get($url);

    if (is_wp_error($result)) {
       $result['status'] = 'error';
       $result['error']['message'] = $result->get_error_message();
       return $result;
    } else {
       return json_decode($result['body'], TRUE);
    }
}

function easycron_options_validate($input) {

	$input["message"] = "";
	$input["error"] = false;

    if (strlen($input["api-token"]) != 32) {
        $input["message"] = "The API token should be 32 characters' long.";
		$input["error"] = true;
    }

    if (!$input["error"]) {
        $easycron_settings = array(
            'token' => $input["api-token"],
            'cron_expression' => $input["cron-expression"],
            'email_me' => $input["email-me"],
            'log_output_length' => $input["log"],
            'url' => get_site_url() . '/wp-cron.php',
            'testfirst' => 0,
        );
        $cron_job_id = isset($input["cron-job-id"]) ? $input["cron-job-id"] : '';
        if (empty($cron_job_id)) {
            $action = 'add';
        } else {
            // if there is already cron job ID in wordpress system, use it
            $action = 'edit';
            $easycron_settings['id'] = $cron_job_id;
        }

        $result = easycron_connect($action, $easycron_settings);

        if ($result['status'] == 'success') {
            // Setting change done. The status switch is performed below
            if ($input['status']) {
                $action = 'enable';
            } else {
                $action = 'disable';
            }
            $cron_job_id = $result['cron_job_id'];
            $result = easycron_connect($action, array(
                'token' => $input["api-token"],
                'id' => $cron_job_id,         
            ));

            if ($result['status'] == 'success') {
                $input['cron-job-id'] = $cron_job_id;
                $input["error"] = false;
            } else {
                $input["message"] = $result['error']['message'];
		        $input["error"] = true;
            }
        } else {
            
            $input["error"] = true;
            $input["message"] = $result['error']['message'];
            
            if (($action == 'edit') && ($result['error']['code'] == 25)) {
                // Something wrong with the cron job ID, create a new cron job
                $result = easycron_connect('add', $easycron_settings);
                if ($result['status'] == 'success') {
                    $input['cron-job-id'] = $result['cron_job_id'];
                    $input["error"] = false;
                } else {
                    $input["message"] = $result['error']['message'];
                }
            }
        }
    }
	return $input;
}

function easycron_option_page() {
	if (!current_user_can('administrator'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	$options = get_option('easycron_options');
	?>

    <div class="wrap">
	<div id="icon-easycron" class="icon32"><br></div>
	<h2>EasyCron</h2>

	<?php
	if ($options["error"] == true) {
		?>
        <div id="message" class="error">
            <p><strong><?php echo $options["message"]; ?></strong></p>
        </div>
        <?php
	} else {
        if( isset($_GET['settings-updated']) ) { ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Settings saved.') ?></strong></p>
            </div>
        <?php
        }
    }
	?>
	<form method="post" action="options.php" id="easycron-settings-form">
	<?php settings_fields( 'easycron_options' ); ?>
    
    <?php
    do_settings_sections('easycron');
    ?>
    <p class="submit">
        <input type="submit" name="easycron_btn_save" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

	</form>
	</div>
	<?php
}
