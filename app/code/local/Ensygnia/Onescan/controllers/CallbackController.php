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
	class Ensygnia_Onescan_CallbackController extends Mage_Core_Controller_Front_Action {
		public function indexAction() {
			echo 'No route';
		}

		public function loginAction() {
			$model = Mage::getModel('onescan/callback_login');
			
			$model->handle();
		}

		public function registrationAction() {
			$model = Mage::getModel('onescan/callback_registration');
			
			$model->handle();	
		}

		public function purchaseAction() {
			$model = Mage::getModel('onescan/callback_purchase');

			$model->handle();
		}
	}