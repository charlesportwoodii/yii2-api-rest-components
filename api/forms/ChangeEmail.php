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
            [['email'], 'required'],
            [['email'], 'email'],
            [['password'], 'validatePassword']
        ];
    }

    /**
     * Validates the user's current password
     * @inheritdoc
     */
    public function validatePassword($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if (!$this->user->validatePassword($this->password_current)) {
                $this->addError('password_current', Yii::t('yrc', 'The provided password is not valid'));
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
                    Yii::$app->yrc->sendEmail('email_change', Yii::t('app', 'Your login information has changed'), $user->email, [
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