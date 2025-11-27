# Leantime UI/UX Customization Architecture Report

**Generated:** November 27, 2025  
**Repository:** leantime-sb (custom-prod branch)  
**Purpose:** Guide for implementing UI/UX customizations in Leantime

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Architecture Overview](#architecture-overview)
3. [Key Files & Directories for UI/UX](#key-files--directories-for-uiux)
4. [Theming System](#theming-system)
5. [Template & View System](#template--view-system)
6. [CSS & Styling Architecture](#css--styling-architecture)
7. [JavaScript & Interactivity](#javascript--interactivity)
8. [Component System](#component-system)
9. [Customization Workflows](#customization-workflows)
10. [Best Practices](#best-practices)

---

## Executive Summary

Leantime uses a **hybrid template system** combining traditional PHP templates and Laravel Blade, with a sophisticated **theming engine** based on CSS variables. The application is transitioning to use **HTMX for asynchronous updates** and **Tailwind CSS** (prefixed with `tw-`) for styling, while maintaining legacy Bootstrap support.

**Key Architecture Points:**
- **Theme-based design** with support for light/dark modes and custom color schemes
- **CSS variable-driven** styling for dynamic theming
- **Domain-driven architecture** where each feature domain contains its own templates, JS, and controllers
- **Webpack (Laravel Mix)** builds frontend assets from LESS and JS files
- **View Composers** prepare data before rendering views
- **Blade components** and shared templates for reusable UI elements

---

## Architecture Overview

### Domain-Driven Structure

As detailed in `CLAUDE.md` (lines 117-135), Leantime follows a domain-driven architecture:

```
app/Domain/{DomainName}/
├── Controllers/          # HTTP endpoints
├── Hxcontrollers/       # HTMX-specific controllers (async endpoints)
├── Services/            # Business logic
├── Repositories/        # Data access
├── Models/              # Data structures
├── Templates/           # View files (.blade.php, .tpl.php)
│   └── partials/       # Reusable template fragments (often for HTMX)
├── Js/                  # Domain-specific JavaScript
└── Composers/           # View data preparation
```

### Core UI Infrastructure

Located in `app/Core/UI/`:
- **Theme.php** - Main theme engine (987 lines) - handles color schemes, fonts, backgrounds
- **Template.php** - Template rendering and view helpers (936 lines)
- **Composer.php** - Abstract base class for view composers
- **Service Providers** - Bootstrap UI services

### Shared Views & Components

Located in `app/Views/`:
- **Templates/layouts/** - Page skeletons (app, blank, entry, registration, error)
- **Templates/components/** - Reusable Blade components
- **Templates/sections/** - Shared sections (header, footer, navigation)
- **Composers/** - Global view composers (App, Entry, Footer, Header, PageBottom)

---

## Key Files & Directories for UI/UX

### Critical Configuration Files

| File | Purpose | Reference |
|------|---------|-----------|
| `webpack.mix.js` | Asset compilation (JS/CSS bundling) | Lines 1-207 |
| `tailwind.config.js` | Tailwind CSS configuration with CSS var integration | Lines 1-100 |
| `package.json` | Frontend dependencies | - |
| `public/theme/*/theme.ini` | Theme metadata & default colors | See below |

### Theme Files

```
public/theme/
├── default/              # Default Leantime theme
│   ├── css/
│   │   ├── light.css    # Light mode CSS variables
│   │   └── dark.css     # Dark mode CSS variables
│   └── theme.ini        # Theme configuration
└── minimal/              # Minimal theme variant
    ├── css/
    │   ├── light.css
    │   └── dark.css
    └── theme.ini
```

**Theme.ini Structure** (from `public/theme/default/theme.ini`):
```ini
[general]
name = "More"
description = "Leantime theme"
version = "1.0.0"
logo = "/dist/images/logo.svg"
primaryColor = "#004666"
secondaryColor = "#00a887"
colorModeSupport = true
colorPickerSupport = true
```

### Asset Source Files

```
public/assets/
├── less/                # LESS source files
│   ├── main.less       # Main stylesheet (imports all CSS libs)
│   ├── app.less        # Application-specific styles
│   └── editor.less     # Editor-specific styles
├── js/
│   └── app/            # Application JavaScript
│       ├── htmx.js             # HTMX configuration
│       ├── htmx-extensions.js  # HTMX extensions
│       ├── app.js              # Main app JS
│       └── core/               # Core JS modules
├── css/
│   ├── components/     # CSS component files
│   └── libs/          # Third-party CSS libraries
└── images/            # Static images
```

### Build Output

```
public/dist/
├── css/
│   ├── main.{version}.min.css
│   ├── app.{version}.min.css
│   └── editor.{version}.min.css
└── js/
    ├── compiled-app.{version}.min.js
    ├── compiled-frameworks.{version}.min.js
    ├── compiled-htmx.{version}.min.js
    └── [other compiled bundles]
```

---

## Theming System

### Theme Core Class

**File:** `app/Core/UI/Theme.php` (987 lines)

**Key Responsibilities:**
1. Theme selection and activation (`getActive()`, `setActive()`)
2. Color scheme management (`getColorScheme()`, `setColorScheme()`)
3. Color mode (light/dark) switching (`getColorMode()`, `setColorMode()`)
4. Font selection (`getFont()`, `setFont()`)
5. Logo management (`getLogoUrl()`)
6. Background customization (`getBackgroundImage()`, `getBackgroundType()`)

**Available Color Schemes** (Theme.php lines 119-130):
```php
private array $colorSchemes = [
    'grayscale1' => [
        'name' => 'Grayscale',
        'primaryColor' => '#000000',
        'secondaryColor' => '#757575',
    ],
    'grayscale2' => [
        'name' => 'Grayscale Reverse',
        'primaryColor' => '#d1d1d1',
        'secondaryColor' => '#000000',
    ],
    'themeDefault' => 'themeDefault',
    'companyColors' => 'companyColors',
];
```

**Font Options** (Theme.php lines 137-141):
```php
public array $fonts = [
    'roboto' => 'Roboto',
    'atkinson' => 'Atkinson Hyperlegible',
    'shantell' => 'Shantell Sans',
];
```

### Theme Application Flow

**Reference:** `app/Views/Composers/Header.php` (lines 41-88)

The Header composer prepares theme variables for every page:

1. **Retrieve theme settings** - Active theme, color mode, color scheme, font
2. **Load colors** - Primary and secondary colors from user settings or defaults
3. **Prepare assets** - CSS and JS URLs for the theme
4. **Set background** - Background image and opacity settings
5. **Pass to view** - All theme data sent to `sections/header.blade.php`

**Applied in:** `app/Views/Templates/sections/header.blade.php` (lines 57-95)

The header blade template dynamically injects:
- Theme CSS files (light or dark based on mode)
- Custom CSS variables for colors (lines 65-72)
- Font family variable (lines 74-77)
- Background image styles (lines 80-95)

### CSS Variable System

**Core Variables** defined in theme CSS files:

**From `public/theme/default/css/light.css`** (lines 1-30):
```css
:root {
    --accent1: hsla(199, 100%, 20%, 1);
    --accent1-hover: #555;
    --accent1-color: #fff;
    
    --accent2: hsla(168, 100%, 33%, 1);
    --accent2-hover: #555;
    --accent2-color: #fff;
    
    --primary-background: rgba(255, 255, 255, 0.8);
    --secondary-background: rgba(255, 255, 255, 1);
    
    --primary-font-color: #333333;
    --secondary-font-color: #000;
    
    --primary-color: var(--accent1);
    --secondary-color: var(--accent2);
    
    /* ... and many more */
}
```

**Categories of CSS Variables:**
- **Colors:** Primary, secondary, accent1, accent2
- **Backgrounds:** Primary, secondary, layered, glass effects
- **Typography:** Font families, sizes (xs, s, m, l, xl, xxl, xxxl)
- **Spacing:** Padding, margin, gaps
- **Borders:** Colors, widths, radius
- **Shadows:** Various shadow definitions
- **Components:** Buttons, dropdowns, menus, modals, tables
- **Z-indexes:** Layer management (--zlayer-1 through --zlayer-6)

**Dynamic Override:** User-selected colors override CSS variables via inline styles in header:
```php
<style id="colorSchemeSetter">
    @foreach ($accents as $accent)
        @if($accent !== false)
            :root {
                --accent{{ $loop->iteration }}: {{{ $accent }}};
            }
        @endif
    @endforeach
</style>
```

---

## Template & View System

### Template Rendering

**Reference:** `CLAUDE.md` (lines 273-291)

Leantime uses a **dual template system**:

1. **PHP Templates (.tpl.php)** - Legacy format, still used in some areas
2. **Blade Templates (.blade.php)** - Modern Laravel Blade engine (preferred)

### Layout Structure

**Main Application Layout:** `app/Views/Templates/layouts/app.blade.php`

**Structure:**
```html
<!DOCTYPE html>
<html>
<head>
    @include('global::sections.header')  <!-- CSS, JS, meta tags -->
    @stack('styles')
</head>
<body hx-ext="preload">
    @include('global::sections.appAnnouncement')
    
    <div class="mainwrapper menu{{ session("menuState") ?? "closed" }}">
        <div class="header">
            <!-- Logo, menu toggles -->
            @include('menu::headMenu')
        </div>
        
        <div class="overlay">
            <div class="leftpanel">
                @include('menu::menu')  <!-- Left sidebar navigation -->
            </div>
            
            <div class="rightpanel {{ $section }}">
                <div class="primaryContent">
                    @isset($action, $module)
                        @include("$module::$action")  <!-- Domain template -->
                    @else
                        @yield('content')
                    @endisset
                    @include('global::sections.footer')
                </div>
            </div>
        </div>
    </div>
    
    @include('global::sections.pageBottom')  <!-- Footer JS, cron -->
    @stack('scripts')
    @include('help::helpermodal')
</body>
</html>
```

**Other Layouts:**
- `layouts/blank.blade.php` - Minimal layout without navigation
- `layouts/entry.blade.php` - Login/authentication pages
- `layouts/registration.blade.php` - User registration flow
- `layouts/error.blade.php` - Error pages

### View Composers

**Reference:** `app/Core/UI/Composer.php` (lines 1-73)

Composers prepare data before views are rendered. They follow this pattern:

```php
namespace Leantime\Views\Composers;

use Leantime\Core\UI\Composer;

class Header extends Composer
{
    public static array $views = [
        'global::sections.header',  // Views this composer applies to
    ];
    
    public function init(/* Dependencies */) {
        // Dependency injection
    }
    
    public function with(): array {
        // Return data array for the view
        return [
            'sitename' => '...',
            'primaryColor' => '...',
            // ...
        ];
    }
}
```

**Registration:** Composers are auto-discovered via `app/Core/UI/ViewsServiceProvider.php` (line 257)

**Shared Composers:**
- `App.php` - Main application data
- `Entry.php` - Login/entry pages
- `Footer.php` - Footer data
- `Header.php` - Header/theme data (most relevant for UI customization)
- `PageBottom.php` - Bottom scripts, cron jobs

### Blade Namespace Convention

**Reference:** `CLAUDE.md` (lines 273-291)

Leantime uses namespace prefixes for includes:

- `global::` - Shared views from `app/Views/Templates/`
- `{domain}::` - Domain-specific views from `app/Domain/{Domain}/Templates/`

**Examples:**
```blade
@include('global::sections.header')
@include('menu::headMenu')
@include('tickets::partials.ticketCard', ['ticket' => $ticket])
```

---

## CSS & Styling Architecture

### Build System

**Reference:** `webpack.mix.js` (lines 171-177)

**LESS Compilation:**
```javascript
.less('./public/assets/less/main.less', `public/dist/css/main.${version}.min.css`)
.less('./public/assets/less/editor.less', `public/dist/css/editor.${version}.min.css`)
.less('./public/assets/less/app.less', `public/dist/css/app.${version}.min.css`)
```

**Main LESS Entry Point:** `public/assets/less/main.less`

Imports all CSS libraries:
```less
// CSS libs
@import "~css/libs/croppie.css";
@import "~css/libs/loading.css";
@import "~css/libs/bootstrap.min.css";
@import "~css/libs/slimselect.min.css";
@import "~css/libs/jquery.ui.css";
@import "~css/libs/fontawesome-free/css/all.css";
// ... many more

/* Components */
@import "~css/components/structure.css";
@import "~css/components/nyroModal.css";
@import "~css/components/dropdowns.css";
```

### Tailwind CSS Integration

**Reference:** `tailwind.config.js` (lines 1-65) & `CLAUDE.md` (lines 493-495)

**Status:** Leantime is transitioning to Tailwind CSS with the `tw-` prefix

**Configuration:**
```javascript
module.exports = {
    content: [
        './app/{Views,Domain,Plugins}/**/*.{tpl,sub,inc,blade}.php',
        './app/Core/Template.php',
        './app/{Views,Domain,Plugins}/**/{Composers,Controllers}/**/*.php',
    ],
    prefix: 'tw-',  // Temporary prefix until bootstrap is removed
    theme: {
        extend: {
            colors: {
                'primary': { DEFAULT: 'var(--primary-color)' },
                'secondary': { DEFAULT: 'var(--secondary-color)' },
            },
            fontSize: {
                'sm': 'var(--font-size-sm)',
                'base': 'var(--base-font-size)',
                'l': 'var(--font-size-l)',
                'xl': 'var(--font-size-xl)',
                // ... all mapped to CSS variables
            },
            padding: {
                'xs': '5px', 'sm': '10px', 'base': '15px',
                'm': '15px', 'l': '20px', 'xl': '30px',
            },
            // ... margin and gap follow same pattern
        },
    },
}
```

**Key Point:** Tailwind classes use CSS variables, maintaining theme consistency!

### Styling Best Practices

**Reference:** `CLAUDE.md` (lines 488-495)

1. **Use CSS Variables:** Always reference existing variables like `var(--primary-color)`
2. **Prefer Tailwind:** For new components, use `tw-` prefixed Tailwind classes
3. **Component CSS:** Domain-specific styles can be in `app/Domain/{Domain}/Templates/`
4. **Maintain Theme Support:** Ensure styles work in both light and dark modes

---

## JavaScript & Interactivity

### Build Configuration

**Reference:** `webpack.mix.js` (lines 58-115)

**JavaScript Bundles Created:**

1. **compiled-htmx** - HTMX library
2. **compiled-htmx-extensions** - HTMX extensions
3. **compiled-app** - Main application JS (includes all domain JS)
4. **compiled-frameworks** - jQuery, Bootstrap
5. **compiled-framework-plugins** - jQuery plugins (UI, chosen, form, tags, etc.)
6. **compiled-global-component** - Global components
7. **compiled-calendar-component** - Calendar functionality
8. **compiled-table-component** - Table/grid functionality
9. **compiled-editor-component** - Rich text editors
10. **compiled-gantt-component** - Gantt chart
11. **compiled-chart-component** - Charts/graphs
12. **compiled-footer** - Footer JS (Prism syntax highlighter)
13. **compiled-lottieplayer** - Lottie animations

### HTMX Architecture

**Reference:** `CLAUDE.md` (lines 298-310)

**Core Concept:** Leantime uses HTMX for asynchronous content updates

**Pattern:**
- Main page controllers load minimal data + shared components
- Content sections use HTMX to load data asynchronously
- HTMX controllers are in `app/Domain/{Domain}/Hxcontrollers/`
- HTMX templates are in `app/Domain/{Domain}/Templates/partials/`

**Example HTMX Controllers Found:**
- `app/Domain/Help/Hxcontrollers/HelperModal.php`
- `app/Domain/Plugins/Hxcontrollers/Details.php`
- `app/Domain/Timesheets/Hxcontrollers/Stopwatch.php`
- `app/Domain/Widgets/Hxcontrollers/MyProjects.php`
- `app/Domain/Menu/Hxcontrollers/ProjectSelector.php`
- `app/Domain/Tickets/Hxcontrollers/TicketCard.php`
- `app/Domain/Projects/Hxcontrollers/ProjectCard.php`

**HTMX in Templates:**
```html
<div hx-get="/tickets/hxcontrollers/ticketCard?id=123" 
     hx-trigger="load"
     hx-swap="innerHTML">
    Loading...
</div>
```

**After Response:** Call `htmx.process()` to initialize new HTMX elements:
```javascript
window.htmx.process('.nyroModalCont');
```

### Domain-Specific JavaScript

**Reference:** `webpack.mix.js` (lines 69-73)

Domain JS files are automatically bundled:
```javascript
...glob.sync("./app/Domain/**/*.js").map(f => `./${f}`)
```

**Structure:**
```
app/Domain/{DomainName}/Js/
└── {domainName}Controller.js
```

**Example Domains with JS:**
- `Dashboard/Js/` - Dashboard interactions
- `Tickets/Js/ticketsController.js` - Ticket management UI
- `Widgets/Js/Widgetcontroller.js` - Widget system
- `Canvas/Js/canvasController.js` - Canvas boards
- `Help/Js/helperController.js` - Help/onboarding

### Core JavaScript Modules

**Reference:** `webpack.mix.js` (lines 61-67) & `public/assets/js/app/core/`

**Core Modules:**
- `app.js` - Main application initialization
- `editors.js` - Rich text editor initialization
- `snippets.js` - Utility functions (theme switching, etc.)
- `modals.js` - Modal dialog handling
- `tableHandling.js` - Table/grid interactions
- `datePickers.js` - Date picker initialization
- `dateHelper.js` - Date formatting utilities

**Theme Switching Example** (from `public/assets/js/app/core/snippets.js`):
```javascript
var toggleColors = function (accent1, accent2) {
    jQuery("#colorSchemeSetter").html(
        ":root { --accent1: "+accent1+"; --accent2: "+accent2+"; }"
    );
};

var toggleFont = function (font) {
    jQuery("#fontStyleSetter").html(
        ":root { --primary-font-family: '"+font+"', 'Helvetica Neue', Helvetica, sans-serif; }"
    );
};
```

### JavaScript Best Practices

**Reference:** `CLAUDE.md` (lines 497-506)

1. **Use HTMX for data requests** - Prefer HTMX over fetch/AJAX for content updates
2. **JavaScript for interactivity** - Use JS for UI interactions (drag-drop, editors)
3. **Fetch API usage:**
   ```javascript
   fetch('/api/jsonrpc', {
       method: 'POST',
       credentials: 'include',
       headers: {
           'Content-Type': 'application/json',
           'X-Requested-With': 'XMLHttpRequest'
       },
       body: JSON.stringify({ /* ... */ })
   })
   ```
4. **Process new HTMX elements:** Call `htmx.process()` after dynamic content injection

---

## Component System

### Blade Components

**Location:** `app/Views/Templates/components/`

**Available Components:**
- `accordion.blade.php` - Collapsible sections
- `badge.blade.php` - Status badges
- `button.blade.php` - Standardized buttons
- `dropdownPill.blade.php` - Dropdown pills
- `emojiinput.blade.php` - Emoji picker input
- `inlineLinks.blade.php` - Inline link lists
- `inlineSelect.blade.php` - Inline select dropdowns
- `loader.blade.php` - Loading spinner
- `loadingText.blade.php` - Loading text animation
- `pageheader.blade.php` - Page header component
- `selectable.blade.php` - Selectable items
- `tabs.blade.php` & `tabs/*` - Tab navigation
- `undrawSvg.blade.php` - Illustration SVGs

**Usage Pattern:**
```blade
<x-button 
    type="primary" 
    size="lg" 
    :disabled="false">
    Click Me
</x-button>

<x-accordion title="Section Title">
    Content here...
</x-accordion>
```

### Reusable Partials

**Pattern:** Shared UI elements that may be used across domains

**Examples from Domain Templates:**
- `tickets::partials.ticketCard` - Ticket display card
- `tickets::partials.timerButton` - Time tracking button
- `tickets::partials.ticketsubmenu` - Ticket actions menu
- `projects::partials.projectCard` - Project card display
- `projects::partials.checklist` - Project checklist
- `widgets::partials.todoItem` - Todo list item
- `menu::partials.projectSelector` - Project selector dropdown

**Usage:**
```blade
@include('tickets::partials.ticketCard', [
    'ticket' => $ticketData,
    'showStatus' => true
])
```

### Creating Custom Components

**For Global Components:**
1. Create `app/Views/Templates/components/mycomponent.blade.php`
2. Use with `<x-mycomponent />` or `@include('global::components.mycomponent')`

**For Domain Components:**
1. Create `app/Domain/{Domain}/Templates/partials/mycomponent.blade.php`
2. Use with `@include('{domain}::partials.mycomponent')`

**For Reusable Cards/Entities:** Consider creating in `Templates/components/` as they may be used in multiple places.

---

## Customization Workflows

### 1. Change Color Scheme

**Files to Modify:**

**Option A: Modify Theme CSS (recommended for global changes)**
- `public/theme/default/css/light.css`
- `public/theme/default/css/dark.css`

**Steps:**
1. Edit CSS variables in `:root` selector
2. Change `--accent1`, `--accent2`, or other color variables
3. Run `npx mix` to rebuild
4. Clear browser cache

**Option B: Modify Theme Defaults**
- `public/theme/default/theme.ini`

**Steps:**
1. Change `primaryColor` and `secondaryColor` hex values
2. Restart application to reload theme configuration

**Option C: Add New Color Scheme**
- `app/Core/UI/Theme.php` (lines 119-130)

**Steps:**
1. Add to `$colorSchemes` array:
   ```php
   'myScheme' => [
       'name' => 'My Custom Scheme',
       'primaryColor' => '#123456',
       'secondaryColor' => '#789ABC',
   ]
   ```
2. Scheme will be available in user settings

### 2. Modify Page Layout

**Files to Modify:**
- `app/Views/Templates/layouts/app.blade.php` (main application)
- `app/Views/Templates/sections/*.blade.php` (header, footer, etc.)

**Examples:**

**Add new menu item:**
- Modify or create domain menu templates
- Reference `app/Domain/Menu/Templates/`

**Change header structure:**
- Edit `app/Views/Templates/layouts/app.blade.php` lines 14-29
- Modify `app/Views/Templates/sections/header.blade.php` for meta/assets

**Adjust sidebar:**
- Edit leftpanel section in `layouts/app.blade.php`
- Modify menu templates in `app/Domain/Menu/Templates/`

### 3. Add Custom Styles

**Recommended Approach:**

**Step 1:** Add custom CSS file
- Create `public/theme/default/css/custom.css`
- Define your custom styles using existing CSS variables

**Step 2:** Register in theme
- Use Theme service events to add custom CSS, or
- Directly modify `app/Views/Composers/Header.php` to include custom stylesheet

**Step 3:** Build
```bash
npx mix
```

**Alternative: Extend LESS**
- Add new LESS file in `public/assets/less/`
- Import in `main.less` or `app.less`
- Build with `npx mix`

### 4. Create Custom Components

**Step 1:** Create Blade component
```php
// app/Views/Templates/components/customcard.blade.php
<div class="tw-rounded-lg tw-shadow-md tw-p-base tw-bg-primary">
    <h3 class="tw-text-xl tw-font-bold">{{ $title }}</h3>
    <div class="tw-mt-sm">
        {{ $slot }}
    </div>
</div>
```

**Step 2:** Use in templates
```blade
<x-customcard title="My Card">
    Card content here
</x-customcard>
```

### 5. Customize Fonts

**Option A: Add to existing font list**

Edit `app/Core/UI/Theme.php` (lines 137-149):
```php
public array $fonts = [
    'roboto' => 'Roboto',
    'atkinson' => 'Atkinson Hyperlegible',
    'shantell' => 'Shantell Sans',
    'myfont' => 'My Custom Font',  // Add here
];

public array $fontTooltips = [
    // ... add tooltip
    'myfont' => 'Description of my custom font',
];
```

**Option B: Include web font**

1. Add font CSS import to `public/assets/less/main.less`:
   ```less
   @import url('https://fonts.googleapis.com/css2?family=MyFont:wght@400;700&display=swap');
   ```

2. Or add font files to `public/assets/fonts/` and import locally

3. Rebuild: `npx mix`

### 6. Implement Dark Mode Customization

**Files:**
- `public/theme/default/css/dark.css`
- `public/theme/default/css/light.css`

**Strategy:** Maintain parallel variable definitions

**Example:**
```css
/* light.css */
:root {
    --my-custom-bg: #ffffff;
    --my-custom-text: #333333;
}

/* dark.css */
:root {
    --my-custom-bg: #1a1a1a;
    --my-custom-text: #eeeeee;
}
```

Then use in components:
```css
.my-element {
    background: var(--my-custom-bg);
    color: var(--my-custom-text);
}
```

### 7. Add Custom JavaScript Functionality

**For Domain-Specific JS:**

1. Create `app/Domain/{YourDomain}/Js/{yourdomain}Controller.js`
2. It will be auto-included in build via `webpack.mix.js` glob pattern
3. Follow existing controller patterns (object with init method)
4. Run `npx mix` to rebuild

**For Global JS:**

1. Add to `public/assets/js/app/core/` directory
2. Import in main bundle via `webpack.mix.js`:
   ```javascript
   .combine([
       // ... existing files
       "./public/assets/js/app/core/mynewfile.js",
   ], `public/dist/js/compiled-app.${version}.min.js`)
   ```
3. Rebuild: `npx mix`

### 8. Create HTMX-Driven Components

**Step 1:** Create Hxcontroller
```php
// app/Domain/{Domain}/Hxcontrollers/MyComponent.php
namespace Leantime\Domain\{Domain}\Hxcontrollers;

use Leantime\Core\Controller\HtmxRequest;

class MyComponent extends HtmxRequest
{
    public function get($params)
    {
        // Fetch data
        $data = $this->myService->getData();
        
        // Return partial view
        return $this->tpl->displayPartial('{domain}::partials.mycomponent', [
            'data' => $data
        ]);
    }
}
```

**Step 2:** Create partial template
```blade
{{-- app/Domain/{Domain}/Templates/partials/mycomponent.blade.php --}}
<div class="my-component">
    @foreach($data as $item)
        <div>{{ $item->name }}</div>
    @endforeach
</div>
```

**Step 3:** Use in main template
```blade
<div hx-get="/{domain}/hxcontrollers/myComponent"
     hx-trigger="load"
     hx-swap="innerHTML">
    <x-loader />
</div>
```

---

## Best Practices

### Development Workflow

**Reference:** `CLAUDE.md` (lines 17-74)

**Local Development:**
```bash
# Start development server (includes hot reloading)
make run-dev

# Build assets for development
make build-dev

# Watch mode (auto-rebuild on changes)
npx mix watch

# Clear cache
make clear-cache
```

**Access Points:**
- Leantime: http://localhost:8090
- MailDev: http://localhost:8081
- phpMyAdmin: http://localhost:8082

### Code Quality

**Run before committing:**
```bash
# Static analysis
make phpstan

# Code style check
make test-code-style

# Fix code style
make fix-code-style
```

### UI/UX Guidelines

**Reference:** `CLAUDE.md` (lines 488-506)

1. **CSS Variables First:** Always use CSS variables for colors, spacing, shadows
2. **Theme Compatibility:** Test in both light and dark modes
3. **Accessibility:** Use semantic HTML, proper ARIA labels
4. **Responsive Design:** Ensure mobile compatibility
5. **Performance:**
   - Use HTMX for data loading (not full page reloads)
   - Lazy load images and heavy components
   - Minimize JavaScript bundle size

### Testing UI Changes

1. **Visual Testing:**
   - Test light and dark modes
   - Test both default and minimal themes
   - Test with different color schemes
   - Test with different fonts
   - Check responsive breakpoints

2. **Functional Testing:**
   - Verify HTMX endpoints work
   - Check JavaScript interactions
   - Test form submissions
   - Validate error states

3. **Browser Testing:**
   - Chrome/Edge
   - Firefox
   - Safari
   - Mobile browsers

### Event System for UI

**Reference:** `CLAUDE.md` (lines 241-258)

Use events to extend UI without modifying core:

**Available UI Events:**
```php
// In header.blade.php
@dispatchEvent('afterMetaTags')
@dispatchEvent('afterLinkTags')
@dispatchEvent('afterScriptLibTags')
@dispatchEvent('afterMainScriptTag')
@dispatchEvent('afterScriptsAndStyles')
@dispatchEvent('afterThemeColors')

// In pageBottom.blade.php
@dispatchEvent('beforeBodyClose')
```

**Hook into events in plugins/custom code:**
```php
EventDispatcher::addFilterListener(
    'afterScriptsAndStyles',
    function($html) {
        return $html . '<link rel="stylesheet" href="/path/to/custom.css">';
    }
);
```

---

## Summary & Quick Reference

### To Change UI, Target These Areas:

| What to Change | Primary Files | Secondary Files |
|----------------|---------------|-----------------|
| **Colors** | `public/theme/default/css/*.css` | `app/Core/UI/Theme.php` |
| **Layout** | `app/Views/Templates/layouts/*.blade.php` | Domain templates |
| **Header/Footer** | `app/Views/Templates/sections/*.blade.php` | `app/Views/Composers/Header.php` |
| **Fonts** | `app/Core/UI/Theme.php`, CSS variables | Theme CSS files |
| **Components** | `app/Views/Templates/components/*.blade.php` | Domain partials |
| **Styles** | `public/assets/less/*.less` | Component CSS files |
| **Scripts** | `app/Domain/*/Js/*.js`, `public/assets/js/app/` | `webpack.mix.js` |
| **Theme Settings** | `public/theme/*/theme.ini` | `app/Core/UI/Theme.php` |

### Build Commands

```bash
# Development
npx mix                    # Build once
npx mix watch             # Watch mode
make build-dev            # Full dev build

# Production
make build                # Production build
make package              # Package for release
```

### Architecture Principles

1. **Domain-Driven:** Each feature domain contains its own UI components
2. **CSS Variables:** All theming uses CSS custom properties
3. **HTMX-First:** Prefer HTMX for async content over JavaScript fetch
4. **Blade Components:** Reusable UI built with Blade components
5. **View Composers:** Separate data preparation from view rendering
6. **Event System:** Use events for extensibility without core modifications

---

## Document References

This report synthesizes information from:
- `CLAUDE.md` (lines 1-553) - Main architecture documentation
- `README.md` (lines 1-294) - Project overview
- `app/Core/UI/Theme.php` (987 lines) - Theme system implementation
- `app/Core/UI/Template.php` (936 lines) - Template rendering
- `app/Core/UI/Composer.php` - View composer pattern
- `app/Views/Composers/Header.php` - Header data preparation
- `app/Views/Templates/layouts/app.blade.php` - Main layout
- `app/Views/Templates/sections/header.blade.php` - Header template
- `webpack.mix.js` (207 lines) - Asset compilation
- `tailwind.config.js` (100 lines) - Tailwind configuration
- `public/theme/default/theme.ini` - Theme metadata
- `public/theme/default/css/*.css` - Theme CSS variables

---

**End of Report**
