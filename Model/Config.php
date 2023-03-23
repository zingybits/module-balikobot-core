<?php

namespace ZingyBits\BalikobotCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public const SEND_EMAIL_TRACKING = "balikobot/general/send_email_tracking";
    public const API_KEY = "balikobot/general/api_key";
    public const API_USER = "balikobot/general/api_user";
    public const ALLOWED_SHIPPERS = "balikobot/allowed_shippers/shippers";

    public const BALIKOBOT_API_URL = "https://api.balikobot.cz";

    public const BALIKOBOT_ORDER_TEMPLATE = 'balikobot_sales_email_order_template';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get config value
     * @return bool
     */
    public function isSendEmailTracking(): bool {
        return (bool) $this->scopeConfig->getValue(self::SEND_EMAIL_TRACKING);
    }

    /**
     * Get config value
     * @return string
     */
    public function getApiKey(): string {
        return (string) $this->scopeConfig->getValue(self::API_KEY);
    }

    /**
     * Get config value
     * @return string
     */
    public function getApiUser(): string {
        return (string) $this->scopeConfig->getValue(self::API_USER);
    }

    /**
     * Get config value
     * @return string
     */
    public function getAllowedShippers(): string {
        return (string) $this->scopeConfig->getValue(self::ALLOWED_SHIPPERS);
    }

}
