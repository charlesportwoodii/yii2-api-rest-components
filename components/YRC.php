<?php

namespace yrc\components;

use Yii;
use yii\base\Object;

/**
 * Yii2 Rest Component 
 * @class YRC
 */
class YRC extends Object
{
    /**
     * The user class
     * @var string
     */
    public $userClass;

    /**
     * The email address that emails should be sent from
     * @var string
     */
    public $fromEmail;

    /**
     * Whether or not we should really send emails
     * @var boolean
     */
    public $realSend = true;

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

        // If the header isn't set, deny
        if ($header === null) {
            return false;
        }

        // Allow if the header values match
        if (\hash_equals($this->accessHeaderSecret, $header)) {
            return true;
        }
        
        // Deny by default
        return false;
    }

    /**
     * Send an email using the provided viewfile and parameters
     * @param string $viewFile
     * @param string $subject
     * @param string $email
     * @param array $params
     * @return boolean
     */
    public function sendEmail($viewFile, $subject, $email, array $params = [])
    {
        // Default to this view file
        $viewFilePath = '@app/views/mail/en-US/' . $viewFile . '.twig';
        
        // Scan the "Accept-Language" header to see if we have a better viewfile
        $languages = Yii::$app->request->getAcceptableLanguages();
        foreach ($languages as $language) {
            $vfp = '@app/views/mail/' . $language . '/' . $viewFile . '.twig';
            if (\file_exists(Yii::getAlias($vfp))) {
                $viewFilePath = $vfp;
                break;
            }
        }

        if (!\file_exists(Yii::getAlias($viewFilePath))) {
            Yii::warning(sprintf('The requested view (%s) file does not exist', $viewFilePath));
            return false;
        }

        $view = Yii::$app->view->renderFile($viewFilePath, $params);

        if ($this->realSend === true) {
            return Yii::$app->mailer->compose()
                ->setFrom($this->fromEmail)
                ->setTo($email)
                ->setSubject($subject)
                ->setHtmlBody($view)
                ->send();
        }

        return true;
    }
}