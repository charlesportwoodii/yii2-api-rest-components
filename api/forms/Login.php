<?php

namespace yrc\api\forms;

use app\models\Token;
use Yii;

/**
 * @class Login
 * Form for authenticating users
 */
abstract class Login extends \yii\base\model
{
    /**
     * The user's email
     * @var string
     */
    public $email;

    /**
     * The user's password
     * @var string
     */
    public $password;

    /**
     * The users OTP code, if provided
     * @var string
     */
    public $otp;

    /**
     * The user object
     * @var User
     */
    private $user = null;

    /**
     * Yii2 model validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['email', 'password'], 'required'],
            [['email'], 'email'],
            [['email', 'password'], 'string', 'max' => 255],
            [['otp'], 'string', 'length' => 6],
            [['password'], 'string', 'min' => 8],
            [['password'], 'validatePasswordAndOTP'],
        ];
    }

    /**
     * Retreives the user object
     * @return User
     */
    public function getUser()
    {
        if ($this->user !== null) {
            return $this->user;
        }
        
        // We only allow verified users to authenticate
        if ($this->user === null) {
            $this->user = Yii::$app->yrc->userClass::findOne(['email' => $this->email, 'verified' => 1]);
        }

        return $this->user;
    }

    /**
     * Validates the users' password and OTP code
     * @param array $attributes
     * @param array $params
     */
    public function validatePasswordAndOTP($attribute, $params)
    {
        // Only proceed if the preceeding validation rules passed
        if (!$this->hasErrors()) {
            // Fetch the user object
            $user = $this->getUser();

            // If the user is null or false, an error occured when fetching them, thus throw an error
            if (!$user) {
                $this->addError($attribute, 'Incorrect email address or password.');
            } else {
                // If the password doesn't validate, throw an error
                if (!$user->validatePassword($this->password)) {
                    $this->addError($attribute, 'Incorrect email address or password.');
                }

                // Check the OTP code if it is enabled for the account
                if ($user->isOTPEnabled() === true) {
                    // Verify the OTP code is valid
                    if ($user->verifyOTP((string)$this->otp) === false) {
                        $this->addError($attribute, 'Incorrect email address or password.');
                    }
                }
            }
        }
    }

    /**
     * Authenticates the user by running the validators, and returning generate auth token
     * @return bool
     */
    public function authenticate()
    {
        if ($this->validate()) {
            return Token::generate($this->getUser()->id);
        }

        return false;
    }
}