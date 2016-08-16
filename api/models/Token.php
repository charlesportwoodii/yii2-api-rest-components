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
    public $accessToken;

    /**
     * The refresh token
     * @var string
     */
    public $refreshToken;

    /**
     * The initial key material for HKDF hashing
     * @var string
     */
    public $ikm;

    /**
     * Method for saving object data
     * @return boolean
     */
    public function save()
    {
        return Yii::$app->cache->set($this->accessToken, [
            'refreshToken'  => $this->refreshToken,
            'ikm'           => $this->ikm,
            'userId'        => $this->userId
        ], strtotime(self::TOKEN_EXPIRATION_TIME));
    }

    /**
     * Generates a new auth and refresh token pair
     * @param int $userId
     * @return array
     */
    public static function generate($userId = null)
    {
        $config = require  Yii::getAlias('@app') . '/config/loader.php';
        $userClass = $config['yii2']['user'];

        $user = $userClass::findOne(['id' => $userId]);
        if ($user == null) {
            throw new \yii\base\Exception('Invalid user');
        }
       
        $token = new static;
        $token->userId = $userId;
        $token->accessToken = Base32::encode(\random_bytes(32));
        $token->refreshToken =  Base32::encode(\random_bytes(32));
        $token->ikm = \base64_encode(\random_bytes(32));

        if ($token->save()) {
            return \array_merge($token->attributes, [ 'expiresAt' => strtotime(self::TOKEN_EXPIRATION_TIME)]);
        }
            
        throw new \yii\base\Exception('Token failed to save');
    }

    /**
     * Retrieves an instance of self
     * @param string $tokenString   The string access token
     * @return Token|null
     */
    public static function getAccessTokenObjectFromString($tokenString = null)
    {
        static $token = null;
        // Find the token, redundant check with user_id

        $tokenData = Yii::$app->cache->get($tokenString);

        // If nothing was found, return null
        if (!$token) {
            return null;
        }
        
        return $token;
    }
}