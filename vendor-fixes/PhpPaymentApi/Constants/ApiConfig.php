<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 */

namespace Heidelpay\PhpPaymentApi\Constants;


/**
 * @copyright  Copyright (c) 2019 TechDivision GmbH
 * @link       http://www.techdivision.com/
 * @author     Lukas Kiederle <l.kiederle@techdivision.com
 */
class ApiConfig
{
    const SDK_VERSION = 'v1.6.2';

    const LIVE_URL = 'https://heidelpay.hpcgw.net/ngw/post';

    /**
     * Techdivision Changes start here........
     */

    private $testUrl = 'https://psp-mock.test/ngw/post';


    /**
     * @return string
     */
    public function getTestUrl()
    {
        $config = include (__DIR__ . '/../../../../../../app/etc/config.php');

        $sApiUrl = $config['system']['default']['techdivision_heidelpay_mockable']['psp_mock_url'] . '/ngw/post';

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
