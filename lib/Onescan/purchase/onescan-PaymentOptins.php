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
	/// Provides a set of features that the partner can opt in
	/// to.
	/// </summary>
	class PaymentOptIns {

		/// <summary>
		/// Does the partner want to be called to supply delivery options?
		/// </summary>
		public $DeliveryOptions;

		/// <summary>
		/// Does the partner want to be called to supply surcharge information
		/// based on the selected payment methods?
		/// </summary>
		public $Surcharges;

	}
?>