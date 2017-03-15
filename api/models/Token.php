<?php

namespace yrc\api\models;

use Base32\Base32;
use yrc\api\models\TokenKeyPair;
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
            'crypt_id',
            'expires_at'
        ];
    }

    /**
     * Returns the token key pair object
     * @return TokenKeyPair
     */
    public function getCryptToken()
    {
        return TokenKeyPair::find()->where(['id' => $this->crypt_id])->one();
    }

    /**
     * Generates a new auth and refresh token pair
     * @param int $userId
     * @param bool $pubkey
     * @return array
     */
    public static function generate($userId = null, $pubkey = null)
    {
        $model = null;
        $user = Yii::$app->yrc->userClass::findOne(['id' => $userId]);
        if ($user === null) {
            throw new \yii\base\Exception('Invalid user');
        }
       
        $token = new static;
        $token->user_id = $userId;
        $token->access_token = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->refresh_token = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->ikm =  \base64_encode(\random_bytes(32));
        $token->expires_at = \strtotime(static::TOKEN_EXPIRATION_TIME);

        if ($pubkey !== null) {
            $model = TokenKeyPair::generate(TokenKeyPair::DEFAULT_TYPE, $pubkey);
            $token->crypt_id = $model->id;
        }

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
        $model = $this->getCryptToken();

        if ($model !== null) {
            $attributes['crypt'] = [
                'public' => \base64_encode($model->getBoxPublicKey()),
                'signing' => \base64_encode($model->getSignPublicKey()),
                'signature' => \base64_encode(\Sodium\crypto_sign(
                    $model->getBoxPublicKey(),
                    \base64_decode($model->secret_sign_kp)
                )),
                'hash' => $model->hash
            ];
        }

        return $attributes;
    }
}