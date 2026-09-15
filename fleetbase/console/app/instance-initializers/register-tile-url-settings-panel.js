import { ExtensionComponent } from '@fleetbase/ember-core/contracts';
import FleetopsTileUrlPanelComponent from '../components/fleetops-tile-url-panel';

/**
 * Registers the "Leaflet Tile Source" panel into the Fleet-Ops
 * Settings -> Map page via the existing `fleet-ops:template:settings:map`
 * extension slot (RegistryYield already present in that template).
 *
 * Standalone, additive instance-initializer -- does not touch any
 * file inside the @fleetbase/fleetops-engine package.
 */
export function initialize(application) {
    const registryService = application.lookup('service:universe/registry-service');

    if (registryService && typeof registryService.registerRenderableComponent === 'function') {
        registryService.registerRenderableComponent('fleet-ops:template:settings:map', new ExtensionComponent('@fleetbase/fleetops-engine', FleetopsTileUrlPanelComponent));
    }
}

export default {
    name: 'register-tile-url-settings-panel',
    after: 'initialize-registries',
    initialize,
};
