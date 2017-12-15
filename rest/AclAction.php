<?php

namespace yrc\rest;

use yrc\rest\Action;
use Yii;

use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\HttpException;

abstract class AclAction extends Action
{
    /**
     * Access Control List
     * @var array $acl
     */
    public $acl = [];

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
        
        // If the requested HTTP method exists AND an ACL is defined for it, apply ACL rules
        if (isset($this->acl[$method]) && method_exists(get_called_class(), $method)) {
            foreach ($this->acl[$method] as $role) {
                // @ is a special symbol meaning a user must be authenticated
                if ($role === '@') {
                    if (Yii::$app->user->isGuest) {
                        throw new UnauthorizedHttpException;
                    }
                } else {
                    // All other items are considered to be a role or permissions within RBAC
                    if (!Yii::$app->user->can($role)) {
                        throw new ForbiddenHttpException;
                    }
                }
            }
        }

        return parent::run($args);
    }
}