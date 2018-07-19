<?php

namespace yrc\web;

use yii\web\User as YiiUser;

class User extends YiiUser
{
    /**
     * Overloads \yii\web\User::loginByAccessToken
     * to provide the token used to authenticate the user
     * @param app\models\Token $token
     * @param mixed $type
     * @return Identity
     */
    public function loginByAccessToken($token, $type = null)
    {
        $identity = parent::loginByAccessToken($token, $type);
        
        if ($identity !== null) {
            $identity->setToken($token);
        }

        return $identity;
    }
}
