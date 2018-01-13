<?php

namespace yrc\forms;

use Base32\Base32;
use Yii;
use yrc\models\redis\Code;

abstract class ResetPassword extends \yii\base\Model
{
    const SCENARIO_INIT = 'init';
    const SCENARIO_RESET = 'reset';
    const SCENARIO_RESET_AUTHENTICATED = 'reset_authenticated';

    // The expiration time of the token
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
     * The user ID for credentialless password resets
     * @var int $user_id
     */
    public $user_id;

    /**
     * The old password
     * @var string $old_password
     */
    public $old_password;

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
     * The OTP code (optional)
     * @var string $otp
     */
    public $otp;

    /**
     * The user associated to the email
     * @var User $user
     */
    protected $user = null;

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

            [['reset_token', 'password', 'password_verify'], 'required', 'on' => self::SCENARIO_RESET],
            [['reset_token'], 'validateResetToken', 'on' => self::SCENARIO_RESET],
            [['password', 'password_verify'], 'string', 'min' => 8, 'on' => self::SCENARIO_RESET],
            [['password_verify'], 'compare', 'compareAttribute' => 'password', 'on' => self::SCENARIO_RESET],
            [['password', 'password_verify'], 'required', 'on' => self::SCENARIO_RESET],
            [['otp'], 'validateOTP', 'on' => self::SCENARIO_RESET],

            [['user_id'], 'validateAuthUser', 'on' => self::SCENARIO_RESET_AUTHENTICATED],
            [['old_password'], 'validateOldPassword', 'on' => self::SCENARIO_RESET_AUTHENTICATED],
            [['old_password', 'password', 'password_verify'], 'required', 'on' => self::SCENARIO_RESET_AUTHENTICATED],
            [['password', 'password_verify'], 'string', 'min' => 8, 'on' => self::SCENARIO_RESET_AUTHENTICATED],
            [['password_verify'], 'compare', 'compareAttribute' => 'password', 'on' => self::SCENARIO_RESET_AUTHENTICATED],
            [['password', 'password_verify'], 'required', 'on' => self::SCENARIO_RESET_AUTHENTICATED],
            [['otp'], 'validateOTP', 'on' => self::SCENARIO_RESET_AUTHENTICATED],
        ];
    }
    
    /**
     * Validates the users OTP code, if they provided
     * @inheritdoc
     */
    public function validateOTP($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if ($this->getUser()->isOTPEnabled()) {
                if ($this->otp === null || !$this->getUser()->verifyOTP((string)$this->otp)) {
                    $this->addError('otp', Yii::t('yrc', 'This account is protected with two factor authentication, and a valid OTP code is required to change the password.'));
                    return;
                }
            }
        }
    }

    /**
     * Validates the users old password
     * @inheritdoc
     */
    public function validateOldPassword($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if (!$this->getUser()->validatePassword($this->old_password)) {
                $this->addError('old_password', Yii::t('yrc', 'Your current password is not valid.'));
                return;
            }
            
            $this->validateOTP('otp', $this->otp);
        }
    }

    /**
     * Validates the authenticated users state
     * @inheritdoc
     */
    public function validateAuthUser($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if ($this->getUser() === null) {
                $this->addError('email', Yii::t('yrc', 'The provided email address is not valid.'));
                return;
            }
        }
    }

    /**
     * Validates the users email
     * @inheritdoc
     */
    public function validateUser($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if ($this->getUser() === null) {
                $this->addError('email', Yii::t('yrc', 'The provided email address is not valid'));
                return;
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
            $code = Code::find()->where([
                'hash' => hash('sha256', $this->reset_token . '_reset_token')
            ])->one();
            
            if ($code === null || $code->isExpired()) {
                $this->addError('reset_token', Yii::t('yrc', 'The password reset token provided is not valid.'));
                return;
            }

            $this->setUser(Yii::$app->yrc->userClass::find()->where([
                'id' => $code->user_id
            ])->one());

            if ($this->getUser() === null) {
                $this->addError('reset_token', Yii::t('yrc', 'The password reset token provided is not valid.'));
                return;
            }

            $this->validateOTP('otp', $this->otp);
        }
    }

    /**
     * Sets the user object
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
        $this->email = $user->email;
    }

    /**
     * Helper method to get the current user
     * @return User
     */
    public function getUser()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if (isset($this->user_id)) {
            $this->user = Yii::$app->yrc->userClass::findOne(['id' => $this->user_id]);
        } elseif (isset($this->email)) {
            $this->user = Yii::$app->yrc->userClass::findOne(['email' => $this->email]);
        }

        return $this->user;
    }

    /**
     * Changes the password for the user
     * @return boolean
     */
    public function reset()
    {
        if ($this->validate()) {
            if ($this->getScenario() === self::SCENARIO_INIT) {
                // Create an reset token for the user, and store it in the cache
                $token = Base32::encode(\random_bytes(64));
                
                $code = new Code;
                $code->hash = hash('sha256', $token . '_reset_token');
                $code->user_id = $this->getUser()->id;
                $code->expires_at = strtotime(self::EXPIRY_TIME);

                if (!$code->save()) {
                    return false;
                }

                $job = Yii::$app->rpq->getQueue()->push(
                    '\yrc\jobs\notifications\email\ResetPasswordNotification',
                    [
                        'email' => $this->getUser()->email,
                        'token' => $token,
                        'user_id' => $this->getUser()->id
                    ],
                    true
                );
    
                Yii::info([
                    'message' => '[Email] Reset Password Notification Scheduled',
                    'user_id' => $this->getUser()->id,
                    'data' => [
                        'email' => $this->getUser()->email
                    ],
                    'job_id' => $job->getId()
                ]);  

                return true;
            } elseif ($this->getScenario() === self::SCENARIO_RESET || $this->getScenario() === self::SCENARIO_RESET_AUTHENTICATED) {
                $this->getUser()->password = $this->password;

                if ($this->getUser()->save()) {
                    $job = Yii::$app->rpq->getQueue()->push(
                        '\yrc\jobs\notifications\email\PasswordChangedNotification',
                        [
                            'email' => $this->getUser()->email,
                            'user_id' => $this->getUser()->id
                        ],
                        true
                    );
        
                    Yii::info([
                        'message' => '[Email] Password Changed Notification Scheduled',
                        'user_id' => $this->getUser()->id,
                        'data' => [
                            'email' => $this->getUser()->email
                        ],
                        'job_id' => $job->getId()
                    ]);  
                    return true;
                }
            }
        }

        return false;
    }
}
