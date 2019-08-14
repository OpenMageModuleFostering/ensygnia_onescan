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
	/// Response from the partner containing the set of delivery options and their costs given
	/// the address that was supplied, and any surcharges for the payment method.
	/// </summary>
	class AdditionalChargesPayload {
		const Name = "Onescan.AdditionalCharges";

		/// <summary>
		/// The list of delivery options that are available for this order. If the
		/// address has not yet been supplied then this can be an empty list.
		/// </summary>
		public $DeliveryOptions=array();

		/// <summary>
		/// Flag indicating that no delivery options could be returned for the specified address.
		/// In the case of orders that do not support/require delivery, this should be set to 
		/// false.
		/// </summary>
		public $AddressNotSupported;

		/// <summary>
		/// A string just describing why the address is not supported. For those purchases that do not
		/// require a delivery address, simply leave blank.
		/// </summary>
		public $AddressNotSupportedReason;

		/// <summary>
		/// Information about any charges.
		/// </summary>
		public $PaymentMethodCharge;
	}
?>