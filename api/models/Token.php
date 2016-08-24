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
     * Integer expiration date
     * @var integer
     */
    public $expiresAt;

    /**
     * Deletes the current access token
     * @return boolean
     */
    public function delete()
    {
        return Yii::$app->cache->delete($this->accessToken);
    }

    /**
     * Method for saving object data
     * @return boolean
     */
    public function save()
    {
        return Yii::$app->cache->set($this->accessToken, [
            'refreshToken'  => $this->refreshToken,
            'ikm'           => $this->ikm,
            'userId'        => $this->userId,
            'expiresAt'     => $this->expiresAt
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
       
        $token                  = new static;
        $token->userId          = $userId;
        $token->accessToken     = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->refreshToken    = \str_replace('=', '', Base32::encode(\random_bytes(32)));
        $token->ikm             = \base64_encode(\random_bytes(32));
        $token->expiresAt       = strtotime(self::TOKEN_EXPIRATION_TIME);

        if ($token->save()) {
            return $token->attributes;
        }
            
        throw new \yii\base\Exception('Token failed to save');
    }

    /**
     * Retrieves an instance of self
     * @param string $tokenString   The string access token
     * @return Token|null
     */
    public static function find(array $params = [])
    {
        $data = Yii::$app->cache->get($params['accessToken']);

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
        $token->accessToken     = $params['accessToken'];
        $token->refreshToken    = $data['refreshToken'];
        $token->ikm             = $data['ikm'];
        $token->expiresAt       = $data['expiresAt'];
        
        return $token;
    }
}