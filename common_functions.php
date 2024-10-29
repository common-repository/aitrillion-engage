<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
* write messages into log file
*
* @param string $message The log text
*
* @return null
*/
function aitrillion_engage_api_log($message = ''){
    $log = '------------'.gmdate('Y-m-d H:i:s').'---------------'.PHP_EOL;
    $log .= $message;
    $wpDir = wp_upload_dir();
    file_put_contents($wpDir["basedir"].'/aitrillion-engage-log.txt', $log.PHP_EOL, FILE_APPEND);
}


/**
* prepare wordpress user detail by user id
*
* @param int $customer_id customer id
*
* @return array customer detail
*/
function aitrilltion_engage_get_customer( $user_id ) {

        // initialize customer object from wordpress customer class
        $customer = get_userdata( $user_id );

        $c = array();

        $c['id'] = $user_id;
        $c['first_name'] = $customer->first_name;
        $c['last_name'] = $customer->last_name;
        $c['email'] = $customer->user_email;
        $c['verified_email'] = true;
        $c['phone'] = get_user_meta($user_id,'phone_number',true);
        
        $date_created = gmdate('Y-m-d H:i:s', strtotime($customer->user_registered));
        
        $c['created_at'] = $date_created;
        $c['accepts_marketing'] = false;

        // if no customer edit date available, assign created date as updated date
        $modified_date = get_user_meta( $user_id, 'modified_date', true ); 

        if(!empty($modified_date)){
            $c['updated_at'] = gmdate('Y-m-d H:i:s', strtotime($modified_date));
        }else{
            $c['updated_at'] = $date_created;
        }

        $c['addresses'] = array();
        $c['type'] = null;

        return $c;
}


?>