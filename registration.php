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
\Magento\Framework\Component\ComponentRegistrar::register(\Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Wallee_Payment', __DIR__);