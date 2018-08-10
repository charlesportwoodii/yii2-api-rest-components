<?php

namespace yrc\actions;

use common\forms\Login;
use ncryptf\Authorization;
use yrc\filters\auth\HMACSignatureAuth;
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
        $params = Authorization::extractParamsFromHeaderString(Yii::$app->request->getHeaders()->get(HMACSignatureAuth::AUTHORIZATION_HEADER));
        if ($params) {
            if ($token = $this->getTokenFromAccessToken($params['access_token'])) {
                return (bool)$token->delete();
            }
        }

        return false;
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
}
