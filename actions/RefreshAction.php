<?php

namespace yrc\actions;

use yrc\actions\AuthenticationAction;
use yrc\web\Json25519Parser;
use yrc\rest\Action as RestAction;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * Handles token refresh
 * @class RefreshAction
 */
class RefreshAction extends RestAction
{
    public $extraAttributes = [];

    public $identityAttributes = [];

    /**
     * Refreshes the user's token
     * @return bool
     */
    public function post($params)
    {
        // Get the token
        $token = AuthenticationAction::getAccessTokenFromHeader();
        
        $refreshToken = Yii::$app->request->post('refresh_token', false);
        if ($refreshToken !== $token->refresh_token) {
            return false;
        }

        // If we can delete the token, send a newly generated token out
        if ($token->delete()) {
            // Merge any extra attributes with the generated tokens
            $tokenClass = (Yii::$app->user->identityClass::TOKEN_CLASS);
            $tokens = ArrayHelper::merge($this->extraAttributes, $tokenClass::generate(Yii::$app->user->id)->getAuthResponse());
            // Merge the identity attributes
            foreach ($this->identityAttributes as $attr) {
                $tokens[$attr] = Yii::$app->user->getIdentity()->$attr;
            }
            return $tokens;
        }
        // Return false for any other reasons
        return false;
    }
}
