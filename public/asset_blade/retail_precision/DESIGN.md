---
name: Retail Precision
colors:
  surface: '#f9f9ff'
  surface-dim: '#d4dae9'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f0f3ff'
  surface-container: '#e8eefd'
  surface-container-high: '#e3e8f7'
  surface-container-highest: '#dde2f1'
  on-surface: '#161c26'
  on-surface-variant: '#44474e'
  inverse-surface: '#2b313c'
  inverse-on-surface: '#ecf1ff'
  outline: '#74777f'
  outline-variant: '#c4c6d0'
  surface-tint: '#485e89'
  primary: '#0a254d'
  on-primary: '#ffffff'
  primary-container: '#243b64'
  on-primary-container: '#90a6d5'
  inverse-primary: '#b0c7f8'
  secondary: '#3b608e'
  on-secondary: '#ffffff'
  secondary-container: '#a8ccff'
  on-secondary-container: '#305683'
  tertiary: '#002c13'
  on-tertiary: '#ffffff'
  tertiary-container: '#004521'
  on-tertiary-container: '#5fb779'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d7e2ff'
  primary-fixed-dim: '#b0c7f8'
  on-primary-fixed: '#001a40'
  on-primary-fixed-variant: '#304670'
  secondary-fixed: '#d3e4ff'
  secondary-fixed-dim: '#a5c9fc'
  on-secondary-fixed: '#001c38'
  on-secondary-fixed-variant: '#214874'
  tertiary-fixed: '#9bf6b2'
  tertiary-fixed-dim: '#80d998'
  on-tertiary-fixed: '#00210d'
  on-tertiary-fixed-variant: '#005228'
  background: '#f9f9ff'
  on-background: '#161c26'
  surface-variant: '#dde2f1'
typography:
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.005em
  title-md:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '500'
    lineHeight: 22px
  body-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  table-dense:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
  currency-display:
    fontFamily: Inter
    fontSize: 22px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-mobile: 0.75rem
  margin: 1.5rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
---

## Brand & Style

This design system is engineered for Indonesian UMKM (Micro, Small, and Medium Enterprises) retail operations—spanning modern warungs, grocery stores (toko kelontong), and specialty retail counters. The aesthetic reflects professional SaaS clarity, operational stability, and utilitarian speed akin to Stripe Dashboard, Toast, and high-reliability core banking interfaces.

The emotional goal is uncompromised confidence, quiet competence, and rapid cognitive recognition under high-stress cashier conditions. The visual style avoids visual noise, glassmorphism, decorative blurs, and neon gradients in favor of structured data density, razor-thin structural dividers, deliberate micro-interactions, and high-readability typography.

## Colors

The palette balances deep corporate navy with high-clarity neutrals and desaturated semantic indicators:

- **Primary (`#243B64`)**: Deep navy blue for core calls-to-action, navigation headers, active states, and dominant visual hierarchy anchors.
- **Secondary (`#5579A8`)**: Muted slate-blue for secondary actions, interactive toggles, active filter outlines, and subtle iconography.
- **Backgrounds**: Base application canvas is set to warm light gray (`#F6F7F9`). Data containers, POS ticket registers, and modal dialogs use pure white (`#FFFFFF`).
- **Typography**: Primary content uses dark charcoal (`#252B36`) with a minimum 7:1 contrast ratio against white surfaces. Supporting labels, table metadata, and inactive states use muted slate gray (`#737B87`).
- **Structural Lines**: All container edges, row dividers, and input strokes use crisp subtle light gray (`#E5E8ED`).
- **Semantic Feedback**:
  - Success / Available: Forest Green (`#1B7A43`) over tinted mist (`#EBF7EE`).
  - Warning / Low Stock / Pending: Warm Amber (`#B57914`) over warm cream (`#FEF7EA`).
  - Danger / Out of Stock / Void: Restrained Red (`#C53030`) over light coral (`#FDECEB`).

## Typography

The type scale strictly enforces tabular clarity and compact density using **Inter**. Oversized marketing displays are prohibited; the maximum application headline tops out at 24px (`headline-lg`) to preserve working screen real estate for order tables and product catalogs.

- **Tabular Figures**: Numeric data, SKU codes, quantities, and Indonesian Rupiah (Rp) amounts must always render with OpenType tabular figures (`font-feature-settings: 'tnum' 1`) to ensure strict vertical column alignment across receipts, inventories, and calculation sheets.
- **Hierarchy Rules**: 
  - Standard interactive forms, modals, and product grids utilize `body-lg` (14px) and `body-md` (14px medium).
  - High-density transaction tables, data tables, and batch order logs scale down to `body-sm` / `table-dense` (13px).
  - Meta tags, status chips, table headers, and timestamp breadcrumbs use `label-md` (12px) and `label-sm` (11px uppercase).

## Layout & Spacing

The layout is built on a responsive 12-column fixed-fluid hybrid grid optimized for retail displays, tablets (common for UMKM cashier stations), and desktop back-offices:

- **Breakpoints**:
  - `mobile` (&lt; 768px): Single column, sticky bottom transaction bar, condensed 0.75rem gutters.
  - `tablet` (768px – 1024px): 8-column layout; split-screen POS mode (60% item catalogue, 40% active cart register).
  - `desktop` (&gt; 1024px): 12-column layout with fixed 240px collateral navigation sidebar, fluid center work area, and dedicated order summary panel.

- **Density Standards**:
  - Use `space-xs` (4px) for badge text padding, icon-to-label gaps, and compact segmented buttons.
  - Use `space-sm` (8px) for table row vertical padding, dropdown menus, and standard input internal vertical padding.
  - Use `space-md` (12px) for input internal horizontal margins and grid card internal spacing.
  - Use `space-lg` (16px) for major component grouping and card boundaries.
  - Use `space-xl` (24px) for page section separators.

## Elevation & Depth

This system avoids layered dropshadows, heavy spreads, and blurred elevation tiers. Depth is achieved via **low-contrast containment** and planar shifts:

- **Border Containment**: All cards, data grids, summary panels, and input fields utilize a crisp 1px structural stroke (`#E5E8ED`) to separate content planes.
- **Card Base**: Pure white surfaces (`#FFFFFF`) sitting over the neutral background (`#F6F7F9`) create clear functional zones without relying on shadows.
- **Surface Elevation**: 
  - Rest State (Cards, Tables, Panels): Flat with 1px border. An optional whisper-light shadow may be applied: `box-shadow: 0 1px 3px 0 rgba(37, 43, 54, 0.04)`.
  - Floating Layers (Dropdowns, Popovers, Modals): Elevated with `box-shadow: 0 4px 12px 0 rgba(37, 43, 54, 0.08)`, retaining the 1px solid border (`#E5E8ED`).
- **Focus & Interaction**: Elements do not lift vertically on hover; instead, interactive states use subtle background fills (`#F0F2F5`) and focused input rings use a 1px border color swap to `#243B64` paired with an accessible 2px offset ring (`rgba(36, 59, 100, 0.12)`).

## Shapes

The geometry uses controlled, restrained radii designed to appear modern yet firmly functional:

- **Cards, Panels, and Modals**: Formed with 8px to 10px corner radius, balancing structure and approachable software ergonomics.
- **Interactive Controls (Buttons, Inputs, Selects)**: Standardized to 6px or 8px corner radius for crisp touch/click targets.
- **Status Badges & Chips**: Formed with 6px radius or fully rounded pill variants where micro tags sit inside dense product tables.
- **Table Wrappers**: Outer borders round out at 8px, while internal cell borders remain crisp 90-degree intersections.

## Components

### Buttons
- **Primary**: Background `#243B64`, text `#FFFFFF`, border none, 8px radius, height 36px (compact) or 40px (standard). Hover state darkens slightly to `#1B2D4D`.
- **Secondary**: Background `#FFFFFF`, text `#252B36`, 1px border `#E5E8ED`, 8px radius. Hover state applies `#F6F7F9`.
- **Tertiary / Ghost**: Transparent background, text `#5579A8`, hover background `#F0F2F5`.
- **Destructive**: Background `#FFFFFF`, text `#C53030`, border 1px solid `#FDECEB`. Active/Hover background `#FDECEB`.

### Form Fields & Inputs
- Height: 36px for dense register configurations, 40px for standard forms.
- Border: 1px solid `#E5E8ED`, background `#FFFFFF`, text `#252B36`, font size 14px.
- Focus: Border shifts to `#243B64` with a 2px outer aura `rgba(36, 59, 100, 0.15)`. No default browser outlines.
- Numeric Input (Rupiah): Prefixed with an integrated uneditable `Rp` tag in `#737B87` inside the left boundary; numeric values right-aligned with tabular figures.

### Status Badges & Chips
- Structure: 4px vertical, 8px horizontal padding; 6px radius; 11px or 12px bold font.
- Variants:
  - *Completed / Paid / Available*: Text `#1B7A43`, background `#EBF7EE`.
  - *Pending / Low Stock*: Text `#B57914`, background `#FEF7EA`.
  - *Failed / Out of Stock*: Text `#C53030`, background `#FDECEB`.
  - *Neutral / Draft*: Text `#737B87`, background `#F0F2F5`.

### Data Tables & Cart Lists
- Row height: 44px for high-density inventory views, 52px for standard product scanning.
- Dividers: Single horizontal line `1px solid #E5E8ED`. No vertical cell dividers.
- Header row: Background `#F6F7F9`, uppercase 11px label font (`#737B87`), 600 weight.
- Row hover: Subtle transition to `#F9FAFB`.

### POS Register Panel & Summary Cards
- Docked cart register features a permanent right-side canvas with a fixed 1px left divider (`#E5E8ED`).
- Cart items render line-by-line with item name (14px medium), quantity modifier controls (compact 28px square buttons), and right-aligned subtotal price.
- Grand total callout uses `currency-display` (22px Inter Semibold) in `#243B64` against a dedicated `#F6F7F9` summary footpad.