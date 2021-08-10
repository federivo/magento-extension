<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Plugin\ConfigurableProduct\Model\Quote\Item;

use Magento\ConfigurableProduct\Model\Quote\Item\CartItemProcessor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartItemInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Debug;

/**
 * Class CartItemProcessorPlugin
 */
class CartItemProcessorPlugin
{
    /**
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CartItemProcessorPlugin constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Add logging
     *
     * @param CartItemProcessor $subject
     * @param CartItemInterface $cartItem
     * @return array
     */
    public function beforeProcessOptions(CartItemProcessor $subject, CartItemInterface $cartItem): array
    {
        $this->logger->debug('--- BEFORE PROCESS OPTIONS PLUGIN START ---');

        try {
            $hasError = false;
            $this->logger->debug('CART ITEM: ' . $cartItem->convertToJson());
            $product = $cartItem->getProduct();
            if ($product) {
                $this->logger->debug('PRODUCT: ' . $product->convertToJson());
                $customOptions = $product->getCustomOptions();
                if (!empty($customOptions)) {
                    foreach ($customOptions as $code => $customOption) {
                        try {
                            $this->logger->debug('OPTION CODE: ' . $code . ' - OPTION VALUE: ' . $customOption->getValue());
                        } catch (LocalizedException $exception) {
                            $this->logger->error('Custom option is null.');
                            $this->logger->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
                        }
                    }
                } else {
                    $hasError = true;
                    $this->logger->error('Product custom options is empty.');
                }
            } else {
                $hasError = true;
                $this->logger->error('Product is empty.');
            }

            if ($hasError) {
                $this->logger->debug(Debug::backtrace(true));
            }
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }

        $this->logger->debug('--- BEFORE PROCESS OPTIONS PLUGIN END ---');

        return [$cartItem];
    }
}
