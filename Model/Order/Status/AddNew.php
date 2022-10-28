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

namespace ZingyBits\BalikobotCore\Model\Order\Status;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory
    as StatusResourceFactory;
use Psr\Log\LoggerInterface;

class AddNew
{
    /**
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * @var StatusResourceFactory
     */
    protected $statusResourceFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * AddNewOrderStatus constructor.
     *
     * @param  StatusFactory          $statusFactory
     * @param  StatusResourceFactory  $statusResourceFactory
     * @param  LoggerInterface        $logger
     */
    public function __construct(
        StatusFactory         $statusFactory,
        StatusResourceFactory $statusResourceFactory,
        LoggerInterface       $logger
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
        $this->logger = $logger;
    }

    /**
     * Add custom order statuses
     *
     * @param  array  $customOrderStatusData
     *
     * @return void
     * @throws \Exception
     */
    public function execute(array $customOrderStatusData)
    {
        $statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();
        $status->setData([
                             'status' => $customOrderStatusData['status_code'],
                             'label'  => $customOrderStatusData['status_label']
                         ]);

        try {
            $statusResource->save($status);
            if ($customOrderStatusData['assign_to_state'] !== null) {
                $status->assignState(
                    $customOrderStatusData['assign_to_state'],
                    $customOrderStatusData['is_default'],
                    $customOrderStatusData['visible_on_front']
                );
            }
            $this->logger->info(
                'Created custom order status "' .
                $customOrderStatusData['status_label'] .
                '" with code "' .
                $customOrderStatusData['status_code'] . '"'
            );
        } catch (AlreadyExistsException $ex) {
            $this->logger->warning($ex->getMessage());
        } catch (Exception $ex) {
            $this->logger->error($ex->getMessage(), ['exception' => $ex]);
        }
    }
}
