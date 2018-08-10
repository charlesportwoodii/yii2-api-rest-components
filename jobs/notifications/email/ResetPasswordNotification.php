<?php

namespace yrc\jobs\notifications\email;

use Yii;
use yrc\jobs\notifications\email\AbstractEmailNotification;

class ResetPasswordNotification extends AbstractEmailNotification
{
    protected function getSubject()
    {
        return Yii::t('yrc', 'Reset Your Password');
    }

    protected $viewFile = 'ResetPassword.twig';
}
