# Personal Brand Site - Design Document

**Date:** 2025-12-29
**Status:** Approved
**Author:** Sam Wilkinson + Claude

---

## Overview

A personal brand site combining developer portfolio (/build) and photography portfolio (/stills) with a distinctive modernist design system. Built with Symfony 7.4, Twig, Tailwind CSS, and dockerized local development.

---

## Design Vision

**North Star:**
> Modernist warm minimalism, crafted tech, analog futurism. Quiet confidence, precision, disciplined playfulness. Curated eclectic with "museum label" clarity and object-first presentation.

**Visual References:**
- Vintage Apple (Snow White language, Platinum beige, friendly industrial)
- Frank Lloyd Wright (prairie lines, horizontality, rhythm, compression/release)
- Arthur Erickson (West Coast modern, brutalism softened by landscape, terraces, negative space)
- MCM/Eames (hero objects in space, walnut + aluminum contrast)
- NSX/Bruce Lee (economy of motion, snap-to-grid, cockpit clarity, stealth performance)

**Anti-patterns:**
- No neon gradients, glassmorphism, over-illustrated SaaS, stock blobs, harsh brutalism without warmth

---

## Tech Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| Backend | Symfony 7.4 (PHP 8.3+) | Modern, well-documented, excellent for rapid prototyping |
| Database | SQLite | Zero overhead for personal portfolio, easy backups, trivial migrations |
| Templates | Twig | Native Symfony templating |
| Styles | Tailwind CSS (standalone CLI) | Zero Node dependencies, 140ms builds, design token system |
| JS | Stimulus (via AssetMapper) | Progressive enhancement, native ESM, minimal footprint |
| Assets | Symfony AssetMapper | No bundler needed, importmap simplicity |
| Images | LiipImagineBundle | Responsive variants, lazy generation, Twig helpers |
| Admin | EasyAdminBundle | Auto CRUD, drag-drop uploads, quick to ship |
| Storage | Local filesystem (dev), Flysystem + S3 (prod) | Environment-specific via config |
| Container | Docker Compose | Consistent dev environment |

---

## Docker Architecture

**Services:**
- `app` - PHP 8.3-FPM + Composer + Symfony CLI
- `web` - Caddy (auto-HTTPS locally via self-signed cert)

**Volumes:**
- `./var/data` - SQLite database (persisted)
- `./public/uploads` - Original + generated image variants (bind mount in dev)
- `./var/cache`, `./var/log` - Symfony runtime

**Makefile Commands:**
```makefile
make up          # Start containers
make down        # Stop containers
make shell       # Shell into app container
make install     # Composer install
make migrate     # Run migrations
make tailwind    # Watch Tailwind (standalone CLI)
make build-prod  # Minified CSS build
```

---

## Data Model

### Photo
```
id, filename, title, caption, takenAt (DateTime)
collection (ManyToOne → Collection)
width, height, aspectRatio (calculated on upload)
exifData (JSON: camera, lens, iso, shutter, aperture, focalLength)
isPublished (bool)
uploadedAt, updatedAt
```

### Collection
```
id, name, slug, description
locationName (nullable: "Japan", "Vancouver", etc.)
country (nullable)
startDate, endDate (nullable - for aging effect + sorting)
coverPhoto (ManyToOne → Photo, nullable - first photo if null)
isRestricted (bool)
accessPassword (nullable, hashed - single password per collection)
allowDownloads (bool - for restricted collections)
visualStyle (JSON: spine color, texture, aging seed)
photos (OneToMany ← Photo)
```

**Access Model:**
- Public collections: visible to all
- Restricted collections: book visible on shelf, content requires password
- Password unlocked: session-based, 7-day expiry (configurable via `COLLECTION_AUTH_TTL`)
- Download originals: only if `allowDownloads=true` and authenticated

### Post (Studio)
```
id, title, slug, content (text, Markdown)
publishedAt, updatedAt
isPublished (bool)
```

### Project (Build)
```
id, title, slug, summary, content (Markdown with sections)
tags (JSON array: skills/tools)
url, githubUrl (nullable)
publishedAt, sortOrder
```

---

## Information Architecture

### Public Routes

| Route | Controller::Action | Purpose |
|-------|-------------------|---------|
| `/` | HomeController::index | Hero + object cards linking to Build/Stills/Studio |
| `/build` | BuildController::index | Projects, Skills, Experience, Now/Status |
| `/build/{slug}` | BuildController::show | Case study detail (constraints → decisions → outcomes) |
| `/stills` | StillsController::index | Bookshelf/library of photo albums (Collections) |
| `/stills/albums/{slug}` | StillsController::album | Open album, film peel interaction, photo viewer |
| `/studio` | StudioController::index | Posts list (workspace vibe) |
| `/studio/{slug}` | StudioController::show | Markdown reading mode |

### Admin Routes

| Route | Purpose |
|-------|---------|
| `/admin` | EasyAdmin dashboard (password-protected) |
| `/admin/photos` | CRUD photos, drag-drop upload, assign to collections |
| `/admin/collections` | Manage albums (set password, downloads, visual style) |
| `/admin/posts` | Studio posts |
| `/admin/projects` | Build case studies |

---

## Design System

### Color Palette

```javascript
// Architectural light (page backgrounds)
paper: '#FFFCF6',
daylight: '#FAF7F0',

// Warm neutrals (cards/panels)
bone: '#F5F1E8',
putty: '#E8E2D5',
oatmeal: '#D4CFC0',
stone: '#9D9786',

// Anchors
graphite: '#3A3A3A',
gunmetal: '#2C2C2E',
ink: '#1A1A1A',

// Materials
walnut: '#5C4033',
cognac: '#9A6324',

// Punch accents (ONE per page/section via CSS var)
signal: '#D32F2F',    // signal red
teal: '#00897B',      // deep teal
mustard: '#F9A825',   // mustard
persimmon: '#E64A19', // persimmon
```

**Accent Discipline:**
- Set `--accent: var(--color-signal)` per page
- Components reference CSS variable
- One punch color per page/section (signal architectural intent)

### Typography

```javascript
fontFamily: {
  sans: ['Inter', 'system-ui', 'sans-serif'],        // neo-grotesk
  display: ['Sohne', 'Inter', 'sans-serif'],         // humanist sans
  mono: ['JetBrains Mono', 'Consolas', 'monospace'], // tabular numerals
}
```

**Museum Label Rules:**
- Labels in `display` with tight tracking
- Values in `mono` with tabular numerals (`font-variant-numeric: tabular-nums`)
- Small caps / uppercase sparingly

### Spacing & Radii

- Baseline rhythm: 8px grid (Tailwind defaults)
- Chamfer language (consistent radii):
  - Cards: `rounded-xl` (0.75rem)
  - Panels: `rounded-lg` (0.5rem)
  - Controls: `rounded-md` (0.375rem)
- Avoid random radii outside this ladder

### Surface Patterns

- Subtle noise texture: `background-image: url('/noise.svg')`
- Inset panels: `box-shadow: inset 0 2px 4px rgba(0,0,0,0.06)`
- Hairlines: `border-stone/40` or `border-graphite/20` (avoid 0.5px)
- Ribbed/slatted patterns via repeating-linear-gradient (sparingly)

### Shadows (Dry & Architectural)

```css
/* Inset panels */
box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);

/* Elevated cards (base) */
box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);

/* Elevated cards (hover) */
box-shadow: 0 4px 6px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.06);
```

Avoid big soft blurs (SaaS aesthetic).

---

## Key Components

### Object Cards (Projects, Albums)
- Elevated plane with subtle shadow
- Chamfered corners (`rounded-xl`)
- Warm background (bone/putty)
- Hover: slight lift + shadow increase
- Optional inset caption rail (hairline ruled area for museum label vibe)

### Bookshelf Albums (CSS 3D)
- Book spine with unique color/texture per collection
- Aging effect: sepia filter + noise overlay (scales with `startDate`)
- Spine colors from accent palette (signal/teal/mustard/persimmon)
- One color family per shelf (restrained)
- Pull-out animation on click (restrained perspective)

### Photo Film Peel (Stimulus controller)
- Overlay div with acetate texture
- Hover: `rotateX()` peel effect
- Click: "pull out" photo (scale + z-index)
- Flip to see EXIF on back
- Keyboard navigation (arrow keys, Esc to close)
- Snappy transforms, consistent easing, small angles

### Spec Placards (Build case studies)
- Museum label aesthetic
- Hairline border
- Tight leading, generous padding
- Hierarchy: Title / Role / Constraints / Outcome / Metrics
- Monospace numerals, tabular alignment

---

## Image Pipeline

### Upload (Admin via EasyAdmin + VichUploaderBundle)
1. Drag-drop photo upload
2. Store original in `public/uploads/photos/originals/{filename}`
3. Extract EXIF using `exif_read_data()` or `intervention/image`
4. Calculate width/height/aspectRatio
5. Generate responsive variants (on-demand via LiipImagine)

### Responsive Variants (LiipImagineBundle)

```yaml
# config/packages/liip_imagine.yaml
filter_sets:
  photo_thumb:  { quality: 85, filters: { thumbnail: { size: [400, 400], mode: inset } } }
  photo_sm:     { quality: 85, filters: { thumbnail: { size: [640, 640], mode: inset } } }
  photo_md:     { quality: 85, filters: { thumbnail: { size: [1024, 1024], mode: inset } } }
  photo_lg:     { quality: 85, filters: { thumbnail: { size: [1920, 1080], mode: inset } } }
  photo_xl:     { quality: 90, filters: { thumbnail: { size: [2560, 1440], mode: inset } } }
```

### Twig Template Pattern

```twig
<picture>
  <source srcset="{{ photo.filename | imagine_filter('photo_xl') }}" media="(min-width: 1920px)">
  <source srcset="{{ photo.filename | imagine_filter('photo_lg') }}" media="(min-width: 1024px)">
  <img src="{{ photo.filename | imagine_filter('photo_md') }}" loading="lazy" alt="{{ photo.title }}">
</picture>
```

---

## Accessibility & Performance

**Accessibility:**
- Semantic HTML5 (`<nav>`, `<article>`, `<figure>`)
- ARIA labels for interactive components (film peel, bookshelf)
- Keyboard navigation: arrow keys in photo viewer, Esc to close
- Focus trapping in modals (photo detail view)
- `prefers-reduced-motion`: disable 3D/animations

**Performance:**
- Lazy loading images (`loading="lazy"`)
- Responsive images via `<picture>` + LiipImagine
- Async Stimulus controllers (load on interaction)
- Minimal JS on initial load
- Tailwind purge (production builds)

---

## Optional/Future Features

### 3D "Hero Objects" (Progressive Enhancement)
- Small interactive 3D object in corner/inset panel on key pages (Eames chair, NSX, Iron Giant)
- Lightweight Three.js + GLTF models
- Load asynchronously, respect `prefers-reduced-motion`
- Never tank performance (budget: <200KB total)

### Flipbook Element (Home page intro)
- Short, fast, tactile animation
- "Day-in-the-life objects" or "me intro"
- Stimulus controller, progressive enhancement

### Map (Stills discovery affordance)
- Minimal, not primary navigation
- Leaflet.js or Mapbox GL
- Plot collections by location
- Click to open album

---

## Next Steps (Post-V1)

1. **n8n Integration Hooks:**
   - Webhook endpoint for photo uploads (e.g., from mobile)
   - Auto-create collections from EXIF location data
   - Scheduled backups to S3

2. **Extended 3D Object Library:**
   - Curated collection of "desk objects"
   - Rotate seasonally or per project theme

3. **Advanced Search:**
   - Full-text search across collections, EXIF, captions
   - Filter by camera/lens/date range

4. **Analytics (Privacy-first):**
   - Plausible or self-hosted Matomo
   - Track which projects/collections get most engagement

---

## Design Principles (Summary)

1. **Object-first presentation** - Gallery layout, hero objects in space
2. **Museum label clarity** - Spec placards, tight typography, hairline rules
3. **Disciplined playfulness** - One punch accent, tasteful 3D, film peel interaction
4. **Analog futurism** - Warm materials (walnut/cognac), modernist precision, tactile metaphors
5. **Architectural restraint** - Compression/release, negative space, baseline rhythm
6. **Performance as craft** - Economy of motion, reduce-motion support, lazy loading

---

**End of Design Document**
