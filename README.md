# SRK Pics Quote Pro

SRK Pics Quote Pro is a professional WooCommerce quote system that replaces pricing and checkout with a structured quote request workflow.

Built for real businesses, not demo setups.

---

## 🚀 Core Purpose

Instead of showing prices and allowing checkout, this plugin:

- Hides all product prices
- Removes add-to-cart functionality
- Adds a "Request Quote" system
- Collects customer leads
- Stores them in a CRM-style dashboard
- Sends email notifications
- Allows admin follow-up tracking

---

## ⚙️ Features Overview

### Frontend

- Hide all product prices globally
- Disable cart, quantity, variations, and add-to-cart
- Show "Request Quote" button on:
  - Shop page
  - Category page
  - Search results
  - Archive pages
- Popup quote form
- Automatically includes:
  - Product name
  - Product image
  - Product URL

---

## 🧩 Elementor Support (Single Product)

Single product pages are controlled manually.

You must place the shortcode inside your Elementor template.

---

## 🔹 SHORTCODE USAGE

Use the shortcode to display the quote button anywhere.

---

### Basic Usage (Recommended)

[srk_quote_button]

- Automatically detects the current product
- Best for Elementor single product template

---

### Custom Button Text

[srk_quote_button text="Request a Quote"]

Example:
[srk_quote_button text="Get Price Now"]

---

### Specific Product (Optional)

[srk_quote_button product_id="123"]

Use this when:
- You are outside a product page
- You want to display a quote button for a specific product
- You are building landing pages or custom layouts

---

### Full Custom Example

[srk_quote_button product_id="123" text="Ask for Price"]

---

### Important Notes

- If `product_id` is not provided, the current product is used
- If used outside a product page, `product_id` is required
- Works inside Elementor Shortcode widget

---

## 🧩 Elementor Setup (Step-by-Step)

1. Go to: Elementor → Theme Builder  
2. Open: Single Product Template  
3. Add: Shortcode widget  
4. Paste:

[srk_quote_button]

5. Place it where needed:
   - Below product title  
   - Under description  
   - Inside CTA section  

6. Click Update  

---

## 🎯 Frontend Flow

### Shop / Category Pages

- Products are visible
- Prices are hidden
- No add-to-cart buttons
- Only "Request Quote" button is shown

---

### Single Product Page

- No price
- No cart or purchase options
- Only shortcode-based quote button

---

### Quote Interaction Flow

When user clicks the button:

Popup opens showing:
- Product image
- Product name
- Product URL

Form fields:
- Name
- Email
- Phone
- Message

---

### After Submission

- User sees confirmation message
- Admin receives email
- Customer receives confirmation email
- Data is stored in database
- Activity log is updated

---

## 📩 Email System

### Admin Email Includes

- Product name
- Product image
- Product URL
- Customer name
- Email
- Phone
- Message

---

### Customer Email Includes

- Confirmation message
- Submitted details
- Follow-up notice

---

## 🧠 Admin Dashboard

Go to:

Dashboard → Quote Pro → Quote Requests

---

### Dashboard Features

- View all quote requests
- Product preview (image + name)
- Customer details
- Message preview
- Date tracking

---

### Status Management

Admin can update quote status:

- New
- Waiting for Confirmation
- Call Done
- Quote Sent
- Closed
- Cancelled

---

### Filters

- Filter by status
- Filter by date range

---

### Export

- Export all quote requests as CSV

---

## 📊 Activity Log

Tracks:

- New quote submissions
- Email sending
- Status updates
- System actions

Auto-refresh enabled

---

## ⚙️ Settings

Go to:

Dashboard → Quote Pro → Settings

---

### Available Settings

- Admin notification email

---

## 🔒 What Gets Disabled

- Product prices
- Add to Cart button
- Quantity fields
- Variation purchase UI
- Cart forms

---

## 💡 Best Practice

- Use Elementor single product template
- Place shortcode in a clear CTA position
- Avoid hardcoding product IDs unless needed

---

## 📦 Installation

1. Upload plugin ZIP  
2. Activate plugin  
3. Go to settings and set admin email  
4. Add shortcode in Elementor template  
5. Clear cache  

---

## 🧪 Troubleshooting

### Button not showing

- Ensure shortcode is added
- Check Elementor template assignment
- Clear cache

---

### Popup not opening

- Check browser console
- Disable conflicting popup plugins

---

### Email not sending

- Configure SMTP
- Use WP Mail SMTP plugin

---

## 🔁 Version

Current Version: 1.3.0

---

## 👤 Author

srkpics  
https://srkpics.com/

---

## 📌 Summary

This plugin transforms WooCommerce into a **lead generation system instead of a traditional checkout store**

Ideal for:

- Custom products  
- B2B services  
- High-ticket items  
- Made-to-order businesses  

---
