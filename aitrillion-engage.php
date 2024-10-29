<?php 

/**
 * Plugin Name:       AiTrillion Engage
 * Plugin URI:        https://wordpress.org/plugins/aitrillion-engage/
 * Description:       Exclusive Email Marketing & Automation, SMS, Web Push, Smart Popup, WhatsApp chat, Announcement bar and more...
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      5.6
 * Author:            AiTrillion
 * Author URI:        https://www.aitrillion.com
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * External Service:  This plugin uses the AiTrillion API to manage customer data and engagement. More details at https://aitrillion.com/
 * Service Terms:     https://aitrillion.com/terms-of-service/
 * Service Privacy Policy: https://aitrillion.com/privacy-policy/
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define( 'AITRILLION_ENGAGE_ENVIRONMENT', 'production'); //'production' // 'development

// Defines the path to the main plugin file.
define( 'AITRILLION_ENGAGE_FILE', __FILE__ );

// Defines the path to be used for includes.
define( 'AITRILLION_ENGAGE_PATH', plugin_dir_path( AITRILLION_ENGAGE_FILE ) );

// Define end point of Ai Trillion 
if(AITRILLION_ENGAGE_ENVIRONMENT == "development"){
    define('AITRILLION_ENGAGE_END_POINT', 'https://connector-api-dev.aitrillion.com/');
    define('AITRILLION_ENGAGE_APP_URL', 'https://ai-dev.aitrillion.com/');
} else{
    define('AITRILLION_ENGAGE_END_POINT', 'https://connector-api.aitrillion.com/');
    define('AITRILLION_ENGAGE_APP_URL', 'https://app.aitrillion.com/'); 
}

define('AITRILLION_ENGAGE_DOMAIN', preg_replace("(^https?://)", "", site_url() ));

include AITRILLION_ENGAGE_PATH . 'common_functions.php';
include AITRILLION_ENGAGE_PATH . 'cron-jobs.php';
include AITRILLION_ENGAGE_PATH . 'platform_api.php';
include AITRILLION_ENGAGE_PATH . 'data_sync.php';
include AITRILLION_ENGAGE_PATH . 'shortcodes.php';

    add_action('admin_menu', 'aitrillion_engage_admin_menu');

    function aitrillion_engage_admin_menu() {

        //create new top-level menu
        add_menu_page(
            'AiTrillion Engage', 
            'AiTrillion Engage',
            'manage_options', 
            'aitrillion-engage.php',
            'aitrillion_engage_options_page'
        );

        add_submenu_page(
            'aitrillion-engage.php',
            'AiTrillion Engage Settings',
            'Settings',
            'manage_options',
            'aitrillion-engage.php',
            'aitrillion_engage_options_page'
        );
    }

    add_action('admin_init', 'aitrillion_engage_admin_init');

    function aitrillion_engage_admin_init(){

        register_setting( 'aitrillion_engage_options', '_aitrillion_engage_api_key');
        register_setting( 'aitrillion_engage_options', '_aitrillion_engage_api_password');
        register_setting( 'aitrillion_engage_options', '_aitrillion_engage_script_url' );
    }

    add_action( 'admin_action_aitrillion_engage_clear_log', 'aitrillion_engage_clear_log' );
    function aitrillion_engage_clear_log()
    {
        $wpDir = wp_upload_dir();
        
        file_put_contents($wpDir["basedir"].'/aitrillion-engage-log.txt', '');

        set_transient('aitrillion_engage_clear_log_message', 'Log Cleared', 60); // 60 seconds

        wp_redirect( admin_url( 'admin.php' ).'?page=aitrillion-engage.php' );
        exit();
    }
    
    add_action('admin_notices', 'aitrillion_engage_clear_log_notice');

    function aitrillion_engage_clear_log_notice() {
        $message = get_transient('aitrillion_engage_clear_log_message');
        if (isset($message) && $message != "") {
            echo '<div class="notice notice-warning is-dismissible">
                <p><strong>'.esc_html(sanitize_text_field($message)).'</p>
                </div>';
            delete_transient('aitrillion_engage_clear_log_message');
        }
    }


 // display the admin options page
    function aitrillion_engage_options_page() {
        $wpDir = wp_upload_dir();

?>
        <div class="wrap">
            <h1>AiTrillion Engage Settings</h1>

            <form method="post" action="options.php">

                <?php settings_errors('aitrillion_engage_options'); ?>

                <?php settings_fields( 'aitrillion_engage_options' ); ?>

                <?php do_settings_sections( 'aitrillion_engage_options' ); ?>

                <div class="card" style="max-width: 700px">
                    Login to <a href="<?php echo esc_url(sanitize_text_field(AITRILLION_ENGAGE_APP_URL));?>" target="_blank">AiTrillion Engage</a>
                </div>

                <div class="card" style="max-width: 700px">
                    <?php settings_errors(); ?>


                    <table class="form-table">
                        <tr valign="top">
                        <th scope="row">AiTrillion Engage API Key</th>
                        <td><input type="text" name="_aitrillion_engage_api_key" value="<?php echo esc_attr( sanitize_text_field(get_option('_aitrillion_engage_api_key')) ); ?>" /></td>
                        </tr>

                        <tr valign="top">
                        <th scope="row">AiTrillion Engage API Password</th>
                        <td>
                            <input type="password" name="_aitrillion_engage_api_password" value="<?php echo esc_attr( sanitize_text_field(get_option('_aitrillion_engage_api_password')) ); ?>" />
                        </td>
                        </tr>

                        <tr valign="top">
                        <th scope="row">AiTrillion Engage Script URL</th>
                        <td>

                            <textarea rows="5" cols="50" name="_aitrillion_engage_script_url"><?php echo esc_attr( sanitize_text_field(get_option('_aitrillion_engage_script_url')) ); ?></textarea>
                        </td>
                        </tr>

                        <?php 

                            $_aitrillion_engage_api_key = get_option( '_aitrillion_engage_api_key' );
                            $_aitrillion_engage_api_password = get_option( '_aitrillion_engage_api_password' );

                            if($_aitrillion_engage_api_key && $_aitrillion_engage_api_password){
                        ?>

                        <tr>
                            <th scope="row">AiTrillion Engage connection</th>
                            <td>
                                <?php 

                                    $url = AITRILLION_ENGAGE_END_POINT.'validate?shop_name='.AITRILLION_ENGAGE_DOMAIN;

                                    $response = wp_remote_get( $url, array(
                                        'headers' => array(
                                            'Authorization' => 'Basic ' . base64_encode( $_aitrillion_engage_api_key.':'.$_aitrillion_engage_api_password )
                                        )
                                    ));


                                    if( !is_wp_error( $response['body'] ) ) {

                                        $r = json_decode($response['body']);

                                        if(isset($r->status) && $r->status == 'sucess'){

                                            echo '<strong style="color: green">Active</strong>';

                                        }else{

                                            echo '<strong style="color: red">In-active</strong>';

                                            if(isset($r->status) && $r->status == 'error'){

                                                 echo ' <strong style="color: red">('.esc_html(sanitize_text_field($r->msg)).')</strong>';

                                            }elseif(isset($r->message)){

                                                echo ' <strong style="color: red">('.esc_html(sanitize_text_field($r->message)).')</strong>';
                                            }

                                        }
                                    }else{
                                        echo ' <strong style="color: red">('.esc_html(sanitize_text_field($response['body']->get_error_message())).')</strong>';   
                                    }


                                ?>
                            </td>
                        </tr>

                        <?php 
                            }
                        ?>
                    </table>    
                </div>


                <?php submit_button(); ?>

            </form>

            <div class="card" style="max-width: 700px">
                <table cellpadding="2" cellspacing="2">
                    <tr>
                        <td colspan="2">
                            <h3>AiTrillion Engage syncing status logs</h3>
                        </td>
                    </tr>

                    <tr>
                        <td><strong>Failed customers sync:</strong></td>
                        <td>
                            <?php 
                                $customers = get_users(array(
                                                'meta_key' => '_aitrillion_engage_user_sync',
                                                'meta_value' => 'false'
                                            ));

                                if(!empty($customers)){
                                    echo esc_html(sanitize_text_field(count($customers)));
                                }else{
                                    echo '0';
                                }


                            ?>
                        </td>
                    </tr>

                </table>

                <a href="<?php echo esc_url(sanitize_text_field($wpDir["baseurl"]."/aitrillion-engage-log.txt"));?>" target="_blank">View Log File</a>&nbsp;&nbsp;

                <?php 
                    $filesize = filesize($wpDir["basedir"].'/aitrillion-engage-log.txt'); // bytes
                    $filesize = round($filesize / 1024 / 1024, 1); // megabytes with 1 digit
                ?>
                <a href="<?php echo esc_url(sanitize_text_field(admin_url( 'admin.php' ).'?action=aitrillion_engage_clear_log')); ?>" onclick="return confirm('Are you sure?')">Clear Log (File size: <?php echo esc_html(sanitize_text_field($filesize));?> MB)</a>&nbsp;&nbsp;
            </div>
        </div>
<?php
    }


