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
	abstract class Ensygnia_Onescan_Model_Callback_Abstract {
		protected $_config;

		abstract public function getConfigReference();
		
		public function handle() {
			return $this;
		}

		public function getConfig() {
			if ($this->_config == null) {
				$this->_config = Mage::getModel($this->getConfigReference());
			}

			return $this->_config;
		}

		public function getHelper() {
			return Mage::helper('onescan');
		}
	}