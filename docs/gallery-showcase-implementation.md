# Gallery Showcase Implementation

## Overview

The horizontal showcase feature creates a deterministic, panel-based photo tour with scroll-snap functionality. Each collection gets a unique but repeatable layout using seeded randomization.

## Architecture

### PHP Classes (src/Gallery/)

1. **XorShift32.php** - Seeded pseudo-random number generator
   - Provides consistent random values for deterministic layouts
   - Methods: `nextFloat()`, `int(min, max)`, `jitter(base, amplitude)`

2. **SlotDef.php** - Defines a photo slot within a panel
   - Properties: x, y, w, h (percentages), z-index, rotation (degrees)

3. **Pattern.php** - Layout pattern definition
   - Contains slot definitions and preferred aspect ratio
   - Each pattern represents a composition style (hero, stack, floating, etc.)

4. **PatternRegistry.php** - Central registry of all layout patterns
   - **CUSTOMIZE HERE** to add/modify layout patterns
   - Currently defines 10 patterns (hero_right, duo_tall, stack_3, etc.)

5. **ShowcaseBuilder.php** - Main builder that generates panels
   - Takes photos + seed key → returns array of panels
   - Implements aspect-ratio-aware pattern selection
   - Applies safe area constraints and jitter

### Twig Templates

1. **templates/stills/album.html.twig** - Main album page
   - Renders horizontal scroll track with panels
   - Uses scroll-snap for smooth navigation

2. **templates/gallery/panels/_photo_panel.html.twig** - Generic photo panel
   - Renders all slots for a pattern

3. **templates/gallery/panels/_slot.html.twig** - Individual photo slot
   - Uses CSS variables for positioning

4. **templates/gallery/panels/_text_card.html.twig** - Text card panel
   - Black card with title + body text

### Stimulus Controller

**assets/controllers/hscroll-gallery_controller.js**
- Wheel → horizontal scroll conversion
- Drag-to-scroll functionality
- Keyboard navigation (arrows, Home, End)
- Reduced-motion support
- Snap-to-panel after drag

### Tests

**tests/Gallery/ShowcaseBuilderTest.php**
- Tests determinism (same seed = same output)
- Tests different seeds produce different layouts
- Validates panel structure and safe area constraints

## How It Works

### 1. Layout Generation

```php
// In StillsController
$panels = $showcaseBuilder->build($photosData, $collection->getSlug());
```

1. Seed is derived from collection slug using `crc32()`
2. XorShift32 RNG is initialized with this seed
3. Photos are classified by aspect ratio (portrait/landscape/square)
4. Patterns are selected based on:
   - Available photo count
   - Photo aspect ratios
   - Random selection from suitable patterns
5. Each pattern's slots get subtle jitter (±2% position, ±2.5% size, ±1.5° rotation)
6. Text cards are occasionally inserted (15% chance)
7. All positions are clamped to safe area:
   - X: 65-96%
   - Y: 18-82%

### 2. Rendering

Panels are rendered as full-viewport divs with scroll-snap:

```twig
<div class="panel w-screen h-screen snap-start">
    {% include '_photo_panel.html.twig' %}
</div>
```

Photos use absolute positioning via CSS variables:

```html
<div style="--x:72%; --y:45%; --w:24%; --h:36%; --r:-1.2deg; --z:2;">
```

### 3. Interaction

The Stimulus controller enhances usability:
- Vertical scroll wheel → horizontal scroll
- Click + drag to scroll
- Arrow keys navigate by 80% viewport
- Home/End jump to start/end
- Respects `prefers-reduced-motion`

## Customization Guide

### Adding New Patterns

Edit `src/Gallery/PatternRegistry.php`:

```php
new Pattern(
    name: 'my_pattern',
    slotsNeeded: 2,  // How many photos this pattern uses
    slotDefs: [
        // Position/size as percentages, rotation in degrees
        new SlotDef(x: 70.0, y: 30.0, w: 20.0, h: 28.0, z: 1, rot: 0.5),
        new SlotDef(x: 78.0, y: 50.0, w: 18.0, h: 24.0, z: 2, rot: -1.0),
    ],
    preferredAspect: 'landscape'  // 'portrait', 'landscape', 'any'
),
```

**Guidelines:**
- Keep compositions in right third (x: 65-92%)
- Maintain safe area (y: 18-82%)
- Use subtle rotations (±2deg max)
- Ensure slots don't overlap too much
- Z-index determines stacking order

### Adjusting Jitter Amounts

Edit `ShowcaseBuilder::createPhotoPanel()`:

```php
$x = $this->clamp($rng->jitter($def->x, 2.0), 65.0, 92.0);  // ±2% position
$w = $this->clamp($rng->jitter($def->w, 2.5), 8.0, 36.0);   // ±2.5% size
$rot = $rng->jitter($def->rot, 1.5);                        // ±1.5deg rotation
```

### Text Card Frequency

Edit `ShowcaseBuilder::build()`:

```php
if (!$lastWasTextCard && $rng->nextFloat() < 0.15 && count($panels) > 0) {
    // 15% chance (0.15) - adjust this value
    $panels[] = $this->createTextCard($rng, $seedKey);
    $lastWasTextCard = true;
}
```

### Adding Text Card Content

Edit `ShowcaseBuilder::createTextCard()`:

```php
$titles = [
    'YOUR TITLE HERE',
    // Add more titles
];

$bodies = [
    'Your description text.',
    // Add more body text
];
```

### Adjusting Safe Area

Edit `ShowcaseBuilder` constants:

```php
// X range (horizontal)
$x = $this->clamp($rng->jitter($def->x, 2.0), 65.0, 92.0);

// Y range (vertical)
$y = $this->clamp($rng->jitter($def->y, 2.0), 18.0, 82.0);
```

### Styling Changes

CSS is in `templates/stills/album.html.twig` in the `{% block head_extra %}` section:

```css
.slot-photo {
    /* Modify shadow/ring here */
    box-shadow: ...;
}
```

Or edit `templates/gallery/panels/_slot.html.twig`:

```html
<img class="shadow-sm ring-1 ring-black/5" />
```

## Visual Reference Compliance

✅ **White background** - `bg-white` on main and panels
✅ **Hairline header** - `border-b border-black/10` on fixed header
✅ **Small uppercase tracking** - `text-[10px] uppercase tracking-[0.3em]`
✅ **Sharp corners** - No `rounded-*` classes on photos
✅ **Subtle shadow/ring** - `shadow-sm ring-1 ring-black/5`
✅ **Minimal overlaps** - Controlled via pattern definitions
✅ **Subtle rotations** - ±1.5deg jitter
✅ **Right-anchored** - All patterns use x: 65-92% range
✅ **Scroll-snap** - `snap-x snap-mandatory` on container
✅ **Full-viewport panels** - `w-screen h-screen` on each panel

## Testing Determinism

Run tests:

```bash
docker compose exec app php bin/phpunit tests/Gallery/ShowcaseBuilderTest.php
```

Expected: All 5 tests pass, confirming deterministic behavior.

## Performance Considerations

- **Lazy loading**: Images use `loading="lazy"` attribute
- **Async decoding**: `decoding="async"` prevents blocking
- **Reduced motion**: Controller respects `prefers-reduced-motion`
- **Smooth scrolling**: Applied only when motion preference allows
- **Minimal DOM**: Only panels in viewport are rendered initially

## Accessibility

- ✅ Keyboard navigation (arrows, Home, End)
- ✅ Reduced motion support
- ✅ Proper ARIA labels on controls
- ✅ Screen reader announcements (view toggle)
- ✅ Focus management
- ✅ Semantic HTML structure

## Browser Compatibility

- Scroll-snap: Supported in all modern browsers
- CSS variables: Widely supported
- Pointer events: Modern browser requirement
- Passive event listeners: Graceful degradation

## Troubleshooting

**Panels not snapping:**
- Check `snap-x snap-mandatory` on container
- Verify `snap-start` on each panel

**Photos not positioned correctly:**
- Verify CSS variables are being set
- Check `.slot-photo` class applies positioning

**Different layout on each page load:**
- Ensure seed key (slug) is consistent
- Check XorShift32 initialization

**Scroll not working:**
- Verify `hscroll-gallery` controller is connected
- Check browser console for errors
- Test with different scroll methods (wheel, drag, keyboard)

## Future Enhancements

Potential improvements:
- Add intro panel with large typography
- Implement progressive image loading
- Add touch gestures for mobile
- Create admin UI for pattern selection
- Add animation on panel entry
- Support video in panels
