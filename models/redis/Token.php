<?php

namespace yrc\models\redis;

use Base32\Base32;
use ncryptf\Token as NcryptfToken;
use yrc\redis\ActiveRecord;
use Yii;

/**
 * Abstract class for generating and storing tokens
 * @property integer $id
 * @property integer $user_id
 * @property string $access_token
 * @property string $refresh_token
 * @property string $ikm
 * @property string $secret_sign_key
 * @property integer $expires_at
 */
abstract class Token extends ActiveRecord
{
    /**
     * This is our default token lifespan
     * @const TOKEN_EXPIRATION_TIME
     */
    const TOKEN_EXPIRATION_TIME = '+15 minutes';

    /**
     * The refresh token class
     */
    const REFRESH_TOKEN_CLASS = '\app\models\RefreshToken';

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
     * @return array
     */
    public static function generate($userId = null)
    {
        $model = null;
        $signKp = sodium_crypto_sign_keypair();

        $user = Yii::$app->user->identityClass::findOne(['id' => $userId]);
        if ($user === null) {
            throw new \yii\base\Exception('Invalid user');
        }
       
        $token = new static;
        $token->setAttributes([
            'user_id' => $user->id,
            'access_token' => \str_replace('=', '', Base32::encode(\random_bytes(32))),
            'refresh_token' => (static::REFRESH_TOKEN_CLASS)::create($user),
            'ikm' => \base64_encode(\random_bytes(32)),
            'secret_sign_kp' => \base64_encode(sodium_crypto_sign_secretkey($signKp)),
            'expires_at' => \strtotime(static::TOKEN_EXPIRATION_TIME)
        ], false);

        if ($token->save()) {
            return $token;
        }
            
        throw new \yii\base\Exception(Yii::t('yrc', 'Token failed to save'));
    }

    /**
     * Returns an ncryptf compatible token
     *
     * @return ncryptf\Token
     */
    public function getNcryptfToken()
    {
        $attributes = $this->getAuthResponse();
        return new NcryptfToken(
            $attributes['access_token'],
            $attributes['refresh_token'],
            \base64_decode($attributes['ikm']),
            \base64_decode($attributes['secret_sign_kp']),
            $attributes['expires_at']
        );
    }

    /**
     * Helper method to get the auth response data
     * @return array
     */
    public function getAuthResponse()
    {
        $attributes = $this->getAttributes();
        unset($attributes['id']);

        $attributes['signing'] = $attributes['secret_sign_kp'];
        unset($attributes['secret_sign_kp']);
        return $attributes;
    }
}
