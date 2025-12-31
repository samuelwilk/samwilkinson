# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Personal brand site combining developer portfolio (/build) and photography portfolio (/stills) with a distinctive modernist design system. Built with Symfony 7.4, Twig, Tailwind CSS, and dockerized local development.

**Design Philosophy:** Modernist warm minimalism, crafted tech, analog futurism. Think vintage Apple + Frank Lloyd Wright + MCM/Eames aesthetics.

## Issue Tracking with bd (beads)

This project uses **bd** (beads) for AI-native issue tracking. Issues live in `.beads/issues.jsonl` and sync with git.

### Essential Commands

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd create "title"     # Create new issue
bd update <id> --status in_progress  # Claim work
bd close <id>         # Complete work
bd sync               # Sync with git
```

### Critical Session Completion Workflow

When ending ANY work session, you MUST complete ALL steps:

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd sync
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds

## Tech Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| Backend | Symfony 7.4 (PHP 8.3+) | |
| Database | SQLite | Zero overhead for personal portfolio |
| Templates | Twig | Native Symfony templating |
| Styles | Tailwind CSS (standalone CLI) | Zero Node dependencies, 140ms builds |
| JS | Stimulus (via AssetMapper) | Progressive enhancement, native ESM |
| Assets | Symfony AssetMapper | No bundler needed |
| Images | LiipImagineBundle | Responsive variants, lazy generation |
| Admin | EasyAdminBundle | Auto CRUD |
| Container | Docker Compose | app (PHP-FPM) + web (Caddy) |

## Development Commands (Planned)

Once the project is scaffolded, these commands will be available via Makefile:

```bash
make up          # Start containers
make down        # Stop containers
make shell       # Shell into app container
make install     # Composer install
make migrate     # Run migrations
make tailwind    # Watch Tailwind (standalone CLI)
make build-prod  # Minified CSS build
```

## Architecture

### Information Architecture

- `/` - Hero + object cards linking to Build/Stills/Studio
- `/build` - Projects, Skills, Experience, Now/Status
- `/build/{slug}` - Case study detail
- `/stills` - Bookshelf/library of photo albums (Collections)
- `/stills/albums/{slug}` - Open album, film peel interaction, photo viewer
- `/studio` - Posts list (workspace vibe)
- `/studio/{slug}` - Markdown reading mode
- `/admin` - EasyAdmin dashboard (password-protected)

### Data Model (Planned)

**Photo:** filename, title, caption, takenAt, collection, exifData (JSON), isPublished
**Collection:** name, slug, locationName, startDate/endDate, coverPhoto, isRestricted, accessPassword, allowDownloads, visualStyle (JSON)
**Post:** title, slug, content (Markdown), publishedAt, isPublished
**Project:** title, slug, summary, content (Markdown), tags (JSON), url, githubUrl, publishedAt, sortOrder

### Key Components (To Be Implemented)

**Object Cards:** Elevated plane with chamfered corners (`rounded-xl`), warm backgrounds (bone/putty), subtle shadows

**Bookshelf Albums (CSS 3D):** Book spines with unique color/texture per collection, aging effect via sepia + noise, pull-out animation

**Photo Film Peel (Stimulus):** Overlay with acetate texture, rotateX() peel effect, flip to see EXIF on back, keyboard navigation

**Spec Placards:** Museum label aesthetic with hairline borders, monospace numerals, tabular alignment

## Design System

### Color Palette

**Architectural light:** paper (#FFFCF6), daylight (#FAF7F0)
**Warm neutrals:** bone (#F5F1E8), putty (#E8E2D5), oatmeal (#D4CFC0), stone (#9D9786)
**Anchors:** graphite (#3A3A3A), gunmetal (#2C2C2E), ink (#1A1A1A)
**Materials:** walnut (#5C4033), cognac (#9A6324)
**Punch accents:** signal (#D32F2F), teal (#00897B), mustard (#F9A825), persimmon (#E64A19)

**Accent Discipline:** ONE punch color per page/section via CSS variable (`--accent`)

### Typography

- `sans`: Inter (neo-grotesk)
- `display`: Sohne (humanist sans) for labels
- `mono`: JetBrains Mono for values with tabular numerals

**Museum Label Rules:** Labels in display with tight tracking, values in mono with tabular numerals

### Spacing & Radii

- Baseline rhythm: 8px grid
- Chamfer language: cards (`rounded-xl`), panels (`rounded-lg`), controls (`rounded-md`)

### Shadows (Dry & Architectural)

Avoid big soft blurs. Use crisp, subtle shadows:
- Inset panels: `inset 0 2px 4px rgba(0,0,0,0.06)`
- Elevated cards (base): `0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)`
- Elevated cards (hover): `0 4px 6px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.06)`

## Design Principles

1. **Object-first presentation** - Gallery layout, hero objects in space
2. **Museum label clarity** - Spec placards, tight typography, hairline rules
3. **Disciplined playfulness** - One punch accent, tasteful 3D, film peel interaction
4. **Analog futurism** - Warm materials (walnut/cognac), modernist precision, tactile metaphors
5. **Architectural restraint** - Compression/release, negative space, baseline rhythm
6. **Performance as craft** - Economy of motion, reduce-motion support, lazy loading

## Anti-patterns

- No neon gradients, glassmorphism, over-illustrated SaaS, stock blobs
- No harsh brutalism without warmth
- Avoid random radii outside the chamfer language
- Don't add features/refactoring beyond what's requested (avoid over-engineering)

## Accessibility & Performance

**Accessibility:**
- Semantic HTML5 (`<nav>`, `<article>`, `<figure>`)
- ARIA labels for interactive components
- Keyboard navigation (arrow keys in photo viewer, Esc to close)
- Focus trapping in modals
- `prefers-reduced-motion`: disable 3D/animations

**Performance:**
- Lazy loading images (`loading="lazy"`)
- Responsive images via `<picture>` + LiipImagine
- Async Stimulus controllers
- Minimal JS on initial load
- Tailwind purge in production

## Reference Documents

- `/docs/plans/2025-12-29-personal-brand-site-design.md` - Complete design specification
- `/.beads/README.md` - Beads issue tracking documentation
- `/AGENTS.md` - Agent instructions for bd workflow
