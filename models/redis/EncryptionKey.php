<?php

namespace yrc\models\redis;

use yrc\redis\ActiveRecord;
use Yii;

final class EncryptionKey extends ActiveRecord
{
    /**
     * This is our default token lifespan
     * @const TOKEN_EXPIRATION_TIME
     */
    const OBJECT_EXPIRATION_TIME = '+15 minutes';

    /**
     * Model attributes
     * @return array
     */
    public function attributes()
    {
        return [
            'id',
            'secret',
            'expires_at',
            'hash'
        ];
    }

    /**
     * @return sodium_crypto_box_publickey
     */
    public function getBoxPublicKey()
    {
        return sodium_crypto_box_publickey($this->getBoxKeyPair());
    }

    /**
     * @return sodium_crypto_box_keypair
     */
    public function getBoxKeyPair()
    {
        $secret = \base64_decode($this->secret);
        $public = sodium_crypto_box_publickey_from_secretkey($secret);
        return sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, $public);
    }

    /**
     * Helper method to return an encryption key
     * @return EncryptionKey
     */
    public function generate()
    {
        $boxKp = sodium_crypto_box_keypair();
        $obj = new static;
        $obj->secret = \base64_encode(sodium_crypto_box_secretkey($boxKp));
        $obj->hash = \hash('sha256', uniqid('__EncryptionKeyPairHash', true));
        $obj->expires_at = \strtotime(static::OBJECT_EXPIRATION_TIME);

        if ($obj->save()) {
            return $obj;
        }

        throw new \yii\base\Exception(Yii::t('yrc', 'Failed to generate security tokens'));
    }
}