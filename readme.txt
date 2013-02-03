=== Plugin Name ===
Contributors: dvarga
Tags: easyling, translation, multilanguage
Requires at least: 3.5
Tested up to: 3.5
Stable tag: 0.9.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easyling is a Website translation tool, suitable for DIY work; or order the professional translation service from  www.easyling.com.

== Description ==

[Easyling.com](http://www.easyling.com "") is a proxy-based website translation solution. It crawls your website, finds all the pages and texts. Then you can do the translation yourself using our in-context, live editing/preview/review editor, or export as an XLIFF file and do the translation in your favourite CAT tool. As an alternative, just leave all the work to us and [order the website translation from Easyling](http://www.easyling.com/website-owners/ ""). 

Once the translation is done, this Easyling for Wordpress plugin does the magic: automatically downloads your translations from Easyling only once, 
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
1. Activate the plugin on the 'Plugins' menu in WordPress
1. Link your Wordpress Installation with your Easyling Account
1. Select your project, available languages and translations under the `Easyling` menu

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

Yes, 100% free. [Easyling.com](http://www.easyling.com/website-owners/prices "") also has a free plan. 

= Will it work with my (XXXXX) theme? =

Yes. By design, it doesn't matter what theme do you use. 

= Do I need to have an Easyling registration? =

Yes! The Plugin is not worth much without an Easyling account. You can create one by visiting [Easyling](http://www.easyling.com/website-owners/#register "")

= What is that "linking" and how do I do that? =

It is necessary to "link" your WP Installation with Easyling services so that the plugin can download the translation from the application in the cloud.

= How do I import the translations? =

Once you have an Easyling account and you get some translations done, go the Easyling menu on your Wordpress admin
and simply click `Retrieve Translations`. The process may take up to a few minutes, but it's safe to close the browser, it happens in the background.

= Is it stable for production use? =

We would like to think so, however there could be certain edge cases when the working of the plugin is not 100%!
Whenever we hear about such a case, we jump on it and give a fix.

== Screenshots ==

1. This screenshot displayes the configuration settings of Easyling for WP. Simple and straightforward.
2. Demonstrates how to "link" the WP Installation with Easyling Services

== Changelog ==

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
