<?php

namespace yrc\api\models\redis;

use Base32\Base32;
use yrc\models\TokenKeyPair;
use Yii;

/**
 * Abstract class for generating and storing tokens
 * @class Token
 */
abstract class Token extends \yrc\redis\ActiveRecord
{
    /**
     * This is our default token lifespan
     * @const TOKEN_EXPIRATION_TIME
     */
    const TOKEN_EXPIRATION_TIME = '+15 minutes';

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'id',
            'user_id',
            'access_token',
            'refresh_token',
            'ikm',
            'secret_sign_kp',
            'expires_at'
        ];
    }

    /**
     * @return sodium_crypto_sign_keypair
     */
    public function getSignKeyPair()
    {
        $secret = \base64_decode($this->secret_sign_kp);
        $public = sodium_crypto_sign_publickey_from_secretkey($secret);
        return sodium_crypto_sign_keypair_from_secretkey_and_publickey($secret, $public);
    }

    /**
     * @return sodium_crypto_sign_publickey
     */
    public function getSignPublicKey()
    {
        return sodium_crypto_sign_publickey($this->getSignKeyPair());
    }

    /**
     * Generates a new auth and refresh token pair
     * @param int $userId
     * @param bool $pubkey
     * @return array
     */
    public static function generate($userId = null)
    {
        $model = null;
        $signKp = sodium_crypto_sign_keypair();

        $user = Yii::$app->yrc->userClass::findOne(['id' => $userId]);
        if ($user === null) {
            throw new \yii\base\Exception('Invalid user');
        }
       
        $token = new static;
        $token->user_id = $userId;
        $token->access_token = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->refresh_token = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->ikm =  \base64_encode(\random_bytes(32));
        $token->secret_sign_kp = \base64_encode(sodium_crypto_sign_secretkey($signKp));
        $token->expires_at = \strtotime(static::TOKEN_EXPIRATION_TIME);

        if ($token->save()) {
            return $token;
        }
            
        throw new \yii\base\Exception(Yii::t('yrc', 'Token failed to save'));
    }

    /**
     * Helper method to get the auth response data
     * @return array
     */
    public function getAuthResponse()
    {
        $attributes = $this->getAttributes();
        unset($attributes['id']);

        $attributes['signing'] = \base64_encode($this->getSignPublicKey());
        unset($attributes['secret_sign_kp']);
        return $attributes;
    }
}