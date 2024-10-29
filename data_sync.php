<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// 
// create action on user register, edit and delete
add_action( 'user_register', 'aitrillion_engage_sync_user_register', 10, 1 );
add_action( 'profile_update', 'aitrillion_engage_sync_user_update', 10, 2 );
add_action( 'delete_user', 'aitrillion_engage_sync_user_delete' );

/**
* flag new user for sync and add into cron queue
*
* @param int $user_id user id
*
* @return false
*/
function aitrillion_engage_sync_user_register( $user_id ) {

    // flag this user as not synced
    update_user_meta($user_id, '_aitrillion_engage_user_sync', 'false');

    // get new registered unsynced users id
    $new_users = get_option( '_aitrillion_engage_created_users' );

    // add this user into new user sync queue
    $new_users[] = $user_id;
    update_option('_aitrillion_engage_created_users', $new_users, false);

    return false;
}

/**
* flag modified user for sync and add into cron queue
*
* @param int $user_id user id
* 
* @param array $old_user_data user data before modify
*
* @return false
*/
function aitrillion_engage_sync_user_update( $user_id, $old_user_data ) {

    // flag this user as not synced
    update_user_meta($user_id, '_aitrillion_engage_user_sync', 'false');

    // get modified unsynced users id
    $updated_users = get_option( '_aitrillion_engage_updated_users' );

    // add this user into updated user sync queue
    $updated_users[] = $user_id;
    update_option('_aitrillion_engage_updated_users', $updated_users, false);

    return false;
    
}

/**
* flag deleted user for sync and add into cron queue
*
* @param int $user_id user id
*
* @return false
*/
function aitrillion_engage_sync_user_delete( $user_id ) {

    // get deleted unsynced users id
    $deleted_users = get_option( '_aitrillion_engage_deleted_users' );

    // add this user into deleted user sync queue
    $deleted_users[] = $user_id;
    update_option('_aitrillion_engage_deleted_users', $deleted_users, false);

    aitrillion_engage_api_log('user deleted: '.$user_id.PHP_EOL);

    return false;
}

?>