import { inject as service } from '@ember/service';

/**
 * Forces the console theme to light by default on boot, unless the
 * current user has an explicit saved preference. Implemented as a
 * standalone instance-initializer using the public theme service API
 * (theme.setTheme) so it works regardless of which version of
 * @fleetbase/ember-core is bundled -- no vendored package files are
 * relied on. Does not modify any existing initializer.
 */
export function initialize(application) {
    const theme = application.lookup('service:theme');
    const currentUser = application.lookup('service:current-user');

    if (!theme) {
        return;
    }

    const userSetTheme = currentUser && typeof currentUser.getOption === 'function' ? currentUser.getOption('theme') : null;

    if (!userSetTheme) {
        theme.applyTheme('light', { persist: false });
    }
}

export default {
    name: 'force-light-theme',
    after: 'initialize-registries',
    initialize,
};
