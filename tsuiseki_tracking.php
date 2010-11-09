<?php
/*
Plugin Name: Tsuiseki Tracking
Plugin URI: http://www.tsuiseki.com
Description: Allows tracking via the Tsuiseki tracking and data analysis system.
Version: 1.0
Author: Nexico Consulting GmbH
Author URI: http://www.nexico.net
*/
session_start(); // We need to start a session to be able to use cookies in wordpress.
// $Id: tsuiseki_tracking.php 6494 2010-04-19 11:37:22Z jens $
/**
 * @file
 * This file contains the tsuiseki wordpress plugin.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 */
/**
 * Here you can define your tsuiseki tracking key.
 * This define is intended to speed things up. If you leave it empty
 * each view/click will cost you an additional database query for looking
 * up the key (variable_get).
 * FIXME You must enter your tsuiseki tracking key here!
 */
define('TSUISEKI_TRACKER_KEY', NULL);

/**
 * The CSS class for outgoing links.
 * FIXME Please enter the css class of the links you want to track here.
 */
define('TSUISEKI_TRACKER_CSS_CLASS', NULL);

/**
 * A character sequence that is used to glue several values that should
 * be stored in a cookie together into one string.
 */
define('TSUISEKI_TRACKER_COOKIE_CONCAT_CHAR', ',,');

/**
 * The name of the cookie/session variable that should be used.
 */
define('TSUISEKI_TRACKER_COOKIE_NAME', 'TS_TRACKING_COOKIE');

/**
 * The time a value stored in a cookie should be valid in seconds.
 */
define('TSUISEKI_TRACKER_COOKIE_TIMEOUT', 900);

/**
 * The name of the cookie/session variable that will be used as view counter.
 */
define('TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME', 'TS_VIEWS');

/**
 * The algorithm used for hash_hmac.
 */
define('TSUISEKI_TRACKER_HMAC_ALGORITHM', 'sha256');

/**
 * The secret key we use to proofe the integrity of our cookies.
 * FIXME You must change this and it should not be too short!
 */
define('TSUISEKI_TRACKER_HMAC_KEY', 'JBOab!,`t?\:>f&R{A\P2gp!+W|s-U66]&t/[{~W}[!#>K92f/N@7aQfvcR!>:Cn');

/**
 * Initializes the options for the settings page.
 */
function tsuiseki_tracking_admin_init() {
  register_setting('tsuiseki-tracking-settings', 'tsuiseki_tracking_key');
  register_setting('tsuiseki-tracking-settings', 'tsuiseki_tracking_css_class');
  register_setting('tsuiseki-tracking-settings', 'tsuiseki_tracking_excluded_uris');
} // function tsuiseki_tracking_admin_init

/**
 * Generates the administration menu.
 */
function tsuiseki_tracking_admin_menu() {
  add_menu_page('Tsuiseki Plugin Settings', 'Tsuiseki Settings', 'administrator', __FILE__, 'tsuiseki_tracking_settings_page');
} // function tsuiseki_tracking_admin_menu

/**
 * This function stores the tracking data into the session or updates the
 * values if needed.
 *
 * @return boolean
 */
function tsuiseki_tracking_buffer_data() {
  $dirty = FALSE;
  $c_network = '';
  $c_partner = '';
  $c_query = '';

  if (isset($_SESSION[TSUISEKI_TRACKER_COOKIE_NAME])) {
    $cookie_data = $_SESSION[TSUISEKI_TRACKER_COOKIE_NAME];
    if (_tsuiseki_tracking_validate_cookie_value($cookie_data)) {
      $parts = _tsuiseki_tracking_extract_cookie_data($cookie_data);
      $data = (string)trim($parts['data']);
      $c_opts = _tsuiseki_tracking_extract_data_from_url($data);
      // Get the values from the cookie.
      $c_network = _tsuiseki_tracking_get_network($c_opts);
      $c_partner = _tsuiseki_tracking_get_partner_id($c_opts);
      $c_query = _tsuiseki_tracking_get_query($c_opts);
    }
    else {
      $dirty = TRUE;
      if (isset($_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME])) {
        unset($_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME]);
      }
    }
  }
  // Get the values from the url.
  $network = _tsuiseki_tracking_get_network();
  $partner = _tsuiseki_tracking_get_partner_id();
  $query = _tsuiseki_tracking_get_query();
  // Now we validate the data and decide if we update the cookie values.
  if (empty($c_network) || (!empty($network) && ($network != 'free') && ($network != $c_network))) {
    $c_network = $network;
    $dirty = TRUE;
  }
  if (empty($c_partner) || (!empty($partner) && ($partner != $c_partner))) {
    $c_partner = $partner;
    $dirty = TRUE;
  }
  else {
    // If we have seen this guy more than once we append an '/i=1' to the
    // partner field to mark it as an internal view.
    if (isset($_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME]) && (int)$_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME] > 1) {
      // As the partner field was very likely deleted from the url if the
      // visitor moved around the website we prefer the value from the cookie.
      if (!empty($c_partner)) {
        $partner = $c_partner;
      }
      else {
        $partner = '';
      }
      if (!preg_match('/.*\/i=1$/', $partner)) {
        $c_partner = $partner .'/i=1';
        $dirty = TRUE;
      }
    }
  }
  if (empty($query) || (!empty($query) && ($query != $c_query))) {
    $c_query = $query;
    $dirty = TRUE;
  }
  // Now we store the data in the session if it was modified.
  if ($dirty) {
    $name = ip2long($_SERVER['REMOTE_ADDR']);
    $time = _tsuiseki_tracking_get_expiration_time();
    $data = _tsuiseki_tracking_create_cookie_data($c_network, $c_partner, $c_query);
    $cookie_data = _tsuiseki_tracking_calculate_cookie_value($name, $time, $data);
    $_SESSION[TSUISEKI_TRACKER_COOKIE_NAME] = $cookie_data;
  }
  // At last we count the times this function is fired.
  if (isset($_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME])) {
    $_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME] =
      (int)$_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME] + 1;
  }
  else {
    $_SESSION[TSUISEKI_TRACKER_COOKIE_VIEW_COUNTER_NAME] = 1;
  }
  $_SESSION['TSUISEKI_TRACKER_KEY'] = (string)trim(tsuiseki_tracking_get_key());

  return $dirty;
} // function tsuiseki_tracking_buffer_data

/**
 * This function extracts all information from the given data string
 * and fires the tracking system via curl().
 *
 * @param string $data The string containing the tracking informations.
 * @return int
 * <code>0</code> on success, <code>1</code> on failure.
 */
function tsuiseki_tracking_click($data) {
  if (!empty($data)) {
    $t_data = (string)(trim($data));
    // We need to cut the first "ref=" as it comes from the javascript part
    // and may overlay the partner field.
    if (preg_match('/^ref=.*/', $t_data)) {
      $t_data = mb_substr($t_data, mb_strpos($t_data, '=') + 1);
    }
    $t_parts = preg_split('/;;/', $t_data);
    $src_url = (string)(trim($t_parts[0]));
    $width = (string)(trim($t_parts[1]));
    $height = (string)(trim($t_parts[2]));
    $mousex = (string)(trim($t_parts[3]));
    $mousey = (string)(trim($t_parts[4]));
    $browser = (string)(trim($t_parts[5]));
    $browser_version = (string)(trim($t_parts[6]));
    $referer = (string)(trim($t_parts[7]));
    if (!empty($referer)) {
      // Wir extrahieren nur die Domain!
      $r_parts = array();
      preg_match('/[a-z]{3,5}:\/\/(.+?)[\/?:]/', $referer, $r_parts);
      if (!empty($r_parts[1])) {
        $referer = 'referer='. (string)trim($r_parts[1]);
      }
    }
    $click_type = (string)(trim($t_parts[8]));
    $click_bits = (string)trim($t_parts[9]);
  }

  $key = (string)trim($_SESSION['TSUISEKI_TRACKER_KEY']);
  if (!empty($src_url) && !empty($key)) {
    $ref = urldecode($src_url);
    $data = (string)(trim(tsuiseki_tracking_get_data($ref)));
    $data .= '&'. $width .'&'. $height .'&'. $mousex .'&'. $mousey .'&'. $browser .'&'. $browser_version .'&'. $click_bits .'&'. $referer .'&'. $click_type;
    $data = urlencode($data);
    $ip = ip2long($_SERVER['REMOTE_ADDR']);
    $url = "http://tracker.tsuiseki.com/tsuiseki.php?q=$key;1;$ip;$data&ajax=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, (string)trim($_SERVER['HTTP_USER_AGENT']));
    curl_setopt($ch, CURLOPT_REFERER, $src_url);
    $result = curl_exec($ch);
    if (curl_errno($ch) > 0) {
      trigger_error(curl_error($ch), curl_errno($ch));
    }
    curl_close($ch);
  }
  // generate the response
  $response = json_encode( array( 'success' => true ) );
  // response output
  header( "Content-Type: application/json" );
  echo $response;
  exit;
  //return 0;
} // function tsuiseki_tracking_click

/**
 * This function generates the javascript for the usage of the
 * tracking system with jquery.
 */
function tsuiseki_tracking_generate_javascript() {
  $track = TRUE;
  $tsuiseki_tracking_excluded_uris = explode("\n", str_replace(array("\r\n", "\r"), "\n", (string)get_option('tsuiseki_tracking_excluded_uris')));
  if (!empty($tsuiseki_tracking_excluded_uris)) {
    $uri = _request_uri();
    foreach ($tsuiseki_tracking_excluded_uris as $item) {
      $item = (string)trim($item);
      if (!empty($item)) {
        $item = '/^\/'. str_replace('/', '\/', $item) .'/i';
        $item = str_replace('*', '.*', $item);
        if (preg_match($item, $uri)) {
          $track = FALSE;
          break;
        }
      }
    } // foreach
  }
  if ($track === TRUE) {
    $output = '<script type="text/javascript" charset="UTF-8">';
    $bits_variable = 'cb'. hash('md5', uniqid() . mt_rand());
    $css = tsuiseki_tracking_get_css_class();
    $output .= "jQuery(document).ready(function() {
      // This function is for logging the view.
      function track_view() {
        var ref = 'none';
        if (document.referrer != '') {
          ref = escape(document.referrer);
        }
        var url = document.location.href;
        url = escape(url);
        var getData = 'ref='+ url + get_view_port_size() + get_browser_data() + ';;referer=' + ref;
        var funcSuccess = function(data) {
          var dummy = data.response;
        };
        jQuery.ajax({
          type: 'POST',
          url: '". get_bloginfo('wpurl') ."/wp-content/plugins/tsuiseki_tracking/tsuiseki_tracking.php?action=view',
          dataType: 'json',
          success: funcSuccess,
          data: getData
        });
        return false;
      };
      // Declare some variables.
      var $bits_variable = 0;
      var fc_$bits_variable = null;
      var md_$bits_variable = null;
      var mo_$bits_variable = null;
      // Attach to the focus event of links.
      jQuery('$css').focus(function(event) {
        $bits_variable = $bits_variable | 2;
        fc_$bits_variable = event.target || event.srcElement;
      });
      // Attach to the mousedown event.
      jQuery('$css').mousedown(function(event) {
        $bits_variable = $bits_variable | 4;
        md_$bits_variable = event.target || event.srcElement;
      });
      // Attach to the mouseover event.
      jQuery('$css').mouseover(function(event) {
        $bits_variable = $bits_variable | 8;
        mo_$bits_variable = event.target || event.srcElement;
      });
      // Attach to the click event of links.
      jQuery('$css').click(function(event) {
        $bits_variable = $bits_variable | 1;
        var target = event.target || event.srcElement;
        if (target == fc_$bits_variable && target == mo_$bits_variable && target == md_$bits_variable) {
          $bits_variable = $bits_variable | 16;
        }
        var ref = 'none';
        if (document.referrer != '') {
          ref = escape(document.referrer);
        }
        var nav = navigator;
        var url = document.location.href;
        url = escape(url);
        var click_type = this.id;
        var getData = 'ref='+ url + get_view_port_size() + get_click_coordinates(event) + get_browser_data() + ';;referer=' + ref +';;feed='+ click_type +';;bits='+ $bits_variable;
        var funcSuccess = function(data) {
          var dummy = data.response;
        };
        // Now we use Ajax to log the click.
        jQuery.ajax({
          type: 'POST',
          url: '". get_bloginfo('wpurl') ."/wp-content/plugins/tsuiseki_tracking/tsuiseki_tracking.php?action=click',
          dataType: 'json',
          success: funcSuccess,
          async: false,
          data: getData
        });
        
        $bits_variable = 0;
        // By returning true we assure that the link is handled by the browser.
        return true;
      });
      // Finally we log the view if there were no errors.
      track_view();
    });";
    $output .= '</script>';
    print $output;
  }
} // function tsuiseki_tracking_generate_javascript

/**
 * Returns the css class for outgoing links that should be tracked.
 *
 * @return string The css class that should be tracked for clicks.
 */
function tsuiseki_tracking_get_css_class() {
  $css = TSUISEKI_TRACKER_CSS_CLASS;
  if (empty($css)) {
    $css = (string)trim(get_option('tsuiseki_tracking_css_class'));
  }
  return $css;
} // function tsuiseki_tracking_get_css_class

/**
 * This function extracts the data for tracking out of the current url.
 *
 * @param string $ref The url of the current page.
 * @return string
 * A string that contains the collected data.
 */
function tsuiseki_tracking_get_data($ref = '') {
  $ref = (string)($ref);
  $opts = _tsuiseki_tracking_extract_data_from_url($ref);
  $c_network = '';
  $c_partner = '';
  $c_query = '';

  if (isset($_SESSION[TSUISEKI_TRACKER_COOKIE_NAME])) {
    $cookie_data = $_SESSION[TSUISEKI_TRACKER_COOKIE_NAME];
    if (_tsuiseki_tracking_validate_cookie_value($cookie_data)) {
      $parts = _tsuiseki_tracking_extract_cookie_data($cookie_data);
      $data = (string)trim($parts['data']);
      $c_opts = _tsuiseki_tracking_extract_data_from_url($data);
      // Get the values from the cookie.
      $c_network = _tsuiseki_tracking_get_network($c_opts);
      $c_partner = _tsuiseki_tracking_get_partner_id($c_opts);
      $c_query = _tsuiseki_tracking_get_query($c_opts);
    }
  }

  $output = '';
  $network = _tsuiseki_tracking_get_network($opts);
  if (!empty($c_network) && ($network == 'free' && $network != $c_network)) {
    // If the network parameter is 'free' and we have a different entry in the
    // session cookie the cookie entry is prefered.
    $network = _check_plain($c_network);
  }
  if (!empty($network)) {
    $output .= '&network='. $network;
  }

  $partner = _tsuiseki_tracking_get_partner_id($opts);
  if (empty($partner) && !empty($c_partner)) {
    $partner = _check_plain($c_partner);
  }
  if (!empty($partner)) {
    $output .= '&partner='. $partner;
  }

  $query = _tsuiseki_tracking_get_query($opts);
  if (empty($query) && !empty($c_query)) {
    $query = _check_plain($c_query);
  }
  if (!empty($query)) {
    $output .= '&query='. $query;
  }
  return $output;
} // function tsuiseki_tracking_get_data

/**
 * Returns the defined tsuiseki tracking key. If one is defined in the source code that one is preferred!
 * 
 * @return string The tracking key.
 */
function tsuiseki_tracking_get_key() {
  $key = TSUISEKI_TRACKER_KEY;
  if (empty($key)) {
    $key = (string)trim(get_option('tsuiseki_tracking_key'));
  }
  return $key;
} // function tsuiseki_tracking_get_key

/**
 * Initialize some variables.
 */
function tsuiseki_tracking_install() {
  add_option('tsuiseki_tracking_key', NULL, NULL, 'no');
  $css = TSUISEKI_TRACKER_CSS_CLASS;
  if (empty($css)) {
    $css = 'a.tsuiseki-link';
  }
  add_option('tsuiseki_tracking_css_class', $css, NULL, 'no');
  add_option('tsuiseki_tracking_excluded_uris', 'wp-admin/*', NULL, 'no');
} // function tsuiseki_tracking_install

/**
 * Generates the settings page.
 */
function tsuiseki_tracking_settings_page() {
?>
<div class="wrap">
<h2>Tsuiseki Tracking Plugin</h2>
<p>Please remind that a key or a css class defined within the source code will overwrite any changes you make here.</p>
<form method="post" action="options.php">
    <?php settings_fields( 'tsuiseki-tracking-settings' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Tsuiseki Tracking Key</th>
        <td><input type="text" name="tsuiseki_tracking_key" value="<?php echo get_option('tsuiseki_tracking_key'); ?>" />
        <span class="description">Enter your tracking key here. Without it nothing can be logged. You can get your tracking key at <a href="http://www.tsuiseki.com" target="_blank">tsuiseki.com</a>.</span>
        <?php $tmp = TSUISEKI_TRACKER_KEY; if (!empty($tmp)) : ?>
        <br /><span class="error">A key is defined in your plugin file source code.</span>
        <?php endif; ?>
        </td>
        </tr>

        <tr valign="top">
        <th scope="row">Tsuiseki Tracking CSS Class</th>
        <td><input type="text" name="tsuiseki_tracking_css_class" value="<?php echo get_option('tsuiseki_tracking_css_class'); ?>" />
        <span class="description">Define a CSS class for outgoing links. This is used for click tracking via ajax.</span>
        <?php $tmp = TSUISEKI_TRACKER_CSS_CLASS; if (!empty($tmp)) : ?>
        <br /><span class="error">A css class is defined in your plugin file source code.</span>
        <?php endif; ?>
        </td>
        </tr>

        <tr valign="top">
        <th scope="row">Exclude the following paths from tracking (one per line):</th>
        <td><textarea name="tsuiseki_tracking_excluded_uris"><?php echo get_option('tsuiseki_tracking_excluded_uris'); ?></textarea>
        <span class="description">The paths you list here (one per line) will be excluded from the tracking process. Use values like <code>foo/*</code> to exclude any path beginning with <code>foo/</code>.</span>
        </td>
        </tr>
    </table>

    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php
} // function tsuiseki_tracking_settings_page

/**
 * Remove some variables.
 */
function tsuiseki_tracking_uninstall() {
  delete_option('tsuiseki_tracking_key');
  delete_option('tsuiseki_tracking_css_class');
  delete_option('tsuiseki_tracking_excluded_uris');
} // function tsuiseki_tracking_install

/**
 * This function extracts all information from the given data string
 * and fires the tracking system via curl().
 *
 * @param string $data The string containing the tracking informations.
 * @return xajaxResponse
 */
function tsuiseki_tracking_view($data) {
  if (!empty($data)) {
    $t_data = (string)(trim($data));
    // We need to cut the first "ref=" as it comes from the javascript part
    // and may overlay the partner field.
    if (preg_match('/^ref=.*/', $t_data)) {
      $t_data = mb_substr($t_data, mb_strpos($t_data, '=') + 1);
    }
    $t_parts = preg_split('/;;/', $t_data);
    $src_url = (string)(trim($t_parts[0]));
    $width = (string)(trim($t_parts[1]));
    $height = (string)(trim($t_parts[2]));
    $browser = (string)(trim($t_parts[3]));
    $browser_version = (string)(trim($t_parts[4]));
    $referer = (string)(trim($t_parts[5]));
    if (!empty($referer)) {
      // Wir extrahieren nur die Domain!
      $r_parts = array();
      preg_match('/[a-z]{3,5}:\/\/(.+?)[\/?:]/', $referer, $r_parts);
      if (!empty($r_parts[1])) {
        $referer = 'referer='. (string)trim($r_parts[1]);
      }
    }
  }
  $key = (string)trim($_SESSION['TSUISEKI_TRACKER_KEY']);
  if (!empty($src_url) && !empty($key)) {
    $ref = urldecode($src_url);
    $data = (string)(trim(tsuiseki_tracking_get_data($ref)));
    $data .= '&'. $width .'&'. $height .'&'. $browser .'&'. $browser_version .'&'. $referer;
    $data = urlencode($data);
    $ip = ip2long($_SERVER['REMOTE_ADDR']);
    $url = "http://tracker.tsuiseki.com/tsuiseki.php?q=$key;0;$ip;$data&ajax=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, (string)trim($_SERVER['HTTP_USER_AGENT']));
    curl_setopt($ch, CURLOPT_REFERER, $src_url);
    $result = curl_exec($ch);
    if (curl_errno($ch) > 0) {
      trigger_error(curl_error($ch), curl_errno($ch));
    }
    curl_close($ch);
  }

  // generate the response
  $response = json_encode( array( 'success' => true ) );
  // response output
  header( "Content-Type: application/json" );
  echo $response;
  exit;
  //return 0;
} // function tsuiseki_tracking_view

/**
 * @name helpers Helper Functions
 * @{
 */

/**
 * Replace all special characters by their html entities.
 * This function was borrowed from drupal.
 *
 * @param $text A string to encode.
 * @return The encoded string.
 */
function _check_plain($text) {
  return _validate_utf8($text) ? htmlspecialchars($text, ENT_QUOTES) : '';
} // function _check_plain

/**
 * Determine the request uri.
 * This function was borrowed from drupal.
 *
 * @return string The request uri.
 */
function _request_uri() {
  if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
  }
  else {
    if (isset($_SERVER['argv'])) {
      $uri = $_SERVER['SCRIPT_NAME'] .'?'. $_SERVER['argv'][0];
    }
    elseif (isset($_SERVER['QUERY_STRING'])) {
      $uri = $_SERVER['SCRIPT_NAME'] .'?'. $_SERVER['QUERY_STRING'];
    }
    else {
      $uri = $_SERVER['SCRIPT_NAME'];
    }
  }
  // Prevent multiple slashes to avoid cross site requests via the FAPI.
  $uri = '/'. ltrim($uri, '/');

  return $uri;
}

/**
 * Calculates the string that should be stored in the user's cookie.
 *
 * @param string $name
 * @param int $expiration_time
 * @param string $data
 * @return string
 */
function _tsuiseki_tracking_calculate_cookie_value($name, $expiration_time, $data) {
  $name = (string)trim($name);
  if (empty($name)) {
    trigger_error('Parameter $name not given!');
  }
  $expiration_time = (int)$expiration_time;
  if ($expiration_time <= 0) {
    trigger_error('Illegal value for parameter $expiration_time!');
  }
  $data = (string)trim($data);
  if (empty($data)) {
    trigger_error('Parameter $data not given!');
  }
  $key = _tsuiseki_tracking_calculate_k($name, $expiration_time, TSUISEKI_TRACKER_HMAC_KEY);
  $cookie_data = $name . TSUISEKI_TRACKER_COOKIE_CONCAT_CHAR . $expiration_time . TSUISEKI_TRACKER_COOKIE_CONCAT_CHAR . $data;
  $cookie_data .= TSUISEKI_TRACKER_COOKIE_CONCAT_CHAR . hash_hmac(TSUISEKI_TRACKER_HMAC_ALGORITHM,
    $name . $expiration_time . $data . session_id(), $key);
  return $cookie_data;
} // function _tsuiseki_tracking_calculate_cookie_value

/**
 * Returns the HMAC value for the given input.
 *
 * @param string $name
 * @param int $expiration_time
 * @param string $key
 * @return string
 */
function _tsuiseki_tracking_calculate_k($name, $expiration_time, $key) {
  $name = (string)trim($name);
  if (empty($name)) {
    trigger_error('Parameter $name not given!');
  }
  $expiration_time = (int)$expiration_time;
  if ($expiration_time <= 0) {
    trigger_error('Illegal value for parameter $expiration_time!');
  }
  $key = (string)trim($key);
  if (empty($key)) {
    trigger_error('Parameter $key not given!');
  }
  $data = $name . $expiration_time;
  return hash_hmac(TSUISEKI_TRACKER_HMAC_ALGORITHM, $data, $key);
} // function _tsuiseki_tracking_calculate_k

/**
 * Creates the data string that should be stored into the cookie.
 *
 * @param string $network
 * @param string $partner
 * @param string $query
 * @return string
 */
function _tsuiseki_tracking_create_cookie_data($network, $partner, $query) {
  $cookie_data = '';
  $names = array();
  $network = (string)trim($network);
  $partner = (string)trim($partner);
  $query = (string)trim($query);
  if (!empty($network)) {
    $names = _tsuiseki_tracking_get_network_names();
    $cookie_data .= '&'. $names[0] .'='. $network;
  }
  if (!empty($partner)) {
    $names = _tsuiseki_tracking_get_partner_id_names();
    $cookie_data .= '&'. $names[0] .'='. $partner;
  }
  if (!empty($query)) {
    $names = _tsuiseki_tracking_get_query_names();
    $cookie_data .= '&'. $names[0] .'='. $query;
  }
  return $cookie_data;
} // function _tsuiseki_tracking_create_cookie_data

/**
 * Extracts the data fields stored in the tracking cookie.
 *
 * @param string $cookie_data
 * @return array
 */
function _tsuiseki_tracking_extract_cookie_data($cookie_data) {
  $parts = array();
  $cookie_data = (string)trim($cookie_data);
  if (!empty($cookie_data)) {
    $parts = preg_split('/'. TSUISEKI_TRACKER_COOKIE_CONCAT_CHAR .'/', $cookie_data);
    $name = (string)trim($parts[0]);
    $expiration_time = (int)$parts[1];
    $data = (string)trim($parts[2]);
    $hmac = (string)trim($parts[3]);
    $parts = array(
      'name' => $name,
      'expiration_time' => $expiration_time,
      'data' => $data,
      'hmac' => $hmac,
    );
  }
  return $parts;
} // function _tsuiseki_tracking_extract_cookie_data

/**
 * Extracts the tracking data from the given url and puts
 * it in form of an array so it can be easily parsed.
 * All values of the form "&|?foo=bar" are extracted into
 * an array of the format: array ( "foo" => bar ).
 *
 * @param string $ref
 * @return array
 */
function _tsuiseki_tracking_extract_data_from_url($ref) {
  $ref = (string)($ref);
  $opts = array();
  if (!empty($ref)) {
    $ref = urldecode($ref);
    $parts = preg_split('/[&|?]/', $ref);
    if (!empty($parts)) {
      $p = array();
      foreach ($parts as $part) {
        if (preg_match('/\w+?=.*\/i=1/', $part)) {
          $p = preg_split('/=/', $part);
          $opts[$p[0]] = $p[1] .'='. $p[2];
        }
        else if (preg_match('/\w+?=.*/', $part)) {
          $p = preg_split('/=/', $part);
          $opts[$p[0]] = $p[1];
        }
      } // foreach
    }
  }
  return $opts;
} // function _tsuiseki_tracking_extract_data_from_url

/**
 * Returns the expiration time of cookie data as unix timestamp.
 *
 * @return int
 */
function _tsuiseki_tracking_get_expiration_time() {
  return (time() + TSUISEKI_TRACKER_COOKIE_TIMEOUT);
} // function _tsuiseki_tracking_get_expiration_time

/**
 * Determines the name of the partner network.
 *
 * @param array $opts
 * An array that shall be parsed instead of $_GET.
 * @param int $max_length
 * The maximum length of the network name.
 * @return string
 * The name of the network or <code>NULL</code>.
 */
function _tsuiseki_tracking_get_network($opts = array(), $max_length = 254) {
  $max_length = (int)($max_length);
  $network = NULL;
  foreach (_tsuiseki_tracking_get_network_names() as $key) {
    if (empty($opts)) {
      if (isset($_GET["$key"]) && !empty($_GET["$key"])) {
        $network = (string)(trim(_check_plain($_GET["$key"])));
        if (mb_strlen($network) > $max_length) {
          $network = substr($network, 0, $max_length);
        }
        break; // Schleife beenden
      }
    }
    else {
      if (isset($opts["$key"]) && !empty($opts["$key"])) {
        $network = (string)(trim(_check_plain($opts["$key"])));
        if (mb_strlen($network) > $max_length) {
          $network = substr($network, 0, $max_length);
        }
        break; // Schleife beenden
      }
    }
  } // foreach
  if (empty($network)) {
    $network = 'free';
  }
  return $network;
} // function _get_query

/**
 * Returns a list of parameter names ($_GET['X']) that may
 * contain a network name.
 *
 * @return array
 * A list of parameter names.
 */
function _tsuiseki_tracking_get_network_names() {
  return array(
    'site',
  );
} // function _get_network_names

/**
 * Determines the partner id.
 *
 * @param array $opts
 * An array that shall be parsed instead of $_GET.
 * @param int $max_length
 * The maximum length of the partner id.
 * @return string
 * The partner id or <code>NULL</code>.
 */
function _tsuiseki_tracking_get_partner_id($opts = array(), $max_length = 254) {
  $max_length = (int)($max_length);
  $p_id = NULL;
  $pids = array();
  foreach (_tsuiseki_tracking_get_partner_id_names() as $key) {
    if (empty($opts)) {
      if (isset($_GET["$key"]) && !empty($_GET["$key"])) {
        $pids[] = (string)(trim(_check_plain($_GET["$key"])));
      }
    }
    else {
      if (isset($opts["$key"]) && !empty($opts["$key"])) {
        $pids[] = (string)(trim(_check_plain($opts["$key"])));
      }
    }
  } // foreach
  // IDs zusammenfügen
  if (count($pids) > 0) {
    $p_id = implode('-', $pids);
  }
  // auf länge begrenzen
  if (mb_strlen($p_id) > $max_length) {
    $p_id = substr($p_id, 0, $max_length);
  }
  return $p_id;
} // function _get_partner_id

/**
 * Returns a list of parameter names ($_GET['X']) that may
 * contain a partner id.
 *
 * @return array
 * A list of parameter names.
 */
function _tsuiseki_tracking_get_partner_id_names() {
  return array(
    'ref',
    'quelle',
  );
} // function _get_partner_id_names

/**
 * Determines the search query.
 *
 * @param array $opts
 * An array that shall be parsed instead of $_GET.
 * @param int $max_length
 * The maximum length of the search query.
 * @return string
 * The search query or <code>NULL</code>.
 */
function _tsuiseki_tracking_get_query($opts = array(), $max_length = 64) {
  $max_length = (int)($max_length);
  $query = NULL;
  foreach (_tsuiseki_tracking_get_query_names() as $key) {
    if (empty($opts)) {
      if (isset($_GET["$key"]) && !empty($_GET["$key"])) {
        $query = (string)(trim(_check_plain($_GET["$key"])));
        if (mb_strlen($query) > $max_length) {
          $query = substr($query, 0, $max_length);
        }
        break; // Schleife beenden
      }
    }
    else {
      if (isset($opts["$key"]) && !empty($opts["$key"])) {
        $query = (string)(trim(_check_plain($opts["$key"])));
        if (mb_strlen($query) > $max_length) {
          $query = substr($query, 0, $max_length);
        }
        break; // Schleife beenden
      }
    }
  } // foreach
  return $query;
} // function _get_query

/**
 * Returns a list of parameter names ($_GET['X']) that may
 * contain a search query.
 *
 * @return array
 * A list of parameter names.
 */
function _tsuiseki_tracking_get_query_names() {
  return array(
    'qe',
    'target_passthrough',
    'query',
  );
} // function _get_query_names()

/**
 * Returns the referer.
 * If the referer is longer than $max_length characters it is cut off.
 *
 * @param int $max_length
 * The maximum allowed length of the referer.
 * @return string
 * The referer or <code>NULL</code>.
 */
function _tsuiseki_tracking_get_referer($max_length = 254) {
  $max_length = (int)($max_length);
  $ref = NULL;
  if (isset($_SERVER['HTTP_REFERER'])) {
    $ref = _check_plain($_SERVER['HTTP_REFERER']);
    if (mb_strlen($ref) > $max_length) {
      $ref = substr($ref, 0, $max_length);
    }
  }
  return $ref;
} // function _nue_feed_get_referer

/**
 * Returns the name of the server.
 * If the name is longer than $max_length characters it is cut off.
 *
 * @todo Check if it is better to use <code>$_SERVER['HTTP_HOST']</code>.
 *
 * @param $max_length
 * The maximum allowed length of the server name.
 * @return string
 * The server name or <code>NULL</code>.
 */
function _tsuiseki_tracking_get_server_name($max_length = 254) {
  $max_length = (int)($max_length);
  $server = NULL;
  if (isset($_SERVER['SERVER_NAME'])) {
    $server = _check_plain($_SERVER['SERVER_NAME']);
    if (mb_strlen($server) > $max_length) {
      $server = substr($server, 0, $max_length);
    }
  }
  return $server;
} // function _tsuiseki_tracking_get_server_name

/**
 * This function validates the integrity of the data from the cookie.
 *
 * @param string $cookie_data
 * @return boolean
 */
function _tsuiseki_tracking_validate_cookie_value($cookie_data) {
  $cookie_data = (string)trim($cookie_data);
  $time = time();
  $valid = FALSE;
  if (!empty($cookie_data)) {
    $parts = _tsuiseki_tracking_extract_cookie_data($cookie_data);
    if ($parts['expiration_time'] > $time) {
      $name = (string)trim($parts['name']);
      $expiration_time = (int)$parts['expiration_time'];
      $data = (string)trim($parts['data']);
      $hmac = (string)trim($parts['hmac']);
      $key = _tsuiseki_tracking_calculate_k($name, $expiration_time, TSUISEKI_TRACKER_HMAC_KEY);
      $t_hmac = hash_hmac(TSUISEKI_TRACKER_HMAC_ALGORITHM, $name . $expiration_time . $data . session_id(), $key);
      if ($hmac === $t_hmac) {
        $valid = TRUE;
      }
    }
  }
  return $valid;
} // function _tsuiseki_tracking_validate_cookie_value

/**
 * Checks if the given text string is utf-8 encoded.
 * This function was borrowed from drupal.
 *
 * @param $text A string to check.
 * @return <code>TRUE</code> or <code>FALSE</code>
 */
function _validate_utf8($text) {
  if (strlen($text) == 0) {
    return TRUE;
  }
  return (preg_match('/^./us', $text) == 1);
} // function _validate_utf8

/**
 * @}
 */

/**
 * @name wordpress Wordpress Code
 * @{
 */

// Do we have some ajax action?
if (!empty($_REQUEST['action']) && !empty($_POST['ref'])) {
  $action = (string)trim($_REQUEST['action']);
  $data = (string)trim($_POST['ref']);
  if ($action === 'click') {
    tsuiseki_tracking_click($data);
    exit();
  }
  if ($action === 'view') {
    tsuiseki_tracking_view($data);
    exit();
  }
}
// Register some functions
register_activation_hook(__FILE__, 'tsuiseki_tracking_install');
register_deactivation_hook(__FILE__, 'tsuiseki_tracking_uninstall');
// Enqueue our javascript for inclusion and give info that we need jquery.
wp_enqueue_script('tsuiseki_js', get_bloginfo('wpurl') .'/wp-content/plugins/tsuiseki_tracking/tsuiseki_tracking.js', array('jquery'));
// Define the ajax callbacks for views and clicks.
add_action('wp_ajax_tsuiseki_tracking_click', 'tsuiseki_tracking_click');
add_action('wp_ajax_tsuiseki_tracking_view', 'tsuiseki_tracking_view');
add_action('wp_ajax_nopriv_tsuiseki_tracking_click', 'tsuiseki_tracking_click');
add_action('wp_ajax_nopriv_tsuiseki_tracking_view', 'tsuiseki_tracking_view');
// Buffer tracking data.
add_action('wp_head', 'tsuiseki_tracking_buffer_data');
// Add our custom javascript to the header.
add_action('wp_head', 'tsuiseki_tracking_generate_javascript');
// Add code for our admin section
if (is_admin()) {
  add_action('admin_menu', 'tsuiseki_tracking_admin_menu');
  add_action('admin_init', 'tsuiseki_tracking_admin_init');
}
/**
 * @}
 */
