<?php

namespace yrc\api\actions;

use yrc\api\actions\AuthenticationAction;
use app\models\Token;
use yrc\rest\Action as RestAction;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * @class RefreshAction
 * Handles token refresh
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
            $tokens = ArrayHelper::merge($this->extraAttributes, Token::generate(Yii::$app->user->id));
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
