<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * The settings an operator can change at runtime, and the only ones.
 *
 * DELIBERATELY SMALL. Every entry here is read by code that changes behaviour
 * when it changes — a settings screen whose switches do nothing is worse than
 * no screen, because it reports a state the application does not honour. New
 * entries belong here only once something reads them.
 *
 * Layering: config/listinghub.php holds the DEFAULT, the settings table holds
 * the operator's override. Reading falls back to config, so a fresh install
 * with an empty table behaves exactly as it did before this screen existed,
 * and deleting a row is a reset rather than a breakage.
 */
class SiteSettings
{
    public const GROUP = 'moderation';

    /**
     * key => [label, config key backing the default]
     *
     * @var array<string, array{label: string, config: string}>
     */
    public const BOOLEANS = [
        'listings_require_approval' => [
            'label' => 'Новите обяви изискват одобрение преди публикуване',
            'config' => 'listinghub.moderation.listings_require_approval',
        ],
        'reviews_require_approval' => [
            'label' => 'Новите отзиви изискват одобрение преди показване',
            'config' => 'listinghub.moderation.reviews_require_approval',
        ],
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * A stored '1'/'0' wins; absent means "not configured", which falls back to
     * the config default rather than to false — an empty table must not
     * silently turn moderation off.
     */
    public function bool(string $key): bool
    {
        $default = (bool) config(self::BOOLEANS[$key]['config'], true);
        $stored = $this->settings->get(self::GROUP, $key);

        return $stored === null ? $default : (bool) (int) $stored;
    }

    /** @return array<string, bool> current effective value of every boolean */
    public function allBooleans(): array
    {
        $values = [];

        foreach (array_keys(self::BOOLEANS) as $key) {
            $values[$key] = $this->bool($key);
        }

        return $values;
    }

    /**
     * Persist the booleans. Keys outside the declared set are ignored rather
     * than stored: the settings table would otherwise accept anything a form
     * post named, and a typo'd key would look saved while nothing read it.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveBooleans(array $input): void
    {
        foreach (array_keys(self::BOOLEANS) as $key) {
            $this->settings->set(self::GROUP, $key, (string) (int) (bool) ($input[$key] ?? false));
        }
    }
}
