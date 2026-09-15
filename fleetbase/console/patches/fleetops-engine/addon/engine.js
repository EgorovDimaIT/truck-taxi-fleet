import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import { services, externalRoutes } from '@fleetbase/ember-core/exports';

const { modulePrefix } = config;

// PATCH (2026-09-08, reverted 2026-09-10): the orchestrator white-screen fix
// previously added 'location', 'map-manager', 'route-engine' and
// 'order-allocation' to `dependencies.services` here (and to the matching
// host-services.js whitelist) to silence an ember-engines assertion raised
// by orchestrator-workbench.js's `@service location` etc. injections.
//
// That was the wrong fix. `dependencies.services` tells ember-engines which
// services must be CLONED FROM THE PARENT (host) app into this engine
// (see cloneParentDependencies in ember-engines/ember.js). It is only for
// services the engine does NOT define itself. 'location', 'map-manager',
// 'route-engine' and 'order-allocation' are all defined locally inside this
// engine, at addon/services/{location,map-manager,route-engine,order-allocation}.js
// -- an engine's own services resolve from its own container automatically
// and must never be listed here.
//
// Declaring them anyway made ember-engines look for service:location etc. on
// the HOST (console) app instead, which has no such service, producing:
//   "Could not find module '@fleetbase/fleetops-engine/services/location'
//    imported from '@fleetbase/console/services/location'"
//   "Assertion Failed: Failed to create an instance of 'service:location'"
// i.e. the white screen simply moved from /orchestrator's mount to boot time.
// Whatever originally caused the assertion on orchestrator-workbench.js's
// service injections needs a different fix (e.g. ensure the engine's own
// addon/services files are actually present in the built engine bundle) --
// not adding engine-local services to the host-sharing whitelist.
const ORCHESTRATOR_EXTRA_SERVICES = [];

export default class FleetOpsEngine extends Engine {
    modulePrefix = modulePrefix;
    Resolver = Resolver;
    dependencies = {
        services: [...new Set([...services, ...ORCHESTRATOR_EXTRA_SERVICES])],
        externalRoutes,
    };
}

loadInitializers(FleetOpsEngine, modulePrefix);