<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// 
// include aitrillion js script
add_action('wp_enqueue_scripts', 'aitrillion_engage_script');

// create shortcode
add_shortcode('aitrillion_engage_site_reviews', 'aitrillion_engage_site_reviews_shortcode');

function aitrillion_engage_script() {

    $username = get_current_user();
    $userid = get_current_user_id();
    $current_user = wp_get_current_user();

    // get aitrillion js script
    $aitrilltion_script = get_option('_aitrillion_engage_script_url');

    if($aitrilltion_script){

        // get aitrillion script version
        $script_version = get_option('_aitrillion_engage_script_version');

        if(empty($script_version)){
            $script_version = 1;
        }

        if($userid){

            $script = "
                <!-- AITRILLION APP SCRIPT -->

                var aioMeta = {
                    meta_e: '".$current_user->user_email."',
                    meta_i: '".$userid."',
                    meta_n: '".$username."',
                } 

                <!-- END AITRILLION APP SCRIPT -->";
        }else{

            $script = "
                <!-- AITRILLION APP SCRIPT -->

                var aioMeta = {
                    meta_e: '',
                    meta_i: '',
                    meta_n: '',
                } 

                <!-- END AITRILLION APP SCRIPT -->";
        }

        $url = explode('?', $aitrilltion_script);

        wp_enqueue_script( 'aitrillion-engage-script', $url[0].'?v='.$script_version.'&'.$url[1], array(), null);

        wp_add_inline_script('aitrillion-engage-script', $script, 'after');     
    }

    
}

function aitrillion_engage_site_reviews_shortcode() {

    $message ='<div class="egg-site-all-reviews"></div>'; 
    return $message;
}

/*
* 
* Create sync status custom column into user list
*/
add_filter('manage_users_columns', 'aitrillion_engage_user_sync_column');
function aitrillion_engage_user_sync_column($columns) {
    $columns['aitrillion_engage_status'] = 'AiT Engage Sync Status';
    return $columns;
}    

add_action('manage_users_custom_column',  'aitrillion_engage_user_sync_status', 10, 3);
function aitrillion_engage_user_sync_status( $output, $column_key, $user_id ) {
    
    switch ( $column_key ) {
        case 'aitrillion_engage_status':
            $value = get_user_meta( $user_id, '_aitrillion_engage_user_sync', true );

            return $value;
            break;
        default: break;
    }

    // if no column slug found, return default output value
    return $output;
}
// end of user list sync status custom column

?>