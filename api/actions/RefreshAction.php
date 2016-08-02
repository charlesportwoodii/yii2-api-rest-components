<?php

namespace yrc\api\actions;

use yrc\api\actions\AuthenticationAction;
use app\models\User\Token;
use yrc\rest\Action as RestAction;

use yii\web\UnauthorizedHttpException;
use Yii;

/**
 * @class RefreshAction
 * Handles token refresh
 */
class RefreshAction extends RestAction
{
    /**
     * [POST] /api/v1/user/refresh
     * Refreshes the user's token
     * @return bool
     */
    public static function post($params)
    {
        // Get the token
        $token = AuthenticationAction::getAccessTokenFromHeader();
            
        // If we can delete the token, send a newly generated token out
        if ($token->delete()) {
            return Token::generate(Yii::$app->user->id);
        }

        // Return false for any other reasons
        return false;
    }
}