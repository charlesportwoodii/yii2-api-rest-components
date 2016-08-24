<?php

namespace yrc\api\actions;

use app\forms\Login;
use app\models\Token;
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
    public static function post($params)
    {
        $model = new Login;
        
        if ($model->load(['Login' => Yii::$app->request->post()])) {
            $data = $model->authenticate();

            if ($data === false) {
                throw new UnauthorizedHttpException;
            } else {
                return [
                    'access_token'  => $data['accessToken'],
                    'refresh_token' => $data['refreshToken'],
                    'ikm'           => $data['ikm'],
                    'expires_at'    => $data['expiresAt']
                ];
            }
        }
            
        return false;
    }

    /**
     * Deauthenticates a user
     * @return mixed
     */
    public static function delete($params)
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
            $token = Token::find([
                'accessToken' => $accessToken,
                'userId'      => Yii::$app->user->id
            ]);

            // Malformed header
            if (!$token) {
                throw new UnauthorizedHttpException;
            }
                
            return $token;
        }

        // Header isn't present
        throw new UnauthorizedHttpException;
    }
}