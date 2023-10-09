<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Plugin\Payment\Model\Config;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Wallee\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use Wallee\Payment\Api\Data\PaymentMethodConfigurationInterface;
use Wallee\Payment\Model\PaymentMethodConfiguration;

/**
 * Interceptor to dynamically extend the payment configuration with the wallee payment method data.
 */
class Reader
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, ResourceConnection $resourceConnection)
    {
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param \Magento\Payment\Model\Config\Reader $subject
     * @param array<mixed> $result
     * @return array<mixed>|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterRead(\Magento\Payment\Model\Config\Reader $subject, $result)
    {
        if (! $this->isTableExists()) {
            return $result;
        }

        if (isset($result['methods'])) {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter(PaymentMethodConfigurationInterface::STATE,
                [
                    PaymentMethodConfiguration::STATE_ACTIVE,
                    PaymentMethodConfiguration::STATE_INACTIVE
                ], 'in')->create();

            $configurations = $this->paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
            foreach ($configurations as $configuration) {
                $result['methods'][$this->getPaymentMethodId($configuration)] = $this->generateConfig();
            }
        }
        return $result;
    }

    /**
     * @return string
     */
    private function getPaymentMethodId(PaymentMethodConfigurationInterface $configuration)
    {
        return 'wallee_payment_' . $configuration->getEntityId();
    }

    /**
     * @return array<mixed>
     */
    private function generateConfig()
    {
        return [
            'allow_multiple_address' => '1'
        ];
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    private function isTableExists()
    {
        return $this->resourceConnection->getConnection()->isTableExists(
            $this->resourceConnection->getTableName('wallee_payment_method_configuration'));
    }
}