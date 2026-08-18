<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

/**
 * Prompts site administrators to update WordPress while the install is behind the latest release.
 *
 * Each plugin Strauss-prefixes its own copy, so cross-plugin state is carried by string keys only:
 * prefixing rewrites namespaces and class names, not literals.
 */
class CoreUpdateNotice
{
    /**
     * Set of dismissed offers, keyed "{version}|{locale}" like WordPress' dismissed_update_core.
     * A set, so dismissing 6.9 does not also hide a 6.8.1 security release.
     *
     * Shared with the other plugins showing this notice. Do not prefix it per plugin.
     */
    public const DISMISSED_OPTION = 'nx_wp_core_update_notice_dismissed';

    /**
     * The query argument and nonce action carried by the dismiss link. Shared, so whichever
     * plugin's admin_init runs first stores the dismissal. The argument's value is the offer key
     * being dismissed.
     */
    public const DISMISS_ACTION = 'nx-dismiss-wp-core-update-notice';

    /**
     * Highest version registered this request wins the render, so an updated plugin controls the
     * notice site-wide. Bump when the copy or behaviour changes, not for the package version.
     */
    public const NOTICE_VERSION = '1.0.0';

    /**
     * Global holding the highest notice version registered this request.
     */
    public const VERSION_KEY = 'nx_wp_core_update_notice_version';

    /**
     * Global marking that a copy already rendered this request. A static property cannot do this:
     * each plugin prefixes the class, so each copy gets its own.
     */
    public const RENDER_GUARD = 'nx_wp_core_update_notice_rendered';

    /**
     * The capability a user needs to see the notice and to dismiss it.
     */
    private const CAPABILITY = 'update_core';

    /**
     * @var array<string, string>
     */
    private array $strings;

    /**
     * @param array<string, string> $strings Optional translated copy, keyed heading, body and
     *                                       dismiss. Supply this from the consuming plugin so the
     *                                       strings land in its own text domain; the defaults are
     *                                       English and untranslated. Missing keys fall back.
     */
    public function __construct(array $strings = [])
    {
        $defaults = [
            'heading' => 'Keep your site protected. Update to the latest version of WordPress.',
            'body' => 'Your site is running on an outdated version of WordPress, which can leave it'
                . ' vulnerable to security issues. To decrease your risk of exposure, please update'
                . ' your WordPress install to the latest version.',
            'dismiss' => 'Dismiss this notice.',
        ];

        $this->strings = array_merge($defaults, array_filter($strings, 'is_string'));
    }

    /**
     * Call on `init` or `admin_init`. Registering from inside `admin_notices` claims the version
     * without this copy's own callback being reached, suppressing the notice for the request.
     */
    public function register(): void
    {
        $this->claimVersion();

        add_action('admin_init', [$this, 'handleDismissal']);
        add_action('admin_notices', [$this, 'render']);

        // Multisite puts update-core.php behind the network admin, which admin_notices never reaches.
        add_action('network_admin_notices', [$this, 'render']);
    }

    /**
     * Record the dismissal when the notice's dismiss control is used.
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

        $key = $this->dismissalKeyFromRequest();

        if ($key !== null) {
            $dismissed = $this->dismissedOffers();
            $dismissed[$key] = true;

            update_site_option(self::DISMISSED_OPTION, $dismissed);
        }

        wp_safe_redirect(remove_query_arg([self::DISMISS_ACTION, '_wpnonce']));

        $this->terminate();
    }

    /**
     * Render the notice, at most once per request across every plugin that registers it.
     *
     * @hook admin_notices
     * @hook network_admin_notices
     */
    public function render(): void
    {
        if (!empty($GLOBALS[self::RENDER_GUARD])) {
            return;
        }

        if (!$this->isNewestRegistered()) {
            return;
        }

        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $offer = $this->currentOffer();

        if ($offer === null || $this->isDismissed($offer)) {
            return;
        }

        $GLOBALS[self::RENDER_GUARD] = true;

        /*
         * The dismiss control is a link so the shared record can be stored server side, without a
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
            esc_url($this->getDismissUrl($this->offerKey($offer))),
            esc_html($this->strings['dismiss'])
        );
    }

    /**
     * @return bool True while an offer is available that has not already been dismissed.
     */
    public function shouldDisplay(): bool
    {
        $offer = $this->currentOffer();

        if ($offer === null) {
            return false;
        }

        return !$this->isDismissed($offer);
    }

    /**
     * Whether this copy is the highest-versioned one registered this request. Ties fall to
     * whichever renders first, settled by the render guard.
     */
    public function isNewestRegistered(): bool
    {
        $registered = $GLOBALS[self::VERSION_KEY] ?? null;

        if (!is_string($registered) || $registered === '') {
            return true;
        }

        return version_compare(static::NOTICE_VERSION, $registered, '>=');
    }

    /**
     * End the request after redirecting. Overridable so the dismissal path can be tested.
     */
    protected function terminate(): void
    {
        exit;
    }

    /**
     * Record this copy's notice version if it beats whatever another plugin already registered.
     */
    private function claimVersion(): void
    {
        $registered = $GLOBALS[self::VERSION_KEY] ?? null;

        if (
            !is_string($registered)
            || $registered === ''
            || version_compare(static::NOTICE_VERSION, $registered, '>')
        ) {
            $GLOBALS[self::VERSION_KEY] = static::NOTICE_VERSION;
        }
    }

    /**
     * The offer key from the dismiss link, so the dismissal records what the user was shown rather
     * than whatever the transient holds a request later. Falls back to the current offer.
     */
    private function dismissalKeyFromRequest(): ?string
    {
        $raw = $_GET[self::DISMISS_ACTION];

        if (is_string($raw) && $raw !== '') {
            $candidate = sanitize_text_field(wp_unslash($raw));

            if (preg_match('/^[A-Za-z0-9._+-]{1,32}\|[A-Za-z0-9_-]{1,32}$/', $candidate) === 1) {
                return $candidate;
            }
        }

        $offer = $this->currentOffer();

        return $offer === null ? null : $this->offerKey($offer);
    }

    /**
     * @param array{version: string, locale: string} $offer
     */
    private function isDismissed(array $offer): bool
    {
        return array_key_exists($this->offerKey($offer), $this->dismissedOffers());
    }

    /**
     * The offers already dismissed, keyed "{version}|{locale}".
     *
     * @return array<string, mixed>
     */
    private function dismissedOffers(): array
    {
        $dismissed = get_site_option(self::DISMISSED_OPTION, []);

        return is_array($dismissed) ? $dismissed : [];
    }

    /**
     * @param array{version: string, locale: string} $offer
     */
    private function offerKey(array $offer): string
    {
        return $offer['version'] . '|' . $offer['locale'];
    }

    /**
     * The update WordPress is currently offering, or null when the install is up to date.
     *
     * @return array{version: string, locale: string}|null
     */
    private function currentOffer(): ?array
    {
        if (!function_exists('get_core_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        $updates = get_core_updates(['dismissed' => false]);

        if (!is_array($updates) || $updates === []) {
            return null;
        }

        $update = $updates[0];

        if (!is_object($update) || !isset($update->response) || $update->response !== 'upgrade') {
            return null;
        }

        // The offered release, not the installed one: get_core_updates() names it "current".
        $version = isset($update->current) && is_string($update->current) ? $update->current : '';

        if ($version === '') {
            return null;
        }

        $locale = isset($update->locale) && is_string($update->locale) ? $update->locale : 'en_US';

        return ['version' => $version, 'locale' => $locale];
    }

    /**
     * The nonce-protected link that records the dismissal, carrying the offer it applies to.
     */
    private function getDismissUrl(string $key): string
    {
        return (string) wp_nonce_url(
            add_query_arg(self::DISMISS_ACTION, rawurlencode($key)),
            self::DISMISS_ACTION
        );
    }
}
