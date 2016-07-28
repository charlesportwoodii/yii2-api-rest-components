<?php

namespace charlesportwoodii\yii2\rest;

use yii\rest\Controller as RestController;
use yii\filters\Cors;
use yii\filters\VerbFilter;
use yii\web\HttpException;
use Yii;

use ReflectionClass;
use ReflectionMethod;

/**
 * Implements Restful API controller interfaces
 * @class Controller
 */
class Controller extends RestController
{
    /**
     * RestController automatically applies HTTP verb filtering and CORS headers
     * @return array
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors['corsFilter'] = [
            'class' => Cors::className(),
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => $this->getHttpVerbMethodsFromClass($this->actions()[$this->action->id]),
                'Access-Control-Request-Headers' => [
                    'Origin',
                    'X-Requested-With',
                    'Content-Type',
                    'Accept',
                    'Authorization',
                    'X-Date'
                ],
                'Access-Control-Expose-Headers' => [
                    'X-Pagination-Per-Page',
                    'X-Pagination-Total-Count',
                    'X-Pagination-Current-Page',
                    'X-Pagination-Page-Count',
                    'Allow'
                ],
            ]
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::className(),
            'actions' => $this->getVerbFilterActionMap()
        ];

        return $behaviors;
    }

    /**
     * Retrieves the HTTP verb list
     * @param string $class
     * @return array
     */
    private function getHttpVerbMethodsFromClass($class)
    {
        $result = [];
        $httpVerbs = ['post', 'get', 'delete', 'put', 'patch', 'options', 'head'];

        // Fetch the static methods for the class
        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC);
        foreach ($methods as $method) {
            if (in_array($method->name, $httpVerbs)) {
                $result[] = $method->name;
            }
        }

        return $result;
    }

    /**
     * Convers self::actions() for automatic verb filtering
     * @return array
     */
    private function getVerbFilterActionMap()
    {
        $actions = $this->actions();

        // Only apply this filtering for ActionMapped Controllers
        if (empty($actions)) {
            return [];
        }

        $actionMap = [];
        
        // Iterate over all the actions, and automatically determine the methods implemented
        foreach ($actions as $actionName => $params) {
            static $class = null;
            if (is_array($params)) {
                $class = $params['class'];
            } else {
                $class = $params;
            }

            $actionMap[$actionName] = $this->getHttpVerbMethodsFromClass($class);
        }

        return $actionMap;
    }
}