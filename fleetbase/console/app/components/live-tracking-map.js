import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

/**
 * Live tracking map — polls the Traccar proxy endpoint and
 * renders current vehicle/device positions on a Leaflet map.
 *
 * Added as a standalone component; does not modify any existing
 * component, route or service.
 */
export default class LiveTrackingMapComponent extends Component {
    @service fetch;
    @service notifications;

    /** @var {Array} current device positions */
    @tracked positions = [];

    /** @var {Boolean} whether the first load has completed */
    @tracked isLoading = true;

    /** @var {String} last error message, if any */
    @tracked errorMessage = null;

    /** @var {Number} default map center latitude (Ukraine) */
    lat = 49.0;

    /** @var {Number} default map center longitude (Ukraine) */
    lng = 31.5;

    /** @var {Number} default zoom */
    zoom = 6;

    /** @var {Number} interval id for polling */
    pollHandle = null;

    /** @var {Number} poll interval in ms */
    pollIntervalMs = 10000;

    constructor() {
        super(...arguments);
        this.loadPositions();
        this.pollHandle = setInterval(() => this.loadPositions(), this.pollIntervalMs);
    }

    willDestroy() {
        super.willDestroy(...arguments);
        if (this.pollHandle) {
            clearInterval(this.pollHandle);
            this.pollHandle = null;
        }
    }

    @action
    async loadPositions() {
        try {
            const positions = await this.fetch.get('traccar/positions');

            if (this.isDestroying || this.isDestroyed) {
                return;
            }

            this.positions = Array.isArray(positions) ? positions : [];
            this.errorMessage = null;
        } catch (error) {
            if (this.isDestroying || this.isDestroyed) {
                return;
            }

            this.errorMessage = error?.message || 'Failed to load live positions from Traccar.';
        } finally {
            if (!this.isDestroying && !this.isDestroyed) {
                this.isLoading = false;
            }
        }
    }

    @action
    refresh() {
        this.loadPositions();
    }
}
