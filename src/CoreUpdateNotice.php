<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

/**
 * Prompts site administrators to update WordPress while the install is behind the latest release.
 *
 * Consumers Strauss-prefix their own copy of this class, so nothing here may rely on class names,
 * namespaces or constants being shared between plugins. The two pieces of cross-plugin state are
 * both string keys, which prefixing does not rewrite: the dismissal option and the render guard.
 */
final class CoreUpdateNotice
{
    /**
     * Dismissal flag shared with the other plugins that display this notice, so a site running
     * more than one of them only has to dismiss it once. Do not prefix it per plugin.
     */
    public const DISMISSED_OPTION = 'nx_wp_core_update_notice_dismissed';

    /**
     * The query argument and nonce action carried by the dismiss link. Shared, so whichever
     * plugin's admin_init runs first stores the flag.
     */
    public const DISMISS_ACTION = 'nx-dismiss-wp-core-update-notice';

    /**
     * Global key marking that a copy of this notice has already rendered this request. A static
     * property cannot do this job: each plugin prefixes the class, so each copy gets its own.
     */
    private const RENDER_GUARD = 'nx_wp_core_update_notice_rendered';

    /**
     * The capability a user needs to see the notice and to dismiss it.
     */
    private const CAPABILITY = 'update_core';

    /**
     * @var array{heading: string, body: string, dismiss: string}
     */
    private $strings;

    /**
     * @param array<string, string> $strings Optional translated copy. Supply this from the
     *                                       consuming plugin so the strings land in its own text
     *                                       domain; the defaults are English and untranslated.
     */
    public function __construct(array $strings = [])
    {
        $this->strings = array_merge(
            [
                'heading' => 'Keep your site protected. Update to the latest version of WordPress.',
                'body' => 'Your site is running on an outdated version of WordPress, which can leave it vulnerable to security issues. To decrease your risk of exposure, please update your WordPress install to the latest version.',
                'dismiss' => 'Dismiss this notice.',
            ],
            array_filter($strings, 'is_string')
        );
    }

    /**
     * Hook the notice into wp-admin.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handleDismissal']);
        add_action('admin_notices', [$this, 'render']);
    }

    /**
     * Store the shared dismissal flag when the notice's dismiss control is used.
     *
     * @hook admin_init
     */
    public function handleDismissal(): void
    {
        if (!isset($_GET[self::DISMISS_ACTION])) {
            return;
        }

        check_admin_referer(self::DISMISS_ACTION);

        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        update_option(self::DISMISSED_OPTION, true, false);

        wp_safe_redirect(remove_query_arg([self::DISMISS_ACTION, '_wpnonce']));

        exit;
    }

    /**
     * Render the notice, at most once per request across every plugin that registers it.
     *
     * @hook admin_notices
     */
    public function render(): void
    {
        if (!empty($GLOBALS[self::RENDER_GUARD])) {
            return;
        }

        if (!current_user_can(self::CAPABILITY) || !$this->shouldDisplay()) {
            return;
        }

        $GLOBALS[self::RENDER_GUARD] = true;

        /*
         * The dismiss control is a link so the shared flag can be stored server side, without a
         * script. "is-dismissible" supplies the positioning context the control needs, and core's
         * makeNoticesDismissible() skips notices that already carry a .notice-dismiss, so it does
         * not append a second, non-persisting button.
         */
        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%1$s</strong></p><p>%2$s</p>'
            . '<a href="%3$s" class="notice-dismiss" style="text-decoration:none;">'
            . '<span class="screen-reader-text">%4$s</span></a></div>',
            esc_html($this->strings['heading']),
            esc_html($this->strings['body']),
            esc_url($this->getDismissUrl()),
            esc_html($this->strings['dismiss'])
        );
    }

    /**
     * @return bool True while a core update is available and the notice has not been dismissed.
     */
    public function shouldDisplay(): bool
    {
        return !$this->isDismissed() && $this->isCoreUpdateAvailable();
    }

    /**
     * Whether the shared dismissal flag has been stored.
     */
    private function isDismissed(): bool
    {
        return (bool) get_option(self::DISMISSED_OPTION, false);
    }

    /**
     * Whether WordPress is offering a core update for the installed version.
     */
    private function isCoreUpdateAvailable(): bool
    {
        if (!function_exists('get_core_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        $updates = get_core_updates(['dismissed' => false]);

        if (empty($updates) || !isset($updates[0]->response)) {
            return false;
        }

        return $updates[0]->response === 'upgrade';
    }

    /**
     * The nonce-protected link that stores the shared dismissal flag.
     */
    private function getDismissUrl(): string
    {
        return wp_nonce_url(add_query_arg(self::DISMISS_ACTION, '1'), self::DISMISS_ACTION);
    }
}
