<?php

namespace yrc\api\actions;

use app\forms\Login;
use app\models\User\Token;
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
     * [POST] /api/v1/authenticate
     * Authenticates a user using their username and password
     * @return mixed
     */
    public static function post($params)
    {
        $model = new Login;
        
        if ($model->load(['Login' => Yii::$app->request->post()])) {
            $authData = $model->authenticate();

            if ($authData === false) {
                throw new UnauthorizedHttpException;
            } else {
                return $authData;
            }
        }
            
        return false;
    }

    /**
     * [DELETE] /api/v1/authenticate
     * Deauthenticates a user
     * @return mixed
     */
    public static function delete($params)
    {
        $all = Yii::$app->request->get('all', null);
        
        if ($all !== null) {
            $token = self::getAccessTokenFromHeader();
            return (bool)$token->delete();
        } else {
            $tokens = Token::find(['user_id' => Yii::$app->user->id])->all();
            foreach ($tokens as $token) {
                $token->delete();
            }
            
            // Make sure everything was deleted
            $count = Token::find(['user_id' => Yii::$app->user->id])->count();
            if ($count === 0) {
                return true;
            }
        }

        return false;
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
            $token = Token::getAccessTokenObjectFromString($accessToken, Yii::$app->user->id);

            // Malformed header
            if ($token === null) {
                throw new UnauthorizedHttpException;
            }
                
            return $token;
        }

        // Header isn't present
        throw new UnauthorizedHttpException;
    }
}