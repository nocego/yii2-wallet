<?php

namespace nocego\yii2\wallet;

use Exception;
use Yii;
use yii\base\BootstrapInterface;
use yii\helpers\ArrayHelper;
use yii\web\Application;

class Module extends \yii\base\Module implements BootstrapInterface
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'nocego\yii2\wallet\controllers';

    /**
     * {@inheritdoc}
     */
    public $defaultRoute = 'wallet';

    /**
     * array of the auth_items that can use this module
     */
    public array $canManageTickets = [];

    /**
     * Google Wallet configuration array
     *
     * e.g.:
     *
     * ```
     *
     *      [
     *          'issuerId' => '1234567891011121314',
     *          'googleServiceAccountCredentials' => [
     *              "type" => "type",
     *              "project_id" => "project_id",
     *              "private_key_id" => "private_key_id",
     *              "private_key" => "-----BEGIN PRIVATE KEY-----\nprivate_key\n",
     *              "client_email" => "client_email",
     *              "client_id" => "client_id",
     *              "auth_uri" => "auth_uri",
     *              "token_uri" => "token_uri",
     *              "auth_provider_x509_cert_url" => "auth_provider_x509_cert_url",
     *              "client_x509_cert_url" => "client_x509_cert_url",
     *              "universe_domain" => "universe_domain",
     *          ],
     *          'timeToAddToValidTimeIntervalEnd' => 'P7D',
     *      ]
     *
     * ```
     */
    public array $googleWalletConfig = [];

    /**
     * pkPass configuration array
     *
     * e.g.:
     * ```
     *      [
     *          'certificatePath' => /srv/www/www.test.org/certificates/pkpass/certificates.p12,
     *          'certificatePassword' => '12.34#56',
     *          'organizationName' => 'MyOrganization',
     *          'passTypeIdentifier' => 'pass.org.test.myapp',
     *          'teamIdentifier' => 'AB1CDEFG2K',
     *          'iconUrl' => 'https://test.org/files/Wallet/icon.png',
     *          'logoUrl' => 'https://test.org/files/Wallet/icon.png',
     *      ]
     * ```
     */
    public array $pkPassConfig = [];

    /**
     * {@inheritDoc}
     */
    public function init(): void
    {
        parent::init();
    }

//    /**
//     * Init module translations
//     *
//     * @return void
//     */
//    public function registerTranslations(): void
//    {
//        Yii::$app->i18n->translations['nocego/wallet*'] = [
//            'class' => 'yii\i18n\GettextMessageSource',
//            'sourceLanguage' => 'en-US',
//            'basePath' => $this->getBasePath() . '/messages'
//        ];
//    }

    /**
     * {@inheritDoc}
     */
    public function bootstrap($app): void
    {
        if ($app instanceof yii\console\Application) {
            $this->controllerNamespace = 'nocego\yii2\wallet\commands';
        } else {
            /** @var Application $app */
            try {
                $route = ArrayHelper::getValue($app->request->resolve(), '0');
            } catch (Exception) {
                $route = '';
            }

            if (str_starts_with($route, 'rest/')) {
                $this->controllerNamespace = 'nocego\yii2\wallet\rest';
                $app->user->enableSession = false;
                $app->user->logout();
            }

            $app->urlManager->addRules([
                [
                    'class' => 'yii\rest\UrlRule',
                    'prefix' => 'rest',
                    'controller' => [
                        $this->id . '/google-wallet',
                    ],
                    'extraPatterns' => [
                        'POST class' => 'create-class',
                        'GET {classSuffix}/class-exists' => 'class-exists',
                        'POST ticket' => 'create-ticket',
                        'GET ticket/{objectSuffix}' => 'get-ticket',
                        'DELETE ticket/{objectSuffix}' => 'expire-ticket',
                    ],
                    'tokens' => [
                        '{classSuffix}' => '<classSuffix:[^/]+>',
                        '{objectSuffix}' => '<objectSuffix:[^/]+>',
                    ],
                    'pluralize' => false,
                ],
            ], false);
            $app->urlManager->addRules([
                [
                    'class' => 'yii\rest\UrlRule',
                    'prefix' => 'rest',
                    'controller' => [
                        $this->id . '/pkpass',
                    ],
                    'patterns' => [
                        'POST' => 'get-ticket',
                    ],
                    'pluralize' => false,
                ],
            ], false);

//            ArrayHelper::setValue($app->view->renderers, 'tpl.widgets.functions.EventsMinmod', 'tonic\hq\reservation\widgets\EventsMinmod');
        }
    }
}
