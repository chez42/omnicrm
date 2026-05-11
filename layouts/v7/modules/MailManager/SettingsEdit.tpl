{*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************}

{strip}
    <div class="modal-dialog modal-md mapcontainer">
        <div class="modal-content">
            {if $MAILBOX->exists()}
                {assign var=MODAL_TITLE value=vtranslate('LBL_EDIT_MAILBOX', $MODULE)}
            {else}
                {assign var=MODAL_TITLE value=vtranslate('LBL_CREATE_MAILBOX', $MODULE)}
            {/if}
            {include file="ModalHeader.tpl"|vtemplate_path:$SOURCE_MODULE TITLE=$MODAL_TITLE}
            <form id="EditView" method="POST">
            	<input type="hidden" name="account_id" value="{$MAILBOX->mId}" />
                <div class="modal-body" id="mmSettingEditModal">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font>{vtranslate('LBL_SELECT_ACCOUNT',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <select id="serverType" class="select2 col-lg-9" data-rule-required="true" name="serverType">
                                        <option></option>
                                        <option value='gmail' {if $SERVERNAME eq 'gmail'} selected {/if}>{vtranslate('JSLBL_Gmail',$MODULE)}</option>
                                        <option value='yahoo' {if $SERVERNAME eq 'yahoo'} selected {/if}>{vtranslate('JSLBL_Yahoo',$MODULE)}</option>
                                        {*<option value='fastmail' {if $SERVERNAME eq 'fastmail'} selected {/if}>{vtranslate('JSLBL_Fastmail',$MODULE)}</option>*}
                                        <option value='office365' {if $SERVERNAME eq 'office365'} selected {/if}>{vtranslate('Office 365',$MODULE)}</option>
                                        {*<option value='omniExchange' {if $SERVERNAME eq 'omniExchange'} selected {/if}>{vtranslate('Omni Mail',$MODULE)}</option>*}
                                        <option value='other' {if $SERVERNAME eq 'other'} selected {/if}>{vtranslate('JSLBL_Other',$MODULE)}</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font> {vtranslate('IMAP',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input name="_mbox_server" id="_mbox_server" data-rule-required="true" class="inputElement width75per" value="{$MAILBOX->server()}" type="text" placeholder="mail.company.com or 192.168.X.X">
                                </td>
                            </tr>
                            <tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font> {vtranslate('SMTP',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input name="_mbox_smtp_server" id="_mbox_smtp_server" data-rule-required="true" class="inputElement width75per" value="{$MAILBOX->smtpServer()}" type="text" placeholder="mail.company.com or 192.168.X.X">
                               		<select id="smtpPort" class="select2 inputElement smtpPort" data-rule-required="true" name="smtpPort" style="display:none;width:23%;margin-left:5px;">
                                        <option></option>
                                        <option value='plain'>{vtranslate('Plain',$MODULE)}</option>
                                        <option value='tls'>{vtranslate('TLS',$MODULE)}</option>
                                        <option value='ssl'>{vtranslate('SSL',$MODULE)}</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font> {vtranslate('From Email',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input name="_mbox_from_email" id="_mbox_from_email" data-rule-required="true" data-rule-email="true" class="inputElement width75per" value="{$MAILBOX->fromEmail()}" type="text" placeholder="from email">
                                </td>
                            </tr>
                            <tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font> {vtranslate('From Name',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input name="_mbox_from_name" id="_mbox_from_name" data-rule-required="true" class="inputElement width75per" value="{$MAILBOX->fromName()}" type="text" placeholder="from name">
                                </td>
                            </tr>
                            <tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font> {vtranslate('LBL_Username',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input name="_mbox_user" class="inputElement width75per" data-rule-required="true" id="_mbox_user" value="{$MAILBOX->username()}" type="text" placeholder="{vtranslate('LBL_Your_Mailbox_Account',$MODULE)}">
                                </td>
                            </tr>
                            <tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer"><font color="red">*</font> {vtranslate('LBL_Password',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input name="_mbox_pwd" class="inputElement width75per" data-rule-required="true" id="_mbox_pwd" value="{$MAILBOX->password()}" type="password" placeholder="{vtranslate('LBL_Account_Password',$MODULE)}">
                                </td>
                            </tr>
                            <tr class="additional_settings {if $SERVERNAME neq 'other'}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer">{vtranslate('LBL_Protocol',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input type="radio" name="_mbox_protocol" class="mbox_protocol" value="IMAP2" {if strcasecmp($MAILBOX->protocol(), 'imap2')===0}checked=true{/if}> {vtranslate('LBL_Imap2',$MODULE)}
                                    <input type="radio" name="_mbox_protocol" class="mbox_protocol" value="IMAP4" {if strcasecmp($MAILBOX->protocol(), 'imap4')===0}checked=true{/if} style="margin-left: 10px;"> {vtranslate('LBL_Imap4',$MODULE)}
                                </td>
                            </tr>
                            <tr class="additional_settings {if $SERVERNAME neq 'other'}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer">{vtranslate('LBL_SSL_Options',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input type="radio" name="_mbox_ssltype" class="mbox_ssltype" value="notls" {if strcasecmp($MAILBOX->ssltype(), 'notls')===0}checked=true{/if}> {vtranslate('LBL_No_TLS',$MODULE)}
                                    <input type="radio" name="_mbox_ssltype" class="mbox_ssltype" value="tls" {if strcasecmp($MAILBOX->ssltype(), 'tls')===0}checked=true{/if} style="margin-left: 10px;"> {vtranslate('LBL_TLS',$MODULE)}
                                    <input type="radio" name="_mbox_ssltype" class="mbox_ssltype" value="ssl" {if strcasecmp($MAILBOX->ssltype(), 'ssl')===0}checked=true{/if}  style="margin-left: 10px;"> {vtranslate('LBL_SSL',$MODULE)}
                                </td>
                            </tr>
                            <tr class="additional_settings {if $SERVERNAME neq 'other'}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer">{vtranslate('LBL_Certificate_Validations',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <input type="radio" name="_mbox_certvalidate" class="mbox_certvalidate" value="validate-cert" {if strcasecmp($MAILBOX->certvalidate(), 'validate-cert')===0}checked=true{/if} > {vtranslate('LBL_Validate_Cert',$MODULE)}
                                    <input type="radio" name="_mbox_certvalidate" class="mbox_certvalidate" value="novalidate-cert" {if strcasecmp($MAILBOX->certvalidate(), 'novalidate-cert')===0}checked=true{/if} style="margin-left: 10px;"> {vtranslate('LBL_Do_Not_Validate_Cert',$MODULE)}
                                </td>
                            </tr>

                            <tr class="refresh_settings {if $MAILBOX && $MAILBOX->exists()}{else}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer">{vtranslate('LBL_REFRESH_TIME',$MODULE)}</label>
                                </td>
                                <td class="fieldValue">
                                    <select name="_mbox_refresh_timeout" class="select2 col-lg-9">
                                        <option value="" {if $MAILBOX->refreshTimeOut() eq ''}selected{/if}>{vtranslate('LBL_NONE',$MODULE)}</option>
                                        <option value="300000" {if strcasecmp($MAILBOX->refreshTimeOut(), '300000')==0}selected{/if}>{vtranslate('LBL_5_MIN',$MODULE)}</option>
                                        <option value="600000" {if strcasecmp($MAILBOX->refreshTimeOut(), '600000')==0}selected{/if}>{vtranslate('LBL_10_MIN',$MODULE)}</option>
                                    </select>
                                </td>
                            </tr>

                            {*<tr class="settings_details {if $SERVERNAME eq ''}hide{/if}">
                                <td class="fieldLabel width40per">
                                    <label class="pull-right detailViewButtoncontainer">{vtranslate('LBL_SAVE_SENT_MAILS_IN',$MODULE)}</label>
                                </td>
                                <td class="fieldValue selectFolderValue {if !$MAILBOX->exists()}hide{/if}">
                                    <select name="_mbox_sent_folder" class="select2 col-lg-9">
                                        {foreach item=FOLDER from=$FOLDERS}
                                            <option value="{$FOLDER->name()}" {if $FOLDER->name() eq $MAILBOX->folder()} selected {/if}>{$FOLDER->name()}</option>
                                        {/foreach}
                                    </select>
                                        <i class="fa fa-info-circle cursorPointer" id="mmSettingInfo" title="{vtranslate('LBL_CHOOSE_FOLDER',$MODULE)}"></i>
                                </td>
                                <td class="fieldValue selectFolderDesc alert alert-info {if $MAILBOX->exists()}hide{/if}">
                                    {vtranslate('LBL_CHOOSE_FOLDER_DESC',$MODULE)}
                                </td>
                            </tr>*}
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    {if $MAILBOX->exists()}
                        <button class="btn btn-danger" data-boxid="{$MAILBOX->mId}" id="deleteMailboxBtn"><strong>{vtranslate('LBL_DELETE_Mailbox',$MODULE)}</strong></button>
                    {/if}
                    <button class="btn btn-success" id="saveMailboxBtn" type="submit" name="saveButton"><strong>{vtranslate('LBL_SAVE',$MODULE)}</strong></button>
                    <a href="#" class="cancelLink" type="reset" data-dismiss="modal">{vtranslate('LBL_CANCEL', $MODULE)}</a>
                </div>
            </form>
        </div>
    </div>
{/strip}
