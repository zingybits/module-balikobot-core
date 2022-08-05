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

use ZingyBits\BalikobotCore\Api\Status;
use Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Request\Http;
use Magento\Framework\App\Action\Context;
use ZingyBits\BalikobotCore\Model\BalikobotApiClient;
use Magento\Store\Model\StoreManagerInterface;
use \Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Index extends Action
{

    const phoneCodes = [
        'CZ' => 420,
        'SK' => 421,
    ];

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

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
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
        BalikobotApiClient $balikobotApiClient,
        Http $request
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->balikobotApiClient = $balikobotApiClient;
        parent::__construct($context);
    }


    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // get order
        $orderId = $this->request->getParam('order_id');
        $order = $this->orderRepository->get($orderId);

        // get Balikobot data from DB
        // If data already exist, we can skip API request
        $data = $order->getBalikobotJson();

        if (!$data) {
            // get required values from order
            $shipper = $order->getShippingMethod();
            $map = json_decode($this->scopeConfig->getValue('balikobot/allowed_shippers/shippers') ?: '[]', true);

            foreach ($map as $shipperCode => $info) {
                if (strpos($shipper, (string)$shipperCode) !== false) {
                    $shipper = $shipperCode;
                    break;
                }
            }

            if (!isset($map[$shipper]) || $map[$shipper]['balikobot_shippers'] == '0') {
                return $result->setData([
                    'status' => 'error',
                    'message' => 'Wrong shipping method'
                ]);
            }

            $balikobotShipper = $map[$shipper]['balikobot_shippers'];
            $balikobotMethod = $map[$shipper]['method'];

            $shippingAddress = $order->getShippingAddress()->getData();

            // adding country code to phone number if it's missing
            $phone = str_replace(' ', '', $shippingAddress['telephone']);
            $phoneCode = static::phoneCodes[$shippingAddress['country_id']] ?? null;
            if(!is_null($phoneCode) && strpos($phone, (string)$phoneCode) === false) {
                $phone = '+' . $phoneCode . $phone;
            }

            try {
                $this->balikobotApiClient->service($balikobotShipper, $balikobotMethod, ['price' => $order->getSubtotal()]);
                $this->balikobotApiClient->customer(
                    $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'],
                    $shippingAddress['street'],
                    $shippingAddress['city'],
                    str_replace(' ', '', $shippingAddress['postcode']),
                    $phone,
                    $shippingAddress['email']
                );
                $response = $this->balikobotApiClient->add();
            } catch (\Exception $e) {
                return $result->setData([
                    'status' => 'error',
                    'message' => 'Balikobot API error: ' . $e->getMessage()
                ]);
            }

            // save api response to order
            $order->setBalikobotJson(json_encode($response));
            $order->addCommentToStatusHistory(__('The label has been generated at Balikobot'));
            $order->setState(status::STATUS_BBOT_INIT, true);
            $order->setStatus(status::STATUS_BBOT_INIT);
            $order->addStatusToHistory($order->getStatus(), __('new order status - ').__(status::LABEL_STATUS_BBOT_INIT ));

            $order->save();
        } else {
            $response = json_decode($data);
        }

        return $result->setData($response);
    }
}
