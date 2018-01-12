<?php

namespace yrc\actions;

use yrc\rest\Action as RestAction;
use yrc\models\EncryptionKey;
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
        $model = EncryptionKey::generate();

        // Return the public keys, and a signature of the public key
        return [
            'public'        => \base64_encode($model->getBoxPublicKey()),
            'hash'          => $model->hash,
        ];
    }
}