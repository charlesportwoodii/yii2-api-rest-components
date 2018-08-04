<?php

namespace yrc\actions;

use common\forms\ResetPassword;
use yrc\rest\Action as RestAction;
use yrc\models\redis\Code;

use yii\web\HttpException;
use Yii;

/**
 * Handles token refresh
 * @class ResetPasswordAction
 */
class ResetPasswordAction extends RestAction
{
    const SCENARIO_TOKENIZED = 'tokenized';
    const SCENARIO_AUTHENTICATED = 'authenticated';

    /**
     * The ResetPassword scenario to use
     * @var string $scenario
     */
    public $scenario;

    /**
     * Reset password flow
     * @param array $params
     * @return boolean
     */
    public function post($params)
    {
        static $form;
        if ($this->scenario === null || $this->scenario === static::SCENARIO_TOKENIZED) {
            $token = Yii::$app->request->get('reset_token', false);

            // Determine the correct scenario to use based upon the reset token
            if ($token === false) {
                $form = new ResetPassword(['scenario' => ResetPassword::SCENARIO_INIT]);
            } else {
                $form = new ResetPassword(['scenario' => ResetPassword::SCENARIO_RESET]);
            }

            // If the user is authenticated, populate the model
            if (!Yii::$app->user->isGuest) {
                $user = Yii::$app->user->identityClass::findOne(['id' => Yii::$app->user->id]);
                $form->setUser($user);
            } else {
                $form->email = Yii::$app->request->post('email', null);
            }

            $form->reset_token = Yii::$app->request->get('reset_token', null);
        } elseif ($this->scenario === static::SCENARIO_AUTHENTICATED) {
            if (Yii::$app->user->isGuest) {
                throw new HttpException(400, Yii::t('yrc', 'You must be authenticated to reset your password'));
                return;
            }

            $form = new ResetPassword(['scenario' => ResetPassword::SCENARIO_RESET_AUTHENTICATED]);
            $form->user_id = Yii::$app->user->id;
        }

        // Load the model using the helper method
        if (self::load($form, Yii::$app->request->post())) {
            // If the form is valid, reset the password
            if ($form->validate()) {
                return $form->reset();
            }

            // If a password reset was requested, (init) return true ALWAYS
            if ($form->getScenario() === ResetPassword::SCENARIO_INIT) {
                return true;
            }

            throw new HttpException(400, \json_encode($form->getErrors()));
        }
            
        return false;
    }

    private static function load(&$form, $attributes)
    {
        foreach ($attributes as $k => $v) {
            if (property_exists($form, $k)) {
                $form->$k = $v;
            }
        }

        return $form;
    }
}
