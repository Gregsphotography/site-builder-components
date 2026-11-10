<?php

declare(strict_types=1);

namespace SiteBuilder\Components;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SiteBuilder\Components\Commands\InstallCommand;
use SiteBuilder\Components\Design\StandardDesignTheme;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\Media\LibraryMediaResolver;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\References\StandardCopyReferenceInspector;
use SiteBuilder\Components\Rendering\StoredNavigation;
use SiteBuilder\Components\Snapshots\MenuSnapshotSource;
use SiteBuilder\Components\Snapshots\MetadataSnapshotSource;
use SiteBuilder\Components\Templates\PatternCatalog;
use SiteBuilder\Components\Templates\TemplateCatalog;
use SiteBuilder\Components\Themes\ThemeCatalog;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Composition\StartingPointDescriptor;
use SiteBuilder\Core\Composition\StartingPointRegistry;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Contracts\CopyReferenceInspector;
use SiteBuilder\Core\Contracts\DocumentAuthoringConstraint;
use SiteBuilder\Core\Contracts\DocumentConstraint;
use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\Contracts\NavigationProvider;
use SiteBuilder\Core\Contracts\RenderedDocumentProcessor;
use SiteBuilder\Core\Design\DesignCatalog;
use SiteBuilder\Core\Installation\SetupRegistry;
use SiteBuilder\Core\Rendering\LayoutRegistry;
use SiteBuilder\Core\Rendering\PathMediaResolver;
use SiteBuilder\Core\Rendering\PublicAsset;
use SiteBuilder\Core\Rendering\PublicAssetRegistry;
use SiteBuilder\Core\Snapshots\SnapshotSourceRegistry;

final class ComponentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(SetupRegistry::class, fn ($registry) => $registry->register($this->app->make(Installation\ComponentsSetupContributor::class)));
        $this->mergeConfigFrom(__DIR__.'/../config/site-builder-components.php', 'site-builder-components');
        $this->mergeConfigFrom(__DIR__.'/../config/site-builder-forms.php', 'site-builder-forms');
        $this->app->scoped(Forms\FormRuntime::class);
        $this->app->tag([Forms\FormAuthoringConstraint::class], DocumentAuthoringConstraint::class);
        $this->app->singleton(TemplateCatalog::class);
        $this->app->singleton(ThemeCatalog::class);
        $this->app->scoped(MediaLibrary::class);
        $this->app->singleton(StandardExamples::class);
        $this->app->tag([Rendering\OutlineProcessor::class], RenderedDocumentProcessor::class);
        $this->app->tag([Rendering\AnchorConstraint::class], DocumentConstraint::class);
        $this->app->singleton(StandardDesignTheme::class);
        $this->app->afterResolving(PublicAssetRegistry::class, function ($registry): void {
            $digest = substr(hash_file('sha256', __DIR__.'/../resources/dist/gallery.js'), 0, 16);
            $registry->register('components.gallery', new PublicAsset('module', '/vendor/site-builder-components/gallery.js?v='.$digest));
            $digest = substr(hash_file('sha256', __DIR__.'/../resources/dist/interactions.js'), 0, 16);
            $registry->register('components.interactions', new PublicAsset('module', '/vendor/site-builder-components/interactions.js?v='.$digest));
            $digest = substr(hash_file('sha256', __DIR__.'/../resources/dist/collections.js'), 0, 16);
            $registry->register('components.collections', new PublicAsset('module', '/vendor/site-builder-components/collections.js?v='.$digest));
            $digest = substr(hash_file('sha256', __DIR__.'/../resources/dist/embeds.js'), 0, 16);
            $registry->register('components.embeds', new PublicAsset('module', '/vendor/site-builder-components/embeds.js?v='.$digest));
            $digest = substr(hash_file('sha256', __DIR__.'/../resources/dist/forms.js'), 0, 16);
            $registry->register('components.forms', new PublicAsset('module', '/vendor/site-builder-components/forms.js?v='.$digest));
        });
        $this->app->tag([StandardCopyReferenceInspector::class], CopyReferenceInspector::class);
        $this->app->bindIf(NavigationProvider::class, StoredNavigation::class);
        $this->app->afterResolving(SnapshotSourceRegistry::class, function ($registry): void {
            $registry->register('standard.metadata', $this->app->make(MetadataSnapshotSource::class));
            $registry->register('standard.menus', $this->app->make(MenuSnapshotSource::class));
        });
        $this->app->extend(MediaResolver::class, fn (MediaResolver $resolver): MediaResolver => $resolver instanceof PathMediaResolver ? $this->app->make(LibraryMediaResolver::class) : $resolver);
    }

    public function boot(): void
    {
        $this->app['events']->listen(MigrationsEnded::class, function (): void {
            if ($this->app->resolved(Forms\FormRuntime::class)) {
                $this->app->make(Forms\FormRuntime::class)->clearAvailabilityCache();
            }
            if ($this->app->resolved(MediaLibrary::class)) {
                $this->app->make(MediaLibrary::class)->clearAvailabilityCache();
            }
        });
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'site-builder-components');
        $components = $this->app->make(ComponentRegistry::class);
        if (config('site-builder-components.register_standard', true)) {
            $components->register(Definitions\Form::class);
            foreach ([Definitions\Video::class, Definitions\Location::class] as $definition) {
                $components->register($definition);
            }
            foreach ([Definitions\Tabs::class, Definitions\TabPanel::class, Definitions\Accordion::class, Definitions\AccordionItem::class, Definitions\Table::class, Definitions\TableOfContents::class, Definitions\Breadcrumbs::class] as $definition) {
                $components->register($definition);
            }
            foreach ([Definitions\Heading::class, Definitions\Buttons::class, Definitions\Icon::class, Definitions\IconList::class, Definitions\SocialLinks::class, Definitions\Divider::class, Definitions\Anchor::class] as $definition) {
                $components->register($definition);
            }
            foreach ([Definitions\Gallery::class, Definitions\Testimonials::class, Definitions\Brand::class, Definitions\Navigation::class, Definitions\Hero::class, Definitions\Text::class, Definitions\Image::class, Definitions\ImageText::class, Definitions\Cards::class, Definitions\CallToAction::class, Definitions\Faq::class, Definitions\ContactDetails::class, Definitions\Section::class, Definitions\Container::class, Definitions\Columns::class, Definitions\Column::class] as $definition) {
                $components->register($definition);
            }
        }
        if (config('site-builder-components.register_demo_compatibility', true)) {
            foreach ([Compatibility\Hero::class, Compatibility\Text::class, Compatibility\ImageText::class, Compatibility\CallToAction::class] as $definition) {
                $components->register($definition);
            }
        }
        $layouts = $this->app->make(LayoutRegistry::class);
        $layouts->register('components.default', 'site-builder-components::layouts.site', ['header', 'body', 'footer']);
        foreach (['minimal', 'business', 'editorial', 'host'] as $preset) {
            $layouts->register('components.'.$preset, 'site-builder-components::layouts.'.$preset, ['header', 'body', 'footer']);
        }
        $this->registerStartingPoints();
        $this->app->booted(function (): void {
            if (config('site-builder-forms.enabled', false) && ! $this->app->routesAreCached()) {
                $prefix = trim((string) config('site-builder-forms.route_prefix', '_site-builder/forms'), '/');
                if (! preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~D', $prefix)) {
                    throw new \InvalidArgumentException('Use a trusted slash-separated forms route prefix.');
                }
                Route::post('/'.$prefix.'/submit', Forms\SubmissionController::class)->middleware(config('site-builder-forms.middleware', ['web', 'throttle:10,1']))->name('site-builder.forms.submit');
            }
        });
        $design = $this->app->make(StandardDesignTheme::class);
        $this->app->make(DesignCatalog::class)->register($design);
        $this->app->make(DesignCatalog::class)->bindDefault($design->defaultTheme(...));
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, Commands\FormsInstallCommand::class, Commands\FormsDispatchCommand::class, Commands\FormsPruneCommand::class, Commands\FormsDoctorCommand::class, Commands\MediaDoctorCommand::class]);
            $this->publishes([__DIR__.'/../config/site-builder-components.php' => config_path('site-builder-components.php')], 'site-builder-components-config');
            $this->publishes([__DIR__.'/../resources/dist' => public_path('vendor/site-builder-components')], ['site-builder-components-assets', 'site-builder-components-update']);
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/site-builder-components')], 'site-builder-components-views');
        }
    }

    private function registerStartingPoints(): void
    {
        $registry = $this->app->make(StartingPointRegistry::class);
        $examples = $this->app->make(StandardExamples::class);
        foreach ($examples->entries() as $type => $entry) {
            $registry->register(new StartingPointDescriptor(
                id: 'component:'.$type,
                version: 1,
                label: $entry['label'],
                category: $entry['category'],
                description: $entry['description'],
                keywords: $entry['keywords'],
                variants: $entry['variants'],
                sourceKind: 'component',
            ), fn (string $locale, ?string $variant): Document => $examples->document($type, $locale, $variant));
        }
        if (config('site-builder-components.register_standard', true)) {
            $templates = $this->app->make(TemplateCatalog::class);
            $patterns = $this->app->make(PatternCatalog::class);
            $practical = $this->app->make(Templates\PracticalPatterns::class);
            foreach ($practical->all() as $pattern) {
                $registry->register($pattern, fn (string $locale, ?string $variant): Document => $practical->snapshot($pattern->id, $locale));
            }
            foreach ($patterns->all() as $pattern) {
                $registry->register($pattern, fn (string $locale, ?string $variant): Document => $patterns->snapshot($pattern->id, $locale, $variant));
            }
            foreach ($templates->all() as $template) {
                $registry->register(new StartingPointDescriptor(
                    id: 'template:'.$template->id,
                    version: $template->version,
                    label: $template->label,
                    category: 'Page templates',
                    description: $template->description,
                    keywords: ['page', 'template', strtolower($template->label)],
                    sourceKind: 'code-template',
                ), fn (string $locale, ?string $variant): Document => $templates->snapshot($template->id, $locale));
            }
        }
    }
}
