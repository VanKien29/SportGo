<template>
  <div ref="mapElement" class="venue-results-map" role="application" aria-label="Bản đồ các cụm sân"></div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

const props = defineProps({
  venues: { type: Array, default: () => [] },
});
const emit = defineEmits(['select']);
const mapElement = ref(null);
let map = null;
let markerLayer = null;

const defaultIcon = L.icon({
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

function coordinates(venue) {
  const latitude = Number(venue?.latitude);
  const longitude = Number(venue?.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return null;
  return [latitude, longitude];
}

function renderMarkers() {
  if (!map || !markerLayer) return;
  markerLayer.clearLayers();
  const bounds = [];

  props.venues.forEach((venue) => {
    const point = coordinates(venue);
    if (!point) return;
    const label = document.createElement('span');
    label.textContent = venue.name || 'Cụm sân';
    const marker = L.marker(point, { icon: defaultIcon, title: venue.name || 'Cụm sân' })
      .bindTooltip(label, { direction: 'top', offset: [0, -30] })
      .on('click', () => emit('select', venue));
    markerLayer.addLayer(marker);
    bounds.push(point);
  });

  if (bounds.length === 1) map.setView(bounds[0], 15);
  if (bounds.length > 1) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
}

onMounted(async () => {
  await nextTick();
  map = L.map(mapElement.value, { scrollWheelZoom: false, minZoom: 4, maxBounds: [[-90, -180], [90, 180]] }).setView([16.0471, 108.2062], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    minZoom: 4,
    maxZoom: 19,
    noWrap: true,
  }).addTo(map);
  markerLayer = L.layerGroup().addTo(map);
  renderMarkers();
  window.setTimeout(() => map?.invalidateSize(), 0);
});

watch(() => props.venues, renderMarkers, { deep: true });

onBeforeUnmount(() => {
  map?.remove();
  map = null;
  markerLayer = null;
});
</script>

<style scoped>
.venue-results-map {
  width: 100%;
  min-height: 460px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-bg-soft);
}

@media (max-width: 640px) {
  .venue-results-map {
    min-height: 380px;
  }
}
</style>
