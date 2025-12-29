# Studio Three.js Scattered Papers - Design Document

**Date:** 2025-12-29
**Status:** Approved
**Author:** Sam Wilkinson + Claude

---

## Overview

Interactive 3D scene for /studio index page showing blog posts as scattered papers on a desk surface with an oscillating desk fan. Built with vanilla Three.js + Stimulus, integrated seamlessly into existing Symfony/Twig site.

**Reference:** Shopify Supply - how 3D elements integrate into page naturally
**Aesthetic:** Modernist warm minimalism, MCM/Eames "objects in space", vintage Apple craft

---

## Design Vision

**Core Concept:**
Papers scattered on a warm desk surface, viewed from an isometric angle (30-35°). A realistic vintage desk fan in the bottom-right corner gently oscillates, creating subtle wind that makes papers sway. Users can hover/tap papers to inspect them, then click to read the full post.

**Key Principles:**
- **Integration, not immersion** - Lives as a section within page, not fullscreen takeover
- **Restrained interactions** - Gentle, considered movements, not flashy demos
- **Authentic workspace gesture** - How you naturally survey papers on your desk
- **Performance conscious** - Works well on mobile, respects reduced-motion

**What Makes This Unforgettable:**
The moment when you hover over a paper and it responds with physicality but restraint - lifting off the desk, tilting toward you. The subtle fan adding life to an otherwise static scene.

---

## Architecture & Scene Setup

### Page Layout Integration

```
[Studio hero/title section - traditional Twig layout]
    ↓
[Three.js canvas section - ~60-70vh height, max-w-7xl container]
  - Isometric view of desk surface
  - Papers scattered with fan in corner
  - Contained within page flow
    ↓
[Optional: metadata/filtering - traditional Twig layout]
```

### Technical Stack

- **Three.js** - Vanilla, loaded via importmap (no bundler)
- **Stimulus controller** - `studio-papers-controller.js` orchestrates scene
- **Twig template** - Passes post data as JSON via data attribute
- **OrbitControls** - Optional subtle camera manipulation
- **GSAP** - Smooth hover animations (optional, can use Three.js tweening)

### Scene Components

1. **Ground plane** - Warm desk surface (bone/putty gradient material)
2. **Paper meshes** - One PlaneGeometry per blog post with rendered post metadata
3. **Desk fan** - Realistic vintage fan (walnut base, metal cage, spinning blades)
4. **Ambient lighting** - Warm directional + ambient lights matching site aesthetic
5. **Wind force field** - Oscillating force based on fan direction/distance
6. **Raycaster** - Detects hover/click on individual papers

### Data Flow

```
Twig (Post entities)
  → JSON in data-studio-papers-posts attribute
  → Stimulus controller initialize()
  → Create paper meshes with post metadata textures
  → Render loop with wind simulation + interaction states
  → Click → navigate to post URL
```

---

## Scene Composition & Paper Layout

### Camera Configuration

```javascript
// Orthographic camera for isometric view
camera = new THREE.OrthographicCamera(
  frustumSize * aspect / -2,  // left
  frustumSize * aspect / 2,   // right
  frustumSize / 2,            // top
  frustumSize / -2,           // bottom
  0.1, 1000
);

// Position: 35° angle looking down at desk
camera.position.set(0, 5, 8);
camera.lookAt(0, 0, 0);
```

### Paper Distribution Algorithm

Papers scattered using **controlled randomness** - intentionally composed, not chaotic:

- **Spatial distribution**: Poisson disk sampling to prevent overlap while maintaining natural clustering
- **Rotation variation**: Each paper rotated 0-30° randomly (no extreme angles)
- **Elevation layers**: Papers at slightly different heights (0-0.2 units) for subtle depth
- **Aspect ratio**: Papers sized based on post content length (shorter posts = smaller papers)

### Paper Material & Appearance

Each paper is a **textured PlaneGeometry** with:

- **Base material**: Canvas texture with slight aging (warm cream/bone color)
- **Post metadata rendered as texture**:
  - Title (site display font)
  - Date + reading time
  - Excerpt (first 100-150 chars)
  - Rendered using HTML5 Canvas API
- **Typography**: Site fonts (Inter for body, display font for titles)
- **Mustard accents**: Small corner marks, underlines, or ink spots
- **Shadows**: Subtle drop shadow below each paper

**Canvas Texture Rendering:**

```javascript
// Create canvas for each post
canvas = document.createElement('canvas');
canvas.width = 1024;
canvas.height = 1024;
ctx = canvas.getContext('2d');

// Render post metadata
ctx.fillStyle = '#F5F1E8'; // bone background
ctx.fillRect(0, 0, 1024, 1024);

ctx.fillStyle = '#1A1A1A'; // ink text
ctx.font = 'bold 48px Sohne, Inter';
ctx.fillText(post.title, 50, 100);

ctx.font = '24px Inter';
ctx.fillStyle = '#9D9786'; // stone for metadata
ctx.fillText(post.date, 50, 150);

// Convert to Three.js texture
texture = new THREE.CanvasTexture(canvas);
```

### Desk Surface

Simple ground plane with:

- Gradient material (putty → bone → oatmeal)
- Subtle noise texture overlay for authenticity
- Soft ambient occlusion where papers rest
- Large enough to contain all papers with breathing room

### Desk Fan (Bottom-Right Corner)

**Physical model:**
- **Base**: Walnut cylinder (MCM aesthetic)
- **Stand**: Gunmetal/brass metal pole
- **Head**: Metal cylinder housing motor
- **Cage**: Wire cage (simplified geometry - 12-16 segments)
- **Blades**: Cream/bone colored disc with texture suggesting 3 blades
- **Pivot joint**: Group for oscillation animation

**Visual style:** Realistic vintage desk fan (1950s-60s era) matching MCM aesthetic

---

## Interaction & Animation

### Wind Effect (Oscillating Fan)

Continuous gentle force emanating from fan:

```javascript
// Fan oscillation (left-right sweep)
fanAngle = Math.sin(time * 0.4) * 0.6; // ±35° swing, ~3-4 sec cycle
fan.pivot.rotation.y = fanAngle;

// Wind direction based on fan heading
windDirection = new THREE.Vector2(
  Math.cos(fanAngle),
  Math.sin(fanAngle)
);

// Distance-based wind falloff
papers.forEach(paper => {
  distance = paper.position.distanceTo(fan.position);
  strength = Math.max(0, 1 - distance / maxWindRange) * 0.015;

  // Apply rotation sway
  paper.rotation.z += windDirection.x * strength * paper.windSensitivity;

  // Subtle position displacement
  paper.position.x += windDirection.x * strength * 0.3;
  paper.position.y += windDirection.y * strength * 0.3;
});
```

**Wind characteristics:**
- Each paper has random `windSensitivity` (0.5-1.5) for natural variation
- Very small displacement - papers sway ~1-2° rotation
- Closer papers move more, distant papers barely affected
- Creates living, breathing desk without chaos

**Fan blade rotation:**
- Spinning disc texture or actual blade mesh
- Fast rotation (~300 RPM equivalent) with motion blur shader

### Hover State (Desktop)

When mouse enters a paper:

1. **Lift**: Smooth transition up ~0.3 units (equivalent to 20-30px)
2. **Tilt toward camera**: Rotate 8-10° on X-axis
3. **Highlight**: Subtle mustard rim light or glow
4. **Wind dampening**: Reduce wind effect by 70% on hovered paper (stabilizes)
5. **Cursor**: Change to pointer
6. **Z-index**: Bring to front (render order)

```javascript
// Smooth hover animation (GSAP or Three.js tween)
gsap.to(paper.position, {
  y: 0.3,
  duration: 0.4,
  ease: "power2.out"
});

gsap.to(paper.rotation, {
  x: -0.15,
  duration: 0.4,
  ease: "power2.out"
});

// Mustard rim light
paper.material.emissive.setHex(0xF9A825);
paper.material.emissiveIntensity = 0.2;
```

### Click/Tap Interaction

Desktop click or mobile tap:

1. Paper **comes forward** slightly (Z-axis toward camera)
2. Brief pause (0.3s) for visual feedback
3. **Navigate to post URL** (`window.location.href = paper.userData.url`)
4. Optional: Brief "page flip" animation before navigation

**Alternative for advanced interaction:**
- First click: Bring forward + allow mouse drag to rotate/inspect
- Second click or timeout: Navigate to post

### Touch Interactions (Mobile/Tablet)

- **Tap paper**: Trigger hover state (lift + tilt)
- **Tap again** or **tap different paper**: Navigate to tapped post
- **Swipe canvas**: Gentle camera pan (optional, low priority)
- No pinch-zoom (keep it simple)

### Scroll Parallax

As user scrolls page:

- Papers move at different speeds based on Z-depth
- Creates subtle parallax effect
- Wind continues (scene stays "alive" while in viewport)
- Pause render loop when scrolled completely past

---

## Technical Implementation & Performance

### Asset Loading Strategy

```javascript
// Lazy load Three.js only when canvas enters viewport
const observer = new IntersectionObserver((entries) => {
  if (entries[0].isIntersecting) {
    import('three').then(THREE => {
      this.initializeScene(THREE);
    });
  }
});

observer.observe(this.canvasTarget);
```

**Benefits:**
- Doesn't block initial page load
- Three.js only loaded if user scrolls to Studio section
- ~500KB saved for users who don't engage

### Performance Optimizations

1. **Geometry instancing**: Shared PlaneGeometry for all papers (only textures differ)
2. **Texture atlas**: Combine multiple paper textures to reduce draw calls
3. **Dynamic resolution**: Lower canvas resolution on mobile (0.75x devicePixelRatio)
4. **Throttled raycasting**: Mouse move events throttled to 60fps max
5. **Render on demand**: Only render when scene changes (wind, hover, camera move)
6. **Cleanup on disconnect**: Dispose geometries, textures, renderer when navigating away

**Target Performance:**
- 60fps on desktop (modern GPU)
- 30-60fps on mobile (integrated GPU)
- <2MB total assets (Three.js + textures)
- <100ms time to interactive after scroll

### Responsive Strategy

**All devices get 3D scene** (no 2D fallback unless WebGL unavailable):

- **Desktop (>1024px)**:
  - Full scene, all papers visible (~15-20 posts)
  - Mouse hover effects
  - Optional OrbitControls for camera manipulation

- **Tablet (768-1023px)**:
  - Same scene, touch interactions
  - Slightly fewer papers if needed (~12-15)
  - Tap to hover + navigate

- **Mobile (<768px)**:
  - Optimized 3D scene
  - Fewer papers (8-12 most recent posts)
  - Simplified fan geometry (lower poly count)
  - Reduced texture resolution (512x512 instead of 1024x1024)
  - Touch to inspect + navigate
  - Portrait orientation: taller canvas (80vh)

### Browser Support

- **WebGL check** on mount
- **Fallback**: Simple CSS grid of paper cards if WebGL totally unavailable
- **prefers-reduced-motion**: Disable wind, hover animations - static scene

### Stimulus Controller Lifecycle

```javascript
class StudioPapersController extends Controller {
  static targets = ["canvas"];
  static values = { posts: Array };

  connect() {
    this.checkWebGL();
    this.lazyLoadThreeJS();
  }

  lazyLoadThreeJS() {
    // IntersectionObserver → load Three.js → buildScene()
  }

  buildScene(THREE) {
    // Create renderer, camera, lights
    // Create ground plane
    // Create fan model
    // Create paper meshes from posts data
    // Setup raycaster
    // Start render loop
  }

  animate() {
    // Update wind simulation
    // Update fan oscillation
    // Check raycaster for hover
    // Render scene
    requestAnimationFrame(() => this.animate());
  }

  disconnect() {
    // Cleanup: dispose geometries, textures, renderer
    // Cancel animation frame
  }
}
```

---

## Implementation Phases

### Phase 1: Basic Scene Setup
- Stimulus controller scaffold
- Three.js lazy loading
- Isometric camera + ground plane
- Static paper meshes (dummy data)
- Basic lighting

### Phase 2: Paper Rendering
- Canvas texture generation from post data
- Typography rendering (title, date, excerpt)
- Mustard accent styling
- Poisson disk distribution algorithm

### Phase 3: Fan & Wind
- Fan 3D model (simple geometry)
- Oscillation animation
- Wind force field implementation
- Paper sway physics

### Phase 4: Interactions
- Raycaster setup
- Hover states (lift + tilt)
- Click navigation
- Touch event handling

### Phase 5: Polish & Optimization
- Scroll parallax
- Performance profiling
- Mobile optimization
- Reduced-motion support
- WebGL fallback

---

## Success Criteria

- ✅ Scene integrates naturally into page (not fullscreen takeover)
- ✅ Papers feel like physical objects with weight
- ✅ Wind effect is subtle and charming, not distracting
- ✅ Hover/tap interactions feel responsive and delightful
- ✅ 60fps on desktop, 30fps+ on mobile
- ✅ Matches established site aesthetic (warm minimalism)
- ✅ Works on all modern browsers with WebGL
- ✅ Degrades gracefully without WebGL or with reduced-motion

---

## Open Questions

1. **Number of papers**: Show all posts or just most recent 15-20?
2. **OrbitControls**: Allow user to rotate camera, or fixed isometric view?
3. **Loading state**: Simple spinner or animated fan assembly?
4. **Navigation transition**: Instant or brief flip animation?

---

## Reference Links

- Shopify Supply: https://shopify.supply/
- Drake Related Rooms: https://drakerelated.com/rooms/front
- Design Spec: docs/plans/2025-12-29-personal-brand-site-design.md
- Beads Task: samwilkinson-sj3
