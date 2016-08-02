<?php

namespace yrc\api\forms;

use app\forms\Registration;
use app\models\User;
use Base32\Base32;

/**
 * @class Activation
 * The form for validating the activation form
 */
abstract class Activation extends \yii\base\model
{
    /**
     * The activation code
     * @var string $activation_code
     */
    public $activation_code;

    /**
     * The user associated to the model
     * @var User $user
     */
    private $user;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['activation_code'], 'required'],
            [['activation_code'], 'belongsToUserAndIsNotExpired']
        ];
    }

    /**
     * Validates that the activation code belongs to a user and is not expired
     * @param string $attribute
     * @param array $params
     */
    public function belongsToUserAndIsNotExpired($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $this->user = User::find()->where(['activation_token' => $this->activation_code])->one();

            if ($this->user === null) {
                $this->addError('activation_code', 'The activation code provided is not valid.');
            } else {
                if (time() > $this->user->activation_token_expires_at) {
                    $this->addError('activation_code', 'The activation code you provided is expired. For security purposes please request a new activation token and activate your account using that new token');
                }
            }
        }
    }

    /**
     * Activates the user
     * @return boolean
     */
    public function activate()
    {
        if ($this->validate()) {
            return $this->user->activate();
        }

        return false;
    }
}