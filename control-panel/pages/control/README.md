# Control Panel System

All control panel files are organized in this folder structure:

## Folder Structure

```
pages/control/
├── dashboard.php          # Main dashboard
├── countries.php          # Countries management (wrapper)
├── agencies.php           # Agencies management (wrapper)
├── registration-requests.php  # Registration requests (wrapper)
├── support-chats.php      # Support chats (wrapper)
├── accounting.php         # Accounting system
└── hr.php                 # HR Management

includes/control/
└── sidebar.php            # Unified sidebar navigation

css/control/
└── system.css             # Unified CSS styles

js/control/
└── system.js              # Unified JavaScript
```

## Access URLs

- Dashboard: `/pages/control/dashboard.php?control=1`
- Countries: `/pages/control/countries.php?control=1`
- Agencies: `/pages/control/agencies.php?control=1`
- Registration Requests: `/pages/control/registration-requests.php?control=1`
- Support Chats: `/pages/control/support-chats.php?control=1`
- Accounting: `/pages/control/accounting.php?control=1`
- HR: `/pages/control/hr.php?control=1`

## Features

- Unified sidebar navigation
- Consistent styling across all pages
- Mobile-responsive design
- Modern dark theme with glassmorphism effects
