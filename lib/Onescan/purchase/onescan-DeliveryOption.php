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
	class DeliveryOption {

		/// <summary>
		/// A unique ID set by the partner so when we hand the record back as the selected
		/// payment option they know what to pick.
		/// </summary>
		public $Code;

		/// <summary>
		/// A short name for the delivery option (e.g. 'free', 'first class').
		/// </summary>
		public $Label;

		/// <summary>
		/// If required, a longer description for the payment method. We might use the
		/// Label in a drop down, then show the description (e.g. 'First class delivery using
		/// Royal Mail recorded delivery) when we show the details of the delivery method.
		/// </summary>
		public $Description;

		/// <summary>
		/// An explanation of the terms and conditions. For example, if the type is courier
		/// it might be some text saying it will be any time before noon or whatever.
		/// </summary>
		public $Conditions;

		/// <summary>
		/// True if we should choose this delivery option as the initial default.
		/// </summary>
		public $IsDefault;

		/// <summary>
		/// The charge for this delivery option.
		/// </summary>
		public $Charge;

	}
?>