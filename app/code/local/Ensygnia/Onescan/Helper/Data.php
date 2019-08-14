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
	class Ensygnia_Onescan_Helper_Data extends Mage_Core_Helper_Abstract {
		const XML_PATH_ONESCAN_GENERAL_ENABLED						= 'onescantab/general/enabled';
		const XML_PATH_ONESCAN_GENERAL_SERVER_URL					= 'onescantab/general/onescan_serverurl';
		const XML_PATH_ONESCAN_GENERAL_ACCOUNT						= 'onescantab/general/onescan_account';
		const XML_PATH_ONESCAN_GENERAL_SECRET						= 'onescantab/general/onescan_secret';
		const XML_PATH_DESIGN_HEADER_LOGO_SRC						= 'design/header/logo_src';
		const XML_PATH_ONESCAN_GENERAL_LOGIN_SUCCESS_MESSAGE		= 'onescantab/general/onescan_login-success-message';
		const XML_PATH_ONESCAN_GENERAL_EMAIL_NOT_CONFIRMED_MESSAGE	= 'onescantab/general/onescan_email-not-confirmed-message';
		const XML_PATH_ONESCAN_GENERAL_SKIP_CONFIRMATION			= 'onescantab/general/onescan_skip-confirmation';
		const XML_PATH_ONESCAN_GENERAL_REGISTER_SUCCESS_MESSAGE		= 'onescantab/general/onescan_register-success-message';
		const XML_PATH_ONESCAN_GENERAL_EMAIL_EXISTS_MESSAGE			= 'onescantab/general/onescan_email-exists-message';
		const XML_PATH_ONESCAN_GENERAL_PRODUCT_MOVE_MAIN			= 'onescantab/general/product_move_main';
		const XML_PATH_ONESCAN_GENERAL_SHOW_ON_CART_PAGE			= 'onescantab/general/show_on_cart_page';

		protected function _getSetting($path) {
			return Mage::getStoreConfig($path);
		}

		public function isEnabled() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_ENABLED);
		}

		public function getServerUrl() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_SERVER_URL);
		}

		public function getAccount() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_ACCOUNT);
		}

		public function getSecret() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_SECRET);
		}

		public function getLogoSrc() {
			return $this->_getSetting(self::XML_PATH_DESIGN_HEADER_LOGO_SRC);
		}

		public function getLoginSuccessMessage() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_LOGIN_SUCCESS_MESSAGE);
		}

		public function getEmailNotConfirmedMessage() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_EMAIL_NOT_CONFIRMED_MESSAGE);
		}

		public function getSkipConfirmation() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_SKIP_CONFIRMATION);
		}

		public function getRegisterSuccessMessage() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_REGISTER_SUCCESS_MESSAGE);
		}

		public function getEmailExistsMessage() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_EMAIL_EXISTS_MESSAGE);
		}

		public function getProductMoveMain() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_PRODUCT_MOVE_MAIN);
		}

		public function getShowOnCartPage() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_SHOW_ON_CART_PAGE);
		}

		public function getShowOnMiniCart() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_SHOW_ON_MINI_CART);
		}

		public function getShowNowAccepting() {
			return $this->_getSetting(self::XML_PATH_ONESCAN_GENERAL_SHOW_NOW_ACCEPTING);
		}

		public function getLoginCallbackUrl() {
			//str_replace('/OnescanLoginConfig.php','',str_replace('/index.php','',Mage::helper('core/url')->getHomeUrl())) . 'app/code/local/Ensygnia/Onescan/controllers/OnescanLoginCallback.php';

			return Mage::getUrl('onescan/callback/login');
		}

		public function getRegistrationCallbackUrl() {
			//str_replace('/OnescanRegistrationConfig.php','', str_replace('/index.php','',Mage::helper('core/url')->getHomeUrl())) . 'app/code/local/Ensygnia/Onescan/controllers/OnescanRegistrationCallback.php'

			return Mage::getUrl('onescan/callback/registration');
		}

		public function getPurchaseCallbackUrl() {
			//str_replace('/OnescanPurchaseConfig.php','',str_replace('/index.php','',Mage::helper('core/url')->getHomeUrl())) . 'app/code/local/Ensygnia/Onescan/controllers/OnescanPurchaseCallback.php'

			return Mage::getUrl('onescan/callback/purchase');
		}

		public function getLogoSrcUrl() {
			//str_replace('/index.php','',Mage::helper('core/url')->getHomeUrl()) . $this->getHelper()->getLogoSrc();

			return Mage::getUrl($this->getLogoSrc());
		}

		public function getLoginSuccessUrl() {
			//str_replace('OnescanLoginCallback.php','index.php',Mage::helper('core/url')->getHomeUrl()) .'onescan/index/loginsuccess';

			return Mage::getUrl('onescan/index/loginsuccess');
		}

		public function getLoginFailureUrl() {
			//str_replace('OnescanLoginCallback.php','index.php',Mage::helper('core/url')->getHomeUrl()) . 'onescan/index/loginfailure';

			return Mage::getUrl('onescan/index/loginfailure');
		}

		public function getEmailConfirmationUrl($username) {
			//str_replace('OnescanRegistrationCallback.php','index.php', Mage::helper('customer')->getEmailConfirmationUrl($credentials->Username));

			return Mage::helper('customer')->getEmailConfirmationUrl($username);
		}
		
		public function getConfirmationMessage($username) {
			return $this->getHelper()->__('Account confirmation is required. Please, check your email for the confirmation link. To resend the confirmation email please <a href="%s">click here</a>.', $this->getEmailConfirmationUrl($username));
		}

		public function getSessionPollUrl() {
			return $this->getServerUrl() . 'CheckOnescanSessionStatus';
		}

		public function getSessionFromBasketUrl() {
			//Mage::helper('core/url')->getHomeUrl() . 'onescan/index/createsessionfrombasket';
			
			return Mage::getUrl('onescan/index/createsessionfrombasket');
		}

		public function getLoginSessionUrl() {
			//Mage::helper('core/url')->getHomeUrl() . 'onescan/index/createloginsession";

			return Mage::getUrl('onescan/index/createloginsession');
		}

		public function getRegistrationSessionUrl() {
			//Mage::helper('core/url')->getHomeUrl() . 'onescan/index/createregistrationsession";

			return Mage::getUrl('onescan/index/createregistrationsession');
		}
	}
?>