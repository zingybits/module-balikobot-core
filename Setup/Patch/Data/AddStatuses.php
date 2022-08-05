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

namespace ZingyBits\BalikobotCore\Setup\Patch\Data;

use ZingyBits\BalikobotCore\Model\Order\Status\AddNew;
use ZingyBits\BalikobotCore\Api\Status;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;

/**
 * Class AddStatuses
 * @package Drmax\OrderStatus\Setup\Patch\Data
 */
class AddStatuses implements DataPatchInterface
{
    const CUSTOM_ORDER_STATUSES = [
        [
            'status_code'       => Status::STATUS_BBOT_PICKUP,
            'status_label'      => Status::LABEL_STATUS_BBOT_PICKUP,
            'assign_to_state'   => Order::STATE_PROCESSING,
            'is_default'        => false,
            'visible_on_front'  => false
        ],
        [
            'status_code'       => Status::STATUS_BBOT_INIT,
            'status_label'      => Status::LABEL_STATUS_BBOT_INIT,
            'assign_to_state'   => Order::STATE_PROCESSING,
            'is_default'        => false,
            'visible_on_front'  => false
        ],
    ];

    /**
     * @var AddNew
     */
    protected $addNew;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * UpdateData constructor.
     *
     * @param AddNew
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        AddNew $addNew,
        \Psr\Log\LoggerInterface $logger,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->addNew = $addNew;
        $this->logger = $logger;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * {@inheritDoc}
     */
    public function apply()
    {
        // add custom defined statuses
        $this->addStatuses(static::CUSTOM_ORDER_STATUSES);
    }

    /**
     * @param array $statuses
     */
    protected function addStatuses(array $statuses)
    {
        foreach ($statuses as $customOrderStatusData) {
            $this->addNew->execute($customOrderStatusData);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
