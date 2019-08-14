<?php
/*
	Ensygnia Onescan extension
	Copyright (C) 2016 Ensygnia Ltd

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
					$this->BuildPurchasePayload($sessionState->SessionID,$purchaseProcess);
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
		/// Build purchase payload.
		/// </summary>
		private function BuildPurchasePayload($sessionID,$purchaseProcess) {
			//Retreive quote from database and reserve order id
			$quote=$this->MagentoRetrieveQuote($sessionID);
			$quote->reserveOrderId();
			$quote->save();

			// Temporarily set the payment method to Onescan to pick up any specific discounts
			$quote->getPayment()->importData(array('method' => 'onescan'));

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

			$payload->PaymentAmount = $totals['grand_total']->getValue();
			$payload->ProductAmount = $payload->PaymentAmount-$payload->Tax;
			$payload->Currency = Mage::app()->getStore($storeid)->getCurrentCurrencyCode();
			$payload->Requires = new PaymentOptIns();
			$payload->Requires->Surcharges = false;
			$payload->Requires->DeliveryOptions=true;

			$payload->ImageData = Mage::getStoreConfig('onescantab/general/onescan_basket-logo-url');

			$discount = 0;
			// Check for a discount
			if(isset($totals['discount']) && $totals['discount']->getValue()) {
				$discount = (float)$totals['discount']->getValue();
			}

			// Check we're above the minimum_order/amount
			$minamountflag = 0;
			if (Mage::getStoreConfig('sales/minimum_order/active', Mage::app()->getStore()) == 1) {
				$minamount = Mage::getStoreConfig('sales/minimum_order/amount', Mage::app()->getStore());
				$minamountflag = ($minamount > $payload->PaymentAmount ? 1 : 0);

				// Need to hold onto this discount and using it in the surcharge callback
				$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
				$onescanData->addFieldToFilter('sessionid', array('like' => $sessionID));
				$onescanData->load();
				$sessionData=$onescanData->getData();

				//Save details to Onescan database tables for later retrieval
				$onescanModel=Mage::getModel('onescan/sessiondata');
				$onescanModel->setId($sessionData[0]['sessiondata_id']);
				$onescanModel->setSessionid($sessionID);
				$onescanModel->setQuoteid($quote->getId());
				$onescanModel->setCustomerid($sessionData[0]['customerid']);
				// TODO: Create a new field for storing min amount flag rather than using shipping tax
				$onescanModel->setShippingtax($minamountflag);
				$onescanModel->save();
			}

			// Add the payload to the process
			$purchaseProcess->ProcessStartPurchase($payload);

			// Add discount details only when minamountflag not set
			if ($minamountflag == 0 && $discount <> 0) {
				$this->AddDiscountPayload($quote, $purchaseProcess->EncodeOutcome(), $discount);
			}

		}

		/// Add discount as a MerchantFieldGroup
		private function AddDiscountPayload($quote, $responseMessage, $discount) {
			$fieldGroup = new MerchantFieldGroup();
			$fieldGroup->GroupHeading = "Discount";
			$fieldGroup->IsMandatory = true;
			$fieldGroup->Code = "DISCOUNT";
			$fieldGroup->GroupType = "Informational";
			//$fieldGroup->AppliesToEachItem = false;
			$fieldGroup->IconUrl = "ion-alert";

			$appliedRuleIds = $quote->getAppliedRuleIds();
			$appliedRuleIds = explode(',', $appliedRuleIds);
			$rules = Mage::getModel('salesrule/rule')->getCollection()->addFieldToFilter('rule_id' , array('in' => $appliedRuleIds));
			$fieldGroup->Fields = array();
			foreach ($rules as $rule) {
				$field = new MerchantField();
				$field->Label = $rule['description'];
				array_push($fieldGroup->Fields, $field);
			}
			$field = new MerchantField();
			$currency = Mage::helper('core')->currency( abs($discount) );
			$field->Label = "This is a saving of " . $currency;
			array_push($fieldGroup->Fields, $field);

			$merchantFieldsPayload = new MerchantFieldsPayload();
			$merchantFieldsPayload->FieldGroups[0] = $fieldGroup;
			// Add payload to responseMessage
			$payload = $responseMessage->AddNewPayloadItem();
			$payload->JsonPayload = json_encode($merchantFieldsPayload);
			$payload->PayloadName = MerchantFieldsPayload::Name;
		}

		/// <summary>
		/// Build some extra information about further charges.
		/// </summary>
		private function BuildAdditionalCharges($process) {
			$additionalChargesContext = $process->AdditionalChargesContext;
			$requires=$process->PurchaseInfo->Requires;
			$purchaseCharges = new AdditionalChargesPayload();

			// Temporarily setting the payment method to Onescan doesnt give us any onescan specific discounts here
			// so we are retrieving them from session store ...
			//$quote=$this->MagentoRetrieveQuote($process->SessionState()->SessionID);
			//$quote->getPayment()->importData(array('method' => 'onescan'));

			// So we are relying on storing amount and retrieving here.
			$onescanData = Mage::getModel('onescan/sessiondata')->getCollection();
			$onescanData->addFieldToFilter('sessionid', array('like' => $process->SessionState()->SessionID));
			$onescanData->load();
			$sessionData=$onescanData->getData();
			// TODO: Move to a dedicated field to store discount.
			$discount = $sessionData[0]['shippingamount'];
			$minamountflag = $sessionData[0]['shippingtax'];

			if ($minamountflag == 1) {
				$purchaseCharges->AddressNotSupported=1;
				$purchaseCharges->AddressNotSupportedReason = Mage::getStoreConfig('sales/minimum_order/description', Mage::app()->getStore());
				return $purchaseCharges;
			}

			/*
			if ($requires->Surcharges) {
				$purchaseCharges->PaymentMethodCharge = new PaymentMethodCharge();
				$purchaseCharges->PaymentMethodCharge->Code = "DISCOUNT";
				// TODO: Move description to config option
				$purchaseCharges->PaymentMethodCharge->Description = "This order has a discount";
				$purchaseCharges->PaymentMethodCharge->Charge = new Charge();
				$purchaseCharges->PaymentMethodCharge->Charge->BaseAmount = $discount;
				$purchaseCharges->PaymentMethodCharge->Charge->Tax = 0;
				$purchaseCharges->PaymentMethodCharge->Charge->TotalAmount = $discount;
			}
			*/
			// Now set delivery options/charges
			if ($requires->DeliveryOptions && !empty($additionalChargesContext->DeliveryAddress)){
				$this->AddDeliveryCharges($additionalChargesContext->DeliveryAddress,$purchaseCharges,$process);
			}

			//Surcharges not yet supported
			/*if ($requires->Surcharges && !empty($additionalChargesContext->PaymentMethod)){
				if(!$this->AddPaymentMethodCharges($additionalChargesContext->PaymentMethod,$purchaseCharges,$process)){
					//REPORT SURCHARGES ERROR TO DEVICE;
				}
			}*/

			return $purchaseCharges;
		}

		/*
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
			$purchaseCharges->PaymentMethodCharge->Description = "The onescan play card attracts a ?1 surcharge";
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
		*/

		/// <summary>
		/// Add delivery charges for the specified address.
		/// </summary>
		private function AddDeliveryCharges($address,$purchaseCharges,$process) {
			//Retreive quote from database
			$quote=$this->MagentoRetrieveQuote($process->SessionState()->SessionID);

			//Retrieve allowed shipping rates.
			$result=$this->MagentoGetShippingRates($quote,$address);

			//If retrieval of shipping rates was unsuccessful, return the error.
			if(gettype($result)=='string'){
				$purchaseCharges->AddressNotSupported=1;
				$purchaseCharges->AddressNotSupportedReason=$result;
				return false;
			}
			$allowedRates = $result;

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
					//Round shipping tax down
					$option->Charge->Tax = floor(($amount-$amount/(1+$shippingTaxPercent/100))*100)/100;
					if(Mage::getStoreConfig('tax/display/type',Mage::app()->getStore())==1){
						//Show price excluding tax
						$option->Charge->BaseAmount = $amount-$option->Charge->Tax;
					}else{
						//Show price including tax
						$option->Charge->BaseAmount = $amount;
					}
					$option->Charge->BaseAmount = $amount;
					$purchaseCharges->DeliveryOptions[]=$option;
				}
			}

			if(count($purchaseCharges->DeliveryOptions)==0){
				//No shipping rates available for this order, return an error
				$purchaseCharges->AddressNotSupported=1;
				$purchaseCharges->AddressNotSupportedReason=Mage::getStoreConfig('onescantab/general/onescan_cannot-deliver-message');
				return false;
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
			$onescanModel->save();

			return true;
		}

				protected function MagentoGetShippingRates($quote,$address){
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
					'street' => $address->AddressLine1 . ( property_exists($address,'AddressLine2') ? ', ' . $address->AddressLine2 : ''),
					'city' => $address->Town,
					'postcode' => $address->Postcode,
					'telephone' => '0',
					'country_id' => $address->CountryCode,
				);

				// Check that this is an allowed country first.
				$validCountry = false;
				$countryList = Mage::getModel('directory/country')->getResourceCollection()->loadByStore()->toOptionArray(true);
				foreach($countryList as $country){
					if ($address->CountryCode == $country['value']) {
						$validCountry = true;
						break;
					}
				}
				if (!$validCountry) {
					//Country not allowed
					return Mage::getStoreConfig('onescantab/general/onescan_cannot-deliver-message');
				}

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
					if(!$regionMatch && strlen($address->County)==2){
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

			//If FirstName or LastName are blank, Magento can't proceed with order
			if ($process->PaymentConfirmation->FirstName=="") {
				$process->PaymentConfirmation->FirstName="First";
			}
			if ($process->PaymentConfirmation->LastName=="") {
				$process->PaymentConfirmation->LastName="Last";
			}

			//Make sure we have an account and are logged in to Magento
			$this->MagentoLogin($customerid,$quoteid,$process);

			//Place the order
			$orderId=$this->MagentoPlaceOrder($process,$quoteid);

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

		protected function MagentoLogin($customerid,$quoteid,$process){
			//Retreive quote from database
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			$quote=$cart->getQuote();
			$sessionID=$process->SessionState()->SessionID;

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

		protected function MagentoPlaceOrder($process,$quoteid){
			//Retreive quote from database
			$cart=Mage::getModel('checkout/cart')->getCheckoutSession();
			$cart->setQuoteId($quoteid);
			$quote=$cart->getQuote();

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
				->setShippingMethod($process->PaymentConfirmation->DeliveryMethodCode)
				->setPaymentMethod('onescan');

			$quote->getPayment()->importData(array('method' => 'onescan'));

			//Create order from quote
			$service = Mage::getModel('sales/service_quote', $quote);
			$service->submitAll();
			$order = $service->getOrder();

			//Add payment to order
			$payment = $order->getPayment();
			$payment->setTransactionId($process->PaymentConfirmation->GatewayTransactionId)
				->setCurrencyCode($order->getBaseCurrencyCode())
				->setPreparedMessage('Comment')
				->setIsTransactionClosed(0)
				->registerCaptureNotification($process->PaymentConfirmation->AmountCharged->PaymentAmount);
			$order->save();

			//Delete quote
			$quote->setIsActive(false);
			$quote->delete();

			return $order->getIncrementId();
		}
	}
?>
