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
	$logindir=dirname(__FILE__);
	$coredir=str_replace("login","core",$logindir);

	require_once($coredir . "/onescan-Settings.php");
	require_once($coredir . "/onescan-Process.php");
	require_once($coredir . "/onescan-Onescan.php");
	require_once($coredir . "/onescan-GUID.php");
	require_once($coredir . "/onescan-HMAC.php");
	require_once($coredir . "/onescan-Message.php");
	require_once($coredir . "/onescan-Login.php");

	class OnescanLoginSettings extends OnescanSettings
	{
        /// <summary>
        /// The SiteID supplied by Onescan.  This is used in the device vault
        /// </summary>
        public $SiteID;
		
        /// <summary>
        /// A friendly name for displaying on the Device login prompt screen
        /// </summary>
        public $FriendlyName;

        /// <summary>
        /// A url to a publically available logo for the site/company authorising access
        /// </summary>
        public $SiteLogoURL;

        /// <summary>
        /// What login mode(s) do we wish to support
        /// </summary>
        public $LoginMode;

        public $Profiles=array();
	}

    class LoginCredentials
    {
        public $Mode;
        public $Username;
        public $Password;
        public $OnescanUserIdentityToken;

        public $Profiles=array();

        //  basic profile data
        public $Firstname;
        public $Lastname;
        public $Email;
    }
	
    class LoginResult
    {
        public function LoginResult()
        {
            $this->NextAction = LoginNextAction::Unknown;

            //  Default Success out to true, this should be set to fault and a DeviceMessage provided in the case we want to signal failure
            $this->Success = true;
            $this->EndProcess = true;
        }

        public $Success;
        public $EndProcess;
        public $BrowserRedirectURL;
        public $RedirectAsFormPost;
        public $FormPostData;
        public $DeviceMessage;
        public $NextAction;
    }
	
	abstract class LoginNextAction
    {
        const Unknown=0;
        const LoginSuccessful=1;
        const FallbackToUsernamePassword=2;
        const PromptForRetry=3;
        const RequestUserDetails=4;
        const SignalFailure=5;
    }
	
	class OnescanLoginProcess extends OnescanProcess
	{
        private $sessionState;
        private $requestMessage;

        public function AddProcessPayload($settings,$message)
        {
            parent::AddProcessPayload($settings,$message);

            //  Define this process type, we are doing login here
            $message->ProcessType = "login";

            $payloadItem = $message->AddNewPayloadItem();
            $payloadItem->PayloadName = "Onescan.LoginDevicePayload";

			$loginSettings = new OnescanLoginSettings;
            $loginSettings = $settings;

            $siteDetails = new LoginDevicePayload();
            $siteDetails->FriendlyName = $loginSettings->FriendlyName;
            $siteDetails->SiteIdentifier = $loginSettings->SiteID;
            $siteDetails->SiteLogoUrl = $loginSettings->SiteLogoURL;
            $siteDetails->LoginMode = $loginSettings->LoginMode;
            $siteDetails->Profiles = $loginSettings->Profiles != null ? $loginSettings->Profiles : array("basic");
            $payloadItem->JsonPayload = json_encode($siteDetails);
        }

        public function DecodeLoginCredentials($message,$settings,$defaultMode = LoginMode::UserToken)
        {
            $creds = new LoginCredentials();
            //  we arrive here only if there is a valid message

            $userTokenPayload = $message->UserTokenPayload();
            if (($userTokenPayload != null) && ($userTokenPayload->UserToken != null))
            {
                $creds->OnescanUserIdentityToken = $userTokenPayload->UserToken;
                $creds->Mode = $defaultMode;
            }

            $userCredentials = $message->FindPayloadAs("Onescan.LoginCredentials");

            $this->sessionState = $message->FindPayloadAs("partnerNamespace.sessionID");
            if ($userCredentials != null && $this->sessionState != null)
            {
                $creds->Username = $userCredentials->Username;
                $creds->Password = $userCredentials->Password;
                $creds->Mode = LoginMode::UsernamePassword;

                if ($userCredentials->Profiles != null)
                {
                    $creds->Mode = LoginMode::Register;
                    $creds->Profiles = $userCredentials->Profiles;
                    
                    $creds->Firstname = $userCredentials->FirstName;
                    $creds->Lastname = $userCredentials->LastName;
                    $creds->Email = $userCredentials->Email;
                }
            }

            $this->requestMessage = $message;
            return $creds;
        }

        private function SetNextAction($responseMessage,$nextAction)
        {
            switch ($nextAction)
            {
                case LoginNextAction::LoginSuccessful:
                    $responseMessage->MessageType = "ProcessComplete";
                    break;
                case LoginNextAction::FallbackToUsernamePassword:
                    $responseMessage->MessageType = "StartLogin"; // as before
                    break;
                case LoginNextAction::PromptForRetry:
                    $responseMessage->MessageType = "RetryLogin";
                    break;
                case LoginNextAction::RequestUserDetails:
                    $responseMessage->MessageType = "RegisterUser";
                    break;
                case LoginNextAction::SignalFailure:
                    $responseMessage->MessageType = "LoginProblem";
                    break;
            }
        }

        public function EncodeOutcome($loginResult,$settings)
        {
            if ($loginResult->NextAction == LoginNextAction::Unknown)
                throw new Exception("Next action is not set");

            $responseMessage = Onescan::BuildResponseMessage($this->requestMessage);
            $this->UpdateSessionState($responseMessage,$settings,$this->sessionState);

            $this->SetNextAction($responseMessage,$loginResult->NextAction);
            
            $responseMessage->Success = $loginResult->Success;

            if ($loginResult->EndProcess)
            {
                //  finally setup the response payload
                $responsePayload = $responseMessage->AddNewPayloadItem();
                $responsePayload->PayloadName = ProcessOutcome::Name;
                $outcome = new ProcessOutcome();
                $outcome->RedirectURL = $loginResult->BrowserRedirectURL;
                $outcome->UserMessage = $loginResult->DeviceMessage;
                $outcome->RedirectAsFormPost = $loginResult->RedirectAsFormPost;
                $outcome->FormPostData = $loginResult->FormPostData;
                $responsePayload->JsonPayload = json_encode($outcome);
            }
            return $responseMessage;
        }

        public function SessionState()
        {
            return $this->sessionState;
        }
	}
?>
