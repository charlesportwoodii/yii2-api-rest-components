<?php

namespace yrc\api\forms;

use Base32\Base32;
use Yii;

abstract class ResetPassword extends \yii\base\model
{
    const SCENARIO_INIT = 'init';
    const SCENARIO_RESET = 'reset';

    const EXPIRY_TIME = '+4 hours';

    /**
     * The email
     * @var string $email
     */
    public $email;

    /**
     * The reset token
     * @var string $reset_token
     */
    public $reset_token;

    /**
     * The new password
     * @var string $password
     */
    public $password;

    /**
     * The new password (again)
     * @var string $password_verify
     */
    public $password_verify;

    /**
     * The user associated to the email
     * @var User $user
     */
    private $user = null;

    /**
     * Validation scenarios
     * @return array
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_INIT => ['email'],
            self::SCENARIO_RESET => ['reset_token'],
        ];
    }

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['email'], 'required', 'on' => self::SCENARIO_INIT],
            [['email'], 'email', 'on' =>  self::SCENARIO_INIT],
            [['email'], 'validateUser', 'on' =>  self::SCENARIO_INIT],

            [['reset_token'], 'required', 'on' => self::SCENARIO_RESET],
            [['reset_token'], 'validateResetToken', 'on' => self::SCENARIO_RESET],
            [['password', 'password_verify'], 'string', 'min' => 8, 'on' => self::SCENARIO_RESET],
            [['password_verify'], 'compare', 'compareAttribute' => 'password', 'on' => self::SCENARIO_RESET]
        ];
    }

    /**
     * Validates the users email
     * @inheritdoc
     */
    public function validateUser($attributes, $params)
    {
        if (!$this->hasErrors()) {
            $this->user = Yii::$app->yrc->userClass::findOne(['email' => $this->email]);

            if ($this->user === null) {
                $this->addError('email', 'The provided email address is not valid');
            }
        }
    }

    /**
     * Reset token validator
     * @inheritdoc
     */
    public function validateResetToken($attributes, $params)
    {
        if (!$this->hasErrors()) {
            // If the user object is set, consider the user pre-authenticated
            if ($this->user !== null) {
                return;
            }

            $tokenInfo = Yii::$app->cache->get(
                hash('sha256', $this->reset_token . '_reset_token')
            );
            
            if ($tokenInfo === null) {
                $this->addError('reset_token', 'The password reset token provided is not valid.');
            }

            $this->user = Yii::$app->yrc->userClass::find()->where(['id' => $tokenInfo['id']])->one();

            if ($this->user === null) {
                $this->addError('reset_token', 'The password reset token provided is not valid.');
            }
        }
    }

    /**
     * Sets the user object
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Initializes a password reset request
     * @return boolean
     */
    public function initReset()
    {
        if ($this->validate()) {
            // Create an reset token for the user, and store it in the cache
            $token = Base32::encode(\random_bytes(64));
            
            Yii::$app->cache->set(hash('sha256', $token . '_reset_token'), [
                'id' => $this->user->id
            ], strtotime(self::EXPIRY_TIME));

            return Yii::$app->yrc->userClass::sendPasswordResetEmail($this->user->email, $token);
        }

        return false;
    }

    /**
     * Changes the password for the user
     * @return boolean
     */
    public function reset()
    {
        if ($this->validate()) {
            $this->user->password = $this->password;

            if ($this->user->save()) {
                return Yii::$app->yrc->userClass::sendPasswordChangedEmail($this->email);
            }
        }

        return false;
    }
}