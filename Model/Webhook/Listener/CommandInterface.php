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
namespace Wallee\Payment\Model\Webhook\Listener;

use Magento\Sales\Model\Order;

/**
 * Webhook listener command interface.
 */
interface CommandInterface
{

    /**
     * @param mixed $entity
     * @param Order $order
     * @return mixed
     */
    public function execute($entity, Order $order);
}