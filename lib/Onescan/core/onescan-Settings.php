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
	class OnescanSettings
	{
		/// <summary>
		/// The partner's OnescanAccountKey supplied by Ensygnia
		/// </summary>
		public $OnescanAccountKey;
		
		/// <summary>
		/// The partner's OnescanSecret supplied by Ensygnia
		/// </summary>
		public $OnescanSecret;
	
		/// <summary>
		/// The partner's callback endpoint used by Onescan to communicate during the process
		/// </summary>
		public $OnescanCallbackURL;

		/// <summary>
		/// The Ensygnia supplied Onescan server endpoint
		/// </summary>
		public $OnescanServerURL;

		/// <summary>
		/// Get or set the namespace for all partner payloads.
		/// </summary>
		public $PartnerNamespace = 'partner';

        public $SecurityCookieLifetimeSeconds = 300;

        public $CookieIsSecure = true;
	
	}
?>
