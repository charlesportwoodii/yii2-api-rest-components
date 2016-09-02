<?php

namespace yrc\api\forms;

use Base32\Base32;
use Yii;

/**
 * @class ChangeEmail
 * The form for validating the activation form
 */
abstract class ChangeEmail extends \yii\base\model
{
    /**
     * The new email to be changed
     * @var string $email
     */
    public $email;

    /**
     * The user's current password
     * @var string $password
     */
    public $password;

    /**
     * The user whose information we want to change
     * @var User $user
     */
    private $user;

    /**
     * Sets the user object
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }
}