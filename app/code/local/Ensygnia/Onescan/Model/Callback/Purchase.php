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
					//Mage::log('Start Payment');
					$purchasePayload = $this->BuildPurchasePayload($sessionState->SessionID);
					$purchaseProcess->ProcessStartPurchase($purchasePayload);
					break;
				case PurchaseAction::AdditionalCharges:
					//Mage::log('Additional Charges');
					$purchaseProcess->DecodeAdditionalChargesRequest($onescanMessage);
					$additionalChargesPayload = $this->BuildAdditionalCharges($purchaseProcess);
					$purchaseProcess->ProcessAdditionalCharges($additionalChargesPayload);
					break;
				case PurchaseAction::PaymentConfirmed:
					//Mage::log('Payment Confirmed');
					$purchaseProcess->DecodePaymentConfirmed($onescanMessage);
					$orderAcceptedPayload = $this->BuildOrderAccepted($purchaseProcess);
					$purchaseProcess->ProcessPaymentConfirmed($orderAcceptedPayload);
					break;
				case PurchaseAction::PaymentFailed:
					//Mage::log('Payment Failed');
					$purchaseProcess->ProcessPaymentFailed();
					break;
				case PurchaseAction::PaymentCancelled:
					//Mage::log('Payment Cancelled');
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
			$purchaseCharges->PaymentMethodCharge->Description = "The onescan play card attracts a Ã‚Â£1 surcharge";
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
			
			/*if ($quote->getCouponCode() != '') {
				$c = Mage::getResourceModel('salesrule/rule_collection');
				$c->getSelect()->where("code=?", $quote->getCouponCode());
				foreach ($c->getItems() as $item) {
					$coupon = $item;
				}
				if ($coupon->getSimpleFreeShipping() > 0) {
					$quote->getShippingAddress()->setShippingMethod($this->_shippingCode)->save();
					return true;
				}
			}*/
			try {
				if ($quote->getShippingAddress()->getCountryId() == '') {
					$quote->getShippingAddress()->setCountryId($address->CountryCode);
				}
				if ($quote->getShippingAddress()->getPostcode() == '') {
					$quote->getShippingAddress()->setPostcode($address->Postcode);
				}
				$quote->getShippingAddress()->collectTotals();
				$quote->getShippingAddress()->setCollectShippingRates(true);
				$quote->getShippingAddress()->collectShippingRates();
				$rates = $quote->getShippingAddress()->getShippingRatesCollection();
			}
			catch (Mage_Core_Exception $e) {
				Mage::getSingleton('checkout/session')->addError($e->getMessage());
			}
			catch (Exception $e) {
				Mage::getSingleton('checkout/session')->addException($e, Mage::helper('checkout')->__('Load customer quote error'));
			}

			$allowedRates=array();
			$lowestPrice=99999;
			foreach ($rates as $rate){
				$rateCode=$rate->getCode();
				if($quote->getShippingAddress()->collectShippingRates()->getShippingRateByCode($rateCode)){
					$allowedRates[$rateCode]=$rate; //Using $rateCode as key removes duplicates
				}

				//Set the lowest rate as the default
				if($rate->getPrice()<$lowestPrice){
					$lowestPrice=$rate->getPrice();
					$defaultCode=$rateCode;
				}
			}

			$purchaseCharges->DeliveryOptions = array();
			$taxCalculation = Mage::getModel('tax/calculation');
			$request = $taxCalculation->getRateRequest(null,null,null,$quote->getStore());
			$taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class',$quote->getStore());
			$shippingTaxPercent = $taxCalculation->getRate($request->setProductClassId($taxRateId));

			$defaultIndex=0;
			foreach ($rates as $rate) {
				$amount=$rate->getPrice();
				if(is_numeric($amount)){
					$option=new DeliveryOption(); 
					$option->Code = $rate->getCode(); 
					$option->Description = $rate->getMethodDescription();
					if($option->Code==$defaultCode){
						$option->IsDefault = true;
						$defaultIndex=count($purchaseCharges->DeliveryOptions);
					}
					$option->Label = $rate->getMethodTitle();
					$option->Charge = new Charge();
					$option->Charge->TotalAmount = $amount;
					$option->Charge->Tax = $amount-($amount*100)/(100+$shippingTaxPercent);
					$option->Charge->BaseAmount = $amount-$option->Charge->Tax;
					$purchaseCharges->DeliveryOptions[]=$option;
				}
			}
			
			//Make sure the default rate is listed first
			$option=$purchaseCharges->DeliveryOptions[$defaultIndex];
			$purchaseCharges->DeliveryOptions[$defaultIndex]=$purchaseCharges->DeliveryOptions[0];
			$purchaseCharges->DeliveryOptions[0]=$option;

			$onescanModel->setId($sessionData[0]['sessiondata_id']);
			$onescanModel->setSessionid($process->SessionState()->SessionID);
			$onescanModel->setQuoteid($quoteid);
			$onescanModel->setCustomerid($sessionData[0]['customerid']);
			$onescanModel->setShippingmethod($option->Code);
			$onescanModel->setShippingamount($option->Charge->TotalAmount * 100);//Floating point numbers are being stored as integers!
			$onescanModel->setShippingtax($option->Charge->Tax * 100);//Floating point numbers are being stored as integers!
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

			$payload->Requires->Surcharges = false;
			$payload->Requires->DeliveryOptions=true;

			$payload->ImageData = Mage::getStoreConfig('onescantab/general/onescan_basket-logo-url');
		 
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
			$shippingAmount=round($sessionData[0]['shippingamount'],2,PHP_ROUND_HALF_DOWN)/100;//Floating point numbers are being stored as integers!
			$shippingTax=round($sessionData[0]['shippingtax'],2,PHP_ROUND_HALF_DOWN)/100;//Floating point numbers are being stored as integers!
			
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

			$addressData = array(
				'firstname' => $process->PaymentConfirmation->FirstName,
				'lastname' => $process->PaymentConfirmation->LastName,
				'street' => $process->PaymentConfirmation->DeliveryAddress->AddressLine1 . ', ' . $process->PaymentConfirmation->DeliveryAddress->AddressLine2,
				'city' => $process->PaymentConfirmation->DeliveryAddress->Town,
				'postcode' => $process->PaymentConfirmation->DeliveryAddress->Postcode,
				'telephone' => '0',
				'country_id' => $process->PaymentConfirmation->DeliveryAddress->CountryCode,
//*** NEED TO SET CORRECT REGION ID ***//
				'region_id' => 0,
			);
			//$quote->getShippingAddress()->collectTotals();

			$billingAddress = $quote->getBillingAddress()->addData($addressData);
			$shippingAddress = $quote->getShippingAddress()->addData($addressData);
			$shippingAddress->setCollectShippingRates(true)->collectShippingRates()
				->setShippingMethod($shippingMethod)
				->setPaymentMethod('onescan');
			
			$quote->getShippingAddress()->collectTotals();
			//$quote->collectTotals();

			$quote->getPayment()->importData(array('method' => 'onescan'));

			foreach($quote->getAllItems() as $item){
				$totalPrice=round($item->getProduct()->getFinalPrice(),2,PHP_ROUND_HALF_DOWN)*$item->getQty();
				$taxAmount=round($totalPrice-$item->getPrice(),2,PHP_ROUND_HALF_DOWN);
				$item->setTaxAmount($taxAmount);
				$item->setTaxPercent(round($taxAmount*100/($totalPrice-$taxAmount),2));
			}

			//Add postage to quote
			$totals['grand_total']->setValue(round($totals['grand_total']->getValue(),2,PHP_ROUND_HALF_DOWN));
			
			$quote->setIsActive(0);
			$quote->save();

			$service = Mage::getModel('sales/service_quote', $quote);
			$service->submitAll();

			$order = $service->getOrder();
			$order->setShippingMethod($shippingMethod);

			$amountCharged=$process->PaymentConfirmation->AmountCharged;
			$order	->setSubtotal($amountCharged->BasketAmount)
				->setSubtotalIncludingTax($amountCharged->BasePaymentAmount)
				->setBaseSubtotal($amountCharged->BasketAmount)
				->setGrandTotal($amountCharged->PaymentAmount)
				->setBaseGrandTotal($amountCharged->PaymentAmount)
				->setBaseTaxAmount(round($amountCharged->BasketTax+$shippingTax,2,PHP_ROUND_HALF_DOWN))
				->setTaxAmount(round($amountCharged->BasketTax+$shippingTax,2,PHP_ROUND_HALF_DOWN));

			$order->getPayment()->capture(null);
			
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