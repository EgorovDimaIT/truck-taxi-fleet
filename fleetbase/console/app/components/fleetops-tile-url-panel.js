import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

/**
 * Adds a "Leaflet Tile URL" field to the Fleet-Ops -> Settings -> Map page,
 * via the existing `fleet-ops:template:settings:map` extension slot.
 *
 * Does not modify any file inside the fleetops engine. Reads/saves the
 * `leafletTileUrl` key on the same `fleet-ops/settings/map` endpoint,
 * always merging with whatever settings are already saved so nothing
 * else on that endpoint is ever overwritten or lost.
 */
export default class FleetopsTileUrlPanelComponent extends Component {
    @service fetch;
    @service notifications;
    @service('map-settings') mapSettings;

    @tracked tileUrl = '';
    @tracked darkTileUrl = '';
    @tracked isLoading = true;
    @tracked isSaving = false;

    constructor() {
        super(...arguments);
        this.load();
    }

    async load() {
        try {
            const settings = await this.fetch.get('fleet-ops/settings/map');
            this.tileUrl = settings?.leafletTileUrl ?? '';
            this.darkTileUrl = settings?.leafletDarkTileUrl ?? '';
        } catch (error) {
            this.notifications.serverError(error);
        } finally {
            this.isLoading = false;
        }
    }

    @action
    async save() {
        this.isSaving = true;
        try {
            // Fetch the latest settings first and merge, so a concurrent
            // change to provider/toggles elsewhere on the page is never
            // clobbered by this panel's save.
            const current = await this.fetch.get('fleet-ops/settings/map');
            const settings = {
                ...current,
                leafletTileUrl: this.tileUrl || undefined,
                leafletDarkTileUrl: this.darkTileUrl || undefined,
            };
            delete settings.googleMapsApiKey; // never re-send, server manages this

            const response = await this.fetch.post('fleet-ops/settings/map', { settings });
            this.mapSettings.applySettings(response);
            this.notifications.success('Tile URL settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        } finally {
            this.isSaving = false;
        }
    }

    @action
    updateTileUrl(event) {
        this.tileUrl = event.target.value;
    }

    @action
    updateDarkTileUrl(event) {
        this.darkTileUrl = event.target.value;
    }
}
