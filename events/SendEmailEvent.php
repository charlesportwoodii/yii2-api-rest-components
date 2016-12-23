<?php

namespace yrc\events;
use Yii;

final class SendEmailEvent extends \yrc\events\AbstractEvent
{
    public $viewFile;
    public $subject;
    public $destination;
    public $locales;
    public $viewParams;

    public function run()
    {
        // Default to this view file
        $viewFilePath = '@app/views/mail/en-US/' . $this->viewFile . '.twig';
        
        // Scan the "Accept-Language" header to see if we have a better viewfile
        foreach ($this->locales as $language) {
            $vfp = '@app/views/mail/' . $language . '/' . $viewFile . '.twig';
            if (\file_exists(Yii::getAlias($vfp))) {
                $viewFilePath = $vfp;
                break;
            }
        }

        if (!\file_exists(Yii::getAlias($viewFilePath))) {
            Yii::warning(sprintf('The requested view (%s) file does not exist', $viewFilePath));
            return $this->handled();
        }

        $view = Yii::$app->view->renderFile($viewFilePath, $this->viewParams);
        try {
            $sent = Yii::$app->mailer->compose()
                ->setFrom(Yii::$app->yrc->fromEmail)
                ->setTo($this->destination)
                ->setSubject($this->subject)
                ->setHtmlBody($view)
                ->send();
            
            return $this->handled();
        } catch (\Exception $e) {
            Yii::error('Failed to send email notification: ' . $e->getMessage());
            return $this->handled();
        }
    }
}