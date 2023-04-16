<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

require_once 'libraries/2FA/vendor/autoload.php';

class Users_LoginOtp_Action extends Vtiger_Action_Controller {

	function loginRequired() {
		return false;
	}

	function checkPermission(Vtiger_Request $request) {
		return true;
	} 

	function process(Vtiger_Request $request) {
		$username = $request->get('username');
		$password = $request->getRaw('password');
		$otpCode = $request->get('otp_code');

		$user = CRMEntity::getInstance('Users');
		$user->column_fields['user_name'] = $username;

        if ($user->doLogin($password)) {
            
            $db = PearDatabase::getInstance();
            
            $result = $db->pquery("SELECT secret_key FROM vtiger_users 
            WHERE user_name = ?",array($username));
            
            $secretKey = $db->query_result_rowdata($result,0)['secret_key'];

            $secret = (!$secretKey || $secretKey == '') ? $request->get('secret') : $secretKey;
            
            $g = new Sonata\GoogleAuthenticator\GoogleAuthenticator();

            if ($g->checkCode($secret, $otpCode)) {
                
                if(!$secretKey || $secretKey == '') {
                    $db->pquery('UPDATE vtiger_users SET secret_key = ? 
                    WHERE user_name=?', array($secret, $username));
                }

                session_regenerate_id(true); // to overcome session id reuse.

                $userid = $user->retrieve_user_id($username);
                Vtiger_Session::set('AUTHUSERID', $userid);

                // For Backward compatability
                // TODO Remove when switch-to-old look is not needed
                $_SESSION['authenticated_user_id'] = $userid;
                $_SESSION['app_unique_key'] = vglobal('application_unique_key');
                $_SESSION['authenticated_user_language'] = vglobal('default_language');

                //Enabled session variable for KCFINDER
                $_SESSION['KCFINDER'] = array();
                $_SESSION['KCFINDER']['disabled'] = false;
                $_SESSION['KCFINDER']['uploadURL'] = "test/upload";
                $_SESSION['KCFINDER']['uploadDir'] = "../test/upload";
                $deniedExts = implode(" ", vglobal('upload_badext'));
                $_SESSION['KCFINDER']['deniedExts'] = $deniedExts;
                // End

                //Track the login History
                $moduleModel = Users_Module_Model::getInstance('Users');
                $moduleModel->saveLoginHistory($user->column_fields['user_name']);
                //End

                if(isset($_SESSION['return_params'])){
                    $return_params = $_SESSION['return_params'];
                }

                echo json_encode(['status' => 'success', 
                'url' => 'index.php?module=Users&parent=Settings&view=SystemSetup']);
            
            } else {
                echo json_encode(['status' => 'fail']);
            }

        } else {
            echo json_encode(['status' => 'fail', 'url' => 'index.php?module=Users&parent=Settings&view=Login&error=login']);
        }
        exit;
	}

}
