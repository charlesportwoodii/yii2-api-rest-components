<?php

namespace yrc\rest;

use yii\rest\Controller as RestController;
use yii\filters\Cors;
use yii\filters\RateLimiter;
use yii\filters\VerbFilter;
use yii\filters\ContentNegotiator;
use yii\web\HttpException;
use yrc\web\Response;

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
     * Allowed HTTP verbs
     * @var array $httpVerbs
     */
    private $httpVerbs = ['post', 'get', 'delete', 'put', 'patch', 'options', 'head'];
    
    /**
     * Global access filter
     */
    public function beforeAction($action)
    {
        $parent = parent::beforeAction($action);

        // Check the global access control header
        if (!Yii::$app->yrc->checkAccessHeader(Yii::$app->request)) {
            throw new HttpException(401);
        }

        return $parent;
    }

    /**
     * RestController automatically applies HTTP verb filtering and CORS headers
     * @return array
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $authenticator = false;

        if (isset($behaviors['authenticator'])) {
            $authenticator = $behaviors['authenticator'];
            unset($behaviors['authenticator']);
        }

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/vnd.25519+json' => Response::FORMAT_JSON25519,
                'application/xml' => Response::FORMAT_XML,
            ]
        ];

        $behaviors['corsFilter'] = [
            'class' => Cors::className(),
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => $this->getHttpVerbMethodsFromClass($this->actions()[$this->action->id]),
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Expose-Headers' => [
                    'Access-Control-Allow-Origin',
                    'X-Pagination-Per-Page',
                    'X-Pagination-Total-Count',
                    'X-Pagination-Current-Page',
                    'X-Pagination-Page-Count',
                    'Allow',
                    'X-Rate-Limit-Limit',
                    'X-Rate-Limit-Remaining',
                    'X-Rate-Limit-Reset'
                ],
            ]
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::className(),
            'actions' => $this->getVerbFilterActionMap()
        ];

        // Move authenticator after verbs and cors
        if ($authenticator != false) {
            $behaviors['authenticator'] = $authenticator;
        }

        $behaviors['rateLimiter'] = [
            'class' => RateLimiter::className(),
            'enableRateLimitHeaders' => true
        ];

        // Manually add the ACAO header because Yii2 is terrible at doing it
        header("Access-Control-Allow-Origin: " . \implode(',', $behaviors['corsFilter']['cors']['Origin']));
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

        if (is_array($class)) {
            $class = $class['class'];
        }

        // Fetch the public methods for the class then filter them out by the http verbs
        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if (\in_array($method->name, $this->httpVerbs)) {
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
