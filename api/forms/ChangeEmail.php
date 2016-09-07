<?php

namespace yrc\api\forms;

use Base32\Base32;
use Yii;

/**
 * @class ChangeEmail
 * The form for validating the activation form
 */
abstract class ChangeEmail extends \yii\base\model
{
    /**
     * The new email to be changed
     * @var string $email
     */
    public $email;

    /**
     * The user's current password
     * @var string $password
     */
    public $password;

    /**
     * The user whose information we want to change
     * @var User $user
     */
    private $user;

    /**
     * Sets the user object
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['email', 'password'], 'required'],
            [['email'], 'email'],
            [['email'], 'validateNewEmail'],
            [['password'], 'validatePassword']
        ];
    }

    /**
     * Validates the email address
     * @inheritdoc
     */
    public function validateNewemail($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if ($this->user === null) {
                throw new \Exception('User object not set. Unable to proceed.');
            }

            if ($this->email === $this->user->email) {
                $this->addError('email', Yii::t('yrc', 'You cannot change your email to your current email address'));
            }

            if (!$this->hasErrors()) {
                // Clone the user object to verify the email can be set
                $mock = clone $this->user;
                $mock->email = $this->email;
                if (!$mock->validate()) {
                    $this->addError('email', $mock->getFirstError('email'));
                }

                unset($mock);
            }
        }
    }

    /**
     * Validates the user's current password
     * @inheritdoc
     */
    public function validatePassword($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if ($this->user === null) {
                throw new \Exception('User object not set. Unable to proceed.');
            }

            if (!$this->user->validatePassword($this->password)) {
                $this->addError('password', Yii::t('yrc', 'The provided password is not valid'));
            }
        }
    }

    /**
     * Changes the user's email address
     * @return boolean
     */
    public function change()
    {
        if ($this->validate()) {
            $oldEmail = $this->user->email;
            $this->user->email = $this->email;

            // Validation check
            if ($this->user->validate()) {
                // Save chec
                if ($this->user->save()) {
                    // Notify the user via their new email that their login information has been changed
                    Yii::$app->yrc->sendEmail('email_change', Yii::t('app', 'Your login information has changed'), $this->email, [
                        'oldEmail' => $oldEmail,
                        'newEmail' => $this->email
                    ]);

                    // Send a notice to the old email notifying them their login information has been changed
                    Yii::$app->yrc->sendEmail('email_change', Yii::t('app', 'Your login information has changed'), $oldEmail, [
                        'oldEmail' => $oldEmail,
                        'newEmail' => $this->email
                    ]);

                    return true;
                }
            }

            $this->addError('email', $this->user->getError('email'));
        }

        return false;
    }
}