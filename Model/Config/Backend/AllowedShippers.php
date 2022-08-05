<?php

/**
 * Gopay payment gateway by ZingyBits - Magento 2 extension
 *
 * NOTICE OF LICENSE
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited
 * Proprietary and confidential
 *
 * @category ZingyBits
 * @package ZingyBits_BalikobotCore
 * @copyright Copyright (c) 2022 ZingyBits s.r.o.
 * @license http://www.zingybits.com/business-license
 * @author ZingyBits s.r.o. <support@zingybits.com>
 */

namespace ZingyBits\BalikobotCore\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized;

use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

use Magento\Shipping\Model\Config\Source\Allmethods;
use Magento\Shipping\Model\Config;

class AllowedShippers extends Serialized
{
    /**
     * @var Json
     */
    private $serializer;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var Config
     */
    protected $_shippingConfig;

    /**
     * @var Allmethods
     */
    protected $allMethods;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param \Magento\Shipping\Model\Config $shippingConfig
     * @param array $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        Config $shippingConfig,
        array $data = [],
        Json $serializer = null
    ) {
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
        $this->_scopeConfig = $config;
        $this->_shippingConfig = $shippingConfig;
        $this->allMethods = new Allmethods($this->_scopeConfig, $this->_shippingConfig);
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data, $serializer);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        $enabledShippingMethods = $this->allMethods->toOptionArray(true);
        $value = $this->getValue();
        if (is_array($value)) {
            unset($value['__empty']);
            foreach ($value as $shipperCode => $data) {
                if (!isset($enabledShippingMethods[$shipperCode])) {
                    unset($value[$shipperCode]);
                }
            }
        }
        $this->setValue($value);
        return parent::beforeSave();
    }

    /**
     * @return void
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();


        if (!is_array($value)) {
            $newValue = empty($value) ? [] : $this->serializer->unserialize($value);
        }

        $enabledShippingMethods = $this->allMethods->toOptionArray(true);

        foreach ($enabledShippingMethods as $shipperCode => $shipperData) {
            if ($shipperCode && !isset($newValue[$shipperCode])) {
                $newValue[$shipperCode] = [
                    'shipper' => $shipperData['label'],
                    'method' => 0
                ];
            }
        }

        $this->setValue($newValue);

    }
}
