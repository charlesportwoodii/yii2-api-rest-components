<?php

namespace yrc\jobs\notifications\email;

use Yii;
use yrc\jobs\notifications\email\AbstractEmailNotification;

class PasswordChangedNotification extends AbstractEmailNotification
{
    protected function getSubject()
    {
        return Yii::t('yrc', 'Password Changed Notification');
    }

    protected $viewFile = 'PasswordChanged.twig';
}
