<?php declare(strict_types=1);

namespace app\jobs\notifications\email;

use RPQ\Server\AbstractJob;
use yii\mail\MessageInterface;
use Yii;

/**
 * @abstract AbstractEmailNotification
 * All email notifications should extend this abstract class to inherit basic queue based email sending capabilities
 * @property array $origin
 * @property string $subject
 * @property string $viewFile
 */
abstract class AbstractEmailNotification extends AbstractJob
{
    /**
     * The sender email address and name as an array.
     * @var array
     */
    protected $origin = [
        'noreply@example.com' => 'No Reply'
    ];

    /**
     * The email subject. This should be explicitly declared in extensions
     * @var string
     */
    protected $subject;

    /**
     * The name of the viewfile to use within @app/views/email/(html|text)
     * @var string
     */
    protected $viewFile;

    /**
     * Generates a message composition and returns a MessageInterface
     * @param string|array $to 
     * @param array $args
     * @return yii\mail\MessageInterface
     */
    protected function compose($to, array $args = []) : MessageInterface
    {
        // Grab the \yii\console\Controller instance, and setup the view paths
        // Yii::$app->mailer->compose() can't handle .twig extension, so the render/renderPartial methods need to be called manually
        $controller = Yii::$app->controller;
        $controller->setViewPath(Yii::$app->mailer->viewPath);

        // Compose a message, and return a MessageInterface if the consumer needs to modify the message interface in any way.
        $composition = Yii::$app->mailer->compose()
            ->setFrom($this->origin)
            ->setSubject(Yii::t('app', $this->subject)) // The subject should be translated, if possible
            ->setTo($to);
        
        $controller->layout = Yii::$app->mailer->htmlLayout;
        $composition->setHtmlBody($controller->render("html/{$this->viewFile}", $args));

        $controller->layout = Yii::$app->mailer->textLayout;
        $composition->setTextBody($controller->render("text/{$this->viewFile}", $args));

        return $composition;
    }

    /**
     * Implementation of RPQ\AbstractJob\Perform to send the actual email
     * @param array $args
     * @return int
     */
    public function perform(array $args = []) : int
    {
        $message = $this->compose($args['email'], $args);
        
        if ($message->send()) {
            return 0;
        }

        return 1;
    }
}