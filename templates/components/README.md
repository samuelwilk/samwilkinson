# Twig Component Library

Reusable Twig macros following the **modernist warm minimalism** design system.

## Components

### 1. Image Components (`image.html.twig`)

Responsive images with LiipImagine integration.

```twig
{% import 'components/image.html.twig' as img %}

{# Responsive image with srcset #}
{{ img.responsive(photo, 'Alt text', 'w-full h-96 object-cover') }}

{# Simple single-size image #}
{{ img.simple(photo, 'Thumbnail', 'thumb', 'w-24 h-24 rounded') }}

{# Background image div #}
{{ img.background(photo, 'lg', 'h-screen') }}
```

### 2. Typography Components (`typography.html.twig`)

Museum-inspired typography primitives.

```twig
{% import 'components/typography.html.twig' as type %}

{# Museum label (uppercase, tight tracking) #}
{{ type.label('Portfolio 2025', 'text-stone mb-4') }}

{# Museum value (monospace, tabular nums) #}
{{ type.value('2,847', 'text-accent text-2xl') }}

{# Label-value pair #}
{{ type.pair('Year', '2024', 'mb-6') }}

{# Metadata grid #}
{% call type.metadata_grid('grid-cols-2 gap-6') %}
  {{ type.pair('Client', 'Acme Corp') }}
  {{ type.pair('Year', '2024') }}
  {{ type.pair('Role', 'Lead Developer') }}
  {{ type.pair('Duration', '6 months') }}
{% endcall %}

{# Overline label #}
{{ type.overline('Case Study', 'text-accent mb-4') }}

{# Large metric display #}
{{ type.metric('99.9%', 'Uptime', 'mb-8') }}
```

### 3. Placard Components (`placard.html.twig`)

Specification displays for case studies and projects.

```twig
{% import 'components/placard.html.twig' as placard %}

{# Full spec placard #}
{{ placard.spec({
  title: 'E-Commerce Platform Rebuild',
  role: 'Lead Backend Developer',
  constraints: [
    'Legacy data migration',
    'Zero downtime deployment',
    'PCI compliance'
  ],
  outcome: 'Migrated 2M+ records with 99.99% data integrity',
  metrics: {
    'Performance': '+340% faster checkout',
    'Conversion': '+28% increase',
    'Uptime': '99.97% SLA exceeded'
  }
}, 'mb-12') }}

{# Simple info placard #}
{{ placard.info({
  'Technology': 'Symfony 7.4, PostgreSQL',
  'Year': '2024',
  'Status': 'Production'
}, 'mb-8', 3) }}

{# Timeline placard #}
{{ placard.timeline([
  {date: 'Jan 2024', event: 'Discovery & Architecture'},
  {date: 'Feb 2024', event: 'Core Development'},
  {date: 'Mar 2024', event: 'Launch'}
]) }}
```

### 4. Layout Components (`layout.html.twig`)

Structural primitives for consistent layouts.

```twig
{% import 'components/layout.html.twig' as layout %}

{# Inset panel (recessed surface) #}
{% call layout.panel('p-8') %}
  <h3>Panel Content</h3>
{% endcall %}

{# Object card (elevated surface with hover) #}
{% call layout.card('group cursor-pointer', '/build/project-slug') %}
  {{ img.responsive(photo, 'Project', 'w-full h-64 object-cover') }}
  <div class="p-6">
    <h3 class="text-xl mb-2">Project Title</h3>
    <p class="text-graphite">Description...</p>
  </div>
{% endcall %}

{# Grid container #}
{% call layout.grid('grid-cols-1 md:grid-cols-2 lg:grid-cols-3', 'gap-8') %}
  {# Grid items... #}
{% endcall %}

{# Section container #}
{% call layout.section('py-24') %}
  <h2 class="text-4xl mb-12">Section Title</h2>
  {# Section content... #}
{% endcall %}

{# Accent line #}
{{ layout.accent_line('my-12', 'w-24') }}

{# Split layout #}
{{ layout.split_start('gap-12', '2:1') }}
  <div>Main content (2/3 width)</div>
  <div>Sidebar (1/3 width)</div>
{{ layout.split_end() }}

{# Prose container for markdown #}
{% call layout.prose() %}
  {{ content|markdown_to_html|raw }}
{% endcall %}

{# Stack (vertical spacing) #}
{% call layout.stack('space-y-8') %}
  <div>Item 1</div>
  <div>Item 2</div>
{% endcall %}

{# Cluster (horizontal wrapping) #}
{% call layout.cluster('gap-3') %}
  <span class="px-3 py-1 bg-bone rounded">Tag 1</span>
  <span class="px-3 py-1 bg-bone rounded">Tag 2</span>
{% endcall %}
```

## Complete Example: Build Project Card

```twig
{% import 'components/layout.html.twig' as layout %}
{% import 'components/image.html.twig' as img %}
{% import 'components/typography.html.twig' as type %}

{% call layout.card('group', path('app_build_show', {slug: project.slug})) %}
  {# Hero image #}
  <div class="overflow-hidden">
    {{ img.responsive(project.coverPhoto, project.title, 'w-full h-64 object-cover transition-transform group-hover:scale-105') }}
  </div>

  {# Card content #}
  <div class="p-6">
    {# Overline #}
    {{ type.overline('Case Study', 'mb-3') }}

    {# Title #}
    <h3 class="font-display text-2xl text-ink mb-3 leading-tight">
      {{ project.title }}
    </h3>

    {# Description #}
    <p class="text-graphite mb-6">
      {{ project.summary }}
    </p>

    {# Metadata #}
    {{ layout.accent_line('mb-4') }}

    {% call layout.cluster('gap-4') %}
      {{ type.pair('Year', project.year|date('Y'), 'text-sm') }}
      {{ type.pair('Role', project.role, 'text-sm') }}
    {% endcall %}
  </div>
{% endcall %}
```

## Design System Reference

- **Colors**: paper, bone, putty, stone, graphite, ink, signal, teal, mustard, persimmon
- **Fonts**: Inter (sans), Sohne (display), JetBrains Mono (mono)
- **Shadows**: inset-panel, elevated-base, elevated-hover
- **Classes**: object-card, museum-label, museum-value, inset-panel, hairline
