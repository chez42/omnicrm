<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

vimport('~~/vtlib/Vtiger/Net/Client.php');
class Users_Login_View extends Vtiger_View_Controller {

	function loginRequired() {
		return false;
	}
	
	function checkPermission(Vtiger_Request $request) {
		return true;
	}
	
	function preProcess(Vtiger_Request $request, $display = true) {
		$viewer = $this->getViewer($request);
		$viewer->assign('PAGETITLE', $this->getPageTitle($request));
		$viewer->assign('SCRIPTS', $this->getHeaderScripts($request));
		$viewer->assign('STYLES', $this->getHeaderCss($request));
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('VIEW', $request->get('view'));
		$viewer->assign('LANGUAGE_STRINGS', array());
		if ($display) {
			$this->preProcessDisplay($request);
		}
	}

// 	public function preProcessTplName(Vtiger_Request $request) {
// 	    return 'CustomHeader.tpl';
// 	}
	
	function process (Vtiger_Request $request) {
		$finalJsonData = array();

		$modelInstance = Settings_ExtensionStore_Extension_Model::getInstance();
		$news = array(); //$modelInstance->getNews();

		if ($news && $news['result']) {
			$jsonData = $news['result'];
			$oldTextLength = vglobal('listview_max_textlength');
			foreach ($jsonData as $blockData) {
// 				if ($blockData['type'] === 'feature') {
// 					$blockData['heading'] = "What's new in Vtiger Cloud";
// 				} else if ($blockData['type'] === 'news') {
					$blockData['heading'] = "Latest News";
// 					$blockData['image'] = '';
// 				}

				vglobal('listview_max_textlength', 80);
				$blockData['displayTitle'] = textlength_check($blockData['title']);

				vglobal('listview_max_textlength', 200);
				$blockData['displaySummary'] = textlength_check($blockData['summary']);
				//$finalJsonData[$blockData['type']][] = $blockData;
				$finalJsonData['news'][] = $blockData;
			}
			vglobal('listview_max_textlength', $oldTextLength);
		}

		$viewer = $this->getViewer($request);
		$viewer->assign('DATA_COUNT', count($jsonData));
		$viewer->assign('JSON_DATA', $finalJsonData);

		$mailStatus = $request->get('mailStatus');
		$error = $request->get('error');
		$message = '';
		if ($error) {
			switch ($error) {
				case 'login'		:	$message = 'Invalid credentials';						break;
				case 'fpError'		:	$message = 'Invalid Username or Email address';			break;
				case 'statusError'	:	$message = 'Outgoing mail server was not configured';	break;
			}
		} else if ($mailStatus) {
			$message = 'Mail has been sent to your inbox, please check your e-mail';
		}

		$auto_u = $request->get('auto_u');
		$auto_p = $request->get('auto_p');

		$viewer->assign('AUTO_U', $auto_u);
        $viewer->assign('AUTO_P', $auto_p);
		$viewer->assign('ERROR', $error);
		$viewer->assign('MESSAGE', $message);
		$viewer->assign('MAIL_STATUS', $mailStatus);
		
		global $site_URL;
		
        $host_parts = explode(".", $_SERVER['HTTP_HOST']);
        
        if($host_parts[0] == 'crm4'){
            
            global $adb, $site_URL;
            
            $loginQuery = $adb->pquery('SELECT * FROM vtiger_login_page_settings');
            
            if($adb->num_rows($loginQuery)){
                
                $logo = $site_URL.'/'.$adb->query_result($loginQuery, 0, 'login_logo');
                $background = $site_URL.'/'.$adb->query_result($loginQuery, 0, 'login_background');
                
                $mime = vtlib_mime_content_type($adb->query_result($loginQuery, 0, 'login_background'));
                if(strstr($mime, "video/")){
                    $bgtype = 'video';
                }else if(strstr($mime, "image/")){
                    $bgtype = 'image';
                }
                
                $copyright = $adb->query_result($loginQuery, 0, 'copyright_text');
                $facebook = $adb->query_result($loginQuery, 0, 'facebook_link');
                $twitter = $adb->query_result($loginQuery, 0, 'twitter_link');
                $linkedin = $adb->query_result($loginQuery, 0, 'linkedin_link');
                $youtube = $adb->query_result($loginQuery, 0, 'youtube_link');
                $instagram = $adb->query_result($loginQuery, 0, 'instagram_link');
                
            }
            $data = array();
            
            $data['copyright'] = $copyright ? $copyright : '2004-'.date("Y") . ' Omniscient CRM';
            $data['facebook'] = $facebook ? $facebook : 'https://facebook.com/omnisrv/';
            $data['twitter'] = $twitter ? $twitter : 'https://twitter.com/omnisrv';
            $data['linkedin'] = $linkedin ? $linkedin : 'https://linkedin.com/company/omnisrv';
            $data['youtube'] = $youtube ? $youtube : 'https://www.youtube.com/channel/UC53BQe0wPV9_TYohwQl2E0g';
            $data['instagram'] = $instagram ? $instagram : 'https://instagram.com/omnisrv';
            $data['bgtype'] = $bgtype ? $bgtype : '';
            $data['logo'] = $logo ? $logo : '';
            $data['background'] = $background ? $background : '';
            
        }else{
            
            $httpc = new Vtiger_Net_Client('https://hq.360vew.com/webservice.php');
            $element = array();
            $element['domain'] = rtrim($site_URL,'/');
            
            $params = array(
                "operation"=>'get_instance_details',
                "element" => json_encode($element)
            );
            
            $response = $httpc->doPost($params);
            
            $response = json_decode($response, true);
            
            if(!empty($response['result'])){
                $data = $response['result'];
            }
            
        }
		$viewer->assign('COPYRIGHT', $data['copyright']);
		$viewer->assign('FACEBOOK_LINK', $data['facebook']);
		$viewer->assign('TWITTER_LINK', $data['twitter']);
		$viewer->assign('LINKEDIN_LINK', $data['linkedin']);
		$viewer->assign('YOUTUBE_LINK', $data['youtube']);
		$viewer->assign('INSTA_LINK', $data['instagram']);
		
		$viewer->assign('BGTYPE', $data['bgtype']);
		$viewer->assign('LOGO', $data['logo']);
		$viewer->assign('BACKGROUND', $data['background']);
		//}
		
		$companyDetails = Settings_Vtiger_CompanyDetails_Model::getInstance();
		
		if($companyDetails->get('office_login')){
		    
    		$clientId = MailManager_Office365Config_Connector::$clientId;
    		$redriectUri = MailManager_Office365Config_Connector::$redirect_url;
    		
    		$auth_url = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?response_type=code&redirect_uri=".urlencode($redriectUri)."&client_id=".urlencode($clientId);
    		$auth_url .= '&state=' . base64_encode(implode('||', array($site_URL, '', "Office365Login", "ValidateLogin")));
    		$auth_url .= '&scope=' . urlencode('User.Read offline_access');
    
    		$viewer->assign('AUTH_URL', $auth_url);
    		
    		$moduleActive = '';
    		
    		if($clientId)
    		    $moduleActive = true;
    		
    	    $viewer->assign('OFFICE_ACTIVE', $moduleActive);
    	    
		}
	    
		if($companyDetails->get('google_login')){
    	    $googleClientId = Google_Config_Connector::$clientId;
    	    
    	    $googleRedirectUri = Google_Config_Connector::$redirect_url;
    	    
    	    $googleauth_url = "https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=offline";
    	    $googleauth_url .= "&client_id=".urlencode($googleClientId);
    	    $googleauth_url .= "&redirect_uri=".urlencode($googleRedirectUri);
    	    $googleauth_url .= '&state=' . base64_encode(implode('||', array($site_URL, '', "GoogleLogin", "ValidateLogin")));
    	    $googleauth_url .= '&scope=' . urlencode('https://www.googleapis.com/auth/userinfo.email');
    	    $googleauth_url .= '&prompt='. urlencode('select_account consent');
    	    
    	    $viewer->assign('GOOGLE_AUTH_URL', $googleauth_url);
    	    
    	    $googleModuleActive = '';
    	    if($googleClientId)
    	        $googleModuleActive = true;
    	        
            $viewer->assign('GOOGLE_ACTIVE', $googleModuleActive);
		}
		
		$viewer->view('Login.tpl', 'Users');
	}

	function postProcess(Vtiger_Request $request) {
		$moduleName = $request->getModule();
		$viewer = $this->getViewer($request);
		//$viewer->view('Footer.tpl', $moduleName);
	}

	function getPageTitle(Vtiger_Request $request) {
		$companyDetails = Vtiger_CompanyDetails_Model::getInstanceById();
		return $companyDetails->get('organizationname');
	}

	function getHeaderScripts(Vtiger_Request $request){
		$headerScriptInstances = parent::getHeaderScripts($request);

		$jsFileNames = array(
							'~libraries/jquery/boxslider/jquery.bxslider.min.js',
							'modules.Vtiger.resources.List',
							'modules.Vtiger.resources.Popup',
                            '~layouts/v7/modules/Vtiger/resources/AutoLogin.js',
							);
		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
		$headerScriptInstances = array_merge($jsScriptInstances,$headerScriptInstances);
		return $headerScriptInstances;
	}
}
