<?php

namespace DevAll\ExtendAmastyOrderFlags\Observer;

use Amasty\Flags\Model\Flag;
use Amasty\Flags\Model\ResourceModel\Flag\Collection as FlagCollection;
use Amasty\Flags\Model\ResourceModel\Flag\CollectionFactory;
use Ess\M2ePro\Model\ActiveRecord\Component\Parent\Ebay\Factory;
use Ess\M2ePro\Model\Exception\Logic;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderSaveAfterObserver extends \Amasty\Flags\Observer\OrderSaveAfterObserver
{
    /**
     * @var CollectionFactory
     */
    private $flagCollectionFactory;
    /**
     * @var \Amasty\Flags\Model\ResourceModel\Order\Flag
     */
    private $flagResource;
    /**
     * @var Factory
     */
    private $ebayFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * OrderSaveAfterObserver Constructor
     *
     * @param CollectionFactory $flagCollectionFactory
     * @param \Amasty\Flags\Model\ResourceModel\Order\Flag $flagResource
     * @param Factory $ebayFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $flagCollectionFactory,
        \Amasty\Flags\Model\ResourceModel\Order\Flag $flagResource,
        Factory $ebayFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($flagCollectionFactory, $flagResource);
        $this->flagCollectionFactory = $flagCollectionFactory;
        $this->flagResource = $flagResource;
        $this->ebayFactory = $ebayFactory;
        $this->logger = $logger;
    }

    /**
     *  Function getAutoFlags
     *
     * @return FlagCollection
     */
    public function getAutoFlags()
    {
        /** @var FlagCollection $flags */
        $flags = $this->flagCollectionFactory->create();
        $flags
            ->addFieldToFilter('apply_column', ['notnull' => true])
            ->setOrder('priority', 'ASC');
        return $flags;
    }

    /**
     * Function applyFlags
     *
     * @param $orderId
     * @param FlagCollection $flags
     * @return void
     */
    public function applyFlags($orderId, FlagCollection $flags)
    {
        $filledColumns = [];
        /** @var Flag $flag */
        foreach ($flags as $flag) {
            $columnId = $flag->getApplyColumn();
            if (isset($filledColumns[$columnId])) {
                continue;
            }
            $this->flagResource->assign($orderId, $columnId, $flag->getId());
            $filledColumns[$columnId] = true;
        }
    }

    /**
     * Apply flags by purchase point
     *
     * @param Order $order
     * @return void
     */
    public function applyByStore(Order $order)
    {
        $flags = $this->flagCollectionFactory->create();
        foreach ($flags as $flag) {
            $applyStores = explode(',', $flag->getApplyStore());
            foreach ($applyStores as $applyStore) {
                if ($order->getStore()->getCode() == $applyStore && $flag->getApplyStatus() == $order->getStatus()) {
                    $this->flagResource->assign($order->getId(), $flag->getApplyColumn(), $flag->getId());
                }
            }
        }
    }

    /**
     * Apply flags by order status
     *
     * @param Order $order
     * @return $this|OrderSaveAfterObserver
     */
    public function applyByStatus(Order $order)
    {
        if ($order->getOrigData('status') != $order->getData('status')) {
            $flags = $this->getAutoFlags()
                ->addFieldToFilter(
                    'apply_status',
                    ['finset' => $order->getStatus()]
                );
            $this->applyFlags($order->getId(), $flags);
        }
        return $this;
    }

    /**
     * Apply flags by shipping method
     *
     * @param Order $order
     * @return $this|OrderSaveAfterObserver
     */
    public function applyByShipping(Order $order)
    {
        if (!$order->getOrigData('entity_id')) {
            $flags = $this->getAutoFlags()
                ->addFieldToFilter(
                    'apply_shipping',
                    ['finset' => $order->getShippingMethod()]
                );
            $this->applyFlags($order->getId(), $flags);
        }
        return $this;
    }

    /**
     * Apply flags by payment method
     *
     * @param Order $order
     * @return $this|OrderSaveAfterObserver
     */
    public function applyByPayment(Order $order)
    {
        if (!$order->getOrigData('entity_id')) {
            $flags = $this->getAutoFlags()
                ->addFieldToFilter(
                    'apply_payment',
                    ['finset' => $order->getPayment()->getMethod()]
                );
            $this->applyFlags($order->getId(), $flags);
        }
        return $this;
    }

    /**
     * Apply flags if ebay order has customer comment
     *
     * @param Order $order
     * @return $this
     * @throws Logic
     */
    public function applyByComment(Order $order)
    {
        $order1 = $this->ebayFactory->getObjectLoaded('Order', $order->getId(), 'magento_order_id');
        $ebayOrder = $order1->getChildObject();
        $flags = $this->flagCollectionFactory->create();
        foreach ($flags as $flag) {
            try {
                if ($ebayOrder->getBuyerMessage() != null && $flag->getData('apply_comment') == 1) {
                    $this->flagResource->assign($order->getId(), $flag->getApplyColumn(), $flag->getId());
                }
            } catch (NoSuchEntityException | StateException $e) {
                $this->logger->info($e->getMessage());
            }
        }
        return $this;
    }

    /**
     * Observer execute method
     *
     * @param Observer $observer
     * @return void
     * @throws Logic
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $this
            ->applyByStatus($order)
            ->applyByShipping($order)
            ->applyByPayment($order)
            ->applyByComment($order)
            ->applyByStore($order)
        ;
    }
}
