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
	/// Contains calculated information about the costs of the entire transaction, including
	/// P+P, appropriate taxes, payment method surcharges and so on.
	/// </summary>
	class PurchaseCharges {

		/// <summary>
		/// The total amount of postage.
		/// </summary>
		public $PostageAmount;

		/// <summary>
		/// The amount of the item(s) costs, excluding taxes
		/// </summary>
		public $BasketAmount;

		/// <summary>
		/// The amount of tax added to the products in the basket.
		/// </summary>
		public $BasketTax;

		/// <summary>
		/// The total payment amount taken. 
		/// </summary>
		public $PaymentAmount;

		/// <summary>
		/// The currency that the payment is being charged in. All amounts in
		/// this class will be in this currency.
		/// </summary>
		public $Currency;

		/// <summary>
		/// Any extra charges for the currently selected payment method
		/// </summary>
		public $Surcharge;
	}
?>