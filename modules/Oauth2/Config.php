<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

class Oauth2_Config implements ArrayAccess {

    protected $data;

    protected function __construct($data) {
        $this->data = $data;
    }

    public function offsetExists($key) {
        return isset($this->data[$key]);
    }

    public function offsetGet($key) {
        return isset($this->data[$key])? $this->data[$key] : null;
    }

    public function offsetSet($key, $value) {}
    public function offsetUnset($key) {}

    protected function initProviderConfig($config, $forprovider) {
        global $site_URL;
        
        switch (strtoupper($forprovider)) {
            case "GOOGLE":
                if (!isset($config["urlAuthorize"])) {
                    $config["urlAuthorize"] = 'https://accounts.google.com/o/oauth2/v2/auth';
                }
                if (!isset($config["urlAccessToken"])) {
                    $config["urlAccessToken"] = "https://oauth2.googleapis.com/token";
                }
                if (!isset($config["urlResourceOwnerDetails"])) {
                    $config["urlResourceOwnerDetails"] = "https://openidconnect.googleapis.com/v1/userinfo";
                }
                if (!isset($config["scopes"])) {
                    $config["scopes"] = 'openid email profile https://mail.google.com/'; /* space separated */
                }
                break;
	    case "OUTLOOK" :
                if (!isset($config["urlAuthorize"])) {
                    $config["urlAuthorize"] = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
                }
                if (!isset($config["urlAccessToken"])) {
                    $config["urlAccessToken"] = "https://login.microsoftonline.com/common/oauth2/v2.0/token";
                }
                if (!isset($config["urlResourceOwnerDetails"])) {
                    $config["urlResourceOwnerDetails"] = "https://graph.microsoft.com/v1.0/me";
                }
                if (!isset($config["scopes"])) {
                    $config["scopes"] = 'user.Read User-Mail.ReadWrite.All Mail.Read Mail.Read.Shared Mail.ReadBasic Mail.ReadBasic.Shared Mail.ReadWrite Mail.ReadWrite.Shared Mail.Send Mail.Send.Shared MailboxFolder.Read MailboxFolder.ReadWrite User.Read.All offline_access'; /* space separated */
                }
                break;	
	    case "OFFICE365" :
                if (!isset($config["urlAuthorize"])) {
                    $config["urlAuthorize"] = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
                }
                if (!isset($config["urlAccessToken"])) {
                    $config["urlAccessToken"] = "https://login.microsoftonline.com/common/oauth2/v2.0/token";
                }
                if (!isset($config["urlResourceOwnerDetails"])) {
                    $config["urlResourceOwnerDetails"] = "https://outlook.office.com/api/v2.0/me";
                }
                if (!isset($config["scopes"])) {
                    $config["scopes"] = 'openid https://outlook.office.com/SMTP.Send offline_access'; /* space separated */
                }
                break;	
        }

        if (!isset($config["clientId"]) && isset($config["client_id"])) {
            $config["clientId"] = $config["client_id"];
        }
        if (!isset($config["clientSecret"]) && isset($config["client_secret"])) {
            $config["clientSecret"] = $config["client_secret"];
        }
	if (!isset($config["redirectUri"])) {
            global $site_URL;
            $config["redirectUri"] = 'https://oauth.360vew.com'; //trim($site_URL, '/') . '/oauth2callback/index.php';
	}

        return $config;
    }

    public function getProviderConfig($provider) {
        return isset($this->data[$provider]) ? $this->initProviderConfig($this->data[$provider], $provider) : null;
    }

    protected static $singleton;

    public static function loadConfig($data) {
        if (!static::$singleton) {
            static::$singleton = new static($data);
        }
        return static::$singleton;
    }
}
