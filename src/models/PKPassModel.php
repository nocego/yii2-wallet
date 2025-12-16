<?php

namespace nocego\yii2\wallet\models;

use nocego\yii2\wallet\Module;
use PKPass\PKPass;
use PKPass\PKPassException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\BadRequestHttpException;

class PKPassModel extends Model
{
    /**
     * PKPass format version
     * Should be 1 according to Apple documentation
     */
    private const int FORMAT_VERSION = 1;

    private const string IMAGE_ICON = 'icon.png';
    private const string IMAGE_LOGO = 'logo.png';

    /**
     * Required configuration keys
     */
    private const array REQUIRED_CONFIG_KEYS = [
        'certificatePath',
        'certificatePassword',
        'organizationName',
        'passTypeIdentifier',
        'teamIdentifier',
        'iconUrl'
    ];

    /**
     * Required POST parameters
     */
    private const array REQUIRED_POST_PARAMS = [
        'description',
        'serialNumber',
        'passType',
        'passValue'
    ];

    /**
     * Optional fields for the pass
     */
    private const array OPTIONAL_FIELDS = [
        'relevantDate',
        'barcodes',
        'backgroundColor',
        'foregroundColor',
        'labelColor'
    ];

    /**
     * Get a pkpass ticket object
     *
     * @param array $fields field parameters for the pass
     * Expected fields parameters:
     * - description: Pass description (required)
     *      e.g., ```'Flight Express Event Ticket'```
     * - serialNumber: Unique serial number for the pass (required)
     *      e.g., ```'1234567890'```
     * - passType: Pass type (required)
     *      e.g., ```'eventTicket', 'boardingPass'```
     * - passValue: Pass type specific data structure with headerFields, etc. (required)
     *      e.g.:
     * ```
     *
     *          [
     *              'headerFields' => [
     *                  [
     *                      'key' => 'origin-destination',
     *                      'label' => 'Event',
     *                      'value' => 'My Event',
     *                  ],
     *                  ...
     *              ],
     *              ...
     *          ]
     * ```
     * - relevantDate: Optional relevant date for the pass
     *      e.g., ```'2024-12-31T23:59:59Z'```
     * - barcodes: Optional barcode data
     *      e.g.:
     * ```
     *
     *         [
     *           'format' => 'PKBarcodeFormatQR',
     *           'message' => '1234567890',
     *           'messageEncoding' => 'iso-8859-1',
     *           'altText' => '1234567890'
     *         ]
     * ```
     * - backgroundColor: Optional background color
     *      e.g., ```'rgb(32,110,247)'```
     * - foregroundColor: Optional foreground color
     *      e.g., ```'rgb(255,255,255)'```
     * - labelColor: Optional label color
     *      e.g., ```'rgb(255,255,255)'```
     *
     * @return void (outputs the pkpass file directly as response)
     *
     * @throws InvalidConfigException
     * @throws PKPassException
     * @throws BadRequestHttpException
     * @see https://hackage-content.haskell.org/package/hs-pkpass-0.6/docs/Passbook-Types.html
     *
     */
    public function getTicket(array $fields): void
    {
        /** @var Module $moduleInstance */
        $moduleInstance = Module::getInstance();

        $this->validateConfig($moduleInstance);

        $this->validateRequiredParams($fields);

        $pass = $this->createPass($moduleInstance, $fields);
        $pass->create(true);
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
        if (!$module->pkPassConfig) {
            throw new InvalidConfigException('pkPassConfig not configured');
        }

        foreach (self::REQUIRED_CONFIG_KEYS as $key) {
            if (!isset($module->pkPassConfig[$key])) {
                throw new InvalidConfigException("$key not configured");
            }
        }
    }

    /**
     * Validate required POST parameters
     *
     * required parameters are defined in self::REQUIRED_POST_PARAMS
     *
     * @param array $params POST parameters
     *
     * @return void
     *
     * @throws BadRequestHttpException
     */
    private function validateRequiredParams(array $params): void
    {
        foreach (self::REQUIRED_POST_PARAMS as $param) {
            if (!isset($params[$param])) {
                throw new BadRequestHttpException("$param is required");
            }
        }
    }

    /**
     * Create PKPass object
     *
     * @param Module $module
     * @param array $fields field parameters
     *
     * @return PKPass
     * @throws PKPassException
     */
    private function createPass(Module $module, array $fields): PKPass
    {
        $pass = new PKPass($module->pkPassConfig['certificatePath'], $module->pkPassConfig['certificatePassword']);

        $data = array_merge(
            [
                'description' => $fields['description'],
                'formatVersion' => self::FORMAT_VERSION,
                'organizationName' => $module->pkPassConfig['organizationName'],
                'passTypeIdentifier' => $module->pkPassConfig['passTypeIdentifier'],
                'serialNumber' => $fields['serialNumber'],
                'teamIdentifier' => $module->pkPassConfig['teamIdentifier'],
                'logoText' => "Erlebnisbank",
                $fields['passType'] => $fields['passValue'],
            ],
            array_intersect_key($fields, array_flip(self::OPTIONAL_FIELDS))
        );

        $pass->setData($data);

        // Add images
        $pass->addRemoteFile($module->pkPassConfig['iconUrl'], self::IMAGE_ICON);
        if (isset($module->pkPassConfig['logoUrl'])) {
            $pass->addRemoteFile($module->pkPassConfig['logoUrl'], self::IMAGE_LOGO);
        }

        return $pass;
    }
}
