# WooCommerce Age Verifier and Tax Plugin

A WordPress plugin that provides age verification and province-based pricing for WooCommerce stores.

## Features

- **Age Gate**: Requires users to verify they are 19+ years old before accessing the site
- **Province-based Pricing**: Different pricing based on Canadian provinces
- **Size-based Tax**: Additional taxes for different product sizes (60ml, 120ml)
- **Product Visibility**: Hide products based on province selection
- **Checkout Integration**: Seamless integration with WooCommerce checkout

## Installation

1. Upload the plugin files to `/wp-content/plugins/woo-price-calc/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings in WooCommerce > Settings > Province Settings

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+

## Configuration

The plugin adds a new "Province Settings" tab in WooCommerce settings where you can configure:

- Taxable categories
- Province-specific tax rates
- Size-based tax rates
- Product visibility rules
- Province-specific product hiding

## Usage

1. Users will see an age gate when first visiting the site
2. They must select their province and verify their age
3. Products will be priced according to their province and size
4. Some products may be hidden based on province rules

## Files

- `woo-price-calc.php` - Main plugin file
- `js/woocommerce-address-handler.js` - JavaScript for checkout integration

## Version

Current version: 5.1

## Author

Block Agency - https://blockagency.co
