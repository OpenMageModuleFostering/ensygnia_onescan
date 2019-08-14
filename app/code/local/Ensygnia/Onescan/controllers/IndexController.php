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
	require_once(Mage::getBaseDir('lib') . "/Onescan/login/LocalDataFactory.php");
	
    class Ensygnia_Onescan_IndexController extends Mage_Core_Controller_Front_Action {
		public function createsessionfrombasketAction() {
			$purchaseSettings = Mage::getModel('onescan/config_purchase')->getSettings();
			$sessionvarname='guid';
			if (isset($_GET['basket'])) {
				$sessionvarname.=$_GET['basket'];
				$purchaseSettings->OnescanCallbackURL .= '?basket=' . $_GET['basket'];
			}
		 
			try {
				$purchaseProcess = new OnescanPurchaseProcess();
				$purchaseSessionID = GUID();
				$_SESSION[$sessionvarname]=$purchaseSessionID;
				$purchaseProcess->CreateSecurityCookie($purchaseSettings, $purchaseSessionID);
				$response = $purchaseProcess->CreateOnescanSessionFromSessionData($purchaseSettings, $purchaseSessionID);
			}
			catch (Exception $e)
			{
				//Error Logging goes here.
				$errorResponse = new OneScanResponseMessage();
				$errorResponse->Success = false;
				$response = json_encode($errorResponse);
			}
			
			//Store the current quote ID and customer ID (if logged in) against the session ID
			$onescanData=Mage::getModel('onescan/sessiondata');
			$onescanData->setSessionid($purchaseSessionID);
			$onescanData->setQuoteid(Mage::getModel('checkout/cart')->getQuote()->getId());
			$onescanData->setCustomerid(Mage::getSingleton('customer/session')->getCustomer()->getId());
			$onescanData->save();
			
			return $response;
		}
		
		public function createregistrationsessionAction() {
		 	$registrationSettings = Mage::getModel('onescan/config_registration')->getSettings();
			
			try {
				$registrationProcess = new OnescanLoginProcess();
				$registrationSessionID = GUID();
				$_SESSION['guid']=$registrationSessionID;
				$registrationProcess->CreateSecurityCookie($registrationSettings,$registrationSessionID);
				$response = $registrationProcess->CreateOnescanSessionFromSessionData($registrationSettings,$registrationSessionID);
			}
			catch (Exception $e)
			{
				//Error Logging goes here.
				$errorResponse = new OneScanResponseMessage();
				$errorResponse->Success = false;
				$response = json_encode($errorResponse);
			}
			
			return $response;
		}
		
		public function createloginsessionAction() {
		 	$loginSettings = Mage::getModel('onescan/config_login')->getSettings();
			
			try {
				$loginProcess = new OnescanLoginProcess();
				$loginSessionID = GUID();
				$_SESSION['guid']=$loginSessionID;
				$loginProcess->CreateSecurityCookie($loginSettings,$loginSessionID);
				$response = $loginProcess->CreateOnescanSessionFromSessionData($loginSettings,$loginSessionID);
			}
			catch (Exception $e)
			{
				//Error Logging goes here.
				$errorResponse = new OneScanResponseMessage();
				$errorResponse->Success = false;
				$response = json_encode($errorResponse);
			}
			
			return $response;
		}
		
		public function loginsuccessAction() {
			$sessionvarname='guid';
			if (isset($_GET['basket'])) {
				$sessionvarname.=$_GET['basket'];
			}
			$loginSessionID=$_SESSION[$sessionvarname];
			$customerID = LocalDataFactory::getStoredObject($loginSessionID);
			$message = LocalDataFactory::getStoredObject($loginSessionID . '-message');
			$redirect='customer/account/';
			if ($customerID != null) {
				$customer = Mage::getModel('customer/customer')->load($customerID);
				
				if ($customer->getId()==null) {
					//Customer no longer exists!
					
					//Delete all login tokens associated with this customer
					$loginTokens = Mage::getModel('onescan/logintokens')->getCollection();
					$loginTokens->addFieldToFilter('magentouserid', array('like' => $customerID));
					$loginTokens->load();
					foreach($loginTokens as $loginToken) {
						$loginToken->delete();
					}
					
					$message = Mage::getStoreConfig('onescantab/general/onescan_customer-deleted-message');
					if ($message != '') {
						Mage::getSingleton('core/session')->addError($message);
					}
					$redirect='customer/account/create/';
				} elseif($customer->getConfirmation()!=null) {
					//Customer not confirmed
					$value=Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
					$message = Mage::helper('customer')->__(Mage::getStoreConfig('onescantab/general/onescan_email-not-confirmed-message'),$value);
					if ($message != '') {
						Mage::getSingleton('core/session')->addError($message);
					}
				} else {
					//Customer can be logged in
					Mage::getSingleton('customer/session')->loginById($customerID);
					if ($message != '') {
						Mage::getSingleton('core/session')->addSuccess($message);
					}
				}
			} elseif ($message != '') {
				Mage::getSingleton('core/session')->addError($message);
			}
			LocalDataFactory::removeStoredObject($loginSessionID);
			LocalDataFactory::removeStoredObject($loginSessionID . '-message');
			
			session_write_close();
			
			$this->_redirect($redirect);
		}
		
		public function loginfailureAction() {			
			$loginSessionID=$_SESSION['guid'];
			$message = LocalDataFactory::getStoredObject($loginSessionID . '-message');
			LocalDataFactory::removeStoredObject($loginSessionID);
			LocalDataFactory::removeStoredObject($loginSessionID . '-message');
			Mage::getSingleton('core/session')->addError($message);
			session_write_close();
			
			$this->_redirect('customer/account/');
		}
		
		public function purchasesuccessAction() {
			//Empty Cart
			$cartHelper = Mage::helper('checkout/cart');
			$items = $cartHelper->getCart()->getItems();
			foreach ($items as $item) {
				$cartHelper->getCart()->removeItem($item->getItemId())->save();
			}
						
			Mage::getSingleton('core/session')->addSuccess('Thank you for ordering with Onescan.');
			$this->loginsuccessAction();
		}
    }
?>