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

class GenerateContentOutlineJob implements ShouldQueue
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
    public $timeout = 300;

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
        Log::info('Starting content outline generation', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'topic_id' => $this->contentDraft->topic_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            DB::beginTransaction();

            // Ensure we have a brief to work with
            if (empty($this->contentDraft->strategy_brief)) {
                throw new Exception('Content draft does not have a strategy brief');
            }

            // Generate the outline from the brief
            $outline = $contentService->generateOutline(
                $this->contentDraft->strategy_brief,
                $this->contentDraft->brand
            );

            // Update the ContentDraft with the outline
            $this->contentDraft->update([
                'outline' => $outline,
            ]);

            DB::commit();

            Log::info('Content outline generated successfully', [
                'content_draft_id' => $this->contentDraft->id,
                'brand_id' => $this->contentDraft->brand_id,
                'sections_count' => count($outline),
            ]);

            // Dispatch the next job in the pipeline
            GenerateContentDraftJob::dispatch($this->contentDraft);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate content outline', [
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
        Log::critical('Content outline generation failed after all retries', [
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
