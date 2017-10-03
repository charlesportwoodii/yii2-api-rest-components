<?php

namespace yrc\api\forms;

use Base32\Base32;
use Yii;

use yrc\api\models\Code;

/**
 * @class Activation
 * The form for validating the activation form
 */
abstract class Activation extends \yii\base\Model
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
            $code = Code::find()->where([
                'hash' => hash('sha256', $this->activation_code . '_activation_token')
            ])->one();
            
            if ($code === null) {
                $this->addError('activation_code', Yii::t('yrc', 'The activation code provided is not valid.'));
                return;
            }

            $this->user = Yii::$app->yrc->userClass::find()->where(['id' => $code->user_id])->one();

            if ($this->user === null) {
                $this->addError('activation_code', Yii::t('yrc', 'The activation code provided is not valid.'));
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
            if ($this->user->activate()) {
                Code::deleteAll(['hash' => hash('sha256', $this->activation_code . '_activation_token')]);

                return true;
            }
        }

        return false;
    }
}
