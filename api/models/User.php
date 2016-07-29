<?php

namespace yrc\api\models;

use yrc\api\models\User\Token;

use Base32\Base32;
use OTPHP\TOTP;
use yii\behaviors\TimestampBehavior;
use Yii;

/**
 * This is the model class for table "user".
 *
 * @property integer $id
 * @property string $username
 * @property string $password
 * @property string $activation_token
 * @property string $reset_token
 * @property integer $account_status
 * @property string $otp_secret
 * @property string $otp_enabled
 * @property integer $activation_token_expires_at
 * @property integer $reset_token_expires_at
 * @property integer $created_at
 * @property integer $updated_at
 *
 * @property Tokens[] $tokens
 */
abstract class User extends \yii\db\ActiveRecord implements \yii\web\IdentityInterface
{
    /**
     * password_hash Algorithm
     * @var integer
     */
    private $passwordHashAlgorithm = PASSWORD_BCRYPT;
    
    /**
     * password_hash options
     * @var array
     */
    private $passwordHashOptions = [];
    
    /**
     * Overrides init
     */
    public function init()
    {
        // self init
        parent::init();

        // Prefer Argon2 if it is available, but fall back to BCRYPT if it isn't
        if (defined('PASSWORD_ARGON2')) {
            $this->passwordHashAlgorithm = PASSWORD_ARGON2;
            $this->passwordHashOptions = [];
        }

        // Lower the bcrypt cost when running tests
        if (YII_DEBUG && $this->passwordHashAlgorithm === PASSWORD_BCRYPT) {
            $this->passwordHashOptions['cost'] = 10;
        }
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['password', 'username'], 'required'],
            [['username'], 'trim'],
            [['password'], 'string', 'length' => [8, 100]],
            [['created_at', 'updated_at', 'activation_token_expires_at', 'reset_token_expires_at', 'otp_enabled'], 'integer'],
            [['password', 'username', 'otp_secret', 'activation_token', 'reset_token'], 'string', 'max' => 255],
            [['username'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                => 'ID',
            'username'          => 'Username',
            'password'          => 'Password',
            'activation_token'  => 'Activation Token',
            'reset_token'       => 'Reset Token',
            'account_status'    => 'Account Status',
            'otp_secret'        => 'One Time Password Secret Value',
            'otp_enabled'       => 'Is Two Factor Authentication Enabled?',
            'activation_token_expires_at' => 'Activation Token Expiration',
            'reset_token_expires_at' => 'Reset Token Expiration',
            'created_at'        => 'Created At',
            'updated_at'        => 'Last Updated At'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTokens()
    {
        return $this->hasMany(Token::className(), ['user_id' => 'id']);
    }

    /**
     * Pre-validation
     * @return bool
     */
    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            if ($this->isNewRecord || $this->password !== $this->oldAttributes['password']) {
                $this->password = password_hash($this->password, $this->passwordHashAlgorithm, $this->passwordHashOptions);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Validates the user's password
     * @param string $password
     * return bool
     */
    public function validatePassword($password)
    {
        if (password_verify($password, $this->password)) {
            if (password_needs_rehash($this->password, $this->passwordHashAlgorithm, $this->passwordHashOptions)) {
                $this->password = password_hash($password, $this->passwordHashAlgorithm, $this->passwordHashOptions);
                
                // Allow authentication to continue if we weren't able to update the password, but log the message
                if (!$this->save()) {
                    Yii::warning('Unable to save newly hashed password for user: ' . $this->id);
                }
            }

            return true;
        }
        
        return false;
    }

    /**
     * Returns true of OTP is enabled
     * @return boolean
     */
    public function isOTPEnabled()
    {
        return (bool)$this->otp_enabled;
    }
    
    /**
     * Provisions TOTP for the account
     * @return boolean|string
     */
    public function provisionOTP()
    {
        if ($this->otp_enabled == 0) {
            return false;
        }

        $secret = \random_bytes(256);
        $encoded_secret = Base32::encode($secret);
        $totp = new TOTP(
            $this->username,
            $encodedSecret,
            30,             // 30 second window
            'sha256',       // SHA256 for the hashing algorithm
            6               // 6 digits
        );

        $this->otp_secret = $encodedSecret;

        if ($this->save()) {
            return $$totp->getProvisioningUri();
        }

        return false;
    }

    /**
     * Enables OTP
     * @return boolean
     */
    public function enableOTP()
    {
        if ($this->isOTPEnabled() === true) {
            return true;
        }

        if ($this->otp_secret == "") {
            return false;
        }
        
        $this->otp_enabled = 1;

        return $this->save();
    }

    /**
     * Disables OTP
     * @return boolean
     */
    public function disableOTP()
    {
        $this->otp_secret = "";
        $this->otp_enabled = 0;

        return $this->save();
    }

    /**
     * Verifies the OTP code
     * @param integer $code
     * @return boolean
     */
    public function verifyOTP($code)
    {
        $totp = new TOTP(
            $this->username,
            $this->otp_secret,
            30,             // 30 second window
            'sha256',       // SHA256 for the hashing algorithm
            6               // 6 digits
        );

        return $totp->verify($code);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }
    
    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        // Checking of the Token is performed in app\components\filters\auth\HMACSignatureAuth
        if ($token === null) {
            return null;
        }
        
        return static::findOne(['id' => $token->user_id]);
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey() {}

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }
}