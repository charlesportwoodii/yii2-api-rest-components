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