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

namespace ZingyBits\BalikobotCore\Controller\Index;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use ZingyBits\BalikobotCore\Api\Status;
use Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Request\Http;
use Magento\Framework\App\Action\Context;
use ZingyBits\BalikobotCore\Model\BalikobotApiClient;
use Magento\Store\Model\StoreManagerInterface;
use \Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use ZingyBits\BalikobotCore\Model\Order\Email\Sender\OrderSender;
use ZingyBits\BalikobotCore\Model\Config;

class Index extends Action
{
    public const PHONE_CODES
        = [
            'CZ' => 420,
            'SK' => 421,
        ];

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Config
     */
    protected $config;

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

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orderRepository
     * @param BalikobotApiClient $balikobotApiClient
     * @param Http $request
     * @param OrderSender $sender
     */
    public function __construct(
        Context                  $context,
        JsonFactory              $resultJsonFactory,
        Config                   $config,
        StoreManagerInterface    $storeManager,
        OrderRepositoryInterface $orderRepository,
        BalikobotApiClient       $balikobotApiClient,
        Http                     $request,
        OrderSender $sender
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->balikobotApiClient = $balikobotApiClient;
        $this->sender = $sender;
        parent::__construct($context);
    }

    /**
     * Return json
     *
     * @return ResponseInterface|Json|ResultInterface
     * @throws \Exception
     */
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
            $map = json_decode($this->config->getAllowedShippers() ?: '[]',true);

            foreach ($map as $shipperCode => $info) {
                if (strpos($shipper, (string)$shipperCode) !== false) {
                    $shipper = $shipperCode;
                    break;
                }
            }

            if (!isset($map[$shipper])
                || $map[$shipper]['balikobot_shippers'] == '0'
            ) {
                return $result->setData(
                    [
                        'status'  => 'error',
                        'message' => 'Wrong shipping method'
                    ]
                );
            }

            $balikobotShipper = $map[$shipper]['balikobot_shippers'];
            $balikobotMethod = $map[$shipper]['method'];

            $shippingAddress = $order->getShippingAddress()->getData();

            // adding country code to phone number if it's missing
            $phone = str_replace(
                ' ',
                '',
                $shippingAddress['telephone']
            );
            $phoneCode = static::PHONE_CODES[$shippingAddress['country_id']] ??
                null;
            if ($phoneCode !== null
                && strpos($phone, (string)$phoneCode) === false
            ) {
                $phone = '+' . $phoneCode . $phone;
            }

            try {
                $this->balikobotApiClient->service(
                    $balikobotShipper,
                    $balikobotMethod,
                    ['price' => $order->getSubtotal()]
                );
                $this->balikobotApiClient->customer(
                    $shippingAddress['firstname'] . ' '
                    . $shippingAddress['lastname'],
                    $shippingAddress['street'],
                    $shippingAddress['city'],
                    str_replace(' ', '', $shippingAddress['postcode']),
                    $phone,
                    $shippingAddress['email']
                );
                $response = $this->balikobotApiClient->add();
            } catch (\Exception $e) {
                return $result->setData(
                    [
                        'status'  => 'error',
                        'message' => 'Balikobot API error: '
                            . $e->getMessage()
                    ]
                );
            }

            // save api response to order
            $order->setBalikobotJson(json_encode($response));
            $order->addCommentToStatusHistory(
                __('The label has been generated at Balikobot')
            );
            $order->setState(Status::STATUS_BBOT_INIT, true);
            $order->setStatus(Status::STATUS_BBOT_INIT);
            $order->addStatusToHistory(
                $order->getStatus(),
                __('new order status - ' . Status::LABEL_STATUS_BBOT_INIT)
            );
            $order->save();

        } else {
            $response = json_decode($data);
        }

        return $result->setData($response);
    }
}
