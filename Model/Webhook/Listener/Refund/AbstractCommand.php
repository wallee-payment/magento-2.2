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
namespace Wallee\Payment\Model\Webhook\Listener\Refund;

use Magento\Framework\Exception\NoSuchEntityException;
use Wallee\Payment\Api\RefundJobRepositoryInterface;
use Wallee\Payment\Model\Webhook\Listener\AbstractOrderRelatedCommand;
use Wallee\Sdk\Model\Refund;

/**
 * Abstract webhook listener command to handle refunds.
 */
abstract class AbstractCommand extends AbstractOrderRelatedCommand
{

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @param RefundJobRepositoryInterface $refundJobRepository
     */
    public function __construct(RefundJobRepositoryInterface $refundJobRepository)
    {
        $this->refundJobRepository = $refundJobRepository;
    }

    /**
     * Deletes the refund job of the given refund if existing.
     *
     * @param Refund $refund
     * @return void
     */
    protected function deleteRefundJob(Refund $refund)
    {
        try {
            $refundJob = $this->refundJobRepository->getByExternalId($refund->getExternalId());
            $this->refundJobRepository->delete($refundJob);
        } catch (NoSuchEntityException $e) {
            // If the refund job cannot be found, there is no need to delete it, so the exception can be ignored.
        }
    }
}