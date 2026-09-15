export const hostServices = [
    'store',
    'session',
    'current-user',
    'fetch',
    'socket',
    'media',
    'app-cache',
    'url-search-params',
    'modals-manager',
    'resource-context-panel',
    'custom-fields-registry',
    'table-context',
    'loader',
    'filters',
    'crud',
    'notifications',
    'fileQueue',
    'sidebar',
    'dashboard',
    'docs-panel',
    'universe',
    'universe/menu-service',
    'universe/registry-service',
    'universe/hook-service',
    'universe/widget-service',
    'universe/extension-manager',
    'events',
    'intl',
    'abilities',
    'language',
    // NOTE: 'location', 'map-manager', 'route-engine' and 'order-allocation'
    // were briefly added here on 2026-09-08 as part of an incorrect fix for
    // an orchestrator-workbench.js white screen (see the matching note in
    // fleetops-engine's patched engine.js). They were removed again on
    // 2026-09-10: this list is for services the HOST app provides to engines;
    // those four services are defined inside fleetops-engine itself
    // (addon/services/*.js) and are never provided by the host, so listing
    // them here just makes ember-engines look for a non-existent host service
    // and crash at boot instead of at /orchestrator.
    { hostRouter: 'router' },
];

export default hostServices;