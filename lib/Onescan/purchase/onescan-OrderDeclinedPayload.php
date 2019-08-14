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
    class OrderDeclinedPayload {
		/* Some standard error codes that the partner can use to indicate
		 * particular problems that the device may be able to fix
		 */
		public static $Name="Onescan.OrderDeclined";
	
		/// <summary>
		/// A general failure.
		/// </summary>
		const GeneralFailure = 0;
	
		/// <summary>
		/// The delivery address was not supplied.
		/// </summary>
		const NoDeliveryAddress = 1;
	
		/// <summary>
		/// The address cannot be delivered to
		/// </summary>
		const AddressNotCovered = 2;
	
		/// <summary>
		/// Insufficient stock levels.
		/// </summary>
		const OutOfStock = 3;
	
		/// <summary>
		/// The address was not valid.
		/// </summary>
		const InvalidAddress = 4;
	
		/// <summary>
		/// For things like car bookings or tickets we might reserve a ticket
		/// for a short period of time whilst the user enters the details for the 
		/// purchase. If we didn't complete in time then it may be that the item
		/// has been released. This means the order didn't really "fail" as such
		/// but that the user still needs to start again.
		/// </summary>
		const ForceRestart = 5;
	
		/// <summary>
		/// Set up which of the errors are considered critical failures that mean the
		/// process must be cancelled.
		/// </summary>
		private $criticalFailures = array(OrderDeclinedPayload::GeneralFailure,
											OrderDeclinedPayload::OutOfStock,
											OrderDeclinedPayload::InvalidAddress);
	
		/// <summary>
		/// Get or set whether the error from the partner is critical (i.e. cannot
		/// be fixed) or not. If not then the device can re-submit the payload with
		/// appropriate corrections.
		/// </summary>
		public function IsCritical() {
			return in_array($this->ErrorCode,$this->criticalFailures);
		}
	
		/// <summary>
		/// An error code indicating what type of failure happened - see the
		/// above error codes.
		/// </summary>
		public $ErrorCode;
	
		/// <summary>
		/// Get or set a human readable version of the error so that the user has some
		/// idea what happened.
		/// </summary>
		public $ReasonDescription;
}

?>