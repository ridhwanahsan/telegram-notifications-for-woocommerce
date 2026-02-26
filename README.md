# Order Notifications for WooCommerce Telegram

Send Telegram notifications automatically when a WooCommerce order is created or its status changes.

## Features

- Notify on new orders and on order status changes.
- Enable/disable notifications from settings.
- Multiple Telegram Chat IDs (comma separated).
- Select which order statuses trigger notifications.
- Custom message template with placeholders.
- Test notification button from the settings screen.
- Logs Telegram responses/errors to a file in uploads.
- Top‑level admin menu “Telegram Notify” with General and Pro/Future pages.
- Pro/Future options: rich messages (Markdown/HTML), multiple bots, filters, delay, per‑status templates, AI test.

## Requirements

- WordPress 6.0+
- WooCommerce active
- PHP 7.4+

## Setup

1. Install and activate the plugin.
2. Go to **Dashboard → Telegram Notify → General Settings**.
3. Enable notifications.
4. Add your Telegram Bot Token.
5. Add one or more Chat IDs (comma separated).
6. Choose statuses to notify and save.
7. Use **Send Test Telegram Message** to verify.

## Bot Token / Chat ID Guide

### Telegram Bot Token

1. Open Telegram and start a chat with **@BotFather**.
2. Send **/newbot** and follow the prompts to set a name and username.
3. BotFather will provide an **HTTP API token** — that is your **Telegram Bot Token**.

### Telegram Chat ID

#### Private chat

1. Send a message to your bot (for example: "Hi").
2. Open:
   `https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates`
3. In the JSON response, find `"chat":{"id": ... }` and copy the `id` value.

#### Group / Supergroup

1. Add your bot to the group (grant admin permissions if required).
2. Send a message in the group.
3. Open `getUpdates` again and copy the group chat `id` (often negative, for example `-100xxxxxxxxxx`).

### Multiple Chat IDs

Enter multiple Chat IDs separated by commas, for example:

`123456789,-1001234567890`

## Message Template Placeholders

```
{site_name}
{order_id}
{customer_name}
{phone}
{order_total}
{payment_method}
{order_status}
{order_date}
{order_link}
{products_list}
{quantity}
{shipping_method}
{billing_address}
{coupon_used}
{order_notes}
```
 
## Pro / Future
 
- Advanced Notifications: per‑status templates, admin vs customer toggles, delay (0–5 mins)
- Filters & Conditions: min total, country, payment method, product/category specific
- Rich Message / Buttons: parse mode, inline buttons, optional ordered products list
- Team / Multi‑Bot: multiple bot entries and role/team scaffolding
- Logs & Analytics: rotation controls, basic counters
- AI Message Generator: placeholder feature with test button

## Logs

Telegram responses and errors are appended to:

`wp-content/uploads/woo-telegram-notify/logs.txt`

## Contributors

- ridhwanahsann
- GitHub: https://github.com/ridhwanahsan
