<?php

namespace yrc\api\models;

use Sodium;
use Yii;

final class TokenKeyPair extends \yrc\redis\ActiveRecord
{
    /**
     * The default token type to create
     * @const DEFAULT_TYPE
     */
    const DEFAULT_TYPE = 1;

    /**
     * One time token types
     * @const DEFAULT_TYPE
     */
    const OTK_TYPE = 2;

    /**
     * The default expiration time for crypted tokens
     * @const DEFAULT_EXPIRATION_TIME
     */
    const DEFAULT_EXPIRATION_TIME = '+15 minutes';

    /**
     * One time tokens have an expiration time of 5 minutes. On consumption are destroyed
     * @const OKT_EXPIRATION_TIME
     */
    const OTK_EXPIRATION_TIME = '+5 minutes';

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'id',
            'type',
            'hash',
            'secret_box_kp',
            'secret_sign_kp',
            'client_public',
            'expires_at'
        ];
    }

    /**
     * @return \Sodium\crypto_box_publickey
     */
    public function getBoxPublicKey()
    {
        return \Sodium\crypto_box_publickey($this->getBoxKeyPair());
    }

    /**
     * @return \Sodium\crypto_sign_publickey
     */
    public function getSignPublicKey()
    {
        return \Sodium\crypto_sign_publickey($this->getSignKeyPair());
    }

    /**
     * @return \Sodium\crypto_box_keypair
     */
    public function getBoxKeyPair()
    {
        $secret = \base64_decode($this->secret_box_kp);
        $public = \Sodium\crypto_box_publickey_from_secretkey($secret);
        return \Sodium\crypto_box_keypair_from_secretkey_and_publickey($secret, $public);
    }
    
    /**
     * @return \Sodium\crypto_sign_keypair
     */
    public function getSignKeyPair()
    {
        $secret = \base64_decode($this->secret_sign_kp);
        $public = \Sodium\crypto_sign_publickey_from_secretkey($secret);
        return \Sodium\crypto_sign_keypair_from_secretkey_and_publickey($secret, $public);
    }

    /**
     * Generates a new crypt token
     * @param int $type
     * @return $array
     */
    public static function generate($type = TokenKeyPair::DEFAULT_TYPE, $pubkey = null)
    {
        if ($type === self::OTK_TYPE) {
            $expires_at = \strtotime(static::OTK_EXPIRATION_TIME);
        } else {
            $expires_at = \strtotime(static::DEFAULT_EXPIRATION_TIME);
        }

        $boxKp = \Sodium\crypto_box_keypair();
        $signKp = \Sodium\crypto_sign_keypair();

        $token = new static;
        $token->type = $type;
        $token->secret_box_kp = \base64_encode(\Sodium\crypto_box_secretkey($boxKp));
        $token->secret_sign_kp = \base64_encode(\Sodium\crypto_sign_secretkey($signKp));
        $token->expires_at = $expires_at;
        $token->client_public = $pubkey;
        $token->hash = \hash('sha256', uniqid('__TokenKeyPairHash', true));

        if ($token->save()) {
            return $token;
        }

        throw new \yii\base\Exception(Yii::t('yrc', 'Failed to generate secure tokens'));
    }
}