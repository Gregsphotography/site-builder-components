<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Installation;

use SiteBuilder\Core\Installation\DoctorCheck;
use SiteBuilder\Core\Installation\SetupAnswers;
use SiteBuilder\Core\Installation\SetupAuthorization;
use SiteBuilder\Core\Installation\SetupContribution;
use SiteBuilder\Core\Installation\SetupContributor;
use SiteBuilder\Core\Installation\SetupDiscovery;
use SiteBuilder\Core\Installation\SetupFile;
use SiteBuilder\Core\Installation\SetupInspection;
use SiteBuilder\Core\Installation\SetupRegistry;
use SiteBuilder\Core\Rendering\LayoutRegistry;

final class ComponentsSetupContributor implements SetupContributor
{
    public function __construct(private SetupInspection $inspection, private SetupRegistry $registry) {}

    public function id(): string
    {
        return 'components';
    }

    public function discover(): SetupDiscovery
    {
        return new SetupDiscovery(['standard' => config('site-builder-components.register_standard', true), 'mediaDisk' => config('site-builder-components.media.disk', 'public')]);
    }

    public function plan(SetupAnswers $answers, SetupDiscovery $discovery): SetupContribution
    {
        $configuration = ['theme' => $answers->get('presentation.mode') === 'preset' ? substr($answers->get('presentation.layout'), 11) : 'host', 'assets' => true, 'media' => ['disk' => $answers->get('storage.disk') ?? 'public', 'directory' => 'site-builder-media', 'max_kilobytes' => 10240]];

        return new SetupContribution([new SetupFile('config/site-builder-components.php', "<?php\n\nreturn ".var_export($configuration, true).";\n", 'Configure installed standard presentation and explicit media storage.', manualIfExisting: true), ...SetupContribution::assets(__DIR__.'/../../resources/dist', 'public/vendor/site-builder-components')],
            $answers->get('migrations') === 'publish' && $answers->get('features.media') && $answers->get('storage.mode') === 'local-public' ? [__DIR__.'/../../database/migrations'] : []);
    }

    public function checks(SetupAnswers $answers, SetupDiscovery $discovery): array
    {
        $checks = [new DoctorCheck('packages.components', $discovery->facts['standard'] || $answers->get('profile') === 'custom' ? 'pass' : 'unavailable', 'components-state', 'The standard library is installed; selected standard definitions must be enabled.'),
            new DoctorCheck('assets.components', $this->inspection->matchesAssets(__DIR__.'/../../resources/dist', 'public/vendor/site-builder-components') ? 'pass' : 'action', 'components-assets', 'Installed component assets must match their published bytes.', 'Apply reviewed components asset publication.'),
            new DoctorCheck('assets.gallery', $this->inspection->matchesAssets(__DIR__.'/../../resources/dist', 'public/vendor/site-builder-components') ? 'pass' : 'action', 'gallery-assets', 'The optional gallery module is shipped locally with content-versioned URLs.', 'Publish matching gallery.js and put @siteBuilderAssets after all regions in a host wrapper.')];
        if ($answers->get('presentation.mode') === 'host') {
            try {
                $view = app(LayoutRegistry::class)->view($answers->get('presentation.layout'));
                $path = view()->getFinder()->find($view);
                $bytes = file_get_contents($path);
                $hook = str_contains($bytes, '@siteBuilderAssets') && (str_contains($bytes, 'components.css') || str_contains($bytes, 'assetUrl('));
            } catch (\Throwable) {
                $hook = false;
            }
            $checks[] = $this->registry->check('assets.host-hook', $answers) ?? new DoctorCheck('assets.host-hook', $hook ? 'pass' : 'action', $hook ? 'host-hook-present' : 'host-hook-review', 'Host wrappers explicitly own stylesheet/asset emission.', $hook ? null : 'Review the host wrapper and include scoped components CSS plus @siteBuilderAssets after all regions; dynamic includes need a trusted host probe.');
        }
        $theme = $answers->get('presentation.mode') === 'preset' ? substr($answers->get('presentation.layout'), 11) : 'host';
        $configured = ($answers->get('presentation.mode') === 'host' || config('site-builder-components.theme') === $theme) && (! $answers->get('features.media') || config('site-builder-components.media.disk') === $answers->get('storage.disk'));
        $checks[] = new DoctorCheck('config.components', $configured ? 'pass' : 'action', $configured ? 'components-config-ready' : 'components-config-required', 'The standard pack theme and selected media disk must match reviewed choices.', $configured ? null : 'Apply or manually merge the reviewed components configuration.');
        $media = $answers->get('features.media');
        $local = $answers->get('storage.mode') === 'local-public';
        $schema = $this->inspection->core()->facts['schema'];
        $checks[] = new DoctorCheck('schema.media', ! $media || ! $local ? 'not-applicable' : (! $schema['available'] ? 'unavailable' : (! empty($schema['media']) ? 'pass' : 'action')), 'media-schema', 'The optional owned media schema is required only for selected local media.', $media && $local && empty($schema['media']) ? 'Publish and explicitly run the optional media migration.' : null);
        $disk = $answers->get('storage.disk');
        $public = $local && is_string($disk) && config('filesystems.disks.'.$disk.'.driver') === 'local' && config('filesystems.disks.'.$disk.'.visibility') === 'public';
        $checks[] = $this->registry->check('storage.media', $answers) ?? new DoctorCheck('storage.media', ! $media ? 'not-applicable' : ($local ? ($public ? 'pass' : 'unavailable') : 'action'), $public ? 'public-local-storage' : 'media-adapter-required', 'Media requires the explicitly selected public local disk or a verified host adapter.', $media && ! $public ? 'Configure a public local disk deliberately or register a host media readiness probe.' : null);
        $checks[] = $this->registry->check('auth.media', $answers) ?? new DoctorCheck('auth.media', ! $media ? 'not-applicable' : 'action', 'media-rights-explicit', 'Media upload/management remains separate from Page authoring.', $media ? 'Verify host media permissions or select explicit allowlist media grants.' : null);
        if ($media && $answers->get('authorization.mode') === 'allowlist' && app()->bound('site-builder.setup.active')) {
            $grants = $answers->get('authorization.mediaAbilities');
            $actors = $this->registry->selectedActors($answers);
            $permitted = $actors !== [] && array_diff(['view', 'upload', 'update'], $grants) === [] && array_all($actors, fn ($actor) => array_all(['view', 'upload', 'update'], fn ($ability) => app(SetupAuthorization::class)->media($actor, $ability) === true));
            $checks[array_key_last($checks)] = new DoctorCheck('auth.media', $permitted ? 'pass' : 'action', 'media-rights-explicit', 'Selected media capabilities are distinct explicit grants.', $permitted ? null : 'Select any still-missing media capabilities deliberately.');
        }

        return $checks;
    }
}
