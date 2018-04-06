<?php

namespace yrc\models\redis;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

use yrc\redis\ActiveRecord;
use Yii;

/**
 * Represents a libsodium keypair with an identifiable hash for encrypted requests & responses
 * @property integer $id
 * @property string $hash
 * @property integer $secret
 * @property integer $expires_at
 */
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
    public static function generate()
    {
        $boxKp = sodium_crypto_box_keypair();
        $obj = new static;
        $obj->secret = \base64_encode(sodium_crypto_box_secretkey($boxKp));
        try {
            $uuid = Uuid::uuid4();
            $obj->hash = $uuid->toString();
        } catch (UnsatisfiedDependencyException $e) {
            throw new \yii\base\Exception(Yii::t('yrc', 'Failed to securely generate security token'));
        }

        $obj->expires_at = \strtotime(static::OBJECT_EXPIRATION_TIME);

        if ($obj->save()) {
            return $obj;
        }

        throw new \yii\base\Exception(Yii::t('yrc', 'Failed to generate security tokens'));
    }
}