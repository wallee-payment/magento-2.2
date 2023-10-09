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
namespace Wallee\Payment\Model\Service;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Quote\Model\Quote;
use Wallee\Payment\Api\PaymentMethodConfigurationManagementInterface;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Model\Gender;
use Wallee\Sdk\Model\Transaction;
use Wallee\Sdk\Service\TransactionService;

/**
 * Abstract service to handle transactions.
 */
abstract class AbstractTransactionService
{

    /**
     *
     * @var ResourceConnection
     */
    private $resource;

    /**
     *
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     *
     * @var PaymentMethodConfigurationManagementInterface
     */
    private $paymentMethodConfigurationManagement;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     *
     * @param ResourceConnection $resource
     * @param CustomerRegistry $customerRegistry
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(ResourceConnection $resource, CustomerRegistry $customerRegistry,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient,
        CookieManagerInterface $cookieManager)
    {
        $this->resource = $resource;
        $this->customerRegistry = $customerRegistry;
        $this->paymentMethodConfigurationManagement = $paymentMethodConfigurationManagement;
        $this->apiClient = $apiClient;
        $this->cookieManager = $cookieManager;
    }

    /**
     * Updates the payment method configurations with the given data.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration[] $paymentMethods
     * @return void
     */
    protected function updatePaymentMethodConfigurations($paymentMethods)
    {
        foreach ($paymentMethods as $paymentMethod) {
            $this->paymentMethodConfigurationManagement->update($paymentMethod);
        }
    }

    /**
     * Gets the transaction by its ID.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->apiClient->getService(TransactionService::class)->read($spaceId, $transactionId);
    }

    /**
     * Updates the transaction information on the quote.
     *
     * @param Quote $quote
     * @param Transaction $transaction
     * @return void
     */
    protected function updateQuote(Quote $quote, Transaction $transaction)
    {
        $this->resource->getConnection()->update($this->resource->getTableName('quote'),
            [
                'wallee_space_id' => $transaction->getLinkedSpaceId(),
                'wallee_transaction_id' => $transaction->getId()
            ], [
                'entity_id = ?' => $quote->getId()
            ]);
    }

    /**
     * Gets the device session identifier from the cookie.
     *
     * @return string|NULL
     */
    protected function getDeviceSessionIdentifier()
    {
        return $this->cookieManager->getCookie('wallee_device_id');
    }

    /**
     * Gets the customer's tax number.
     *
     * @param string $taxNumber
     * @param int $customerId
     * @return string
     */
    protected function getTaxNumber($taxNumber, $customerId)
    {
        if ($taxNumber !== null) {
            return $taxNumber;
        } elseif (! empty($customerId)) {
            return $this->customerRegistry->retrieve($customerId)->getTaxvat();
        } else {
            return null;
        }
    }

    /**
     * Gets the customer's gender.
     *
     * @param string $gender
     * @param int $customerId
     * @return string
     */
    protected function getGender($gender, $customerId)
    {
        if ($gender == null && ! empty($customerId)) {
            $gender = $this->customerRegistry->retrieve($customerId)->getGender();
        }

        if ($gender == 2) {
            return Gender::FEMALE;
        } elseif ($gender == 1) {
            return Gender::MALE;
        } else {
            return null;
        }
    }

    /**
     * Gets the customer's email address.
     *
     * @param string $customerEmailAddress
     * @param int $customerId
     * @return string
     */
    protected function getCustomerEmailAddress($customerEmailAddress, $customerId)
    {
        if ($customerEmailAddress != null) {
            return $customerEmailAddress;
        } elseif (! empty($customerId)) {
            $customer = $this->customerRegistry->retrieve($customerId);
            $customerMail = $customer->getEmail();
            if (! empty($customerMail)) {
                return $customerMail;
            } else {
                return null;
            }
        }
    }

    /**
     * Gets the customer's date of birth.
     *
     * @param string $dateOfBirth
     * @param int $customerId
     * @return string
     */
    protected function getDateOfBirth($dateOfBirth, $customerId)
    {
        if ($dateOfBirth === null && ! empty($customerId)) {
            $customer = $this->customerRegistry->retrieve($customerId);
            $dateOfBirth = $customer->getDob();
        }

        if ($dateOfBirth !== null) {
            return \substr($dateOfBirth, 0, 10);
        }
    }
}