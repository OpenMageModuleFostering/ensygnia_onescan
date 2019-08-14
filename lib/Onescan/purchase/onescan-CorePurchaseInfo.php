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
	class CorePurchaseInfo {

		const Name = "Onescan.PurchasePayload";

		public $MerchantName;

		/// <summary>
		/// A merchant defined transaction ID for their own internal use.
		/// </summary>
		public $MerchantTransactionID;

		/// <summary>
		/// The subject of the purchase.
		/// </summary>
		public $PurchaseDescription;

		/// <summary>
		/// The amount of the basic product
		/// </summary>
		public $ProductAmount;

		/// <summary>
		/// The amount of the tax added to the product.
		/// </summary>
		public $Tax;

		/// <summary>
		/// The total payment amount to be taken.
		/// </summary>
		public $PaymentAmount;

		/// <summary>
		/// The currency that the payment is being charged in.
		/// </summary>
		public $Currency;

		/// <summary>
		/// Indicates whether the order is a physical order that requires
		/// a delivery address in addition to the billing address.
		/// </summary>
		public $RequiresDeliveryAddress;

		/// <summary>
		/// The set of payment optins that the partner requires.
		/// </summary>
		public $Requires;
	}
?>