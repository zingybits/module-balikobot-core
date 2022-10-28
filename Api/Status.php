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

namespace ZingyBits\BalikobotCore\Api;

class Status
{
    public const STATUS_BBOT_PICKUP = 'balikobot_pickup_requested';
    public const LABEL_STATUS_BBOT_PICKUP = 'Balikobot pickup requested';

    public const STATUS_BBOT_INIT = 'balikobot_initiated';
    public const LABEL_STATUS_BBOT_INIT = 'Balikobot initiated';
}
