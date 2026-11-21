<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SiteBuilder\Components\Albums\AlbumLibrary;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\PageResponse;

class AlbumEmbedTest extends DatabaseTestCase
{
    public function test_multiple_published_pages_follow_the_same_album_only_on_publication(): void
    {
        Storage::fake('public');
        $photo = app(MediaLibrary::class)->store(UploadedFile::fake()->image('summer.jpg'));
        $albums = app(AlbumLibrary::class);
        $album = $albums->create([
            'title' => 'Summer trip', 'slug' => 'summer-trip',
            'items' => [['image' => $photo->path, 'alt' => 'Mountain lake', 'caption' => 'First day']],
        ]);
        $document = new Document([new Node('album', 'standard.album', props: new JsonObject(['album' => $album->id]))]);
        $validator = app(DocumentValidator::class);
        $this->assertSame(['unavailable_album'], array_column($validator->diagnostics($document, publishing: true), 'code'));
        $empty = new Document([new Node('empty', 'standard.album')]);
        $this->assertSame(['required_for_publish'], array_column($validator->diagnostics($empty, publishing: true), 'code'));

        $copy = app(DocumentCopies::class)->copy($document);
        $this->assertSame($album->id, $copy->nodes[0]->props->get('album'));
        $pages = array_map(fn (Document $doc): Page => Page::factory()->create([
            'layout' => 'components.minimal', 'status' => 'published', 'published_content' => $doc,
        ]), [$document, $copy]);
        $render = fn (Page $page): string => app(PageResponse::class)->render($page->fresh(), 'en')->getContent();
        $this->assertStringNotContainsString('Mountain lake', $render($pages[0]));
        $album = $albums->publish($album, $album->write_version);
        $this->assertSame([], $validator->validate($document, publishing: true));

        $changed = $album->draft;
        $changed['title'] = 'Winter trip';
        $changed['items'][0]['caption'] = 'Last day';
        $album = $albums->saveDraft($album, $changed, $album->write_version);
        foreach ($pages as $page) {
            $html = $render($page);
            $this->assertStringContainsString('Summer trip', $html);
            $this->assertStringContainsString('First day', $html);
            $this->assertStringContainsString('alt="Mountain lake"', $html);
            $this->assertStringContainsString('gallery.js?v=', $html);
            $this->assertStringNotContainsString('Winter trip', $html);
            $this->assertStringNotContainsString('Last day', $html);
        }

        $albums->publish($album, $album->write_version);
        foreach ($pages as $page) {
            $html = $render($page);
            $this->assertStringContainsString('Winter trip', $html);
            $this->assertStringContainsString('Last day', $html);
            $this->assertStringNotContainsString('First day', $html);
        }
        Storage::disk('public')->delete($photo->path);
        $this->assertStringContainsString('Image unavailable', $render($pages[0]));
    }
}
