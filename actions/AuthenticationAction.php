<?php

namespace yrc\actions;

use app\forms\Login;
use yrc\rest\Action as RestAction;

use yii\web\UnauthorizedHttpException;
use Yii;

/**
 * Handles Authentication and Deauthentication of users
 * @class AuthenticationAction
 */
class AuthenticationAction extends RestAction
{
    /**
     * Authenticates a user using their username and password
     * @return mixed
     */
    public function post($params)
    {
        $model = new Login;
        
        if ($model->load(['Login' => Yii::$app->request->post()])) {
            $token = $model->authenticate();

            if ($token === false) {
                throw new UnauthorizedHttpException('The credentials you provided are not valid', $model->exitStatus);
            } else {
                return $token->getAuthResponse();
            }
        }
            
        return false;
    }

    /**
     * Deauthenticates a user
     * @return mixed
     */
    public function delete($params)
    {
        $token = self::getAccessTokenFromHeader();
        return (bool)$token->delete();
    }

    /**
     * Helper method to grab the User Token object from the header
     * @return User\Token|bool
     */
    public static function getAccessTokenFromHeader()
    {
        // Grab the authentication header
        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');
        
        // Pull the accessToken from the Authorization header string
        if ($authHeader !== null && preg_match('/^HMAC\s+(.*?)$/', $authHeader, $matches)) {
            if (\strpos($matches[1], ',') !== false) {
                $data = self::getV1Headers($matches[1]);
            } else {
                $data = self::getVersionedHeaders($matches[1]);
            }

            // Retrieve the token object
            $tokenClass = (Yii::$app->user->identityClass::TOKEN_CLASS);
            $token = $tokenClass::find()
                ->where([
                    'access_token' => $data['access_token'],
                    'user_id'      => Yii::$app->user->id
                ])
                ->one();

            // Malformed header
            if ($token === null || $token->isExpired()) {
                throw new UnauthorizedHttpException;
            }
                
            return $token;
        }

        // Header isn't present
        throw new UnauthorizedHttpException;
    }

    /**
     * Returns the unversioned authorization headers
     * @param string $data
     * @return array|bool
     */
    private static function getV1Headers(string $data)
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
    private static function getVersionedHeaders(string $data)
    {
        $params = \json_decode(\base64_decode($data), true);

        // Make sure all the necessary parameters are set
        if (!isset($params['access_token']) || !isset($params['hmac']) || !isset($params['salt']) || !isset($params['v']) || !isset($params['date'])) {
            return false;
        }

        return $params;
    }
}
