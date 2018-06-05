<?php

namespace yrc\forms;

use Base32\Base32;
use Yii;
use yrc\models\redis\Code;

/**
 * @class Registration
 * Registration form
 */
abstract class Registration extends \yii\base\Model
{
    const ACTIVATION_TOKEN_TIME = '+2 days';

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
            $user = new Yii::$app->user->identityClass;
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
            $user = new Yii::$app->user->identityClass;
            
            $user->attributes = [
                'email'             => $this->email,
                'username'          => $this->username,
                'password'          => $this->password,
                'otp_enabled'       => 0,
                'otp_secret'        => '',
            ];
        
            if ($user->save()) {                
                $token = \str_replace('=', '', Base32::encode(\random_bytes(64)));
        
                $code = new Code;
                $code->hash = hash('sha256', $token . '_activate');
                $code->user_id = $user->id;
                $code->type = 'activate_email';
                $code->attributes = [
                    'token' => $token,
                    'email' => $user->email
                ];
                $code->expires_at = strtotime(static::ACTIVATION_TOKEN_TIME);

                $job = Yii::$app->rpq->getQueue()->push(
                    '\yrc\jobs\notifications\email\ActivationNotification',
                    [
                        'email' => $user->email,
                        'token' => $token,
                        'user_id' => $user->id
                    ],
                    true
                );
    
                Yii::info([
                    'message' => '[Email] Account activation notification scheduled',
                    'user_id' => $user->id,
                    'data' => [
                        'email' => $user->email
                    ],
                    'job_id' => $job->getId()
                ], 'yrc/forms/Registration:register');    

                return true;
            }
        }

        return false;
    }
}
