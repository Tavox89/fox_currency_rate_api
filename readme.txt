=== FOX Currency Rate API ===
Contributors: tavox89
Tags: currency, exchange rate, api, woocommerce
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Fox Currency Rate API exposes read-only REST endpoints for the FOX - Currency Switcher Professional for WooCommerce plugin.

== Installation ==
1. Sube el directorio del plugin a `/wp-content/plugins/`.
2. Activa el plugin en el menú "Plugins" de WordPress.
3. Configure the upstream URL from the plugin settings if needed.

== Usage ==
Consulta `GET /wp-json/fox-rate/v1/rate?from=USD&to=VES` para obtener la tasa actual almacenada por el switcher.

== Changelog ==
= 1.1.0 =
* Added REST endpoint `fox-rate/v1/rate` with a 5-minute cache, 2 s upstream timeout, stale fallback, and `no-store` headers.

= 1.0.2 =
* Initial release.
