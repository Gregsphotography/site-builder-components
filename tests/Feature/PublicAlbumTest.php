<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use SiteBuilder\Components\Albums\AlbumLibrary;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\Tests\DatabaseTestCase;

class PublicAlbumTest extends DatabaseTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('site-builder.routes.prefix', 'photos');
        $app['config']->set('site-builder-components.album_routes', true);
    }

    public function test_shared_url_serves_only_the_published_cover_and_gallery_under_the_public_prefix(): void
    {
        Storage::fake('public');
        $media = app(MediaLibrary::class);
        $cover = $media->store(UploadedFile::fake()->image('cover.jpg'));
        $first = $media->store(UploadedFile::fake()->image('glacier.jpg'));
        $second = $media->store(UploadedFile::fake()->image('river.jpg'));
        $albums = app(AlbumLibrary::class);
        $album = $albums->create([
            'title' => 'Summer trip', 'slug' => 'summer-trip', 'cover' => $cover->path,
            'items' => [
                ['image' => $first->path, 'alt' => 'Glacier at sunrise', 'caption' => 'On the mountain'],
                ['image' => $second->path, 'alt' => 'River in the valley', 'caption' => 'Downstream'],
            ],
        ]);
        $url = route('site-builder.album', ['slug' => 'summer-trip'], false);
        $this->assertSame('/photos/albums/summer-trip', $url);
        $this->get($url)->assertNotFound();
        $this->get('/albums/summer-trip')->assertNotFound();

        $album = $albums->publish($album, $album->write_version);
        $this->get($url)->assertOk()
            ->assertSee('<h1>Summer trip</h1>', false)
            ->assertSeeInOrder([$cover->path, 'Glacier at sunrise', 'On the mountain', 'River in the valley', 'Downstream'])
            ->assertSee('data-sb-gallery-dialog', false)
            ->assertSee('gallery.js?v=', false)
            ->assertSee('property="og:image" content="'.url('/storage/'.$cover->path).'"', false);

        $album = $albums->saveDraft($album, [
            ...$album->draft, 'slug' => 'alpine-summer', 'title' => 'Alpine summer',
            'items' => array_reverse($album->draft['items']), 'lightbox' => false,
        ], $album->write_version);
        $this->get($url)->assertOk()->assertDontSee('Alpine summer');
        $this->get('/photos/albums/alpine-summer')->assertNotFound();
        $albums->publish($album, $album->write_version);
        $this->get($url)->assertNotFound();
        $this->get('/photos/albums/alpine-summer')->assertOk()->assertSee('Alpine summer')
            ->assertSeeInOrder(['Downstream', 'On the mountain'])->assertDontSee('gallery.js');
    }

    public function test_absent_optional_album_schema_returns_not_found(): void
    {
        Schema::drop('site_builder_albums');
        $this->get('/photos/albums/summer-trip')->assertNotFound();
    }
}
