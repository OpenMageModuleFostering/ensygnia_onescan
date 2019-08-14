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
	require_once(Mage::getBaseDir('lib') . "/Onescan/login/onescan-LoginProcess.php");

	class Ensygnia_Onescan_Model_Config_Login extends Ensygnia_Onescan_Model_Config_Abstract {
		protected function _loadSettings() {
			$this->_settings = new OnescanLoginSettings();
			
			$this->_settings->OnescanServerURL = $this->getHelper()->getServerUrl() . "RequestOneScanSession";

			$this->_settings->OnescanCallbackURL = $this->getHelper()->getLoginCallbackUrl();
			
			$this->_settings->OnescanAccountKey = $this->getHelper()->getAccount();
			
			$this->_settings->OnescanSecret = $this->getHelper()->getSecret();

        	$this->_settings->SiteID = Mage::app()->getWebsite()->getId();
        	
        	$this->_settings->FriendlyName = Mage::app()->getStore()->getFrontendName();
        	
        	$this->_settings->SiteLogoURL = $this->getHelper()->getLogoSrcUrl();
        	
        	$this->_settings->LoginMode = LoginMode::TokenOrCredentials;
		}
	}