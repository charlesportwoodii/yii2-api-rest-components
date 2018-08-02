<?php

namespace yrc\actions;

use common\forms\Registration;
use yrc\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * Handles user registration
 * @class RegisterAction
 */
class RegisterAction extends RestAction
{
    /**
     * Handles registration of users
     * @return mixed
     */
    public function post($params)
    {
        $model = new Registration;
        
        if ($model->load(['Registration' => Yii::$app->request->post()])) {
            if ($model->register()) {
                return true;
            }

            throw new HttpException(400, \json_encode($model->getErrors()));
        }
            
        return false;
    }
}
