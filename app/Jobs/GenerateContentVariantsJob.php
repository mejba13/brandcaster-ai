<?php

namespace App\Jobs;

use App\Models\ContentDraft;
use App\Models\ContentVariant;
use App\Services\AI\OpenAIContentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateContentVariantsJob implements ShouldQueue
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
        public ContentDraft $contentDraft,
        public array $platforms = ['website', 'facebook', 'twitter', 'linkedin']
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAIContentService $contentService): void
    {
        Log::info('Starting content variants generation', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'platforms' => $this->platforms,
            'attempt' => $this->attempts(),
        ]);

        try {
            DB::beginTransaction();

            // Ensure we have a body to work with
            if (empty($this->contentDraft->body)) {
                throw new Exception('Content draft does not have a body');
            }

            $variantsCreated = 0;

            // Generate variants for each platform
            foreach ($this->platforms as $platform) {
                try {
                    Log::info('Generating variant for platform', [
                        'content_draft_id' => $this->contentDraft->id,
                        'platform' => $platform,
                    ]);

                    // Generate platform-specific variant
                    $variantData = $contentService->generateVariant(
                        $this->contentDraft->body,
                        $platform,
                        $this->contentDraft->brand
                    );

                    // Create ContentVariant record
                    ContentVariant::create([
                        'content_draft_id' => $this->contentDraft->id,
                        'platform' => $platform,
                        'title' => $variantData['title'] ?? $this->contentDraft->title,
                        'content' => $variantData['content'],
                        'formatting' => $variantData['formatting'] ?? [],
                        'metadata' => $variantData['metadata'] ?? [],
                        'status' => ContentVariant::STATUS_PENDING,
                    ]);

                    $variantsCreated++;

                    Log::info('Variant created successfully', [
                        'content_draft_id' => $this->contentDraft->id,
                        'platform' => $platform,
                    ]);

                } catch (Exception $e) {
                    // Log error but continue with other platforms
                    Log::error('Failed to generate variant for platform', [
                        'content_draft_id' => $this->contentDraft->id,
                        'platform' => $platform,
                        'error' => $e->getMessage(),
                    ]);

                    // Re-throw if this is a critical error
                    if ($this->isCriticalError($e)) {
                        throw $e;
                    }
                }
            }

            // Update content draft status to pending review if variants were created
            if ($variantsCreated > 0) {
                // Check if auto-approval is enabled for this brand
                $brand = $this->contentDraft->brand;
                $autoApprove = $brand->settings['auto_approve'] ?? false;
                $highQuality = $this->contentDraft->confidence_score >= 0.8;

                if ($autoApprove && $highQuality) {
                    // Auto-approve high-quality content
                    $this->contentDraft->update([
                        'status' => ContentDraft::APPROVED,
                        'approved_by' => null, // System approval
                        'approved_at' => now(),
                    ]);

                    Log::info('Content auto-approved', [
                        'content_draft_id' => $this->contentDraft->id,
                        'confidence_score' => $this->contentDraft->confidence_score,
                    ]);

                    // Dispatch publishing job if auto-publish is enabled
                    if ($brand->settings['auto_publish'] ?? false) {
                        PublishContentJob::dispatch($this->contentDraft, [
                            'schedule' => true,
                            'publish_to_website' => true,
                            'publish_to_social' => true,
                        ]);

                        Log::info('Content queued for publishing', [
                            'content_draft_id' => $this->contentDraft->id,
                        ]);
                    }
                } else {
                    // Manual review required
                    $this->contentDraft->update([
                        'status' => ContentDraft::PENDING_REVIEW,
                    ]);
                }
            }

            DB::commit();

            Log::info('Content variants generation completed', [
                'content_draft_id' => $this->contentDraft->id,
                'brand_id' => $this->contentDraft->brand_id,
                'variants_created' => $variantsCreated,
                'platforms' => $this->platforms,
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate content variants', [
                'content_draft_id' => $this->contentDraft->id,
                'brand_id' => $this->contentDraft->brand_id,
                'platforms' => $this->platforms,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Determine if an error is critical and should stop all variant generation
     *
     * @param Exception $exception
     * @return bool
     */
    protected function isCriticalError(Exception $exception): bool
    {
        // Check for API quota errors, authentication errors, etc.
        $message = strtolower($exception->getMessage());

        $criticalPatterns = [
            'quota',
            'rate limit',
            'authentication',
            'unauthorized',
            'invalid api key',
        ];

        foreach ($criticalPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::critical('Content variants generation failed after all retries', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'platforms' => $this->platforms,
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
