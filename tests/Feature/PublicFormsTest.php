<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use SiteBuilder\Components\Definitions\Form;
use SiteBuilder\Components\Forms\DeliverNotification;
use SiteBuilder\Components\Forms\EnquiryMail;
use SiteBuilder\Components\Forms\FormNotification;
use SiteBuilder\Components\Forms\FormRuntime;
use SiteBuilder\Components\Forms\NotificationProfile;
use SiteBuilder\Components\Forms\Submission;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Composition\CompositionData;
use SiteBuilder\Core\Composition\PublishedPartReference;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Persistence\AuthoringConstraints;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\PageResponse;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\SiteParts\SitePartCommands;
use SiteBuilder\Core\SiteParts\SitePartLocks;
use SiteBuilder\Core\SiteParts\SitePartPayload;

class PublicFormsTest extends DatabaseTestCase
{
    private const INSTALLATION = '019a0000-0000-7000-8000-000000000001';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
        $app['config']->set('site-builder-forms.enabled', true);
        $app['config']->set('site-builder-forms.installation_id', self::INSTALLATION);
        $app['config']->set('queue.connections.database', ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 180]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/forms-migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        Bus::fake([DeliverNotification::class]);
        Mail::fake();
    }

    public function test_public_submission_is_private_idempotent_and_snapshots_notification_recipients(): void
    {
        [$page, $profile, $token] = $this->published();
        $this->assertTrue(app(FormRuntime::class)->available());
        $html = app(PageResponse::class)->render($page, 'en');
        $this->assertSame('no-store, private', $html->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private-inbox@example.test', $html->getContent());
        $payload = ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'avery@example.test', 'message' => 'Please tell me more.']];
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertOk()->assertJson(['accepted' => true]);
        $profile->update(['recipients' => ['changed@example.test']]);
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertOk();
        $this->assertSame(1, Submission::query()->count());
        $this->assertSame(['private-inbox@example.test'], FormNotification::query()->sole()->recipients);
        Bus::assertDispatchedTimes(DeliverNotification::class, 1);
        Mail::assertNothingSent();
        $payload['fields']['message'] = 'Different content with the same submission token';
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertUnprocessable();
        $this->assertSame('Please tell me more.', Submission::query()->sole()->fields['message']['value']);
    }

    public function test_schema_validation_rejects_extra_fields_and_retains_no_partial_submission(): void
    {
        [, , $token] = $this->published();
        $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'not an email', 'message' => 'Hello']])->assertUnprocessable()->assertJsonValidationErrors('fields.email');
        $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello', 'recipient' => 'attacker@example.test']])->assertUnprocessable();
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_changed_unpublished_hidden_or_disabled_forms_cannot_be_submitted(): void
    {
        [$page, $profile, $token] = $this->published();
        $payload = ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']];
        $page->forceFill(['status' => 'draft'])->save();
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertConflict();
        $page->forceFill(['status' => 'published'])->save();
        $profile->update(['active' => false]);
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertConflict();
        $profile->update(['active' => true]);
        $original = $page->published_content->nodes[0];
        $changed = new Node($original->id, $original->type, props: $original->props, translations: new JsonObject(['en' => (object) [...(array) $original->translations->get('en'), 'heading' => 'Changed form']]));
        $page->forceFill(['published_content' => new Document([$changed])])->save();
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertConflict();
        $page->forceFill(['published_content' => new Document([new Node($original->id, $original->type, props: $original->props, translations: $original->translations, visibility: 'hidden')])])->save();
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertConflict();
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_tampered_expired_and_honeypot_requests_do_not_create_enquiries(): void
    {
        [, , $token] = $this->published();
        $payload = ['context' => $token.'tampered', 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']];
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertUnprocessable();
        $payload['context'] = $token;
        $this->postJson(route('site-builder.forms.submit'), [...$payload, 'website' => 'bot'])->assertOk();
        $this->travel(121)->minutes();
        $this->postJson(route('site-builder.forms.submit'), $payload)->assertUnprocessable();
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_queue_failure_retains_the_enquiry_and_retryable_outbox_record(): void
    {
        [, , $token] = $this->published();
        config(['site-builder-forms.queue_connection' => 'sync', 'queue.connections.sync' => ['driver' => 'sync']]);
        $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']])->assertOk();
        $this->assertSame(1, Submission::query()->count());
        $this->assertSame('failed', FormNotification::query()->sole()->status);
        Mail::assertNothingSent();
        config(['site-builder-forms.queue_connection' => 'database']);
        $this->travel(2)->minutes();
        $this->artisan('site-builder:forms-dispatch')->assertExitCode(0);
        $this->assertSame('queued', FormNotification::query()->sole()->status);
    }

    public function test_duplicate_jobs_send_once_and_mark_the_notification_sent_to_transport(): void
    {
        [, , $token] = $this->published();
        $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => '<script>plain enquiry text</script>']])->assertOk();
        $record = FormNotification::query()->sole();
        (new DeliverNotification($record->id))->handle();
        (new DeliverNotification($record->id))->handle();
        Mail::assertSent(EnquiryMail::class, 1);
        $this->assertSame('sent', $record->fresh()->status);
        $this->assertSame(1, $record->fresh()->attempts);
    }

    public function test_translated_repeated_pinned_forms_have_independent_occurrences_and_removed_mounts_are_rejected(): void
    {
        [$page] = $this->published();
        $this->artisan('migrate', ['--path' => realpath(dirname((new \ReflectionClass(\SiteBuilder\Core\SiteBuilderServiceProvider::class))->getFileName(), 2).'/database/site-parts-migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        config(['site-builder.site_parts.preview_layout' => 'components.minimal']);
        Gate::before(fn (): bool => true);
        $actor = new GenericUser(['id' => 'editor-uuid']);
        $commands = app(SitePartCommands::class);
        $part = $commands->create($actor, 'section', ['name' => 'Contact section'], SitePartPayload::document('section', $page->published_content))->part;
        $lease = app(SitePartLocks::class)->acquire($actor, $part->id);
        $part = $commands->publish($actor, $part->id, $lease->token, 0, app(CanonicalJson::class)->digest($part->draft_payload))->part;
        $revision = $part->revisions()->findOrFail($part->current_revision_id);
        $reference = new PublishedPartReference($part->id, $revision->id, $revision->digest);
        $nodes = array_map(fn ($id) => new Node($id, 'core.global-reference', props: new JsonObject(['revision' => $reference->jsonSerialize()])), ['first-mount', 'second-mount']);
        $document = CompositionData::promote(new Document($nodes));
        $page->forceFill(['published_content' => $document])->save();
        $page->slugs()->create(['locale' => 'de', 'slug' => 'kontakt-'.$page->id]);
        $html = app(PageResponse::class)->render($page->fresh(), 'de')->getContent();
        preg_match_all('/name="context" value="([^"]+)"/', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $tokens = array_map(html_entity_decode(...), $matches[1]);
        $runtime = app(FormRuntime::class);
        $this->assertNotSame($runtime->resolve($tokens[0])['address'], $runtime->resolve($tokens[1])['address']);
        foreach ($tokens as $token) {
            $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']])->assertOk();
        }
        $this->assertSame(2, Submission::query()->where('locale', 'de')->count());
        $page->forceFill(['published_content' => CompositionData::promote(new Document([$nodes[1]]))])->save();
        $this->postJson(route('site-builder.forms.submit'), ['context' => $tokens[0], 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']])->assertConflict();
    }

    public function test_smtp_failure_retains_inbox_and_sanitized_retry_state(): void
    {
        [, , $token] = $this->published();
        $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']])->assertOk();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp://private-credential@example.test'));
        $notification = FormNotification::query()->sole();
        (new DeliverNotification($notification->id))->handle();
        $this->assertSame(1, Submission::query()->count());
        $this->assertSame('failed', $notification->fresh()->status);
        $this->assertTrue($notification->fresh()->retry_at->isFuture());
        $this->assertStringNotContainsString('private-credential', $notification->fresh()->last_error);
    }

    public function test_forms_installer_preserves_existing_configuration_and_never_enables_or_migrates(): void
    {
        $path = config_path('site-builder-forms.php');
        $this->assertFileDoesNotExist($path);
        $this->artisan('site-builder:forms-install')->assertExitCode(0);
        try {
            $bytes = file_get_contents($path);
            $configuration = require $path;
            $this->assertFalse($configuration['enabled']);
            $this->assertNotEmpty($configuration['installation_id']);
            $this->artisan('site-builder:forms-install')->assertExitCode(0);
            $this->assertSame($bytes, file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_foreign_profiles_are_unavailable_and_current_profile_use_requires_explicit_permission(): void
    {
        [$page] = $this->published();
        Gate::before(fn (): bool => false);
        $this->expectException(AuthorizationException::class);
        app(AuthoringConstraints::class)->check(new GenericUser(['id' => 'editor-uuid']), $page->published_content, new RenderContext('en'));
    }

    public function test_foreign_profile_binding_blocks_publication_and_has_no_live_form_token(): void
    {
        [$page] = $this->published();
        config(['site-builder-forms.installation_id' => '019a0000-0000-7000-8000-000000000002']);
        $diagnostics = app(DocumentValidator::class)->diagnostics($page->published_content, true);
        $this->assertContains('unavailable_form_profile', array_map(fn ($diagnostic) => $diagnostic->code, $diagnostics));
        $html = app(DocumentRenderer::class)->render($page->published_content, new RenderContext('en', 'public', $page->id))->html->toHtml();
        $this->assertStringNotContainsString('name="context"', $html);
        $this->assertStringNotContainsString('data-sb-enquiry-form', $html);
    }

    public function test_retention_dry_run_and_prune_remove_only_expired_submissions_and_notifications(): void
    {
        [, , $token] = $this->published();
        $this->postJson(route('site-builder.forms.submit'), ['context' => $token, 'fields' => ['name' => 'Avery', 'email' => 'a@example.test', 'message' => 'Hello']])->assertOk();
        Submission::query()->update(['created_at' => now()->subDays(91)]);
        $this->artisan('site-builder:forms-prune', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(1, Submission::query()->count());
        $this->artisan('site-builder:forms-prune')->assertExitCode(0);
        $this->assertSame(0, Submission::query()->count());
        $this->assertSame(0, FormNotification::query()->count());
    }

    private function published(): array
    {
        $profile = NotificationProfile::query()->create(['name' => 'Approved inbox', 'recipients' => ['private-inbox@example.test'], 'active' => true]);
        $defaults = (new Form)->defaults();
        $document = new Document([new Node('enquiry', 'standard.form', props: new JsonObject(['profile' => $profile->id, 'installation' => self::INSTALLATION]), translations: $defaults->translations)]);
        $page = Page::factory()->create(['title' => 'Contact', 'layout' => 'components.host', 'status' => 'published', 'published_content' => $document, 'draft_content' => $document]);
        $page->slugs()->create(['locale' => 'en', 'slug' => 'contact-'.$page->id]);
        $html = app(PageResponse::class)->render($page, 'en')->getContent();
        preg_match('/name="context" value="([^"]+)"/', $html, $match);
        $this->assertNotEmpty($match[1] ?? null, $html);

        return [$page, $profile, html_entity_decode($match[1])];
    }
}
