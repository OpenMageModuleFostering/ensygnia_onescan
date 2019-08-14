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
	
	class Ensygnia_Onescan_Model_Callback_Purchase extends Ensygnia_Onescan_Model_Callback_Abstract {
		public function getConfigReference() {
			return 'onescan/config_purchase';
		}
	
		public function handle() {
			$settings = $this->getConfig()->getSettings();
			if (isset($_GET['basket'])) {
				$settings->OnescanCallbackURL .= '?basket=' . $_GET['basket'];
			}
			
			$onescanMessage = Onescan::ReadMessage($settings);
			
			$purchaseProcess = new OnescanPurchaseProcess();
			$nextAction = $purchaseProcess->DecodePurchaseMessage($onescanMessage);
			$sessionState = $purchaseProcess->SessionState();
			
			switch ($nextAction)
			{
				case PurchaseAction::StartPayment:
					$purchasePayload = $this->BuildPurchasePayload($sessionState->SessionID);
					$purchaseProcess->ProcessStartPurchase($purchasePayload);
					break;
				case PurchaseAction::AdditionalCharges:
					$purchaseProcess->DecodeAdditionalChargesRequest($onescanMessage);
					$additionalChargesPayload = $this->BuildAdditionalCharges($purchaseProcess);
					$purchaseProcess->ProcessAdditionalCharges($additionalChargesPayload);
					break;
				case PurchaseAction::PaymentConfirmed:
					$purchaseProcess->DecodePaymentConfirmed($onescanMessage);
					$orderAcceptedPayload = $this->BuildOrderAccepted($purchaseProcess);
					$purchaseProcess->ProcessPaymentConfirmed($orderAcceptedPayload);
					break;
				case PurchaseAction::PaymentFailed:
					$purchaseProcess->ProcessPaymentFailed();
					break;
				case PurchaseAction::PaymentCancelled:
					$purchaseProcess->ProcessPaymentCancelled();
					break;
			}
			 
			$responseMessage = $purchaseProcess->EncodeOutcome();
			 
			Onescan::SendMessage($responseMessage,$settings);

			return parent::handle();
		}

		/// <summary>
		/// Build some extra information about further charges.
		/// </summary>
		public function BuildAdditionalCharges($process) {
			$additionalChargesContext = $process->AdditionalChargesContext;
			$requires=$process->PurchaseInfo->Requires;
			$purchaseCharges = new AdditionalChargesPayload();
	
			if ($requires->DeliveryOptions && !empty($additionalChargesContext->DeliveryAddress))
				$this->AddDeliveryCharges($additionalChargesContext->DeliveryAddress,$purchaseCharges,$process);
	
			if ($requires->Surcharges && !empty($additionalChargesContext->PaymentMethod)) 
				$this->AddPaymentMethodCharges($additionalChargesContext->PaymentMethod,$purchaseCharges,$process);
					
			return $purchaseCharges;
		}

		/// <summary>
		/// Add charges for the specified payment method.
		/// </summary>
		public function AddPaymentMethodCharges($paymentMethod,$purchaseCharges,$process) {
			switch ($paymentMethod->PaymentMethodType) {
				case 'CreditCard':
					$this->HandleCreditCardCharges($paymentMethod,$purchaseCharges,$process);
					break;
				case 'OnescanPlay':
					$this->HandlePlayCardCharges($paymentMethod,$purchaseCharges,$process);
					break;
			}
		}

		/// <summary>
		/// Handle charges for the onescan test card.
		/// </summary>
		public function HandlePlayCardCharges($paymentMethod,$purchaseCharges,$process) {
			$cardDetails = $paymentMethod->CardInformation;
	
			$purchaseCharges->PaymentMethodCharge = new PaymentMethodCharge();
			$purchaseCharges->PaymentMethodCharge->Code = "PLAY";
			$purchaseCharges->PaymentMethodCharge->Description = "The onescan play card attracts a £1 surcharge";
			$purchaseCharges->PaymentMethodCharge->Charge = new Charge();
			$purchaseCharges->PaymentMethodCharge->Charge->BaseAmount = 1.00;
			$purchaseCharges->PaymentMethodCharge->Charge->Tax = 0;
			$purchaseCharges->PaymentMethodCharge->Charge->TotalAmount = 1.00;
		}

		/// <summary>
		/// Handle charges for credit cards
		/// </summary>
		public function HandleCreditCardCharges($paymentMethod,$purchaseCharges,$process) {
			$cardDetails = $paymentMethod->CardInformation;
	
			if ($cardDetails->PaymentSystemCode == "VISA") {
	
				$surcharge = round($process->PurchaseInfo->PaymentAmount * 0.01, 2);
	
				$purchaseCharges->PaymentMethodCharge = new PaymentMethodCharge();
				$purchaseCharges->PaymentMethodCharge->Code = "VISA";
				$purchaseCharges->PaymentMethodCharge->Description = "Paying with a credit card attracts a 1% charge";
				$purchaseCharges->PaymentMethodCharge->Charge = new Charge();
				$purchaseCharges->PaymentMethodCharge->Charge->BaseAmount = $surcharge;
				$purchaseCharges->PaymentMethodCharge->Charge->Tax = 0;
				$purchaseCharges->PaymentMethodCharge->Charge->TotalAmount = $surcharge;
			}
		}

		/// <summary>
		/// Add delivery charges for the specified address.
		/// </summary>
		public function AddDeliveryCharges($address,$purchaseCharges,$process) {
			$onescanModel=Mage::getModel('onescan/sessiondata');
			$onescanData = $onescanModel->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $process->SessionState()->SessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();
			$quoteid=$sessionData[0]['quoteid'];
			
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			$quote=$cart->getQuote();
			$quote->getShippingAddress()->setCountryId(Mage::app()->getLocale()->getCountryTranslation(Mage::getStoreConfig('general/country/default')));
			$quote->getShippingAddress()->collectTotals();
			$quote->getShippingAddress()->setCollectShippingRates(true);
			$quote->getShippingAddress()->collectShippingRates();
			$rates = $quote->getShippingAddress()->getShippingRatesCollection();
			
			$purchaseCharges->DeliveryOptions = array();
			foreach ($rates as $rate) {
				$option=new DeliveryOption(); 
				$option->Code = $rate->getCode(); 
				$option->Description = $rate->getMethodDescription();
				if (count($purchaseCharges->DeliveryOptions)==0) {
					$option->IsDefault = true;
				}
				$option->Label = $rate->getMethodTitle();
				$option->Charge = new Charge(); 
				$option->Charge->BaseAmount = $rate->getPrice();
				$option->Charge->Tax = 0;
				$option->Charge->TotalAmount = $rate->getPrice();
				$purchaseCharges->DeliveryOptions[]=$option;
			}
			
			//Temporary fix: Save first shipping rate found
			//Once we can select shipping rate on device we can save the selected shipping rate.
			$option=$purchaseCharges->DeliveryOptions[0];
			$onescanModel->setId($sessionData[0]['sessiondata_id']);
			$onescanModel->setSessionid($process->SessionState()->SessionID);
			$onescanModel->setQuoteid($quoteid);
			$onescanModel->setCustomerid($sessionData[0]['customerid']);
			$onescanModel->setShippingmethod($option->Code);
			$onescanModel->setShippingrate($option->Charge->TotalAmount);
			$onescanModel->save();
		}
		
		public function BuildPurchasePayload($sessionID) {			
			$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $sessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();
			$quoteid=$sessionData[0]['quoteid'];
			
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			
			$quote=$cart->getQuote();
			$quote->reserveOrderId();
			$quote->save();
			
			$storeid=$quote->getStoreId();
			$totals=$quote->getTotals();
	
			$payload = new PurchasePayload();
			$payload->RequiresDeliveryAddress = true;
			$payload->MerchantName = Mage::app()->getStore()->getFrontendName();
			$payload->MerchantTransactionID = GUID();
			$payload->PurchaseDescription = Mage::getStoreConfig('onescantab/general/onescan_basket-name');
			
			if(isset($totals['tax']) && $totals['tax']->getValue()) {
				$payload->Tax = $totals['tax']->getValue();
			} else {
 				$payload->Tax = 0;
			}
			$payload->ProductAmount = $totals['subtotal']->getValue()-$payload->Tax;
			$payload->PaymentAmount = $totals['grand_total']->getValue();
			$payload->Currency = Mage::app()->getStore($storeid)->getCurrentCurrencyCode();
	
			$payload->Requires = new PaymentOptIns();
			$payload->Requires->DeliveryOptions = true;
			$payload->Requires->Surcharges = false;
			$filename = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN).'frontend/' .
						Mage::getSingleton('core/design_package')->getPackageName() . '/' . 
						Mage::getSingleton('core/design_package')->getTheme('frontend') . '/' .
						Mage::getStoreConfig('design/header/logo_src');
			$payload->ImageData = base64_encode(file_get_contents($filename));
		 
			return $payload;
		}
		
		public function BuildOrderAccepted($process) {
			$sessionState=$process->SessionState();
			$sessionID = $sessionState->SessionID;
			
			$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $process->SessionState()->SessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();
			
			$quoteid=$sessionData[0]['quoteid'];
			$customerid=$sessionData[0]['customerid'];
			$shippingMethod=$sessionData[0]['shippingmethod'];
			$shippingRate=$sessionData[0]['shippingrate'];
			
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			$totals=$cart->getQuote()->getTotals();
			$quote=$cart->getQuote();
			
			//TEMPORARY FIX FOR BLANK FIRST AND LAST NAMES
			if ($process->PaymentConfirmation->FirstName=="") {
				$process->PaymentConfirmation->FirstName="First";
			}
			if ($process->PaymentConfirmation->LastName=="") {
				$process->PaymentConfirmation->LastName="Last";
			}

			if ($customerid) {
				// We are already logged in:
				$customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getWebsite()->getId())->load($customerid);
				$quote->assignCustomer($customer);
			} else {
				$loginTokens = Mage::getModel('onescan/logintokens')->getCollection();
				$loginTokens->addFieldToFilter('onescantoken', array('like' => $process->UserToken));
				$loginTokens->load();
				$details=$loginTokens->getData();
				$customer = Mage::getModel('customer/customer');
				$customer->setWebsiteId(Mage::app()->getWebsite()->getId());
				if (empty($details)) {
					//We do not recognise the user token, so we create an account and log in
					$customer->loadByEmail($process->PaymentConfirmation->UserEmail);
					
					if(!$customer->getId()) {
						//New customer registration
						$customer->setEmail($process->PaymentConfirmation->UserEmail);
						$customer->setFirstname($process->PaymentConfirmation->FirstName);
						$customer->setLastname($process->PaymentConfirmation->LastName);
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
							$newToken->setOnescantoken($process->UserToken);
							$newToken->setMagentouserid($customer->getId());
							$newToken->save();
							
							$message=Mage::getStoreConfig('onescantab/general/onescan_register-success-message');
							
							if ($confirmMessage) {
								$value=Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
								$message = Mage::helper('customer')->__(Mage::getStoreConfig('onescantab/general/onescan_email-not-confirmed-message'),$value);
							}
							
							LocalDataFactory::storeObject($sessionID,$customer->getId());
							LocalDataFactory::storeObject($sessionID . '-message',$message);
						}
						 catch (Mage_Core_Exception $e) {
							LocalDataFactory::storeObject($sessionID . '-message',$e->getMessage());
						}
					} else { //Email address recognised so redirect to login page
						LocalDataFactory::storeObject($sessionID . '-message',Mage::getStoreConfig('onescantab/general/onescan_email-exists-message'));	
					}
				} else {
					//We recognise the user token, so we can log in
					$customer->load($details[0]['magentouserid']);
					LocalDataFactory::storeObject($sessionID,$details[0]['magentouserid']);
					LocalDataFactory::storeObject($sessionID . '-message',Mage::getStoreConfig('onescantab/general/onescan_login-success-message'));
				}
				$quote->assignCustomer($customer);
			}

			$country=Mage::app()->getLocale()->getCountryTranslation(Mage::getStoreConfig('general/country/default'));
			$addressData = array(
				'firstname' => $process->PaymentConfirmation->FirstName,
				'lastname' => $process->PaymentConfirmation->LastName,
				'street' => $process->PaymentConfirmation->DeliveryAddress->AddressLine1 . ', ' . $process->PaymentConfirmation->DeliveryAddress->AddressLine2,
				'city' => $process->PaymentConfirmation->DeliveryAddress->Town,
				'postcode' => $process->PaymentConfirmation->DeliveryAddress->Postcode,
				'telephone' => '0',
				'country_id' => $country,
				'region_id' => 0,
			);
			$billingAddress = $quote->getBillingAddress()->addData($addressData);
			$shippingAddress = $quote->getShippingAddress()->addData($addressData);
			$shippingAddress->setCollectShippingRates(true)->collectShippingRates()
				->setShippingMethod($shippingMethod)
				->setPaymentMethod('onescan');
			
			$quote->getPayment()->importData(array('method' => 'onescan'));
			
			$quote->collectTotals();
			
			//Add postage to quote
			$totals['grand_total']->setValue($totals['grand_total']->getValue()+$shippingRate);
			
			$quote->setIsActive(0);
			$quote->save();
			
			$service = Mage::getModel('sales/service_quote', $quote);
			$service->submitAll();
			$order = $service->getOrder();
			
			if(isset($totals['tax']) && $totals['tax']->getValue()) {
				$order->setTaxAmount($totals['tax']->getValue());
				$order->setBaseTaxAmount($totals['tax']->getValue());
				$order->setGrandTotal($totals['grand_total']->getValue());
				$order->setBaseGrandTotal($totals['grand_total']->getValue());
			}
			
			$order->place();
			$order->save();
			$order->sendNewOrderEmail();
			
			$quote->setIsActive(false);
			$quote->delete();
			
			$orderAccepted = new OrderAcceptedPayload();
			$orderAccepted->ReceiptId = $process->ProcessId();
			$orderAccepted->OrderId = $order->getIncrementId();
			return $orderAccepted;
		}

		public function randomPassword() {
			$alphabet = "abcdefghijkmnopqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ23456789";
			$pass='';
			
			for ($i = 0; $i < 8; $i++) {
				$n = rand(0, strlen($alphabet)-1);
				$pass .= $alphabet[$n];
			}
			
			return $pass;
		}
	}
?>