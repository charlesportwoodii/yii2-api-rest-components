<?php

namespace charlesportwoodii\yii2\api\forms;

use charlesportwoodii\yii2\api\models\User;

/**
 * @class Login
 * Form for authenticating users
 */
final class Login extends \yii\base\model
{
    /**
     * The user's username
     * @var string
     */
    public $username;

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
            [['username', 'password'], 'required'],
            [['username', 'password'], 'string', 'max' => 255],
            [['opt'], 'string', 'length' => 6],
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
        if ($this->_user === null) {
            $this->_user = User::findOne(['username' => $this->username]);
        }
        return $this->_user;
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
                $this->addError($attribute, 'Incorrect usernamne or password.');
            } else {
                // If the password doesn't validate, throw an error
                if (!$user->validatePassword($this->password)) {
                    $this->addError($attribute, 'Incorrect usernamne or password.');
                }

                // Check the OTP code if it is enabled for the account
                if ($user->isOTPEnabled() === true) {
                    // Verify the OTP code is valid
                    if ($user->verifyOTP((integer)$this->otp) === false) {
                        $this->addError($attribute, 'Incorrect usernamne or password.');
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