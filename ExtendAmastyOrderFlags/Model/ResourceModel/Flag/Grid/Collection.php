<?php

namespace DevAll\ExtendAmastyOrderFlags\Model\ResourceModel\Flag\Grid;

use Amasty\Flags\Model\Flag;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Sales\Model\Order\Config;
use Magento\Shipping\Model\Config\Source\Allmethods;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Collection extends \Amasty\Flags\Model\ResourceModel\Flag\Grid\Collection
{
    /**
     * @var Flag
     */
    private $flagSingleton;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var Config
     */
    private $orderConfig;
    /**
     * @var Allmethods
     */
    private $shippingConfig;

    /**
     * Collection Constructor
     *
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param $mainTable
     * @param $eventPrefix
     * @param $eventObject
     * @param $resourceModel
     * @param Flag $flagSingleton
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $orderConfig
     * @param Allmethods $shippingConfig
     * @param string $model
     * @param null $connection
     * @param StoreManagerInterface $storeManager
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable,
        $eventPrefix,
        $eventObject,
        $resourceModel,
        Flag $flagSingleton,
        ScopeConfigInterface $scopeConfig,
        Config $orderConfig,
        Allmethods $shippingConfig,
        StoreManagerInterface $storeManager,
        AbstractDb $resource = null,
        $model = 'Magento\Framework\View\Element\UiComponent\DataProvider\Document',
        $connection = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $eventPrefix,
            $eventObject,
            $resourceModel,
            $flagSingleton,
            $scopeConfig,
            $orderConfig,
            $shippingConfig,
            $model,
            $connection,
            $storeManager,
            $resource
        );
        $this->flagSingleton = $flagSingleton;
        $this->scopeConfig = $scopeConfig;
        $this->orderConfig = $orderConfig;
        $this->shippingConfig = $shippingConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Function getAggregations
     *
     * @return AggregationInterface
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * Function setAggregations
     *
     * @param AggregationInterface $aggregations
     * @return \Amasty\Flags\Model\ResourceModel\Flag\Grid\Collection
     */
    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;

        return $this;
    }

    /**
     * Retrieve all ids for collection
     * Backward compatibility with EAV collection
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllIds($limit = null, $offset = null)
    {
        return $this->getConnection()->fetchCol($this->_getAllIdsSelect($limit, $offset), $this->_bindParams);
    }

    /**
     * Get search criteria.
     *
     * @return SearchCriteriaInterface|null
     */
    public function getSearchCriteria()
    {
        return null;
    }

    /**
     * Set search criteria.
     *
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria = null)
    {
        return $this;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * Set total count.
     *
     * @param int $totalCount
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount($totalCount)
    {
        return $this;
    }

    /**
     * Set items list.
     *
     * @param ExtensibleDataInterface[] $items
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setItems(array $items = null)
    {
        return $this;
    }

    /**
     * @return \Amasty\Flags\Model\ResourceModel\Flag\Grid\Collection
     */
    protected function _afterLoad()
    {
        $paymentMethods = $this->scopeConfig->getValue('payment');
        $shippingMethods = $this->shippingConfig->toOptionArray(true);
        $orderStatuses = $this->orderConfig->getStatuses();
        $orderStores = $this->storeManager->getStores();
        $orderComments = 1;
        $shippingCarriers = [];

        foreach ($shippingMethods as $shippingMethod) {
            if (is_array($shippingMethod['value'])) {
                foreach ($shippingMethod['value'] as $carrier) {
                    $shippingCarriers[$carrier['value']] = $carrier['label'];
                }
            }
        }

        foreach ($this->getItems() as $item) {
            $item
                ->setData('image_name_src', $this->flagSingleton->getImageUrl($item))
                ->setData('image_name_alt', $item->getName());

            // payment
            $appliedMethods = explode(',', $item->getApplyPayment());
            $output = [];
            foreach ($appliedMethods as $code) {
                if (isset($paymentMethods[$code])) {
                    if (isset($paymentMethods[$code]['title'])) {
                        $output[] = $paymentMethods[$code]['title'];
                    } else {
                        $output[] = $code;
                    }
                }
            }
            $item->setApplyPayment(implode(', ', $output));

            //shipping
            $appliedMethods = explode(',', $item->getApplyShipping());
            $output = [];
            foreach ($appliedMethods as $code) {
                if (isset($shippingCarriers[$code])) {
                    $output[] = $shippingCarriers[$code];
                }
            }
            $item->setApplyShipping(implode(', ', $output));

            //purchase point
            $appliedStores = explode(',', $item->getApplyStore());
            $output = [];
            foreach ($appliedStores as $code) {
                if (isset($orderStores[$code])) {
                    $output[] = $orderStores[$code];
                }
            }
            $item->setApplyStore(implode(', ', $output));

            //customer comment
            $appliedComments = explode(',', $item->getData('apply_comment'));
            $output = [];
            foreach ($appliedComments as $code) {
                if (isset($orderComments[$code])) {
                    $output[] = $orderComments[$code];
                }
            }
            $item->setData('apply_comment', (implode(', ', $output)));

            //status
            $appliedStatuses = explode(',', $item->getApplyStatus());
            $output = [];
            foreach ($appliedStatuses as $code) {
                if (isset($orderStatuses[$code])) {
                    $output[] = $orderStatuses[$code];
                }
            }
            $item->setApplyStatus(implode(', ', $output));

        }

        return parent::_afterLoad();
    }
}
