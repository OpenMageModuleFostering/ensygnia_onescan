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
class HTTPClient 
{
    public static $UseAsync = TRUE;
    public static $HTTPTimeout = 10000;

    public function HTTPClient()
    {
        $this->RequestTimeout = HTTPClient::$HTTPTimeout;
        $this->WasAborted = FALSE;
    }
 
    private function MakeRequest($account_key, $hmac, $post, $url, $data, $UseGZip, $contentType = "application/json")
    {
        
        $uri = curl_init($url);
        if ($UseGZip)
            curl_setopt($uri,CURLOPT_ENCODING,"gzip");

        $header = array();
        if ($contentType != NULL)
            $header[] = "content-type: " . $contentType;

        $header[] = "x-onescan-account: " . $account_key;
        $header[] = "x-onescan-signature: " . $hmac;

        curl_setopt($uri, CURLOPT_HTTPHEADER, $header);
        curl_setopt($uri,CURLOPT_POST,$post);

        if ($data != NULL)
        {
            curl_setopt($uri, CURLOPT_POSTFIELDS, $data);
        }
 
        try {
            $response = curl_exec($uri);
 
            if (curl_errno($uri) != 200) {
                throw new Exception("HTTP Error " . curl_errno($uri) . '. ' . curl_error($uri));
            }
        }
        catch (Exception $e) {
            curl_close($uri);
            throw $e;
        }
 
        return $response;
    }

    public function Post($account_key, $hmac, $url, $data, $UseGZip = false)
    {
        return $this->MakeRequest($account_key, $hmac, TRUE, $url, $data, $UseGZip);
    }

    public function Get($account_key, $hmac, $url, $UseGZip)
    {
        return $this->MakeRequest($account_key, $hmac, FALSE, $url, "", $UseGZip);
    }


    public $RequestTimeout;
    public $WasAborted;
}

?>
