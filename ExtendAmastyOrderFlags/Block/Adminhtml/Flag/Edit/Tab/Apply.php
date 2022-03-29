<?php

namespace DevAll\ExtendAmastyOrderFlags\Block\Adminhtml\Flag\Edit\Tab;

use Amasty\Flags\Block\Adminhtml\Element\Multiselect;
use Amasty\Flags\Model\Column;
use Amasty\Flags\Model\Flag;
use Amasty\Flags\Model\ResourceModel\Flag\CollectionFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Config;
use Magento\Shipping\Model\Config\Source\Allmethods;
use Magento\Store\Model\StoreManagerInterface;

class Apply extends \Amasty\Flags\Block\Adminhtml\Flag\Edit\Tab\Apply
{
    /**
     * @var Config
     */
    private $orderConfig;
    /**
     * @var Allmethods
     */
    private $shippingConfig;
    /**
     * @var CollectionFactory
     */
    private $flagCollectionFactory;
    /**
     * @var \Amasty\Flags\Model\ResourceModel\Column\CollectionFactory
     */
    private $columnCollectionFactory;

    /**
     * Apply Constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $orderConfig
     * @param Allmethods $shippingConfig
     * @param CollectionFactory $flagCollectionFactory
     * @param \Amasty\Flags\Model\ResourceModel\Column\CollectionFactory $columnCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        StoreManagerInterface $storeManager,
        Config $orderConfig,
        Allmethods $shippingConfig,
        CollectionFactory $flagCollectionFactory,
        \Amasty\Flags\Model\ResourceModel\Column\CollectionFactory $columnCollectionFactory,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $storeManager,
            $orderConfig,
            $shippingConfig,
            $flagCollectionFactory,
            $columnCollectionFactory,
            $data
        );
        $this->storeManager = $storeManager;
        $this->orderConfig = $orderConfig;
        $this->shippingConfig = $shippingConfig;
        $this->flagCollectionFactory = $flagCollectionFactory;
        $this->columnCollectionFactory = $columnCollectionFactory;
    }

    /**
     * PrepareForm Function Adds Automatic Apply Options In Flags
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var Flag $model */
        $model = $this->_coreRegistry->registry('amflags_flag');

        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('flag_');

        $fieldset = $form->addFieldset('apply_fieldset', [
            'legend' => __('Automatically Apply On Order Status Change And Selected Shipping Method')
        ]);

        $fieldset->addType(
            'partial_multiselect',
            Multiselect::class
        );

        $columns = $this->columnCollectionFactory->create();

        $values = [['value' => null, 'label' => ' ']];
        /** @var Column $column */
        foreach ($columns as $column) {
            $values[] = ['value' => $column->getId(), 'label' => $column->getName()];
        }

        $fieldset->addField('apply_column', 'select', [
            'name'      => 'apply_column',
            'label'     => __('Column name'),
            'title'     => __('Column name'),
            'values'    => $values,
            'note'      => __('Assign to column'),
        ]);

        //customer comment
        $values1[]   = ['value'=>1, 'label'=>'Auto Apply On Customer Message'];

        $fieldset->addField('apply_comment', 'partial_multiselect', [
            'name'      => 'apply_comment',
            'label'     => __('Customer Comment'),
            'title'     => __('Customer Comment'),
            'values'    => $values1,
            'note'      => __('Auto Apply On Customer Message'),
        ]);

        //purchase point
        $stores = $this->storeManager->getStores();

        $values   = [];
        foreach ($stores as $store) {
            $values[] = ['value' => $store->getCode(), 'label' => $store->getName()];
        }

        $fieldset->addField('apply_store', 'partial_multiselect', [
            'name'      => 'apply_store',
            'label'     => __('Order Purchase'),
            'title'     => __('Order Purchase'),
            'values'    => $values,
            'note'      => __('Set flag if order is purchased from this store'),
        ]);

        //status
        $statuses = $this->orderConfig->getStatuses();

        $values   = [];
        foreach ($statuses as $code => $name) {
            $values[] = ['value' => $code, 'label' => $name];
        }

        $fieldset->addField('apply_status', 'multiselect', [
            'name'      => 'apply_status',
            'label'     => __('Order Status'),
            'title'     => __('Order Status'),
            'values'    => $values,
            'note'      => __('Set flag if order changes to one of selected statuses'),
        ]);

        // shipping methods
        $methods = $this->shippingConfig->toOptionArray(true);

        $flags = $this->flagCollectionFactory->create();

        // disable shipping methods, selected in other flags
        $appliedMethods = [];
        /** @var Flag $flag */
        foreach ($flags as $i => $flag) {
            if ($flag->getId() != $model->getId() && $flag->getApplyShipping()) {
                $appliedMethods = array_merge(
                    $appliedMethods,
                    explode(',', $flag->getApplyShipping())
                );
            }
        }

        foreach ($methods as &$carrier) {
            if (!is_array($carrier['value'])) {
                continue;
            }

            foreach ($carrier['value'] as &$method) {
                if (in_array($method['value'], $appliedMethods)) {
                    $method['disabled'] = true;
                }
            }
            unset($method);
        }

        $fieldset->addField('apply_shipping', 'partial_multiselect', [
            'name'      => 'apply_shipping',
            'label'     => __('Order Shipping Method'),
            'title'     => __('Order Shipping Method'),
            'values'    => $methods,
            'note'      => __('Set flag if in the order used one of selected shipping methods. Each shipping method can be selected for only one flag.'),
        ]);

        // payment methods
        $methods = $this->_scopeConfig->getValue('payment');

        // disable payment methods, selected in other flags
        foreach ($flags as $i => $flag) {
            if ($flag->getId() != $model->getId()) {
                $appliedMethods = explode(',', $flag->getApplyPayment());
                if ($appliedMethods) {
                    foreach ($appliedMethods as $j => $method) {
                        $methods[$method]['disabled'] = true;
                    }
                }
            }
        }

        $values = [];
        foreach ($methods as $code => $method) {
            $value = ['value' => $code];

            if (isset($method['title'])) {
                $value['label'] = $method['title'];
            } else {
                $value['label'] = $code;
            }

            if (isset($method['disabled'])) {
                $value['disabled'] = $method['disabled'];
            }

            $values[] = $value;
        }

        $fieldset->addField('apply_payment', 'partial_multiselect', [
            'name'      => 'apply_payment',
            'label'     => __('Order Payment Method'),
            'title'     => __('Order Payment Method'),
            'values'    => $values,
            'note'      => __('Set flag if in the order used one of selected payment methods. Each payment method can be selected for only one flag.'),
        ]);

        $form->setValues($model->getData());
        $this->setForm($form);
    }
}
