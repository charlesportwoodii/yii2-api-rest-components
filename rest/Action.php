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
     * The access control list for this endpoint
     * @var array
     */
    public $acl;

    /**
     * Action runner
     *
     * @param array $args
     * @return mixed
     * @throws HttpException
     */
    public function run(array $args = [])
    {
        $method = strtolower(Yii::$app->request->method);
        
        // Make sure the method exists before trying to call it
        if (method_exists(get_called_class(), $method)) {
            return $this->$method($args);
        }

        // Return a 405 if the method isn't implemented
        // When coupled with RestController, this should _never_ get called
        // But this is the correct response if for some reason it isn't
        throw new HttpException(405);
    }

    /**
     * HTTP Options defaults
     * @param array $params
     * @return void
     */
    public function options(array $params = [])
    {
        Yii::$app->response->statusCode = 204;
        return;
    }
}