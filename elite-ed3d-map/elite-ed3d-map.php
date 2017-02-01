<?php
/**
 * @package elite-dangerous-status
 */
/*
Plugin Name: Elite Dangerous - ED3D galactic Map
Plugin URI:
Description: Show server status for Elite: Dangerous
Version: 0.1
Author: Biobob
Author URI: https://github.com/gbiobob/ED3D-Galaxy-Map
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

/* Install and default settings */

add_action( 'activate_' . WPCF7_PLUGIN_BASENAME, 'ed3dmap_install' );

function ed3dmap_install() {

  global $wpdb;

  $sql =
    " CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."ed3d_systems_posts` ( ".
    "   `ID_SYSTEM` int(11) NOT NULL, ".
    "   `ID_POST` int(11) NOT NULL, ".
    "   PRIMARY KEY (`ID_SYSTEM`,`ID_POST`) ".
    " ); ";
  $wpdb->query($sql);

  $sql =
    " CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."ed3d_systems` ( ".
    "   `ID_SYSTEM` int(11) NOT NULL AUTO_INCREMENT, ".
    "   `NAME` varchar(64) NOT NULL, ".
    "   `X` decimal(8,3) DEFAULT NULL, ".
    "   `Y` decimal(8,3) DEFAULT NULL, ".
    "   `Z` decimal(8,3) DEFAULT NULL, ".
    "   `EDSM_ID` int(11) DEFAULT NULL, ".
    "   `EDSM_ERROR` tinyint(1) DEFAULT NULL, ".
    "   PRIMARY KEY (`ID_SYSTEM`), ".
    "   UNIQUE KEY `NAME` (`NAME`) ".
    " ); ";
  $wpdb->query($sql);

}

//[foobar]

function system_func( $atts ) {

  if(isset($atts['name']))
    return '<strong class="system_name">'.$atts['name'].'</strong>';

}
add_shortcode( 'system',  'system_func' );


function ed3dmap_func( $atts ) {

  return get_ed3d_map(true);

}
add_shortcode( 'ed3dmap',  'ed3dmap_func' );


class Ed3dMap {

  private $pathJson = null;

  private $isDebug = false;

  private $rootCategory = 28; // ADD AN OPTION TO EDIT THAT

  function __construct() {

    $this->pathJson = plugin_dir_path( __FILE__ ).'cache/systems.json';

  }


  public function getEd3dMap () {

    ed3dmap_install();

    $this->refreshPostCoords();
    $this->getCategories();

  }

  //----------------------------------------------------------------------------
  /**
   * Search for systems to attach on posts
   *
   * @param Array $joins An array with posts ID for each systems
   * @return
   */
  //----------------------------------------------------------------------------

  private function refreshPostCoords() {


    global $wpdb;

    $results = $wpdb->get_results(
      " SELECT ID, post_content ".
      " FROM $wpdb->posts ".
      " WHERE post_status = 'publish' ".
      " AND post_content LIKE '%[system %' "
      , OBJECT
    );

    $matchList = array();

    foreach ($results as $row) {

      preg_match_all('/' . get_shortcode_regex() . '/s', $row->post_content, $codes);

      if(!empty($codes)) {

        foreach ($codes[2] as $key => $value) {
          if($value == 'system') {
            $attr = shortcode_parse_atts($codes[3][$key]);
            if(isset($attr['name'])) {
              $system = $attr['name'];

              if(!isset($matchList[$system]))
                $matchList[$system] = array();
              $matchList[$system][] = $row->ID;
            }
          }
        }

      }

    }

    if(!empty($matchList)) {
      $this->insertPostJoinSystem($matchList);
    }

  }

  //----------------------------------------------------------------------------
  /**
   * Add join between posts and systems
   *
   * @param Array $joins An array with posts ID for each systems
   * @return
   */
  //----------------------------------------------------------------------------

  private function insertPostJoinSystem ($joins) {

    global $wpdb;

    $addQuery = '';
    $cleanId = array();

    foreach ($joins as $system => $idPosts) {

      $idSystem = $this->addSystem($system);
      if(empty($idSystem)) continue;

      foreach ($idPosts as $id) {
        $addQuery .= (!empty($addQuery)) ? ',' : '';
        $addQuery .= "($idSystem, $id)";

        if(!in_array($id, $cleanId)) $cleanId[] = $id;
      }

    }

    if(empty($addQuery)) return;

    if(!empty($cleanId)) {
      $implode = implode(',', $cleanId);
      $wpdb->query(" DELETE FROM `".$wpdb->prefix."ed3d_systems_posts` WHERE `ID_SYSTEM` IN ($implode) ");
    }

    $sql =
      " INSERT IGNORE INTO `".$wpdb->prefix."ed3d_systems_posts` ".
      " (`ID_SYSTEM`, `ID_POST`) VALUES ".
      $addQuery;

    $wpdb->query($sql);

  }

  //----------------------------------------------------------------------------
  /**
   * Add a system into database and return the system ID
   *
   * @param String $name The system namz
   * @return Int
   */
  //----------------------------------------------------------------------------

  private function addSystem($name) {

    global $wpdb;

    $idSystem = false;

    //-- Try to insert a system

    $name = strip_tags($name);
    $nameSql = $wpdb->_real_escape($name);

    $sql =
      " INSERT IGNORE INTO `".$wpdb->prefix."ed3d_systems` ".
      " SET `NAME` = '$nameSql' ";

    $wpdb->query($sql);

    //-- If new system, check EDSM for coordinates
    if(!empty($wpdb->insert_id)) {

      $idSystem = $wpdb->insert_id;

      //$httpEdsm = 'https://www.edsm.net/api-v1/system?systemName='.urlencode($name).'&showId=1&showCoordinates=1';

      $httpEdsm = 'http://ed-board.net/tmp/test.php?name='.urlencode($name);


      // $edsm = $this->gs_getContentsCurl($httpEdsm);
      // $edsm = wp_remote_request($httpEdsm);
      //$request = new WP_Http;
  //  $request = new WP_Http;
  //  $edsm = $request->request( $httpEdsm );
      $edsm = $this->getSslPage( $httpEdsm );

      $data = json_decode($edsm);


      //-- If coordinates founded, save the values
      if(!empty($data) && isset($data->coords)) {

        $name = $wpdb->_real_escape($data->name);
        $x = floatval($data->coords->x);
        $y = floatval($data->coords->y);
        $z = floatval($data->coords->z);
        $idEdsm = intval($data->id);

        $wpdb->query(
          "UPDATE `".$wpdb->prefix."ed3d_systems` SET ".
          " `NAME`       = '$name', ".
          " `X`          = '$x', ".
          " `Y`          = '$y', ".
          " `Z`          = '$z', ".
          " `EDSM_ID`    = '$idEdsm', ".
          " `EDSM_ERROR` = '0' ".
          " WHERE `ID_SYSTEM` = $idSystem "
        );

      //-- Else set error
      } else {

        $wpdb->query(
          "UPDATE `".$wpdb->prefix."ed3d_systems` SET `EDSM_ERROR` = '1' WHERE `ID_SYSTEM` = $idSystem "
        );

      }

    //-- System already exist, get the ID
    } else {

      $results = $wpdb->get_results(
        " SELECT `ID_SYSTEM` ".
        " FROM ".$wpdb->prefix."ed3d_systems ".
        " WHERE `NAME` LIKE '$nameSql' ".
        "  AND  `EDSM_ERROR` = 0;"
        , OBJECT
      );

      if(empty($results)) return false;

      $idSystem = $results[0]->ID_SYSTEM;
    }

    return $idSystem;

  }

  //----------------------------------------------------------------------------
  /**
   *
   *
   * @param
   * @return
   */
  //----------------------------------------------------------------------------

  private function buildCategorieTree(&$Obj, &$categories, &$childList = array(), $parent, $parentName = null) {

    foreach ($categories as $cat) {
      if($cat->category_parent != $parent) continue;

      switch ($parent) {
        case $this->rootCategory:
          $catId = $cat->name;
          $Obj->$catId = new stdClass;
          $this->buildCategorieTree($Obj, $categories, $childList, $cat->term_id, $catId);
          break;

        default:
          $catId = $cat->term_id;
          $childList[] = $catId;
          $Obj->$parentName->$catId = new stdClass;
          $Obj->$parentName->$catId->name = $cat->name;
          $Obj->$parentName->$catId->color = $this->getColorCategory($catId);
          break;
      }
    }

  }

  private function getColorCategory($staticId) {


    static $pointer = -1;
    static $staticList = array();

    if($staticId !== null) {
      if(isset($staticList[$staticId])) return $staticList[$staticId];
    }

    $color = array(
      "2196f3",
      "f44336",
      "ff9800",
      "e91e63",
      "4caf50",
      "3f51b5",
      "00bcd4",
      "ffeb3b",
      "009688",
      "8bc34a",
      "673ab7",
      "cddc39",
      "9c27b0",
      "ffc107",
      "03a9f4",
      "ff5722",
      "795548",
      "9e9e9e",
      "607d8b"
    );

    $pointer++;
    if($pointer>=sizeof($color)) $pointer = 0;

    if($staticId !== null) {
      $staticList[$staticId] = $color[$pointer];
    }
    return $color[$pointer];

  }


  public function getSystems() {

    //$json = @file_get_contents($this->pathJson);

    if(!is_file($this->pathJson) || $this->isDebug) {

      global $wpdb;
      $ObjEd3d = new stdClass;
      $ObjEd3d->categories = new stdClass;
      $ObjEd3d->systems = array();

      //-- Init categories
      $childList = array();
      $categories = get_categories();
      $this->buildCategorieTree($ObjEd3d->categories, $categories, $childList, $this->rootCategory);
      foreach ($ObjEd3d->categories as $mainGroup => $objItems) {
        $props = get_object_vars($objItems);
        if(sizeof($props) == 0) unset($ObjEd3d->categories->$mainGroup);
      }

      //-- Get systems

      $results = $wpdb->get_results(
        " SELECT S.*, P.ID, P.guid, GROUP_CONCAT(T.term_taxonomy_id) AS TERM_LIST ".
        " FROM ".$wpdb->prefix."ed3d_systems AS S ".
        " JOIN ".$wpdb->prefix."ed3d_systems_posts AS SP ON S.ID_SYSTEM = SP.ID_SYSTEM ".
        " JOIN ".$wpdb->prefix."posts AS P ON P.ID = SP.ID_POST ".
        " JOIN ".$wpdb->prefix."term_relationships AS T ON T.object_id = P.ID ".
        " WHERE  P.post_status = 'publish' ".
        " GROUP BY P.ID "
        , OBJECT
      );

      if(!empty($results)) {

        foreach ($results as $s) {
          $Obj = new stdClass;
          $Obj->name = $s->NAME;
          $Obj->url  = get_post_permalink( $s->ID ); //$s->guid;
          $Obj->coords = new stdClass;
          $Obj->coords->x = intval($s->X);
          $Obj->coords->y = intval($s->Y);
          $Obj->coords->z = intval($s->Z);

          //-- Parse categories

          $catLst = explode(',', $s->TERM_LIST);
          if(!empty($catLst)) {
            foreach ($catLst as $idCat) {
              if(!in_array($idCat, $childList)) continue;
              if(!isset($Obj->cat)) $Obj->cat = array();
              $Obj->cat[] = intval($idCat);
            }
          }


          $ObjEd3d->systems[] = $Obj;
        }

      }



      $json = json_encode($ObjEd3d, JSON_PRETTY_PRINT);
      file_put_contents($this->pathJson, $json, FILE_TEXT );

    }


    //return $json;


    /*

  $html .= "\n".'  {';
  $html .= "\n".'    "name": "Solati",';
  $html .= "\n".'    "coords": {';
  $html .= "\n".'      "x": 66.53125,';
  $html .= "\n".'      "y": 29.1875,';
  $html .= "\n".'      "z": 34.6875';
  $html .= "\n".'    }';
  $html .= "\n".'  }';
*/


  }



  //----------------------------------------------------------------------------
  /**
   *
   *
   * @param
   * @return
   */
  //----------------------------------------------------------------------------

  private function getCategories() {

    global $wpdb;

  }



  static function getSslPage($url) {

    if(!function_exists('curl_init')) return @file_get_contents($url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, "http://osiris.identipack.fr/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 400);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }

}





function get_ed3d_map($withReturn = false) {

  $Map = new Ed3dMap();
  $Map->getEd3dMap();
  $Map->getSystems();

  $mapHeight = 500;


  $html = '';


  $html .= "\n".'<!-- jQuery -->';
  $html .= "\n".'<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>';
  $html .= "\n".'<!-- Three.js -->';
  $html .= "\n".'<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r75/three.min.js"></script>';

  $html .= "\n".'<!-- JSon inside a container -->';
  $html .= "\n".'<div id="edmap" style="width:100%;height:'.$mapHeight.'px;"></div>';

  $html .= "\n".'<!-- Launch ED3Dmap -->';
  $html .= "\n".'<link href="'.plugins_url( '/ed3d/css/styles.css', __FILE__ ).'" rel="stylesheet" type="text/css" />';
  $html .= "\n".'<script src="'.plugins_url( '/ed3d/js/ed3dmap.min.js', __FILE__ ).'"></script>';
  $html .= "\n".'<script type="text/javascript">';
  $html .= "\n".'  Ed3d.init({';
  $html .= "\n".'      basePath : \''.plugins_url( '/ed3d/', __FILE__ ).'\',';
  $html .= "\n".'      container   :   \'edmap\',';
  $html .= "\n".'      jsonPath : \''.plugins_url( '/cache/systems.json', __FILE__ ).'\',';
  $html .= "\n".'      withHudPanel : true,';
  $html .= "\n".'      withFullscreenToggle: true,';
  $html .= "\n".'      showGalaxyInfos: true,';
  $html .= "\n".'      showNameNear: true,';
  $html .= "\n".'      hudMultipleSelect: false,';
  $html .= "\n".'      cameraPos: [0,15000,-15000],';
  $html .= "\n".'      effectScaleSystem : [5500,30500]';
 // $html .= "\n".'      effectScaleSystem : [128,1500]';
  $html .= "\n".'  });';
  $html .= "\n".'</script>';

  if($withReturn) return $html;
  else echo($html);

}
