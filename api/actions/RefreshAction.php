<?php

namespace yrc\api\actions;

use yrc\api\actions\AuthenticationAction;
use app\models\Token;
use yrc\api\models\TokenKeyPair;
use yrc\web\Json25519Parser;
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
        $publicKey = Yii::$app->request->post('public_key', null);

        // If the current request is encrypted, prevent the request from being downgraded
        // @todo: Modify the request object to determine if a request is encrypted or not
        //if (Yii::$app->request->isEncrypted() && $publicKey === null) {
        //    throw new HttpException(400, Yii::t('yrc', 'Encrypted communications cannot be downgraded'));
        //}

        if ($refreshToken !== $token->refresh_token) {
            return false;
        }

        // If we can delete the token, send a newly generated token out
        if ($token->delete()) {
            // Generate a new Token with the newly provided public key if one is set
            $newToken = Token::generate(Yii::$app->user->id, $publicKey);

            // If a public key is provided, delete the old TokenKeyPair
            if ($publicKey !== null) {
                $kpToken = Json25519Parser::getTokenFromHash(Yii::$app->request->getHeaders()->get(Json25519Parser::HASH_HEADER, null));
                $kpToken->delete();

                // Identity the user by this new token
                // The current session is now encrypted by the new public key
                Yii::$app->user->loginByAccessToken($newToken);
            }

            // Merge the response attributes
            $tokens = ArrayHelper::merge($this->extraAttributes, $newToken->getAuthResponse());

            // Merge the identity attributes
            foreach ($this->identityAttributes as $attr) {
                $tokens[$attr] = Yii::$app->user->getIdentity()->$attr;
            }

            // And finally return the entire response
            return $tokens;
        }

        // Return false for any other reasons
        return false;
    }
}
