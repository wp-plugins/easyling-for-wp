=== Plugin Name ===
Contributors: dvarga
Tags: easyling, translation, multilanguage, website translation, wordpress translation, translation plugin
Requires at least: 3.5
Tested up to: 4.1.1
Stable tag: 0.9.22
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easyling is a WordPress translation plugin, suitable for DIY work, crowdsource translation or order the professional translation service.

== Description ==

[Easyling.com](http://www.easyling.com/?utm_source=easyling-wp-plugin-admin&utm_medium=admin-link&utm_campaign=easyling-wp "") is a proxy-based website translation solution. It crawls your website, finds all the pages and texts. Then you can do the translation yourself using our in-context, live editing/preview/review editor, or export as an XLIFF file and do the translation in your favourite CAT tool.

Once the translation of your Wordpress is done, this Easyling for Wordpress plugin does the magic: automatically downloads your translations from Easyling only once, 
and presents it to your visitors using domain-based or URL-based configuration, i.e.:

*   www.example.com for the main language
*   www.example.com/de/ for the German translation

Or

*   www.example.com for the main language
*   www.example.de for the German translation

Or

*   www.example.com for the main language
*   http://de.example.com for the German translation

== Installation ==

1. Upload the plugin to the `/wp-content/plugins` directory
2. Activate the plugin on the 'Plugins' menu in WordPress
3. Link your Wordpress Installation with your Easyling Account
4. Select your project, available languages and translations under the `Easyling` menu

For more information please visit the FAQ.

= Dependencies =

* MySQL 5.x
* PHP 5.2.4
* PHP Extension: openssl
* PHP Extension: curl
* PHP Extension: iconv
* PHP Extension: mbstring

== Upgrade Notice ==

We recommend to test the Plugin on a *non-live* version of your blog to confirm
functionality before activating it on a production website.

== Frequently Asked Questions ==

= Is the Easyling for Wordpress Plugin free to use? =

Yes, 100% free. [Easyling.com Website Translation](http://www.easyling.com/pricing/?utm_source=easyling-wp-plugin-admin&utm_medium=admin-link&utm_campaign=easyling-wp "") also has got a free plan.

= Will it work with my theme? =

Yes. By design, it doesn't matter what theme do you use. 

= Do I need to set up an account with Easyling? =

Yes! The Plugin is not worth much without an Easyling account. You can create one by visiting [Easyling](https://app.easyling.com/login/frame/reg "")

= What is that "linking" and how do I do that? =

It is necessary to "link" your WP Installation with Easyling so the plugin can download the translations from the Easyling application in the cloud.

= How do I import the translations? =

Once you have an Easyling account and you get some translations done, navigate to the Easyling menu on your WordPress admin and simply click
Retrieve Translations. The process may take up to a few minutes, but it's safe to close the browser, it happens in the background.

= Is it stable for production use? =

To date it has been tested with a standard WordPress 4.1 installation running Apache and MySQL on Ubuntu server, however there could be
certain edge cases when the plugin is not 100% functional! If you happen to stumble upon such issue, just give us feedback and we’ll be happy to fix it.

== Screenshots ==

1. This screenshot displayes the configuration settings of Easyling for WP. Simple and straightforward.
2. Demonstrates how to "link" the WP Installation with Easyling Services

== Changelog ==

= 0.9.22 =
* Works with php.ini settings display_errors = on

= 0.9.21 =
* Small fixes

= 0.9.20 =
* Added feedback to user, when error occurred during the linking

= 0.9.19 =
* Changed Easyling endpoint to HTTPS

= 0.9.18 =
* Added whitelabel support

= 0.9.17 =
* Implemented Easyling features: Group pages, Pattern matching, Ignore classes
* Added alternative page languages
* Fixed HTML lang attribute
* Fixed rewrite rules

= 0.9.16 =
* Updated short description

= 0.9.15 =
* Project access fix

= 0.9.14 =
* Language selector fix

= 0.9.13 =
* Updated readme

= 0.9.12 =
* Prefixed the OAuth lib's classes so it would not confict with other lib's OAuth libraries for some of our users

= 0.9.11 =
* Fixed a small compatibility issue with PHP 5.2 in easyling_get_languages method

= 0.9.10 =
* Multiple enhancements to how communication between Easyling and the WP blog is handled

= 0.9.9 =
* Tweaks on the admin to better user experience

= 0.9.8 =
* HTML5 Doctype support

= 0.9.7 =
* Updated name of the plugin

= 0.9.6 =
* Numerous small usability improvements

= 0.9.5 =
* Using live easyling version

= 0.9.4 =
* Important compatibility update

= 0.9.3 =
* Updated repo

= 0.9.2 =
* Multiple improvements to the admin UI
* Included 5 step tutorial on how to "link"
* Some improvements to better the stability

= 0.9.1 =
* Easyling Plugin commited to the Wordpress Repo
* Open Beta starts :)
