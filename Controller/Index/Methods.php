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

namespace ZingyBits\BalikobotCore\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Action\Context;
use ZingyBits\BalikobotCore\Model\BalikobotApiClient;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Methods extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var BalikobotApiClient
     */
    protected $balikobotApiClient;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        OrderRepositoryInterface $orderRepository,
        Http $request,
        BalikobotApiClient $balikobotApiClient
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->balikobotApiClient = $balikobotApiClient;
        parent::__construct($context);
    }


    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $shipper = $this->request->getParam('shipper');
        $balikobotShippers = $this->balikobotApiClient->getShippers();
        if (!in_array($shipper, $balikobotShippers)) {
            return $result->setData([
                'status' => 'error',
                'message' => 'Wrong shipper'
            ]);
        }

        try {
            $response = $this->balikobotApiClient->getServices($shipper);
        } catch (\Exception $e) {
            return $result->setData([
                'status' => 'error',
                'message' => 'Balikobot API error: ' . $e->getMessage()
            ]);
        }

        return $result->setData($response);
    }
}
