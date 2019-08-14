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
	
		//Callback entry point
		public function handle() {
			$settings = $this->getConfig()->getSettings();
			if (isset($_GET['basket'])) {
				$settings->OnescanCallbackURL .= '?basket=' . $_GET['basket'];
			}
			
			$onescanMessage = Onescan::ReadMessage($settings);
			//Mage::log('Message: ' . print_r($onescanMessage,true));

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
			//Mage::log('Response: ' . print_r($responseMessage,true));
			 
			Onescan::SendMessage($responseMessage,$settings);
			return parent::handle();
		}

		/// <summary>
		/// Build some extra information about further charges.
		/// </summary>
		private function BuildAdditionalCharges($process) {
			$additionalChargesContext = $process->AdditionalChargesContext;
			$requires=$process->PurchaseInfo->Requires;
			$purchaseCharges = new AdditionalChargesPayload();
	
			if ($requires->DeliveryOptions && !empty($additionalChargesContext->DeliveryAddress)){
				$deliveryChargesResponse=$this->AddDeliveryCharges($additionalChargesContext->DeliveryAddress,$purchaseCharges,$process);
				if($deliveryChargesResponse!==true){
					$purchaseCharges->AddressNotSupported=1;
					$purchaseCharges->AddressNotSupportedReason=$deliveryChargesResponse;
				}
			}
	
			//Surcharges not yet supported
			/*if ($requires->Surcharges && !empty($additionalChargesContext->PaymentMethod)){
				if(!$this->AddPaymentMethodCharges($additionalChargesContext->PaymentMethod,$purchaseCharges,$process)){
					//REPORT SURCHARGES ERROR TO DEVICE;
				}
			}*/
					
			return $purchaseCharges;
		}

		/// <summary>
		/// Add charges for the specified payment method.
		/// </summary>
		private function AddPaymentMethodCharges($paymentMethod,$purchaseCharges,$process) {
			//Surcharges not yet supported, this sample function never gets called
			switch ($paymentMethod->PaymentMethodType) {
				case 'CreditCard':
					$this->HandleCreditCardCharges($paymentMethod,$purchaseCharges,$process);
					break;
				case 'OnescanPlay':
					$this->HandlePlayCardCharges($paymentMethod,$purchaseCharges,$process);
					break;
			}
			return true;
		}

		/// <summary>
		/// Handle charges for the onescan test card.
		/// </summary>
		private function HandlePlayCardCharges($paymentMethod,$purchaseCharges,$process) {
			//Surcharges not yet supported, this sample function never gets called
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
		private function HandleCreditCardCharges($paymentMethod,$purchaseCharges,$process) {
			//Surcharges not yet supported, this sample function never gets called
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
		private function AddDeliveryCharges($address,$purchaseCharges,$process) {
			//Retreive quote from database
			$quote=$this->MagentoRetrieveQuote($process->SessionState()->SessionID);

			//Retrieve allowed shipping rates.
			$shippingError=false;
			$allowedRates=$this->MagentoGetShippingRates($quote,$shippingError,$address);
			
			//If retrieval of shipping rates was unsuccessful, return the error.
			if($shippingError!==false){
				return $shippingError;
			}

			//Get shipping tax rate
			$taxCalculation = Mage::getModel('tax/calculation');
			$request = $taxCalculation->getRateRequest(null,null,null,$quote->getStore());
			$taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class',$quote->getStore());
			$shippingTaxPercent = $taxCalculation->getRate($request->setProductClassId($taxRateId));

			//Build delivery options array to pass back to Onescan
			$purchaseCharges->DeliveryOptions = array();
			foreach ($allowedRates as $rate) {
				$amount=$rate->getPrice();
				if(is_numeric($amount)){
					$option=new DeliveryOption(); 
					$option->Code = $rate->getCode(); 
					$option->Description = $rate->getMethodDescription();
					//Setting default option is not yet supported in the Onescan extension
					/*if($option->Code==$defaultCode){
						$option->IsDefault = true;
					}*/
					$option->Label = $rate->getMethodTitle();
					$option->Charge = new Charge();
					$option->Charge->TotalAmount = $amount;
					$option->Charge->Tax = $amount-($amount*100)/(100+$shippingTaxPercent);
					$option->Charge->BaseAmount = $amount-$option->Charge->Tax;
					$purchaseCharges->DeliveryOptions[]=$option;
				}
			}

			if(count($purchaseCharges->DeliveryOptions)==0){
				//No shipping rates available for this order, return an error
				return Mage::getStoreConfig('onescantab/general/onescan_cannot-deliver-message');
			}
			
			//Retreive Onescan data from database
			$sessionID = $process->SessionState()->SessionID;
			
			$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $sessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();

			//Save details to Onescan database tables for later retrieval
			$onescanModel=Mage::getModel('onescan/sessiondata');
			$onescanModel->setId($sessionData[0]['sessiondata_id']);
			$onescanModel->setSessionid($process->SessionState()->SessionID);
			$onescanModel->setQuoteid($quote->getId());
			$onescanModel->setCustomerid($sessionData[0]['customerid']);
			$onescanModel->setShippingtax($shippingTaxPercent);
			$onescanModel->save();

			return true;
		}

		/// <summary>
		/// Build purchase payload.
		/// </summary>
		private function BuildPurchasePayload($sessionID) {
			//Retreive quote from database and reserve order id
			$quote=$this->MagentoRetrieveQuote($sessionID);
			$quote->reserveOrderId();
			$quote->save();
			
			//Retreive prices from quote
			$storeid=$quote->getStoreId();
			$totals=$quote->getTotals();
	
			//Build Onescan payload from quote data
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
			$payload->PaymentAmount = $totals['subtotal']->getValue();
			$payload->Currency = Mage::app()->getStore($storeid)->getCurrentCurrencyCode();
	
			$payload->Requires = new PaymentOptIns();

			$payload->Requires->Surcharges = false;
			$payload->Requires->DeliveryOptions=true;

			$payload->ImageData = Mage::getStoreConfig('onescantab/general/onescan_basket-logo-url');
		 
			return $payload;
		}
		
		/// <summary>
		/// Build order accepted payload.
		/// </summary>
		private function BuildOrderAccepted($process) {
			//Retreive Onescan data from database
			$sessionID = $process->SessionState()->SessionID;
			
			$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $sessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();
			
			$quoteid=$sessionData[0]['quoteid'];
			$customerid=$sessionData[0]['customerid'];
			$shippingTaxRate=$sessionData[0]['shippingtax'];
			
			//If FirstName or LastName are blank, Magento can't proceed with order
			if ($process->PaymentConfirmation->FirstName=="") {
				$process->PaymentConfirmation->FirstName="First";
			}
			if ($process->PaymentConfirmation->LastName=="") {
				$process->PaymentConfirmation->LastName="Last";
			}

			//Make sure we have an account and are logged in to Magento
			$this->MagentoLogin($customerid,$quoteid);

			//Place the order
			$orderId=$this->MagentoPlaceOrder($process,$shippingTaxRate,$quoteid);

			//Pass data back to Onescan
			$orderAccepted = new OrderAcceptedPayload();
			$orderAccepted->ReceiptId = $process->ProcessId();
			$orderAccepted->OrderId = $orderId;

			return $orderAccepted;
		}

		//Create random password for "manual" login
		private function randomPassword() {
			$alphabet = "abcdefghijkmnopqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ23456789";
			$pass='';
			
			for ($i = 0; $i < 8; $i++) {
				$n = rand(0, strlen($alphabet)-1);
				$pass .= $alphabet[$n];
			}
			
			return $pass;
		}
		
		private function MagentoRetrieveQuote($sessionID){
			//Retrieve quote from database
			$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $sessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();
			$quoteid=$sessionData[0]['quoteid'];
				
			//Retreive quote
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
		
			return $cart->getQuote();
		}
		
		protected function MagentoLogin($customerid,$quoteid){
			//Retreive quote from database
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			$totals=$cart->getQuote()->getTotals();
			$quote=$cart->getQuote();

			if ($customerid) {
				// We are already logged in:
				$customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getWebsite()->getId())->load($customerid);
				$quote->assignCustomer($customer);
			} else {
				//Determine whether Onescan user token is recognised
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
							//Determine whether email confirmation is required then set up account and send appropriate email
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
							
							//Store the Onescan user token in the database and associate it with the Magento account
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
					} else {
						//Email address recognised so redirect to login page
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
		}
		
		protected function MagentoPlaceOrder($process,$shippingTaxRate,$quoteid){
			//Retreive quote from database
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			$totals=$cart->getQuote()->getTotals();
			$quote=$cart->getQuote();
			
			//Calculate shipping values.
			$shippingMethod=$process->PaymentConfirmation->DeliveryMethodCode;
			$totalShippingAmount=$process->PaymentConfirmation->AmountCharged->PostageAmount;
			$shippingTax=$totalShippingAmount-($totalShippingAmount*100)/(100+$shippingTaxRate);
			$shippingAmount=$totalShippingAmount-$shippingTax;
			
			//Add customer name to the quote, along with billing address and shipping address.
			$quote->setcustomerfirstname($process->PaymentConfirmation->FirstName);
			$quote->setcustomerlastname($process->PaymentConfirmation->LastName);

			$addressData=$quote->getBillingAddress()->getData();
			$addressData['firstname']=$process->PaymentConfirmation->FirstName;
			$addressData['lastname']=$process->PaymentConfirmation->LastName;
			$billingAddress = $quote->getBillingAddress()->setData($addressData);

			$addressData=$quote->getShippingAddress()->getData();
			$addressData['firstname']=$process->PaymentConfirmation->FirstName;
			$addressData['lastname']=$process->PaymentConfirmation->LastName;
			$shippingAddress = $quote->getShippingAddress()->setData($addressData);

			//Set shipping and payment methods for order
			$shippingAddress->setCollectShippingRates(true)->collectShippingRates()
				->setShippingMethod($shippingMethod)
				->setPaymentMethod('onescan');
			
			$quote->getShippingAddress()->collectTotals();

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

			//Create order from quote
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
			
			return $order->getIncrementId();
		}
		
		protected function MagentoGetShippingRates($quote,$shippingError,$address){			
			//Coupons not yet supported, sample code for reference
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
			
			//Add address to quote
			try {
				$addressData = array(
					'street' => $address->AddressLine1 . ', ' . $address->AddressLine2,
					'city' => $address->Town,
					'postcode' => $address->Postcode,
					'telephone' => '0',
					'country_id' => $address->CountryCode,
				);

				//Check to see if region/state is required for the delivery country
				$requiredStates=explode(',',Mage::getStoreConfig('general/region/state_required', Mage::app()->getStore()));
				if(in_array($address->CountryCode,$requiredStates)){
					$regions=Mage::getModel('directory/region')
						->getResourceCollection()
						->addCountryFilter($address->CountryCode)
						->load();
	
					//Ensure we have a valid region/state as either the full name or two letter abbreviation
					$regionMatch=false;
					//Check if supplied county text appears within full region name
					foreach($regions as $region){
						if(stripos($region->default_name,$address->County)!==false
								//Use first region if county is empty to allow testing with early version of Onscan app
								|| $address->County==''){
							$addressData['region']=$region->default_name;
							$addressData['region_id']=$region->region_id;
							$regionMatch=true;
							break;
						}
					}
					//If supplied county is two characters long, check if we match the two letter region abbreviation
					if(strlen($address->County)==2){
						foreach($regions as $region){
							if(strcasecmp($address->County,$region->code)==0){
								$addressData['region']=$region->default_name;
								$addressData['region_id']=$region->region_id;
								$regionMatch=true;
								break;
							}
						}
					}
					if(!$regionMatch){
						//Region not found, return an error
						return Mage::getStoreConfig('onescantab/general/onescan_unknown-region-message');
					}
				}

				$billingAddress = $quote->getBillingAddress()->addData($addressData);
				$shippingAddress = $quote->getShippingAddress()->addData($addressData);

				//Get valid shipping rates for this address
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
			
			$quote->save();

			$allowedRates=array();
			foreach ($rates as $rate){
				$rateCode=$rate->getCode();
				if($quote->getShippingAddress()->collectShippingRates()->getShippingRateByCode($rateCode)){
					$allowedRates[$rateCode]=$rate; //Using $rateCode as key removes duplicates
				}
			}

			return $allowedRates;
		}
	}
?>