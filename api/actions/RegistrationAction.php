<?php

namespace yrc\api\actions;

use app\forms\Register;
use yrc\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * @class RegistrationAction
 * Handles user registration
 */
class RegistrationAction extends RestAction
{
    /**
     * [POST] /api/[...]/register
     * Handles registration of users
     * @return mixed
     */
    public static function post($params)
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