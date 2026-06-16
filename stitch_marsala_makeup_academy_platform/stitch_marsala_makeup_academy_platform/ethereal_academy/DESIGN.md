---
name: Ethereal Academy
colors:
  surface: '#fcf9f9'
  surface-dim: '#dcd9d9'
  surface-bright: '#fcf9f9'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f3'
  surface-container: '#f0eded'
  surface-container-high: '#eae7e7'
  surface-container-highest: '#e4e2e2'
  on-surface: '#1b1b1c'
  on-surface-variant: '#514343'
  inverse-surface: '#303031'
  inverse-on-surface: '#f3f0f0'
  outline: '#847373'
  outline-variant: '#d6c2c2'
  surface-tint: '#845052'
  primary: '#4f2427'
  on-primary: '#ffffff'
  primary-container: '#6a3a3c'
  on-primary-container: '#e7a6a7'
  inverse-primary: '#f9b5b7'
  secondary: '#874f4f'
  on-secondary: '#ffffff'
  secondary-container: '#fdb4b3'
  on-secondary-container: '#794343'
  tertiary: '#582005'
  on-tertiary: '#ffffff'
  tertiary-container: '#743519'
  on-tertiary-container: '#f9a07c'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdada'
  primary-fixed-dim: '#f9b5b7'
  on-primary-fixed: '#340f12'
  on-primary-fixed-variant: '#69393b'
  secondary-fixed: '#ffdad9'
  secondary-fixed-dim: '#fdb4b3'
  on-secondary-fixed: '#360e0f'
  on-secondary-fixed-variant: '#6b3838'
  tertiary-fixed: '#ffdbce'
  tertiary-fixed-dim: '#ffb598'
  on-tertiary-fixed: '#370e00'
  on-tertiary-fixed-variant: '#733518'
  background: '#fcf9f9'
  on-background: '#1b1b1c'
  surface-variant: '#e4e2e2'
  deep-marsala: '#6A3A3C'
  blush-canvas: '#F4ACAB'
  apricot-glow: '#FFA580'
  surface-muted: '#FDF9F8'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
  title-md:
    fontFamily: Hanken Grotesk
    fontSize: 20px
    fontWeight: '500'
    lineHeight: 28px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.08em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 40px
---

## Brand & Style
The brand personality is defined by a "Sophisticated Academic" aesthetic—merging the disciplined structure of a high-end educational institution with the fluid, expressive nature of professional makeup artistry. It targets aspiring and professional artists who value precision and elegance.

The design style is **Corporate Modern with a Minimalist Editorial** influence. It utilizes expansive whitespace, disciplined typography, and a "Tonal Layering" approach to create a sense of calm and focus. The interface should feel like a premium beauty magazine that has been transformed into a functional learning workspace, evoking feelings of inspiration, authority, and artistic growth.

## Colors
The palette is rooted in a deep, authoritative Marsala (`#6A3A3C`) which serves as the anchor for all structural elements and high-hierarchy typography. This is balanced by a soft, skin-tone adjacent Blush (`#F4ACAB`) used for secondary containment and soft categorization.

The Apricot Glow (`#FFA580`) is reserved strictly for high-intent actions (CTAs) and success metrics, ensuring that instructional signposts stand out against the more muted brand tones. Neutral surfaces should lean towards a "warm white" or "bone" finish rather than pure sterile white to maintain the sophisticated, organic feel of the brand.

## Typography
The system employs **Hanken Grotesk** as the primary typeface for its sharp, contemporary geometry and professional clarity. Headlines should use tighter letter-spacing and heavier weights to convey the "Deep Marsala" color with impact.

For technical or academic data—such as course duration, product SKU references, or curriculum metadata—**JetBrains Mono** is introduced in all-caps labels. This monospaced contrast adds the "Academic" rigor to the otherwise soft and artistic visual language, creating a functional distinction between narrative content and technical information.

## Layout & Spacing
The layout follows a **Fixed-Fluid Hybrid** grid. On desktop, content is constrained to a 1280px container to maintain readability, while backgrounds and decorative elements may bleed to the edges. A 12-column system is used with generous 24px gutters to allow the UI to "breathe."

Spacing is built on an 8px base unit. For educational content, vertical rhythm is prioritized: larger gaps (64px+) between sections are encouraged to separate distinct modules of a course, preventing cognitive overload. On mobile, margins reduce to 16px, and complex grids should collapse into a single-column stack with cards occupying the full width minus margins.

## Elevation & Depth
Depth is achieved primarily through **Tonal Layers** and **Ambient Shadows** rather than stark borders. Surfaces use the `surface-muted` tint to create a subtle separation from the background. 

Shadows must be "makeup-inspired"—extremely soft, diffused, and slightly tinted with the Primary color (`#6A3A3C`) at very low opacity (3-5%). This "Deep Tinted Glow" replaces standard gray shadows, making the interface feel warmer and more integrated. Glassmorphism is reserved for navigation overlays and video controls, using a backdrop blur of 12px and 80% opacity of the secondary color.

## Shapes
A **Rounded** (0.5rem base) shape language is applied to balance the sharp typography. This level of roundedness evokes the soft edges of makeup palettes and cosmetic containers. 

Interactive elements like primary buttons and category badges should use larger radii (up to `rounded-xl` or 1.5rem) to feel more inviting and tactile. Progress bars and input fields maintain the standard 0.5rem radius to preserve the professional, structured feel of a learning platform.

## Components
- **Buttons:** Primary buttons use the `apricot-glow` (`#FFA580`) with white or deep marsala text. They feature a soft, tinted shadow that expands slightly on hover. Secondary buttons use an outline of `primary_color` or a solid `blush-canvas` background.
- **Cards:** Course cards use the `surface-muted` background with a subtle 1px border in `blush-canvas`. The image aspect ratio should be 16:9 for video preview consistency.
- **Progress Bars:** The track uses a very light version of the primary color (10% opacity), while the active indicator uses the `apricot-glow` gradient. The bar should be thin (4px-6px) for an "elegant" feel.
- **Badges/Chips:** Used for "Beginner", "Advanced", or "Certified" tags. These use the `blush-canvas` (`#F4ACAB`) background with `primary-color` text in the `label-sm` monospaced font.
- **Input Fields:** Clean, minimal styling with a bottom-border only or a very soft 4-sided border in `blush-canvas`. When active, the border transitions to `primary_color`.
- **Lesson Lists:** Use high-contrast typography for the title and the `label-sm` font for duration, separated by a thin horizontal rule in `blush-canvas`.