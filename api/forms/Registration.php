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
class Registration extends \yii\base\Model
{
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
            [['username', 'password', 'password_verify'], 'required'],
            [['username'], 'string', 'min' => 4],
            [['password', 'password_verify'], 'string', 'min' => 8],
            [['password_verify'], 'compare', 'compareAttribute' => 'password'],
            [['password'], 'verifyPasswordComplexity']
        ];
    }

    /**
     * Verifies that the password has sufficient entrophy
     * @param array $attributes
     * @param array $params
     */
    public function verifyPasswordComplexity($attributes, $params)
    {
        if (!$this->hasErrors()) {
            $zxcvbn = new Zxcvbn;
            $userData = [ $this->username ];
            $strength = $zxcvbn->passwordStrength((string)$this->password, $userData);
            if ($strength['entropy'] < 36) {
                $this->addError('password', 'Your password has low entrophy. Please choose a stronger password');
            }
        }
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
            $user->attributes = [
                'username'          => $this->username,
                'password'          => $this->password,
                'activation_token'  => Base32::encode(\random_bytes(64)),
                'otp_enabled'       => 0,
                'otp_secret'        => '',
                'activation_token_expires_at' => strtotime('+5 days')
            ];
            
            return $user->save();
        }

        return false;
        
    }
}