# SRK Pics Quote Pro

SRK Pics Quote Pro is a WooCommerce quote request plugin that hides product prices and replaces the standard purchase flow with a product-based quote request system.

## Features

- Hide WooCommerce product prices globally
- Disable Add to Cart and cart form
- Show Request Quote button on:
  - Shop page
  - Product category pages
  - Search result pages
  - Product archive pages
- Elementor shortcode support for single product pages
- Popup quote form
- Auto-selected product name, image, and URL
- Customer fields:
  - Name
  - Email
  - Phone
  - Message
- Admin email notification
- Customer confirmation email
- Admin notification email setting
- Modern admin quote dashboard
- Inline quote status update
- Status labels:
  - New
  - Waiting for Confirmation
  - Call Done
  - Quote Sent
  - Closed
  - Cancelled
- Filter quotes by date and status
- Export quote list as CSV
- Live activity log

## Shortcode

Use this shortcode inside the Elementor single product template:

```text
[srk_quote_button]

```text
Custom button text:

[srk_quote_button text="Request a Quote"]

For a specific product ID:

[srk_quote_button product_id="123"]

Custom text with product ID:

[srk_quote_button product_id="123" text="Ask for Price"]
