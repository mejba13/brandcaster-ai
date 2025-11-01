<?php

namespace App\Jobs;

use App\Models\ContentDraft;
use App\Services\AI\OpenAIContentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateContentDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContentDraft $contentDraft
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAIContentService $contentService): void
    {
        Log::info('Starting full content draft generation', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'topic_id' => $this->contentDraft->topic_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            DB::beginTransaction();

            // Ensure we have an outline to work with
            if (empty($this->contentDraft->outline)) {
                throw new Exception('Content draft does not have an outline');
            }

            // Generate the full draft from the outline
            $draft = $contentService->generateDraft(
                $this->contentDraft->outline,
                $this->contentDraft->brand,
                $this->contentDraft->topic
            );

            // Update the ContentDraft with title, body, and SEO metadata
            $this->contentDraft->update([
                'title' => $draft['title'],
                'body' => $draft['body'],
                'seo_metadata' => $draft['seo_metadata'],
            ]);

            DB::commit();

            Log::info('Full content draft generated successfully', [
                'content_draft_id' => $this->contentDraft->id,
                'brand_id' => $this->contentDraft->brand_id,
                'title' => $draft['title'],
                'word_count' => str_word_count($draft['body']),
            ]);

            // Dispatch moderation job
            ModerateContentJob::dispatch($this->contentDraft);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate full content draft', [
                'content_draft_id' => $this->contentDraft->id,
                'brand_id' => $this->contentDraft->brand_id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::critical('Content draft generation failed after all retries', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'error' => $exception->getMessage(),
        ]);

        // Update draft status to indicate failure
        try {
            $this->contentDraft->update([
                'status' => ContentDraft::STATUS_REJECTED,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update content draft status', [
                'content_draft_id' => $this->contentDraft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
