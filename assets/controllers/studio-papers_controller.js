import { Controller } from '@hotwired/stimulus';

/**
 * Studio Papers Controller
 *
 * Desktop-only Three.js scene showing scattered papers on a desk with an oscillating fan.
 * Papers represent blog posts with interactive hover/click behavior.
 *
 * Note: Mobile devices show a static grid layout instead (see template).
 */
export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        posts: Array
    };

    connect() {
        // Three.js objects
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.papers = [];
        this.fan = null;
        this.fanPivot = null;
        this.fanBlades = null;
        this.ground = null;

        // Animation state
        this.animationId = null;
        this.isIntersecting = false;
        this.fanTime = 0;

        // Interaction state
        this.raycaster = null;
        this.mouse = null;
        this.hoveredPaper = null;

        // Accessibility
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Check WebGL support
        if (!this.hasWebGLSupport()) {
            console.warn('WebGL not supported');
            return;
        }

        // Lazy load Three.js when canvas enters viewport
        this.setupIntersectionObserver();
    }

    hasWebGLSupport() {
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
            const THREE = await import('three');
            this.THREE = THREE;
            await this.initializeScene();
        } catch (error) {
            console.error('Failed to load Three.js:', error);
        }
    }

    async initializeScene() {
        // Scene setup
        this.scene = new this.THREE.Scene();
        this.scene.background = new this.THREE.Color(0xFFFCF6); // --color-paper
        this.scene.fog = new this.THREE.Fog(0xFFFCF6, 8, 20);

        // Camera setup (orthographic for isometric view)
        const aspect = this.canvasTarget.clientWidth / this.canvasTarget.clientHeight;
        const frustumSize = 6;
        this.camera = new this.THREE.OrthographicCamera(
            frustumSize * aspect / -2,
            frustumSize * aspect / 2,
            frustumSize / 2,
            frustumSize / -2,
            0.1,
            1000
        );
        this.camera.position.set(0, 5, 8);
        this.camera.lookAt(0, 0, 0);

        // Renderer setup
        this.renderer = new this.THREE.WebGLRenderer({
            canvas: this.canvasTarget,
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
        });
        this.renderer.setSize(this.canvasTarget.clientWidth, this.canvasTarget.clientHeight);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = this.THREE.PCFSoftShadowMap;

        // Scene content
        this.setupLighting();
        this.createGroundPlane();
        this.createDeskFan();
        this.createPapers();

        // Interaction setup
        this.setupInteraction();

        // Event listeners
        this.resizeHandler = this.onWindowResize.bind(this);
        window.addEventListener('resize', this.resizeHandler);

        if (!this.prefersReducedMotion) {
            this.scrollHandler = this.onScroll.bind(this);
            window.addEventListener('scroll', this.scrollHandler, { passive: true });
            this.baseCameraY = 5;
        }

        // Start animation loop
        this.animate();

        // Hide loading state
        this.hideLoadingState();
    }

    setupLighting() {
        // Warm ambient light
        const ambientLight = new this.THREE.AmbientLight(0xFFE8C0, 0.7);
        this.scene.add(ambientLight);

        // Main directional light (warm sunlight)
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
        sunLight.shadow.radius = 3;
        this.scene.add(sunLight);

        // Mustard accent fill light
        const accentLight = new this.THREE.DirectionalLight(0xF9A825, 0.4);
        accentLight.position.set(6, 4, -2);
        this.scene.add(accentLight);

        // Rim light
        const rimLight = new this.THREE.DirectionalLight(0xFFD700, 0.3);
        rimLight.position.set(0, 5, -8);
        this.scene.add(rimLight);

        // Hemisphere light for natural bounce
        const hemiLight = new this.THREE.HemisphereLight(
            0xFFF5E6, // sky
            0xD4CFC0, // ground
            0.5
        );
        this.scene.add(hemiLight);
    }

    createGroundPlane() {
        const geometry = new this.THREE.PlaneGeometry(20, 15);

        // Generate warm wood texture
        const canvas = document.createElement('canvas');
        canvas.width = 1024;
        canvas.height = 1024;
        const ctx = canvas.getContext('2d');

        // Warm wood gradient
        const gradient = ctx.createRadialGradient(512, 512, 100, 512, 512, 700);
        gradient.addColorStop(0, '#E8DCC8');
        gradient.addColorStop(0.4, '#D9CAB3');
        gradient.addColorStop(0.7, '#C9B79C');
        gradient.addColorStop(1, '#B8A889');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, 1024, 1024);

        // Wood grain
        for (let i = 0; i < 200; i++) {
            const y = i * 5 + Math.random() * 10;
            const opacity = 0.02 + Math.random() * 0.03;
            ctx.fillStyle = `rgba(92, 64, 51, ${opacity})`;
            ctx.fillRect(0, y, 1024, 1 + Math.random() * 2);
        }

        // Fine noise
        for (let i = 0; i < 15000; i++) {
            const x = Math.random() * 1024;
            const y = Math.random() * 1024;
            const size = Math.random() < 0.5 ? 1 : 2;
            const opacity = Math.random() * 0.04;
            const isDark = Math.random() > 0.5;
            ctx.fillStyle = isDark
                ? `rgba(92, 64, 51, ${opacity})`
                : `rgba(255, 248, 240, ${opacity})`;
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
        this.ground.rotation.x = -Math.PI / 2;
        this.ground.receiveShadow = true;
        this.scene.add(this.ground);
    }

    createDeskFan() {
        const fanGroup = new this.THREE.Group();
        const scale = 2.5;

        // Base (walnut wood)
        const baseGeometry = new this.THREE.CylinderGeometry(0.18 * scale, 0.22 * scale, 0.08 * scale, 32);
        const baseMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x5C4033,
            roughness: 0.7,
            metalness: 0.1
        });
        const base = new this.THREE.Mesh(baseGeometry, baseMaterial);
        base.position.y = 0.04 * scale;
        base.castShadow = true;
        base.receiveShadow = true;
        fanGroup.add(base);

        // Stand (gunmetal pole)
        const standGeometry = new this.THREE.CylinderGeometry(0.02 * scale, 0.02 * scale, 0.6 * scale, 16);
        const standMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x4A4A4A,
            roughness: 0.4,
            metalness: 0.7
        });
        const stand = new this.THREE.Mesh(standGeometry, standMaterial);
        stand.position.y = 0.38 * scale;
        stand.castShadow = true;
        fanGroup.add(stand);

        // Pivot group (for oscillation)
        this.fanPivot = new this.THREE.Group();
        this.fanPivot.position.y = 0.68 * scale;
        fanGroup.add(this.fanPivot);

        // Motor housing
        const motorGeometry = new this.THREE.CylinderGeometry(0.12 * scale, 0.12 * scale, 0.14 * scale, 32);
        const motorMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x3A3A3A,
            roughness: 0.3,
            metalness: 0.8
        });
        const motor = new this.THREE.Mesh(motorGeometry, motorMaterial);
        motor.rotation.z = Math.PI / 2;
        motor.castShadow = true;
        this.fanPivot.add(motor);

        // Wire cage
        const cageRadius = 0.2 * scale;
        const cageDepth = 0.12 * scale;
        const numBars = 14;
        const cageMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x4A4A4A,
            roughness: 0.4,
            metalness: 0.7
        });

        // Front and back rings
        const ringGeometry = new this.THREE.TorusGeometry(cageRadius, 0.008 * scale, 8, 32);
        const frontRing = new this.THREE.Mesh(ringGeometry, cageMaterial);
        frontRing.position.x = cageDepth / 2;
        frontRing.rotation.y = Math.PI / 2;
        this.fanPivot.add(frontRing);

        const backRing = new this.THREE.Mesh(ringGeometry, cageMaterial);
        backRing.position.x = -cageDepth / 2;
        backRing.rotation.y = Math.PI / 2;
        this.fanPivot.add(backRing);

        // Vertical bars
        const barGeometry = new this.THREE.CylinderGeometry(0.006 * scale, 0.006 * scale, cageDepth, 8);
        for (let i = 0; i < numBars; i++) {
            const angle = (i / numBars) * Math.PI * 2;
            const bar = new this.THREE.Mesh(barGeometry, cageMaterial);
            bar.position.set(0, Math.cos(angle) * cageRadius, Math.sin(angle) * cageRadius);
            bar.rotation.z = Math.PI / 2;
            this.fanPivot.add(bar);
        }

        // Blades
        this.fanBlades = new this.THREE.Group();
        this.fanBlades.position.x = 0;

        const bladeMaterial = new this.THREE.MeshStandardMaterial({
            color: 0xE8DCC8,
            roughness: 0.6,
            metalness: 0.3,
            side: this.THREE.DoubleSide
        });

        const numBlades = 4;
        for (let i = 0; i < numBlades; i++) {
            const angle = (i / numBlades) * Math.PI * 2;
            const bladeShape = new this.THREE.Shape();
            const innerWidth = 0.03 * scale;
            const outerWidth = 0.055 * scale;
            const bladeLength = 0.16 * scale;
            const bladeStart = 0.02 * scale;

            bladeShape.moveTo(bladeStart, -innerWidth / 2);
            bladeShape.lineTo(bladeStart, innerWidth / 2);
            bladeShape.lineTo(bladeLength, outerWidth / 2);
            bladeShape.lineTo(bladeLength, -outerWidth / 2);
            bladeShape.lineTo(bladeStart, -innerWidth / 2);

            const extrudeSettings = {
                steps: 1,
                depth: 0.002 * scale,
                bevelEnabled: false
            };

            const bladeGeometry = new this.THREE.ExtrudeGeometry(bladeShape, extrudeSettings);
            const blade = new this.THREE.Mesh(bladeGeometry, bladeMaterial);
            blade.rotation.y = Math.PI / 2;
            blade.position.x = -0.001 * scale;

            const bladeGroup = new this.THREE.Group();
            bladeGroup.add(blade);
            bladeGroup.rotation.x = angle;
            blade.castShadow = true;
            this.fanBlades.add(bladeGroup);
        }

        // Hub
        const hubGeometry = new this.THREE.CylinderGeometry(0.02 * scale, 0.02 * scale, 0.01 * scale, 16);
        const hubMaterial = new this.THREE.MeshStandardMaterial({
            color: 0x5C4033,
            roughness: 0.7,
            metalness: 0.1
        });
        const hub = new this.THREE.Mesh(hubGeometry, hubMaterial);
        hub.rotation.z = Math.PI / 2;
        hub.castShadow = true;
        this.fanBlades.add(hub);

        this.fanPivot.add(this.fanBlades);

        // Position and rotate fan
        fanGroup.position.set(4.0, 0, -1.5);
        const targetAngle = Math.atan2(1.5, -4.0);
        fanGroup.rotation.y = targetAngle - Math.PI / 2 + Math.PI;

        this.fan = fanGroup;
        this.scene.add(fanGroup);
    }

    createPapers() {
        const posts = this.postsValue.length > 0 ? this.postsValue : this.getDummyPosts();
        const positions = this.poissonDiskSampling(posts.length, 8, 6, 1.2);

        posts.forEach((post, index) => {
            if (positions[index]) {
                const { x, z } = positions[index];
                const rotation = (Math.random() - 0.5) * 0.52; // ±30°
                this.createPaper(post, x, z, rotation);
            }
        });
    }

    getDummyPosts() {
        return [
            { title: 'Symfony Service Configuration', date: '2024-12-15', excerpt: 'Exploring autoconfigure and autowire patterns in modern Symfony applications.', readingTime: '8 min read', url: '#' },
            { title: 'Modern PHP Patterns', date: '2024-11-20', excerpt: 'Best practices for PHP 8.3 and beyond.', readingTime: '6 min read', url: '#' },
            { title: 'Tailwind Best Practices', date: '2024-10-05', excerpt: 'Building maintainable utility-first CSS.', readingTime: '5 min read', url: '#' },
            { title: 'Database Optimization', date: '2024-09-12', excerpt: 'Query performance and indexing strategies.', readingTime: '10 min read', url: '#' },
            { title: 'API Design Principles', date: '2024-08-30', excerpt: 'RESTful design patterns that scale.', readingTime: '7 min read', url: '#' },
            { title: 'Testing Strategies', date: '2024-07-18', excerpt: 'Unit, integration, and E2E testing approaches.', readingTime: '9 min read', url: '#' },
            { title: 'Docker Workflows', date: '2024-06-22', excerpt: 'Container-based development environments.', readingTime: '6 min read', url: '#' },
            { title: 'Git Techniques', date: '2024-05-14', excerpt: 'Advanced workflows and collaboration patterns.', readingTime: '8 min read', url: '#' }
        ];
    }

    poissonDiskSampling(numPoints, width, height, minDistance) {
        const points = [];
        const cellSize = minDistance / Math.sqrt(2);
        const gridWidth = Math.ceil(width / cellSize);
        const gridHeight = Math.ceil(height / cellSize);
        const grid = Array(gridWidth * gridHeight).fill(null);
        const activeList = [];

        const isValid = (x, z) => {
            if (x < -width / 2 || x > width / 2 || z < -height / 2 || z > height / 2) return false;

            const gridX = Math.floor((x + width / 2) / cellSize);
            const gridZ = Math.floor((z + height / 2) / cellSize);

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

        const addPoint = (x, z) => {
            const point = { x, z };
            points.push(point);
            activeList.push(point);
            const gridX = Math.floor((x + width / 2) / cellSize);
            const gridZ = Math.floor((z + height / 2) / cellSize);
            grid[gridZ * gridWidth + gridX] = point;
        };

        // Start with random point near center
        addPoint((Math.random() - 0.5) * 2, (Math.random() - 0.5) * 2);

        while (activeList.length > 0 && points.length < numPoints) {
            const randomIndex = Math.floor(Math.random() * activeList.length);
            const point = activeList[randomIndex];
            let found = false;

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
        const width = 1.2;
        const height = 1.6;
        const geometry = new this.THREE.PlaneGeometry(width, height, 64, 64);

        // Generate paper texture
        const canvas = document.createElement('canvas');
        const resolution = 2048;
        canvas.width = resolution;
        canvas.height = Math.floor(resolution * 1.333);
        const ctx = canvas.getContext('2d');

        const scale = resolution / 1024;
        const canvasHeight = canvas.height;

        // Background gradient
        const bgGradient = ctx.createRadialGradient(
            512 * scale, 682 * scale, 200 * scale,
            512 * scale, 682 * scale, 900 * scale
        );
        bgGradient.addColorStop(0, '#FFFEF8');
        bgGradient.addColorStop(0.7, '#FAF6ED');
        bgGradient.addColorStop(1, '#F0EAD6');
        ctx.fillStyle = bgGradient;
        ctx.fillRect(0, 0, resolution, canvasHeight);

        // Paper texture
        for (let i = 0; i < 3000; i++) {
            const x = Math.random() * resolution;
            const y = Math.random() * canvasHeight;
            const size = (Math.random() < 0.8 ? 1 : 2) * scale;
            const opacity = Math.random() * 0.04;
            ctx.fillStyle = `rgba(180, 160, 120, ${opacity})`;
            ctx.fillRect(x, y, size, size);
        }

        // Coffee stains
        if (Math.random() > 0.5) {
            const stainX = (100 + Math.random() * 824) * scale;
            const stainY = (200 + Math.random() * 900) * scale;
            const stainGrad = ctx.createRadialGradient(stainX, stainY, 0, stainX, stainY, 40 * scale);
            stainGrad.addColorStop(0, 'rgba(160, 120, 80, 0.08)');
            stainGrad.addColorStop(1, 'rgba(160, 120, 80, 0)');
            ctx.fillStyle = stainGrad;
            ctx.fillRect(stainX - 40 * scale, stainY - 40 * scale, 80 * scale, 80 * scale);
        }

        // Mustard accent
        const highlightGrad = ctx.createLinearGradient(0, 0, 200 * scale, 200 * scale);
        highlightGrad.addColorStop(0, 'rgba(249, 168, 37, 0.12)');
        highlightGrad.addColorStop(0.5, 'rgba(249, 168, 37, 0.08)');
        highlightGrad.addColorStop(1, 'rgba(249, 168, 37, 0)');
        ctx.fillStyle = highlightGrad;
        ctx.fillRect(0, 0, 300 * scale, 300 * scale);

        // Title
        ctx.fillStyle = '#1A1A1A';
        ctx.font = `700 ${68 * scale}px Inter, sans-serif`;
        ctx.textAlign = 'left';
        ctx.letterSpacing = '-0.02em';
        this.wrapText(ctx, postData.title, 60 * scale, 120 * scale, 904 * scale, 80 * scale);

        // Underline
        const titleLines = Math.ceil(ctx.measureText(postData.title).width / (904 * scale)) || 1;
        const underlineY = 120 * scale + (titleLines * 80 * scale) + 20 * scale;
        ctx.strokeStyle = '#F9A825';
        ctx.lineWidth = 4 * scale;
        ctx.globalAlpha = 0.6;
        ctx.beginPath();
        ctx.moveTo(60 * scale, underlineY);
        ctx.lineTo(280 * scale, underlineY);
        ctx.stroke();
        ctx.globalAlpha = 1.0;

        // Date
        ctx.fillStyle = '#5C4033';
        ctx.font = `600 ${32 * scale}px Inter, sans-serif`;
        ctx.letterSpacing = '0.1em';
        ctx.fillText(postData.date.toUpperCase(), 60 * scale, underlineY + 60 * scale);

        // Reading time
        if (postData.readingTime) {
            ctx.fillStyle = '#9D9786';
            ctx.font = `500 ${28 * scale}px Inter, sans-serif`;
            ctx.letterSpacing = '0';
            ctx.fillText(postData.readingTime, 60 * scale, underlineY + 110 * scale);
        }

        // Excerpt
        if (postData.excerpt) {
            ctx.fillStyle = '#3A3A3A';
            ctx.font = `400 ${32 * scale}px Inter, sans-serif`;
            ctx.letterSpacing = '0';
            const excerptY = underlineY + 170 * scale;
            this.wrapText(ctx, postData.excerpt, 60 * scale, excerptY, 904 * scale, 48 * scale);
        }

        // Tags
        if (postData.tags && postData.tags.length > 0) {
            const tagsY = underlineY + (postData.excerpt ? 400 : 200) * scale;
            ctx.fillStyle = '#9D9786';
            ctx.font = `500 ${24 * scale}px Inter, sans-serif`;
            ctx.fillText('Tags:', 60 * scale, tagsY);

            ctx.fillStyle = '#F9A825';
            ctx.font = `600 ${24 * scale}px Inter, sans-serif`;
            const tagsText = postData.tags.slice(0, 3).join(' • ');
            ctx.fillText(tagsText, 140 * scale, tagsY);
        }

        // Folded corner
        ctx.fillStyle = 'rgba(0, 0, 0, 0.08)';
        ctx.beginPath();
        ctx.moveTo(resolution, 0);
        ctx.lineTo(resolution, 80 * scale);
        ctx.lineTo(944 * scale, 0);
        ctx.closePath();
        ctx.fill();

        // Create material
        const texture = new this.THREE.CanvasTexture(canvas);
        texture.minFilter = this.THREE.LinearFilter;
        texture.magFilter = this.THREE.LinearFilter;
        texture.anisotropy = 4;

        const material = new this.THREE.MeshStandardMaterial({
            map: texture,
            roughness: 0.9,
            metalness: 0.02,
            side: this.THREE.DoubleSide,
            emissive: new this.THREE.Color(0xFFFAF0),
            emissiveIntensity: 0.1
        });

        // Create mesh
        const paper = new this.THREE.Mesh(geometry, material);
        paper.position.set(x, 0.02 + Math.random() * 0.08, z);
        paper.rotation.x = -Math.PI / 2;
        paper.rotation.z = rotation;
        paper.castShadow = true;
        paper.receiveShadow = true;

        // Store metadata
        const positions = geometry.attributes.position.array;
        const originalPositions = new Float32Array(positions.length);
        originalPositions.set(positions);

        paper.userData = {
            postData,
            url: postData.url || '#',
            originalY: paper.position.y,
            originalRotation: { x: paper.rotation.x, z: paper.rotation.z },
            originalPositions: originalPositions,
            originalPosition: { x: paper.position.x, y: paper.position.y, z: paper.position.z },
            deformProgress: 0,
            windSensitivity: 0.5 + Math.random() * 1.0,
            animating: false
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

    setupInteraction() {
        this.raycaster = new this.THREE.Raycaster();
        this.mouse = new this.THREE.Vector2(-999, -999);

        // Mouse events
        this.mouseMoveHandler = this.onMouseMove.bind(this);
        this.clickHandler = this.onClick.bind(this);
        this.canvasTarget.addEventListener('mousemove', this.mouseMoveHandler, { passive: true });
        this.canvasTarget.addEventListener('click', this.clickHandler);
    }

    onMouseMove(event) {
        if (!this.canvasTarget) return;
        const rect = this.canvasTarget.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    }

    onClick() {
        if (this.hoveredPaper && this.hoveredPaper.userData.url) {
            window.location.href = this.hoveredPaper.userData.url;
        }
    }

    checkHover() {
        if (!this.raycaster || !this.camera || !this.papers || this.papers.length === 0) return;
        if (this.mouse.x === -999 && this.mouse.y === -999) return;

        this.raycaster.setFromCamera(this.mouse, this.camera);
        const intersects = this.raycaster.intersectObjects(this.papers, false);

        if (intersects.length > 0) {
            const paper = intersects[0].object;
            if (this.hoveredPaper !== paper) {
                if (this.hoveredPaper) {
                    this.resetPaper(this.hoveredPaper);
                }
                this.hoveredPaper = paper;
                this.hoverPaper(paper);
                this.canvasTarget.style.cursor = 'pointer';
            }
        } else {
            if (this.hoveredPaper) {
                this.resetPaper(this.hoveredPaper);
                this.hoveredPaper = null;
                this.canvasTarget.style.cursor = 'default';
            }
        }
    }

    hoverPaper(paper) {
        if (!paper || !this.THREE) return;

        const targetY = paper.userData.originalY + 0.28;
        if (!paper.userData.animating) {
            paper.userData.animating = true;
            this.animatePaperHover(paper, targetY, true);
        }

        if (paper.material) {
            paper.material.emissive.setHex(0xF9A825);
            paper.material.emissiveIntensity = 0.15;
        }
    }

    resetPaper(paper) {
        if (!paper || !this.THREE) return;

        const targetY = paper.userData.originalY;
        paper.userData.animating = true;
        this.animatePaperHover(paper, targetY, false);

        if (paper.material) {
            paper.material.emissive.setHex(0xFFFAF0);
            paper.material.emissiveIntensity = 0.1;
        }
    }

    animatePaperHover(paper, targetY, isHovering) {
        const startY = paper.position.y;
        const startRotX = paper.rotation.x;
        const targetRotX = isHovering
            ? paper.userData.originalRotation.x + 0.25
            : paper.userData.originalRotation.x;

        const startDeform = paper.userData.deformProgress;
        const targetDeform = isHovering ? 1.0 : 0.0;

        const duration = this.prefersReducedMotion ? 150 : (isHovering ? 350 : 650);
        const startTime = Date.now();

        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const eased = isHovering
                ? 1 - Math.pow(1 - progress, 3) // ease-out cubic
                : 1 - Math.pow(1 - progress, 4); // ease-out quartic

            paper.position.y = startY + (targetY - startY) * eased;
            paper.rotation.x = startRotX + (targetRotX - startRotX) * eased;
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

    deformPaper(paper, progress) {
        if (!paper.geometry || !paper.userData.originalPositions) return;

        const positions = paper.geometry.attributes.position.array;
        const original = paper.userData.originalPositions;
        const width = 1.2;
        const height = 1.6;

        for (let i = 0; i < positions.length; i += 3) {
            const x = original[i];
            const y = original[i + 1];
            const z = original[i + 2];

            const nx = x / width;
            const ny = y / height;
            const normalizedY = ny + 0.5;

            const gravityDroop = -Math.sin(normalizedY * Math.PI) * 0.08;
            const widthCurl = Math.pow(Math.abs(nx), 2.5) * -0.04;
            const topEdgeLift = normalizedY > 0.7 ? Math.pow((normalizedY - 0.7) / 0.3, 2) * 0.12 : 0;
            const ripple = Math.sin(normalizedY * 4 + nx * 3 + progress * 3) * 0.006;

            const deformation = (gravityDroop + widthCurl + topEdgeLift + ripple) * progress;

            positions[i] = x;
            positions[i + 1] = y;
            positions[i + 2] = z + deformation;
        }

        paper.geometry.attributes.position.needsUpdate = true;
        paper.geometry.computeVertexNormals();
    }

    animate() {
        if (!this.isIntersecting) return;

        this.animationId = requestAnimationFrame(() => this.animate());

        // Fan animation
        let oscillationAngle = 0;
        if (this.fanPivot && this.fanBlades && !this.prefersReducedMotion) {
            this.fanTime += 0.016;
            oscillationAngle = Math.sin(this.fanTime * 0.4) * 0.61; // ±35°
            this.fanPivot.rotation.y = oscillationAngle;
            this.fanBlades.rotation.x += 0.52; // ~300 RPM
        } else if (this.fanPivot) {
            this.fanPivot.rotation.y = 0;
        }

        // Wind simulation
        if (this.fan && this.papers && this.papers.length > 0 && !this.prefersReducedMotion) {
            const fanPosition = this.fan.position;
            const maxWindRange = 8;
            const targetAngle = Math.atan2(1.5, -4.0);
            const fanBaseRotation = targetAngle - Math.PI / 2 + Math.PI;
            const totalAngle = fanBaseRotation + oscillationAngle;
            const windDirX = Math.cos(totalAngle);
            const windDirZ = Math.sin(totalAngle);

            this.papers.forEach(paper => {
                const windDampening = paper === this.hoveredPaper ? 0.3 : 1.0;
                const dx = paper.position.x - fanPosition.x;
                const dz = paper.position.z - fanPosition.z;
                const distance = Math.sqrt(dx * dx + dz * dz);
                const dirToPaperX = dx / distance;
                const dirToPaperZ = dz / distance;
                const alignment = windDirX * dirToPaperX + windDirZ * dirToPaperZ;
                const directionalFactor = Math.max(0, alignment);
                const distanceStrength = Math.max(0, 1 - distance / maxWindRange);
                const rawStrength = distanceStrength * directionalFactor;
                const strength = rawStrength * 0.025 * paper.userData.windSensitivity * windDampening;

                const targetRotZ = paper.userData.originalRotation.z + (windDirX * strength * 3.5);
                paper.rotation.z += (targetRotZ - paper.rotation.z) * 0.08;

                if (paper !== this.hoveredPaper && !paper.userData.animating) {
                    const targetX = paper.userData.originalPosition.x + (windDirX * strength * 22);
                    const targetZ = paper.userData.originalPosition.z + (windDirZ * strength * 22);
                    paper.position.x += (targetX - paper.position.x) * 0.05;
                    paper.position.z += (targetZ - paper.position.z) * 0.05;

                    const windLift = rawStrength * 0.14 * windDampening;
                    const targetY = paper.userData.originalY + windLift;
                    paper.position.y += (targetY - paper.position.y) * 0.08;

                    const windDeform = rawStrength * 0.5 * windDampening;
                    paper.userData.deformProgress += (windDeform - paper.userData.deformProgress) * 0.08;
                    this.deformPaper(paper, paper.userData.deformProgress);

                    const windTilt = rawStrength * 0.125 * windDampening;
                    const targetRotX = paper.userData.originalRotation.x + windTilt;
                    paper.rotation.x += (targetRotX - paper.rotation.x) * 0.08;
                }
            });
        }

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

        const rect = this.element.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const scrollProgress = (viewportHeight / 2 - rect.top) / viewportHeight;
        const parallaxAmount = scrollProgress * 0.5;

        if (!this.baseCameraY) {
            this.baseCameraY = 5;
        }

        this.camera.position.y = this.baseCameraY + parallaxAmount;
        this.camera.lookAt(0, 0, 0);
    }

    onWindowResize() {
        if (!this.camera || !this.renderer) return;

        const aspect = this.canvasTarget.clientWidth / this.canvasTarget.clientHeight;
        const frustumSize = 6;

        this.camera.left = frustumSize * aspect / -2;
        this.camera.right = frustumSize * aspect / 2;
        this.camera.top = frustumSize / 2;
        this.camera.bottom = frustumSize / -2;
        this.camera.updateProjectionMatrix();

        this.renderer.setSize(this.canvasTarget.clientWidth, this.canvasTarget.clientHeight);
    }

    hideLoadingState() {
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
    }

    disconnect() {
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
