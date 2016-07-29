<?php

namespace yrc\api\models\User;

use CryptLib\Random\Factory as CryptLibRandomFactory;
use yrc\api\models\User;

use yii\behaviors\TimestampBehavior;
use Yii;

/**
 * This is the model class for table "user_token".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $access_token
 * @property string $refresh_token
 * @property string $salt
 * @property integer $expires_at
 *
 * @property User $user
 */
class Token extends \yii\db\ActiveRecord
{
    /**
     * Constant OFFSET_TIME
     * The offset time in minutes, declaring how long access tokens are valid for
     */
    const OFFSET_TIME = '+15 minutes';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'expires_at',
                'updatedAtAttribute' => false,
                'value' => strtotime(self::OFFSET_TIME)
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_token';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'access_token', 'refresh_token'], 'required'],
            [['user_id',  'expires_at'], 'integer'],
            [['access_token', 'refresh_token', 'salt'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                => 'ID',
            'user_id'           => 'User ID',
            'access_token'      => 'Access Token',
            'refresh_token'     => 'Refresh Token',
            'salt'              => 'Salt',
            'expires_at'        => 'Expires At',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * Expires the current token
     * @return bool
     */
    public function expire()
    {
        $this->expires_at = time();
        return $this->save();
    }
    
    /**
     * Generates a new auth and refresh token pair
     * @param int $userId
     * @return array
     */
    public static function generate($userId = null)
    {
        if ($userId == null) {
            throw new \yii\base\Exception('Missing Arg: userId');
        }
        
        $generator = (new CryptLibRandomFactory)->getMediumStrengthGenerator();
        
        $token = new self;
        $token->attributes = [
            'user_id'       => $userId,
            'access_token'  => $generator->generateString(32),
            'refresh_token' => $generator->generateString(32),
            'salt'          => \base64_encode(\random_bytes(32))
        ];
        
        if ($token->save()) {
            return [
                'access_token'  => $token->access_token,
                'refresh_token' => $token->refresh_token,
                'salt'          => $token->salt
            ];
        }
            
        throw new \yii\base\Exception('Token failed to save');
    }
    
    /**
     * Retrieves an instance of app\models\User\Token;
     * @param string $tokenString   The string access token
     * @return Token|null
     */
    public static function getAccessTokenObjectFromString($tokenString = null)
    {
        static $token = null;
        
        // Find the token, redundant check with user_id
        if (!Yii::$app->user->isGuest) {
            $token = self::findOne([
                'user_id'       => Yii::$app->user->id,
                'access_token'  => $tokenString
            ]);
        } else {
            $token = self::findOne([
                'access_token'  => $tokenString
            ]);
        }

        // If nothing was found, return null
        if ($token === null) {
            return null;
        }

        // Check if the token is expired
        if ($token->expires_at < time()) {
            // Remove the user token
            $token->delete();
            return null;
        }
        
        return $token;
    }
}