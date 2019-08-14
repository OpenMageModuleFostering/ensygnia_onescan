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
	$purchasedir=dirname(__FILE__);
	$coredir=str_replace("purchase","core",$purchasedir);
	
	require_once($coredir . "/onescan-Onescan.php");
	require_once($coredir . "/onescan-GUID.php");
    require_once($coredir . "/onescan-Settings.php");
    require_once($coredir . "/onescan-Process.php");
	
	//Include all php files in the onescan/purchase directory except this one
	$filenames=scandir($purchasedir);
	foreach($filenames as $filename) {
		if(substr(strtolower($filename),-3)=="php" && $filename!=basename(__FILE__)) {
			require_once($purchasedir . "/" . $filename);
		}
	}
?>
