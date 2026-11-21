<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SiteBuilder\Components\Albums\AlbumLibrary;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Exceptions\WriteConflict;

class AlbumLibraryTest extends DatabaseTestCase
{
    private AlbumLibrary $albums;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->albums = app(AlbumLibrary::class);
    }

    public function test_publication_is_atomic_and_all_public_reads_follow_the_same_ordered_snapshot(): void
    {
        $first = $this->photo('Summit');
        $second = $this->photo('Lake');
        $album = $this->albums->create(['title' => 'Trip', 'slug' => 'trip', 'cover' => $second, 'items' => [
            ['image' => $first], ['image' => $second, 'alt' => 'Boats on the water', 'caption' => 'At dawn'],
        ]]);
        $this->assertNull($this->albums->publishedById($album->id));
        $this->assertNull($this->albums->publishedBySlug('trip'));
        $this->assertSame([], $this->albums->publishedOptions());

        $published = $this->albums->publish($album, 0);
        $snapshot = $this->albums->publishedBySlug('trip');
        $this->assertEquals($published->published, $snapshot);
        $this->assertSame([$first, $second], array_column($snapshot['items'], 'image'));
        $this->assertSame('Summit', $snapshot['items'][0]['alt']);
        $this->assertSame('Boats on the water', $snapshot['items'][1]['alt']);
        $this->assertSame('At dawn', $snapshot['items'][1]['caption']);
        $this->assertSame($second, $snapshot['cover']);

        $edited = $this->albums->saveDraft($album, ['title' => 'Lakes', 'slug' => 'lakes', 'items' => [['image' => $second]]], $published->write_version);
        $this->assertEquals($snapshot, $this->albums->publishedById($album->id));
        $this->assertNull($this->albums->publishedBySlug('lakes'));
        $this->assertSame([$album->id => 'Trip'], $this->albums->publishedOptions('tri'));
        $this->assertSame([], $this->albums->publishedOptions('Lakes'));
        try {
            $this->albums->publish($album, $published->write_version);
            $this->fail('A stale editor must not publish another user’s draft.');
        } catch (WriteConflict $conflict) {
            $this->assertSame($edited->write_version, $conflict->currentVersion);
        }
        $this->albums->publish($album, $edited->write_version);
        $updated = $this->albums->publishedById($album->id);
        $this->assertNull($this->albums->publishedBySlug('trip'));
        $this->assertEquals($updated, $this->albums->publishedBySlug('lakes'));
        $this->assertSame([$second], array_column($updated['items'], 'image'));
        $this->assertSame($second, $updated['cover']);
    }

    public function test_duplicate_public_slug_rolls_back_the_entire_publication(): void
    {
        $data = ['title' => 'First', 'slug' => 'trip', 'items' => [['image' => $this->photo('Lake')]]];
        $first = $this->albums->publish($this->albums->create($data), 0);
        $second = $this->albums->publish($this->albums->create([...$data, 'title' => 'Second', 'slug' => 'other']), 0);
        $edited = $this->albums->saveDraft($second, $data, $second->write_version);
        try {
            $this->albums->publish($second, $edited->write_version);
            $this->fail('The same URL cannot be assigned to two published albums.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }
        $this->assertEquals($first->published, $this->albums->publishedBySlug('trip'));
        $this->assertEquals($second->published, $this->albums->publishedBySlug('other'));
        $this->assertSame($edited->write_version, $second->fresh()->write_version);
    }

    public function test_unpublishing_removes_all_public_reads_and_preserves_unpublished_edits_for_republication(): void
    {
        $data = ['title' => 'Trip', 'slug' => 'trip', 'items' => [['image' => $this->photo('Lake')]]];
        $published = $this->albums->publish($this->albums->create($data), 0);
        $unchanged = $this->albums->saveDraft($published, $data, $published->write_version);
        $this->assertSame($published->published_version, $unchanged->write_version);
        $edited = $this->albums->saveDraft($unchanged, [...$data, 'title' => 'Summer trip'], $unchanged->write_version);
        $this->assertNotSame($edited->published_version, $edited->write_version);
        try {
            $this->albums->unpublish($published, $published->write_version);
            $this->fail('A stale editor must not remove the live album.');
        } catch (WriteConflict $exception) {
            $this->assertSame($edited->write_version, $exception->currentVersion);
        }
        $draft = $this->albums->unpublish($edited, $edited->write_version);
        $this->assertNull($this->albums->publishedBySlug('trip'));
        $this->assertNull($this->albums->publishedById($draft->id));
        $this->assertSame([], $this->albums->publishedOptions());
        $this->assertNull($draft->published_version);
        $this->assertNull($draft->published_at);
        $this->assertEquals($edited->draft, $draft->draft);
        $republished = $this->albums->publish($draft, $draft->write_version);
        $this->assertSame([$draft->id => 'Summer trip'], $this->albums->publishedOptions());
        $this->assertSame($republished->write_version, $republished->published_version);
    }

    public function test_empty_draft_can_be_saved_and_only_readable_described_or_decorative_photos_can_be_published(): void
    {
        $data = ['title' => 'Trip', 'slug' => 'trip'];
        $draft = $this->albums->create($data);
        try {
            $this->albums->publish($draft, 0);
            $this->fail('Publishing an empty album should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        try {
            $this->albums->saveDraft($draft, $data + ['cover' => 'foreign/path.png'], 0);
            $this->fail('An unknown cover should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cover', $exception->errors());
        }
        $image = $this->photo('');
        $draft = $this->albums->saveDraft($draft, $data + ['items' => [['image' => $image]]], 0);
        try {
            $this->albums->publish($draft, $draft->write_version);
            $this->fail('A photo needs alternative text or a decorative designation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.alt', $exception->errors());
        }
        $draft = $this->albums->saveDraft($draft, $data + ['items' => [['image' => $image, 'decorative' => true]]], $draft->write_version);
        $published = $this->albums->publish($draft, $draft->write_version);
        $this->assertTrue($published->published['items'][0]['decorative']);
        Storage::disk('public')->delete($image);
        $this->expectException(ValidationException::class);
        $this->albums->publish($draft, $published->write_version);
    }

    public function test_hosts_without_album_tables_have_empty_public_lookups(): void
    {
        Schema::drop('site_builder_albums');
        $this->assertFalse($this->albums->available());
        $this->assertSame([], $this->albums->publishedOptions());
        $this->assertNull($this->albums->publishedBySlug('trip'));
        $this->assertNull($this->albums->publishedById('01a1c755-5e79-7611-88d1-d9f4014797ec'));
    }

    public function test_video_and_caption_track_references_are_rejected_by_album_writes(): void
    {
        $media = app(MediaLibrary::class);
        $video = $media->storeVideo(new UploadedFile(__DIR__.'/../../resources/dist/examples/intro.mp4', 'sample.mp4', test: true));
        $track = $media->storeTrack(UploadedFile::fake()->createWithContent('captions.vtt', "WEBVTT\n\n00:00.000 --> 00:01.000\nA silent sample\n"));
        try {
            $this->albums->create([
                'title' => 'Trip', 'slug' => 'trip', 'cover' => $video->path,
                'items' => [['image' => $track->path, 'alt' => 'Caption track']],
            ]);
            $this->fail('Albums must accept only photos from the media library.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cover', $exception->errors());
            $this->assertArrayHasKey('items.0.image', $exception->errors());
        }
    }

    private function photo(string $alt): string
    {
        return app(MediaLibrary::class)->store(UploadedFile::fake()->image('photo.png'), ['en' => $alt])->path;
    }
}
