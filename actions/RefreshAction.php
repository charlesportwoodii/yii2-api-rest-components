<?php

namespace yrc\actions;

use common\models\RefreshToken;
use yrc\web\Json25519Parser;
use yrc\rest\Action as RestAction;
use yii\helpers\ArrayHelper;
use yii\web\HttpException;
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
        $refreshToken = Yii::$app->request->post('refresh_token', false);
        $model = RefreshToken::find()->where([
            'user_id' => Yii::$app->user->id,
            'token' => $refreshToken
        ])->one();

        if ($model === null) {
            throw new HttpException(401, Yii::t('yrc', 'The refresh token provided is either not valid, or has expired.'));
        }

        // If we can delete the token, send a newly generated token out
        if ($model->delete()) {
            // Merge any extra attributes with the generated tokens
            $tokenClass = (Yii::$app->user->identityClass::TOKEN_CLASS);
            $tokens = ArrayHelper::merge($this->extraAttributes, $tokenClass::generate(Yii::$app->user->id)->getAuthResponse());
            // Merge the identity attributes
            foreach ($this->identityAttributes as $attr) {
                $tokens[$attr] = Yii::$app->user->getIdentity()->$attr;
            }
            return $tokens;
        }

        throw new HttpException(400, Yii::t('yrc', 'An unexpected error occurred. Please re-authenticate.'));
    }
}
