<?php

namespace yrc\rest;

use yii\web\HttpException;
use yii\helpers\ArrayHelper;
use Yii;
use ReflectionProperty;
use ReflectionClass;

abstract class Action extends \yii\base\Action
{
    /**
     * Action runner
     *
     * @param varadic $args
     * @return mixed
     */
    public function run(array $args = [])
    {
        $method = Yii::$app->request->method;
        $method = strtolower($method);
        
        // Make sure the method exists before trying to call it
        if (method_exists(get_called_class(), $method)) {
            return $this->$method($args);
        }

        // Return a 405 if the method isn't implemented
        // When coupled with RestController, this should _never_ get called
        // But this is the correct response if for some reason it isn't
        throw new HttpException(405);
    }

    public function options($params)
    {
        Yii::$app->response->statusCode = 204;
        return;
    }
}