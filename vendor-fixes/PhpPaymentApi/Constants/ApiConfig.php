<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 */

namespace TechDivision\PayoneMockable\PhpPaymentApi\Constants;

use Heidelpay\PhpPaymentApi\Constants\ApiConfig as CoreApiConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * @copyright  Copyright (c) 2019 TechDivision GmbH
 * @link       http://www.techdivision.com/
 * @author     Lukas Kiederle <l.kiederle@techdivision.com
 */
class ApiConfig extends CoreApiConfig
{
    const SDK_VERSION = 'v1.6.2';

    const LIVE_URL = 'https://heidelpay.hpcgw.net/ngw/post';

    /**
     * Techdivision Changes start here........
     */


    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;


    private $testUrl = 'https://psp-mock.test/ngw/post';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     */
    public function getTestUrl()
    {
        $sApiUrl = $this->scopeConfig->getValue('techdivision_payone_mockable/payone/post_gateway');

        if($sApiUrl != null){
            return $sApiUrl;
        } else {
            return $this->testUrl;
        }
    }

    /**
     * Techdivision Changes stop here........
     */
}
