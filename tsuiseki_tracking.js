// $Id: tsuiseki_tracking.js 6405 2010-04-11 12:07:41Z jens $
/**
 * @file
 * This javascript file contains some helper functions for the tsuiseki tracking.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 */

/**
 * This function returns informations about the user's browser.
 */
function get_browser_data() {
  var browser;
  var version;
  var n = navigator;
  if (n.appName) {
	browser = n.appName;
  }
  if (n.appVersion) {
    version = n.appVersion;
  }
  var data = ';;browser=' + browser + ';;browserversion=' + version;
  return data;
}

/**
 * This function returns the mouse coordinates.
 */
function get_click_coordinates(e) {
  var x = 0;
  var y = 0;
  var coordinates;
  
  if (!e) e = window.event;
  var body = (window.document.compatMode && window.document.compatMode == "CSS1Compat") ? 
  window.document.documentElement : window.document.body || null;
  
  y = e.layerY ? e.layerY : e.clientY + body.scrollTop; 
  x = e.layerX ? e.layerX : e.clientX + body.scrollLeft; 
    
  // Build string
  coordinates = ';;x=' + x + ';;y=' + y;
  // and return it.
  return coordinates;
}

/**
 * This function gets the viewport size
 * (source: http://andylangton.co.uk/articles/javascript/get-viewport-size-javascript/).
 */
function get_view_port_size() {
  var viewportwidth = 0;
  var viewportheight = 0;
  var viewport;
  
  // Mozilla/Netscape/Opera/IE7
  if (typeof window.innerWidth != 'undefined') {
    viewportwidth = window.innerWidth;
    viewportheight = window.innerHeight;
  }
  // IE6
  else if (typeof document.documentElement != 'undefined' && 
    typeof document.documentElement.clientWidth !='undefined' && 
    document.documentElement.clientWidth != 0) {
      viewportwidth = document.documentElement.clientWidth;
      viewportheight = document.documentElement.clientHeight;
  }
  // older versions of IE
  else {
    viewportwidth = document.getElementsByTagName('body')[0].clientWidth;
    viewportheight = document.getElementsByTagName('body')[0].clientHeight;
  }
  
  // Write both values into a string
  viewport = ';;width=' + viewportwidth + ';;height=' + viewportheight;
  // and return the value.
  return viewport;
}