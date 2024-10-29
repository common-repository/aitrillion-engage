<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If a cron job interval does not already exist, create one.
add_filter( 'cron_schedules', 'aitrillion_engage_check_every_minute' );

function aitrillion_engage_check_every_minute( $schedules ) {
    $schedules['every_minute'] = array(
        'interval' => 60, // in seconds
        'display'  => esc_html__('Every Minute', 'aitrillion-engage'),
    );

    return $schedules;
}

// Unless an event is already scheduled, create one.
add_action( 'init', 'aitrillion_engage_data_sync_cron' );
 
function aitrillion_engage_data_sync_cron() {

    if ( ! wp_next_scheduled( 'aitrillion_engage_data_sync_schedule' ) ) {
        wp_schedule_single_event( time(), 'aitrillion_engage_data_sync_schedule' );
    }
}

// call sync function on cron action 
add_action( 'aitrillion_engage_data_sync_schedule', 'aitrillion_engage_data_sync_action' );


/**
* cron data sync function, execute on each cron call
* 
*/
function aitrillion_engage_data_sync_action() { 

    aitrillion_engage_sync_new_customers();
    aitrillion_engage_sync_updated_customers();  
    aitrillion_engage_sync_deleted_customers();  
}

/**
* sync new customers
*
*/
function aitrillion_engage_sync_new_customers(){
    
    // get ids of new customer registered since last cron call
    $users = get_option( '_aitrillion_engage_created_users' );

    aitrillion_engage_api_log('new user sync log '.print_r($users, true).PHP_EOL);

    // variable to store failed sync users id
    $failed_sync_users = array();
    $failed_sync_users_data = array();

    if(!empty($users)){

        // remove duplicate ids
        $users = array_unique($users);

        $synced_users = array();

        foreach($users as $user_id){

            aitrillion_engage_api_log('user id '.$user_id.PHP_EOL);

            // get customer data from common function
            $c = aitrilltion_engage_get_customer( $user_id );
            
            $json_payload = wp_json_encode($c);

            $_aitrillion_engage_api_key = get_option( '_aitrillion_engage_api_key' );
            $_aitrillion_engage_api_password = get_option( '_aitrillion_engage_api_password' );

            $url = AITRILLION_ENGAGE_END_POINT.'customers/create';

            $response = wp_remote_post( $url, array(
                'headers' => array(
                            'Authorization' => 'Basic ' . base64_encode( $_aitrillion_engage_api_key.':'.$_aitrillion_engage_api_password )
                        ),
                'body' => $json_payload
            ));

            $r = json_decode($response['body']);

            // if sync failed, store failed user into separate variable
            if(!isset($r->status) && $r->status != 'success'){

                $failed_sync_users_data[$user_id][] = array('user_id' => $user_id, 'error' => $r->message, 'date' => gmdate('Y-m-d H:i:s'));
                
                $failed_sync_users[] = $user_id;

            }else{

                // flag this user as synced successfully
                update_user_meta($user_id, '_aitrillion_engage_user_sync', 'true');
                $synced_users[] = $user_id;
            }

            aitrillion_engage_api_log('API Response for user id: '.$user_id.PHP_EOL.print_r($r, true));
        }

        if(!empty($failed_sync_users)){
            // if there are failed sync users, add them into next cron queue
            update_option('_aitrillion_engage_created_users', $failed_sync_users, false);
            update_option('_aitrillion_engage_failed_sync_users', $failed_sync_users_data, false);

        }else{

            // all user synced successfully, clear queue
            delete_option('_aitrillion_engage_created_users');    
        }
    }
}


/**
* sync modified customers
*
*/
function aitrillion_engage_sync_updated_customers(){

    // get ids of modified customers since last cron call
    $users = get_option( '_aitrillion_engage_updated_users' );

    aitrillion_engage_api_log('udpated users sync log '.print_r($users, true).PHP_EOL);

    $failed_sync_users = array();

    if(!empty($users)){

        // remove duplicate ids
        $users = array_unique($users);

        $synced_users = array();

        foreach($users as $user_id){

            // get customer data from common function
            $c = aitrilltion_engage_get_customer( $user_id );
            
            $json_payload = wp_json_encode($c);

            $_aitrillion_engage_api_key = get_option( '_aitrillion_engage_api_key' );
            $_aitrillion_engage_api_password = get_option( '_aitrillion_engage_api_password' );

            $bearer = base64_encode( $_aitrillion_engage_api_key.':'.$_aitrillion_engage_api_password );

            $_aitrillion_engage_api_key = get_option( '_aitrillion_engage_api_key' );
            $_aitrillion_engage_api_password = get_option( '_aitrillion_engage_api_password' );

            $url = AITRILLION_ENGAGE_END_POINT.'customers/update';

            $response = wp_remote_post( $url, array(
                'headers' => array(
                            'Authorization' => 'Basic ' . base64_encode( $_aitrillion_engage_api_key.':'.$_aitrillion_engage_api_password )
                        ),
                'body' => $json_payload
            ));

            $r = json_decode($response['body']);

            // if sync failed, store failed user into separate variable
            if(!isset($r->status) && $r->status != 'success'){
                $failed_sync_users_data[$user_id][] = array('user_id' => $user_id, 'error' => $r->message, 'date' => gmdate('Y-m-d H:i:s'));
                $failed_sync_users[] = $user_id;
            }else{
                // flag this user as synced successfully
                update_user_meta($user_id, '_aitrillion_engage_user_sync', 'true');
                $synced_users[] = $user_id;
            }

            aitrillion_engage_api_log('API Response for user id: '.$user_id.PHP_EOL.print_r($r, true));
        }
    }

    if(!empty($failed_sync_users)){
        
        // if there are failed sync users, add them into next cron queue
        update_option('_aitrillion_engage_updated_users', $failed_sync_users, false);
        update_option('_aitrillion_engage_failed_sync_users', $failed_sync_users_data, false);

    }else{

        // all user synced successfully, clear queue
        delete_option('_aitrillion_engage_updated_users');    
    }
}

/**
* sync deleted customers
*
*/
function aitrillion_engage_sync_deleted_customers(){

    // get ids of deleted customers since last cron call
    $deleted_users = get_option( '_aitrillion_engage_deleted_users' );

    aitrillion_engage_api_log('deleted users sync log: '.print_r($deleted_users, true).PHP_EOL);

    $failed_sync_users = array();

    if(!empty($deleted_users)){

        // remove duplicate ids
        $deleted_users = array_unique($deleted_users);

        foreach($deleted_users as $k => $user_id){

            $json_payload = wp_json_encode(array('id' => $user_id));

            $_aitrillion_engage_api_key = get_option( '_aitrillion_engage_api_key' );
            $_aitrillion_engage_api_password = get_option( '_aitrillion_engage_api_password' );

            $bearer = base64_encode( $_aitrillion_engage_api_key.':'.$_aitrillion_engage_api_password );

            $_aitrillion_engage_api_key = get_option( '_aitrillion_engage_api_key' );
            $_aitrillion_engage_api_password = get_option( '_aitrillion_engage_api_password' );

            $url = AITRILLION_ENGAGE_END_POINT.'customers/delete';

            $response = wp_remote_post( $url, array(
                'headers' => array(
                            'Authorization' => 'Basic ' . base64_encode( $_aitrillion_engage_api_key.':'.$_aitrillion_engage_api_password )
                        ),
                'body' => $json_payload
            ));

            $r = json_decode($response['body']);

            // if sync failed, store failed user into separate variable
            if(!isset($r->status) && $r->status != 'success'){
                $failed_sync_users_data[$user_id][] = array('user_id' => $user_id, 'error' => $r->message, 'date' => gmdate('Y-m-d H:i:s'));
                $failed_sync_users[] = $user_id;
            }

            aitrillion_engage_api_log('Delete customer API Response for user id: '.$user_id.PHP_EOL.print_r($r, true));

        }

        if(!empty($failed_sync_users)){
        
            // if there are failed sync users, add them into next cron queue
            update_option('_aitrillion_engage_deleted_users', $failed_sync_users, false);
            update_option('_aitrillion_engage_failed_sync_users', $failed_sync_users_data, false);

        }else{

            // all user synced successfully, clear queue
            delete_option('_aitrillion_engage_deleted_users');    
        }
    }

}

?>