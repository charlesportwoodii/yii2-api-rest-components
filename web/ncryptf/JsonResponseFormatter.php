<?php declare(strict_types=1);

namespace yrc\web\ncryptf;

use ncryptf\Request;
use ncryptf\Response;
use yrc\models\redis\EncryptionKey;
use yii\web\NotAcceptableHttpException;
use yii\web\HttpException;
use Yii;

use Exception;

class JsonResponseFormatter extends \yrc\web\JsonResponseFormatter
{
    /**
     * Take the response generated by JsonResponseFormatter and encrypt it
     * @param array $response
     */
    protected function formatJson($response)
    {
        // Generate a new encryption key
        $key = EncryptionKey::generate();
        $request = Yii::$app->request;
        if ($request->method === 'GET' && $request->getRawBody() === '' && $request->getHeaders()->get('Content-Type') === 'application/json') {
            $headerExtractionVersion = 1;
        } else {
            $headerExtractionVersion = Response::getVersion(\base64_decode($request->getRawBody()));
        }

        $headers = $response->getHeaders();

        if ($headerExtractionVersion === 2) {
            $rawPublic = Response::getPublicKeyFromResponse(\base64_decode($request->getRawBody()));
            $version = 2;
        } else {
            $public = $request->getHeaders()->get('x-pubkey', null);
            $version = 1;

            if ($public === null) {
                $response->statusCode = 400;
                $response->content = '';
                $response->getHeaders('x-reason', Yii::t('yrc', 'Accept: application/vnd.ncryptf+json requires x-pubkey header to be set.'));
                return;
            }

            $rawPublic = \base64_decode($public);
            if (strlen($rawPublic) !== 32) {
                $response->statusCode = 400;
                $headers->set('x-reason', Yii::t('yrc', 'Public key is not 32 bytes in length.'));
                return;
            }
        }

        // Upgrade GET request with empty bodies
        if ($request->method === 'GET' && $request->getRawBody() === '') {
            $version = 2;
        }

        parent::formatJson($response);
        $headers->set('Content-Type', 'application/vnd.ncryptf+json; charset=UTF-8');

        if (!Yii::$app->user->isGuest) {
            $token = Yii::$app->user->getIdentity()->getToken();

            if ($token === null) {
                $response->statusCode = 406;
                $response->content = '';
                Yii::warning([
                    'message' => 'Could not fetch token keypair. Unable to generate encrypted response.'
                ], 'yrc');
                return;
            }

            $r = new Request(
                $key->getBoxSecretKey(),
                \base64_decode($token->secret_sign_kp)
            );

            $nonce = \random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);

            $content = $r->encrypt(
                $response->content,
                $rawPublic,
                $version,
                $version === 2 ? null : $nonce
            );

            if ($version === 1) {
                $signature = $r->sign($response->content);
                // Sign the raw response and send the signature alongside the header
                $headers->set('x-sigpubkey', \base64_encode($token->getSignPublicKey()));
                $headers->set('x-signature', \base64_encode($signature));
                $headers->set('x-pubkey-expiration', $key->getPublicKeyExpiration());
                $headers->set('x-nonce', \base64_encode($nonce));
                $headers->set('x-pubkey', \base64_encode($key->getBoxPublicKey()));
            }

            $headers->set('x-hashid', $key->hash);
            $response->content = \base64_encode($content);
            return;
        }
    }
}
