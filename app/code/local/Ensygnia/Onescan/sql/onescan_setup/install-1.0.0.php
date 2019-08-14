<?php
/*
	Ensygnia Onescan extension
	Copyright (C) 2014 Ensygnia Ltd

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
	$installer = $this;
	$installer->startSetup();

	$sessionDataTable = $installer->getConnection()->newTable($installer->getTable('onescan/sessiondata'))
		->addColumn('sessiondata_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'unsigned' => true,
			'nullable' => false,
			'primary' => true,
			'identity' => true,
			), 'Session Data ID')
		->addColumn('sessionid', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable' => false,
			), 'Session ID')
		->addColumn('quoteid', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable' => false,
			), 'Quote ID')
		->addColumn('customerid', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable' => true,
			), 'Customer ID')
		->addColumn('shippingmethod', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable' => true,
			), 'Shipping Method')
		->addColumn('shippingrate', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'unsigned' => true,
			'nullable' => true,
			), 'Shipping Rate')
		->setComment('Ensygnia onescan/sessiondata entity table');
	$installer->getConnection()->createTable($sessionDataTable);

	$LoginTokensTable = $installer->getConnection()->newTable($installer->getTable('onescan/logintokens'))
		->addColumn('logintoken_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'unsigned' => true,
			'nullable' => false,
			'primary' => true,
			'identity' => true,
			), 'Login Token ID')
		->addColumn('onescantoken', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable' => false,
			), 'Onescan Token')
		->addColumn('magentouserid', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable' => false,
			), 'Magento Userid')
		->setComment('Ensygnia onescan/logintokens entity table');
	$installer->getConnection()->createTable($LoginTokensTable);

	$installer->endSetup();
?>