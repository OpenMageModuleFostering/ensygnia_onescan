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

	class Ensygnia_Onescan_Model_Callback_Login extends Ensygnia_Onescan_Model_Callback_Abstract {
		public function getConfigReference() {
			return 'onescan/config_login';
		}

		public function handle() {
			$settings = $this->getConfig()->getSettings();
	
			$session = Mage::getSingleton('customer/session');
			
			$onescanMessage = Onescan::ReadMessage($settings);
		
			$loginProcess = new OnescanLoginProcess();
			$credentials = $loginProcess->DecodeLoginCredentials($onescanMessage,$settings, LoginMode::TokenOrCredentials);
			
			$sessionState=$loginProcess->SessionState();
			$sessionID = $sessionState->SessionID;
		
			$loginResult = new LoginResult();
			
			$loginTokens = Mage::getModel('onescan/logintokens')->getCollection();
			$loginTokens->addFieldToFilter('onescantoken', array('like' => $credentials->OnescanUserIdentityToken));
			$loginTokens->load();
			$details=$loginTokens->getData();
			if (empty($details)) {
				//We do not recognise the user token, so we check for username and password
				if ($credentials->Username == null || $credentials->Username == "") {
					$loginResult->NextAction = LoginNextAction::FallbackToUsernamePassword;
					$loginResult->EndProcess = false;
				} else {
					$customer = Mage::getModel('customer/customer');
					$customer->setWebsiteId(Mage::app()->getWebsite()->getId());
					try {
						$customer->authenticate($credentials->Username,$credentials->Password);
						$newToken=Mage::getModel('onescan/logintokens');
						$newToken->setOnescantoken($credentials->OnescanUserIdentityToken);
						$newToken->setMagentouserid($customer->getId());
						$newToken->save();
						
						LocalDataFactory::storeObject($sessionID,$customer->getId());
						LocalDataFactory::storeObject($sessionID . '-message',Mage::getStoreConfig('onescantab/general/onescan_login-success-message'));
						
						$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginsuccess';
						$loginResult->NextAction = LoginNextAction::LoginSuccessful;
					} catch (Mage_Core_Exception $e) {
						switch ($e->getCode()) {
							case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
								$value = Mage::helper('customer')->getEmailConfirmationUrl($credentials->Username);
								$message = Mage::helper('customer')->__(Mage::getStoreConfig('onescantab/general/onescan_email-not-confirmed-message'), $value);
								break;
							default:
								$message = $e->getMessage();
						}
						LocalDataFactory::storeObject($sessionID . '-message',$message);
						$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginfailure';
						$loginResult->NextAction = LoginNextAction::LoginSuccessful;
					}
				}
			} else {
				//We recognise the user token, so we can log in		
				LocalDataFactory::storeObject($sessionID,$details[0]['magentouserid']);
				LocalDataFactory::storeObject($sessionID . '-message',Mage::getStoreConfig('onescantab/general/onescan_login-success-message'));
				
				$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginsuccess';
				$loginResult->NextAction = LoginNextAction::LoginSuccessful;
			}
			$responseMessage = $loginProcess->EncodeOutcome($loginResult,$settings);
		
			Onescan::SendMessage($responseMessage,$settings);

			return parent::handle();
		}	
	}