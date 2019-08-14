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
    class OnescanPurchaseProcess extends OnescanProcess
    {
		/// <summary>
		/// The name of the process.
		/// </summary>
        public static $CONST_PAYMENT_PROCESS_TYPE = "payment";

		/// <summary>
		/// The current session state.
		/// </summary>
        private $sessionState;

		/// <summary>
		/// The message from onescan that we are processing.
		/// </summary>
        private $requestMessage;

		/// <summary>
		/// The message we are building as a response.
		/// </summary>
        private $responseMessage = NULL;

		/// <summary>
		/// Get or set the additional charges context payload
		/// </summary>
		public $AdditionalChargesContext;

		/// <summary>
		/// Get or set the purchase info
		/// </summary>
		public $PurchaseInfo;

		/// <summary>
		/// Get or set the payment confirmation payload.
		/// </summary>
		public $PaymentConfirmation;

		/// <summary>
		/// Get or set the next action to perform
		/// </summary>
		public $NextAction;

		/// <summary>
		/// Return the session state.
		/// </summary>
		public function SessionState()
		{
			return $this->sessionState;
		}
		
		/// <summary>
		/// Get the process ID
		/// </summary>
        public function ProcessId()
        {
            return $this->requestMessage->ProcessId;
        }
		

		/// <summary>
		/// Add any process specific payload and configuration.
		/// </summary>
        public function AddProcessPayload($settings, $message)
        {
            parent::AddProcessPayload($settings, $message);

            //  Define this process type, we are doing payment here
            $message->ProcessType = "payment";

        }

		/// <summary>
		/// Encode the outcome of this process.
		/// </summary>
        public function EncodeOutcome()
        {
            return $this->responseMessage;
        }

		/// <summary>
		/// From the request message, decode the session state.
		/// </summary>
        protected function DecodeSessionState($requestMessage)
        {
			$this->DecodeUserToken($requestMessage);
            $this->sessionState = $requestMessage->FindPayloadAs("partnerNamespace.sessionID");
        }

		/// <summary>
		/// Decode the payment confirmed payload.
		/// </summary>
		public function DecodePaymentConfirmed( $requestMessage ) {
			$this->PaymentConfirmation = $requestMessage->FindPayloadAs(ConfirmPaymentPayload::Name);
		}

		/// <summary>
		/// Decode the purchase message.
		/// </summary>
        public function DecodePurchaseMessage($requestMessage)
        {
            $this->requestMessage = $requestMessage;
            $this->DecodeSessionState($requestMessage);
            switch ($requestMessage->MessageType)
            {
                case "StartPayment":
					$this->NextAction = PurchaseAction::StartPayment;
                    break;

				case "AdditionalCharges" :
					$this->NextAction = PurchaseAction::AdditionalCharges;
					break;

                case "PaymentConfirmed":
                    $this->NextAction = PurchaseAction::PaymentConfirmed;
                    break;

                case "PaymentCancelled":
                    $this->NextAction = PurchaseAction::PaymentCancelled;
                    break;

                case "PaymentFailed":
                    $this->NextAction = PurchaseAction::PaymentFailed;
                    break;
            }
            return $this->NextAction;
        }

		/// <summary>
		/// Decode the additional charges request.
		/// </summary>
		public function DecodeAdditionalChargesRequest($onescanMessage) {
			$this->AdditionalChargesContext = $onescanMessage->FindPayloadAs(PurchaseContextPayload::Name);
			$this->PurchaseInfo = $onescanMessage->FindPayloadAs(CorePurchaseInfo::Name);
		}

		/// <summary>
		/// Handle the start purchase step of the process.
		/// </summary>
        public function ProcessStartPurchase($purchaseInfo)
        {
            if ($this->requestMessage->ProcessType != self::$CONST_PAYMENT_PROCESS_TYPE)
                throw new Exception("Unexpected process type");

            //  we are expecting our session payload to be present
            $sessionPayload = $this->requestMessage->FindPayloadAs("partnerNamespace.sessionID");
            if ($sessionPayload==NULL)
                throw new Exception("Missing session payload");

            $responseMessage = new OneScanMessage();
            $responseMessage->ProcessType = self::$CONST_PAYMENT_PROCESS_TYPE;
            $responseMessage->MessageType = $this->requestMessage->MessageType;
            $responseMessage->ProcessId = $this->requestMessage->ProcessId;

            //  this is the actual information detailing the purchase for this merchant
            $messagePayload = $responseMessage->AddNewPayloadItem();
            $messagePayload->JsonPayload = json_encode($purchaseInfo);
            $messagePayload->PayloadName = CorePurchaseInfo::Name;

            $this->responseMessage = $responseMessage;
        }

		/// <summary>
		/// Process the additional charges payload.
		/// </summary>
		public function ProcessAdditionalCharges($additionalChargesPayload) {
			$responseMessage = new OnescanMessage();
			$responseMessage->MessageType = "AdditionalCharges";
			$responseMessage->ProcessId = $this->requestMessage->ProcessId;
			$responseMessage->ProcessType = self::$CONST_PAYMENT_PROCESS_TYPE;

			$payload = $responseMessage->AddNewPayloadItem();
			$payload->JsonPayload = json_encode($additionalChargesPayload);
			$payload->PayloadName = AdditionalChargesPayload::Name;

            $this->responseMessage = $responseMessage;
		}

		/// <summary>
		/// Process the payment confirmed message when we want to accept the order.
		/// </summary>
        public function ProcessPaymentConfirmed($orderAccepted, $outcome = NULL)
        {
			$this->responseMessage = new OneScanMessage();
            $this->responseMessage->ProcessType = self::$CONST_PAYMENT_PROCESS_TYPE;
            $this->responseMessage->MessageType = "OrderAccepted";
            $this->responseMessage->ProcessId = $this->requestMessage->ProcessId;
			
			if($outcome!=null){
            	$messagePayload = $this->responseMessage->AddNewPayloadItem();
            	$messagePayload->JsonPayload = json_encode($outcome);
            	$messagePayload->PayloadName = ProcessOutcome::Name;
			}
			
			if($orderAccepted!=null){
            	$confirmPayload = $this->responseMessage->AddNewPayloadItem();
            	$confirmPayload->JsonPayload = json_encode($orderAccepted);
            	$confirmPayload->PayloadName = OrderAcceptedPayload::Name;
			}

            //$this->InternalAddOutcome($outcome);

            return $this->responseMessage;
        }

		/// <summary>
		/// Add the outcome.
		/// </summary>
        private function InternalAddOutcome($outcome)
        {
            $outcome = ($outcome == NULL) ? new ProcessOutcome() : $outcome;
            $messagePayload = $this->responseMessage->AddNewPayloadItem();
            $messagePayload->JsonPayload = json_encode($outcome);
            $messagePayload->PayloadName = ProcessOutcome::Name;
        }

		/// <summary>
		/// Process the outcome.
		/// </summary>
        private function InternalProcessOutcome($outcome)
        {
            $this->responseMessage = new OneScanMessage();
            $this->responseMessage->ProcessType = self::$CONST_PAYMENT_PROCESS_TYPE;
            $this->responseMessage->MessageType = $this->requestMessage->MessageType;
            $this->responseMessage->ProcessId = $this->requestMessage->ProcessId;
            $this->InternalAddOutcome($outcome);
        }

		/// <summary>
		/// Process payment cancelled.
		/// </summary>
        public function ProcessPaymentCancelled($outcome = NULL)
        {
            $this->InternalProcessOutcome($outcome);
        }

		/// <summary>
		/// Process payment failed.
		/// </summary>
        public function ProcessPaymentFailed($outcome = NULL)
        {
            $this->InternalProcessOutcome($outcome);
        }
    }


?>
