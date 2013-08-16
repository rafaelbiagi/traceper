<?php

class SiteController extends Controller
{
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
				// captcha action renders the CAPTCHA image displayed on the contact page
// 				'captcha'=>array(
// 						'class'=>'CCaptchaAction',
// 						'backColor'=>0xFFFFFF,
// 				),
				
				'captcha'=>array(
						'class'=>'CaptchaExtendedAction',
						// if needed, modify settings
						'mode'=>CaptchaExtendedAction::MODE_MATH, //MODE_MATH, MODE_MATHVERBAL, MODE_DEFAULT, MODE_LOGICAL, MODE_WORDS
				),	

				// page action renders "static" pages stored under 'protected/views/site/pages'
				// They can be accessed via: index.php?r=site/page&view=FileName
				'page'=>array(
						'class'=>'CViewAction',
				),
		);
	}

	public function filters()
	{
		return array(
				'accessControl',
		);
	}

	public function accessRules()
	{
		return array(
				array('deny',
						'actions'=>array('changePassword', 'inviteUser', 'registerGPSTracker'),
						'users'=>array('?'),
				),			
		);
	}


	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionIndex()
	{
		// renders the view file 'protected/views/site/index.php'
		// using the default layout 'protected/views/layouts/main.php'
		$this->render('index');
	}



	/**
	 * This is the action to handle external exceptions.
	 */
	public function actionError()
	{
		if($error=Yii::app()->errorHandler->error)
		{
			if(Yii::app()->request->isAjaxRequest)
				echo $error['message'];
			else
				$this->render('error', $error);
		}
	}
	
	//UTF8_mail() from parametresini <> ile vermezsen �al��m�yor, buna bak�lacak
	public function UTF8_mail($from, $to, $subject, $message) 
	{
		$from2 = explode("<", $from);
		
		if (isset($from2[0])) {
			$headers = "From: =?UTF-8?B?".base64_encode($from2[0])."?= <".$from2[1]."\r\n";
		} else {
			$headers = "From: ".$from[1]."\r\n";
		}
		
		$subject ="=?UTF-8?B?".base64_encode($subject)."?=\n";
	
// 		$headers .= "Content-Type: text/plain; charset=iso-8859-1; format=flowed \n".
// 				"MIME-Version: 1.0 \n" .
// 				"Content-Transfer-Encoding: 8bit \n".
// 				"X-Mailer: PHP \n";
		
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";	
		
		//ini_set('sendmail_from', 'contact@traceper.com'); //Suggested by "Some Guy"
			
		return mail($to, $subject, $message, $headers);
	}	

	/**
	 * Displays the contact page
	 */
// 	public function actionContact()
// 	{
// 		$model=new ContactForm;
// 		if(isset($_POST['ContactForm']))
// 		{
// 			$model->attributes=$_POST['ContactForm'];
// 			if($model->validate())
// 			{
// 				$headers="From: {$model->email}\r\nReply-To: {$model->email}";
// 				mail(Yii::app()->params['adminEmail'],$model->subject,$model->body,$headers);
// 				Yii::app()->user->setFlash('contact','Thank you for contacting us. We will respond to you as soon as possible.');
// 				$this->refresh();
// 			}
// 		}
// 		$this->render('contact',array('model'=>$model));
// 	}
	
	public function actionContact()
	{
		$model = new ContactForm;
		
		$processOutput = true;
		// collect user input data
		if(isset($_POST['ContactForm']))
		{
			$model->attributes=$_POST['ContactForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				if(Yii::app()->user->isGuest == true)
				{
					if($this->SMTP_UTF8_mail($model->email, $model->firstName.' '.$model->lastName, 'contact@traceper.com', 'Traceper', $model->subject, $model->detail))
					{
						echo CJSON::encode(array("result"=> "1"));
					}
					else
					{
						echo CJSON::encode(array("result"=> "0"));
					}								
				}
				else
				{
					$name = null;
					$email = null;
					
					Users::model()->getUserInfo(Yii::app()->user->id, $name, $email);

					if($this->SMTP_UTF8_mail($email, $name, 'contact@traceper.com', 'Traceper', $model->subject, $model->detail))
					{
						echo CJSON::encode(array("result"=> "1"));
					}
					else
					{
						echo CJSON::encode(array("result"=> "0"));
					}					
				}

				Yii::app()->end();
			}
		}

		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
		
		//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
		if (Yii::app()->request->getIsAjaxRequest()) {
			Yii::app()->clientScript->scriptMap['*.js'] = false;
			Yii::app()->clientScript->scriptMap['*.css'] = false;
		}		
		
		$this->renderPartial('contact',array('model'=>$model), false, $processOutput);		
	}	

	public function actionLogin()
	{
		//Fb::warn("actionLogin() called", "SiteController");		
		
		$model = new LoginForm;
			
		$processOutput = true;

		// collect user input data
		if(isset($_REQUEST['LoginForm']))
		{
			$model->attributes = $_REQUEST['LoginForm'];
			// validate user input and if ok return json data and end application.

			// 			if (Yii::app()->request->isAjaxRequest) {
			// 				$processOutput = false;
			// 			}
			
			$minDataSentInterval = Yii::app()->params->minDataSentInterval;
			$minDistanceInterval = Yii::app()->params->minDistanceInterval;	 
			$facebookId = 0; 
			$autoSend = 0;
			
			$deviceId = null;
			$androidVer = null;
			$appVer = null;
			$preferredLanguage = null;

			$isRecordUpdateRequired = false;

			if($model->validate() && $model->login()) {
				if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
				{			
					Users::model()->getLoginRequiredValues(Yii::app()->user->id, $minDataSentInterval, $minDistanceInterval, $facebookId, $autoSend, $deviceId, $androidVer, $appVer, $preferredLanguage);
					
					if (isset($_REQUEST['deviceId']))
					{
						if(strcmp($deviceId, $_REQUEST['deviceId']) != 0)
						{
							$deviceId = $_REQUEST['deviceId'];
							$isRecordUpdateRequired = true;
						}
					}					
					
					if (isset($_REQUEST['androidVer']))
					{
						if(strcmp($androidVer, $_REQUEST['androidVer']) != 0)
						{
							$androidVer = $_REQUEST['androidVer'];
							$isRecordUpdateRequired = true;
						}						
					}
					
					if (isset($_REQUEST['appVer']))
					{
						if(strcmp($appVer, $_REQUEST['appVer']) != 0)
						{
							$appVer = $_REQUEST['appVer'];
							$isRecordUpdateRequired = true;
						}
					}
					
					if (isset($_REQUEST['preferredLanguage']))
					{
						if(strcmp($preferredLanguage, $_REQUEST['preferredLanguage']) != 0)
						{
							$preferredLanguage = $_REQUEST['preferredLanguage'];
							$isRecordUpdateRequired = true;
						}
					}

					if($isRecordUpdateRequired == true)
					{
						Users::model()->updateLoginSentItemsNotNull(Yii::app()->user->id, $deviceId, $androidVer, $appVer, $preferredLanguage);
					}

					echo CJSON::encode(array(
							"result"=> "1",
							"id"=>Yii::app()->user->id,
							"realname"=> $model->getName(),
							"minDataSentInterval"=> $minDataSentInterval,
							"minDistanceInterval"=> $minDistanceInterval,							
							"facebookId"=> $facebookId,
							"autoSend "=> $autoSend
					));
				}
				else {
					//echo 'Model NOT valid in SiteController';
					Yii::app()->clientScript->scriptMap['jquery.js'] = false;
					Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
					$this->renderPartial('loginSuccessful',array('id'=>Yii::app()->user->id, 'realname'=>$model->getName()), false, $processOutput);
				}

				Yii::app()->end();
			}
			else
			{
				//echo 'model NOT valid';

				if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
				{
					$result = "1"; //Initialize with "1" to be used whether no error occured
					
					if($model->getError('password') == Yii::t('site', 'Incorrect password or e-mail'))
					{
						$result = "0";
					}
					else if($model->getError('password') == Yii::t('site', 'Activate your account first'))
					{
						$result = "-1";
					}
					else
					{
						$result = "-2"; //Unknown login error
					}					

					echo CJSON::encode(array(
							"result"=> $result,
							"id"=>Yii::app()->user->id,
							"realname"=> $model->getName(),
							"minDataSentInterval"=> $minDataSentInterval,
							"minDistanceInterval"=> $minDistanceInterval,
							"facebookId"=> $facebookId,
							"autoSend "=> $autoSend
					));
				}
				else {
					//echo 'Model NOT valid in SiteController';

					Yii::app()->clientScript->scriptMap['jquery.js'] = false;
					Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
					
					//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
					if (Yii::app()->request->getIsAjaxRequest()) {
						Yii::app()->clientScript->scriptMap['*.js'] = false;
						Yii::app()->clientScript->scriptMap['*.css'] = false;
					}					
					
					$this->renderPartial('login',array('model'=>$model), false, $processOutput);
				}

				Yii::app()->end();
			}
		}
		else
		{
			//echo 'LoginForm NOT set';
			$this->renderPartial('login',array('model'=>$model), false, $processOutput);
		}
	}

	/**
	 *
	 * facebook login action
	 */
	public function actionFacebooklogin() {
		Yii::import('ext.facebook.*');
		$ui = new FacebookUserIdentity('370934372924974', 'c1e85ad2e617b480b69a8e14cfdd16c7');

		if ($ui->authenticate()) {
			$user=Yii::app()->user;
			$user->login($ui);

			$this->FB_Web_Register($nd);
			if($nd == 0)
			{
					


				$str=array("email" => Yii::app()->session['facebook_user']['email'] ,"password" => Yii::app()->session['facebook_user']['id']) ;

					
				$this->fbLogin($str);
					

			}else {

			}


			//exit;
			$this->redirect($user->returnUrl);
		} else {
			throw new CHttpException(401, $ui->error);
		}
	}

	/**
	 * Logs out the current user and redirect to homepage.
	 */
	public function actionLogout()
	{
		Yii::app()->user->logout(false);
		if (isset($_REQUEST['client']) && $_REQUEST['client'] == 'mobile') {
			// if mobile client end the app, no need to redirect...
			echo CJSON::encode(array(
					"result"=> "1"));
			Yii::app()->end();
		}
		else {
			$this->redirect(Yii::app()->homeUrl);
		}
	}


	/**
	 * Changes the user's current password with the new one
	 */
	public function actionChangePassword()
	{
		$model = new ChangePasswordForm;

		$processOutput = true;
		// collect user input data
		if(isset($_POST['ChangePasswordForm']))
		{
			$model->attributes=$_POST['ChangePasswordForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {
				//$users=Users::model()->findByPk(Yii::app()->user->id);
				//$users->password=md5($model->newPassword);

				//if($users->save()) // save the change to database
				if(Users::model()->changePassword(Yii::app()->user->id, $model->newPassword)) // save the change to database
				{
					echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					echo CJSON::encode(array("result"=> "0"));
				}
				Yii::app()->end();
			}
		}

		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
		
		//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
		if (Yii::app()->request->getIsAjaxRequest()) {
			Yii::app()->clientScript->scriptMap['*.js'] = false;
			Yii::app()->clientScript->scriptMap['*.css'] = false;
		}		

		$this->renderPartial('changePassword',array('model'=>$model), false, $processOutput);
	}
	
	/**
	 * Sends a password reset link to the user's e-mail address 
	 */
	public function actionForgotPassword()
	{
		//Fb::warn("actionForgotPassword() called", "SiteController");
		
		$model = new ForgotPasswordForm;
	
		$processOutput = true;
		// collect user input data
		if(isset($_POST['ForgotPasswordForm']))
		{
			$model->attributes = $_POST['ForgotPasswordForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {				
				$token = sha1(uniqid(mt_rand(), true));

				if(Users::model()->isUserRegistered($model->email))
				{
					ResetPassword::model()->saveToken($model->email, $token);
					$name = Users::model()->getNameByEmail($model->email);
					
					$message = Yii::t('site', 'Hi').' '.$name.',<br/><br/>';
									
					$message .= Yii::t('site', 'If you forgot your password, you can create a new password by clicking');
					$message .= ' '.'<a href="'.'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'/index.php?tok='.$token.'">'.Yii::t('site', 'here').'</a>';
					$message .= ' '.Yii::t('site', 'or the link below:').'<br/>';
					$message .= '<a href="'.'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'/index.php?tok='.$token.'">';
					$message .= 'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'/index.php?tok='.$token;
					$message .= '</a>';
					$message .= '<br/><br/><br/>';										
					$message .= Yii::t('site', 'If you did not attempt to create a new password, take no action and please inform <a href="mailto:contact@traceper.com">us</a>.');				

					//echo $message;
					
					if($this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $model->email, $name, Yii::t('site', 'Did you forget your Traceper password?'), $message))
					{
						echo CJSON::encode(array("result"=> "1", "email"=>$model->email));
					}
					else
					{
						echo CJSON::encode(array("result"=> "0"));
					}										
				}
				else
				{
					echo CJSON::encode(array("result"=> "0"));
				}

				Yii::app()->end();
			}
		}
	
		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
		
		//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
		if (Yii::app()->request->getIsAjaxRequest()) {
			Yii::app()->clientScript->scriptMap['*.js'] = false;
			Yii::app()->clientScript->scriptMap['*.css'] = false;
		}		
	
		$this->renderPartial('forgotPassword',array('model'=>$model), false, $processOutput);
	}	
	
	/**
	 * Resets the user's current password
	 */
	public function actionResetPassword()
	{
		$model = new ResetPasswordForm;
		
		$processOutput = true;
		
		$token = null;
		
		if (isset($_GET['token']) && $_GET['token'] != null)
		{
			$token = $_GET['token'];
		}			
		
		// collect user input data
		if(isset($_POST['ResetPasswordForm']))
		{
			$model->attributes = $_POST['ResetPasswordForm'];
			// validate user input and if ok return json data and end application.
			
			Fb::warn("token:".$token, "SiteController - actionResetPassword()");
			
			if($model->validate()) {
				if(Users::model()->changePassword(Users::model()->getUserId(ResetPassword::model()->getEmailByToken($token)), $model->newPassword)) // save the change to database
				{
					ResetPassword::model()->deleteToken($token);

					Yii::app()->clientScript->scriptMap['jquery.js'] = false;
					Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
					$this->renderPartial('resetPasswordSuccessful',array(), false, $processOutput);
					
					Yii::app()->end();
				}
				else
				{				
					//Fb::warn("An error occured while changing your password!", "SiteController - actionResetPassword()");
					
					echo '<script type="text/javascript">
					TRACKER.showMessageDialog("'.Yii::t('site', 'An error occured while changing your password!').'");
					</script>';					
				}								
			}
			else
			{
				//Fb::warn("model NOT valid", "SiteController - actionResetPassword()");												
			}
			
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;

			//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
			if (Yii::app()->request->getIsAjaxRequest()) {
				Yii::app()->clientScript->scriptMap['*.js'] = false;
				Yii::app()->clientScript->scriptMap['*.css'] = false;
			}			
			
			$this->renderPartial('resetPassword',array('model'=>$model, 'token'=>$token), false, $processOutput);
			
			Yii::app()->end();
		}
		else
		{
			//Fb::warn("ResetPasswordForm is NOT set", "SiteController - actionResetPassword()");
		}															
	}

// 	public function actionResetPassword2()
// 	{
// 		$result = "Sorry, you entered this page with wrong parameters";
// 		$tokenNotGiven = false;
// 		$tokenNotFound = false;
		
// 		$model = new ResetPasswordForm;
// 		$processOutput = true;
		
// 		if (isset($_GET['tok']) && $_GET['tok'] != null)
// 		{
// 			$token = $_GET['tok'];

// 			// collect user input data
// 			if(isset($_POST['ResetPasswordForm']))
// 			{
// 				$model->attributes = $_POST['ResetPasswordForm'];
// 				// validate user input and if ok return json data and end application.
				
// 				if($model->validate()) {
// // 					if(Users::model()->changePassword(Users::model()->getUserId(ResetPassword::model()->getEmailByToken($token)), $model->newPassword)) // save the change to database
// // 					{
// // 						echo CJSON::encode(array("result"=> "1")); //Password Changed
// // 						ResetPassword::model()->deleteToken($token);
// // 					}
// // 					else
// // 					{
// // 						echo CJSON::encode(array("result"=> "0")); //Password Not Chaged
// // 					}
// 					//var_dump($model->getErrors());
					
// 					echo CJSON::encode(array("result"=> "1")); //Password Changed
					
//  					Yii::app()->end();
// 				}
				
// 				//print_r($model->getErrors());
					
// 				if (Yii::app()->request->isAjaxRequest) {
// 					$processOutput = false;							
// 				}
// 			}
// 			else
// 			{
// 				if(ResetPassword::model()->tokenExists($token) == false)	
// 				{
// 					$tokenNotFound = true;
// 				}				
// 			}
// 		}
// 		else 
// 		{
// 			$tokenNotGiven = true;
// 		}
	
// 		if($tokenNotGiven)
// 		{
// 			$result = Yii::t('site', 'Sorry, you entered this page with wrong parameters...'); 
// 			$this->renderPartial('errorInPage',array('result'=>$result), false, true);
// 		}
// 		else if($tokenNotFound)
// 		{
// 			$result = Yii::t('site', 'This link is not valid anymore...');
// 			$this->renderPartial('errorInPage',array('result'=>$result), false, true);
// 		}
// 		else
// 		{
// // 			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
// // 			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;			
			
// 			$this->renderPartial('resetPassword2',array('model'=>$model), false, $processOutput);
			
// 			//$this->render('resetPassword2',array('model'=>$model), false);
// 		}
// 	}

	/**
	 * Resends an account activation link to the user's e-mail address, if he has already started registration process
	 */
	public function actionActivationNotReceived()
	{
		//Fb::warn("actionActivationNotReceived() called", "SiteController");
	
		$model = new ActivationNotReceivedForm;
	
		$processOutput = true;
		// collect user input data
		if(isset($_POST['ActivationNotReceivedForm']))
		{
			$model->attributes = $_POST['ActivationNotReceivedForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) 
			{
				$candidatePassword = null;
				$candidateName = null;
				$candidateRegistrationTime = null;
				
				UserCandidates::model()->getCandidateInfoByEmail($model->email, $candidatePassword, $candidateName, $candidateRegistrationTime);
				
				$key = md5($model->email.$candidateRegistrationTime);
				
				$message = Yii::t('site', 'Hi').' '.$candidateName.',<br/><br/>';
				$message .= Yii::t('site', 'You could activate your account by clicking');
				$message .= ' '.'<a href="'.'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">'.Yii::t('site', 'here').'</a>';
				$message .= ' '.Yii::t('site', 'or the link below:').'<br/>';
				$message .= '<a href="'.'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">';
				$message .= 'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key));
				$message .= '</a>';
				$message .= '<br/><br/>';				
				$message .= Yii::t('site', 'If you do not remember your password, you could request to generate new one.');

				//echo $message;
												
				if($this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $model->email, $candidateName, Yii::t('site', 'Traceper Activation'), $message))
				{
					echo CJSON::encode(array("result"=>"1", "email"=>$model->email));
				}
				else
				{
					echo CJSON::encode(array("result"=>"0"));
				}
	
				Yii::app()->end();
			}
		}
	
		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
		
		//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
		if (Yii::app()->request->getIsAjaxRequest()) {
			Yii::app()->clientScript->scriptMap['*.js'] = false;
			Yii::app()->clientScript->scriptMap['*.css'] = false;
		}		
	
		$this->renderPartial('activationNotReceived',array('model'=>$model), false, $processOutput);
	}	

	public function actionRegister()
	{
		$model = new RegisterForm;
		
		$processOutput = true;
		
		$mobileLang = null;
		$preferredLaguage = substr(Yii::app()->getRequest()->getPreferredLanguage(), 0, 2);
		
		if(isset($_REQUEST['language']))
		{
			$mobileLang = $_REQUEST['language'];
			$preferredLaguage = $_REQUEST['language'];;
		}
		
		// collect user input data
		if(isset($_REQUEST['RegisterForm']))
		{
			$model->attributes = $_REQUEST['RegisterForm'];
		
			// validate user input and if ok return json data and end application.
		
			// 			if (Yii::app()->request->isAjaxRequest) {
			// 				$processOutput = false;
			// 			}
		
			if($model->validate()) {
		
				$time = date('Y-m-d h:i:s');
		
				//echo $model->ac_id;
				
				$registrationMedium = null;
				
				if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
				{
					$registrationMedium = 'Mobile';
				}
				else
				{
					$registrationMedium = 'Web';
				}				
		
				if (isset($model->ac_id) && $model->ac_id != "0") {
					if (Users::model()->saveFacebookUser($model->email, md5($model->password), $model->name, $model->ac_id, $model->account_type)) {
						//echo CJSON::encode(array("result"=> "1"));
		
						if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
						{
							echo CJSON::encode(array(
									"result"=> "1",
							));
						}
						else
						{
							Yii::app()->clientScript->scriptMap['jquery.js'] = false;
							Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
							
							//Complete solution for blinks at FireFox
							if (Yii::app()->request->getIsAjaxRequest()) {
								Yii::app()->clientScript->scriptMap['*.js'] = false;
								Yii::app()->clientScript->scriptMap['*.css'] = false;
							}
														
							$this->renderPartial('register',array('model'=>$model), false, $processOutput);
							echo '<script type="text/javascript">
							TRACKER.showMessageDialog("'.Yii::t('site', 'An activation mail is sent to your e-mail address...').'");
							</script>';
						}
					}
					else {
						if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
						{
							/*
							 echo CJSON::encode(array(
							 		"result"=> "1",
							 ));
							*/
							echo JSON::encode(array("result"=>"Error in saving"));
						}
						else
						{
							Yii::app()->clientScript->scriptMap['jquery.js'] = false;
							Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
							
							//Complete solution for blinks at FireFox
							if (Yii::app()->request->getIsAjaxRequest()) {
								Yii::app()->clientScript->scriptMap['*.js'] = false;
								Yii::app()->clientScript->scriptMap['*.css'] = false;
							}
							
							$this->renderPartial('register',array('model'=>$model), false, $processOutput);
							
							echo '<script type="text/javascript">
							TRACKER.showMessageDialog("'.Yii::t('common', 'Sorry, an error occured in operation').'");
							</script>';
						}
					}
				}
				else if (UserCandidates::model()->saveUserCandidates($model->email, md5($model->password), trim($model->name).' '.trim($model->lastName), date('Y-m-d h:i:s'), $registrationMedium, $preferredLaguage))
				{
					$isTranslationRequired = false;

					if($mobileLang != null)
					{
						if($mobileLang == 'tr')
						{
							if(Yii::app()->language == 'tr')
							{
								$isTranslationRequired = false;
							}
							else
							{
								$isTranslationRequired = true;
							}
						}
						else
						{
							if(Yii::app()->language == 'tr')
							{
								$isTranslationRequired = true;
							}
							else
							{
								$isTranslationRequired = false;
							}
						}						
					}

					if($isTranslationRequired == true)
					{
						if($mobileLang == 'tr')
						{
							Yii::app()->language = 'tr';
						}
						else
						{
							Yii::app()->language = 'en';
						}
					}
					
					//Yii::app()->language = 'en';
						
					$key = md5($model->email.$time);
						
					$message = Yii::t('site', 'Hi').' '.trim($model->name).',<br/><br/>';
					
					//$message .= 'mobileLang: '.$mobileLang;
					
					$message .= Yii::t('site', 'You could activate your account by clicking');
					$message .= ' '.'<a href="'.'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">'.Yii::t('site', 'here').'</a>';
					$message .= ' '.Yii::t('site', 'or the link below:').'<br/>';
					$message .= '<a href="'.'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">';
					$message .= 'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key));
					$message .= '</a>';
					$message .= '<br/><br/>';
					$message .= Yii::t('site', 'Your Password is').':'.$model->password;
		
					//echo $message;

					if($this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $model->email, trim($model->name).' '.trim($model->lastName), Yii::t('site', 'Traceper Activation'), $message))
					{
						//echo CJSON::encode(array("result"=> "1"));
						if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
						{
							echo CJSON::encode(array(
									"result"=> "1",
							));
						}
						else
						{
							Yii::app()->clientScript->scriptMap['jquery.js'] = false;
							Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
						
							//Complete solution for blinks at FireFox
							if (Yii::app()->request->getIsAjaxRequest()) {
								Yii::app()->clientScript->scriptMap['*.js'] = false;
								Yii::app()->clientScript->scriptMap['*.css'] = false;
							}
						
							$this->renderPartial('register',array('model'=>$model), false, $processOutput);
						
							echo '<script type="text/javascript">
							TRACKER.showLongMessageDialog("'.Yii::t('site', 'Your account created successfully. ').Yii::t('site', 'We have sent an account activation link to your mail address \"<b>').$model->email.Yii::t('site', '</b>\". </br></br>Please make sure you check the spam/junk folder as well. The links in a spam/junk folder may not work sometimes; so if you face such a case, mark our e-mail as \"Not Spam\" and reclick the link.').'");
							</script>';														
						}						
					}
					else
					{
						//echo CJSON::encode(array("result"=> "1"));
						if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
						{
							echo CJSON::encode(array(
									"result"=> "2", //Account created but an error occured while sending the activation e-mail
							));
						}
						else
						{
							Yii::app()->clientScript->scriptMap['jquery.js'] = false;
							Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
						
							//Complete solution for blinks at FireFox
							if (Yii::app()->request->getIsAjaxRequest()) {
								Yii::app()->clientScript->scriptMap['*.js'] = false;
								Yii::app()->clientScript->scriptMap['*.css'] = false;
							}
						
							$this->renderPartial('register',array('model'=>$model), false, $processOutput);
						
							echo '<script type="text/javascript">
							TRACKER.showLongMessageDialog("'.Yii::t('site', 'Your account created successfully, but an error occured while sending your account activation e-mail. You could request your account activation e-mail by clicking the link \"Not Received Our Activation E-Mail?\" just below the Sign Up form. If the error persists, please contact us about the problem.').'");
							</script>';
						}						
					}

					if($isTranslationRequired == true) //Recover the language if needed for mobile
					{
						if($mobileLang == 'tr')
						{
							Yii::app()->language = 'en';
						}
						else
						{
							Yii::app()->language = 'tr';
						}
					}

					//Yii::app()->language = 'tr';	
				}
				else
				{
					//echo CJSON::encode(array("result"=> "Error in saving"));
					if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
					{
						/*
						 echo CJSON::encode(array(
						 		"result"=> "1",
						 ));
						*/
						echo JSON::encode(array("result"=>"0")); //Error in saving
					}
					else
					{
						Yii::app()->clientScript->scriptMap['jquery.js'] = false;
						Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
							
						//Complete solution for blinks at FireFox
						if (Yii::app()->request->getIsAjaxRequest()) {
							Yii::app()->clientScript->scriptMap['*.js'] = false;
							Yii::app()->clientScript->scriptMap['*.css'] = false;
						}
							
						$this->renderPartial('register',array('model'=>$model), false, $processOutput);
						
						echo '<script type="text/javascript">
						TRACKER.showMessageDialog("'.Yii::t('common', 'Sorry, an error occured in operation').'");
						</script>';
					}
				}
		
				Yii::app()->end();
			}
			else
			{
				if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
				{
					$result = "1"; //Initialize with "1" to be used whether no error occured
						
					//These kind of controls are done in mobile now
		
					// 					if ($model->getError('password') != null) {
					// 						$result = $model->getError('password');
					// 					}
					// 					else if ($model->getError('email') != null) {
					// 						$result = $model->getError('email');
					// 					}
					// 					else if ($model->getError('passwordAgain') != null) {
					// 						$result = $model->getError('passwordAgain');
					// 					}
					// 					else if ($model->getError('passwordAgain') != null) {
					// 						$result = $model->getError('passwordAgain');
					// 					}
						
					if($model->getError('email') == Yii::t('site', 'E-mail is already registered!'))
					{
						$result = "-1";
					}
					else if($model->getError('email') == Yii::t('site', 'Registration incomplete, please request activation e-mail below'))
					{
						$result = "-2";
					}
					else if($model->getError('email') != null)
					{
						$result = $model->getError('email') ;
					}
					else
					{
						$result = "-3"; //Unkown registration errror
					}
		
					echo CJSON::encode(array(
							"result"=> $result,
					));
				}
				else
				{
					//echo 'RegisterForm not valid';
					Yii::app()->clientScript->scriptMap['jquery.js'] = false;
					Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
						
					//Complete solution for blinks at FireFox
					if (Yii::app()->request->getIsAjaxRequest()) {
						Yii::app()->clientScript->scriptMap['*.js'] = false;
						Yii::app()->clientScript->scriptMap['*.css'] = false;
					}
						
					$this->renderPartial('register',array('model'=>$model), false, $processOutput);
				}
		
				Yii::app()->end();
			}
		}
		else
		{
			//echo 'RegisterForm is NOT set';
			//Even if model is not set this renderPartial is useful for language transition
			$this->renderPartial('register',array('model'=>$model), false, $processOutput);
		}
	}

	public function actionIsFacebookUserRegistered(){

		$result = "Missing parameter";
		if (isset($_REQUEST['email']) && $_REQUEST['email'] != NULL
			 && isset($_REQUEST['facebookId']) && $_REQUEST['facebookId'] != NULL)
		{
			$email = $_REQUEST['email'];
			$facebookId = $_REQUEST['facebookId'];
			$result = "0";
			if (Users::model()->isFacebookUserRegistered($email, $facebookId)){
				$result = "1";
			}
		}
		echo CJSON::encode(array(
				"result"=> $result,
		));
	}

	//facebook web register
	public function FB_Web_Register()
	{
		$result = 0;
			
		// validate user input and if ok return json data and end application.
		if(Yii::app()->session['facebook_user']) {

			if (Users::model()->saveFacebookUser(Yii::app()->session['facebook_user']['email'], md5(Yii::app()->session['facebook_user']['id']), Yii::app()->session['facebook_user']['name'], Yii::app()->session['facebook_user']['id'], 1))
			{
				$result = 1;
			}
			else
			{
				$result = 0;
			}

		}
		return $result;
	}


	public function actionRegisterGPSTracker()
	{
		$model = new RegisterGPSTrackerForm;

		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterGPSTrackerForm']))
		{
			$isMobileClient = false;
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
			}
			$model->attributes = $_POST['RegisterGPSTrackerForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				//Check whether a device exists with the same name in the Users table (Since the table 'Users' is used as common for both
				//real users and devices we cannot add unique index for realname, so we have to check same name existance manually)
				if(Users::model()->find('userType=:userType AND realname=:name', array(':userType'=>UserType::GPSDevice, ':name'=>$model->name)) == null)
				{
					try
					{
						if (Users::model()->saveGPSUser($model->deviceId, md5($model->name), $model->name, UserType::GPSDevice, 0))
						{

							if(Friends::model()->makeFriends(Yii::app()->user->id, Users::model()->getUserId($model->deviceId)))
							{
								echo CJSON::encode(array("result"=> "1"));
							}
							else
							{
								echo CJSON::encode(array("result"=> "Unknown error 1"));
							}
						}
						else
						{
							echo CJSON::encode(array("result"=> "Unknown error 2"));
						}
					}
					catch (Exception $e)
					{
						if($e->getCode() == Yii::app()->params->duplicateEntryDbExceptionCode) //Duplicate Entry
						{
							echo CJSON::encode(array("result"=> "Duplicate Entry"));
						}
						else
						{
							echo 'Caught exception: ',  $e->getMessage(), "\n";
							echo 'Code: ', $e->getCode(), "\n";
						}
						Yii::app()->end();							
					}
				}
				else
				{
					echo CJSON::encode(array("result"=> "Duplicate Name"));
				}

				Yii::app()->end();
			}

// 			if (Yii::app()->request->isAjaxRequest) {
// 				$processOutput = false;
// 			}
		}

		if ($isMobileClient == true)
		{
			$result = "1"; //Initialize with "1" to be used whether no error occured

			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}

			echo CJSON::encode(array(
					"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
			
			//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
			if (Yii::app()->request->getIsAjaxRequest()) {
				Yii::app()->clientScript->scriptMap['*.js'] = false;
				Yii::app()->clientScript->scriptMap['*.css'] = false;
			}
						
			$this->renderPartial('registerGPSTracker',array('model'=>$model), false, $processOutput);
		}

	}

	public function actionRegisterNewStaff()
	{
		$model = new RegisterNewStaffForm;

		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterNewStaffForm']))
		{
			$isMobileClient = false;
			$registrationMedium = null;
			
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
				$registrationMedium = 'Mobile';
			}
			else
			{
				$registrationMedium = 'Web';
			}
			
			$model->attributes = $_POST['RegisterNewStaffForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				try
				{
					if(Users::model()->saveUser($model->email, md5($model->password), $model->name, UserType::RealStaff/*userType*/, 0/*accountType*/, $registrationMedium))
					{
						if(Friends::model()->makeFriends(Yii::app()->user->id, Users::model()->getUserId($model->email)))
						{
							echo CJSON::encode(array("result"=> "1"));
						}
						else
						{
							echo CJSON::encode(array("result"=> "Unknown error 1"));
						}
					}
					else
					{
						echo CJSON::encode(array("result"=> "Unknown error 2"));
					}
				}
				catch (Exception $e)
				{
					if($e->getCode() == Yii::app()->params->duplicateEntryDbExceptionCode) //Duplicate Entry
					{
						echo CJSON::encode(array("result"=> "Duplicate Entry"));
					}
					Yii::app()->end();
				}

				Yii::app()->end();
			}
		}

		if ($isMobileClient == true)
		{
			$result = "1"; //Initialize with "1" to be used whether no error occured

			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}

			echo CJSON::encode(array(
					"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
			
			//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
			if (Yii::app()->request->getIsAjaxRequest()) {
				Yii::app()->clientScript->scriptMap['*.js'] = false;
				Yii::app()->clientScript->scriptMap['*.css'] = false;
			}
						
			$this->renderPartial('registerNewStaff',array('model'=>$model), false, $processOutput);
		}

	}

	public function actionInviteUsers()
	{
		$model = new InviteUsersForm;

		$processOutput = true;
		// collect user input data
		if(isset($_POST['InviteUsersForm']))
		{
			$model->attributes = $_POST['InviteUsersForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				$emailArray= $this->splitEmails($model->emails);
				$duplicateEmails = array();
				$arrayLength = count($emailArray);
				$invitationSentCount = 0;
				for ($i = 0; $i < $arrayLength; $i++)
				{					
					 $dt = date("Y-m-d H:m:s");

					try
					{
						if(InvitedUsers::model()->saveInvitedUsers($emailArray[$i], $dt))
						{
							$key = md5($emailArray[$i].$dt);
							//send invitation mail
							$invitationSentCount++;
						
							$message = Yii::t('site', 'Hi').',<br/>'.Yii::t('site', 'You have been invited to traceper by one of your friends').'. '.Yii::t('site', 'Your friend\'s message:').'<br/><br/>';
							$message .= $model->invitationMessage;
							$message .= '<br/><br/>';

							$message .= '<a href="'.'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'">';
							
							$message .= Yii::t('site', 'Click here to register to traceper');
							$message .= '</a>';
							
							//echo $message;
							$this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $emailArray[$i], '', Yii::t('site', 'Traceper Invitation'), $message);
						}						
					} 
					catch (Exception $e)
					{
						if($e->getCode() == Yii::app()->params->duplicateEntryDbExceptionCode) //Duplicate Entry
						{
							//echo CJSON::encode(array("result"=> "Duplicate Entry"));
							$duplicateEmails[] = $emailArray[$i];
						}
						else
						{
							echo 'Caught exception: ',  $e->getMessage(), "\n";
							echo 'Code: ', $e->getCode(), "\n";
						}
						//Yii::app()->end();
					}
				}

				if ($arrayLength == $invitationSentCount) // save the change to database
				{
					echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					//echo CJSON::encode(array("result"=> "0"));
					echo CJSON::encode(array("result"=>"Duplicate Entry", "emails"=>$duplicateEmails));
				}
				Yii::app()->end();
			}
		}

		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
		
		//Yukar�dakiler kullan�l�nca az da olsa sorun oluyor, bunu koyunca hi� sorun olmuyor
		if (Yii::app()->request->getIsAjaxRequest()) {
			Yii::app()->clientScript->scriptMap['*.js'] = false;
			Yii::app()->clientScript->scriptMap['*.css'] = false;
		}		

		$this->renderPartial('inviteUsers',array('model'=>$model), false, $processOutput);
	}

	private function splitEmails($emails)
	{
		$emails = str_replace(array(" ",",","\r","\n"),array(";",";",";",";"),$emails);
		$emails = str_replace(";;", ";",$emails);
		$emails = explode(";", $emails);
		return $emails;
	}

	public function actionActivate()
	{
		$result = "Sorry, you entered this page with wrong parameters";
		if (isset($_GET['email']) && $_GET['email'] != null
				&& isset($_GET['key']) && $_GET['key'] != null
		)
		{
			$email = $_GET['email'];
			$key = $_GET['key'];

			$processOutput = true;
			// collect user input data

			$criteria=new CDbCriteria;
			$criteria->select='Id,email,realname,password,time,registrationMedium,preferredLanguage';
			$criteria->condition='email=:email';
			$criteria->params=array(':email'=>$email);
			$userCandidate = UserCandidates::model()->find($criteria); // $params is not needed
			
			if($userCandidate != null)
			{
				$generatedKey =  md5($email.$userCandidate->time);
					
				if ($generatedKey == $key)
				{
					$result = "Sorry, there is a problem in activating the user";
					if(Users::model()->saveUser($userCandidate->email, $userCandidate->password, $userCandidate->realname, UserType::RealUser/*userType*/, 0/*accountType*/, $userCandidate->registrationMedium, $userCandidate->preferredLanguage))
					{
						$userCandidate->delete();
						$result = Yii::t('site', 'Your account has been activated successfully, you can login now');
						//echo CJSON::encode(array("result"=> "1"));
					}
				}
				else 
				{
					$result = Yii::t('site', 'There has been a problem with your registration process. Please try to register to Traceper again.');
				}				
			}
			else
			{
				if(Users::model()->isUserRegistered($email))
				{
					$result = Yii::t('site', 'You have already registered to Traceper, so you can login now. If you forgot your password, you can request to generate a new one.');
				}
				else 
				{
					$result = Yii::t('site', 'There has been a problem with your registration process. Please try to register to Traceper again.');
				}
			}
		}

		$this->renderPartial('messageDialog', array('result'=>$result, 'title'=>Yii::t('site', 'Account Activation')), false, true);	
	}
	
	public function actionChangeLanguage()
	{
		$app = Yii::app();
		
		if (isset($_GET['lang'])  && ($_GET['lang'] != null))
		{
			$app->language = $_GET['lang'];
			$app->session['_lang'] = $_GET['lang'];
		}
	}

	/**
	 * Displays About Us Info
	 */
	public function actionAboutUs()
	{
		$processOutput = true;
	
		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
	
		$this->renderPartial('aboutUs',array(), false, $processOutput);
	}

	/**
	 * Displays Terms Info
	 */
	public function actionTerms()
	{
		$processOutput = true;
	
		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
	
		$this->renderPartial('terms',array(), false, $processOutput);
	}

// 	public function sendUserDemands(){
// 		if (isset($_REQUEST['email']) && $_REQUEST['email'] != NULL
// 				&& isset($_REQUEST['demandType']) && $_REQUEST['demandType'] != NULL)
// 		{
// 			$email = $_REQUEST['email'];
// 			$demandType = $_REQUEST['demandType'];
			
// 			if($demandType == 0) //Forgot Password
// 			{
// 				$token = sha1(uniqid(mt_rand(), true));
			
// 				if(Users::model()->isUserRegistered($email))
// 				{
// 					ResetPassword::model()->saveToken($email, $token);
// 					$name = Users::model()->getNameByEmail($email);
						
// 					$message = Yii::t('site', 'Hi').' '.$name.',<br/><br/>';
						
// 					$message .= Yii::t('site', 'If you forgot your password, you can create a new password by clicking');
// 					$message .= ' '.'<a href="'.'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'/index.php?tok='.$token.'">'.Yii::t('site', 'here').'</a>';
// 					$message .= ' '.Yii::t('site', 'or the link below:').'<br/>';
// 					$message .= '<a href="'.'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'/index.php?tok='.$token.'">';
// 					$message .= 'http://'.Yii::app()->request->getServerName().Yii::app()->request->getBaseUrl().'/index.php?tok='.$token;
// 					$message .= '</a>';
// 					$message .= '<br/><br/><br/>';
// 					$message .= Yii::t('site', 'If you did not attempt to create a new password, take no action and please inform <a href="mailto:contact@traceper.com">us</a>.');
			
// 					//echo $message;
						
// 					if($this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $email, $name, Yii::t('site', 'Did you forget your Traceper password?'), $message))
// 					{
// 						echo CJSON::encode(array("result"=> "1", "email"=>$email));
// 					}
// 					else
// 					{
// 						$result = "-4"; //An error occured while sending the mail
// 					}
// 				}
// 				else
// 				{
// 					$result = "-3"; //This e-mail is not registered
// 				}				
// 			}
// 			else if($demandType == 1) //Activation E-mail
// 			{
// 				$candidatePassword = null;
// 				$candidateName = null;
// 				$candidateRegistrationTime = null;
			
// 				UserCandidates::model()->getCandidateInfoByEmail($email, $candidatePassword, $candidateName, $candidateRegistrationTime);
			
// 				$key = md5($model->email.$candidateRegistrationTime);
			
// 				$message = Yii::t('site', 'Hi').' '.$candidateName.',<br/><br/>';
// 				$message .= Yii::t('site', 'You could activate your account by clicking');
// 				$message .= ' '.'<a href="'.'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">'.Yii::t('site', 'here').'</a>';
// 				$message .= ' '.Yii::t('site', 'or the link below:').'<br/>';
// 				$message .= '<a href="'.'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">';
// 				$message .= 'http://'.Yii::app()->request->getServerName().$this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key));
// 				$message .= '</a>';
// 				$message .= '<br/><br/>';
// 				$message .= Yii::t('site', 'If you do not remember your password, you could request to generate new one.');
			
// 				//echo $message;
			
// 				if($this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $model->email, $candidateName, Yii::t('site', 'Traceper Activation'), $message))
// 				{
// 					echo CJSON::encode(array("result"=>"1", "email"=>$model->email));
// 				}
// 				else
// 				{
// 					echo CJSON::encode(array("result"=>"0"));
// 				}
			
// 				Yii::app()->end();								
// 			}
// 			else //Unknown Demand
// 			{
// 				$result = "-2"; //Unknown demand type
// 			}
				
			
// 			$result = "0";
// 			if (Users::model()->isFacebookUserRegistered($email, $facebookId)){
// 				$result = "1";
// 			}
// 		}
// 		else
// 		{
// 			$result = "-1"; //Missing parameter
// 		}
		
// 		echo CJSON::encode(array("result" => $result));
// 	}	
}



