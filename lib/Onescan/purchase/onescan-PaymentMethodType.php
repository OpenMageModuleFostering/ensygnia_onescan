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
	/// The various types of payment method
	/// </summary>
	abstract class PaymentMethodType {

		const CreditCard = 0;
		const PayPal = 1;
		const DirectDebit = 2;

		/// <summary>
		/// Essentially a no-operation payment method that allows users to try out a payment scan on
		/// a test site. If they have provided an email then it simulates a full payment process
		/// but obviously doesn't actually take any actual payments.
		/// </summary>
		const OnescanPlay = 3;
	}
?>