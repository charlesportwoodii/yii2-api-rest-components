<?php

namespace yrc\actions;

use app\forms\Login;
use yrc\rest\Action as RestAction;

use yii\web\UnauthorizedHttpException;
use Yii;

/**
 * @class AuthenticationAction
 * Handles Authentication and Deauthentication of users
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
            $data = explode(',', trim($matches[1]));
            $accessToken = $data[0];

            // Retrieve the token object
            $tokenClass = (Yii::$app->user->identityClass::TOKEN_CLASS);
            $token = $tokenClass::find()
                ->where([
                    'access_token' => $accessToken,
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
}