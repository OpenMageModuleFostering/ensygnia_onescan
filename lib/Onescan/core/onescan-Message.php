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

	// -------- Pre-process version ----------

define('CONST_SENARIO_LOGIN', 1);
define('CONST_SENARIO_PAYMENT', 2);
define('CONST_SENARIO_REGISTRATION', 3);
define('CONST_SENARIO_ONESCANLOGIN', 4);

class OneScanMetadata 
{
	public $EndpointURL;
    public $VisualCodeTTL;
}

class PayloadItem {

	/// <summary>
	/// The name of this payload item. The names of payloads are entirely defined
	/// by the process that uses the payloads.
	/// </summary>
	public $PayloadName;

	/// <summary>
	/// The actualy payload, stored as P7 encoded data.
	/// </summary>
	public $JsonPayload;

	/// <summary>
	/// The time stamp at which this payload item was first added to the
	/// durable version of the process message that contains this payload item.
	/// </summary>
	public $PersistTimeStamp;

	// -- Pre process version.
	public $payloadType;
	public $Context;
	public $OriginatingParty;
	public $DestinationParty;

	protected function Duplicate() {
		$payloadItem = new PayloadItem();
		$payloadItem->PayloadName = $PayloadName;
		$payloadItem->JsonPayload = $JsonPayload;
		$payloadItem->PersistTimeStamp = $PersistTimeStamp;
		$payloadItem->payloadType = $payloadType;
		$payloadItem->Context = $Context;
		$payloadItem->OriginatingParty = $OriginatingParty;
		$payloadItem->DestinationParty = $DestinationParty;
	}
}

class OneScanMessage {
	/// <summary>
	/// Get or set the Payloads that are part of this message.
	/// </summary>		
	public $Payloads = array();

	/// <summary>
	/// The version of this message.
	/// </summary>
	public $Version;

	/// <summary>
	/// The type of process that this message supports (e.g. payments). Process
	/// types are registered late so the full set of possible processes
	/// cannot be defined here. We use a string ID for the type so that it is
	/// a little easier to debug!
	/// </summary>
	public $ProcessType;
    
	/// <summary>
	/// The type of message. This essentially represents one "step" in the process
	/// defined by ProcessType.
	/// </summary>
	public $MessageType;

	/// <summary>
	/// The ID of the process that this message is part of. Logically a single
	/// message is created for a process and we continually enhance that message
	/// with additional payloads. 
	/// </summary>
	public $ProcessId;

    public function CopyFrom($message)
    {
        if (isset($message->Payloads)) {
        	$this->Payloads = $message->Payloads;
		} else {
			$this->Payloads = null;
		}  
        if (isset($message->Version)) {
        	$this->Version = $message->Version;
		} else {
			$this->Version = null;
		}
		if (isset($message->CertificateStore)) {
        	$this->CertificateStore = $message->CertificateStore;
		} else {
			$this->CertificateStore = null;
		}
        if (isset($message->ProcessType)) {
        	$this->ProcessType = $message->ProcessType;
		} else {
			$this->ProcessType = null;
		}
        if (isset($message->MessageType)) {
        	$this->MessageType = $message->MessageType;
		} else {
			$this->MessageType = null;
		}
        if (isset($message->ProcessId)) {
        	$this->ProcessId = $message->ProcessId;
		} else {
			$this->ProcessId = null;
		}
        if (isset($message->scenarioID)) {
        	$this->scenarioID = $message->scenarioID;
		} else {
			$this->scenarioID = null;
		}
    }
	
	public $CertificateStore;
	
	/// <summary>
	/// Fetches a particular payload item from the set of payloads, if that item
	/// actually exists. Returns null otherwise.
	/// </summary>
	/// <param name="payloadName"></param>
	/// <returns></returns>
	public function GetPayloadItemByName($payloadName) 
    {
		foreach ($this->Payloads as $item) 
        {
            if (strcmp($item->PayloadName,$payloadName) == 0)
            {
                return $item;
            }
        }
        return NULL;
	}

	/// <summary>
	/// Decode the specified payload.
	/// </summary>
	/// <typeparam name="T"></typeparam>
	/// <param name="payloadName"></param>
	/// <returns></returns>
	public function FindPayloadAs( $payloadName ) {
    	$payload = $this->GetPayloadItemByName($payloadName);
	    if ($payload != NULL)
	        return json_decode($payload->JsonPayload);

	    return NULL;
	}

	/// <summary>
	/// Copy the payloads from this message to the target message, unless the
	/// target already has a payload with that name.
	/// </summary>
	/// <param name="message"></param>
	public function MergePayloads( $message ) {
		foreach ($Payloads as $item => $value) {
			if ($message[$item->PayloadName] == null) {
				$message->AddPayloadItem($item->Duplicate());
			}
		}
	}

	public function AddNewPayloadItem() {
		$newPayloadItem = new PayloadItem();
		$newPayloadItem->OriginatingParty = "";
		$newPayloadItem->DestinationParty = "";
		$this->Payloads[] = $newPayloadItem;
		return $newPayloadItem;
	}

	public function AssertPayloadExists() {
		if (($this->Payloads != NULL) && (count($this->Payloads) > 0))
			return TRUE;
		throw new Exception("No payload exists");
	}

	public function GetPayloadCount() {
	    if ($this->Payloads != NULL) 
        {
            return count($this->Payloads);
        }
        return 0;
	}

	public $scenarioID;

	public function UserTokenPayload()
	{
		return $this->FindPayloadAs(UserTokenPayload::Name);
	}

}

class OneScanResponseMessage extends OneScanMessage 
{

	function OneScanResponseMessage() {
		$Success = TRUE;
	}

	/// <summary>
	/// Did the request succeed?
	/// </summary>
	public $Success;
    
}

?>
