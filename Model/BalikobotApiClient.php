<?php

/**
 * Gopay payment gateway by ZingyBits - Magento 2 extension
 *
 * NOTICE OF LICENSE
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited
 * Proprietary and confidential
 *
 * @category  ZingyBits
 * @package   ZingyBits_BalikobotCore
 * @copyright Copyright (c) 2022 ZingyBits s.r.o.
 * @license   http://www.zingybits.com/business-license
 * @author    ZingyBits s.r.o. <support@zingybits.com>
 */

namespace ZingyBits\BalikobotCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use ZingyBits\BalikobotCore\Api\Data\BalikobotApiClientInterface;

class BalikobotApiClient implements BalikobotApiClientInterface
{
    /** @var string */
    private $apiUser = null;

    /** @var string */
    private $apiKey = null;

    /** @var int */
    private $shopId = null;

    /** @var string */
    private $apiUrl = 'https://api.balikobot.cz';

    /** @var array */
    private $data
        = [
            'isService'        => false,
            'isCustomer'       => false,
            'isCashOnDelivery' => false,
            'shipper'          => null,
            'data'             => [],
        ];

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var string
     */
    protected $apiKeyConfigPath;

    /**
     * @var string
     */
    protected $apiUserConfigPath;

    /**
     * @param  StoreManagerInterface  $storeManager
     * @param  ScopeConfigInterface   $scopeConfig
     * @param  string                 $apiKeyConfigPath
     * @param  string                 $apiUserConfigPath
     *
     * @throws NoSuchEntityException
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface  $scopeConfig,
        string                $apiKeyConfigPath,
        string                $apiUserConfigPath
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->apiKeyConfigPath = $apiKeyConfigPath;
        $this->apiUserConfigPath = $apiUserConfigPath;

        $shopId = (int)$this->storeManager->getStore()->getId();
        $apiKey = $this->scopeConfig->getValue($this->apiKeyConfigPath);
        $apiUser = $this->scopeConfig->getValue($this->apiUserConfigPath);

        if (empty($apiUser) || empty($apiKey) || empty($shopId)) {
            throw new \InvalidArgumentException(
                'Balikobot API token and key are not in DB.'
            );
        }
        if (!is_int($shopId)) {
            throw new \InvalidArgumentException(
                'Invalid shopId has been entered. Enter number.'
            );
        }

        $this->shopId = $shopId;
        $this->apiUser = $apiUser;
        $this->apiKey = $apiKey;
    }

    /**
     * @inheritdoc
     */
    public function service($shipper, $service = null, array $options = [])
    {
        if (empty($shipper)) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }
        if (!in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException("Unknown $shipper shipper.");
        }
        if (!empty($service)
            && !isset(
                $this->getServices(
                    $shipper
                )[$service]
            )
        ) {
            throw new \InvalidArgumentException(
                "Invalid $service service for $shipper shipper."
            );
        }

        // clean first
        $this->clean();

        // test if options are valid
        $validOptions = $this->getOptions($shipper);
        foreach ($options as $key => $v) {
            if (!in_array($key, $validOptions)) {
                throw new \InvalidArgumentException(
                    "Invalid $key option for $shipper shipper."
                );
            }
        }

        switch ($shipper) {
            case self::SHIPPER_CP:
                if (!isset($options[self::OPTION_PRICE])) {
                    throw new \InvalidArgumentException(
                        "The price option is required for $shipper shipper."
                    );
                }
                break;

            case self::SHIPPER_DPD:
                if ($service == 3 /* pickup */) {
                    if (empty($options[self::OPTION_BRANCH])) {
                        throw new \InvalidArgumentException(
                            'The branch option is required for pickup service.'
                        );
                    }
                }
                break;

            case self::SHIPPER_PPL:
                if (($service == 15) || ($service == 19)) {
//                    palette shipping
                    if (!isset($options[self::OPTION_MU_TYPE])) {
                        throw new \InvalidArgumentException(
                            'The mu type option is required for this service.'
                        );
                    }
                    if (!isset($options[self::OPTION_WEIGHT])) {
                        throw new \InvalidArgumentException(
                            'The weight option is required for this service.'
                        );
                    }
                } else {
                    if (isset($options[self::OPTION_NOTE])) {
                        throw new \InvalidArgumentException(
                            'The note option is not supported for this service.'
                        );
                    }
                }
                break;

            case self::SHIPPER_ZASILKOVNA:
                if (!isset($options[self::OPTION_BRANCH])) {
                    throw new \InvalidArgumentException(
                        "The branch option is required for $shipper shipper."
                    );
                }
                if (!isset($options[self::OPTION_PRICE])) {
                    throw new \InvalidArgumentException(
                        "The price option is required for $shipper shipper."
                    );
                }
                break;

            case self::SHIPPER_GEIS:
                if (isset($options[self::OPTION_INSURANCE])
                    && !isset($options[self::OPTION_PRICE])
                ) {
                    throw new \InvalidArgumentException(
                        'The price option is required for insurance option.'
                    );
                }
                if ($service == 6) {
//                    pickup
                    if (empty($options[self::OPTION_BRANCH])) {
                        throw new \InvalidArgumentException(
                            'The branch option is required for pickup service.'
                        );
                    }
                } elseif (($service == 4) || ($service == 5)) {
//                    palette
                    if (empty($options[self::OPTION_MU_TYPE])) {
                        throw new \InvalidArgumentException(
                            'The mu type option is required for pickup service.'
                        );
                    }
                    if (empty($options[self::OPTION_WEIGHT])) {
                        throw new \InvalidArgumentException(
                            'The weight option is required for pickup service.'
                        );
                    }
                }
                break;

            case self::SHIPPER_ULOZENKA:
                if (in_array($service, [1, 5, 7, 10, 11])) {
//                    pickup
                    if (empty($options[self::OPTION_BRANCH])) {
                        throw new \InvalidArgumentException(
                            'The branch option is required for pickup service.'
                        );
                    }
                }
                if ($service == 2) {
                    if (!isset($options[self::OPTION_PRICE])) {
                        throw new \InvalidArgumentException(
                            "The price option is required for this service."
                        );
                    }
                }
                if (in_array($service, [2, 6, 7])) {
                    if (empty($options[self::OPTION_WEIGHT])) {
                        throw new \InvalidArgumentException(
                            'The weight option is required for this service.'
                        );
                    }
                }
                break;

            case self::SHIPPER_INTIME:
                if (isset($options[self::OPTION_INSURANCE])
                    && !isset($options[self::OPTION_PRICE])
                ) {
                    throw new \InvalidArgumentException(
                        'The price option is required for insurance option.'
                    );
                }
                if (($service == 4) || ($service == 5)) {
//                    pickup
                    if (empty($options[self::OPTION_BRANCH])) {
                        throw new \InvalidArgumentException(
                            'The branch option is required for pickup service.'
                        );
                    }
                }
                break;

            case self::SHIPPER_GLS:
                if (!isset($options[self::OPTION_PRICE])) {
                    throw new \InvalidArgumentException(
                        "The price option is required for $shipper shipper."
                    );
                }
                if ($service == 2 /* pickup */) {
                    if (empty($options[self::OPTION_BRANCH])) {
                        throw new \InvalidArgumentException(
                            'The branch option is required for pickup service.'
                        );
                    }
                }
                break;

            case self::SHIPPER_TOPTRANS:
                if (empty($options[self::OPTION_TT_MU_TYPE])) {
                    throw new \InvalidArgumentException(
                        'The mu type option is required for this service.'
                    );
                }
                if (empty($options[self::OPTION_WEIGHT])) {
                    throw new \InvalidArgumentException(
                        'The weight option is required for this service.'
                    );
                }
                break;

            case self::SHIPPER_PBH:
                if (!isset($options[self::OPTION_PRICE])) {
                    throw new \InvalidArgumentException(
                        "The price option is required for $shipper shipper."
                    );
                }
                break;
        }

        // save options
        foreach ($options as $name => $value) {
            $this->saveOption($name, $value, $shipper);
        }
        $this->data['data']['service_type'] = $service;
        $this->data['shipper'] = $shipper;

        $this->data['isService'] = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function customer(
        $name,
        $street,
        $city,
        $zip,
        $phone,
        $email,
        $company = null,
        $country = self::COUNTRY_CZECHIA
    ) {
        if (empty($name) || empty($street) || empty($city) || empty($zip)
            || empty($phone)
            || empty($email)
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }
        if (!in_array($country, $this->getCountryCodes())) {
            throw new \InvalidArgumentException(
                'Invalid country code has been entered.'
            );
        }

        switch ($country) {
            case self::COUNTRY_CZECHIA:
                if (!preg_match('/^\d{5}$/', $zip)) {
                    throw new \InvalidArgumentException(
                        'Invalid zip code has been entered. Match XXXXX pattern.'
                    );
                }
                if (!preg_match('/^\+420\d{9}$/', $phone)) {
                    throw new \InvalidArgumentException(
                        'Invalid phone has been entered. Match +420YYYYYYYYY pattern.'
                    );
                }
                break;
            case self::COUNTRY_SLOVAKIA:
                if (!preg_match('/^\d{5}$/', $zip)) {
                    throw new \InvalidArgumentException(
                        'Invalid zip code has been entered. Match XXXXX pattern.'
                    );
                }
                if (!preg_match('/^\+421\d{9}$/', $phone)) {
                    throw new \InvalidArgumentException(
                        'Invalid phone has been entered. Match +421YYYYYYYYY pattern.'
                    );
                }
                break;

            default:
                throw new \UnexpectedValueException(
                    "Validation method is not implemented for $country country."
                );
        }

        $this->data['data']['rec_name'] = $name;
        $this->data['data']['rec_street'] = $street;
        $this->data['data']['rec_city'] = $city;
        $this->data['data']['rec_zip'] = $zip;
        $this->data['data']['rec_phone'] = $phone;
        $this->data['data']['rec_email'] = $email;
        $this->data['data']['rec_country'] = $country;
        if (isset($company)) {
            $this->data['data']['rec_firm'] = $company;
        }

        $this->data['isCustomer'] = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function cashOnDelivery(
        $price,
        $variableSymbol,
        $currency = self::CURRENCY_CZK
    ) {
        if (empty($price) || empty($variableSymbol)) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }
        if (!is_numeric($price)) {
            throw new \InvalidArgumentException(
                'Invalid price has been entered.'
            );
        }
        if (!is_numeric($variableSymbol)) {
            throw new \InvalidArgumentException(
                'Invalid variable symbol has been entered.'
            );
        }
        if (!in_array($currency, $this->getCurrencies())) {
            throw new \InvalidArgumentException(
                'Invalid currency has been entered.'
            );
        }

        $this->data['data']['cod_price'] = (float)$price;
        $this->data['data']['vs'] = $variableSymbol;
        $this->data['data']['cod_currency'] = $currency;

        $this->data['isCashOnDelivery'] = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function add()
    {
        if (!$this->data['isService'] || !$this->data['isCustomer']) {
            throw new \UnexpectedValueException(
                'Call service and customer method before.'
            );
        }

        $orderId = isset($this->data['data'][self::OPTION_ORDER]) ? sprintf(
            '%\'010s',
            $this->data['data'][self::OPTION_ORDER]
        ) : '0000000000';
        $this->data['data']['eid'] = $this->getEid(null, $orderId);
        // add only one package
        $response = $this->call(
            self::REQUEST_ADD,
            $this->data['shipper'],
            [$this->data['data']]
        );
        $this->clean();

        if (!isset($response[0]['package_id'])) {
            throw new \InvalidArgumentException(
                'Invalid arguments. Errors: ' .
                json_encode($response[0]) . '.',
                self::EXCEPTION_INVALID_REQUEST
            );
        }

        return $response[0];
    }

    /**
     * @inheritdoc
     */
    public function getServices($shipper)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(self::REQUEST_SERVICES, $shipper);
        if (isset($response['status']) && ($response['status'] == 409)) {
            throw new \InvalidArgumentException(
                "The $shipper shipper is not supported.",
                self::EXCEPTION_NOT_SUPPORTED
            );
        }
        if (!isset($response['status']) || ($response['status'] != 200)) {
            $code = isset($response['status']) ? $response['status'] : 0;
            throw new \UnexpectedValueException(
                "Unexpected server response, code = $code.",
                self::EXCEPTION_SERVER_ERROR
            );
        }

        return (!empty($response['service_types'])) ? $response['service_types']
            : [];
    }

    /**
     * @inheritdoc
     */
    public function getManipulationUnits($shipper)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(self::REQUEST_MANIPULATIONUNITS, $shipper);

        if (isset($response['status']) && ($response['status'] == 409)) {
            throw new \InvalidArgumentException(
                "The $shipper shipper is not supported.",
                self::EXCEPTION_NOT_SUPPORTED
            );
        }
        if (!isset($response['status']) || ($response['status'] != 200)) {
            $code = isset($response['status']) ? $response['status'] : 0;
            throw new \UnexpectedValueException(
                "Unexpected server response, code = $code.",
                self::EXCEPTION_SERVER_ERROR
            );
        }

        if ($response['units'] === null) {
            return [];
        }

        $units = [];

        foreach ($response['units'] as $item) {
            $units[$item['code']] = $item['name'];
        }

        return $units;
    }

    /**
     * @inheritdoc
     *
     * @param  string  $shipper
     * @param  string  $service
     * @param  bool    $full
     *
     * @return array
     */
    public function getBranches(
        string $shipper,
        string $service = null,
        bool   $full = false
    ) {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            $full ? self::REQUEST_FULLBRANCHES : self::REQUEST_BRANCHES,
            $shipper,
            [],
            $service
        );

        if (isset($response['status']) && ($response['status'] == 409)) {
            throw new \InvalidArgumentException(
                "The $shipper shipper is not supported.",
                self::EXCEPTION_NOT_SUPPORTED
            );
        }
        if (!isset($response['status']) || ($response['status'] != 200)) {
            $code = isset($response['status']) ? $response['status'] : 0;
            throw new \UnexpectedValueException(
                "Unexpected server response, code = $code.",
                self::EXCEPTION_SERVER_ERROR
            );
        }

        if ($response['branches'] === null) {
            return [];
        }

        $branches = [];
        $id = 'id';

        if ($shipper == self::SHIPPER_CP) {
            $id = 'zip';
        } elseif ($shipper == self::SHIPPER_INTIME) {
            $id = 'name';
        }

        foreach ($response['branches'] as $item) {
            $branches[$item[$id]] = $item;
        }

        return $branches;
    }

    /**
     * @inheritdoc
     *
     * @param  string  $shipper
     *
     * @return array
     */
    public function getCountriesForService(string $shipper)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(self::REQUEST_COUNTRIES4SERVICE, $shipper);

        if (isset($response['status']) && ($response['status'] == 409)) {
            throw new \InvalidArgumentException(
                "The $shipper shipper is not supported.",
                self::EXCEPTION_NOT_SUPPORTED
            );
        }
        if (!isset($response['status']) || ($response['status'] != 200)) {
            $code = isset($response['status']) ? $response['status'] : 0;
            throw new \UnexpectedValueException(
                "Unexpected server response, code = $code.",
                self::EXCEPTION_SERVER_ERROR
            );
        }

        if ($response['service_types'] === null) {
            return [];
        }

        $services = [];

        foreach ($response['service_types'] as $item) {
            $services[$item['service_type']] = $item['countries'];
        }

        return $services;
    }

    /**
     * @inheritdoc
     */
    public function getZipCodes(
        $shipper,
        $service,
        $country = self::COUNTRY_CZECHIA
    ) {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())
            || empty($service)
            || !isset($this->getServices($shipper)[$service])
            || !in_array($country, $this->getCountryCodes())
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_ZIPCODES,
            $shipper,
            [],
            "$service/$country"
        );

        if (isset($response['status']) && ($response['status'] == 409)) {
            throw new \InvalidArgumentException(
                "The $shipper shipper is not supported.",
                self::EXCEPTION_NOT_SUPPORTED
            );
        }
        if (!isset($response['status']) || ($response['status'] != 200)) {
            $code = isset($response['status']) ? $response['status'] : 0;
            throw new \UnexpectedValueException(
                "Unexpected server response, code = $code.",
                self::EXCEPTION_SERVER_ERROR
            );
        }

        if ($response['zip_codes'] === null) {
            return [];
        }

        $zip = [];

        // type item indicates if structure is zip or zip codes, but for some shippers response structure is wrong
        // so we test if zip exist
        if (isset($response['zip_codes'][0]['zip'])) {
            foreach ($response['zip_codes'] as $item) {
                $zip[] = $item['zip'];
            }
        } elseif (isset($response['zip_codes'][0]['zip_start'])
            && isset($response['zip_codes'][0]['zip_end'])
        ) {
            foreach ($response['zip_codes'] as $item) {
                $zip[] = [$item['zip_start'], $item['zip_end']];
            }
        }

        return $zip;
    }

    /**
     * @inheritdoc
     */
    public function dropPackage($shipper, $packageId)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())
            || empty($packageId)
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_DROP,
            $shipper,
            ['id' => $packageId]
        );

        if (!isset($response['status'])) {
            throw new \UnexpectedValueException(
                'Unexpected server response.',
                self::EXCEPTION_SERVER_ERROR
            );
        }
        if ($response['status'] == 404) {
            throw new \UnexpectedValueException(
                'The package does not exist or it was ordered.',
                self::EXCEPTION_INVALID_REQUEST
            );
        }
        if ($response['status'] != 200) {
            throw new \UnexpectedValueException(
                "Unexpected server response, code={$response['status']}.",
                self::EXCEPTION_SERVER_ERROR
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function trackPackage($shipper, $carrierId)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())
            || empty($carrierId)
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_TRACK,
            $shipper,
            ['id' => $carrierId]
        );

        if (isset($response['status']) && ($response['status'] != 200)) {
            throw new \UnexpectedValueException(
                "Unexpected server response, code={$response['status']}.",
                self::EXCEPTION_SERVER_ERROR
            );
        }
        if (empty($response[0])) {
            throw new \UnexpectedValueException(
                'Unexpected server response.',
                self::EXCEPTION_SERVER_ERROR
            );
        }

        return $response[0];
    }

    /**
     * @inheritdoc
     */
    public function trackPackageLast($shipper, $carrierId)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())
            || empty($carrierId)
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_TRACKSTATUS,
            $shipper,
            ['id' => $carrierId]
        );

        if (isset($response['status']) && ($response['status'] != 200)) {
            throw new \UnexpectedValueException(
                "Unexpected server response, code={$response['status']}.",
                self::EXCEPTION_SERVER_ERROR
            );
        }
        if (empty($response[0])) {
            throw new \UnexpectedValueException(
                'Unexpected server response.',
                self::EXCEPTION_SERVER_ERROR
            );
        }

        return $response[0];
    }

    /**
     * @inheritdoc
     */
    public function overview($shipper)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(self::REQUEST_OVERVIEW, $shipper);

        if (isset($response['status']) && ($response['status'] == 404)) {
            throw new \UnexpectedValueException(
                'No packages.',
                self::EXCEPTION_INVALID_REQUEST
            );
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function getLabels($shipper, array $packages)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())
            || empty($packages)
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_LABELS,
            $shipper,
            ['package_ids' => $packages]
        );

        if (isset($response['status']) && ($response['status'] != 200)) {
            throw new \UnexpectedValueException(
                'Invalid data or invalid packages number.',
                self::EXCEPTION_INVALID_REQUEST
            );
        }

        return $response['labels_url'];
    }

    /**
     * @inheritdoc
     */
    public function getPackageInfo($shipper, $packageId)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())
            || empty($packageId)
        ) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_PACKAGE,
            $shipper,
            [],
            $packageId
        );

        if (isset($response['status']) && ($response['status'] == 404)) {
            throw new \UnexpectedValueException(
                'Invalid package number.',
                self::EXCEPTION_INVALID_REQUEST
            );
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function order($shipper, array $packages = [])
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $response = $this->call(
            self::REQUEST_ORDER,
            $shipper,
            empty($packages) ? [] : ['package_ids' => $packages]
        );

        if (!isset($response['status'])) {
            throw new \UnexpectedValueException(
                'Unexpected server response.',
                self::EXCEPTION_SERVER_ERROR
            );
        }
        if ($response['status'] == 406) {
            throw new \UnexpectedValueException(
                'Invalid package numbers.',
                self::EXCEPTION_INVALID_REQUEST
            );
        }
        if ($response['status'] != 200) {
            throw new \UnexpectedValueException(
                "Unexpected server response, code={$response['status']}.",
                self::EXCEPTION_SERVER_ERROR
            );
        }

        return $response;
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /**
     * Returns available shippers
     *
     * @return array
     */
    public function getShippers()
    {
        $rc = new \ReflectionClass($this);
        $constants = $rc->getConstants();

        foreach ($constants as $key => $item) {
            if (substr($key, 0, 8) !== 'SHIPPER_') {
                unset($constants[$key]);
            }
        }

        return $constants;
    }

    /**
     * Returns available options for the given shipper
     *
     * @param  string  $shipper
     *
     * @return array
     */
    public function getOptions($shipper)
    {
        if (empty($shipper) || !in_array($shipper, $this->getShippers())) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        switch ($shipper) {
            case self::SHIPPER_SP:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_SERVICES,
                    self::OPTION_WEIGHT,
                    self::OPTION_BRANCH
                ];

            case self::SHIPPER_CP:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_SERVICES,
                    self::OPTION_WEIGHT,
                    self::OPTION_BRANCH
                ];

            case self::SHIPPER_DPD:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_SMS_NOTIFICATION,
                    self::OPTION_BRANCH,
                    self::OPTION_INSURANCE,
                    self::OPTION_NOTE,
                    self::OPTION_WEIGHT,
                ];

            case self::SHIPPER_GEIS:
                return [
                    self::OPTION_BRANCH,
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_INSURANCE,
                    self::OPTION_PAY_BY_CUSTOMER,
                    self::OPTION_NOTE,
                    // palette
                    self::OPTION_MU_TYPE,
                    self::OPTION_PIECES,
                    self::OPTION_WEIGHT,
                    self::OPTION_PAY_BY_CUSTOMER,
                    self::OPTION_SMS_NOTIFICATION,
                    self::OPTION_PHONE_NOTIFICATION,
                    self::OPTION_B2C,
                    self::OPTION_NOTE_DRIVER,
                    self::OPTION_NOTE_CUSTOMER,
                ];

            case self::SHIPPER_GLS:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_BRANCH,
                    self::OPTION_WEIGHT,
                ];

            case self::SHIPPER_INTIME:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_BRANCH,
                    self::OPTION_INSURANCE,
                    self::OPTION_NOTE,
                    self::OPTION_WEIGHT,
                ];

            case self::SHIPPER_PBH:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                ];

            case self::SHIPPER_PPL:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_BRANCH,
                    self::OPTION_INSURANCE,
                    // palette
                    self::OPTION_MU_TYPE,
                    self::OPTION_PIECES,
                    self::OPTION_WEIGHT,
                    self::OPTION_PAY_BY_CUSTOMER,
                    self::OPTION_COMFORT,
                    self::OPTION_RETURN_OLD_HA,
                    self::OPTION_NOTE,
                ];

            case self::SHIPPER_TOPTRANS:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_TT_MU_TYPE,
                    self::OPTION_TT_PIECES,
                    self::OPTION_WEIGHT,
                    self::OPTION_NOTE,
                    self::OPTION_COMFORT,
                ];

            case self::SHIPPER_ULOZENKA:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_BRANCH,
                    self::OPTION_WEIGHT,
                    self::OPTION_NOTE,
                    self::OPTION_AGE,
                    self::OPTION_PASSWORD,
                ];

            case self::SHIPPER_ZASILKOVNA:
                return [
                    self::OPTION_PRICE,
                    self::OPTION_ORDER,
                    self::OPTION_BRANCH,
                    self::OPTION_WEIGHT,
                ];
        }

        return [];
    }

    /**
     * Returns country codes
     */
    public function getCountryCodes()
    {
        $rc = new \ReflectionClass($this);
        $constants = $rc->getConstants();

        foreach ($constants as $key => $item) {
            if (substr($key, 0, 8) !== 'COUNTRY_') {
                unset($constants[$key]);
            }
        }

        return $constants;
    }

    /**
     * Returns currencies
     *
     * @return array
     */
    public function getCurrencies()
    {
        $rc = new \ReflectionClass($this);
        $constants = $rc->getConstants();

        foreach ($constants as $key => $item) {
            if (substr($key, 0, 9) !== 'CURRENCY_') {
                unset($constants[$key]);
            }
        }

        return $constants;
    }

    /**
     * Returns available values for option services
     *
     * @return array
     */
    public function getOptionServices(): array
    {
        $rc = new \ReflectionClass($this);
        $constants = $rc->getConstants();

        foreach ($constants as $key => $item) {
            if (substr($key, 0, 16) !== 'OPTION_SERVICES_') {
                unset($constants[$key]);
            }
        }

        return $constants;
    }

    /**
     * Save options
     *
     * @param  string  $name
     * @param  string  $value
     * @param  string  $shipper
     *
     * @return void
     */
    private function saveOption(
        string $name,
        string $value,
        string $shipper = null
    ) {
        if (empty($name)) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        switch ($name) {
            case self::OPTION_BRANCH:
                // do nothing
                break;

            case self::OPTION_MU_TYPE:
            case self::OPTION_TT_MU_TYPE:
                // do nothing
                break;

            case self::OPTION_SERVICES:
                if (!is_array($value)) {
                    throw new \InvalidArgumentException(
                        'Invalid value of services option has been entered.'
                    );
                }

                $cpServices = $this->getOptionServices();

                foreach ($value as $serviceItem) {
                    if (!in_array($serviceItem, $cpServices)) {
                        throw new \InvalidArgumentException(
                            "Invalid $serviceItem value of services option has been entered."
                        );
                    }
                }

                $value = implode('+', $value);
                break;

            case self::OPTION_SMS_NOTIFICATION:
            case self::OPTION_INSURANCE:
            case self::OPTION_PAY_BY_CUSTOMER:
            case self::OPTION_COMFORT:
            case self::OPTION_RETURN_OLD_HA:
            case self::OPTION_PHONE_NOTIFICATION:
            case self::OPTION_B2C:
            case self::OPTION_AGE:
                if (!is_bool($value)) {
                    throw new \InvalidArgumentException(
                        "Invalid value of $name option has been entered. Enter boolean."
                    );
                }

                $value = (bool)$value;
                break;

            case self::OPTION_PRICE:
            case self::OPTION_WEIGHT:
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException(
                        "Invalid value of $name option has been entered. Enter float."
                    );
                }

                $value = (float)$value;
                break;

            case self::OPTION_NOTE:
            case self::OPTION_NOTE_DRIVER:
            case self::OPTION_NOTE_CUSTOMER:
            case self::OPTION_PASSWORD:
                if (!is_string($value)) {
                    throw new \InvalidArgumentException(
                        'Invalid value of note option has been entered. Enter string.'
                    );
                }

                $limit = 64;

                if ($shipper == self::SHIPPER_DPD) {
                    $limit = 70;
                } elseif ($shipper == self::SHIPPER_PPL) {
                    $limit = 350;
                } elseif ($shipper == self::SHIPPER_GEIS) {
                    $limit = ($name == self::OPTION_NOTE) ? 57 : 62;
                } elseif ($shipper == self::SHIPPER_ULOZENKA) {
                    $limit = ($name == self::OPTION_PASSWORD) ? 99 : 75;
                } elseif ($shipper == self::SHIPPER_INTIME) {
                    $limit = 75;
                } elseif ($shipper == self::SHIPPER_TOPTRANS) {
                    $limit = 50;
                }

                if (strlen($value) > $limit) {
                    throw new \InvalidArgumentException(
                        "Invalid value of note option has been entered. Maximum length is $limit characters."
                    );
                }
                break;

            case self::OPTION_PIECES:
            case self::OPTION_TT_PIECES:
                if (!is_int($value) || ($value < 1)) {
                    throw new \InvalidArgumentException(
                        'Invalid value of pieces has been entered. Enter positive integer.'
                    );
                }
                break;

            case self::OPTION_ORDER:
                if (!is_numeric($value) || (strlen($value) > 10)) {
                    throw new \InvalidArgumentException(
                        "Invalid value of order option has been entered. Enter number, max 10 characters length."
                    );
                }
                break;
        }

        $this->data['data'][$name] = $value;
    }

    /**
     * Return Eid
     *
     * @param  string|null  $shipper
     * @param  string|null  $orderId
     *
     * @return string
     */
    private function getEid(string $shipper = null, string $orderId = null)
    {
        $time = time();
        $delimeter = '';

        if (isset($shipper) && isset($orderId)) {
            return implode(
                $delimeter,
                [$this->shopId, $shipper, $orderId, $time]
            );
        } elseif (isset($shipper)) {
            return implode($delimeter, [$this->shopId, $shipper, $time]);
        } elseif (isset($orderId)) {
            return implode($delimeter, [$this->shopId, $orderId, $time]);
        } else {
            return implode($delimeter, [$this->shopId, $time]);
        }
    }

    /**
     * Send request
     *
     * @param  string       $request
     * @param  string       $shipper
     * @param  array        $data
     * @param  string|null  $url
     *
     * @return mixed
     */
    private function call(
        string $request,
        string $shipper,
        array  $data = [],
        string $url = null
    ) {
        if (empty($request) || empty($shipper)) {
            throw new \InvalidArgumentException(
                'Invalid argument has been entered.'
            );
        }

        $r = curl_init();
        curl_setopt(
            $r,
            CURLOPT_URL,
            $url ? "$this->apiUrl/$shipper/$request/$url"
                : "$this->apiUrl/$shipper/$request"
        );
        curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($r, CURLOPT_HEADER, false);
        if (!empty($data)) {
            curl_setopt($r, CURLOPT_POST, true);
            curl_setopt($r, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($r, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode(
                "$this->apiUser:$this->apiKey"
            ),
            'Content-Type: application/json',
            'BB-Partner: .k.nbwwKcdE50naJPc2a',
        ]);
        $response = curl_exec($r);
        curl_close($r);

        return json_decode($response, true);
    }

    /**
     * Cleans temporary data about created package
     *
     * @return void
     */
    private function clean()
    {
        $this->data = [
            'isService'        => false,
            'isCustomer'       => false,
            'isCashOnDelivery' => false,
            'shipper'          => null,
            'data'             => [],
        ];
    }
}
