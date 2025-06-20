# WordPress Google Tags Scanner

A precise WordPress plugin that scans and identifies Google tracking codes with full snippet preview - perfect for cleaning up before implementing fresh tracking campaigns.

## 🎯 Features

- **Precise Detection** - Shows exact code snippets, not just word matches
- **Full Code Preview** - See exactly what tracking code exists before removal
- **Tracking ID Extraction** - Automatically identifies UA-, G-, GTM-, AW-, and ca-pub- IDs
- **Multiple Scan Locations** - Posts, Pages, Options, Elementor, Theme Files
- **Safety Assessment** - Risk scoring to guide cleanup decisions
- **Zero False Positives** - Advanced regex patterns for accurate detection

## 🚀 Quick Start

1. **Download** or clone this repository
2. **Upload** the plugin folder to `/wp-content/plugins/`
3. **Activate** the plugin in WordPress admin
4. **Navigate** to Tools → Google Tags Scanner
5. **Click** "Start Precise Scan"

## 📦 Installation

### Method 1: Plugin Upload
```bash
# Download the repository
git clone https://github.com/yourusername/wp-google-tags-scanner.git

# Upload to your WordPress site
cp -r wp-google-tags-scanner /path/to/wordpress/wp-content/plugins/
```

### Method 2: Direct File Creation
1. Create folder: `/wp-content/plugins/google-tags-scanner/`
2. Copy `google-tags-scanner.php` into the folder
3. Activate via WordPress admin

## 🔍 What It Detects

The scanner identifies these specific Google tracking implementations:

- **Google Analytics 4 (GA4)** - `G-XXXXXXXXX` tracking IDs
- **Universal Analytics** - `UA-XXXXXXXX-X` tracking IDs  
- **Google Tag Manager** - `GTM-XXXXXXX` container IDs
- **Google Ads/AdWords** - `AW-XXXXXXXXX` conversion IDs
- **Google AdSense** - `ca-pub-XXXXXXXXXXXXXXXX` publisher IDs
- **Google Optimize** - Optimization container codes
- **Legacy Analytics** - analytics.js implementations

## 📊 Sample Results

```
📊 Precise Scan Results
Safety Score: 85%
Total Snippets: 3
Unique IDs: 2

Found Tracking IDs:
[G-ABC123DEF]  [GTM-XYZ789]

📄 Posts/Pages (1 location)
├── Homepage
    └── Google Analytics 4 (247 characters) - ID: G-ABC123DEF
        └── Line: 15 | Position: 1250

🎭 Theme Files (1 file)  
├── header.php (12KB)
    └── Google Tag Manager (156 characters) - ID: GTM-XYZ789
        └── Line: 8 | Position: 340
```

## 🛡️ Safety Guidelines

| Safety Score | Recommendation | Action |
|-------------|---------------|---------|
| 90-100% | ✅ Safe to proceed | Automated cleanup OK |
| 80-89% | ⚠️ Review carefully | Manual review first |
| Below 80% | ❌ High complexity | Staging site testing |

## 🧹 Common Use Cases

### Scenario 1: Agency Handover
Client site has mixed tracking codes from previous agencies:
```
Found: UA-12345678-1 (Old Universal Analytics)
Found: G-ABCDEF123 (Current GA4)  
Found: GTM-WXYZ789 (Unknown GTM container)
```

### Scenario 2: Plugin Leftovers
Deactivated analytics plugins leave database entries:
```
Found: G-FEXY90DBGT (wpcode) - ⚠️ Plugin leftover
Found: UA-87654321-1 (monsterinsights) - ⚠️ Plugin leftover
```

### Scenario 3: Theme Hardcoded
Tracking codes embedded directly in theme files:
```
Found in header.php: Google Analytics 4 implementation
Found in footer.php: Google Tag Manager noscript
```

## 🔧 Advanced Usage

### Scanning Specific Post Types
The scanner automatically detects:
- Posts and Pages
- Custom Post Types  
- Elementor page data
- WPCode/Insert Headers & Footers snippets
- Theme customizer settings

### Elementor Integration
Detects tracking codes in:
- Page-level custom CSS/JS
- Widget settings
- Global Elementor settings
- Theme Builder templates

## 📝 Output Examples

### Code Snippet Preview
```html
<!-- Example detected snippet -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC123DEF"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-ABC123DEF');
</script>
```

### Database Options Found
```
⚙️ WordPress Options (2 locations)
├── google_analytics_code
└── theme_mods_twentytwentythree[header_scripts]
```

## 🚨 Important Notes

- **Always backup** your database before cleanup
- **Test on staging** sites first
- **Read-only scanning** - this plugin only detects, doesn't modify
- **Plugin leftovers** commonly found after analytics plugin removal
- **Theme files** require manual editing via FTP/File Manager

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -am 'Add feature'`
4. Push to branch: `git push origin feature-name`
5. Submit a Pull Request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🔗 Related Tools

- [Google Tag Assistant](https://tagassistant.google.com/) - Verify tracking implementation
- [GTM Debug Console](https://www.google.com/analytics/tag-manager/) - Test Tag Manager setup
- [GA Debugger](https://chrome.google.com/webstore/detail/google-analytics-debugger) - Chrome extension for GA debugging

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/wp-google-tags-scanner/issues)
- **WordPress Forum**: [Plugin Support](https://wordpress.org/support/)
- **Documentation**: [Wiki](https://github.com/yourusername/wp-google-tags-scanner/wiki)

---

**Made for WordPress developers who need precise control over tracking code cleanup** 🎯
