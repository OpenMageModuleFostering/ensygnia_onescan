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
	require_once("onescan-SessionState.php");
	require_once("onescan-HMAC.php");
	require_once("onescan-HTTPClient.php");
	require_once("onescan-Message.php");
	require_once("onescan-Exceptions.php");
	
	/// <summary>
	/// Represents a payload that can be added to each message sent to a partner
	/// to uniquely identify the user. This token is expected to be constant for 
	/// a given user and partner pairing.
	/// </summary>
	class UserTokenPayload
	{
		const Name="Onescan.UserToken";
		public $UserToken;
	}
	
	class ProcessOutcome
	{
		const Name='Onescan.ProcessOutcome';
		public $RedirectURL;
		public $RedirectAsFormPost;
		public $FormPostData;
		public $UserMessage;
	}
	
	class OnescanProcess
	{
		public $UserToken;
	
		/// <summary>
		/// Get the token uniquely representing the logged in user.
		/// </summary>
		protected function DecodeUserToken( $requestMessage ) {
			$payload = $requestMessage->FindPayloadAs(UserTokenPayload::Name);
			$this->UserToken = $payload->UserToken;
		}
		
		public function BuildSessionStateManager()
		{
			return new SimpleSessionStateManager();
		}
	
		/// <summary>
		/// Build the common parts of the message that we will send to Onescan
		/// </summary>
		/// <returns></returns>
		private function BuildBasicMessage($settings, $sessionState)
		{
	
			$sessionMessage = new OneScanMessage();
	
			$metadataPayload = $sessionMessage->AddNewPayloadItem();
			$metadataPayload->PayloadName = 'onescan.metadata';
			$metadata = new OneScanMetadata();
			$metadata->EndpointURL = $settings->OnescanCallbackURL;
			$metadata->VisualCodeTTL = 500 * 60;
	
			$metadataPayload->JsonPayload = json_encode($metadata);
	
			// And we add a custom payload to it
			$payloadItem = $sessionMessage->AddNewPayloadItem();
			$payloadItem->PayloadName = 'partnerNamespace.sessionID';
			$payloadItem->JsonPayload = json_encode($sessionState);
	
			return $sessionMessage;
		}

        private function SetSessionState($message,$settings,$sessionState)
        {
            // And we add a custom payload to it
            $payloadItem = $message->AddNewPayloadItem();
            $payloadItem->PayloadName = 'partnerNamespace.sessionID';
            $payloadItem->JsonPayload = json_encode($sessionState);
        }

        public function UpdateSessionState($message,$settings,$sessionState)
        {
            $this->SetSessionState($message,$settings,$sessionState);
            return;
            // And we add a custom payload to it
            /*string sessionStatePayloadName = settings.PartnerNamespace + ".sessionID";
            PayloadItem payload = message[sessionStatePayloadName];
            if (payload == null)
                throw new ArgumentException("Payload must contain a PayloadName entry", "PayloadItem");

            try
            {
                payload.JsonPayload = JSonUtils.Serialise(sessionState);
            }
            catch (Exception ex)
            {
            }*/
        }

		/// <summary>
		/// Build a message that we
		/// </summary>
		public function BuildStaticResponseMessage($settings,$sessionId) {
			$sessionState = $this->BuildOnescanSessionState($sessionId);
			$message = $this->BuildBasicMessage($settings,$sessionState);
			$responseMessage = Onescan::BuildResponseMessage($message);
			$message->MergePayloads($responseMessage);
			return $responseMessage;
		}
	
		/// <summary>
		/// Passes the data describing the process we want to initiate
		/// over to onescan and gets back a session object that we can
		/// use to render the QR code 
		/// </summary>
		private function InternalCreateOnescanSession($settings,$sessionState)
		{
			//  create payload
			//  lodge session data
			//  perform HMAC signing
			//  call OnescanServer and grabresult
			$message = $this->BuildBasicMessage($settings,$sessionState);
			$this->AddProcessPayload($settings,$message);
			$messageAsJson = json_encode($message);
			$hmac = HMAC::Encode($messageAsJson,$settings->OnescanSecret);
	
				$client = new HTTPClient();
				$response = $client->Post($settings->OnescanAccountKey, $hmac, $settings->OnescanServerURL, $messageAsJson);
	
				$onescanSessionMessage = json_decode($response);
				$jsonPayload = $onescanSessionMessage->Payloads[0]->JsonPayload;
	
			return $response;
		}
	
		public function AddProcessPayload($settings, $message)
		{
		}
	
		public function BuildOnescanSessionState($sessionData)
		{
			$sessionState = new OnescanSessionState();
			$sessionState->SessionID = $sessionData;
			return $sessionState;
		}
	
		public function CreateOnescanSessionFromSessionData($settings, $sessionData)
		{
			$sessionState = $this->BuildOnescanSessionState($sessionData);
			return $this->CreateOnescanSessionFromSessionState($settings, $sessionState);
		}
	
		public function CreateOnescanSessionFromSessionState($settings, $sessionState)
		{
			return $this->InternalCreateOnescanSession($settings, $sessionState);
		}
	
		public function CreateSecurityCookie($settings, $sessionID)
		{
			$settingsAsJson = json_encode($settings);
            $Expires = time()+$settings->SecurityCookieLifetimeSeconds;
            $Secure = $settings->CookieIsSecure;
			setcookie($sessionID, HMAC::Encode($settingsAsJson,$sessionID),$Expires,"/",$_SERVER["SERVER_NAME"],$Secure);
		}
	
	    public function CheckSecurityCookie($settings,$sessionID)
	    {
	        $result = false;
	        $securityCookie = $_COOKIE[$sessionID];
	        if ($securityCookie != null)
	        {
	            $hmac = HMAC_Encode($settings,$sessionID);
	            if ($securityCookie != null)
	            {
	                $result = $hmac == $securityCookie;
	            }
	        }
	        return $result;
	    }
	}
?>
