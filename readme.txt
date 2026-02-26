=== Order Notifications for WooCommerce Telegram ===
Contributors: ridhwanahsann
Donate link: https://github.com/ridhwanahsan/woo-telegram-notify
Tags: woocommerce, telegram, notifications, orders, bot
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Telegram notifications automatically when a WooCommerce order is created or its status changes. Includes a top-level “Telegram Notify” menu and Pro/Future options like rich messages, multiple bots, filters, and delay.

== Description ==

Order Notifications for WooCommerce Telegram sends order updates to one or more Telegram chats using your Telegram Bot.

Features:

* Notify on new orders and on order status changes.
* Enable/disable notifications from settings.
* Support multiple Telegram Chat IDs (comma separated).
* Choose which order statuses trigger notifications.
* Custom message template with placeholders.
* Test notification button from the settings screen.
* Simple file logging for Telegram responses/errors.
* Pro / Future: rich messages, filters, multiple bots, and more (see below).

Settings location:

Dashboard → Telegram Notify

Available placeholders:

{site_name} {order_id} {customer_name} {phone} {order_total} {payment_method} {order_status} {order_date} {order_link} {products_list} {quantity} {shipping_method} {billing_address} {coupon_used} {order_notes}

Logs:

wp-content/uploads/woo-telegram-notify/logs.txt

== Pro / Future Features ==

Location:

Dashboard → Telegram Notify → Pro / Future Features

Sections and highlights:

* Advanced Notifications
  * Per-status custom templates
  * Admin vs Customer notifications
  * Delay notification (0–5 minutes)
* Filters & Conditions
  * Minimum order amount
  * Country / payment method filters
  * Product / category specific filters
* Rich Message / Buttons
  * Markdown/HTML parse mode
  * Optional ordered products list
  * Inline buttons (example “View Orders”)
* Team / Multi-Bot
  * Configure multiple bots (Label|Token|ChatID1,ChatID2)
  * Role-based chat mappings
* Logs & Analytics
  * Log rotation (max size, retention)
  * Basic counters
* AI Message Generator
  * Toggle and test button (placeholder)

Test buttons are provided for rich messages, multi-bot, and AI message generator.

Example rich message:

*Site Name* — _New Order_  
Order #{order_id} — Total {order_total}  
{products_list}

Example multi-bot configuration line:

SupportBot|123456:ABCDEF|123456789,-1001234567890

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install the plugin ZIP via WordPress → Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Dashboard → Telegram Notify → General Settings.
4. Enable notifications, enter your Bot Token and Chat ID(s), choose statuses, then save.
5. Use the Test Notification button to verify delivery.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. If WooCommerce is not active, an admin notice is shown and the plugin does not load order hooks.

= How do I find my Chat ID? =

For private chats, you can message your bot and use a Telegram “getUpdates” method/tooling to find the chat id. For groups/supergroups, add the bot and retrieve the group chat id (usually negative).

= Where do I get the Bot Token and Chat ID? =

Telegram Bot Token:

1. Open Telegram and start a chat with @BotFather.
2. Send /newbot and follow the prompts to set a name and username.
3. BotFather will provide an HTTP API token — that is your Bot Token.

Telegram Chat ID:

Option A (Private chat):

1. Send a message to your bot (for example: "Hi").
2. Open this URL in your browser (replace YOUR_BOT_TOKEN):
   https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates
3. In the JSON response, find `"chat":{"id": ... }` and copy the `id` value.

Option B (Group / Supergroup):

1. Add your bot to the group (grant admin permissions if required).
2. Send a message in the group.
3. Open getUpdates again and copy the group chat `id` (often negative, for example `-100xxxxxxxxxx`).

Multiple Chat IDs:

Add multiple Chat IDs separated by commas, for example: 123456789,-1001234567890

= Can I send to multiple chats? =

Yes. Add multiple Chat IDs separated by commas.

= Can I customize the message? =

Yes. Use the Custom Message Template field and the available placeholders.

== Screenshots ==

1. Settings page under Dashboard → Telegram Notify.

== Changelog ==

= 1.1.0 =

* New top-level admin menu “Telegram Notify” with General + Pro pages.
* Pro/Future settings tabs and test buttons (Rich, Multi-Bot, AI).
* Optional rich messages (Markdown/HTML) and inline buttons.
* Multiple bot support and role/team scaffolding.
* Per-status templates and extended placeholders.
* Optional delay via WP‑Cron; basic filters (min total, country, payment).
* Bot token encryption at rest; log rotation controls.
* Documentation updates to readme and placeholders list.

= 1.0.0 =

* Initial release.
