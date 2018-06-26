<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Model\Service;

use Magento\Sales\Model\Order\Creditmemo;
use Wallee\Payment\Helper\Data as Helper;
use Wallee\Payment\Helper\LineItem as LineItemHelper;
use Wallee\Payment\Helper\LineItemReduction as LineItemReductionHelper;
use Wallee\Payment\Model\ApiClient;
use Wallee\Sdk\Model\CriteriaOperator;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Model\EntityQueryOrderByType;
use Wallee\Sdk\Model\LineItemReductionCreate;
use Wallee\Sdk\Model\Refund;
use Wallee\Sdk\Model\RefundState;
use Wallee\Sdk\Model\TransactionInvoiceState;
use Wallee\Sdk\Service\RefundService;
use Wallee\Sdk\Service\TransactionInvoiceService;

/**
 * Service to handle line item reductions.
 */
class LineItemReductionService
{

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var LineItemReductionHelper
     */
    protected $_reductionHelper;

    /**
     *
     * @var LineItemHelper
     */
    protected $_lineItemHelper;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Helper $helper
     * @param LineItemReductionHelper $reductionHelper
     * @param LineItemHelper $lineItemHelper
     * @param ApiClient $apiClient
     */
    public function __construct(Helper $helper, LineItemReductionHelper $reductionHelper, LineItemHelper $lineItemHelper,
        ApiClient $apiClient)
    {
        $this->_helper = $helper;
        $this->_reductionHelper = $reductionHelper;
        $this->_lineItemHelper = $lineItemHelper;
        $this->_apiClient = $apiClient;
    }

    /**
     * Converts the creditmemo's items to line item reductions.
     *
     * @param Creditmemo $creditmemo
     * @return LineItemReductionCreate[]
     */
    public function convertCreditmemo(Creditmemo $creditmemo)
    {
        $reductions = [];

        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            if ($this->isIncludeItem($creditmemoItem)) {
                $reductions[] = $this->convertItem($creditmemoItem, $creditmemo);
            }
        }

        $shippingReduction = $this->convertShipping($creditmemo);
        if ($shippingReduction instanceof LineItemReductionCreate) {
            $reductions[] = $shippingReduction;
        }

        return $this->fixReductions($reductions, $creditmemo);
    }

    /**
     * Gets whether the given creditmemo item is to be included in the line item reductions.
     *
     * @param Creditmemo\Item $creditmemoItem
     * @return boolean
     */
    protected function isIncludeItem(Creditmemo\Item $creditmemoItem)
    {
        if ($creditmemoItem->getParentItemId() != null && $creditmemoItem->getParentItem()->getProductType() ==
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return false;
        }

        if ($creditmemoItem->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE &&
            $creditmemoItem->getParentItemId() == null &&
            $creditmemoItem->getProduct()->getPriceType() != \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED) {
            return false;
        }

        return true;
    }

    /**
     * Converts the given creditmemo item to a line item reduction.
     *
     * @param Creditmemo\Item $creditmemoItem
     * @param Creditmemo $creditmemo
     * @return LineItemReductionCreate[]
     */
    protected function convertItem(Creditmemo\Item $creditmemoItem, Creditmemo $creditmemo)
    {
        $reduction = new LineItemReductionCreate();
        $reduction->setLineItemUniqueId($creditmemoItem->getOrderItem()
            ->getQuoteItemId());
        $reduction->setQuantityReduction($creditmemoItem->getQty());
        $reduction->setUnitPriceReduction(0);
        return $reduction;
    }

    /**
     * Converts the creditmemo's shipping information to a line item reduction.
     *
     * @param Creditmemo $creditmemo
     * @return LineItemReductionCreate
     */
    protected function convertShipping(Creditmemo $creditmemo)
    {
        if ($creditmemo->getShippingAmount() > 0) {
            $reduction = new LineItemReductionCreate();
            $reduction->setLineItemUniqueId('shipping');
            if ($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount() ==
                $creditmemo->getOrder()->getShippingInclTax()) {
                $reduction->setQuantityReduction(1);
                $reduction->setUnitPriceReduction(0);
            } else {
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    $this->_helper->roundAmount($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(),
                        $creditmemo->getOrderCurrencyCode()));
            }
            return $reduction;
        }
    }

    /**
     * Returns the fixed line item reductions for the creditmemo.
     *
     * If the amount of the given reductions does not match the creditmemo's grand total, the amount to refund is
     * distributed equally to the line items.
     *
     * @param LineItemReductionCreate[] $reductions
     * @param Creditmemo $creditmemo
     * @return LineItemReductionCreate[]
     */
    protected function fixReductions(array $reductions, Creditmemo $creditmemo)
    {
        /** @var \Wallee\Sdk\Model\LineItem $baseLineItems */
        $baseLineItems = $this->getBaseLineItems(
            $creditmemo->getOrder()
                ->getWalleeSpaceId(),
            $creditmemo->getOrder()
                ->getWalleeTransactionId());
        $reducedAmount = $this->_reductionHelper->getReducedAmount($baseLineItems, $reductions);
        if ($reducedAmount != $creditmemo->getGrandTotal()) {
            $baseAmount = $this->_lineItemHelper->getTotalAmountIncludingTax($baseLineItems);
            $rate = $creditmemo->getGrandTotal() / $baseAmount;
            $fixedReductions = [];
            foreach ($baseLineItems as $lineItem) {
                if ($lineItem->getQuantity() > 0) {
                    $reduction = new LineItemReductionCreate();
                    $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                    $reduction->setQuantityReduction(0);
                    $reduction->setUnitPriceReduction(
                        $this->_helper->roundAmount(
                            $lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(),
                            $creditmemo->getOrderCurrencyCode()));
                    $fixedReductions[] = $reduction;
                }
            }
            return $fixedReductions;
        } else {
            return $reductions;
        }
    }

    /**
     * Gets the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or of the transaction invoice otherwise.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Refund $refund
     * @return \Wallee\Sdk\Model\LineItem[]
     */
    public function getBaseLineItems($spaceId, $transactionId, Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund instanceof Refund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transactionId)->getLineItems();
        }
    }

    /**
     * Gets the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @throws \Exception
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, $transactionId)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->_helper->createEntityFilter('state', TransactionInvoiceState::CANCELED,
                    CriteriaOperator::NOT_EQUALS),
                $this->_helper->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId)
            ));
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->_apiClient->getService(TransactionInvoiceService::class)->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new \Exception('The transaction invoice could not be found.');
        }
    }

    /**
     * Gets the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Refund $refund
     * @return Refund
     */
    protected function getLastSuccessfulRefund($spaceId, $transactionId, Refund $refund = null)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filters = [
            $this->_helper->createEntityFilter('state', RefundState::SUCCESSFUL),
            $this->_helper->createEntityFilter('transaction.id', $transactionId)
        ];
        if ($refund != null) {
            $filters[] = $this->_helper->createEntityFilter('id', $refund->getId(), CriteriaOperator::NOT_EQUALS);
        }
        $filter->setChildren($filters);
        $query->setFilter($filter);
        $query->setOrderBys(
            [
                $this->_helper->createEntityOrderBy('createdOn', EntityQueryOrderByType::DESC)
            ]);
        $query->setNumberOfEntities(1);
        $result = $this->_apiClient->getService(RefundService::class)->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        }
    }
}