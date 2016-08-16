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
            [['email', 'password', 'password_verify'], 'required'],
            [['email'], 'string', 'min' => 4],
            [['password', 'password_verify'], 'string', 'min' => 8],
            [['password_verify'], 'compare', 'compareAttribute' => 'password']
        ];
    }

    /**
     * Registers a new user
     * @return boolean
     */
    public function register()
    {
        if ($this->validate()) {
            $config = require  Yii::getAlias('@app') . '/config/loader.php';
            $userClass = $config['yii2']['user'];
            $user = new $userClass;
            
            $user->attributes = [
                'email'             => $this->email,
                'password'          => $this->password,
                'otp_enabled'       => 0,
                'otp_secret'        => '',
            ];
        
            if ($user->save()) {
                // Create an activation token for the user, and store it in the cache
                $token = Base32::encode(\random_bytes(64));
                Yii::$app->cache->set($token, [
                    'id' => $user->id
                ]);

                return $userClass::sendActivationEmail($user->email, $token);
            }
        }

        return false;
        
    }
}