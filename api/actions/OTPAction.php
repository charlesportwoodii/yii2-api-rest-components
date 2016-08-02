<?php

namespace yrc\api\actions;

use app\models\User;
use yrc\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * @class OTPAction
 * Handles enabling and disabling of OTP
 */
class OTPAction extends RestAction
{
    /**
     * [POST] /api/v1/otp
     * Enables OTP for an account
     * @return mixed
     */
    public static function post($params)
    {
        // If the user is a guest, do not proceed (endpoint should be protected)
        if (Yii::$app->user->isGuest) {
            return false;
        }

        // Find the user
        $user = User::findOne(Yii::$app->user->id);
        if ($user === null) {
            return false;
        }

        if ($user->isOTPEnabled() === true) {
            throw new HttpException(400, 'OTP is already enabled');
        }

        // If an OTP code was provided, assume the account has been provisioned and just needs activation
        if (Yii::$app->request->post('code', false) !== false) {
            if ($user->verifyOTP(Yii::$app->request->post('code', false)) !== false) {
                return $user->enableOTP();
            }
        } else {
            // Otherwise return the provisioning string
            return $user->provisionOTP();
        }
    }

    /**
     * [POST] /api/v1/otp
     * Disables OTP for an account
     * @return mixed
     */
    public static function delete($params)
    {
        // If the user is a guest, do not proceed (endpoint should be protected)
        if (Yii::$app->user->isGuest) {
            return false;
        }

        // Find the user
        $user = User::findOne(Yii::$app->user->id);
        if ($user === null) {
            return false;
        }

        if ($user->isOTPEnabled() === false) {
            throw new HttpException(400, 'OTP is not enabled');
        }

        // Grab the code from the GET parameter, and check it
        if (Yii::$app->request->get('code', false) !== false) {
            if ($user->verifyOTP(Yii::$app->request->post('code', false)) !== false) {
                return $user->disableOTP();
            }
        }

        return false;
    }
}