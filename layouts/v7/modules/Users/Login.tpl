{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}
{* modules/Users/views/Login.php *}

{strip}
	<style>
		 body { 
		 	overflow-x:hidden; 
		 }
		 #page{
		 	padding-top :0px;
		 }
		.login-heading {
		    line-height: 113px;
			vertical-align:middle;
		    overflow: hidden;
		    width: 180px;
		    margin-left: auto;
		    margin-right: auto;
		    text-align: center;
		    display: table;
		    background-color :unset !important;
		}
		.loginDiv {
			max-width: 380px;
			margin: 0 auto;
			border: 1px solid #d8dde6;
			border-radius: 4px;
			//box-shadow: 0 0 10px gray;
			background-color: #FFFFFF;
			padding: 1.25rem;
		}
		.form-group input{
			border: 1px solid #d8dde6;
			border-radius: 4px;
			height : auto !important;
			padding : 12px !important;
		}
		.form-group label{
			font-size: 12px;
		    color: #54698d;
		    margin: 0px 0px 8px;
		    line-height: inherit;
		    font-weight: unset;
		}
		.button{
			width: 100%;
			background-color: #0070d2;
		    color: white;
		    transition: all 0.1s ease 0s;
		    border: 1px solid transparent;
		    padding: 12px 24px; 
    		border-radius: 4px;
    		cursor: pointer;
    		background-image: none !important;
		}
		.fgtlink{
		    border-top: 1px solid #f4f6f9;
		    padding-top: 10px;
		    margin-top: 10px;
		    font-size: 1rem;
		    color: #0070d2;
		}
	    .failureMessage {
			color: red;
			display: block;
			text-align: center;
			padding: 0px 0px 10px;
		}
		.successMessage {
			color: green;
			display: block;
			text-align: center;
			padding: 0px 0px 10px;
		}
		.app-footer {
		    width: 100%;
		    text-align: center;
		    background: #FBFBFB;
		    margin-bottom: 0;
		    padding: 4px 0;
		    border-top: unset;
		    border-width: thin;
	    }
	    .app-footer .login-social li {
		    display: inline-block;
		    list-style: none;
		    margin-right: 1em;
		}
		.app-footer { 
			position:fixed; 
			bottom:0px;
		}
		
		@media (min-width: 576px) { 
			.app-footer { 
				width:50%;
			}
		}
		
		@media (min-width: 768px) {
			.app-footer { 
				width:50%;
			}
		}
		
		{if $BGTYPE eq 'image'}
			
			@media (min-width: 992px) { 
				body{
					background-image: url({$BACKGROUND});
					background-repeat: no-repeat;
				    background-position: center right;
				    background-size: 50% 100%;
				    background-attachment: fixed;
				}
				.app-footer { 
					width:50%;
				}
			}

			@media (min-width: 1200px) { 
				body{
					background-image: url({$BACKGROUND});
					background-repeat: no-repeat;
				    background-position: center right;
				    background-size: 50% 100%;
				    background-attachment: fixed;
				}
				.app-footer { 
					width:50%;
				}
			}
			
		{/if}
		
		
	    
		#myVideo {
		  position: fixed;
		  right: 0;
		  bottom: 0;
		  left:50%;
		  min-width: 100%;
		  min-height: 100%;
		}
		.right {
			color : #ffffff;
			padding-top :10%;
		}
		.extbuttons {
			max-width: 380px;
			margin: 0 auto;
			padding: 1.25rem;
			margin-top: 10px;
		}
	</style>
	<div class="main-container">
		<div class="row">

			<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 left">
				<div class="login-heading" style = "padding-top:80px;padding-bottom:15px;">
                    {if $LOGO}
	        			<img class="login-logo center" src="{$LOGO}" style="width: 100%;" />
	        		{else}
	        			<img class="login-logo center" src="test/logo/Omnilogo.png" style="width: 100%;" />
        			{/if}
                </div>
		
				<div class="loginDiv bg-white">
					<div class="vertical-align ">
						<div>
							<span class="{if !$ERROR}hide{/if} failureMessage" id="validationMessage">{$MESSAGE}</span>
							<span class="{if !$MAIL_STATUS}hide{/if} successMessage">{$MESSAGE}</span>
						</div>
						
						<div id="loginFormDiv">
							<form class="login-form" method="POST" action="index.php" >
								<input type="hidden" name="module" value="Users"/>
								<input type="hidden" name="action" value="Login"/>
								<div class="form-group">
									<label  for="inlineFormInputGroup">Username</label>
								    <input type="text" class="form-control" id="username" placeholder="Username" name="username" >
								</div>
								<div class="form-group">
									<label  for="inlineFormInputGroup">Password</label>
								    <input type="password" class="form-control" id="password" placeholder="Password" name="password" >
								</div>
								<div class="text-center">
									<button type="submit" class="button buttonBlue">Log In</button>
								</div>
							</form>
							<div class="fgtlink">
								<a class = "forgotPasswordLink">Forgot password?</a>
							</div>
						</div>
						<div id="forgotPasswordDiv" class="hide">
							<form  action="forgotPassword.php" method="POST" >
								<div class="form-group">
									<label  for="inlineFormInputGroup">Username</label>
								    <input type="text" class="form-control" id="fusername" placeholder="Username" name="username">
								</div>
								<div class="form-group">
									<label  for="inlineFormInputGroup">Email</label>
								    <input id="email" type="email" class="form-control" id="inlineFormInputGroup" placeholder="Email" name="emailId">
								</div>
								<div class="text-center">
									<button type="submit" class="button buttonBlue forgot-submit-btn">Submit</button>
								</div>
							</form>
							<div class="fgtlink">
								<a class="purple forgotPasswordLink">Back</a>
							</div>
						</div>
					</div>
				</div>
				<div class="extbuttons">
					{if $OFFICE_ACTIVE}
	            		<button class="btn btn-lg btn-block officeLogin oauthLogin pull-left m-b" data-url="{$AUTH_URL}" type="button" style = "padding:0px;background:#DD4B39;border-color:rgba(0,0,0,0.2);font-weight:600; color:#FFFFFF !important;margin-bottom: 15px;">
	            			<div class="officeIcon pull-left" style="padding:6px;background-color:white;">
	                			<svg xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" viewBox="0 0 278050 333334" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd">
	                				<path fill="#ea3e23" d="M278050 305556l-29-16V28627L178807 0 448 66971l-448 87 22 200227 60865-23821V80555l117920-28193-17 239519L122 267285l178668 65976v73l99231-27462v-316z"></path>
	            				</svg>
	        				</div>
	        				<div class="officetext" style="padding: 6px;padding-left: 40px;">
	            				Sign In With Office365
	        				</div>
	        			</button>
	            	{/if}
	            	
	            	{if $GOOGLE_ACTIVE}
	            		<button class="btn btn-lg btn-block googleLogin oauthLogin m-b" data-url="{$GOOGLE_AUTH_URL}" type="button" style = "padding:0px;background:#0098CF;border-color:rgba(0,0,0,0.2);font-weight:600; color:#FFFFFF !important;margin-bottom: 15px;">
	            			<div class="googleIcon pull-left" style="padding:6px;background-color:white;">
								<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" viewBox="0 0 48 48" class="abcRioButtonSvg">
									<g>
										<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>	
										<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
										<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
										<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
										<path fill="none" d="M0 0h48v48H0z"></path>
									</g>
								</svg>
							</div>
							<div class="googletext" style="padding: 6px;padding-left: 40px;">
	            				Sign In With Google
	        				</div>
	        			</button>
	        		{/if}
				</div>
				<br><br>
				<div class="app-footer text-center" id="footer">
					{if $COPYRIGHT}
		          		<div class="login-copyright " style = "padding-top:5px;">
		                   <span style = "padding-right:5px;">&copy; {$COPYRIGHT} </span>
		                </div>
	                {/if}
                    <ul class="login-social" style = "padding:0px;margin-top:5px;">
                    	{if $FACEBOOK_LINK}
                            <li>
                                <a href="{$FACEBOOK_LINK}" target="_blank">
                                    <i class="fa fa-facebook"></i>
                                </a>
                            </li>
                        {/if}
                        {if $TWITTER_LINK}
                            <li>
                                <a href="{$TWITTER_LINK}" target="_blank">
                                    <i class="fa fa-twitter"></i>
                                </a>
                            </li>
                        {/if}
                        {if $LINKEDIN_LINK}
                        	<li>
                    			<a href="{$LINKEDIN_LINK}" target="_blank">
                                    <i class="fa fa-linkedin"></i>
                                </a>
                            </li>
                        {/if}
                        {if $YOUTUBE_LINK}
                            <li>
                                <a href="{$YOUTUBE_LINK}" target="_blank">
                                    <i class="fa fa-youtube"></i>
                                </a>
                            </li>
                        {/if}
                        {if $INSTA_LINK}
                            <li>
                                <a href="{$INSTA_LINK}" target="_blank">
                                    <i class="fa fa-instagram"></i>
                                </a>
                            </li>
                        {/if}
                    </ul>
				</div>
			</div>
			<div class="col-lg-6 hidden-xs hidden-sm hidden-md right">
				{if $BGTYPE eq 'video'}
					<video autoplay muted loop id="myVideo">
						<source src="{$BACKGROUND}" type="video/mp4">
					</video>
				{else if $BGTYPE eq ''}
					<video autoplay muted loop id="myVideo">
						<source src="test/logo/login-video.mp4" type="video/mp4">
					</video>
				{/if}
				<div class="marketingDiv widgetHeight">
					{if $JSON_DATA}
						<div class="scrollContainer">
							{assign var=ALL_BLOCKS_COUNT value=0}
							{foreach key=BLOCK_NAME item=BLOCKS_DATA from=$JSON_DATA}
								{if $BLOCKS_DATA}
									<div>
										<!-- <h4>{$BLOCKS_DATA[0].heading}</h4> -->
										<ul class="bxslider">
											{foreach item=BLOCK_DATA from=$BLOCKS_DATA}
												<li class="slide" style = "color:black;">
													{assign var=ALL_BLOCKS_COUNT value=$ALL_BLOCKS_COUNT+1}
													<div class="col-lg-12">
													
														<div title="{$BLOCK_DATA.summary}">
															<h4 style = "font-size:20px;line-height:30px;font-weight:600;">{$BLOCK_DATA.displayTitle}</h4>
															<div style = "font-size:13px;line-height:25px;">{$BLOCK_DATA.summary}</div>
															<a href="{$BLOCK_DATA.url}" target="_blank"><u>{$BLOCK_DATA.urlalt}</u></a>
														</div>
													
													</div>
													{if $BLOCK_DATA.image}
														<div class="col-lg-12" style = "margin-bottom:10px;">
															<img src="{$BLOCK_DATA.image}" style="width:60%;height: 100%;margin-top: 10px;object-fit:contain;"/>
														</div>
													{/if}
													
													
													
													
												</li>
											{/foreach}
										</ul>
									</div>
									{if $ALL_BLOCKS_COUNT neq $DATA_COUNT}
										<br>
										<hr>
									{/if}
								{/if}
							{/foreach}
						</div>
					{/if}
				</div>
			</div>
         
		</div>

</div>
	{include file='JSResources.tpl'|@vtemplate_path}

		<script>
			jQuery(document).ready(function () {
				var validationMessage = jQuery('#validationMessage');
				var forgotPasswordDiv = jQuery('#forgotPasswordDiv');

				var loginFormDiv = jQuery('#loginFormDiv');
				loginFormDiv.find('#password').focus();

				loginFormDiv.find('a').click(function () {
					loginFormDiv.toggleClass('hide');
					forgotPasswordDiv.toggleClass('hide');
					validationMessage.addClass('hide');
				});

				forgotPasswordDiv.find('a').click(function () {
					loginFormDiv.toggleClass('hide');
					forgotPasswordDiv.toggleClass('hide');
					validationMessage.addClass('hide');
				});

				loginFormDiv.find('button').on('click', function () {
					var username = loginFormDiv.find('#username').val();
					var password = jQuery('#password').val();
					var result = true;
					var errorMessage = '';
					if (username === '') {
						errorMessage = 'Please enter valid username';
						result = false;
					} else if (password === '') {
						errorMessage = 'Please enter valid password';
						result = false;
					}
					if (errorMessage) {
						validationMessage.removeClass('hide').text(errorMessage);
					}
					return result;
				});

				forgotPasswordDiv.find('button').on('click', function () {
					var username = jQuery('#forgotPasswordDiv #fusername').val();
					var email = jQuery('#email').val();

					var email1 = email.replace(/^\s+/, '').replace(/\s+$/, '');
					var emailFilter = /^[^@]+@[^@.]+\.[^@]*\w\w$/;
					var illegalChars = /[\(\)\<\>\,\;\:\\\"\[\]]/;

					var result = true;
					var errorMessage = '';
					if (username === '') {
						errorMessage = 'Please enter valid username';
						result = false;
					} else if (!emailFilter.test(email1) || email == '') {
						errorMessage = 'Please enter valid email address';
						result = false;
					} else if (email.match(illegalChars)) {
						errorMessage = 'The email address contains illegal characters.';
						result = false;
					}
					if (errorMessage) {
						validationMessage.removeClass('hide').text(errorMessage);
					}
					return result;
				});
				jQuery('input').blur(function (e) {
					var currentElement = jQuery(e.currentTarget);
					if (currentElement.val()) {
						currentElement.addClass('used');
					} else {
						currentElement.removeClass('used');
					}
				});

				var ripples = jQuery('.ripples');
				ripples.on('click.Ripples', function (e) {
					jQuery(e.currentTarget).addClass('is-active');
				});

				ripples.on('animationend webkitAnimationEnd mozAnimationEnd oanimationend MSAnimationEnd', function (e) {
					jQuery(e.currentTarget).removeClass('is-active');
				});
				loginFormDiv.find('#username').focus();

				var slider = jQuery('.bxslider').bxSlider({
					auto: true,
					pause: 4000,
					nextText: "",
					prevText: "",
					autoHover: true
				});
				jQuery('.bx-prev, .bx-next, .bx-pager-item').live('click',function(){ slider.startAuto(); });
				jQuery('.bx-wrapper .bx-viewport').css('background-color', 'transparent');
				jQuery('.bx-wrapper .bxslider li').css('text-align', 'left');
				jQuery('.bx-wrapper .bx-pager').css('bottom', '-15px');

				var params = {
					theme		: 'dark-thick',
					setHeight	: '100%',
					advanced	:	{
										autoExpandHorizontalScroll:true,
										setTop: 0
									}
				};
				jQuery('.scrollContainer').mCustomScrollbar(params);
				
			   $('.oauthLogin').on('click', function(){
	            	console.log('test');
	            	var url = $(this).data('url');
	            	
	            	var win= window.open(url,'','height=600,width=600,channelmode=1');
					
					window.RefreshPage = function(code, module) {
						
						var data = [];
						$.each($('.login-form').serializeArray(), function(i, field){
						    data[field.name] = field.value;
						});
						data['code'] = code;
						
						$.ajax({
			    			type: "POST",
			    			url:'index.php?'+$('.login-form').serialize()+'&code='+code+'&source='+module,
			    			error: function(errorThrown) {
			    				console.log(errorThrown)
			    			},
			    			success: function(url) {
			    				window.location = url;
			    			}
			    		});
					}
	            	
	            });
			});
		</script>
		</div>
	</body>

</html>
{/strip}
