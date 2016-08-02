<?php

namespace yrc\api\forms;

use ZxcvbnPhp\Zxcvbn;
use Base32\Base32;
use app\models\User;

use Yii;

/**
 * @class Registration
 * Registration form
 */
abstract class Registration extends \yii\base\Model
{
    const ACTIVATION_TOKEN_TIMEOUT = '+5 days';

    /**
     * The username
     * @var string $username
     */
    public $username;

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
            [['email', 'username', 'password', 'password_verify'], 'required'],
            [['email', 'username'], 'string', 'min' => 4],
            [['password', 'password_verify'], 'string', 'min' => 8],
            [['password_verify'], 'compare', 'compareAttribute' => 'password']
        ];
    }

    /**
     * Creates a new user
     * @param string $username      The username
     * @param string $password      Password to assign to the user
     * @param bool   $withKeypair   Generate a new keypair for this user
     * @return User
     */
    public function register()
    {
        if ($this->validate()) {
            $user = new User;
            $token = Base32::encode(\random_bytes(64));
            $user->attributes = [
                'email'             => $this->email,
                'username'          => $this->username,
                'password'          => $this->password,
                'activation_token'  => \password_hash($token, PASSWORD_DEFAULT),
                'otp_enabled'       => 0,
                'otp_secret'        => '',
                'activation_token_expires_at' => strtotime(self::ACTIVATION_TOKEN_TIMEOUT)
            ];
        
            if ($user->save()) {
                return User::sendActivationEmail($user->email, $token);
            }
        }

        return false;
        
    }
}