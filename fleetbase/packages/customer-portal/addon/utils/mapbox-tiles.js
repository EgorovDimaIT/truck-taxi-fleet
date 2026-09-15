/**
 * Shared Mapbox raster tile configuration for the Customer Portal's Leaflet-based maps.
 *
 * Mirrors the fix already applied in `@fleetbase/fleetops-engine`
 * (packages/fleetops/addon/utils/mapbox-tiles.js), which replaced the
 * CartoDB basemap tiles (which require a paid API key and render as
 * "API KEY REQUIRED" placeholder tiles) with Mapbox Streets/Dark raster tiles.
 *
 * NOTE: this token is a Mapbox *public* token, intended for client-side use.
 * It is intentionally kept in the frontend bundle (not routed through the
 * backend .env / API) per project decision.
 */

export const MAPBOX_ACCESS_TOKEN = 'pk.eyJ1IjoiZWdvcm92LTE5ODMiLCJhIjoiY21oYzFtMWExMDlxcjJqc2FtdWkwcHF0aSJ9.wPjAUFKC4nPIQxhQ_x2QuA';

export const MAPBOX_TILE_URL_LIGHT = `https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/{z}/{x}/{y}@2x?access_token=${MAPBOX_ACCESS_TOKEN}`;

export const MAPBOX_TILE_URL_DARK = `https://api.mapbox.com/styles/v1/mapbox/dark-v11/tiles/256/{z}/{x}/{y}@2x?access_token=${MAPBOX_ACCESS_TOKEN}`;

export const MAPBOX_ATTRIBUTION =
    '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>';
