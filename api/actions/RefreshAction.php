<?php

namespace yrc\api\actions;

use yrc\api\actions\AuthenticationAction;
use app\models\Token;
use yrc\rest\Action as RestAction;

use Yii;

/**
 * @class RefreshAction
 * Handles token refresh
 */
class RefreshAction extends RestAction
{
    /**
     * [POST] /api/[...]/refresh
     * Refreshes the user's token
     * @return bool
     */
    public static function post($params)
    {
        // Get the token
        $token = AuthenticationAction::getAccessTokenFromHeader();
        
        $refreshToken = Yii::$app->request->post('refresh_token', false);

        if ($refreshToken !== $token->refreshToken) {
            return false;
        }

        // If we can delete the token, send a newly generated token out
        if ($token->delete()) {
            $tokens = Token::generate(Yii::$app->user->id);
            return [
                'access_token'  => $tokens['accessToken'],
                'refresh_token' => $tokens['refreshToken'],
                'ikm'           => $tokens['ikm'],
                'expires_at'    => $tokens['expiresAt']
            ];
        }

        // Return false for any other reasons
        return false;
    }
}