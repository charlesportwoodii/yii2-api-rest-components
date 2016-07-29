<?php

namespace charlesportwoodii\yii2\api\actions;

use charlesportwoodii\yii2\api\forms\Register;
use charlesportwoodii\yii2\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * @class RegistrationAction
 * Handles user registration
 */
final class RegistrationAction extends RestAction
{
    /**
     * [POST] /api/v1/register
     * Handles registration of users
     * @return mixed
     */
    public static function post($params)
    {
        $model = new Registration;
        
        if ($model->load(['Registration' => Yii::$app->request->post()])) {
            if ($model->register()) {
                return true;
            } else {
                throw new HttpException(400, \json_encode($model->getErrors()));
            }
        }
            
        return false;
    }
}