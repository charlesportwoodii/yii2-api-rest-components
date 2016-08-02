<?php

namespace yrc\api\actions;

use app\models\User;
use app\forms\Registration;

use yrc\rest\Action as RestAction;
use Base32\Base32;

use yii\web\HttpException;
use Yii;

/**
 * @class ReactivationAction
 * Handles token refresh
 */
class ReactivationAction extends RestAction
{
    /**
     * [POST] /api/v1/reactivate
     * Given an email, re-sends an activation email with a new token to a user
     * @return mixed
     */
    public static function post($params)
    {
        $email = Yii::$app->request->post('email', false);

        if ($email === false) {
            throw new HttpException(400);
        }

        $user = User::find()->where(['email' => $email])->one();

        if ($user === null || $user->isActivated() === true) {
            throw new HttpException(400);
        }

        $token = Base32::encode(\random_bytes(64));

        $user->activation_token = \password_hash($token, PASSWORD_DEFAULT);
        $user->activation_token_expires_at = strtotime(Registration::ACTIVATION_TOKEN_TIMEOUT);

        if ($user->save()) {
            return User::sendActivationEmail($user->email, $token);
        }

        throw new HttpException(400);
    }
}