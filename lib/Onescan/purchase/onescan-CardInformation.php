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
	class CardInformation {

		/// <summary>
		/// Is this a debit card rather than a credit card? 
		/// </summary>
		public $CardType;

		/// <summary>
		/// Is this a corporate card? 
		/// </summary>
		public $Corporate;

		/// <summary>
		/// The type of the card (e.g. AMEX, VISA). Note that we do not use this currently in the
		/// platform and all we do is pass it through from the SagePay getCardDetails call to the 
		/// partner. It could be an enum but since we are just serializing, it will go across the wire
		/// as text anyway. By just keeping it as text, if SagePay add new types then we will just
		/// automatically support them.
		/// </summary>
		public $PaymentSystemCode;

		/// <summary>
		/// The 2 character ISO code for the country the card was issued in.
		/// </summary>
		public $CountryCode;

		/// <summary>
		/// The name of the payment system ( e.g. MasterCard)
		/// </summary>
		public $PaymentSystemName;

		/// <summary>
		/// The issuer of the card.
		/// </summary>
		public $Issuer;

	}
?>