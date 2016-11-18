<?php

namespace yrc\filters\auth;

use app\models\Token;

use yii\helpers\Json;
use yii\filters\auth\AuthMethod;

use Yii;

/**
 * HeaderParamAuth is an action filter that supports the authentication based on the access token passed through a query parameter.
 */
final class HMACSignatureAuth extends AuthMethod
{
    // The date header
    const DATE_HEADER = 'X-DATE';
    
    // The authorization header
    const AUTHORIZATION_HEADER = 'Authorization';
    
    // The amount of the seconds the request is permitted to differ from the server time
    const DRIFT_TIME_ALLOWANCE = 90;

    // The HKDF algorithm
    const HKDF_ALGO = 'sha256';

    // The HKDF authentication info
    const AUTH_INFO = 'HMAC|AuthenticationKey';

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (Yii::$app->request->method === 'OPTIONS') {
            return true;
        }

        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        $authHeader = $request->getHeaders()->get(self::AUTHORIZATION_HEADER);

        if ($authHeader !== null && preg_match('/^HMAC\s+(.*?)$/', $authHeader, $matches)) {
            $data = explode(',', trim($matches[1]));
            if (count($data) !== 3) {
                $this->handleFailure($response);
            }
            
            $accessToken = $data[0];
            $hmac        = $data[1];
            $salt        = $data[2];

            // Check the access token, and make sure we get a valid token data back
            $token = Token::find()
                ->where(['access_token' => $accessToken])
                ->one();

            if ($token === null || $token->isExpired()) {
                $this->handleFailure($response);
            }

            // Verify the HMAC Signature
            if ($this->isHMACSignatureValid($accessToken, \base64_decode($token['ikm']), \base64_decode($salt), $request, $hmac) === false) {
                $this->handleFailure($response);
            }
            
            // If we have an access token, we should always get an Identity back at this stage
            $identity = $user->loginByAccessToken($token, get_class($this));
            
            // Double check we have an identity back
            if ($identity === null) {
                $this->handleFailure($response);
            }
                
            return $identity;
        }
    }
    
    /**
     * Verifies the HMAC signature
     * @param string $hmac
     * @param string $accessToken
     * @param string $salt
     * @param \yii\web\request $request
     * @return bool
     */
    private function isHMACSignatureValid($accessToken, $ikm, $salt, $request, $hmac = null)
    {
        static $selfHMAC = null;
        static $hkdf   = null;
        
        // Null check the HMAC string
        if (empty($hmac) || $hmac === null) {
            return false;
        }

        // Calculate the PBKDF2 hash of the access token
        $hkdf = Yii::$app->security->hkdf(
            self::HKDF_ALGO,
            $ikm,
            $salt,
            self::AUTH_INFO,
            0
        );

        // Verify HKDF didn't fail
        if ($hkdf === null) {
            return false;
        }

        // Termiante the request if the time drift exceeds the allocated value
        // Do not allow request to be submitted before the server time
        $drift = $this->getTimeDrift($request);
        if ($drift >= self::DRIFT_TIME_ALLOWANCE) {
            return false;
        }

        $body = null;
        if ($request->getRawBody() === '') {
            $body = $request->getRawBody();
        } else {
            $body = JSON::encode($request->bodyParams);
        }

        // Calculate the signature string
        $signatureString = hash('sha256', $body) . "\n" .
                           $request->method . "+" . $request->getUrl() . "\n" .
                           $request->getHeaders()->get(self::DATE_HEADER) . "\n" .
                           \base64_encode($salt);
       
        // Calculate the HMAC
        $selfHMAC = \base64_encode(\hash_hmac('sha256', $signatureString, $hkdf, true));
        
        // Verify the HMAC worked
        if ($selfHMAC === null) {
            return false;
        }
        
        // If the calculate HMAC matches our HMAC string
        if (\hash_equals($hmac, $selfHMAC)) {
            return true;
        }
        
        // Always return false
        return false;
    }
    
    /**
     * Gets the datetime drift that has occured since the request was sent
     * @param yii\web\Request $request
     * @return int
     */
    private function getTimeDrift($request)
    {
        // Get the current datetime
        $now = new \DateTime();
        $now->format(\DateTime::RFC1123);
        
        // Get the Header datetime
        $headerDate = $request->getHeaders()->get(self::DATE_HEADER);
        if ($headerDate === null) {
            return false;
        }
        
        // Try to fetch the server datetime. If parsing fails, return an error
        try {
            $headerDatetime = new \DateTime($headerDate);
        } catch (\Exception $e) {
            return false;
        }
        
        return \abs($now->getTimestamp() - $headerDatetime->getTimestamp());
    }
}