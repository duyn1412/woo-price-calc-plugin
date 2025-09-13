# WordPress Modules Filter Plugin

A WordPress plugin that provides an advanced filter system for modules with accordion-style taxonomy filters, pagination, and smooth loading animations.

## Features

- **Accordion Filter UI**: Collapsible taxonomy filters with plus/minus icons
- **Auto-submit**: Filters update automatically when checkboxes are changed
- **Pagination**: Smart pagination with page numbers, prev/next buttons
- **Loading Effects**: Smooth loading spinners and fade-in animations
- **Responsive Design**: Works on desktop and mobile devices
- **Dark Theme**: Clean dark theme with white text

## Files

- `functions.php` - WordPress functions and shortcode implementation
- `modules-query-filters.js` - JavaScript for filter functionality and API calls
- `style.css` - CSS styles for the filter UI and animations

## Installation

1. Copy the files to your WordPress theme directory
2. Add the shortcode `[modules_filters]` to any page or post
3. Ensure you have the `modules` post type and `industry` taxonomy

## Usage

```php
// Basic usage
[modules_filters]

// With custom parameters
[modules_filters taxonomy="category" post_type="post" per_page="12"]
```

## Shortcode Parameters

- `taxonomy` - Taxonomy to filter by (default: "industry")
- `post_type` - Post type to query (default: "modules")
- `per_page` - Number of items per page (default: 8)
- `orderby` - Order by field (default: "date")
- `order` - Sort order (default: "desc")
- `anchor` - Target element ID (default: "modules-loop")

## Requirements

- WordPress 5.0+
- GeneratePress theme (or compatible theme)
- Custom post type "modules"
- Custom taxonomy "industry"

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## License

MIT License
