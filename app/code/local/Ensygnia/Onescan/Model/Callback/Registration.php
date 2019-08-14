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

	class Ensygnia_Onescan_Model_Callback_Registration extends Ensygnia_Onescan_Model_Callback_Abstract {
		public function getConfigReference() {
			return 'onescan/config_login';
		}

		public function handle() {
			$settings = $this->getConfig()->getSettings();
			
			$session = Mage::getSingleton('customer/session');
			
			$onescanMessage = Onescan::ReadMessage($settings);
		
			$loginProcess = new OnescanLoginProcess();
			$credentials = $loginProcess->DecodeLoginCredentials($onescanMessage,$settings);
			
			$sessionState=$loginProcess->SessionState();
			$sessionID = $sessionState->SessionID;
		
			$loginResult = new LoginResult();
			
			$loginTokens = Mage::getModel('onescan/logintokens')->getCollection();
			$loginTokens->addFieldToFilter('onescantoken', array('like' => $credentials->OnescanUserIdentityToken));
			$loginTokens->load();
			$details=$loginTokens->getData();
			if (empty($details)) {
				//We do not recognise the user token, so we create an account and log in
				$customer = Mage::getModel('customer/customer');
				
				$customer->setWebsiteId(Mage::app()->getWebsite()->getId());
				$customer->loadByEmail($credentials->Email);
				
				if(!$customer->getId()) { //New customer registration
					$customer->setEmail($credentials->Email);
					$customer->setFirstname($credentials->Firstname);
					$customer->setLastname($credentials->Lastname);
					$customer->setPassword($this->randomPassword());
					try {
						$customer->save();
						if (Mage::getStoreConfig('onescantab/general/onescan_skip-confirmation') || !$customer->isConfirmationRequired()) {
							$customer->setConfirmation(null);
							$customer->save();
							$customer->sendNewAccountEmail(
								'registered',
								'',
								Mage::app()->getStore()->getId()
							);
							$confirmMessage=false;
						} else {
							$customer->sendNewAccountEmail(
								'confirmation',
                				$session->getBeforeAuthUrl(),
								Mage::app()->getStore()->getId()
							);
							$confirmMessage=true;
						}
						
						$newToken=Mage::getModel('onescan/logintokens');
						$newToken->setOnescantoken($credentials->OnescanUserIdentityToken);
						$newToken->setMagentouserid($customer->getId());
						$newToken->save();
						
						$message=Mage::getStoreConfig('onescantab/general/onescan_register-success-message');
						
						if ($confirmMessage) {
							$value=Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
							$message = Mage::helper('customer')->__(Mage::getStoreConfig('onescantab/general/onescan_email-not-confirmed-message'),$value);
							$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginfailure';
						} else {
							$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginsuccess';
						}
						
						LocalDataFactory::storeObject($sessionID,$customer->getId());
						LocalDataFactory::storeObject($sessionID . '-message',$message);
						$loginResult->NextAction = LoginNextAction::LoginSuccessful;
					}
					 catch (Mage_Core_Exception $e) {
						LocalDataFactory::storeObject($sessionID . '-message',$e->getMessage());
						$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginfailure';
						$loginResult->NextAction = LoginNextAction::LoginSuccessful;
					}
				} else { //Email address recognised so redirect to login page
					LocalDataFactory::storeObject($sessionID . '-message',Mage::getStoreConfig('onescantab/general/onescan_email-exists-message'));		
					$loginResult->BrowserRedirectURL = Mage::helper('core/url')->getHomeUrl() . 'onescan/index/loginfailure';
					$loginResult->NextAction = LoginNextAction::LoginSuccessful;
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

		private function randomPassword() {
			$alphabet = "abcdefghijkmnopqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ23456789";
			$pass='';
			
			for ($i = 0; $i < 8; $i++) {
				$n = rand(0, strlen($alphabet)-1);
				$pass .= $alphabet[$n];
			}
			
			return $pass;
		}
	}