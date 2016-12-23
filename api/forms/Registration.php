<?php

namespace yrc\api\forms;

use Base32\Base32;
use Yii;

/**
 * @class Registration
 * Registration form
 */
abstract class Registration extends \yii\base\Model
{
    /**
     * The email
     * @var string $email
     */
    public $email;

    /**
     * The username
     * @var string $username
     */
    public $username;

    /**
     * The password
     * @var string $password
     */
    public $password;

    /**
     * The password repeated
     * @var string $password_verify
     */
    public $password_verify;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['email', 'password', 'password_verify', 'username'], 'required'],
            [['email'], 'email'],
            [['email'], 'verifyUsernameOrEmail'],
            [['username'], 'verifyUsernameOrEmail'],
            [['password', 'password_verify'], 'string', 'length' => [8, 100]],
            [['username'], 'string', 'length' => [1, 100]],
            [['password_verify'], 'compare', 'compareAttribute' => 'password']
        ];
    }

    /**
     * Verifies the username
     * @param string $attribute
     * @param array $params
     */
    public function verifyUsernameOrEmail($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = new Yii::$app->yrc->userClass;
            $user->$attribute = $this->$attribute;

            $user->validate([$attribute]);

            if ($user->hasErrors($attribute)) {
                $this->addError($attribute, $user->getErrors($attribute));
            }
        }
    }

    /**
     * Registers a new user
     * @return boolean
     */
    public function register()
    {
        if ($this->validate()) {
            $user = new Yii::$app->yrc->userClass;
            
            $user->attributes = [
                'email'             => $this->email,
                'username'          => $this->username,
                'password'          => $this->password,
                'otp_enabled'       => 0,
                'otp_secret'        => '',
            ];
        
            if ($user->save()) {
                // Create an activation token for the user, and store it in the cache
                $token = Base32::encode(\random_bytes(64));
                
                Yii::$app->cache->set(hash('sha256', $token . '_activation_token'), [
                    'id' => $user->id
                ]);

                Yii::$app->queue->addJob([
                    'class' => '\yrc\events\SendEmailEvent',
                    'viewFile' => 'activate',
                    'subject' => Yii::t('app', 'Activate your account'),
                    'destination' => $this->email,
                    'locales' => Yii::$app->request->getAcceptableLanguages(),
                    'viewParams' => [
                        'token' => $token
                    ]
                ]);

                return true;
            }
        }

        return false;
    }
}
