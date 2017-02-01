<?php
/**
 * @package elite-dangerous-status
 */
/*
Plugin Name: Elite Dangerous - Server status
Plugin URI:
Description: Show server status for Elite: Dangerous
Version: 1.0
Author: Biobob
Author URI:
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

function get_ED_status() {

  $s = file_get_contents('http://hosting.zaonce.net/launcher-status/status.json');

  $obj = json_decode($s);

  if(empty($obj) || !isset($obj->text)) return;

  return '<span class="ed-status-'.$obj->status.'">'.$obj->text.'</span>';

}
