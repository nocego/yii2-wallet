<?php

namespace nocego\yii2\wallet\models;

use DateInterval;
use DateInvalidTimeZoneException;
use DateMalformedIntervalStringException;
use DateTimeImmutable;
use DateTimeZone;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Client as GoogleClient;
use Google\Collection;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Walletobjects;
use Google\Service\Walletobjects\Barcode;
use Google\Service\Walletobjects\EventTicketObject;
use Google\Service\Walletobjects\Image;
use Google\Service\Walletobjects\ImageModuleData;
use Google\Service\Walletobjects\ImageUri;
use Google\Service\Walletobjects\LinksModuleData;
use Google\Service\Walletobjects\LocalizedString;
use Google\Service\Walletobjects\TextModuleData;
use Google\Service\Walletobjects\TranslatedString;
use Google\Service\Walletobjects\Uri;
use InvalidArgumentException;
use nocego\yii2\wallet\Module;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Json;

class GoogleWallet extends Model
{
    /**
     * The Google API Client
     * @see https://github.com/google/google-api-php-client
     */
    public GoogleClient $client;

    /**
     * Path to service account key file from Google Cloud Console.
     */
    public string|array $keyFilePath;

    /**
     * Issuer ID for Google Wallet
     */
    public string $issuerId;

    /**
     * Service account credentials for Google Wallet APIs.
     */
    public ServiceAccountCredentials $credentials;

    /**
     * Google Wallet service client.
     */
    public Walletobjects $service;

    /**
     * Required configuration keys
     */
    private const array REQUIRED_CONFIG_KEYS = [
        'issuerId',
        'googleServiceAccountCredentials',
    ];

    /**
     * Supported ticket types mapping
     */
    private const array SUPPORTED_TICKET_TYPES = [
        Walletobjects\EventTicketClass::class => [EventTicketObject::class, 'eventticketobject'],
        Walletobjects\FlightClass::class => [Walletobjects\FlightObject::class, 'flightobject'],
        Walletobjects\GenericClass::class => [Walletobjects\GenericObject::class, 'genericobject'],
        Walletobjects\GiftCardClass::class => [Walletobjects\GiftCardObject::class, 'giftcardobject'],
        Walletobjects\LoyaltyClass::class => [Walletobjects\LoyaltyObject::class, 'loyaltyobject'],
        Walletobjects\OfferClass::class => [Walletobjects\OfferObject::class, 'offerobject'],
        Walletobjects\TransitClass::class => [Walletobjects\TransitObject::class, 'transitobject'],
    ];


    /**
     * {inheritDoc}
     * @throws GoogleServiceException
     * @throws InvalidConfigException
     * @throws \Google\Exception
     */
    public function init(): void
    {
        parent::init();
        /** @var Module $moduleInstance */
        $moduleInstance = Module::getInstance();

        $this->validateConfig($moduleInstance);

        $this->issuerId = $moduleInstance->googleWalletConfig['issuerId'];

        // todo: check if variable is required or if it is used only once or twice
        $this->keyFilePath = $moduleInstance->googleWalletConfig['googleServiceAccountCredentials'];

        $this->auth();
    }

    /**
     * Create authenticated HTTP client using a service account file.
     *
     * @throws GoogleServiceException
     * @throws \Google\Exception
     */
    public function auth(): void
    {
        $this->credentials = new ServiceAccountCredentials(
            Walletobjects::WALLET_OBJECT_ISSUER,
            $this->keyFilePath
        );

        // Initialize Google Wallet API service
        $this->client = new GoogleClient();
        $this->client->setApplicationName('APPLICATION_NAME');
        $this->client->setScopes(Walletobjects::WALLET_OBJECT_ISSUER);
        $this->client->setAuthConfig($this->keyFilePath);

        $this->service = new Walletobjects($this->client);
    }

    /**
     * Create a class.
     *
     * @param string $classSuffix Developer-defined unique ID for this pass class.
     * @param array $specifiedClassFields The fields to set for the class.
     *  e.g.:
     * ```
     *      [
     *          'eventName' => [
     *              'defaultValue' => [
     *                  'language' => 'en-US',
     *                  'value' => 'My Event Name'
     *              ]
     *          ],
     *          'issuerName' => 'My Issuer Name',
     *      ]
     * ```
     * @param string $classType The type of class to create. Default is 'eventticket'.
     *
     * @return bool whether the class was created successfully
     *
     * @throws Exception
     * @throws GoogleServiceException
     */
    public function createClass(string $classSuffix, array $specifiedClassFields, string $classType = 'eventticket'): bool
    {
        $this->validateClassFields($specifiedClassFields);

        if ($this->classExists($classSuffix)) {
            throw new Exception("Class $this->issuerId.$classSuffix already exists");
        }

        $classFields = [
            'eventId' => "$this->issuerId.$classSuffix",
            'id' => "$this->issuerId.$classSuffix",
            'reviewStatus' => 'UNDER_REVIEW',
        ];

        foreach ($specifiedClassFields as $fieldName => $classField) {
            if (is_array($classField) && isset($classField['defaultValue'])) {
                $specifiedClassFields[$fieldName] = new LocalizedString($classField);
            }
        }
        $classFields = array_merge($classFields, $specifiedClassFields);

        $classInstanceAndService = $this->getClassInstanceAndService($classType, $classFields);

        $classInstanceAndService['service']->insert($classInstanceAndService['class']);

        return true;
    }

    /**
     * Check if a class exists.
     *
     * @param String $classSuffix
     *
     * @return bool
     *
     * @throws GoogleServiceException
     */
    public function classExists(string $classSuffix): bool
    {
        $supportedClassServices = [
            'eventticketclass',
            'flightclass',
            'genericclass',
            'giftcardclass',
            'loyaltyclass',
            'offerclass',
            'transitclass',
        ];

        foreach ($supportedClassServices as $serviceName) {
            try {
                $this->service->$serviceName->get("$this->issuerId.$classSuffix");

                return true;
            } catch (GoogleServiceException $ex) {
                if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] === 'invalidResource') {
                    continue;
                }
                throw $ex;
            }
        }

        return false;
    }

    /**
     * Create a ticket object (returns the object ID also if it already exists).
     *
     * @param string $classSuffix the ticket class suffix
     * @param string $objectSuffix the ticket object suffix
     * @param array|null $specifiedTicketFields
     *
     * @return string
     * @throws DateInvalidTimeZoneException
     * @throws Exception
     * @throws GoogleServiceException
     * @throws InvalidConfigException
     * @throws DateMalformedIntervalStringException
     */
    public function createTicket(
        string $classSuffix,
        string $objectSuffix,
        array  $specifiedTicketFields = null,
    ): string
    {
        if ($this->ticketExists($objectSuffix)) {
            return "$this->issuerId.$objectSuffix";
        }

        $eventTicketClass = $this->getClass($classSuffix);
        $eventTicketArr = [
            'id' => "$this->issuerId.$objectSuffix",
            'classId' => "$eventTicketClass->id",
            'state' => 'ACTIVE',
        ];

        if ($specifiedTicketFields != null) {
            $specifiedTicketFields = $this->transformTicketFields($specifiedTicketFields);
            $eventTicketArr = array_merge($eventTicketArr, $specifiedTicketFields);
        }

        $objectClass = $this->getTicketObjectClass($eventTicketClass);
        $serviceObject = $this->getTicketServiceObject($eventTicketClass);

        $newObject = new $objectClass($eventTicketArr);
        $response = $serviceObject->insert($newObject);

        return $response->id;
    }

    /**
     * Get an Ticket
     *
     * @param string $objectSuffix Developer-defined unique ID for this pass object.
     *
     * @return string|array The pass object ID: "{$issuerId}.{$objectSuffix}" or the object as array
     */
    public function getTicket(string $objectSuffix): string|array
    {
        // Check if the object exists
        try {
            $object = $this->service->eventticketobject->get("$this->issuerId.$objectSuffix");
        } catch (GoogleServiceException $ex) {
            if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] == 'resourceNotFound') {
                print("Object $this->issuerId.$objectSuffix not found!");
            } else {
                // Something else went wrong...
                print_r($ex);
            }
            return "$this->issuerId.$objectSuffix";
        }

        // return $object as array
        return Json::decode(json_encode($object));
    }

    /**
     * Expire an object.
     *
     * Sets the object's state to Expired. If the valid time interval is
     * already set, the pass will expire automatically up to 24 hours after.
     *
     * @param string $objectSuffix Developer-defined unique ID for this pass object.
     *
     * @return bool True on success, false on failure
     * @throws GoogleServiceException
     */
    public function expireTicket(string $objectSuffix): bool
    {
        // Check if the object exists
        $this->service->eventticketobject->get("$this->issuerId.$objectSuffix");

        // Patch the object, setting the pass as expired
        $patchBody = new EventTicketObject([
            'state' => 'EXPIRED'
        ]);

        $this->service->eventticketobject->patch("$this->issuerId.$objectSuffix", $patchBody);

        return true;
    }

    /**
     * Validate required fields for class creation.
     *
     * @param array $specifiedClassFields
     * @return void
     * @throws Exception
     */
    private function validateClassFields(array $specifiedClassFields): void
    {
        $requiredFields = [
            'eventName.defaultValue.language',
            'eventName.defaultValue.value',
            'issuerName',
        ];

        foreach ($requiredFields as $fieldPath) {
            if (!$this->hasNestedKey($specifiedClassFields, $fieldPath)) {
                throw new Exception("$fieldPath must be set in classFields");
            }
        }
    }

    /**
     * Check if a nested key exists in an array.
     *
     * @param array $array
     * @param string $path
     * @return bool
     */
    private function hasNestedKey(array $array, string $path): bool
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return false;
            }
            $array = $array[$key];
        }
        return true;
    }

    /**
     * Get class instance and service based on class type.
     *
     * @param string $classType
     * @param array $classFields
     *
     * @return array
     *
     * @throws Exception
     */
    private function getClassInstanceAndService(string $classType, array $classFields): array
    {
        $classMap = [
            'eventticket' => [Walletobjects\EventTicketClass::class, 'eventticketclass'],
            'flight' => [Walletobjects\FlightClass::class, 'flightclass'],
            'generic' => [Walletobjects\GenericClass::class, 'genericclass'],
            'giftcard' => [Walletobjects\GiftCardClass::class, 'giftcardclass'],
            'loyalty' => [Walletobjects\LoyaltyClass::class, 'loyaltyclass'],
            'offer' => [Walletobjects\OfferClass::class, 'offerclass'],
            'transit' => [Walletobjects\TransitClass::class, 'transitclass'],
        ];

        if (!isset($classMap[$classType])) {
            throw new Exception("Class type $classType is not supported");
        }

        [$className, $serviceName] = $classMap[$classType];
        return [
            'class' => new $className($classFields),
            'service' => $this->service->$serviceName
        ];
    }

    /**
     * Get a class
     *
     * @param string $classSuffix Developer-defined unique ID for this pass class.
     *
     * @return Collection the class object
     *
     * @throws GoogleServiceException
     */
    private function getClass(string $classSuffix): Collection
    {
        $supportedClassServices = [
            'eventticketclass',
            'flightclass',
            'genericclass',
            'giftcardclass',
            'loyaltyclass',
            'offerclass',
            'transitclass',
        ];

        foreach ($supportedClassServices as $serviceName) {
            try {
                return $this->service->$serviceName->get("$this->issuerId.$classSuffix");
            } catch (GoogleServiceException $ex) {
                if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] === 'invalidResource') {
                    continue;
                }
                throw $ex;
            }
        }

        throw new GoogleServiceException("Class $this->issuerId.$classSuffix not found in any supported class types");
    }

    /**
     * format end date by adding an interval and setting time to end of day in UTC
     *
     * @param string $dateEnd
     * @param string $inputFormat
     * @param string $inputTz
     * @param string $intervalDuration
     * @return string
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedIntervalStringException
     */
    private function formatEndDate(string $dateEnd, string $inputFormat = 'd.m.Y', string $inputTz = 'UTC', string $intervalDuration = 'P0D'): string
    {
        $dt = DateTimeImmutable::createFromFormat($inputFormat, $dateEnd, new DateTimeZone($inputTz));
        if ($dt === false) {
            throw new InvalidArgumentException("Ungültiges Datum: $dateEnd");
        }

        // 2 Tage hinzufügen, Ende des Tages setzen und nach UTC konvertieren
        $dt = $dt->add(new DateInterval($intervalDuration))
            ->setTime(23, 59, 59, 59)
            ->setTimezone(new DateTimeZone('UTC'));

        return $dt->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Check if a ticket exists.
     *
     * @param String $objectSuffix
     *
     * @return bool
     *
     * @throws GoogleServiceException
     */
    public function ticketExists(string $objectSuffix): bool
    {
        $supportedClassServices = [
            'eventticketobject',
            'flightobject',
            'genericobject',
            'giftcardobject',
            'loyaltyobject',
            'offerobject',
            'transitobject',
        ];

        foreach ($supportedClassServices as $serviceName) {
            try {
                $this->service->$serviceName->get("$this->issuerId.$objectSuffix");

                return true;
            } catch (GoogleServiceException $ex) {
                if (empty($ex->getErrors()) || $ex->getErrors()[0]['reason'] == 'resourceNotFound') {
                    continue;
                }
                throw $ex;
            }
        }

        return false;
    }

    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedIntervalStringException
     */
    private function replaceDatetimeStringsRecursive(array &$fields, array $keysToReplace = ['start', 'end'], string $parentKey = '', array $exceptions = []): void
    {
        foreach ($fields as $fieldName => &$fieldValue) {
            $currentPath = $parentKey ? "$parentKey.$fieldName" : $fieldName;

            if (in_array($fieldName, $keysToReplace) && !in_array($currentPath, $exceptions)) {
                $fieldValue = new Walletobjects\DateTime(['date' => $this->formatEndDate($fieldValue)]);
            } elseif (is_array($fieldValue)) {
                $this->replaceDatetimeStringsRecursive($fieldValue, $keysToReplace, $currentPath, $exceptions);
            }
        }
    }

    /**
     * Validate module configuration
     *
     * required keys are defined in self::REQUIRED_CONFIG_KEYS
     *
     * @param Module $module
     *
     * @return void
     *
     * @throws InvalidConfigException
     */
    private function validateConfig(Module $module): void
    {
        if (!$module->googleWalletConfig) {
            throw new InvalidConfigException('googleWalletConfig not configured');
        }

        foreach (self::REQUIRED_CONFIG_KEYS as $key) {
            if (!isset($module->googleWalletConfig[$key])) {
                throw new InvalidConfigException("$key not configured");
            }
        }
    }

    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedIntervalStringException
     * @throws InvalidConfigException
     */
    private function transformTicketFields(array $specifiedTicketFields): array
    {
        $this->transformValidTimeInterval($specifiedTicketFields);
        $this->transformBarcode($specifiedTicketFields);
        $this->transformInfoModuleData($specifiedTicketFields);
        $this->transformImageModulesData($specifiedTicketFields);
        $this->transformTextModulesData($specifiedTicketFields);
        $this->transformLinksModuleData($specifiedTicketFields);
        $this->transformMerchantLocations($specifiedTicketFields);

        return $specifiedTicketFields;
    }

    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedIntervalStringException
     * @throws InvalidConfigException
     */
    private function transformValidTimeInterval(array &$specifiedTicketFields): void
    {
        $intervalDuration = Module::getInstance()->googleWalletConfig['timeToAddToValidTimeIntervalEnd'] ?? 'P0D';
        if (isset($specifiedTicketFields['validTimeInterval']['end'])) {
            $specifiedTicketFields['validTimeInterval']['end'] = Yii::createObject([
                'class' => Walletobjects\DateTime::class,
                'date' => $this->formatEndDate(
                    $specifiedTicketFields['validTimeInterval']['end'],
                    'd.m.Y',
                    'UTC',
                    $intervalDuration
                )
            ]);
        }

        $this->replaceDatetimeStringsRecursive(
            $specifiedTicketFields,
            ['start', 'end'],
            '',
            ['validTimeInterval.end']
        );
    }

    /**
     * @param Collection $eventTicketClass
     * @return string
     * @throws InvalidConfigException
     */
    private function getTicketObjectClass(Collection $eventTicketClass): string
    {
        foreach (self::SUPPORTED_TICKET_TYPES as $class => $values) {
            if (is_a($eventTicketClass, $class)) {
                return $values[0];
            }
        }
        throw new InvalidConfigException('Unsupported class type for ticket creation');
    }

    /**
     * @param Collection $eventTicketClass
     * @return mixed
     * @throws InvalidConfigException
     */
    private function getTicketServiceObject(Collection $eventTicketClass): mixed
    {
        foreach (self::SUPPORTED_TICKET_TYPES as $class => $values) {
            if (is_a($eventTicketClass, $class)) {
                return $this->service->{$values[1]};
            }
        }
        throw new InvalidConfigException('Unsupported class type for ticket creation');
    }

    /**
     * @param array $specifiedTicketFields
     * @return void
     */
    private function transformBarcode(array &$specifiedTicketFields): void
    {
        if (isset($specifiedTicketFields['barcode'])) {
            $specifiedTicketFields['barcode'] = new Barcode($specifiedTicketFields['barcode']);
        }
    }

    /**
     * @param array $specifiedTicketFields
     * @return void
     */
    private function transformInfoModuleData(array &$specifiedTicketFields): void
    {
        if (!isset($specifiedTicketFields['infoModuleData'])) {
            return;
        }

        $rows = $specifiedTicketFields['infoModuleData']['labelValueRows'] ?? [];
        foreach ($rows as $key => $row) {
            $columns = array_map(
                fn($column) => new Walletobjects\LabelValue($column),
                $row['columns'] ?? []
            );
            $rows[$key] = new Walletobjects\LabelValueRow(['columns' => $columns]);
        }
        $specifiedTicketFields['infoModuleData'] = new Walletobjects\InfoModuleData(['labelValueRows' => $rows]);
    }

    /**
     * @param array $specifiedTicketFields
     * @return void
     */
    private function transformImageModulesData(array &$specifiedTicketFields): void
    {
        if (!isset($specifiedTicketFields['imageModulesData'])) {
            return;
        }

        $specifiedTicketFields['imageModulesData'] = array_map(
            fn($imageModuleData) => $this->createImageModuleData($imageModuleData),
            $specifiedTicketFields['imageModulesData']
        );
    }

    /**
     * @param array $imageModuleData
     * @return ImageModuleData
     */
    private function createImageModuleData(array $imageModuleData): ImageModuleData
    {
        $newImageModuleDatas = [];
        foreach ($imageModuleData as $imageDataKey => $imageData) {
            $sourceUri = new ImageUri($imageData['sourceUri']);
            $contentDescriptionValues = array_map(
                fn($cdValue) => new TranslatedString($cdValue),
                $imageData['contentDescription'] ?? []
            );
            $contentDescription = new LocalizedString($contentDescriptionValues);
            $image = new Image([
                'sourceUri' => $sourceUri,
                'contentDescription' => $contentDescription,
            ]);
            $newImageModuleDatas[] = new ImageModuleData([
                $imageDataKey => $image,
                'id' => $imageData['id'],
            ]);
        }
        return $newImageModuleDatas[0] ?? new ImageModuleData([]);
    }

    /**
     * @param array $specifiedTicketFields
     * @return void
     */
    private function transformTextModulesData(array &$specifiedTicketFields): void
    {
        if (isset($specifiedTicketFields['textModulesData'])) {
            $specifiedTicketFields['textModulesData'] = array_map(
                fn($textModuleData) => new TextModuleData($textModuleData),
                $specifiedTicketFields['textModulesData']
            );
        }
    }

    /**
     * @param array $specifiedTicketFields
     * @return void
     */
    private function transformLinksModuleData(array &$specifiedTicketFields): void
    {
        if (isset($specifiedTicketFields['linksModuleData'])) {
            $uris = array_map(
                fn($linkModuleData) => new Uri($linkModuleData),
                $specifiedTicketFields['linksModuleData']
            );
            $specifiedTicketFields['linksModuleData'] = new LinksModuleData(['uris' => $uris]);
        }
    }

    /**
     * @param array $specifiedTicketFields
     * @return void
     */
    private function transformMerchantLocations(array &$specifiedTicketFields): void
    {
        if (isset($specifiedTicketFields['merchantLocations'])) {
            $specifiedTicketFields['merchantLocations'] = array_map(
                fn($merchantLocation) => new Walletobjects\MerchantLocation($merchantLocation),
                $specifiedTicketFields['merchantLocations']
            );
        }
    }
}
