<?php

namespace yrc\actions;

use common\forms\Activation;
use yrc\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * Handles token refresh
 * @class ActivationAction
 */
class ActivationAction extends RestAction
{
    /**
     * Activates a user given their activation token
     * @return mixed
     */
    public function post($params)
    {
        $model = new Activation;
        
        if ($model->load(['Activation' => Yii::$app->request->post()])) {
            if ($model->validate()) {
                return $model->activate();
            }

            throw new HttpException(400, \json_encode($model->getErrors()));
        }
            
        return false;
    }
}
