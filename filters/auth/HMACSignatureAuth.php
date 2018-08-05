<?php

namespace yrc\filters\auth;

use DateTime;
use ncryptf\Authorization;
use ncryptf\Token as NcryptfToken;
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
        $params = Authorization::extractParamsFromHeaderString($request->getHeaders()->get(self::AUTHORIZATION_HEADER));

        if ($params) {
            if ($token = $this->getTokenFromAccessToken($params['access_token'])) {
                try {
                    $date = new DateTime($params['date'] ?? $request->getHeaders()->get(self::DATE_HEADER));
                    $auth = new Authorization(
                        $request->method,
                        $request->getUrl(),
                        $token->getNcryptfToken(),
                        $date,
                        $this->getBodyFromRequest($request),
                        $params['v'],
                        \base64_decode($params['salt'])
                    );

                    if ($auth->verify(\base64_decode($params['hmac']), $auth, static::DRIFT_TIME_ALLOWANCE)) {
                        if ($identity = $user->loginByAccessToken($token, \get_class($this))) {
                            return $identity;
                        }
                    }
                } catch (\Exception $e) {
                    Yii::error('Failed to determine date.');
                }
            }
        }

        $this->handleFailure($response);
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
}
