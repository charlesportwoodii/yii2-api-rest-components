<?php

namespace yrc\models;

use Base32\Base32;
use OTPHP\TOTP;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\filters\RateLimitInterface;
use yii\web\IdentityInterface;
use Yii;

/**
 * This is the model class for table "user".
 *
 * @property integer $id
 * @property string $email
 * @property strin $username
 * @property string $password
 * @property string $activation_token
 * @property string $reset_token
 * @property integer $verified
 * @property string $otp_secret
 * @property string $otp_enabled
 * @property integer $created_at
 * @property integer $updated_at
 */
abstract class User extends ActiveRecord implements IdentityInterface, RateLimitInterface
{
    /**
     * password_hash Algorithm
     * @var integer
     */
    private $passwordHashAlgorithm = PASSWORD_BCRYPT;
    
    /**
     * The rate limit
     * @var integer
     */
    private $rateLimit = 150;

    /**
     * The rate limit window
     * @var integer
     */
    private $rateLimitWindow = 900;
    
    /**
     * password_hash options
     * @var array
     */
    private $passwordHashOptions = [
        'cost' => 13,
        'memory_cost' => 1<<12,
        'time_cost' => 3,
        'threads' => 1
    ];
    
    /**
     * The token used to authenticate the user
     * @var app\models\Token
     */
    protected $token;

    /**
     * Sets the token used to authenticate the user
     * @return app\models\Token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Sets the token that was used to authenticate the user
     */
    public function setToken($token)
    {
        if ($this->token !== null) {
            throw new \yii\base\Exception(Yii::t('yrc', 'The user has already been authenticated.'));
        }

        $this->token = $token;
    }

    /**
     * Overrides init
     */
    public function init()
    {
        // self init
        parent::init();

        // Prefer Argon2 if it is available, but fall back to BCRYPT if it isn't
        if (defined('PASSWORD_ARGON2I')) {
            $this->passwordHashAlgorithm = PASSWORD_ARGON2I;
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
            TimestampBehavior::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getRateLimit($request, $action)
    {
        return [
            $this->rateLimit,
            $this->rateLimitWindow
        ];
    }

    /**
     * @inheritdoc
     */
    public function loadAllowance($request, $action)
    {
        $hash = Yii::$app->user->id . $request->getUrl() . $action->id;
        $allowance = Yii::$app->cache->get($hash);

        if ($allowance === false) {
            return [
                $this->rateLimit,
                time()
            ];
        }

        return $allowance;
    }

    /**
     * @inheritdoc
     */
    public function saveAllowance($request, $action, $allowance, $timestamp)
    {
        $hash = Yii::$app->user->id . $request->getUrl() . $action->id;
        $allowance = [
            $allowance,
            $timestamp
        ];

        Yii::$app->cache->set($hash, $allowance, $this->rateLimitWindow);
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
            [['password', 'email', 'username'], 'required'],
            [['email'], 'email'],
            [['password'], 'string', 'length' => [8, 100]],
            [['username'], 'string', 'length' => [1, 100]],
            [['created_at', 'updated_at', 'otp_enabled', 'verified'], 'integer'],
            [['password', 'email'], 'string', 'max' => 255],
            [['email'], 'unique'],
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
            'email'             => 'Email Address',
            'username'          => 'Username',
            'password'          => 'Password',
            'activation_token'  => 'Activation Token',
            'otp_secret'        => 'One Time Password Secret Value',
            'otp_enabled'       => 'Is Two Factor Authentication Enabled?',
            'verified'          => 'Is the account email verified?',
            'created_at'        => 'Created At',
            'updated_at'        => 'Last Updated At'
        ];
    }

    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            $this->username = \strtolower($this->username);
            return true;
        }

        return false;
    }

    /**
     * Before save occurs
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
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
        if ($this->isOTPEnabled() === true) {
            return false;
        }

        $secret = \random_bytes(256);
        $encodedSecret = Base32::encode($secret);
        $totp = TOTP::create(
            $encodedSecret,
            30,             // 30 second window
            'sha256',       // SHA256 for the hashing algorithm
            6               // 6 digits
        );
        $totp->setLabel($this->username);

        $this->otp_secret = $encodedSecret;

        if ($this->save()) {
            return $totp->getProvisioningUri();
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
        $totp = TOTP::create(
            $this->otp_secret,
            30,             // 30 second window
            'sha256',       // SHA256 for the hashing algorithm
            6               // 6 digits
        );

        $totp->setLabel($this->username);

        return $totp->verify($code);
    }

    /**
     * Activates the user
     * @return boolean
     */
    public function activate()
    {
        $this->verified = 1;
        return $this->save();
    }

    /**
     * Whether or not a user is activated or not
     * @return boolean
     */
    public function isActivated()
    {
        return (bool)$this->verified;
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
        
        return static::find()->where(['id' => $token->user_id])->one();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        
    }

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
