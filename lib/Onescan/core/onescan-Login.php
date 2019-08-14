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
    abstract class LoginMode
    {
        const UsernamePassword=0;
        const UserToken=1;
        const TokenOrCredentials=2;
        const Register=3;
    }

    class LoginDevicePayload
    {

        /// <summary>
        /// Used by the device as a key for vaulted passwords.
        /// </summary>
        public $SiteIdentifier;

        public $SiteLogoUrl;

        /// <summary>
        /// Tells the device what the friendly name of the site is.
        /// </summary>
        public $FriendlyName;

        /// <summary>
        /// Set by the partner to indicate what type of login
        /// processes they support.
        /// </summary>
        public $LoginMode;

        /// <summary>
        /// An array of names of profiles for the data that the partner
        /// wants to access to (e.g. "Basic" for name/email).
        /// </summary>
        public $Profiles=array();
    }

    class LoginDeviceSubmitPayload
    {
        /// <summary>
        /// The list of profiles that have actually been provided. The user may have rejected
        /// some of the ones requested.
        /// </summary>
        public $Profiles=array();

        public $Username;
        public $Password;
        public $DefaultPassword;

        // -- Profile data -- 
        // this is "basic", we will populate the properties
        public $FirstName;
        public $LastName;
        public $Email;
    }
?>