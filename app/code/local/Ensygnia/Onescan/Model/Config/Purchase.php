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
	require_once(Mage::getBaseDir('lib') . "/Onescan/purchase/onescan-PurchaseInclude.php");

	class Ensygnia_Onescan_Model_Config_Purchase extends Ensygnia_Onescan_Model_Config_Abstract {
		protected function _loadSettings() {
			$this->_settings = new OnescanPurchaseSettings();
		
			$this->_settings->OnescanServerURL = $this->getHelper()->getServerUrl() . "RequestOneScanSession";
			
			$this->_settings->OnescanCallbackURL = $this->getHelper()->getPurchaseCallbackUrl();
			
			$this->_settings->OnescanAccountKey = $this->getHelper()->getAccount();
		
			$this->_settings->OnescanSecret = $this->getHelper()->getSecret();
		}
	}