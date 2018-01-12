<?php

namespace yrc\actions;

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
     * [POST] /api/[...]/otp
     * Enables OTP for an account
     * @return mixed
     */
    public function post($params)
    {
        // Find the user
        $user = User::findOne(Yii::$app->user->id);
        if ($user === null) {
            return false;
        }

        if ($user->isOTPEnabled() === true) {
            throw new HttpException(400, Yii::t('yrc', 'OTP is already enabled'));
        }

        // If an OTP code was provided, assume the account has been provisioned and just needs activation
        $otpVerificationCode = Yii::$app->request->post('code', false);
        if ($otpVerificationCode !== false) {
            if ($user->verifyOTP((string)$otpVerificationCode) !== false) {
                return $user->enableOTP();
            }
        } else {
            // Otherwise return the provisioning string
            return [
                'provisioning_code' => $user->provisionOTP()
            ];
        }

        return false;
    }

    /**
     * [DELETE] /api/[...]/otp
     * Disables OTP for an account
     * @return mixed
     */
    public function delete($params)
    {
        // Find the user
        $user = User::findOne(Yii::$app->user->id);
        if ($user === null) {
            return false;
        }

        if ($user->isOTPEnabled() === false) {
            throw new HttpException(400, Yii::t('yrc', 'Two-factor is not enabled'));
        }

        // Grab the code from the GET parameter, and check it
        $otpVerificationCode = Yii::$app->request->post('code', false);
        if ($otpVerificationCode !== false) {
            if ($user->verifyOTP((string)$otpVerificationCode) !== false) {
                return $user->disableOTP();
            }
        }

        return false;
    }
}