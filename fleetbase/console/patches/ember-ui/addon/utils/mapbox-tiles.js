/**
 * Shared Mapbox raster tile configuration for Fleetbase's Leaflet-based maps.
 *
 * Mirrors packages/fleetops/addon/utils/mapbox-tiles.js. Duplicated here
 * (instead of imported) because @fleetbase/ember-ui is a lower-level
 * dependency of @fleetbase/fleetops-engine, not the other way around, so
 * ember-ui cannot import from fleetops without creating a circular
 * dependency. Keep both files in sync if the token or style changes.
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
