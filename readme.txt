=== Plugin Name ===
Contributors: nexico
Tags: analysis, tracking, e-commerce, click fraud detection
Requires at least: 2.9
Tested up to: 3.0
Stable tag: 1.0.5

Allows tracking via the Tsuiseki tracking and data analysis system.

== Description ==

The Tsuiseki Tracking plugin integrates the [Tsuiseki Tracking and Data Analysis](http://www.tsuiseki.com) system into
your wordpress installation. To use this plugin a tracking key is required which you can obtain by
[registering at our website](http://www.tsuiseki.com/pricing.html).
Afterwards you will be able to use the Tsuiseki Data Analysis system to analyse your website traffic and detect
click fraud (e.g. click robots and click farms) from your traffic sources and react accordingly.

== Installation ==

1. Extract the `tsuiseki_tracking.tar.gz` into the `/wp-content/plugins/` directory or install the plugin via the admin panel.
1. Open the file `tsuiseki_tracking.php` and change the value of the variable `TSUISEKI_TRACKER_HMAC_KEY` at line 64 (see http://www.tsuiseki.com/faq.html#2n26).
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Get your Tsuiseki tracking key at http://www.tsuiseki.com and set it either in the configuration menu or in the file `tsuiseki_tracking.php` at line 25.
1. Configure the CSS selectors to track the clicks you desire (see http://www.tsuiseki.com/faq.html#2n31).
1. If you have any further questions please visit our website for help or contact our technical support team.

== Frequently Asked Questions ==

Please refer to our official FAQ at http://www.tsuiseki.com/faq.html.

== Screenshots ==

1. The settings section of the Tsuiseki Tracking plugin.
2. A screenshot of our [online demo](http://demo.tsuiseki.com) that can be tested at http://demo.tsuiseki.com.

== Changelog ==

= 1.0.5 =
* Fixing js path

= 1.0.4 =
* Fixing some tracking relevant bugs

= 1.0.3 =
* Renamed css class to css selector as we are using selector expressions not only class names.
* Updated screenshot and readme

= 1.0.2 =
* Added a screenshot from our online demo
* Adjusted the screenshot size to the wordpress.org site (560px wide)

= 1.0.1 =
* Changed the link for user registration
* Removed the donate link

= 1.0 =
* Initial release of the wordpress plugin.

== Upgrade Notice ==

= General Upgrade Instructions =
If you define settings within the source code (e.g. the `TSUISEKI_TRACKER_KEY` or `TSUISEKI_TRACKER_HMAC_KEY`)
be sure to backup your old plugin directory and migrate your settings into the new source code file.

= 1.0.5 =
Be sure to backup your old tsuiseki_tracking.php if you defined settings directly in the source code. Thus you can
migrate your changes afterwards.

= 1.0.4 =
Be sure to backup your old tsuiseki_tracking.php if you defined settings directly in the source code. Thus you can
migrate your changes afterwards.

= 1.0.3 =
Be sure to backup your old tsuiseki_tracking.php if you defined settings directly in the source code. Thus you can
migrate your changes afterwards.

= 1.0.2 =
Be sure to backup your old tsuiseki_tracking.php if you defined settings directly in the source code. Thus you can
migrate your changes afterwards.

= 1.0.1 =
Be sure to backup your old tsuiseki_tracking.php if you defined settings directly in the source code. Thus you can
migrate your changes afterwards.

= 1.0 =
No instructions needed.
