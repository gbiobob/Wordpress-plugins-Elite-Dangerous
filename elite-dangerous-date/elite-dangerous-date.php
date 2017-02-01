<?php
/**
 * @package elite-dangerous-date
 */
/*
Plugin Name: Elite Dangerous - Date convert
Plugin URI:
Description: Show date for Elite: Dangerous
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

function eliteDangerousDate( $dateformatstring, $unixtimestamp = false, $gmt = false ) {

  if ( is_admin() ) {
    return $dateformatstring;
  }

  global $wp_locale;
  $date = new DateTime();
  date_timestamp_set($date, $gmt);

  $dateIRL = $date->format('Y');
  $dateEd = intval($date->format('Y'))+1286;

  return str_replace($dateIRL, $dateEd, $dateformatstring);

}
add_action( 'date_i18n', 'eliteDangerousDate', 10, 3 );