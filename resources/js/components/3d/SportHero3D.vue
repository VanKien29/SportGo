<template>
  <div
    ref="containerRef"
    class="sport-3d-root"
    :style="{ height: canvasHeight }"
    @mousemove="onMouseMove"
    @mouseleave="onMouseLeave"
    @touchstart="onTouchStart"
    @touchmove="onTouchMove"
    @touchend="onTouchEnd"
  >
    <canvas ref="canvasRef" class="sport-3d-canvas"></canvas>

    <!-- Optional sport switch pills for quick interactive demo -->
    <div v-if="showControls" class="sport-3d-controls">
      <button
        v-for="sport in sports"
        :key="sport.id"
        type="button"
        class="sport-3d-pill"
        :class="{ active: currentSport === sport.id }"
        @click.stop="switchSport(sport.id)"
      >
        <span>{{ sport.name }}</span>
      </button>
    </div>
  </div>
</template>

<script>
import * as THREE from 'three';

export default {
  name: 'SportHero3D',
  props: {
    initialSport: {
      type: String,
      default: 'pickleball',
    },
    canvasHeight: {
      type: String,
      default: '380px',
    },
    showControls: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      currentSport: this.initialSport,
      sports: [
        { id: 'pickleball', name: 'Pickleball 3D' },
        { id: 'badminton', name: 'Cầu Lông 3D' },
        { id: 'football', name: 'Bóng Đá 3D' },
        { id: 'tennis', name: 'Tennis 3D' },
      ],
      mouseX: 0,
      mouseY: 0,
      targetRotationX: 0,
      targetRotationY: 0,
      isInteracting: false,
    };
  },
  mounted() {
    this.initThree();
    window.addEventListener('resize', this.onWindowResize);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.onWindowResize);
    if (this.animId) cancelAnimationFrame(this.animId);
    this.disposeThree();
  },
  methods: {
    initThree() {
      const container = this.$refs.containerRef;
      const canvas = this.$refs.canvasRef;
      if (!container || !canvas) return;

      const width = container.clientWidth || 400;
      const height = container.clientHeight || 380;

      // 1. Scene & Transparent Renderer
      this.scene = new THREE.Scene();
      this.camera = new THREE.PerspectiveCamera(42, width / height, 0.1, 100);
      this.camera.position.set(0, 0, 7.5);

      this.renderer = new THREE.WebGLRenderer({
        canvas,
        alpha: true, // 100% Alpha Transparent (No background)
        antialias: true,
        powerPreference: 'high-performance',
      });
      this.renderer.setSize(width, height);
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      this.renderer.outputColorSpace = THREE.SRGBColorSpace;
      this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
      this.renderer.toneMappingExposure = 1.15;

      // 2. Lighting Rig
      const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
      this.scene.add(ambientLight);

      const keyLight = new THREE.DirectionalLight(0xffffff, 2.4);
      keyLight.position.set(5, 7, 6);
      this.scene.add(keyLight);

      const fillLight = new THREE.DirectionalLight(0x34d399, 1.2);
      fillLight.position.set(-6, -3, 4);
      this.scene.add(fillLight);

      const rimLight = new THREE.DirectionalLight(0x06b6d4, 1.5);
      rimLight.position.set(0, -6, -5);
      this.scene.add(rimLight);

      // 3. Sport Mesh Group
      this.group = new THREE.Group();
      this.scene.add(this.group);

      this.buildSportMesh(this.currentSport);

      this.animate();
    },

    clearGroup() {
      while (this.group.children.length > 0) {
        const obj = this.group.children[0];
        this.group.remove(obj);
        if (obj.geometry) obj.geometry.dispose();
        if (obj.material) {
          if (Array.isArray(obj.material)) {
            obj.material.forEach((m) => m.dispose());
          } else {
            obj.material.dispose();
          }
        }
      }
    },

    buildSportMesh(sport) {
      this.clearGroup();

      if (sport === 'pickleball') {
        this.buildPickleball();
      } else if (sport === 'badminton') {
        this.buildBadminton();
      } else if (sport === 'football') {
        this.buildFootball();
      } else if (sport === 'tennis') {
        this.buildTennis();
      }
    },

    // A. 3D PICKLEBALL PADDLE & PERFORATED BALL
    buildPickleball() {
      const paddleGroup = new THREE.Group();

      // Paddle Surface (Rounded cylinder blend)
      const bladeGeo = new THREE.CylinderGeometry(1.4, 1.3, 0.08, 36);
      const bladeMat = new THREE.MeshPhysicalMaterial({
        color: 0x087642,
        roughness: 0.25,
        metalness: 0.1,
        clearcoat: 0.8,
        clearcoatRoughness: 0.15,
      });
      const blade = new THREE.Mesh(bladeGeo, bladeMat);
      blade.rotation.x = Math.PI / 2;
      paddleGroup.add(blade);

      // Paddle Edge Guard
      const ringGeo = new THREE.TorusGeometry(1.36, 0.06, 16, 40);
      const ringMat = new THREE.MeshStandardMaterial({
        color: 0x0f172a,
        roughness: 0.5,
      });
      const ring = new THREE.Mesh(ringGeo, ringMat);
      paddleGroup.add(ring);

      // Handle
      const handleGeo = new THREE.CylinderGeometry(0.18, 0.2, 1.5, 20);
      const handleMat = new THREE.MeshStandardMaterial({
        color: 0x1e293b,
        roughness: 0.8,
      });
      const handle = new THREE.Mesh(handleGeo, handleMat);
      handle.position.set(0, -1.8, 0);
      paddleGroup.add(handle);

      // Paddle Grip Tape Ring
      const gripRingGeo = new THREE.TorusGeometry(0.2, 0.03, 12, 24);
      const gripRingMat = new THREE.MeshStandardMaterial({ color: 0x10b981, roughness: 0.3 });
      const gripRing = new THREE.Mesh(gripRingGeo, gripRingMat);
      gripRing.rotation.x = Math.PI / 2;
      gripRing.position.set(0, -1.1, 0);
      paddleGroup.add(gripRing);

      paddleGroup.position.set(-0.6, 0, 0);
      paddleGroup.rotation.z = -0.3;
      this.group.add(paddleGroup);

      // Perforated Neon Yellow Pickleball Ball
      const ballGeo = new THREE.IcosahedronGeometry(0.72, 4);
      const ballMat = new THREE.MeshPhysicalMaterial({
        color: 0xd9f99d,
        roughness: 0.35,
        clearcoat: 0.5,
        wireframe: false,
      });
      const ball = new THREE.Mesh(ballGeo, ballMat);
      ball.position.set(1.4, 0.8, 0.6);

      // Holes illusion (wireframe accent overlay)
      const holesGeo = new THREE.IcosahedronGeometry(0.73, 2);
      const holesMat = new THREE.MeshBasicMaterial({
        color: 0x047857,
        wireframe: true,
        transparent: true,
        opacity: 0.45,
      });
      const holes = new THREE.Mesh(holesGeo, holesMat);
      ball.add(holes);

      this.group.add(ball);
    },

    // B. 3D BADMINTON SHUTTLECOCK
    buildBadminton() {
      const shuttleGroup = new THREE.Group();

      // Cork Base
      const corkGeo = new THREE.SphereGeometry(0.65, 32, 24);
      const corkMat = new THREE.MeshStandardMaterial({
        color: 0xf8fafc,
        roughness: 0.3,
        metalness: 0.05,
      });
      const cork = new THREE.Mesh(corkGeo, corkMat);
      cork.position.set(0, -1.2, 0);
      shuttleGroup.add(cork);

      // Red Stripe on Cork
      const stripGeo = new THREE.TorusGeometry(0.62, 0.04, 16, 32);
      const stripMat = new THREE.MeshBasicMaterial({ color: 0xef4444 });
      const strip = new THREE.Mesh(stripGeo, stripMat);
      strip.rotation.x = Math.PI / 2;
      strip.position.set(0, -1.05, 0);
      shuttleGroup.add(strip);

      // Feather Skirt
      const featherCount = 14;
      const featherGeo = new THREE.BoxGeometry(0.18, 1.8, 0.02);
      const featherMat = new THREE.MeshPhysicalMaterial({
        color: 0xffffff,
        roughness: 0.4,
        transmission: 0.3,
        opacity: 0.95,
        transparent: true,
      });

      for (let i = 0; i < featherCount; i++) {
        const angle = (i / featherCount) * Math.PI * 2;
        const feather = new THREE.Mesh(featherGeo, featherMat);
        const radiusBottom = 0.55;
        const radiusTop = 1.35;
        const xB = Math.cos(angle) * radiusBottom;
        const zB = Math.sin(angle) * radiusBottom;
        const xT = Math.cos(angle) * radiusTop;
        const zT = Math.sin(angle) * radiusTop;

        feather.position.set((xB + xT) / 2, 0.1, (zB + zT) / 2);
        feather.rotation.y = -angle;
        feather.rotation.z = Math.cos(angle) * 0.45;
        feather.rotation.x = Math.sin(angle) * -0.45;
        shuttleGroup.add(feather);
      }

      // Reinforcing Thread Rings
      const ring1Geo = new THREE.TorusGeometry(0.85, 0.03, 12, 36);
      const ring1 = new THREE.Mesh(ring1Geo, new THREE.MeshBasicMaterial({ color: 0x10b981 }));
      ring1.rotation.x = Math.PI / 2;
      ring1.position.set(0, -0.2, 0);
      shuttleGroup.add(ring1);

      const ring2Geo = new THREE.TorusGeometry(1.15, 0.03, 12, 36);
      const ring2 = new THREE.Mesh(ring2Geo, new THREE.MeshBasicMaterial({ color: 0x10b981 }));
      ring2.rotation.x = Math.PI / 2;
      ring2.position.set(0, 0.5, 0);
      shuttleGroup.add(ring2);

      shuttleGroup.rotation.x = 0.5;
      shuttleGroup.rotation.z = -0.4;
      this.group.add(shuttleGroup);
    },

    // C. 3D SOCCER BALL
    buildFootball() {
      const ballGroup = new THREE.Group();

      const ballGeo = new THREE.SphereGeometry(1.6, 36, 36);
      const ballMat = new THREE.MeshPhysicalMaterial({
        color: 0xffffff,
        roughness: 0.2,
        clearcoat: 0.9,
      });
      const ball = new THREE.Mesh(ballGeo, ballMat);
      ballGroup.add(ball);

      // Geometric Hexagon Pattern Overlays
      const hexGeo = new THREE.IcosahedronGeometry(1.61, 1);
      const hexMat = new THREE.MeshBasicMaterial({
        color: 0x0f172a,
        wireframe: true,
      });
      const hex = new THREE.Mesh(hexGeo, hexMat);
      ballGroup.add(hex);

      // Athletic Dynamic Ring Orbiting the Ball
      const orbitGeo = new THREE.TorusGeometry(2.1, 0.04, 16, 60);
      const orbitMat = new THREE.MeshBasicMaterial({
        color: 0x10b981,
        transparent: true,
        opacity: 0.8,
      });
      const orbit = new THREE.Mesh(orbitGeo, orbitMat);
      orbit.rotation.x = Math.PI / 3;
      ballGroup.add(orbit);

      this.group.add(ballGroup);
    },

    // D. 3D TENNIS BALL
    buildTennis() {
      const ballGroup = new THREE.Group();

      const ballGeo = new THREE.SphereGeometry(1.5, 36, 36);
      const ballMat = new THREE.MeshStandardMaterial({
        color: 0xccff00,
        roughness: 0.95,
      });
      const ball = new THREE.Mesh(ballGeo, ballMat);
      ballGroup.add(ball);

      // White Seam
      const seamGeo = new THREE.TorusGeometry(1.52, 0.05, 16, 60);
      const seamMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
      const seam1 = new THREE.Mesh(seamGeo, seamMat);
      seam1.rotation.y = Math.PI / 4;
      seam1.rotation.x = Math.PI / 6;
      ballGroup.add(seam1);

      this.group.add(ballGroup);
    },

    switchSport(sportId) {
      this.currentSport = sportId;
      this.buildSportMesh(sportId);
      this.$emit('sport-change', sportId);
    },

    animate() {
      this.animId = requestAnimationFrame(this.animate);
      if (!this.group || !this.renderer || !this.scene || !this.camera) return;

      const time = performance.now() * 0.001;

      // Gentle floating oscillation
      this.group.position.y = Math.sin(time * 1.5) * 0.15;

      // Inertial smooth rotation towards mouse interaction
      if (!this.isInteracting) {
        this.targetRotationY += 0.008;
      }

      this.group.rotation.y += (this.targetRotationY - this.group.rotation.y) * 0.06;
      this.group.rotation.x += (this.targetRotationX - this.group.rotation.x) * 0.06;

      this.renderer.render(this.scene, this.camera);
    },

    onMouseMove(e) {
      const rect = this.$refs.containerRef.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
      const y = -(((e.clientY - rect.top) / rect.height) * 2 - 1);
      this.isInteracting = true;
      this.targetRotationY = x * 1.2;
      this.targetRotationX = y * -0.6;
    },

    onMouseLeave() {
      this.isInteracting = false;
    },

    onTouchStart(e) {
      if (e.touches.length > 0) {
        this.touchStartX = e.touches[0].clientX;
        this.touchStartY = e.touches[0].clientY;
        this.isInteracting = true;
      }
    },

    onTouchMove(e) {
      if (!this.isInteracting || !e.touches.length) return;
      const dx = e.touches[0].clientX - this.touchStartX;
      const dy = e.touches[0].clientY - this.touchStartY;
      this.targetRotationY += dx * 0.01;
      this.targetRotationX += dy * 0.01;
      this.touchStartX = e.touches[0].clientX;
      this.touchStartY = e.touches[0].clientY;
    },

    onTouchEnd() {
      this.isInteracting = false;
    },

    onWindowResize() {
      const container = this.$refs.containerRef;
      if (!container || !this.renderer || !this.camera) return;
      const width = container.clientWidth;
      const height = container.clientHeight;
      this.camera.aspect = width / height;
      this.camera.updateProjectionMatrix();
      this.renderer.setSize(width, height);
    },

    disposeThree() {
      this.clearGroup();
      if (this.renderer) {
        this.renderer.dispose();
      }
    },
  },
};
</script>

<style scoped>
.sport-3d-root {
  position: relative;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  user-select: none;
  background: transparent !important;
}

.sport-3d-root:active {
  cursor: grabbing;
}

.sport-3d-canvas {
  width: 100% !important;
  height: 100% !important;
  display: block;
  background: transparent !important;
  pointer-events: auto;
}

.sport-3d-controls {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(15, 23, 42, 0.08);
  backdrop-filter: blur(12px);
  padding: 4px;
  border-radius: 999px;
  z-index: 10;
}

.sport-3d-pill {
  border: none;
  background: transparent;
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  white-space: nowrap;
}

.sport-3d-pill:hover {
  color: #087642;
}

.sport-3d-pill.active {
  background: #087642;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(8, 118, 66, 0.35);
}
</style>
