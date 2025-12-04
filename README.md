# yii2-wallet

A simple wallet management module for Yii2 framework.

## Features

### PKPass Support
- Create PKPass tickets for Apple Wallet

### Google Wallet Support
- Create Google Wallet class
- Check if a Google Wallet class exists
- Create a Google Wallet ticket
- Get a Google Wallet ticket
- Expire a Google Wallet ticket

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
php composer.phar require --prefer-dist nocego/yii2-wallet
```

or add

```json
"nocego/yii2-wallet": "*"
```

to the require section of your composer.json

## Initial Setup
After you have installed the extension, you need to configure the module in your application configuration file:

```php
'wallet' => [
    'class' => 'nocego\yii2\wallet\Module',
    'canManageTickets' => ['admin'],
    'googleWalletConfig' => [
        'issuerId' => '1234567891011121314',
        'googleServiceAccountCredentials' => [
            "type" => "type",
            "project_id" => "project_id",
            "private_key_id" => "private_key_id",
            "private_key" => "-----BEGIN PRIVATE KEY-----\nprivate_key\n-----END PRIVATE KEY-----\n",
            "client_email" => "client_email",
            "client_id" => "123456789101112131415",
            "auth_uri" => "auth_uri",
            "token_uri" => "token_uri",
            "auth_provider_x509_cert_url" => "auth_provider_x509_cert_url",
            "client_x509_cert_url" => "client_x509_cert_url",
            "universe_domain" => "universe_domain",
        ],
        'timeToAddToValidTimeIntervalEnd' => 'P7D',
    ],
    'pkPassConfig' => [
        'certificatePath' => /srv/www/www.test.org/certificates/pkpass/certificates.p12,
        'certificatePassword' => '12.34#56',
        'organizationName' => 'MyOrganization',
        'passTypeIdentifier' => 'pass.org.test.myapp',
        'teamIdentifier' => 'AB1CDEFG2K',
        'iconUrl' => 'https://test.org/files/Wallet/icon.png',
        'logoUrl' => 'https://test.org/files/Wallet/icon.png',
    ],
]
```

## Usage
You can use the wallet module in your application as follows:

### PKPass examples
```php
$pkPassFields = [
    'description' => 'Test PKPass Ticket',
    'serialNumber' => '1234567890',
    'organizationName' => 'MyOrganization',
    'logoText' => 'My PKPass Ticket',
    'foregroundColor' => 'rgb(255, 255, 255)',
    'backgroundColor' => 'rgb(0, 0, 0)',
    // Add other necessary fields here
];
$pkPass = new PkpassModel();
$pkPass->getTicket($pkPassFields);
```

### Google Wallet create class example
```php
// Google Wallet example
$classSuffix = 'test_class_suffix';
$classType = 'eventticket
$classFields = [
    'ticketFields' => [
        'eventName' => [
            'defaultValue' => [
                'language' => 'en-US',
                'value' => 'Test Event',
            ],
        ],
        'issuerName' => 'Test Issuer',
        // Add other necessary fields here
    ],
];
$wallet = new GoogleWallet();
$success = $wallet->createClass($classSuffix, $classFields, $classType);
```

### Google Wallet create ticket example
```php
$classSuffix = 'test_class_suffix';
$objectSuffix = 'test_object_suffix';
$classFields = [
    'ticketFields' => [
        'ticketHolderName' => 'John Doe',
        'barcode': {
          'type': 'QR_CODE',
          'value': 1234567890,
        },
        // Add other necessary fields here
    ],
];
$wallet = new GoogleWallet();
$wallet->createTicket($classSuffix, $objectSuffix, $classFields);
```

## REST API
The module also provides a REST API for managing wallets. You can access the API endpoints at `/rest/wallet/`.
The following endpoints are available:
- `POST /rest/wallet/pkpass` Get a PKPass ticket
- `POST /rest/wallet/google-wallet/class` Create a Google Wallet class
- `GET /rest/wallet/google-wallet/{$id}/class-exists` Check if a Google Wallet class exists
- `POST /rest/wallet/google-wallet/ticket` Create a Google Wallet ticket
- `GET /rest/wallet/google-wallet/ticket/{$id}` Get a Google Wallet ticket
- `DELETE /rest/wallet/google-wallet/ticket/{$id}` Expire a Google Wallet ticket

## Contributing
Contributions are welcome! Please submit a pull request or open an issue to discuss changes.

## License
This project is licensed under the MIT License.




