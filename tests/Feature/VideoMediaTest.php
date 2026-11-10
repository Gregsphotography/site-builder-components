<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\Rendering\EmbedUrls;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;

class VideoMediaTest extends DatabaseTestCase
{
    private array $uploads = [];

    protected function tearDown(): void
    {
        foreach ($this->uploads as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'builder-video-');
        $this->uploads[] = $path;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
    }

    public function test_video_and_caption_uploads_never_appear_in_legacy_image_choices_or_image_rendering(): void
    {
        $library = app(MediaLibrary::class);
        $file = $this->upload('sample.mp4', file_get_contents(__DIR__.'/../../resources/dist/examples/intro.mp4'));
        $video = $library->storeVideo($file, 'Sample clip');
        $track = $library->storeTrack($this->upload('captions.vtt', "WEBVTT\n\n00:00.000 --> 00:01.000\nA silent sample\n"));
        $this->assertSame('video', $library->kind($video->path));
        $this->assertSame('track', $library->kind($track->path));
        $this->assertSame([], $library->options('en'));
        $this->assertArrayHasKey($video->path, $library->options('en', 'video'));
        $this->assertArrayHasKey($track->path, $library->options('en', 'track'));
        $image = new Document([new Node('wrong-kind', 'standard.image', props: new JsonObject(['image' => $video->path]), translations: new JsonObject(['en' => (object) ['alt' => 'Not an image']]))]);
        $this->assertStringNotContainsString('<img', app(DocumentRenderer::class)->render($image, new RenderContext('en'))->html->toHtml());
        foreach ([$video, $track] as $asset) {
            @unlink(storage_path('app/public/'.$asset->path));
        }
    }

    public function test_disguised_video_and_invalid_caption_files_are_rejected(): void
    {
        foreach ([['video', 'fake.mp4', '<html>not a video</html>'], ['track', 'captions.vtt', '<script>not captions</script>']] as [$kind, $name, $content]) {
            try {
                $library = app(MediaLibrary::class);
                $kind === 'video' ? $library->storeVideo($this->upload($name, $content)) : $library->storeTrack($this->upload($name, $content));
                $this->fail('Disguised media was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_external_embeds_accept_only_supported_urls_and_never_emit_iframes_before_interaction(): void
    {
        $urls = app(EmbedUrls::class);
        $this->assertSame('https://www.youtube-nocookie.com/embed/abcdefghijk?rel=0', $urls->video('youtube', 'https://youtu.be/abcdefghijk'));
        $this->assertNull($urls->video('youtube', 'https://youtube.com.attacker.test/watch?v=abcdefghijk'));
        $this->assertNull($urls->map('https://attacker.test/maps/embed?pb=value'));
        $doc = new Document([new Node('video', 'standard.video', props: new JsonObject(['provider' => 'youtube']), translations: new JsonObject(['en' => (object) ['title' => 'Example video', 'url' => 'https://youtu.be/abcdefghijk']]))]);
        $public = app(DocumentRenderer::class)->render($doc, new RenderContext('en'))->html->toHtml();
        $preview = app(DocumentRenderer::class)->render($doc, new RenderContext('en', 'preview'))->html->toHtml();
        $this->assertStringContainsString('data-sb-load-embed', $public);
        $this->assertStringNotContainsString('<iframe', $public);
        $this->assertStringNotContainsString('data-sb-load-embed', $preview);
        $this->assertStringNotContainsString('<video', $preview);
    }
}
