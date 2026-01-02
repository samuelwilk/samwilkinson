import { Controller } from '@hotwired/stimulus';

/**
 * Studio Papers Controller
 *
 * Manages Three.js scattered papers scene for /studio index page.
 * Papers represent blog posts on a desk surface with oscillating fan.
 */
export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        posts: Array
    };

    connect() {
        console.log('Studio papers controller connected');
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.papers = [];
        this.animationId = null;
        this.isIntersecting = false;

        // Detect reduced motion preference
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Detect mobile/tablet for performance optimizations
        this.isMobile = window.innerWidth < 768;
        this.isTablet = window.innerWidth >= 768 && window.innerWidth < 1024;

        // Check WebGL support
        if (!this.checkWebGLSupport()) {
            console.warn('WebGL not supported, showing fallback');
            this.showFallback();
            return;
        }

        // Lazy load Three.js when canvas enters viewport
        this.setupIntersectionObserver();
    }

    checkWebGLSupport() {
        try {
            const canvas = document.createElement('canvas');
            return !!(
                window.WebGLRenderingContext &&
                (canvas.getContext('webgl') || canvas.getContext('experimental-webgl'))
            );
        } catch (e) {
            return false;
        }
    }

    showFallback() {
        // Hide canvas and loading state
        this.canvasTarget.style.display = 'none';
        const loadingEl = this.element.querySelector('[data-studio-papers-loading]');
        if (loadingEl) {
            loadingEl.closest('.absolute').style.display = 'none';
        }

        // Create fallback CSS grid with post cards
        const posts = this.postsValue.length > 0 ? this.postsValue : [];
        const fallbackHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-8 max-w-7xl mx-auto">
                ${posts.map(post => `
                    <a href="${post.url}" class="group block">
                        <div class="bg-bone rounded-lg p-6 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1">
                            <h3 class="font-display text-xl font-medium text-ink mb-2 group-hover:text-mustard transition-colors">
                                ${post.title}
                            </h3>
                            <div class="flex items-center gap-4 text-sm text-stone mb-3">
                                <span>${post.date}</span>
                                <span>•</span>
                                <span>${post.readingTime}</span>
                            </div>
                            ${post.excerpt ? `<p class="text-graphite text-sm leading-relaxed">${post.excerpt}</p>` : ''}
                            ${post.tags && post.tags.length > 0 ? `
                                <div class="flex flex-wrap gap-2 mt-4">
                                    ${post.tags.map(tag => `
                                        <span class="px-2 py-1 text-xs bg-mustard/10 text-walnut rounded">${tag}</span>
                                    `).join('')}
                                </div>
                            ` : ''}
                        </div>
                    </a>
                `).join('')}
            </div>
        `;

        this.element.innerHTML = fallbackHTML;
    }

    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '100px',
            threshold: 0.1
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.scene) {
                    this.isIntersecting = true;
                    this.loadThreeJS();
                } else if (!entry.isIntersecting) {
                    this.isIntersecting = false;
                    this.pauseRendering();
                } else if (entry.isIntersecting && this.scene) {
                    this.isIntersecting = true;
                    this.resumeRendering();
                }
            });
        }, options);

        this.observer.observe(this.canvasTarget);
    }

    async loadThreeJS() {
        try {
            console.log('Loading Three.js...');
            const THREE = await import('three');
            this.THREE = THREE;
            await this.initializeScene();
        } catch (error) {
            console.error('Failed to load Three.js:', error);
        }
    }

    async initializeScene() {
        console.log('Initializing Three.js scene...');

        // Scene setup
        this.scene = new this.THREE.Scene();
        // Match page background for seamless integration
        this.scene.background = new this.THREE.Color(0xFFFCF6); // --color-paper
        this.scene.fog = new this.THREE.Fog(0xFFFCF6, 8, 20); // Subtle depth fog

        // Camera setup (isometric orthographic)
        const aspect = this.canvasTarget.clientWidth / this.canvasTarget.clientHeight;
        const frustumSize = 6; // Reduced from 10 to zoom in closer
        this.camera = new this.THREE.OrthographicCamera(
            frustumSize * aspect / -2,  // left
            frustumSize * aspect / 2,   // right
            frustumSize / 2,             // top
            frustumSize / -2,            // bottom
            0.1,
            1000
        );

        // Position camera at 35° angle looking down
        this.camera.position.set(0, 5, 8);
        this.camera.lookAt(0, 0, 0);

        // Renderer setup with mobile optimizations
        this.renderer = new this.THREE.WebGLRenderer({
            canvas: this.canvasTarget,
            antialias: !this.isMobile, // Disable antialiasing on mobile for performance
            alpha: true, // Enable transparency for seamless integration
            powerPreference: this.isMobile ? 'low-power' : 'high-performance'
        });
        this.renderer.setSize(this.canvasTarget.clientWidth, this.canvasTarget.clientHeight);
        // Lower pixel ratio on mobile for better performance
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, this.isMobile ? 1 : 2));
        this.renderer.shadowMap.enabled = !this.isMobile; // Disable shadows on mobile
        if (!this.isMobile) {
            this.renderer.shadowMap.type = this.THREE.PCFSoftShadowMap;
        }

        // Lighting setup
        this.setupLighting();

        // Create ground plane (desk surface)
        this.createGroundPlane();

        // Create vintage desk fan (Phase 3)
        this.createDeskFan();

        // Create dummy papers for Phase 1
        this.createDummyPapers();

        // Setup raycaster for hover/click detection
        this.raycaster = new this.THREE.Raycaster();
        this.mouse = new this.THREE.Vector2(-999, -999); // Initialize off-screen
        this.hoveredPaper = null;

        // Mouse move handler (bind before adding listener)
        this.mouseMoveHandler = this.onMouseMove.bind(this);
        this.canvasTarget.addEventListener('mousemove', this.mouseMoveHandler, { passive: true });

        // Click handler
        this.clickHandler = this.onClick.bind(this);
        this.canvasTarget.addEventListener('click', this.clickHandler);

        // Touch handlers for mobile/tablet support (passive false to allow conditional preventDefault)
        this.touchStartHandler = this.onTouchStart.bind(this);
        this.touchEndHandler = this.onTouchEnd.bind(this);
        this.touchingPaper = false; // Track if touching a paper
        this.canvasTarget.addEventListener('touchstart', this.touchStartHandler, { passive: false });
        this.canvasTarget.addEventListener('touchend', this.touchEndHandler, { passive: false });

        // Handle window resize
        this.resizeHandler = this.onWindowResize.bind(this);
        window.addEventListener('resize', this.resizeHandler);

        // Scroll parallax (disabled for reduced motion)
        if (!this.prefersReducedMotion) {
            this.scrollHandler = this.onScroll.bind(this);
            window.addEventListener('scroll', this.scrollHandler, { passive: true });
            this.baseScroll = 0;
        }

        // Start render loop
        this.animate();

        // Hide loading state
        const loadingEl = this.element.querySelector('[data-studio-papers-loading]');
        if (loadingEl) {
            const loadingContainer = loadingEl.closest('.absolute');
            if (loadingContainer) {
                loadingContainer.style.opacity = '0';
                setTimeout(() => {
                    loadingContainer.style.display = 'none';
                }, 300);
            }
        }

        console.log('Three.js scene initialized successfully');
    }

    setupLighting() {
        // Warm golden ambient light (like afternoon sunlight)
        const ambientLight = new this.THREE.AmbientLight(0xFFE8C0, 0.7);
        this.scene.add(ambientLight);

        // Main directional light (warm sunlight from top-left)
        const sunLight = new this.THREE.DirectionalLight(0xFFF5E6, 1.2);
        sunLight.position.set(-6, 8, 4);
        sunLight.castShadow = true;
        sunLight.shadow.camera.left = -10;
        sunLight.shadow.camera.right = 10;
        sunLight.shadow.camera.top = 10;
        sunLight.shadow.camera.bottom = -10;
        sunLight.shadow.mapSize.width = 2048;
        sunLight.shadow.mapSize.height = 2048;
        sunLight.shadow.bias = -0.001;
        sunLight.shadow.radius = 3; // Softer shadows
        this.scene.add(sunLight);

        // Mustard accent fill light (from right side)
        const accentLight = new this.THREE.DirectionalLight(0xF9A825, 0.4);
        accentLight.position.set(6, 4, -2);
        this.scene.add(accentLight);

        // Subtle backlight for rim lighting
        const rimLight = new this.THREE.DirectionalLight(0xFFD700, 0.3);
        rimLight.position.set(0, 5, -8);
        this.scene.add(rimLight);

        // Hemisphere light for natural sky/ground bounce
        const hemiLight = new this.THREE.HemisphereLight(
            0xFFF5E6, // sky color - warm cream
            0xD4CFC0, // ground color - oatmeal
            0.5
        );
        this.scene.add(hemiLight);
    }

    createGroundPlane() {
        // Ground plane geometry
        const geometry = new this.THREE.PlaneGeometry(20, 15);

        // Create warm wood desk texture
        const canvas = document.createElement('canvas');
        canvas.width = 1024;
        canvas.height = 1024;
        const ctx = canvas.getContext('2d');

        // Warm wood gradient (walnut tones)
        const gradient = ctx.createRadialGradient(512, 512, 100, 512, 512, 700);
        gradient.addColorStop(0, '#E8DCC8');   // Warm cream center
        gradient.addColorStop(0.4, '#D9CAB3'); // Light wood
        gradient.addColorStop(0.7, '#C9B79C'); // Medium wood
        gradient.addColorStop(1, '#B8A889');   // Darker edges
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, 1024, 1024);

        // Add wood grain texture
        for (let i = 0; i < 200; i++) {
            const x = Math.random() * 1024;
            const y = i * 5 + Math.random() * 10;
            const width = 1024;
            const height = 1 + Math.random() * 2;
            const opacity = 0.02 + Math.random() * 0.03;
            ctx.fillStyle = `rgba(92, 64, 51, ${opacity})`; // walnut
            ctx.fillRect(0, y, width, height);
        }

        // Add fine noise/texture
        for (let i = 0; i < 15000; i++) {
            const x = Math.random() * 1024;
            const y = Math.random() * 1024;
            const size = Math.random() < 0.5 ? 1 : 2;
            const opacity = Math.random() * 0.04;
            const isDark = Math.random() > 0.5;
            ctx.fillStyle = isDark
                ? `rgba(92, 64, 51, ${opacity})`  // walnut
                : `rgba(255, 248, 240, ${opacity})`; // cream highlight
            ctx.fillRect(x, y, size, size);
        }

        const texture = new this.THREE.CanvasTexture(canvas);
        texture.wrapS = texture.wrapT = this.THREE.RepeatWrapping;
        texture.repeat.set(2, 2);

        const material = new this.THREE.MeshStandardMaterial({
            map: texture,
            roughness: 0.85,
            metalness: 0.05,
            envMapIntensity: 0.3
        });

        this.ground = new this.THREE.Mesh(geometry, material);
        this.ground.rotation.x = -Math.PI / 2; // Rotate to horizontal
        this.ground.receiveShadow = true;
        this.scene.add(this.ground);
    }

    createDeskFan() {
        // Create vintage MCM desk fan (1950s-60s aesthetic)
        // Scaled to realistic proportions relative to papers (1.2 x 1.6 units)

        const fanGroup = new this.THREE.Group();
        const scale = 2.5; // Scale factor to make fan realistic size

        // === BASE (Walnut wood cylinder) ===
        const baseGeometry = new this.THREE.CylinderGeometry(0.18 * scale, 0.22 * scale, 0.08 * scale, 32);
        const baseMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x5C4033, // Walnut color
            roughness: 0.7,
            metalness: 0.1
        });
        const base = new this.THREE.Mesh(baseGeometry, baseMaterial);
        base.position.y = 0.04 * scale;
        base.castShadow = true;
        base.receiveShadow = true;
        fanGroup.add(base);

        // === STAND (Gunmetal pole) ===
        const standGeometry = new this.THREE.CylinderGeometry(0.02 * scale, 0.02 * scale, 0.6 * scale, 16);
        const standMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x4A4A4A, // Gunmetal gray
            roughness: 0.4,
            metalness: 0.7
        });
        const stand = new this.THREE.Mesh(standGeometry, standMaterial);
        stand.position.y = 0.38 * scale;
        stand.castShadow = true;
        fanGroup.add(stand);

        // === PIVOT GROUP (for oscillation) ===
        this.fanPivot = new this.THREE.Group();
        this.fanPivot.position.y = 0.68 * scale;
        fanGroup.add(this.fanPivot);

        // === MOTOR HOUSING (Metal cylinder) ===
        const motorGeometry = new this.THREE.CylinderGeometry(0.12 * scale, 0.12 * scale, 0.14 * scale, 32);
        const motorMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x3A3A3A, // Dark gunmetal
            roughness: 0.3,
            metalness: 0.8
        });
        const motor = new this.THREE.Mesh(motorGeometry, motorMaterial);
        motor.rotation.z = Math.PI / 2; // Rotate to horizontal
        motor.castShadow = true;
        this.fanPivot.add(motor);

        // === WIRE CAGE (Simplified with 14 vertical bars) ===
        const cageRadius = 0.2 * scale;
        const cageDepth = 0.12 * scale;
        const numBars = 14;

        // Front ring
        const frontRingGeometry = new this.THREE.TorusGeometry(cageRadius, 0.008 * scale, 8, 32);
        const cageMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x4A4A4A,
            roughness: 0.4,
            metalness: 0.7
        });
        const frontRing = new this.THREE.Mesh(frontRingGeometry, cageMaterial);
        frontRing.position.x = cageDepth / 2;
        frontRing.rotation.y = Math.PI / 2;
        this.fanPivot.add(frontRing);

        // Back ring
        const backRing = new this.THREE.Mesh(frontRingGeometry, cageMaterial);
        backRing.position.x = -cageDepth / 2;
        backRing.rotation.y = Math.PI / 2;
        this.fanPivot.add(backRing);

        // Vertical bars connecting rings
        const barGeometry = new this.THREE.CylinderGeometry(0.006 * scale, 0.006 * scale, cageDepth, 8);
        for (let i = 0; i < numBars; i++) {
            const angle = (i / numBars) * Math.PI * 2;
            const bar = new this.THREE.Mesh(barGeometry, cageMaterial);
            bar.position.set(
                0,
                Math.cos(angle) * cageRadius,
                Math.sin(angle) * cageRadius
            );
            bar.rotation.z = Math.PI / 2;
            this.fanPivot.add(bar);
        }

        // === BLADES (4 flat paddle blades like real vintage fan) ===
        this.fanBlades = new this.THREE.Group();
        this.fanBlades.position.x = 0; // Center of motor housing

        // Blade material (cream/bone colored metal)
        const bladeMaterial = new this.THREE.MeshStandardMaterial({
            color: 0xE8DCC8, // Warm cream
            roughness: 0.6,
            metalness: 0.3,
            side: this.THREE.DoubleSide
        });

        // Create 4 blades (like vintage fans typically have)
        const numBlades = 4;
        for (let i = 0; i < numBlades; i++) {
            const angle = (i / numBlades) * Math.PI * 2;

            // Create trapezoid blade shape - wider at outer edge
            const bladeShape = new this.THREE.Shape();
            const innerWidth = 0.03 * scale; // Width near hub
            const outerWidth = 0.055 * scale; // Width at tip
            const bladeLength = 0.16 * scale; // Extend to near cage

            // Draw trapezoid (wider at far end)
            const bladeStart = 0.02 * scale;
            bladeShape.moveTo(bladeStart, -innerWidth/2); // Inner edge, bottom
            bladeShape.lineTo(bladeStart, innerWidth/2);  // Inner edge, top
            bladeShape.lineTo(bladeLength, outerWidth/2);  // Outer edge, top
            bladeShape.lineTo(bladeLength, -outerWidth/2); // Outer edge, bottom
            bladeShape.lineTo(bladeStart, -innerWidth/2); // Close

            const extrudeSettings = {
                steps: 1,
                depth: 0.002 * scale, // Very thin metal
                bevelEnabled: false
            };

            const bladeGeometry = new this.THREE.ExtrudeGeometry(bladeShape, extrudeSettings);
            const blade = new this.THREE.Mesh(bladeGeometry, bladeMaterial);

            // Rotate blade to correct orientation in Y-Z plane
            blade.rotation.y = Math.PI / 2; // Rotate 90° to lie flat in Y-Z plane
            blade.position.x = -0.001 * scale; // Slight offset for extrude depth

            // Position blade at correct radial angle
            const bladeGroup = new this.THREE.Group();
            bladeGroup.add(blade);
            bladeGroup.rotation.x = angle; // Rotate around X axis

            blade.castShadow = true;
            this.fanBlades.add(bladeGroup);
        }

        // Center hub (smaller, at motor center)
        const hubGeometry = new this.THREE.CylinderGeometry(0.02 * scale, 0.02 * scale, 0.01 * scale, 16);
        const hubMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x5C4033, // Walnut
            roughness: 0.7,
            metalness: 0.1
        });
        const hub = new this.THREE.Mesh(hubGeometry, hubMaterial);
        hub.rotation.z = Math.PI / 2; // Horizontal
        hub.castShadow = true;
        this.fanBlades.add(hub);

        this.fanPivot.add(this.fanBlades);

        // Position fan in right side of scene, moved down and right
        fanGroup.position.set(4.0, 0, -1.5);

        // Rotate fan to face inward toward papers
        // Fan motor points along local +X axis after rotation.z = π/2
        // Need to rotate so +X points from new position toward (0, 0)
        const targetAngle = Math.atan2(1.5, -4.0); // Direction to papers from new position
        fanGroup.rotation.y = targetAngle - Math.PI / 2 + Math.PI; // Adjust for motor orientation + flip 180°

        this.fan = fanGroup;
        this.scene.add(fanGroup);

        // Initialize oscillation state
        this.fanTime = 0;
    }

    createDummyPapers() {
        // Use real posts data if available, otherwise fallback to dummy
        const posts = this.postsValue.length > 0 ? this.postsValue : [
            { title: 'Symfony Service Configuration', date: '2024-12-15', excerpt: 'Exploring autoconfigure and autowire patterns in modern Symfony applications.', readingTime: '8 min read', url: '#' },
            { title: 'Modern PHP Patterns', date: '2024-11-20', excerpt: 'Best practices for PHP 8.3 and beyond.', readingTime: '6 min read', url: '#' },
            { title: 'Tailwind Best Practices', date: '2024-10-05', excerpt: 'Building maintainable utility-first CSS.', readingTime: '5 min read', url: '#' },
            { title: 'Database Optimization', date: '2024-09-12', excerpt: 'Query performance and indexing strategies.', readingTime: '10 min read', url: '#' },
            { title: 'API Design Principles', date: '2024-08-30', excerpt: 'RESTful design patterns that scale.', readingTime: '7 min read', url: '#' },
            { title: 'Testing Strategies', date: '2024-07-18', excerpt: 'Unit, integration, and E2E testing approaches.', readingTime: '9 min read', url: '#' },
            { title: 'Docker Workflows', date: '2024-06-22', excerpt: 'Container-based development environments.', readingTime: '6 min read', url: '#' },
            { title: 'Git Techniques', date: '2024-05-14', excerpt: 'Advanced workflows and collaboration patterns.', readingTime: '8 min read', url: '#' },
        ];

        // Use Poisson disk sampling for natural distribution
        const positions = this.poissonDiskSampling(posts.length, 8, 6, 1.2);

        posts.forEach((post, index) => {
            if (positions[index]) {
                const { x, z } = positions[index];
                const rotation = (Math.random() - 0.5) * 0.52; // ±30° variation (0.52 radians)
                this.createPaper(post, x, z, rotation);
            }
        });
    }

    /**
     * Poisson disk sampling for natural, non-overlapping distribution
     * Returns array of {x, z} positions
     */
    poissonDiskSampling(numPoints, width, height, minDistance) {
        const points = [];
        const cellSize = minDistance / Math.sqrt(2);
        const gridWidth = Math.ceil(width / cellSize);
        const gridHeight = Math.ceil(height / cellSize);
        const grid = Array(gridWidth * gridHeight).fill(null);
        const activeList = [];

        // Helper: Check if point is valid
        const isValid = (x, z) => {
            if (x < -width/2 || x > width/2 || z < -height/2 || z > height/2) return false;

            const gridX = Math.floor((x + width/2) / cellSize);
            const gridZ = Math.floor((z + height/2) / cellSize);

            // Check neighboring cells
            for (let i = -2; i <= 2; i++) {
                for (let j = -2; j <= 2; j++) {
                    const neighborX = gridX + i;
                    const neighborZ = gridZ + j;
                    if (neighborX >= 0 && neighborX < gridWidth && neighborZ >= 0 && neighborZ < gridHeight) {
                        const neighborIndex = neighborZ * gridWidth + neighborX;
                        const neighbor = grid[neighborIndex];
                        if (neighbor) {
                            const dx = neighbor.x - x;
                            const dz = neighbor.z - z;
                            if (Math.sqrt(dx * dx + dz * dz) < minDistance) {
                                return false;
                            }
                        }
                    }
                }
            }
            return true;
        };

        // Helper: Add point to grid
        const addPoint = (x, z) => {
            const point = { x, z };
            points.push(point);
            activeList.push(point);

            const gridX = Math.floor((x + width/2) / cellSize);
            const gridZ = Math.floor((z + height/2) / cellSize);
            grid[gridZ * gridWidth + gridX] = point;
        };

        // Start with random point near center
        addPoint((Math.random() - 0.5) * 2, (Math.random() - 0.5) * 2);

        // Generate points
        while (activeList.length > 0 && points.length < numPoints) {
            const randomIndex = Math.floor(Math.random() * activeList.length);
            const point = activeList[randomIndex];
            let found = false;

            // Try to generate new point around this one
            for (let k = 0; k < 30; k++) {
                const angle = Math.random() * Math.PI * 2;
                const radius = minDistance + Math.random() * minDistance;
                const newX = point.x + Math.cos(angle) * radius;
                const newZ = point.z + Math.sin(angle) * radius;

                if (isValid(newX, newZ)) {
                    addPoint(newX, newZ);
                    found = true;
                    break;
                }
            }

            if (!found) {
                activeList.splice(randomIndex, 1);
            }
        }

        return points;
    }

    createPaper(postData, x, z, rotation) {
        // Paper geometry (A4-ish proportions, scaled down)
        // Reduce segments on mobile for performance
        const width = 1.2;
        const height = 1.6;
        const segments = this.isMobile ? 16 : 32; // Half resolution on mobile
        const geometry = new this.THREE.PlaneGeometry(width, height, segments, segments);

        // Canvas resolution - lower on mobile
        const canvas = document.createElement('canvas');
        const resolution = this.isMobile ? 512 : 1024; // Half resolution on mobile
        canvas.width = resolution;
        canvas.height = Math.floor(resolution * 1.333); // A4 aspect ratio
        const ctx = canvas.getContext('2d');

        // Warm aged paper background with vignette
        const bgGradient = ctx.createRadialGradient(512, 682, 200, 512, 682, 900);
        bgGradient.addColorStop(0, '#FFFEF8');   // Warm white center
        bgGradient.addColorStop(0.7, '#FAF6ED'); // Cream
        bgGradient.addColorStop(1, '#F0EAD6');   // Aged edges
        ctx.fillStyle = bgGradient;
        ctx.fillRect(0, 0, 1024, 1365);

        // Add paper texture/aging
        for (let i = 0; i < 3000; i++) {
            const x = Math.random() * 1024;
            const y = Math.random() * 1365;
            const size = Math.random() < 0.8 ? 1 : 2;
            const opacity = Math.random() * 0.04;
            ctx.fillStyle = `rgba(180, 160, 120, ${opacity})`;
            ctx.fillRect(x, y, size, size);
        }

        // Subtle coffee stains (random placement)
        if (Math.random() > 0.5) {
            const stainX = 100 + Math.random() * 824;
            const stainY = 200 + Math.random() * 900;
            const stainGrad = ctx.createRadialGradient(stainX, stainY, 0, stainX, stainY, 40);
            stainGrad.addColorStop(0, 'rgba(160, 120, 80, 0.08)');
            stainGrad.addColorStop(1, 'rgba(160, 120, 80, 0)');
            ctx.fillStyle = stainGrad;
            ctx.fillRect(stainX - 40, stainY - 40, 80, 80);
        }

        // Mustard highlight accent (diagonal stripe in corner)
        const highlightGrad = ctx.createLinearGradient(0, 0, 200, 200);
        highlightGrad.addColorStop(0, 'rgba(249, 168, 37, 0.12)');
        highlightGrad.addColorStop(0.5, 'rgba(249, 168, 37, 0.08)');
        highlightGrad.addColorStop(1, 'rgba(249, 168, 37, 0)');
        ctx.fillStyle = highlightGrad;
        ctx.fillRect(0, 0, 300, 300);

        // Title with better typography
        ctx.fillStyle = '#1A1A1A'; // --color-ink
        ctx.font = '700 68px Inter, sans-serif';
        ctx.textAlign = 'left';
        ctx.letterSpacing = '-0.02em';
        this.wrapText(ctx, postData.title, 60, 120, 904, 80);

        // Mustard underline beneath title
        const titleLines = Math.ceil(ctx.measureText(postData.title).width / 904) || 1;
        const underlineY = 120 + (titleLines * 80) + 20;
        ctx.strokeStyle = '#F9A825'; // --color-mustard
        ctx.lineWidth = 4;
        ctx.globalAlpha = 0.6;
        ctx.beginPath();
        ctx.moveTo(60, underlineY);
        ctx.lineTo(280, underlineY);
        ctx.stroke();
        ctx.globalAlpha = 1.0;

        // Date with small-caps style
        ctx.fillStyle = '#5C4033'; // --color-walnut
        ctx.font = '600 32px Inter, sans-serif';
        ctx.letterSpacing = '0.1em';
        ctx.fillText(postData.date.toUpperCase(), 60, underlineY + 60);

        // Reading time
        if (postData.readingTime) {
            ctx.fillStyle = '#9D9786'; // --color-stone
            ctx.font = '500 28px Inter, sans-serif';
            ctx.letterSpacing = '0';
            ctx.fillText(postData.readingTime, 60, underlineY + 110);
        }

        // Excerpt (if available)
        if (postData.excerpt) {
            ctx.fillStyle = '#3A3A3A'; // --color-graphite
            ctx.font = '400 32px Inter, sans-serif';
            ctx.letterSpacing = '0';
            ctx.lineHeight = 1.6;
            const excerptY = underlineY + 170;
            this.wrapText(ctx, postData.excerpt, 60, excerptY, 904, 48);
        }

        // Tags (if available)
        if (postData.tags && postData.tags.length > 0) {
            const tagsY = underlineY + (postData.excerpt ? 400 : 200);
            ctx.fillStyle = '#9D9786'; // --color-stone
            ctx.font = '500 24px Inter, sans-serif';
            ctx.fillText('Tags:', 60, tagsY);

            ctx.fillStyle = '#F9A825'; // --color-mustard
            ctx.font = '600 24px Inter, sans-serif';
            const tagsText = postData.tags.slice(0, 3).join(' • '); // Max 3 tags
            ctx.fillText(tagsText, 140, tagsY);
        }

        // Subtle folded corner effect (top-right)
        ctx.fillStyle = 'rgba(0, 0, 0, 0.08)';
        ctx.beginPath();
        ctx.moveTo(1024, 0);
        ctx.lineTo(1024, 80);
        ctx.lineTo(944, 0);
        ctx.closePath();
        ctx.fill();

        // Create texture with better filtering
        const texture = new this.THREE.CanvasTexture(canvas);
        texture.minFilter = this.THREE.LinearFilter;
        texture.magFilter = this.THREE.LinearFilter;
        texture.anisotropy = 4;

        // Warmer paper material
        const material = new this.THREE.MeshStandardMaterial({
            map: texture,
            roughness: 0.9,
            metalness: 0.02,
            side: this.THREE.DoubleSide,
            emissive: new this.THREE.Color(0xFFFAF0),
            emissiveIntensity: 0.1 // Slight self-illumination for warmth
        });

        // Create mesh
        const paper = new this.THREE.Mesh(geometry, material);
        paper.position.set(x, 0.02 + Math.random() * 0.08, z); // More elevation variation
        paper.rotation.x = -Math.PI / 2; // Rotate to horizontal
        paper.rotation.z = rotation;
        paper.castShadow = true;
        paper.receiveShadow = true;

        // Store original vertex positions for deformation
        const positions = geometry.attributes.position.array;
        const originalPositions = new Float32Array(positions.length);
        originalPositions.set(positions);

        // Store metadata including URL for navigation
        paper.userData = {
            postData,
            url: postData.url || '#',
            originalY: paper.position.y,
            originalRotation: { x: paper.rotation.x, z: paper.rotation.z },
            originalPositions: originalPositions,
            deformProgress: 0,
            windSensitivity: 0.5 + Math.random() * 1.0, // Random 0.5-1.5 for variation
            originalPosition: { x: paper.position.x, y: paper.position.y, z: paper.position.z }
        };

        this.papers.push(paper);
        this.scene.add(paper);
    }

    wrapText(ctx, text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        let currentY = y;

        words.forEach(word => {
            const testLine = line + word + ' ';
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && line !== '') {
                ctx.fillText(line, x, currentY);
                line = word + ' ';
                currentY += lineHeight;
            } else {
                line = testLine;
            }
        });
        ctx.fillText(line, x, currentY);
    }

    onMouseMove(event) {
        if (!this.canvasTarget) return;

        const rect = this.canvasTarget.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    }

    onTouchStart(event) {
        if (!this.canvasTarget || event.touches.length !== 1) return;

        const touch = event.touches[0];
        const rect = this.canvasTarget.getBoundingClientRect();
        this.mouse.x = ((touch.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((touch.clientY - rect.top) / rect.height) * 2 + 1;

        // Update hover state immediately for touch
        this.checkHover();

        // Store if we're touching a paper (for touchEnd)
        this.touchingPaper = !!this.hoveredPaper;

        // Only prevent default if touching a paper (allow normal scrolling otherwise)
        if (this.touchingPaper) {
            event.preventDefault();
        }
    }

    onTouchEnd(event) {
        // Only handle tap if we started on a paper
        if (this.touchingPaper && this.hoveredPaper && this.hoveredPaper.userData.url) {
            event.preventDefault();
            window.location.href = this.hoveredPaper.userData.url;
        }

        // Reset touch state
        this.touchingPaper = false;
    }

    onClick(event) {
        // Click to navigate
        if (this.hoveredPaper && this.hoveredPaper.userData.url) {
            window.location.href = this.hoveredPaper.userData.url;
        }
    }

    checkHover() {
        if (!this.raycaster || !this.camera || !this.papers || this.papers.length === 0) return;

        // Skip if mouse hasn't moved yet
        if (this.mouse.x === -999 && this.mouse.y === -999) return;

        // Update raycaster with mouse position
        this.raycaster.setFromCamera(this.mouse, this.camera);

        // Check for intersections with papers (recursive = false for better performance)
        const intersects = this.raycaster.intersectObjects(this.papers, false);

        if (intersects.length > 0) {
            const paper = intersects[0].object;

            // If hovering a new paper
            if (this.hoveredPaper !== paper) {
                // Reset previous hovered paper
                if (this.hoveredPaper) {
                    this.resetPaper(this.hoveredPaper);
                }

                // Set new hovered paper
                this.hoveredPaper = paper;
                this.hoverPaper(paper);

                // Change cursor
                this.canvasTarget.style.cursor = 'pointer';
            }
        } else {
            // No paper hovered
            if (this.hoveredPaper) {
                this.resetPaper(this.hoveredPaper);
                this.hoveredPaper = null;
                this.canvasTarget.style.cursor = 'default';
            }
        }
    }

    hoverPaper(paper) {
        if (!paper || !this.THREE) return;

        // Lift paper up - higher to account for gravity droop in middle
        const targetY = paper.userData.originalY + 0.28;

        // Animate using simple interpolation
        if (!paper.userData.animating) {
            paper.userData.animating = true;
            this.animatePaperHover(paper, targetY, true);
        }

        // Add mustard glow
        if (paper.material) {
            paper.material.emissive.setHex(0xF9A825);
            paper.material.emissiveIntensity = 0.15;
        }
    }

    resetPaper(paper) {
        if (!paper || !this.THREE) return;

        // Return to original position
        const targetY = paper.userData.originalY;

        // Always animate back, even if currently animating
        paper.userData.animating = true;
        this.animatePaperHover(paper, targetY, false);

        // Remove glow
        if (paper.material) {
            paper.material.emissive.setHex(0xFFFAF0);
            paper.material.emissiveIntensity = 0.1;
        }
    }

    deformPaper(paper, progress) {
        if (!paper.geometry || !paper.userData.originalPositions) return;

        const positions = paper.geometry.attributes.position.array;
        const original = paper.userData.originalPositions;
        const width = 1.2;
        const height = 1.6;

        // Deform each vertex to simulate flexible paper bending when lifted
        for (let i = 0; i < positions.length; i += 3) {
            const x = original[i];
            const y = original[i + 1];
            const z = original[i + 2];

            // Normalized coordinates (-0.5 to 0.5)
            const nx = x / width;
            const ny = y / height;

            // Create gravity droop - middle sags DOWN when grabbed from top
            // Inverted parabola: 0 at top (grabbed), negative in middle (droops), 0 at bottom
            const normalizedY = ny + 0.5; // 0 to 1 (bottom to top)
            const gravityDroop = -Math.sin(normalizedY * Math.PI) * 0.08; // Middle droops down

            // Width curl - sides droop down naturally
            const widthCurl = Math.pow(Math.abs(nx), 2.5) * -0.04; // Stronger curl at edges

            // Top edge lift (being grabbed and pulled up)
            const topEdgeLift = normalizedY > 0.7
                ? Math.pow((normalizedY - 0.7) / 0.3, 2) * 0.12 // Top lifts up
                : 0;

            // Subtle wave/ripple effect (paper flexing)
            const ripple = Math.sin(normalizedY * 4 + nx * 3 + progress * 3) * 0.006;

            // Combine all deformations
            const deformation = (gravityDroop + widthCurl + topEdgeLift + ripple) * progress;

            // Apply deformation to Z (up/down)
            positions[i] = x;
            positions[i + 1] = y;
            positions[i + 2] = z + deformation;
        }

        paper.geometry.attributes.position.needsUpdate = true;
        paper.geometry.computeVertexNormals(); // Recompute normals for proper lighting
    }

    animatePaperHover(paper, targetY, isHovering) {
        const startY = paper.position.y;
        const startRotX = paper.rotation.x;
        const targetRotX = isHovering
            ? paper.userData.originalRotation.x + 0.25 // Reduced tilt for flexible paper (~14 degrees)
            : paper.userData.originalRotation.x;

        const startDeform = paper.userData.deformProgress;
        const targetDeform = isHovering ? 1.0 : 0.0;

        // Different durations: quick lift, slow float down (air resistance)
        // Shorter durations for reduced motion
        const baseDuration = this.prefersReducedMotion ? 150 : (isHovering ? 350 : 650);
        const duration = baseDuration;
        const startTime = Date.now();

        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function - different curves for up vs down
            let eased;
            if (isHovering) {
                // Quick responsive lift (ease-out cubic)
                eased = 1 - Math.pow(1 - progress, 3);
            } else {
                // Slow floating drop with air resistance (ease-out quartic - gentler)
                eased = 1 - Math.pow(1 - progress, 4);
            }

            // Interpolate position and rotation
            paper.position.y = startY + (targetY - startY) * eased;
            paper.rotation.x = startRotX + (targetRotX - startRotX) * eased;

            // Interpolate deformation
            paper.userData.deformProgress = startDeform + (targetDeform - startDeform) * eased;
            this.deformPaper(paper, paper.userData.deformProgress);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                paper.userData.animating = false;
            }
        };

        animate();
    }

    animate() {
        if (!this.isIntersecting) return;

        this.animationId = requestAnimationFrame(() => this.animate());

        // Update fan oscillation and blade rotation (reduced or disabled based on preference)
        let oscillationAngle = 0;
        if (this.fanPivot && this.fanBlades && !this.prefersReducedMotion) {
            this.fanTime += 0.016; // ~60fps

            // Oscillation: ±35° swing, ~4 second cycle
            oscillationAngle = Math.sin(this.fanTime * 0.4) * 0.61; // 0.61 radians ≈ 35°
            this.fanPivot.rotation.y = oscillationAngle;

            // Blade rotation: ~300 RPM (5 rotations per second) around X axis
            this.fanBlades.rotation.x += 0.52; // 5 * 2π / 60 ≈ 0.52 rad/frame
        } else if (this.fanPivot && this.fanBlades) {
            // Static position for reduced motion - fan points forward
            this.fanPivot.rotation.y = 0;
        }

        // Wind simulation - papers sway based on fan direction (disabled for reduced motion)
        if (this.fan && this.papers && this.papers.length > 0 && !this.prefersReducedMotion) {
            const fanPosition = this.fan.position;
            const maxWindRange = 8; // Wind effective range (increased for larger fan)

            // Wind direction based on fan base rotation + oscillation
            const targetAngle = Math.atan2(1.5, -4.0); // Direction to papers from fan position
            const fanBaseRotation = targetAngle - Math.PI / 2 + Math.PI; // Adjust for motor orientation + flip 180°
            const totalAngle = fanBaseRotation + oscillationAngle;
            const windDirX = Math.cos(totalAngle);
            const windDirZ = Math.sin(totalAngle);

            this.papers.forEach(paper => {
                // Dampen wind on hovered papers
                const windDampening = paper === this.hoveredPaper ? 0.3 : 1.0;

                // Calculate distance and direction from fan to paper
                const dx = paper.position.x - fanPosition.x;
                const dz = paper.position.z - fanPosition.z;
                const distance = Math.sqrt(dx * dx + dz * dz);

                // Normalize direction to paper
                const dirToPaperX = dx / distance;
                const dirToPaperZ = dz / distance;

                // Calculate how aligned the wind direction is with the direction to this paper
                // Dot product: 1 = directly aligned, 0 = perpendicular, -1 = opposite
                const alignment = windDirX * dirToPaperX + windDirZ * dirToPaperZ;

                // Only affect papers in front of fan (alignment > 0), with smooth falloff
                const directionalFactor = Math.max(0, alignment);

                // Wind strength based on distance AND direction
                const distanceStrength = Math.max(0, 1 - distance / maxWindRange);
                const rawStrength = distanceStrength * directionalFactor;
                const strength = rawStrength * 0.025 * paper.userData.windSensitivity * windDampening; // Increased from 0.015

                // Apply subtle rotation sway (accumulates over time, but dampens back)
                const targetRotZ = paper.userData.originalRotation.z + (windDirX * strength * 3.5); // Increased from 2.5
                paper.rotation.z += (targetRotZ - paper.rotation.z) * 0.08; // Smooth interpolation

                // Only apply wind effects if not being hovered/animated
                if (paper !== this.hoveredPaper && !paper.userData.animating) {
                    // Apply subtle position displacement
                    const targetX = paper.userData.originalPosition.x + (windDirX * strength * 22); // Increased from 15
                    const targetZ = paper.userData.originalPosition.z + (windDirZ * strength * 22); // Increased from 15
                    paper.position.x += (targetX - paper.position.x) * 0.05;
                    paper.position.z += (targetZ - paper.position.z) * 0.05;

                    // Wind-triggered hover effect (stronger: ~50% of full hover instead of 35%)
                    // Lift based on wind strength (max 50% of 0.28 = 0.14)
                    const windLift = rawStrength * 0.14 * windDampening; // Increased from 0.098
                    const targetY = paper.userData.originalY + windLift;
                    paper.position.y += (targetY - paper.position.y) * 0.08;

                    // Deformation based on wind strength (max 50% of full deform = 0.5)
                    const windDeform = rawStrength * 0.5 * windDampening; // Increased from 0.35
                    paper.userData.deformProgress += (windDeform - paper.userData.deformProgress) * 0.08;
                    this.deformPaper(paper, paper.userData.deformProgress);

                    // Slight tilt toward wind direction (50% of 0.25 = 0.125)
                    const windTilt = rawStrength * 0.125 * windDampening; // Increased from 0.0875
                    const targetRotX = paper.userData.originalRotation.x + windTilt;
                    paper.rotation.x += (targetRotX - paper.rotation.x) * 0.08;
                }
            });
        }

        // Check for hover interactions
        this.checkHover();

        this.renderer.render(this.scene, this.camera);
    }

    pauseRendering() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
    }

    resumeRendering() {
        if (!this.animationId && this.scene) {
            this.animate();
        }
    }

    onScroll() {
        if (!this.camera) return;

        // Get scroll position relative to element
        const rect = this.element.getBoundingClientRect();
        const viewportHeight = window.innerHeight;

        // Calculate parallax factor based on element position in viewport
        // Negative when above viewport, 0 at center, positive when below
        const scrollProgress = (viewportHeight / 2 - rect.top) / viewportHeight;

        // Subtle camera movement for parallax effect
        // Camera moves down slightly as you scroll down
        const parallaxAmount = scrollProgress * 0.5; // Max 0.5 unit movement

        // Store base camera Y if not set
        if (!this.baseCameraY) {
            this.baseCameraY = 5;
        }

        // Apply parallax to camera Y position
        this.camera.position.y = this.baseCameraY + parallaxAmount;
        this.camera.lookAt(0, 0, 0);
    }

    onWindowResize() {
        if (!this.camera || !this.renderer) return;

        const aspect = this.canvasTarget.clientWidth / this.canvasTarget.clientHeight;
        const frustumSize = 6; // Match initial camera setup

        this.camera.left = frustumSize * aspect / -2;
        this.camera.right = frustumSize * aspect / 2;
        this.camera.top = frustumSize / 2;
        this.camera.bottom = frustumSize / -2;
        this.camera.updateProjectionMatrix();

        this.renderer.setSize(this.canvasTarget.clientWidth, this.canvasTarget.clientHeight);
    }

    disconnect() {
        console.log('Studio papers controller disconnected');

        // Stop observing
        if (this.observer) {
            this.observer.disconnect();
        }

        // Cancel animation
        this.pauseRendering();

        // Remove event listeners
        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
        }
        if (this.scrollHandler) {
            window.removeEventListener('scroll', this.scrollHandler);
        }
        if (this.mouseMoveHandler) {
            this.canvasTarget.removeEventListener('mousemove', this.mouseMoveHandler);
        }
        if (this.clickHandler) {
            this.canvasTarget.removeEventListener('click', this.clickHandler);
        }
        if (this.touchStartHandler) {
            this.canvasTarget.removeEventListener('touchstart', this.touchStartHandler);
        }
        if (this.touchEndHandler) {
            this.canvasTarget.removeEventListener('touchend', this.touchEndHandler);
        }

        // Cleanup Three.js resources
        if (this.scene) {
            this.papers.forEach(paper => {
                if (paper.geometry) paper.geometry.dispose();
                if (paper.material) {
                    if (paper.material.map) paper.material.map.dispose();
                    paper.material.dispose();
                }
            });

            if (this.ground) {
                this.ground.geometry.dispose();
                this.ground.material.map.dispose();
                this.ground.material.dispose();
            }

            this.scene.clear();
        }

        if (this.renderer) {
            this.renderer.dispose();
        }
    }
}
