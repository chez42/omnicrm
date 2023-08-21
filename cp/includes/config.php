<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

ini_set("display_errors", 1);

session_start();

include_once('includes/functions.php');

include_once('includes/function.php');

if(!$_SESSION['api_url']){
    
     $master_api_url = 'https://hq.360vew.com';
    
     $master_api_username = 'admin';
     $master_api_accesskey = 'rV8daZto5LQj779';
     
     $master_ws_url =  $master_api_url . '/webservice.php';
     
     $loginObj = login($master_ws_url, $master_api_username, $master_api_accesskey);
     
     $session_id = $loginObj->sessionName;
     
     $params = array();
     
     $request_URL = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on')? 'https': 'http')."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
     $request_URL = str_replace(array('http://', 'https://', "www.", '/login.php', '/index.php'), '', $request_URL);
     $params['portalurl'] = rtrim($request_URL, " /");
     
     $postParams = array(
         'operation'=>'portalconfiguration',
         'sessionName'=>$session_id,
         'element'=>json_encode($params)
     );
     
     $response = postHttpRequest($master_ws_url, $postParams);
     
     $response = json_decode($response,true);
     
     $results = $response['result'];
     
     if(!empty($results)){
         $_SESSION['api_url'] = $results['url'];
         $_SESSION['portal_user'] = $results['user'];
         $_SESSION['portal_logo'] = $results['image'];
         $_SESSION['portal_name'] = $results['name'];
         $_SESSION['portal_accesskey'] = $results['accesskey'];
         $_SESSION['portal_main_title'] = $results['portal_main_title'];
         $_SESSION['portal_subtitle'] = $results['portal_subtitle'];
     }
     
}

$api_url = $_SESSION['api_url'];
 
$api_username = $_SESSION['portal_user'];
 
$api_accesskey = $_SESSION['portal_accesskey'];
 
$websocketUrl = '';

if($_SESSION['portal_logo'])
    $GLOBALS['portal_logo'] = $_SESSION['portal_logo'];
else 
    $GLOBALS['portal_logo'] = 'images/logo1.png';

if($_SESSION['portal_name'])
    $GLOBALS['portal_title'] = $_SESSION['portal_name'];
else
    $GLOBALS['portal_title'] = 'OMNI Client Portal';

if($_SESSION['portal_main_title'])
    $GLOBALS['portal_main_title'] = $_SESSION['portal_main_title'];
else 
    $GLOBALS['portal_main_title'] = 'OMNI Client Information Center';

if($_SESSION['portal_subtitle'])
    $GLOBALS['portal_subtitle'] = $_SESSION['portal_subtitle'];
else 
    $GLOBALS['portal_subtitle'] = 'Empowering our Advisors to focus on their clients.';
    
if(isset($_SESSION['ID']) && $_SESSION['ID'] != ''){
    
    if($_SESSION['portal_logo'] != '')
        $GLOBALS['portal_logo'] = $_SESSION['portal_logo'];
	
	if($_SESSION['favicon'])
		$GLOBALS['favicon'] = $_SESSION['favicon'];
	else
		$GLOBALS['favicon'] = 'images/favicon.ico';

	$GLOBALS['portal_profile_image'] = $_SESSION['portal_profile_image'];
    
    $GLOBALS['user_basic_details'] = $_SESSION['data']['basic_details'];
    
    foreach($GLOBALS['user_basic_details']['allowed_modules'] as $allowedModule){
        $modules[] = array('modules'=>$allowedModule['module'],'label'=>$allowedModule['module_label']);
        $_SESSION[$allowedModule['module']] = $allowedModule['module_label'];
    }
	
    $avmod = array();
    
    if(!empty($modules)){
        $avmod = array_values($modules);
        $home[] = array('modules'=>"Home",'label'=>"Home");
        $avmod = array_merge($home,$avmod);
    }
    
    $GLOBALS['avmod'] = $avmod;
    
    $GLOBALS['hiddenmodules'] = array();
	
	$GLOBALS['profilefield'] = $_SESSION['profile_fields'];
}
