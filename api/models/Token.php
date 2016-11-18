<?php

namespace yrc\api\models;

use Base32\Base32;
use Yii;

/**
 * Abstract class for generating and storing tokens
 * @class Token
 */
abstract class Token extends \yii\redis\ActiveRecord
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
            'expires_at'
        ];
    }

    /**
     * Generates a new auth and refresh token pair
     * @param int $userId
     * @return array
     */
    public static function generate($userId = null)
    {
        $user = Yii::$app->yrc->userClass::findOne(['id' => $userId]);
        if ($user == null) {
            throw new \yii\base\Exception('Invalid user');
        }
       
        $token = new static;
        $token->user_id = $userId;
        $token->access_token = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->refresh_token = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->ikm =  \base64_encode(\random_bytes(32));
        $token->expires_at = strtotime(self::TOKEN_EXPIRATION_TIME);

        if ($token->save()) {
            return $token->attributes;
        }
            
        throw new \yii\base\Exception(Yii::t('yrc', 'Token failed to save'));
    }

    /**
     * Return true if the token is expired
     * @return boolean
     */
    public function isExpired()
    {
        return $this->expires_at < time();
    }
}