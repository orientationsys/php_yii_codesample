<?php

class UserController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/column2';

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('goals', 'profile', 'register', 'feeds'),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('update', 'followers', 'following', 'account', 'stats', 'passwordNew', 'findFriends', 'contact', 'checkFbUsername', 'messages', 'findfreinds' ,'deleteavatar', 'twitterinvite', 'RecommendFriends'),
				'users'=>array('@'),
			),
			array('allow', // allow admin user to perform 'admin' and 'delete' actions
				'actions'=>array('delete'),
				'users'=>array('alan@finance.co.jp', 'nick@nickramsay.com'),
			),			
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * Profile for User.
	 */
	public function actionProfile($username = '')
	{
		$user = $this->getUserByUserName($username);
		$model = $user->profile;
		
		if ($model->owner)
		{
		    if(isset($_POST['Profile']))
		    {
			    $model->attributes = $_POST['Profile'];

			    if($model->validate())
			    {
				    $model->FirstName = Tools::clean($_POST['Profile']['FirstName']);
				    $model->LastName = $_POST['Profile']['LastName'];
				    $model->Bio = $_POST['Profile']['Bio'];
				    $model->City = $_POST['Profile']['City'];
				    $model->Website = $_POST['Profile']['Website'];
				    $model->Country = $_POST['Profile']['Country'];
				    if ($model->save())
					Yii::app()->user->setFlash('profile',"Profile was saved successfully.");
			    }
		    }
		
			$this->render('views/_profile',array(
				'model'=>$model,
		    ));
		} else {
		    $this->render('profile',array(
			'model'=>$model,
		    ));
		}
		
	}
	
	public function actionDeleteAvatar($username = '')
	{
		$user = $this->getUserByUserName($username);
		$model = $user->profile;
		$imagemodel = new ImageUpload;
		
		if(is_dir(Yii::app()->basePath . '/../images/User/Avatar/' . $model->UserID))
		{
			
			$this->clearDir($model->UserID);
			
			$model->AvatarURL = NULL;
			$model->save();
			$user->userSetting->avatarSelection = 0;
			$user->userSetting->save();
		}
		
		$this->redirect('account?option=avatars',array(
			'model'=>$model,
			'imagemodel'=>$imagemodel
		));
	}


	public function actionFindFriends($username = '')
	{
		$user = $this->getUserByUserName($username);

		if (!$user->owner)
			$this->redirect(array('user/goals', 'username'=>$username));

		else
		{
			$jquerypath = Yii::app()->assetManager->publish(Yii::app()->basePath.'/scripts/');
			Yii::app()->clientScript->registerScriptFile($jquerypath.'/follow.js');
			
			require_once('protected/components/twitteroauth.php');
			$twitteroauth = new TwitterOAuth(Yii::app()->params->twitterConsumerKey, Yii::app()->params->twitterConsumerSecret, $user->twitter_oauth_token, $user->twitter_oauth_secret);
			$twitterdata = $twitteroauth->get('statuses/friends');

			$my_url = $this->createAbsoluteUrl('user/findfriends', array('username'=> $user->UserName));
			$access_token = Tools::facebookAccessToken($my_url);
			
			$graph_url = "https://graph.facebook.com/" . $user->FacebookID . '/friends?' . $access_token ;
			$facebookdata = json_decode(file_get_contents($graph_url));
			
			$twitteridlist = '';
			if(!$twitterdata->error)
			{
				foreach($twitterdata as $data)
				{
					//$twitteridlist .= '"' $data->id_str . '",';	
					$twitteridlist .= '"' . $data->name . '",';
				}
			}
			
			$facebookidlist = '';
			if(!$facebookdata->error)
			{
				foreach($facebookdata->data as $data)
				{
					$facebookidlist .= '"' . $data->id . '",';	
				}
			}
			
			//$twitteridlist = '"gtoeaslt","goalcheckr",';
			//$facebookidlist = '';
			
			$facebookfriends = array();
			$facebookinvite = array();
			$twitterfriends = array();
			$twitterinvite = array();
			
			if($twitteridlist != '' || $facebookidlist != '')
			{
				if($twitteridlist != '')
				{
					$twitteridlist = substr($twitteridlist, 0, -1); 
					$twittersql = "twitter_username IN (" . $twitteridlist . ")";
				}
				
				if($facebookidlist != '')
				{
					$facebookidlist = substr($facebookidlist, 0, -1); 
					$facebooksql = "FaceBookID IN (" . $facebookidlist . ")";
				}
				
				if($twitteridlist != '' && $facebookidlist != '')
				{
					$sql = $twittersql . ' OR ' . $facebooksql;
				}
				else
				{
					$sql = ($twitteridlist != '')?$twittersql:$facebooksql;
				}
				
				$userfriend = User::model()->findAll($sql);
				
				$facebooklist = CHtml::listData($userfriend, 'UserID', 'FacebookID');
				$twitterlist = CHtml::listData($userfriend, 'UserID', 'twitter_username');
				
				foreach($facebookdata->data as $data)
				{
					if(in_array($data->id, $facebooklist))
					{
						$facebookfriends[] = $data;
					}
					else
					{
						$facebookinvite[] = $data;
					}
				}
				
				foreach($twitterdata as $data)
				{
					if(in_array($data->name, $twitterlist))
					{
						$twitterfriends[] = $data;
					}
					else
					{
						$twitterinvite[] = $data;
					}
				}
			}
			
			$facebookFriendsOnGoalCheckr = new CArrayDataProvider($facebookfriends);
			$facebookinviteList = new CArrayDataProvider($facebookinvite);
			
			$twitterFriendsOnGoalCheckr = new CArrayDataProvider($twitterfriends);
			$twitterinviteList = new CArrayDataProvider($twitterinvite);

			$this->render('findFriends', array(
				'facebookFriendsOnGoalCheckr' => $facebookFriendsOnGoalCheckr,
				'facebookinviteList' => $facebookinviteList,
				'twitterFriendsOnGoalCheckr' => $twitterFriendsOnGoalCheckr,
				'twitterinviteList' => $twitterinviteList,
				'access_token' => $access_token,
				'user' => $user,
			));
		}
	}
	
	public function actionRecommendFriends($username = '')
	{
		$sql = 'SELECT *
			FROM User
			WHERE User.UserID NOT
			IN (
			SELECT Follow.FollowingID
			FROM Follow
			WHERE Follow.FollowerID = ' . intval(Yii::app()->user->id) . '
			)
			AND User.UserID <> ' . intval(Yii::app()->user->id) . '
			ORDER BY RAND()';
		
		$dataProvider=new CSqlDataProvider($sql, array(
			'pagination'=>array(
				'pageSize'=>5,
			),
		));
		
		$userArray = $dataProvider->getData();
		
		$criteria=new CDbCriteria(array(
			'order'=>'LastLogin DESC',
		));
		
		if($userArray)
		{	   
			foreach($userArray as $key=> $user)
			{
				$str .= $user['UserID'] . ' ';
				$criteria->addColumnCondition(array('UserID'=>$user['UserID']), 'AND', 'OR');
			}		   
		} else {
			$criteria->addCondition('1=0');
		}
		
		$dataProvider=new CActiveDataProvider('User', array(
			'criteria'=>$criteria,
			'pagination'=>array(
				'pageSize'=>5,
			),
		));
		
		$this->render('recommendfriends',array(
		    'dataProvider' => $dataProvider,
		));
	}
	
	public function actionTwitterInvite()
	{
		if($_POST)
		{
			$user = User::model()->findByPK(Yii::app()->user->id);
			
			require_once('protected/components/twitteroauth.php');
			$twitteroauth = new TwitterOAuth(Yii::app()->params->twitterConsumerKey, Yii::app()->params->twitterConsumerSecret, $user->twitter_oauth_token, $user->twitter_oauth_secret);
			
			$text = 'How about starting a goal? ' . Yii::app()->createAbsoluteUrl('');
			
			$result = $twitteroauth->post('direct_messages/new', array('user_id' => $_POST['twitterid'], 'screen_name' => $_POST['twittername'], 'include_entities' => '1', 'text' => $text));
			
			if(!$result->error){echo 1;}else{echo $result->error;}
			
		}
	}

	/**
	 * Manages all models.
	 */
	public function actionAccount($username = '')
	{
		// set submenu view to call
		// would like to handle each of these as separate fucntions but could not get it to work
		if(isset($_GET['option'])) $option = Tools::clean($_GET['option']); else $option = 'account';
		
		if ($option == 'password' || $option == 'username') {
		    $model = User::model()->findByPk(Yii::app()->user->id);
		    $user = $model;
		} 
		else if ($option == 'avatars') {
			$model = UserSetting::model()->findByPk(Yii::app()->user->id);
		    $user = $model->user;
			$imagemodel = new ImageUpload;
		}
		else {
		    $model = UserSetting::model()->findByPk(Yii::app()->user->id);
		    $user = $model->user;
		}

		if ($user->UserName == $username || $username == 'new')
		{
		    if(isset($_POST['UserSetting']))
		    { 
			    $model->attributes =  $_POST['UserSetting'];
			    // should not need to sepecify below, but it wont save without for some reason
			    $model->notifyMyNewGoals =  $_POST['UserSetting']['notifyMyNewGoals'];
			    $model->notifyCommentsOnMyGoals =  $_POST['UserSetting']['notifyCommentsOnMyGoals'];
			    $model->notifyCommentsOnFriendsGoals =  $_POST['UserSetting']['notifyCommentsOnFriendsGoals'];
			    $model->notifyCommentsOnGoalsWithMyComments =  $_POST['UserSetting']['notifyCommentsOnGoalsWithMyComments'];
			    $model->notifyFriendsNewGoals =  $_POST['UserSetting']['notifyFriendsNewGoals'];
			    $model->notifyFriendsProgressUpdates =  $_POST['UserSetting']['notifyFriendsProgressUpdates'];
			    $model->notifyFriendsFinishGoals =  $_POST['UserSetting']['notifyFriendsFinishGoals'];
			    $model->notifyFollowAdded =  $_POST['UserSetting']['notifyFollowAdded'];
			    $model->notifyFollowRequest =  $_POST['UserSetting']['notifyFollowRequest'];
			    $model->notifyMyFollowRequestApproved =  $_POST['UserSetting']['notifyMyFollowRequestApproved'];
			    if (isset($_POST['UserSetting']['notifyAdminNewUser'])) $model->notifyAdminNewUser =  $_POST['UserSetting']['notifyAdminNewUser'];

			    if($model->save()) {				
				    Yii::app()->user->setFlash('account','Your Settings have been updated');
				    $this->refresh();
			    } else {
				    Yii::app()->user->setFlash('account','There was an error updating your settings');
				    $this->refresh();
			    }
		    }

		    if(isset($_POST['User']))
		    { 
			    $model = User::model()->findByPk(Yii::app()->user->id);
			    $model->attributes =  $_POST['User'];
			    switch ($option)
			    {
				case 'password':
				    $model->setScenario('changePassword');
				    if ($model->validate())
				    {
					$model->Password = $model->newPassword;
					if($model->save()) {
					    Yii::app()->user->setFlash('account','Your password has been updated');
					    $this->refresh();
					} else {
					    Yii::app()->user->setFlash('account','There was an error updating your password');
					    $this->refresh();
					}
				    }
				    break;

				case 'username':
				    $model->setScenario('newUsername');
				    if ($model->validate())
				    {					
					if($model->save()) {
					    Yii::app()->user->setFlash('account','Your new username has been saved');
					    $this->redirect(array('user/account', 'username'=>$model->UserName, 'option'=>$option));
					} else {
					    Yii::app()->user->setFlash('account','There was an error saving your username');
					    $this->refresh();
					}
				    }
				    break;
			    }
		    }
			
			if(isset($_POST['ImageUpload']))
			{
				$imagemodel->attributes=$_POST['ImageUpload'];
				$imagemodel->image=CUploadedFile::getInstance($imagemodel,'image');
				if($imagemodel->validate())
				{
					if(!is_dir(Yii::app()->basePath . '/../images/User/Avatar/' . $model->UserID))
					{
						mkdir(Yii::app()->basePath . '/../images/User/Avatar/' . $model->UserID);
					}
					$this->clearDir($model->UserID);
					$imagemodel->image->saveAs(Yii::app()->basePath . '/../images/User/Avatar/' . $model->UserID . "/" . $imagemodel->image);
					$model->profile->AvatarURL = Yii::app()->request->baseUrl . '/images/User/Avatar/' . $model->UserID . "/" . $imagemodel->image;
					$model->profile->save();
				}
			}

		    if ($username == 'new') {
			// get fblink and work out what username they might want }
		    }
 
		    $this->render('account',array(
			    'option'=>$option,
			    'model'=>$model,
			    'imagemodel'=>$imagemodel,
		    ));
		} else {
		    // if you are not the logged in user for this account then redirect to the profile page for user		   
			$this->redirect('profile', array('username'=>$username));
		}
	}

	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
		$model=new User('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['User']))
			$model->attributes = Tools::clean($_GET['User'], 'tags');

		$this->render('admin',array(
			'model'=>$model,
		));
	}	


	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer the ID of the model to be loaded
	 */
	public function loadModel($id)
	{
		$model=User::model()->findByPk((int)$id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer the ID of the model to be loaded
	 */
	public static function getUserByUserName($username)
	{
		$model=User::model()->find('UserName=:userName', array(':userName'=>Tools::clean($username)));
		if($model===null)
			throw new CHttpException(404,'We could not find a user with that name.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param CModel the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='user-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
	
	public function clearDir($ID)
	{
		$path = Yii::app()->basePath . '/../images/User/Avatar/' . $ID . '/';
		$dp = opendir($path);
		while($file=readdir($dp))
		{
			if($file!='.'&&$file!='..')
			{
				unlink($path . $file);
			}
		}
	}
}
