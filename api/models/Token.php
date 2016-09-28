<?php

namespace yrc\api\models;

use Base32\Base32;
use Yii;

/**
 * Abstract class for generating and storing tokens
 * @class Token
 */
abstract class Token extends \yii\base\Model
{
    const TOKEN_EXPIRATION_TIME = '+15 minutes';
    
    /**
     * The user ID
     * @var integer
     */
    public $userId;

    /**
     * The access token
     * @var string
     */
    public $access_token;

    /**
     * The refresh token
     * @var string
     */
    public $refresh_token;

    /**
     * The initial key material for HKDF hashing
     * @var string
     */
    public $ikm;

    /**
     * Integer expiration date
     * @var integer
     */
    public $expires_at;

    /**
     * Deletes the current access token
     * @return boolean
     */
    public function delete()
    {
        return Yii::$app->cache->delete($this->access_token);
    }

    /**
     * Method for saving object data
     * @return boolean
     */
    public function save()
    {
        return Yii::$app->cache->set($this->access_token, [
            'refresh_token'     => $this->refresh_token,
            'ikm'               => $this->ikm,
            'userId'            => $this->userId,
            'expires_at'        => $this->expires_at
        ], strtotime(self::TOKEN_EXPIRATION_TIME));
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
       
        $token                  = new static;
        $token->userId          = $userId;
        $token->access_token    = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->refresh_token   = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->ikm             = \base64_encode(\random_bytes(32));
        $token->expires_at      = strtotime(self::TOKEN_EXPIRATION_TIME);

        if ($token->save()) {
            return $token->attributes;
        }
            
        throw new \yii\base\Exception(Yii::t('yrc', 'Token failed to save'));
    }

    /**
     * Retrieves an instance of self
     * @param array $params
     * @return Token|null
     */
    public static function find(array $params = [])
    {
        $data = Yii::$app->cache->get($params['access_token']);

        // If nothing was found, return null
        if (!$data) {
            return null;
        }

        if (isset($params['userId']) && isset($data['userId'])) {
            if ($data['userId'] !== $params['userId']) {
                return null;
            }
        }
        
        $token                  = new static;
        $token->userId          = $data['userId'];
        $token->access_token    = $params['access_token'];
        $token->refresh_token   = $data['refresh_token'];
        $token->ikm             = $data['ikm'];
        $token->expires_at      = $data['expires_at'];
        
        return $token;
    }
}