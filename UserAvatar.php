<?php

class UserAvatar extends CWidget
{
	public $model = array();
	public $size = 50;
	public $type;  // normally we dont want to set the type as we rely upon the users avatarSelection in the db, but if required we can override and set manually
	public $link = true; // set the link url to the users page
	public $output = true;
	public $getUrl = false;

	public function init()
        {
                //parent::init();
        }


	public function run()
	{
	    
		if (!$this->model) {
		    $user = User::model()->findByPk(Yii::app()->user->id)->with('userSetting');
		    $profile = $user->profile;
		    $userName = $user->UserName;
		    $siteurl = $profile->AvatarURL;
		    $gravatarEmail = $user->EmailAddress;
		    $fbId = $user->FacebookID;
		    if (!$this->type) $this->type = $user->userSetting->avatarSelection;
		    $twitterUserName = $user->twitter_username;
		} else {
			if (!$this->type) $this->type = $this->model->userSetting->avatarSelection;
		    $userName = $this->model->UserName;
		    $profile = $this->model->profile;
		    $siteurl = $profile->AvatarURL;
		    $gravatarEmail = $this->model->EmailAddress;
		    $fbId = $this->model->FacebookID;
		    $twitterUserName = $this->model->twitter_username;
		}

		switch ($this->type) {
			case UserSetting::AVATAR_SITE :
				if ($this->size>50) $imagetype = '?type=large'; else $imagetype ='';
			    $image = CHtml::image($siteurl, 'avatar', array('width'=>$this->size, 'title'=>  ucfirst($this->model->UserName)));
			    if (!$this->link) $output = $image; else $output = CHtml::link($image, array('user/goals', 'username'=>$userName));
			    if ($this->getUrl) $output = Yii::app()->request->hostInfo . $siteurl;
			    break;
		    case UserSetting::AVATAR_GRAVATAR :
			    $output = $this->widget('ext.VGGravatarWidget.VGGravatarWidget',
				    array(
					    'email' => $gravatarEmail, // email to display the gravatar belonging to it
					    'hashed' => false, // if the email provided above is already md5 hashed then set this property to true, defaults to false
					    'size' => $this->size, // the gravatar icon size in px defaults to 40
					    'rating' => 'PG', // the Gravatar ratings, Can be G, PG, R, X, Defaults to G
					    'htmlOptions' => array( 'alt' => 'User Avatar', 'title'=>  ucfirst($this->model->UserName)), // Html options that will be appended to the image tag
					    'linkUrl'=> array('user/goals', 'username'=>$userName), // link Url to wrap the image in
					    'output'=> false
				    ))->output;
			    if ($this->getUrl) $output = '';
			    break;

			case UserSetting::AVATAR_FACEBOOK :
			    if ($this->size>50) $imagetype = '?type=large'; else $imagetype ='';
			    $facebookUrl = 'http://graph.facebook.com/' . $fbId . '/picture' . $imagetype;
			    $image = CHtml::image($facebookUrl, 'avatar', array('width'=>$this->size, 'title'=>  ucfirst($this->model->UserName)));
			    if (!$this->link) $output = $image; else $output = CHtml::link($image, array('user/goals', 'username'=>$userName));
			    if ($this->getUrl) $output = $facebookUrl;
			    break;

			case UserSetting::AVATAR_TWITTER :
			     $twitterUrl = 'http://api.twitter.com/1/users/profile_image/' . $twitterUserName;
			     $image = CHtml::image($twitterUrl, 'avatar', array('width'=>$this->size, 'title'=>  ucfirst($this->model->UserName)));
			     if (!$this->link) $output = $image; $output = CHtml::link($image, array('user/goals', 'username'=>$this->model->UserName));
			     if ($this->getUrl) $output = $twitterUrl;
			    break;
		}


		if (!$this->output) {
		    $this->output = $output;
		} else {
			echo "<span class='grid_avatar'>" . $output . "</span>";
		}
	}

}

?>