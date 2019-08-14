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
	/// This is a payload that we can send to the partner whenever we need them to give 
	/// some information about possible extra charges that depend upon the current 
	/// delivery and payment information provided by the user. 
	/// </summary>
	/// <remarks>
	/// If we choose to use a default delivery address and payment method then this payload
	/// could be provided as part of the initial "start purchase" method so that the 
	/// partner can provide this information straight away. It can also be passed in as part
	/// of an explicit call when the user changes one of those options.
	/// </remarks>
	class PurchaseContextPayload {

		/// <summary>
		///  The name of the payload.
		/// </summary>
		const Name = "Onescan.PurchaseContext";

		/// <summary>
		/// The delivery address that the user has chosen, if any.
		/// </summary>
		public $DeliveryAddress;

		/// <summary>
		/// The type of payment method that the user has selected, if any.
		/// </summary>
		public $PaymentMethod;
	}
?>