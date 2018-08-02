<?php

namespace yrc\models\redis;

use Base32\Base32;
use Yii;

/**
 * Represents a temporary single use consumable token
 * @property integer $id
 * @property string $token
 * @property integer $expires_at
 */
abstract class RefreshToken extends \yrc\redis\ActiveRecord
{
    /**
     * This is our default token lifespan
     * @const TOKEN_EXPIRATION_TIME
     */
    const TOKEN_EXPIRATION_TIME = '+30 days';

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'id',
            'user_id',
            'token',
            'expires_at'
        ];
    }

    /**
     * Creates a new refresh token that operated independently of the access token
     * @param User $user
     * @return string
     */
    public static function create($user)
    {
        $model = new static;
        $model->setAttributes([
            'user_id' => $user->id,
            'token' => \str_replace('=', '', Base32::encode(\random_bytes(64))),
            'expires_at' => \strtotime(static::TOKEN_EXPIRATION_TIME)
        ], false);

        if ($model->save()) {
            return $model->token;
        }

        throw new \yii\base\Exception(Yii::t('yrc', 'Refresh token failed to generate.'));
    }
}
