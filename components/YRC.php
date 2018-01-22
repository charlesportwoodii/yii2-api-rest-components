<?php

namespace yrc\components;

use Yii;
use yii\base\BaseObject;

/**
 * Yii2 Rest Component 
 * @class YRC
 */
class YRC extends BaseObject
{
    /**
     * The user class
     * @var string
     */
    public $user;

    /**
     * The token class
     * @var string
     */
    public $token;

    /**
     * The access header
     * If set access to controller actions is granted if and only if the HTTP header value
     * identified by this parameters equals the $accessHeaderSecret property
     * @return mixed
     */
    public $accessHeader;

    /**
     * The access header secret value
     * @return mixed
     */
    public $accessHeaderSecret;

    /**
     * Helper method to get the user class
     * @return string
     */
    public function getUserClass()
    {
        return $this->user['class'];
    }

     /**
     * Helper method to get the token class
     * @return string
     */
    public function getTokenClass()
    {
        return $this->token['class'];
    }

    /**
     * Helper method to check the access header
     * @return boolean
     */
    public function checkAccessHeader($request)
    {
        // Both the access header and access header secret must be set for this check to validate
        if ($this->accessHeader === null || $this->accessHeaderSecret === null) {
            return true;
        }

        // Fetch the access header from the request
        $header = $request->getHeaders()->get($this->accessHeader);

        // Allow if the header values match
        if (\hash_equals($this->accessHeaderSecret, $header)) {
            return true;
        }
        
        // Deny by default
        return false;
    }
}
