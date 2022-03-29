<?php

namespace DevAll\ExtendAmastyOrderFlags\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Add apply_comment column in amasty_flags_flag table
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(
        SchemaSetupInterface   $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;

        $installer->startSetup();
        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('amasty_flags_flag'),
                'apply_comment',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => null,
                    'nullable' => false,
                    'comment' => 'Automatically Applied For Customer Comment'
                ]
            );
        }
        $installer->endSetup();
    }
}
