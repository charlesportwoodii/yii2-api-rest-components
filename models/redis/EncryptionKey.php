<?php

namespace yrc\models\redis;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

use ncryptf\Utils;
use ncryptf\Keypair;
use ncryptf\middleware\EncryptionKeyInterface;

use yrc\redis\ActiveRecord;
use Yii;

/**
 * Represents a libsodium keypair with an identifiable hash for encrypted requests & responses
 * @property integer $id                The internal Redis ID for this object
 * @property string $hash               A UUID1 hash to identify this object publicly
 * @property string $secret             Secret key material for encryption
 * @property string $public             Public key material for encryption
 * @property string $signing_secret     Secret signing key material
 * @property string $signing_public     Public signing key material
 * @property boolean $ephemeral         Whether or not this key is single use or not
 * @property integer $expires_at        The unix timestamp at which this key will expire at and be invalid
 */
final class EncryptionKey extends ActiveRecord implements EncryptionKeyInterface
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
            'public',
            'signing_secret',
            'signing_public',
            'expires_at',
            'ephemeral',
            'hash'
        ];
    }

    /**
     * Returns the hash identifier
     *
     * @return string
     */
    public function getHashIdentifier() : string
    {
        return $this->hash;
    }

    /**
     * Retrieves the box public key
     *
     * @return string
     */
    public function getBoxPublicKey() : string
    {
        return $this->getBoxKeyPair()
            ->getPublicKey();
    }

    /**
     * Retrieves the box secret key
     *
     * @return string
     */
    public function getBoxSecretKey() : string
    {
        return $this->getBoxKeyPair()
            ->getSecretKey();
    }

    /**
     * Retrieves the box keypair
     *
     * @return Keypair
     */
    public function getBoxKeyPair() : \ncryptf\Keypair
    {
        return new \ncryptf\Keypair(
            \base64_decode($this->secret),
            \base64_decode($this->public)
        );
    }

    /**
     * Retrieves the signing public key
     *
     * @return string
     */
    public function getSignPublicKey() : string
    {
        return $this->getSignKeyPair()
            ->getPublicKey();
    }

    /**
     * Retrieves the signing secret key
     *
     * @return string
     */
    public function getSignSecretKey() : string
    {
        return $this->getSignKeyPair()
            ->getSecretKey();
    }

    /**
     * Retrieves the signing keypair
     *
     * @return Keypair
     */
    public function getSignKeyPair() : \ncryptf\Keypair
    {
        return new \ncryptf\Keypair(
            \base64_decode($this->signing_secret),
            \base64_decode($this->signing_public)
        );
    }

    /**
     * Returns `true` if the key is ephemeral
     *
     * @return boolean
     */
    public function isEphemeral() : bool
    {
        return $this->ephemeral;
    }

    /**
     * Retrieves the public key expiration time
     *
     * @return integer
     */
    public function getPublicKeyExpiration() : int
    {
        return $this->expires_at;
    }

    /**
     * Generates a new EncryptionKeyInterface
     *
     * @param boolean $ephemeral
     * @return EncryptionKeyInterface
     */
    public static function generate($ephemeral = false) : \ncryptf\middleware\EncryptionKeyInterface
    {
        $key = Utils::generateKeyPair();
        $signingKey = Utils::generateSigningKeypair();

        $obj = new static;
        $obj->secret = \base64_encode($key->getSecretKey());
        $obj->public = \base64_encode($key->getPublicKey());
        $obj->signing_secret = \base64_encode($signingKey->getSecretKey());
        $obj->signing_public = \base64_encode($signingKey->getPublicKey());
        try {
            $uuid = Uuid::uuid1();
            $obj->hash = $uuid->toString();
        } catch (UnsatisfiedDependencyException $e) {
            throw new \yii\base\Exception(Yii::t('yrc', 'Failed to securely generate security token'));
        }

        $obj->expires_at = \strtotime(static::OBJECT_EXPIRATION_TIME);
        $obj->ephemeral = $ephemeral;
        if ($obj->save()) {
            return $obj;
        }

        throw new \yii\base\Exception(Yii::t('yrc', 'Failed to generate security tokens'));
    }
}
