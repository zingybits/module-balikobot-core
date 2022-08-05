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

namespace ZingyBits\BalikobotCore\Test\Unit;

use ZingyBits\BalikobotCore\Model\BalikobotApiClient;

/**
 * To run unit tests for module use CLI comand:
 * ./vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/ZingyBits/Balikobot/Test/Unit
 */
class DpdApiTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ZingyBits\BalikobotCore\Model\BalikobotApiClient
     */
    protected $balikobotApiClient;

    public function setUp(): void
    {
        $this->balikobotApiClient = new BalikobotApiClient('imagentoczt', 'TLWV561G', 1);
    }

    public function testService()
    {
        $this->assertEquals($this->balikobotApiClient, $this->balikobotApiClient->service('dpd', '1', ['price' => 5]));

        return $this->balikobotApiClient;
    }

    /**
     * @depends clone testService
     */
    public function testCustomer()
    {
        $this->assertEquals($this->balikobotApiClient, $this->balikobotApiClient->customer('Test Man', 'Revoluční 16', 'Praha', '11000', '+420777888777', 'test@balikobot.cz'));
    }

    /**
     * @depends testCustomer
     */
    public function testAdd()
    {
        $this->balikobotApiClient->service('dpd', '1', ['price' => 5]);
        $this->balikobotApiClient->customer('Test Man', 'Revoluční 16', 'Praha', '11000', '+420777888777', 'test@balikobot.cz');
        $response = $this->balikobotApiClient->add();
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals(200, $response['status']);
    }

}
