<?php

namespace yrc\jobs\notifications\email;

use Yii;
use yrc\jobs\notifications\email\AbstractEmailNotification;

class ActivationNotification extends AbstractEmailNotification
{
    protected function getSubject()
    {
        return Yii::t('yrc', 'Activate Your Account');
    }

    protected $viewFile = 'Register.twig';
}
