<?php

namespace nocego\yii2\wallet\rest;

use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use Google\Service\Exception;
use nocego\yii2\wallet\models\GoogleWallet;
use nocego\yii2\wallet\Module;
use Yii;
use yii\base\InvalidConfigException;
use yii\filters\AccessControl;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\helpers\ArrayHelper;
use yii\helpers\ReplaceArrayValue;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;

class GoogleWalletController extends Controller
{
    public function behaviors(): array
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'create-class',
                            'class-exists',
                            'create-ticket',
                            'get-ticket',
                            'expire-ticket'
                        ],
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
     * Create a class
     *
     * @return bool
     *
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws Exception
     * @throws \yii\base\Exception
     */
    public function actionCreateClass(): bool
    {
        $post = Yii::$app->request->getBodyParams();
        if (!isset($post['classSuffix'])) {
            throw new BadRequestHttpException('classSuffix is required');
        }
        if (!isset($post['classFields'])) {
            throw new BadRequestHttpException('classSuffix is required');
        }

        $params = [
            $post['classSuffix'],
            $post['classFields'],
        ];
        if (isset($post['classType'])) {
            $params[] = $post['classType'];
        }
        $wallet = new GoogleWallet();
        $success = $wallet->createClass(...$params);


        if ($success) {
            Yii::$app->response->statusCode = 201;
            return true;
        } else {
            Yii::$app->response->statusCode = 500;
            return false;
        }
    }

    /**
     * Check if a class exists
     *
     * @param String $classSuffix
     *
     * @return bool
     * @throws Exception
     */
    public function actionClassExists(string $classSuffix): bool
    {
        $wallet = new GoogleWallet();
        return $wallet->classExists($classSuffix);
    }

    /**
     * Create a ticket object
     *
     * @return array
     *
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws DateInvalidTimeZoneException
     * @throws \yii\base\Exception
     * @throws DateMalformedIntervalStringException
     */
    public function actionCreateTicket(): array
    {
        $post = Yii::$app->request->getBodyParams();
        if (!isset($post['classSuffix'])) {
            throw new BadRequestHttpException('classSuffix is required');
        }
        if (!isset($post['objectSuffix'])) {
            throw new BadRequestHttpException('objectSuffix is required');
        }
        if (isset($post['ticketFields'])) {
            $ticketFields = $post['ticketFields'];
        }

        $wallet = new GoogleWallet();
        $success = $wallet->createTicket(
            $post['classSuffix'],
            $post['objectSuffix'],
            $ticketFields ?? null,
        );

        if ($success) {
            Yii::$app->response->statusCode = 201;
            return [
                'passObjectId' => $success
            ];
        } else {
            Yii::$app->response->statusCode = 500;
            return [];
        }
    }

    /**
     * Get a ticket object
     *
     * @param string $objectSuffix
     *
     * @return string|array
     */
    public function actionGetTicket(string $objectSuffix): string|array
    {
        $wallet = new GoogleWallet();
        return $wallet->getTicket($objectSuffix);
    }

    /**
     * Expire a ticket object
     *
     * @param string $objectSuffix
     *
     * @return bool
     * @throws Exception
     */
    public function actionExpireTicket(string $objectSuffix): bool
    {
        $wallet = new GoogleWallet();
        return $wallet->expireTicket($objectSuffix);
    }
}
