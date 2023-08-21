<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

require_once 'modules/Emails/mail.php';

class HelpDeskHandler extends VTEventHandler {

	function handleEvent($eventName, $entityData) {
		global $log, $adb;

		if($eventName == 'vtiger.entity.aftersave.final') {
			$moduleName = $entityData->getModuleName();
			if ($moduleName == 'HelpDesk') {
				$ticketId = $entityData->getId();
				$adb->pquery('UPDATE vtiger_ticketcf SET from_portal=0 WHERE ticketid=?', array($ticketId));
			}
		}
	}
}

function HelpDesk_nofifyOnPortalTicketCreation($entityData) {
	global $HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID;
	$adb = PearDatabase::getInstance();
	$moduleName = $entityData->getModuleName();
	$wsId = $entityData->getId();
	$parts = explode('x', $wsId);
	$entityId = $parts[1];

	$ownerIdInfo = getRecordOwnerId($entityId);
	if(!empty($ownerIdInfo['Users'])) {
		$ownerId = $ownerIdInfo['Users'];
		$to_email = getUserEmailId('id',$ownerId);
	}
	if(!empty($ownerIdInfo['Groups'])) {
		$ownerId = $ownerIdInfo['Groups'];
		$to_email = implode(',', getDefaultAssigneeEmailIds($ownerId));
	}
	$wsParentId = $entityData->get('contact_id');
	$parentIdParts = explode('x', $wsParentId);
	$parentId = $parentIdParts[1];

	$subject = '[From Portal] ' .$entityData->get('ticket_no'). " [ Ticket Id : $entityId ] " .$entityData->get('ticket_title');
	$contents = ' Ticket No : '.$entityData->get('ticket_no'). '<br> Ticket ID : '.$entityId.'<br> Ticket Title : '.
							$entityData->get('ticket_title').'<br><br>'.$entityData->get('description');

	//get the contact email id who creates the ticket from portal and use this email as from email id in email
	$result = $adb->pquery("SELECT email, concat (firstname,' ',lastname) as name FROM vtiger_contactdetails WHERE contactid=?", array($parentId));
	$contact_email = $adb->query_result($result,0,'email');
	$name = $adb->query_result($result, 0, 'name');
	$from_email = $contact_email;

	//send mail to assigned to user
	$mail_status = send_mail('HelpDesk',$to_email,$name,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$contents);

	//send mail to the customer(contact who creates the ticket from portal)
	$mail_status = send_mail('Contacts',$contact_email,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$contents);
}

function HelpDesk_notifyOnPortalTicketComment($entityData) {
	$adb = PearDatabase::getInstance();
	$moduleName = $entityData->getModuleName();
	$wsId = $entityData->getId();
	$parts = explode('x', $wsId);
	$entityId = $parts[1];

	$ownerIdInfo = getRecordOwnerId($entityId);

	if(!empty($ownerIdInfo['Users'])) {
		$ownerId = $ownerIdInfo['Users'];
		$ownerName = getOwnerName($ownerId);
		$to_email = getUserEmailId('id',$ownerId);
	}
	if(!empty($ownerIdInfo['Groups'])) {
		$ownerId = $ownerIdInfo['Groups'];
		$groupInfo = getGroupName($ownerId);
		$ownerName = $groupInfo[0];
		$to_email = implode(',', getDefaultAssigneeEmailIds($ownerId));
	}
	$wsParentId = $entityData->get('contact_id');
	$parentIdParts = explode('x', $wsParentId);
	$parentId = $parentIdParts[1];

	$entityDelta = new VTEntityDelta();
	$oldComments = $entityDelta->getOldValue($entityData->getModuleName(), $entityId, 'comments');
	$newComments = $entityDelta->getCurrentValue($entityData->getModuleName(), $entityId, 'comments');
	$commentDiff = str_replace($oldComments, '', $newComments);
	$latestComment = strip_tags($commentDiff);

	//send mail to the assigned to user when customer add comment
	$subject = getTranslatedString('LBL_RESPONSE_TO_TICKET_NUMBER', $moduleName). ' : ' .$entityData->get('ticket_no'). ' ' .getTranslatedString('LBL_CUSTOMER_PORTAL', $moduleName);
	$contents = getTranslatedString('Dear', $moduleName)." ".$ownerName.","."<br><br>"
						.getTranslatedString('LBL_CUSTOMER_COMMENTS', $moduleName)."<br><br>
						<b>".$latestComment."</b><br><br>"
						.getTranslatedString('LBL_RESPOND', $moduleName)."<br><br>"
						.getTranslatedString('LBL_REGARDS', $moduleName)."<br>"
						.getTranslatedString('LBL_SUPPORT_ADMIN', $moduleName);

	//get the contact email id who creates the ticket from portal and use this email as from email id in email
	$result = $adb->pquery("SELECT lastname, firstname, email FROM vtiger_contactdetails WHERE contactid=?", array($parentId));
	$customername = $adb->query_result($result,0,'firstname').' '.$adb->query_result($result,0,'lastname');
	$customername = decode_html($customername);//Fix to display the original UTF-8 characters in sendername instead of ascii characters
	$from_email = $adb->query_result($result,0,'email');

	send_mail('HelpDesk',$to_email,'',$from_email,$subject,$contents);
}

      
function HelpDesk_notifyAdvisorOnTicketChange($entityData){
    
	return true;
	
   /*
	require_once('include/utils/GetGroupUsers.php');
    require_once('include/utils/GetUserGroups.php');
    
    global $HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID;
    $adb = PearDatabase::getInstance();
    $moduleName = $entityData->getModuleName();
    $wsId = $entityData->getId();
    $parts = explode('x', $wsId);
    $entityId = $parts[1];

    $isNew = $entityData->isNew();

    if(!$isNew) {
            $reply = 'Re : ';
    } else {
            $reply = '';
    }

    $subject = $entityData->get('ticket_no') . " [ Ticket Id : $entityId ] (Advisor Notification) " . $reply . $entityData->get('ticket_title');
    $emailoptout = 0;
    $wsContactId = $entityData->get('contact_id');
    $contactId = explode('x', $wsContactId);
    $wsAccountId = $entityData->get('parent_id');
    $accountId = explode('x', $wsAccountId);

    $email_body = HelpDesk::getTicketEmailContents($entityData);
    $record = HelpDesk_Record_Model::getInstanceById($entityId);
    
    $query = "SELECT e.crmid, tt.parent_id, tt.ticketid, e.smownerid, u.first_name, u.last_name, u.id
            FROM vtiger_troubletickets tt
            LEFT JOIN vtiger_crmentity e ON (e.crmid = tt.parent_id AND tt.parent_id != 0)
            LEFT JOIN vtiger_users u ON u.id = e.smownerid
            WHERE tt.ticketid = $entityId";
    $result = $adb->pquery($query,null);
    
    $advisor_id = $adb->query_result($result, 0, "id");
    
    $advisor_email = getUserEmail($advisor_id);
    
    $data = $record->getData();
    $owner_type = vtws_getOwnerType($data['assigned_user_id']);
    $skip_email = 0;
    if($owner_type == "Groups")
    {
        $groupUsers = new GetGroupUsers();
        $groupUsers->getAllUsersInGroup($data['assigned_user_id']);
        $usersInGroup = $groupUsers->group_users;
        foreach ($usersInGroup as $user) {
        if($user == $advisor_id){
                $skip_email = 1;
            }
        }
    }

    $viewer = new Vtiger_Viewer();
    $viewer->assign("DATA", $entityData);
    $viewer->assign("ENTITYID", $entityId);
    $viewer->assign("ISNEW", $isNew);
    $subject = $viewer->view("Subject.tpl", "HelpDesk", true);
    if( ($data['assigned_user_id'] != $advisor_id) && ($skip_email != 1) )
        send_mail('HelpDesk',$advisor_email,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$email_body);
    */
}

function HelpDesk_notifyParentOnTicketChange($entityData) {
	global $HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID;
	$adb = PearDatabase::getInstance();
	$moduleName = $entityData->getModuleName();
	$wsId = $entityData->getId();
	$parts = explode('x', $wsId);
	$entityId = $parts[1];

	$isNew = $entityData->isNew();

	if(!$isNew) {
		$reply = 'Re : ';
	} else {
		$reply = '';
	}

	$subject = $entityData->get('ticket_no') . " [ Ticket Id : $entityId ] " . $reply . $entityData->get('ticket_title');
	$emailoptout = 0;
	$wsContactId = $entityData->get('contact_id');
	$contactId = explode('x', $wsContactId);
	$wsAccountId = $entityData->get('parent_id');
	$accountId = explode('x', $wsAccountId);
	//To get the emailoptout vtiger_field value and then decide whether send mail about the tickets or not
	if(!empty($contactId[0])) {
		$result = $adb->pquery('SELECT email, emailoptout, lastname, firstname FROM vtiger_contactdetails WHERE
						contactid=?', array($contactId[1]));
		$emailoptout = $adb->query_result($result,0,'emailoptout');
		$parent_email = $contact_mailid = $adb->query_result($result,0,'email');
		$parentname = $adb->query_result($result,0,'firstname').' '.$adb->query_result($result,0,'firstname');

		//Get the status of the vtiger_portal user. if the customer is active then send the vtiger_portal link in the mail
		if($parent_email != '') {
			$sql = "SELECT * FROM vtiger_portalinfo WHERE user_name=?";
			$isPortalUser = $adb->query_result($adb->pquery($sql, array($contact_mailid)),0,'isactive');
		}
	} elseif(!empty($accountId[0])) {
		$result = $adb->pquery("SELECT accountname, emailoptout, email1 FROM vtiger_account WHERE accountid=?",
									array($accountId[1]));
		$emailoptout = $adb->query_result($result,0,'emailoptout');
		$parent_email = $adb->query_result($result,0,'email1');
		$parentname = $adb->query_result($result,0,'accountname');
	}
	//added condition to check the emailoptout(this is for contacts and vtiger_accounts.)
	if($emailoptout == 0) {
		if($isPortalUser == 1) {
			$email_body = HelpDesk::getTicketEmailContents($entityData);
		} else {
			$email_body = HelpDesk::getTicketEmailContents($entityData);
		}

		if($isNew) {
			send_mail('HelpDesk',$parent_email,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$email_body);
		} else {
			$entityDelta = new VTEntityDelta();
			$statusHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'ticketstatus');
			$solutionHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'solution');
			$descriptionHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'description');

			if(($statusHasChanged && $entityData->get('ticketstatus') == "Closed") || $solutionHasChanged || $descriptionHasChanged) {
				send_mail('HelpDesk',$parent_email,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$email_body);
			}
		}
	}
}

function HelpDesk_notifyOwnerOnTicketChange($entityData) {
	global $HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID;

	$moduleName = $entityData->getModuleName();
	$wsId = $entityData->getId();
	$parts = explode('x', $wsId);
	$entityId = $parts[1];

	$isNew = $entityData->isNew();

	if(!$isNew) {
		$reply = 'Re : ';
	} else {
		$reply = '';
	}

	$subject = getTranslatedString('LBL_TICKET_NUMBER', $moduleName). ' : ' .$entityData->get('ticket_no'). ' ' .$reply.$entityData->get('ticket_title');

	$email_body = HelpDesk::getTicketEmailContents($entityData, true);
	if(PerformancePrefs::getBoolean('NOTIFY_OWNER_EMAILS', true) === true){
		//send mail to the assigned to user and the parent to whom this ticket is assigned
		require_once('modules/Emails/mail.php');
		$wsAssignedUserId = $entityData->get('assigned_user_id');
		$userIdParts = explode('x', $wsAssignedUserId);
		$ownerId = $userIdParts[1];
		$ownerType = vtws_getOwnerType($ownerId);

		if($ownerType == 'Users') {
			$to_email = getUserEmailId('id',$ownerId);
		}
		if($ownerType == 'Groups') {
			$to_email = implode(',', getDefaultAssigneeEmailIds($ownerId));
		}
		if($to_email != '') {
			if($isNew) {
				$mail_status = send_mail('HelpDesk',$to_email,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$email_body);
			} else {
				$entityDelta = new VTEntityDelta();
				$statusHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'ticketstatus');
				$solutionHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'solution');
				$ownerHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'assigned_user_id');
				$descriptionHasChanged = $entityDelta->hasChanged($entityData->getModuleName(), $entityId, 'description');
				if(($statusHasChanged && $entityData->get('ticketstatus') == "Closed") || $solutionHasChanged || $ownerHasChanged || $descriptionHasChanged) {
					$mail_status = send_mail('HelpDesk',$to_email,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID,$subject,$email_body);
				}
			}
			$mail_status_str = $to_email."=".$mail_status."&&&";

		} else {
			$mail_status_str = "'".$to_email."'=0&&&";
		}

		if ($mail_status != '') {
			$mail_error_status = getMailErrorString($mail_status_str);
		}
	}
}

function HelpDesk_notifyTicketComment( $entityData ){
    
    global $site_URL, $adb,$HELPDESK_SUPPORT_NAME,$HELPDESK_SUPPORT_EMAIL_ID;
	
    $currentUserModel = Users_Record_Model::getCurrentUserModel();
    
	$rootDirectory = vglobal('root_directory');
    
    $moduleName = $entityData->getModuleName();
    
	$wsId = $entityData->getId();
    
	$parts = explode('x', $wsId);
    
	$entityId = $parts[1];
    
    $parent = explode('x', $entityData->get('parent_id'));
    
	$parentId = $parent[1];
    
    $cc = '';
	
    if(getSalesEntityType($parentId) == 'Accounts'){
        
		$account = $adb->pquery("SELECT vtiger_account.email1 FROM vtiger_account
        INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_account.accountid
        WHERE vtiger_crmentity.deleted = 0 AND vtiger_account.accountid = ?",array($parentId));
        
		if($adb->num_rows($account)){
            $cc = $adb->query_result($account, 0, 'email1');
        }
		
    }elseif(getSalesEntityType($parentId) == 'Contacts'){
        
		$contact = $adb->pquery("SELECT vtiger_contactdetails.email FROM vtiger_contactdetails
        INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_contactdetails.contactid
        WHERE vtiger_crmentity.deleted = 0 AND vtiger_contactdetails.contactid = ?",array($parentId));
        
		if($adb->num_rows($contact)){
            $cc = $adb->query_result($contact, 0, 'email');
        }
		
    }
    
    $html = '';
    
    $comment = $adb->pquery("SELECT vtiger_modcomments.modcommentsid, vtiger_modcomments.filename, 
    vtiger_modcomments.customer, vtiger_modcomments.commentcontent,vtiger_crmentity.smownerid,
    vtiger_crmentity.createdtime FROM vtiger_modcomments
    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_modcomments.modcommentsid
    WHERE vtiger_crmentity.deleted = 0 AND vtiger_modcomments.related_to = ?
    ORDER BY vtiger_modcomments.modcommentsid DESC",array($entityId));
    
    if($adb->num_rows($comment)){
        
        $documentIds = $adb->query_result($comment, 0, 'filename');
        
        $html = '<div style="padding:10px;line-height:1.5;font-family:\'Lucida Grande\',Verdana,Arial,sans-serif;font-size:12px;color:#444444">
            <div style="color:#ff0000">##- This is a no-reply mailbox and it is not monitored. -##</div>
            <p>Your request (<span dir="ticket_no"><strong>'. $entityData->get('ticket_no') .'</strong></span>) has been updated.<br></p>';
        
        for($c=0;$c<$adb->num_rows($comment);$c++){
            $comData = $adb->query_result_rowdata($comment,$c);
            
            if($comData['customer']){
                $commentedBy = Vtiger_Record_Model::getInstanceById($comData['customer'], 'Contacts');
                $creator = $commentedBy->getName();
            } else {
                $commentedBy = Vtiger_Record_Model::getInstanceById($comData['smownerid'], 'Users');
                $creator = $commentedBy->getName();
            }
            
            $html.='<div style="margin-top:25px">
            	<table width="100%" cellpadding="0" cellspacing="0" border="0">
        	 		<tbody>
            	 		<tr>
            	 			<td width="100%" style="padding:15px 0;border-top:1px dotted #c5c5c5">
            	 			    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed">
            	 			    	<tbody>
            	 			    		<tr>
            	 			    			<td valign="top" style="padding:0 15px 0 15px;width:40px">
            	 			    				<img width="40" height="40" alt="" style="height:auto;line-height:100%;outline:none;text-decoration:none;border-radius:5px"
            	 			    				src="'.$site_URL.'/test/user.png" class="CToWUd">
 			    				            </td>
		                                	<td width="100%" style="padding:0;margin:0" valign="top">
                            		            <p style="font-family:\'Lucida Grande\',\'Lucida Sans Unicode\',\'Lucida Sans\',Verdana,Tahoma,sans-serif;font-size:15px;line-height:18px;margin-bottom:0;margin-top:0;padding:0;color:#1b1d1e">
                                                	<strong>'.$creator.'</strong>
                                                </p>
                                                <p style="font-family:\'Lucida Grande\',\'Lucida Sans Unicode\',\'Lucida Sans\',Verdana,Tahoma,sans-serif;font-size:13px;line-height:25px;margin-bottom:15px;margin-top:0;padding:0;color:#bbbbbb">
                                                	'. date('M d/Y, H:i',strtotime($comData['createdtime'])) .'
                                	            </p>
                                                <div dir="auto" style="color:#2b2e2f;font-family:\'Lucida Sans Unicode\',\'Lucida Grande\',\'Tahoma\',Verdana,sans-serif;font-size:14px;line-height:22px;margin:15px 0">
													'.$comData['commentcontent'].'
												<br></div><p></p>
                                            </td>
                                        </tr>
                            	    </tbody>
                        	    </table>
                	        </td>
        	        	</tr>
    	        	</tbody>
	        	</table>
	        	<p></p>
            </div>';
        }
        $html .='</div>';
    }
    
    $wsAssignedUserId = $entityData->get('assigned_user_id');
    $userIdParts = explode('x', $wsAssignedUserId);
    $ownerId = $userIdParts[1];
    $ownerType = vtws_getOwnerType($ownerId);
    
    if($ownerType == 'Users') {
        $to_email = getUserEmailId('id',$ownerId);
    }
    if($ownerType == 'Groups') {
        $to_email = implode(',', getDefaultAssigneeEmailIds($ownerId));
    }
    
    $subject = getTranslatedString('LBL_TICKET_NUMBER', $moduleName). ' : ' .$entityData->get('ticket_no'). ' ' .$reply.$entityData->get('ticket_title');
    
    $email_body = $html;

    $processedContent = Emails_Mailer_Model::getProcessedContent($email_body);
	
    $mailer = Emails_Mailer_Model::getInstance();
    
	$processedContentWithURLS = decode_html($mailer->convertToValidURL($processedContent));
    
    $mailer->IsHTML(true);
    
    $fromEmail = $HELPDESK_SUPPORT_EMAIL_ID; //getFromEmailAddress();
    
	$replyTo = "no-reply@360vew.com";
    
	$userName = $currentUserModel->getName();
    
    // To eliminate the empty value of an array
    $toEmailInfo = array();
    $emailsInfo = array();
    
    $toEmailInfo = array_map("unserialize", array_unique(array_map("serialize", array_map("array_unique", $toEmailInfo))));
    $toFieldData = array_diff(explode(',', $to_email), $emailsInfo);
    $toEmailsData = array();
    $i = 1;
    foreach ($toFieldData as $value) {
        $toEmailInfo['to'.$i++] = array($value);
    }
    $attachments = getAttachmentDetails($documentIds);
    $status = false;
    
    // Merge Users module merge tags based on current user.
    $mergedDescription = getMergedDescription($processedContentWithURLS, $currentUserModel->getId(), 'Users');
    $mergedSubject = getMergedDescription($subject,$currentUserModel->getId(), 'Users');
    foreach($toEmailInfo as $id => $emails) {
        $inReplyToMessageId = '';
        $generatedMessageId = '';
        $mailer->reinitialize();
        $mailer->ConfigSenderInfo($fromEmail, $userName, $replyTo);
        $old_mod_strings = vglobal('mod_strings');
        
        $description = getMergedDescription($mergedDescription, $id, 'Users');
        $subject = getMergedDescription($mergedSubject, $id, 'Users');;
        
        if (strpos($description, '$logo$')) {
            $description = str_replace('$logo$',"<img src='cid:companyLogo' />", $description);
            $logo = true;
        }
        
        foreach($emails as $email) {
            $mailer->Body = $description;
           
            $mailer->Subject = decode_html(strip_tags($subject));
            
            $plainBody = decode_emptyspace_html($description);
            $plainBody = preg_replace(array("/<p>/i","/<br>/i","/<br \/>/i"),array("\n","\n","\n"),$plainBody);
            $plainBody .= "\n\n".$currentUserModel->get('signature');
            $plainBody = utf8_encode(strip_tags($plainBody));
            $plainBody = Emails_Mailer_Model::convertToAscii($plainBody);
            
            $mailer->AltBody = $plainBody;
            
            $mailer->AddAddress($email);
            
            //Adding attachments to mail
            if(is_array($attachments)) {
                foreach($attachments as $attachment) {
                    $fileNameWithPath = $rootDirectory.$attachment['path'].$attachment['fileid']."_".$attachment['attachment'];
                    if(is_file($fileNameWithPath)) {
                        $mailer->AddAttachment($fileNameWithPath, $attachment['attachment']);
                    }
                }
            }
            if ($logo) {
                $companyDetails = Vtiger_CompanyDetails_Model::getInstanceById();
                $companyLogoDetails = $companyDetails->getLogo();
                //While sending email template and which has '$logo$' then it should replace with company logo
                $mailer->AddEmbeddedImage($companyLogoDetails->get('imagepath'), 'companyLogo', 'attachment', 'base64', 'image/jpg');
            }
            
            $ccs = array_filter(explode(',',$cc));
            
            if(!empty($ccs)) {
                foreach($ccs as $cc) $mailer->AddCC($cc);
            }
            
        }
        // to convert external css to inline css
        $mailer->Body = Emails_Mailer_Model::convertCssToInline($mailer->Body);
        //To convert image url to valid
        $mailer->Body = Emails_Mailer_Model::makeImageURLValid($mailer->Body);
        
        $mailer->Send(true);
    
    }
    
}

function getAttachmentDetails($recordId) {
    
	$db = PearDatabase::getInstance();
    
    $attachmentRes = $db->pquery("SELECT * FROM vtiger_attachments
	INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_attachments.attachmentsid
	WHERE vtiger_crmentity.deleted = 0 AND vtiger_attachments.attachmentsid=?", array($recordId));
	
    $numOfRows = $db->num_rows($attachmentRes);
    
	$attachmentsList = array();
    
	if($numOfRows) {
        
		for($i=0; $i<$numOfRows; $i++) {
            
			$attachmentsList[$i]['fileid'] = $db->query_result($attachmentRes, $i, 'attachmentsid');
            
			$attachmentsList[$i]['attachment'] = decode_html($db->query_result($attachmentRes, $i, 'name'));
            $path = $db->query_result($attachmentRes, $i, 'path');
            
			$attachmentsList[$i]['path'] = $path;
            $attachmentsList[$i]['size'] = filesize($path.$attachmentsList[$i]['fileid'].'_'.$attachmentsList[$i]['attachment']);
            $attachmentsList[$i]['type'] = $db->query_result($attachmentRes, $i, 'type');
            $attachmentsList[$i]['cid'] = $db->query_result($attachmentRes, $i, 'cid');
        
		}
		
    }
    
    return $attachmentsList;
}

function SyncTicketsWithHQ( $entityData ){
    
    $data = $entityData->getData();
    
    $creatorId = vtws_getIdComponents($data['creator']);
    $assigned = vtws_getIdComponents($data['assigned_user_id']);
    
    $creatorName = getUserFullName($creatorId[1]);
    $userName = getUserFullName($assigned[1]);
    
    $data['originalcreatorname'] = $creatorName;
    $data['originalassigneduser'] = $userName; 
	
	$host_parts = explode(".", $_SERVER['HTTP_HOST']);
	$data['source'] = $host_parts[0];
	
    require_once "vtlib/Vtiger/Net/Client.php";
    
    $httpc = new Vtiger_Net_Client("https://hq.360vew.com/webservice.php");
    
    $params = array();
    
    $params['operation'] = 'getchallenge';
    
    $params['username'] = 'admin';
    
    $response = $httpc->doGet($params);
    
    $jsonResponse = json_decode($response,true);
    
    $challengeToken = $jsonResponse['result']['token'];
    
    $master_Key = 'rV8daZto5LQj779';
    
    $generatedKey = md5($challengeToken.$master_Key);
    
    $params = array();
    
    $params['operation'] = 'login';
    
    $params['username'] = 'admin';
    
    $params['accessKey'] = $generatedKey;
    
    $response = $httpc->doPost($params);
    
    $jsonResponse = json_decode($response,true);
   
    $sessionId = $jsonResponse['result']['sessionName'];
    
    $userId = $jsonResponse['result']['userId'];
    
    $data['mode'] = 'tickets';
    
    $params = array(
		"sessionName" => $sessionId, 
		"operation" => 'sync_ticket_and_comment', 
		"element" => json_encode($data)
	);
    
    $response = $httpc->doPost($params);
}

function SyncTicketCommentsWithHQ( $entityData ){
    
    
    global $adb;
    global $current_user;
	
	$current_user_name = $current_user->first_name . ' ' . $current_user->last_name;
	
    $wsId = $entityData->getId();
    $parts = explode('x', $wsId);
    $entityId = $parts[1];
    
    $data = $entityData->getData();
    
    $comment = $adb->pquery("SELECT vtiger_modcomments.* FROM vtiger_modcomments
    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_modcomments.modcommentsid
    WHERE vtiger_crmentity.deleted = 0 AND vtiger_modcomments.related_to = ?
    ORDER BY vtiger_modcomments.modcommentsid DESC",array($entityId));
    
    $comData = array();
    if($adb->num_rows($comment)){
        $comData = $adb->query_result_rowdata($comment,0);
    }
	
	
	$comData['commentcontent'] = $current_user_name . ':  ' . $comData['commentcontent'];
	
    $comData['mode'] = 'comment';
    
    require_once "vtlib/Vtiger/Net/Client.php";
    
    $httpc = new Vtiger_Net_Client("https://hq.360vew.com/webservice.php");
    
    $params = array();
    
    $params['operation'] = 'getchallenge';
    
    $params['username'] = 'admin';
    
    $response = $httpc->doGet($params);
    
    $jsonResponse = json_decode($response,true);
    
    $challengeToken = $jsonResponse['result']['token'];
    
    $master_Key = 'rV8daZto5LQj779';
    
    $generatedKey = md5($challengeToken.$master_Key);
    
    $params = array();
    
    $params['operation'] = 'login';
    
    $params['username'] = 'admin';
    
    $params['accessKey'] = $generatedKey;
    
    $response= $httpc->doPost($params);
    
    $jsonResponse = json_decode($response,true);
    
	$sessionId = $jsonResponse['result']['sessionName'];
    
    $userId = $jsonResponse['result']['userId'];
    
    $comData['ticket_no'] = $data['ticket_no'];
    
    $host_parts = explode(".", $_SERVER['HTTP_HOST']);
    $comData['source'] = $host_parts[0];
    
    $params = array("sessionName"=>$sessionId, "operation"=>'sync_ticket_and_comment', "element" => json_encode($comData));
    
    $response = $httpc->doPost($params);
}

function SyncTicketWithInstance( $entityData ){
    
    $data = $entityData->getData();
    
    $site = strtolower($data['source']);
    
    require_once "vtlib/Vtiger/Net/Client.php";
    
    $httpc = new Vtiger_Net_Client("https://".$site.".360vew.com/webservice.php");
    
    $data['mode'] = 'tickets';
    
    $params = array(
        "operation" => 'sync_ticket_and_comments_with_instance',
        "element" => json_encode($data)
    );
    
    $response = $httpc->doPost($params);
   
}

function SyncTicketCommentsWithInstance( $entityData ){
    
    global $adb;
    global $current_user;
    
    $current_user_name = $current_user->first_name . ' ' . $current_user->last_name;
    
    $wsId = $entityData->getId();
    $parts = explode('x', $wsId);
    $entityId = $parts[1];
    
    $data = $entityData->getData();
    
    $comment = $adb->pquery("SELECT vtiger_modcomments.* FROM vtiger_modcomments
    INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_modcomments.modcommentsid
    WHERE vtiger_crmentity.deleted = 0 AND vtiger_modcomments.related_to = ?
    ORDER BY vtiger_modcomments.modcommentsid DESC",array($entityId));
    
    $comData = array();
    if($adb->num_rows($comment)){
        $comData = $adb->query_result_rowdata($comment,0);
    }
    
    $comData['commentcontent'] = $current_user_name . ':  ' . $comData['commentcontent'];
    
    $comData['mode'] = 'comment';
    
    $site = strtolower($data['source']);
    
    require_once "vtlib/Vtiger/Net/Client.php";
    
    $httpc = new Vtiger_Net_Client("https://".$site.".360vew.com/webservice.php");

    $comData['ticket_no'] = $data['referenceid'];
    
    $params = array(
        "operation"=>'sync_ticket_and_comments_with_instance', 
        "element" => json_encode($comData)
    );
    
    $response = $httpc->doPost($params);
    
}
?>
