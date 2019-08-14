<?php
	$installer = $this;
	$installer->startSetup();

	$sessionDataTable = $installer->getConnection()
		->addColumn($installer->getTable('onescan/sessiondata'),
			'shippingamount', array(
			'type' => Varien_Db_Ddl_Table::TYPE_FLOAT,
			'nullable' => false,
			'default' => 0,
			'comment' => 'Shipping Amount'));
	$sessionDataTable = $installer->getConnection()
		->addColumn($installer->getTable('onescan/sessiondata'),
			'shippingtax', array(
			'type' => Varien_Db_Ddl_Table::TYPE_FLOAT,
			'nullable' => false,
			'default' => 0,
			'comment' => 'Shipping Tax'));

	$installer->endSetup();
?>