<?php

namespace yrc\api\actions;

use yrc\rest\Action as RestAction;
use yrc\api\models\TokenKeyPair;
use Sodium;
use Yii;

class OneTimeKeyAction extends RestAction
{
    /**
     * Generates a one time key pair to authenticate further authentication sessions
     * @return array
     */
    public function get($params)
    {
        // Generate a one time key pair
        $model = TokenKeyPair::generate(TokenKeyPair::OTK_TYPE);

        // Return the public keys, and a signature of the public key
        return [
            'public'        => \base64_encode($model->getBoxPublicKey()),
            'signing'       => \base64_encode($model->getSignPublicKey()),
            'signature'     => \base64_encode(\Sodium\crypto_sign(
                $model->getBoxPublicKey(),
                \base64_decode($model->secret_sign_kp)
            )),
            'hash'          => $model->hash,
            'expires_at'    => $model->expires_at
        ];
    }
}