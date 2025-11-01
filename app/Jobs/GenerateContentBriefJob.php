<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\Topic;
use App\Services\AI\OpenAIContentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateContentBriefJob implements ShouldQueue
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
        public Topic $topic,
        public Brand $brand
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAIContentService $contentService): void
    {
        Log::info('Starting content brief generation', [
            'topic_id' => $this->topic->id,
            'brand_id' => $this->brand->id,
            'attempt' => $this->attempts(),
        ]);

        try {
            DB::beginTransaction();

            // Generate the content brief using OpenAI
            $brief = $contentService->generateBrief($this->topic, $this->brand);

            // Create the ContentDraft with the brief
            $contentDraft = ContentDraft::create([
                'brand_id' => $this->brand->id,
                'topic_id' => $this->topic->id,
                'strategy_brief' => $brief,
                'status' => ContentDraft::STATUS_DRAFT,
            ]);

            // Mark topic as used
            $this->topic->markAsUsed();

            DB::commit();

            Log::info('Content brief generated successfully', [
                'topic_id' => $this->topic->id,
                'brand_id' => $this->brand->id,
                'content_draft_id' => $contentDraft->id,
            ]);

            // Dispatch the next job in the pipeline
            GenerateContentOutlineJob::dispatch($contentDraft);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate content brief', [
                'topic_id' => $this->topic->id,
                'brand_id' => $this->brand->id,
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
        Log::critical('Content brief generation failed after all retries', [
            'topic_id' => $this->topic->id,
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);

        // You could notify administrators or update a status here
    }
}
