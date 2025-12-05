<?php

namespace nocego\yii2\wallet\rest;

use nocego\yii2\wallet\models\PKPassModel;
use nocego\yii2\wallet\Module;
use PKPass\PKPassException;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\filters\AccessControl;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\helpers\ArrayHelper;
use yii\helpers\ReplaceArrayValue;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;

class PkpassController extends Controller
{
    /**
     * {@inheritDoc}
     */
    public function behaviors(): array
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['get-ticket'],
                        'roles' => Module::getInstance()->canManageTickets,
                    ]
                ]
            ],
            'authenticator' => new ReplaceArrayValue([
                'class' => CompositeAuth::class,
                'authMethods' => [
                    [
                        'class' => HttpBearerAuth::class,
                        'realm' => 'rest'
                    ]
                ],
            ]),
            'contentNegotiator' => [
                'languages' => [
                    'de-CH',
                    'en-US',
                    'fr-CH',
                    'it-CH'
                ]
            ]
        ]);
    }

    /**
     * Get a pkpass ticket object
     *
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws PKPassException
     * @throws Exception
     *
     * @return void (outputs the pkpass file directly in the function)
     */
    public function actionGetTicket(): void
    {
        $pkPass = new PkpassModel();
        $pkPass->getTicket(Yii::$app->request->getBodyParams());
    }
}
