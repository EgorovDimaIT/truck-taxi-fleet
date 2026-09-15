/**
 * Registers a "Live Tracking" header menu item pointing to the
 * new live-tracking route. Added as a standalone instance-initializer;
 * does not modify any existing initializer, menu, or engine.
 */
export function initialize(application) {
    const universe = application.lookup('service:universe');

    if (universe && typeof universe.registerHeaderMenuItem === 'function') {
        universe.registerHeaderMenuItem('Live Tracking', 'console.live-tracking', {
            icon: 'location-crosshairs',
            slug: 'live-tracking',
            priority: 50,
        });
    }
}

export default {
    name: 'register-live-tracking-menu',
    after: 'initialize-registries',
    initialize,
};
