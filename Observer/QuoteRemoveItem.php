<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */
namespace Extend\Warranty\Observer;

use Extend\Warranty\Helper\Tracking;
use Magento\Framework\Debug;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Class QuoteRemoveItem
 * @package Extend\Warranty\Observer
 */
class QuoteRemoveItem implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Extend\Warranty\Helper\Tracking
     */
    protected $_trackingHelper;

    /**
     * Logger Interface
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * QuoteRemoveItem constructor
     *
     * @param Tracking $trackingHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Tracking $trackingHelper,
        LoggerInterface $logger
    ) {
        $this->_trackingHelper = $trackingHelper;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getData('quote_item');
        //if the item being removed is a warranty offer, send tracking for the offer removed, if tracking enabled
        if ($quoteItem->getProductType() === \Extend\Warranty\Model\Product\Type::TYPE_CODE) {
            if ($this->_trackingHelper->isTrackingEnabled()) {
                $warrantySku = (string)$quoteItem->getOptionByCode('associated_product')->getValue();
                $planId = (string)$quoteItem->getOptionByCode('warranty_id')->getValue();
                $trackingData = [
                    'eventName' => 'trackOfferRemovedFromCart',
                    'productId' => $warrantySku,
                    'planId'    => $planId,
                ];
                $this->_trackingHelper->setTrackingData($trackingData);
            }
            return;
        }

        //this is a regular product, check if there is an associated warranty item
        /** @var \Magento\Quote\Model\Quote\Item $warrantyItem */
        $warrantyItem = $this->_trackingHelper->getWarrantyItemForQuoteItem($quoteItem);
        if (!$warrantyItem && $this->_trackingHelper->isTrackingEnabled()) {
            //there is no associated warranty item. Just track the product removal
            $sku = $quoteItem->getSku();
            $trackingData = [
                'eventName' => 'trackProductRemovedFromCart',
                'productId' => $sku,
            ];
            $this->_trackingHelper->setTrackingData($trackingData);
            return;
        }

        //there is an associated warranty item, remove it
        //the removal will dispatch this event again where the offer removal will be tracked above
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $quoteItem->getQuote();

        $removeWarranty = true;
        $items = $quote->getAllItems();
        $visibleItems = $quote->getAllVisibleItems();

        $this->logger->debug('--- QUOTE REMOVE ITEM OBSERVER START ---');

        $debugQuoteData = 'Quote ID: ' . $quote->getId()
            . '. Items Count: ' . count($items)
            . '. Visible Items Count: ' . count($visibleItems);
        $this->logger->debug($debugQuoteData);
        $this->logger->debug('--- REMOVED ITEM START ---');
        $this->logger->debug('Removed Item ID: ' .  $quoteItem->getId() . '. Product SKU: ' . $quoteItem->getSku());
        $this->addLogging($quoteItem);
        $this->logger->debug('--- REMOVED ITEM END ---');

        foreach ($items as $item) {
            $this->logger->debug('Item ID: ' .  $item->getId() . '. Product SKU: ' . $item->getSku() . '. Product Name: ' . $item->getName());
            if (!$item->getId()) {
                $this->logger->debug('--- ITEM START ---');
                $this->addLogging($item);
                $this->logger->debug('--- ITEM END ---');
            }

            if ($item->getSku() === $quoteItem->getSku()) {
                $removeWarranty = false;
                $this->logger->debug('Warranty for ' . $quoteItem->getSku() . ' shouldn\'t be removed.');
                break;
            }
        }

        if ($warrantyItem && $removeWarranty) {
            $warrantyItemId = $warrantyItem->getItemId();
            $quote->removeItem($warrantyItem->getItemId());
            $this->logger->debug('Warranty ' . $warrantyItemId . ' has been removed.');
        }

        $this->logger->debug('--- QUOTE REMOVE ITEM OBSERVER END ---');
    }

    /**
     * Add logging
     *
     * @param CartItemInterface $quoteItem
     */
    private function addLogging(CartItemInterface $quoteItem): void
    {
        try {
            $hasError = false;
            $this->logger->debug('CART ITEM: ' . $quoteItem->convertToJson());
            $product = $quoteItem->getProduct();
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
    }
}
