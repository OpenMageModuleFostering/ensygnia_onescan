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
require_once("onescan-HMAC.php");
require_once("onescan-Message.php");

class Onescan
{
    private static function VerifyContentSignature($OnescanMessageAsJson, $settings, $passedHmac)
    {
		$calculatedHmac = Onescan::CalculateHMAC($settings, $OnescanMessageAsJson);
		return ($calculatedHmac == $passedHmac);
    }

    public static function safe_getallheaders() 
    { 
        $headers = ''; 
        foreach ($_SERVER as $name => $value) 
        { 
            if (strtoupper(substr($name, 0, 5)) == 'HTTP_') 
            { 
                $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value; 
            } 
        } 
        return $headers;
    } 
 
	public static function ReadMessage($settings)
    {
        $message = NULL;

        $jsonContent = file_get_contents("php://input");
        $headers=Onescan::safe_getallheaders();
        $hmacOfRequest=$headers["x-onescan-signature"];
        if (Onescan::VerifyContentSignature($jsonContent, $settings, $hmacOfRequest))
        {
            $message = new OnescanMessage();
            $message->CopyFrom( json_decode($jsonContent) );
        }
        return $message;
    }

    public static function SendMessage($message,$settings)
    {
		$jsonResponse=json_encode($message);
		$hmacOfResponse = Onescan::CalculateHMAC($settings, $jsonResponse);
		header("x-onescan-signature: " . $hmacOfResponse);
        echo $jsonResponse;
    }

    public static function BuildResponseMessage($requestMessage)
    {
        $responseMessage = new OneScanResponseMessage();
        $responseMessage->ProcessId = $requestMessage->ProcessId;
        $responseMessage->ProcessType = $requestMessage->ProcessType;
        $responseMessage->MessageType = $requestMessage->MessageType;
        return $responseMessage;
    }

    public static function CalculateHMAC($settings, $content)
    {
        return HMAC::Encode($content, $settings->OnescanSecret);
    }
}

?>
