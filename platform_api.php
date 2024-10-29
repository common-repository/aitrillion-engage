<?php 

    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

    // create filter for authentication check on each API call
    add_filter( 'rest_authentication_errors', 'aitrillion_engage_auth_check', 99 );

    // crete API end point
    add_action('rest_api_init', function () {

        // register rest API end point and callback functions
        register_rest_route( 'aitrillion-engaged/v1', 'getshopinfo',array(
                    'methods'  => 'GET',
                    'callback' => 'aitrillion_engage_getStoreDetail',
                    'permission_callback' => '__return_true'
        ));

        register_rest_route( 'aitrillion-engaged/v1', 'getcustomers',array(
                    'methods'  => 'GET',
                    'callback' => 'aitrillion_engage_getCustomers',
                    'permission_callback' => '__return_true'
        ));

        register_rest_route( 'aitrillion-engaged/v1', 'updatescriptversion',array(
                    'methods'  => 'GET',
                    'callback' => 'aitrillion_engage_updateScriptVersion',
                    'permission_callback' => '__return_true'
        ));
        
    });


/* 
* Check header authentication
*/

function aitrillion_engage_auth_check(){
    
    $request_user = '';
    $request_pw = '';

    $routes = ltrim( $GLOBALS['wp']->query_vars['rest_route'], '/' );
    if(strpos($routes, "aitrillion/v1") === false){ // If request not for Aitrillion plugin then just return true.
        return true;
    }
    // get header auth username and password
    if(isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])){
        $request_user = sanitize_text_field($_SERVER["PHP_AUTH_USER"]);
        $request_pw = sanitize_text_field($_SERVER["PHP_AUTH_PW"]);
    }

    // get aitrillion key and password from store settings
    $api_key = sanitize_text_field(get_option('_aitrillion_engage_api_key'));
    $api_pw = sanitize_text_field(get_option('_aitrillion_engage_api_password'));

    if($api_key && $api_pw){

        $url = AITRILLION_ENGAGE_END_POINT.'validate?shop_name='.AITRILLION_ENGAGE_DOMAIN;

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $api_key.':'.$api_pw )
            )
        ));

        // if error in response, return error message
        if( is_wp_error( $response ) ) {
            
            $error_message = $response->get_error_message();

            $return['result'] = false;
            $return['message'] = $error_message;
            
            echo wp_json_encode($return);
            exit;

        }else{
            $r = json_decode($response['body']);    
        }

        if(isset($r->status) && $r->status != 'sucess'){

            $return['result'] = false;
            $return['message'] = 'Invalid api username or password';
            
            echo wp_json_encode($return);
            exit;
        }

        // if header auth key and store key are not matched, throw error message
        if(($request_user != $api_key) || ($request_pw != $api_pw)){

            $return['result'] = false;
            $return['message'] = 'Invalid api username or password';
            
            echo wp_json_encode($return);
            exit;

        }else{

            // if API key are valid, return success
            return true;
        }
    }else{
        $return['result'] = false;
        $return['message'] = 'API key not defined in AiTrillion settings';
        
        echo wp_json_encode($return);
        exit;
    }
}


/**
* get store detail API function
*
* @param WP_REST_Request $request The log text
*
* @return WP_REST_Response store detail
*/
function aitrillion_engage_getStoreDetail(WP_REST_Request $request){

    $endpoint = $request->get_route();

    $return['shop_name'] = AITRILLION_ENGAGE_DOMAIN;
    $return['shop_type'] = 'wordpress-nonecommerce';
    $return['shop_owner'] = '';

    $super_admins = get_super_admins();

    // if there are more than one super admin, select first super admin as shop owner
    if($super_admins){
        $admin = $super_admins[0];
        $admin_user = get_user_by('login', $admin);

        if($admin_user){
            $return['shop_owner'] = $admin_user->display_name;
        }
    }

    $return['country'] = "";
    $return['city'] = "";
    $return['zip'] = "";
    $return['phone'] = '';
    $return['store_name'] = get_bloginfo('name');
    $return['email'] = get_bloginfo( 'admin_email' );

    $return['shop_currency'] = '';
    $return['money_format'] = '';

    $return['created_at'] = gmdate('Y-m-d H:i:s');

    $response = new WP_REST_Response($return);
    $response->set_status(200);


    $log_message = '------------------------'.gmdate('Y-m-d H:i:s').'----------------------------------'.PHP_EOL;
    $log_message .= 'Get Shop Info '.$endpoint.PHP_EOL.'return: '.print_r($return, true);

    aitrillion_engage_api_log($log_message);

    return $response;
}

/**
* get customers API function
*
* @param WP_REST_Request $request The log text
*
* @return WP_REST_Response store detail
*/
function aitrillion_engage_getCustomers(WP_REST_Request $request){

    // get API params
    $params = $request->get_query_params();

    // set default result type as row, if result type not provided
    if(!isset($params['result_type']) || empty($params['result_type'])){

        $params['result_type'] = 'row';
    }

    $updated_at = array();
    
    // filter result on updated at, if parameter passed
    if(isset($params['updated_at']) && !empty($params['updated_at'])){
        $updated_at = array( 
                            array( 'after' => $params['updated_at'], 'inclusive' => true )  
                        );
    }

    // if result type count, return total customer count
    if($params['result_type'] == 'count'){

        $customer_query = new WP_User_Query(
          array(
             'fields' => 'ID',
             'role' => 'customer',    
             'date_query' => $updated_at,      
          )
        );

        $customers = $customer_query->get_results();

        $return = array();

        $return['result'] = true;
        $return['customers']['count'] = count($customers);


        $log_message = '------------------------'.gmdate('Y-m-d H:i:s').'----------------------------------'.PHP_EOL;
        $log_message .= 'Get Customer API: result_type Count .'.PHP_EOL.'params: '.print_r($params, true).PHP_EOL.'response: '.print_r($return, true);

        aitrillion_engage_api_log($log_message);

        $response = new WP_REST_Response($return);
        $response->set_status(200);

        return $response;

    }

    // if result type row, return total customer data
    if($params['result_type'] == 'row'){

        // define result filter variables
        if(isset($params['page'])  && !empty($params['page'])){
            $paged = $params['page'];
        }else{
            $paged = 1;
        }

        if(isset($params['limit'])  && !empty($params['limit'])){
            $limit = $params['limit'];
        }else{
            $limit = 10;
        }

        if($paged == 1){
            $offset = 0;  
        }else {
            $offset = ($paged-1) * $limit;
        }
        
        $customer_query = new WP_User_Query(
          array(
             'fields' => 'ID',
             'role' => 'customer',
             'paged' => $paged,
             'number' => $limit,
             'offset' => $offset,
             'date_query' => $updated_at, 
          )
        );

        $customers = $customer_query->get_results();

        if(count($customers) > 0){

            $return = array();

            foreach ( $customers as $customer_id ) {

                // get customer data from common function
                $c = aitrilltion_engage_get_customer( $customer_id );

                $return['customers'][] = $c;

                update_user_meta($customer_id, '_aitrillion_engage_user_sync', 'true');
                update_user_meta($customer_id, '_aitrillion_engage_sync_date', gmdate('Y-m-d H:i:s'));
            }

            $return['result'] = true;

            $log_message = '------------------------'.gmdate('Y-m-d H:i:s').'----------------------------------'.PHP_EOL;
            $log_message .= 'Get Customer API: result_type row .'.PHP_EOL.'params: '.print_r($params, true).PHP_EOL.'response: '.print_r($return, true);

            aitrillion_engage_api_log($log_message);

            $response = new WP_REST_Response($return);
            $response->set_status(200);

            return $response;

        }else{
            $return = array();

            $return['status'] = false;
            $return['msg'] = 'No Customer found';

            $response = new WP_REST_Response($return);
            $response->set_status(200);

            return $response;
        }

       
    }
   
}

/**
* update script version API function
*
*/
function aitrillion_engage_updateScriptVersion(){

    // get current script verstion
    $script_version = get_option('_aitrillion_engage_script_version');

    // increment in previous version if available or set as 1
    if(empty($script_version)){
        $script_version = 1;
    }else{
        $script_version++;    
    }

    update_option('_aitrillion_engage_script_version', $script_version, false);

    $script_version = get_option('_aitrillion_engage_script_version');

    $return['result'] = false;
    $return['script_version'] = $script_version;


    $log_message = 'Script version updated '.$script_version.PHP_EOL;

    aitrillion_engage_api_log($log_message);

    echo wp_json_encode($return);
    exit;

}

?>