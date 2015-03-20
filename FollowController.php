<?php

class FollowController extends Controller
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
				'actions'=>array('index'),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('updatefollow'),
				'users'=>array('@'),
			),			
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}	

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$dataProvider=new CActiveDataProvider('Follow');
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
		));
	}	

	
	public function actionUpdateFollow($id, $return = false)
	{
//		if($_POST)
//		{
			$userId = Yii::app()->user->id;
			$followingUserId = $id;
			if (isset($_GET['status'])) $status =  $_GET['status'];
			if (isset($_POST['status'])) $status = $_POST['status'];
			
			if($status == Follow::STATUS_REMOVE)
			{ 
				$currentFollow = Follow::model()->findByAttributes(array('FollowerID'=>$userId, 'FollowingID'=>$followingUserId));
				if ($currentFollow)
				    $result = $currentFollow->delete();
			}

			if($status ==  Follow::STATUS_REQUEST)
			{ 
				$currentFollow = Follow::model()->findByAttributes(array('FollowerID'=>$userId, 'FollowingID'=>$followingUserId));
				if(!$currentFollow)
				{
					$user = UserSetting::model()->findByPk($followingUserId);
					$newFollow = new Follow;
					//$model->attributes=$_POST['Follow'];
					$newFollow->FollowerID = $userId;
					$newFollow->FollowingID = $followingUserId;
					if ($user->followAutoAccept)
					    $newFollow->Status =  Follow::STATUS_ACCEPTED;
					else
					     $newFollow->Status =  Follow::STATUS_REQUEST;
					$result = $newFollow->save();
				}
			}

			if($status ==  Follow::STATUS_ACCEPTED || $status ==  Follow::STATUS_DECLINED || $status ==  Follow::STATUS_IGNORED)
			{
				// Notice the Following / Follower paths are crossed here, because these actions are reactive to a user rather than us acting first
				$currentFollow = Follow::model()->findByAttributes(array('FollowingID'=>$userId, 'FollowerID'=>$followingUserId));
				$currentFollow->Status =$status;
				$result = $currentFollow->save();
			}

			if ($return)
			    $this->redirect (CHttpRequest::getUrlReferrer());
			else
			    echo $result;
//		}
	}
	

	/**
	 * Performs the AJAX validation.
	 * @param CModel the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='like-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}
