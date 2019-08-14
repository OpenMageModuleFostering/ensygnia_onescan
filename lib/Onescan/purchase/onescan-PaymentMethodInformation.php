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
	/// <summary>
	/// Contains information about the type of payment method that the user has 
	/// entered. The actual contents of this will vary by the payment field.
	/// </summary>
	class PaymentMethodInformation {

		/// <summary>
		/// The type of payment method that the user has selected, if any. This indicates if the
		/// payment is a card, PayPal etc.
		/// </summary>
		public $PaymentMethodType;

		/// <summary>
		/// If the payment type is CreditCard this will be present to provide further information about
		/// the card.
		/// </summary>
		public $CardInformation;
	}
?>