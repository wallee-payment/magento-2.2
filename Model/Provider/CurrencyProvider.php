<?php
/**
 * Wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with Wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Model\Provider;

use Magento\Framework\Cache\FrontendInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Service\CurrencyService;

/**
 * Provider of currency information from the gateway.
 */
class CurrencyProvider extends AbstractProvider
{

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, $apiClient, 'wallee_payment_currencies');
    }

    /**
     * Gets the currency by the given code.
     *
     * @param string $code
     * @return \Wallee\Sdk\Model\RestCurrency
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Gets a list of currencies.
     *
     * @return \Wallee\Sdk\Model\RestCurrency[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->_apiClient->getService(CurrencyService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \Wallee\Sdk\Model\RestCurrency $entry */
        return $entry->getCurrencyCode();
    }
}