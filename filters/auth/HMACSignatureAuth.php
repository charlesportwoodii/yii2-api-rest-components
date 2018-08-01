<?php

namespace yrc\filters\auth;

use yrc\models\redis\Token;
use yii\helpers\Json;
use yii\filters\auth\AuthMethod;
use yii\web\Request;
use yii\web\Response;
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
            if (\strpos($matches[1], ',') !== false) {
                $data = $this->getV1Headers($matches[1]);
            } else {
                $data = $this->getVersionedHeaders($matches[1]);
            }

            if ($data) {
                if ($token = $this->getTokenFromAccessToken($data['access_token'])) {
                    if ($this->isHMACSignatureValid($request, $token, \base64_decode($data['salt']), $data['hmac'], $data['date'])) {
                        if ($identity = $user->loginByAccessToken($token, \get_class($this))) {
                            return $identity;
                        }
                    }
                }
            }
        }
            
        $this->handleFailure($response);
    }

    /**
     * Returns the unversioned authorization headers
     * @param string $data
     * @return array|bool
     */
    private function getV1Headers(string $data)
    {
        $params = explode(',', trim($data[1]));

        if (count($params) !== 3) {
            return false;
        }

        return [
            'access_token' => $params[0],
            'hmac' => $params[1],
            'salt' => $params[2],
            'v' => 1,
            'date' => null,
        ];
    }

    /**
     * Returns the versioned authorization headers
     * @param string $data
     * @return array|bool
     */
    private function getVersionedHeaders(string $data)
    {
        $params = \json_decode(\base64_decode($data), true);

        // Make sure all the necessary parameters are set
        if (!isset($params['access_token']) || !isset($params['hmac']) || !isset($params['salt']) || !isset($params['v']) || !isset($params['date'])) {
            return false;
        }

        return $params;
    }
    
    /**
     * Verifies the HMAC signature
     * @param \yii\web\Request $request
     * @param \yrc\models\redis\Token
     * @param string $salt
     * @param string $hmac
     * @return bool
     */
    private function isHMACSignatureValid(Request $request, Token $token, string $salt, string $hmac = null, string $date = null)
    {
        static $selfHMAC = null;
        static $hkdf = null;
        
        // Null check the HMAC string
        if (empty($hmac) || $hmac === null) {
            return false;
        }

        // Calculate the PBKDF2 hash of the access token
        $hkdf = Yii::$app->security->hkdf(
            self::HKDF_ALGO,
            \base64_decode($token->ikm),
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
        $drift = $this->getTimeDrift($request, $date);
        if ($drift >= self::DRIFT_TIME_ALLOWANCE) {
            return false;
        }

        return $this->isHMACValid($request, $hmac, $hkdf, $salt, $date);
    }
    
    /**
     * Gets the datetime drift that has occured since the request was sent
     * @param \yii\web\Request $request
     * @param string $date
     * @return int
     */
    private function getTimeDrift(Request $request, string $headerDate = null)
    {
        // Get the current datetime
        $now = new \DateTime();
        $now->format(\DateTime::RFC1123);
        
        // Get the Header datetime
        if ($headerDate === null) {
            $headerDate = $request->getHeaders()->get(self::DATE_HEADER);
        }
        
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

    /**
     * Retrieves a Token object from an access token string
     * @param string $accessToken
     * @return \yrc\models\redis\Token
     */
    private function getTokenFromAccessToken(string $accessToken)
    {
        try {
            $tokenClass = (Yii::$app->user->identityClass::TOKEN_CLASS);
            $token = $tokenClass::find()
                ->where(['access_token' => $accessToken])
                ->one();
        } catch (\Exception $e) {
            return null;
        }

        if ($token === null || $token->isExpired()) {
            return null;
        }

        return $token;
    }

    /**
     * Retrieves the appropriate request body
     * @param \yii\web\Request $request
     * @return mixed
     */
    private function getBodyFromRequest(Request $request)
    {
        if ($request->getRawBody() === '') {
            return $request->getRawBody();
        }
        
        return JSON::encode($request->bodyParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Generates the signature string
     * @param \yii\web\Request $request
     * @param string $body
     * @param string $salt
     * @param strin $date
     * @return string
     */
    private function generateSignatureStringFromRequestAndBody(Request $request, string $body, string $salt, string $headerDate = null)
    {
        if ($headerDate === null) {
            $headerDate = $request->getHeaders()->get(self::DATE_HEADER);
        }

        $signatureString = hash('sha256', $body) . "\n" .
                            $request->method . "+" . $request->getUrl() . "\n" .
                            $headerDate . "\n" .
                            \base64_encode($salt);
       
        Yii::debug([
            'message' => sprintf('Derived Signature String %s', $signatureString),
            'body' => $body
        ], 'yrc\filters\auth\HMACSignatureAuth:generateSignature');

        return $signatureString;
    }

    /**
     * Verify the provided HMAC matches our generated one
     * @param \yii\web\Request $request
     * @param string $hmac
     * @param string $hkdf
     * @param string $salt
     * @param string $date
     * @return boolean
     */
    public function isHMACValid(Request $request, string $hmac, string $hkdf, string $salt, string $date = null)
    {
        $body = $this->getBodyFromRequest($request);
        $signatureString = $this->generateSignatureStringFromRequestAndBody($request, $body, $salt, $date);

        $selfHMAC = \base64_encode(\hash_hmac('sha256', $signatureString, \bin2hex($hkdf), true));
        
        if ($selfHMAC === null) {
            return false;
        }
        
        if (\hash_equals($hmac, $selfHMAC)) {
            return true;
        }
        
        return false;
    }
}
