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
namespace Wallee\Payment\Model\Webhook\Listener\DeliveryIndication;

use Magento\Sales\Model\Order;

/**
 * Webhook listener command to handle delivery indications where a manual check is required.
 */
class ManualCheckRequiredCommand extends AbstractCommand
{

    public function execute($entity, Order $order)
    {
        if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->addStatusToHistory(true, \__('A manual decision about whether to accept the payment is required.'));
        }
        $this->_orderRepository->save($order);
    }
}