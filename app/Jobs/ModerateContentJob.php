<?php

namespace App\Jobs;

use App\Models\ContentDraft;
use App\Services\AI\ContentModerator;
use App\Services\AI\OpenAIContentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModerateContentJob implements ShouldQueue
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
    public $backoff = [30, 60, 120]; // 30s, 1 min, 2 min

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * Maximum number of automatic regeneration attempts.
     *
     * @var int
     */
    protected const MAX_REGENERATION_ATTEMPTS = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContentDraft $contentDraft,
        public int $regenerationAttempt = 0
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(ContentModerator $moderator, OpenAIContentService $contentService): void
    {
        Log::info('Starting content moderation', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'regeneration_attempt' => $this->regenerationAttempt,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Ensure we have content to moderate
            if (empty($this->contentDraft->body)) {
                throw new Exception('Content draft does not have a body to moderate');
            }

            // Perform moderation
            $moderationResult = $moderator->moderate(
                $this->contentDraft->title . "\n\n" . $this->contentDraft->body,
                $this->contentDraft->brand
            );

            Log::info('Content moderation completed', [
                'content_draft_id' => $this->contentDraft->id,
                'passed' => $moderationResult['passed'],
                'score' => $moderationResult['score'],
                'violations_count' => count($moderationResult['violations']),
            ]);

            DB::beginTransaction();

            // Store moderation metadata
            $seoMetadata = $this->contentDraft->seo_metadata ?? [];
            $seoMetadata['moderation'] = [
                'checked_at' => now()->toISOString(),
                'score' => $moderationResult['score'],
                'passed' => $moderationResult['passed'],
                'violations_count' => count($moderationResult['violations']),
            ];

            $this->contentDraft->update([
                'seo_metadata' => $seoMetadata,
            ]);

            if ($moderationResult['passed']) {
                // Content passed moderation - proceed to generate variants
                Log::info('Content passed moderation, generating variants', [
                    'content_draft_id' => $this->contentDraft->id,
                ]);

                DB::commit();

                // Dispatch variant generation job
                GenerateContentVariantsJob::dispatch($this->contentDraft);

            } else {
                // Content failed moderation
                $this->handleModerationFailure($moderationResult, $contentService);
                DB::commit();
            }

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to moderate content', [
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
     * Handle moderation failure
     *
     * @param array $moderationResult
     * @param OpenAIContentService $contentService
     * @return void
     */
    protected function handleModerationFailure(array $moderationResult, OpenAIContentService $contentService): void
    {
        Log::warning('Content failed moderation', [
            'content_draft_id' => $this->contentDraft->id,
            'violations' => $moderationResult['violations'],
            'regeneration_attempt' => $this->regenerationAttempt,
        ]);

        // Check if violations are severe or if we've exhausted regeneration attempts
        $hasSevereViolations = $this->hasSevereViolations($moderationResult['violations']);

        if ($hasSevereViolations) {
            // Severe violations - reject immediately
            Log::error('Content has severe violations, rejecting', [
                'content_draft_id' => $this->contentDraft->id,
                'violations' => $moderationResult['violations'],
            ]);

            $this->rejectContent($moderationResult['violations']);
            return;
        }

        // Check if we can attempt regeneration
        if ($this->regenerationAttempt < self::MAX_REGENERATION_ATTEMPTS) {
            $this->attemptRegeneration($moderationResult, $contentService);
        } else {
            // Exhausted regeneration attempts - reject
            Log::warning('Exhausted regeneration attempts, rejecting content', [
                'content_draft_id' => $this->contentDraft->id,
                'attempts' => $this->regenerationAttempt,
            ]);

            $this->rejectContent($moderationResult['violations']);
        }
    }

    /**
     * Check if violations are severe
     *
     * @param array $violations
     * @return bool
     */
    protected function hasSevereViolations(array $violations): bool
    {
        $severeTypes = ['toxicity', 'brand_safety'];

        foreach ($violations as $violation) {
            if (in_array($violation['type'], $severeTypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt to regenerate content with improvements
     *
     * @param array $moderationResult
     * @param OpenAIContentService $contentService
     * @return void
     */
    protected function attemptRegeneration(array $moderationResult, OpenAIContentService $contentService): void
    {
        Log::info('Attempting content regeneration', [
            'content_draft_id' => $this->contentDraft->id,
            'regeneration_attempt' => $this->regenerationAttempt + 1,
        ]);

        try {
            // Build improvement instruction based on violations
            $instruction = $this->buildImprovementInstruction($moderationResult['violations']);

            // Regenerate content
            $improvedContent = $contentService->improveContent(
                $this->contentDraft->body,
                $instruction,
                $this->contentDraft->brand
            );

            // Update draft with improved content
            $this->contentDraft->update([
                'body' => $improvedContent,
            ]);

            Log::info('Content regenerated, re-moderating', [
                'content_draft_id' => $this->contentDraft->id,
            ]);

            // Dispatch moderation again with incremented regeneration attempt
            self::dispatch($this->contentDraft, $this->regenerationAttempt + 1);

        } catch (Exception $e) {
            Log::error('Failed to regenerate content, rejecting', [
                'content_draft_id' => $this->contentDraft->id,
                'error' => $e->getMessage(),
            ]);

            $this->rejectContent($moderationResult['violations']);
        }
    }

    /**
     * Build improvement instruction from violations
     *
     * @param array $violations
     * @return string
     */
    protected function buildImprovementInstruction(array $violations): string
    {
        $instructions = ['Please improve the content by addressing the following issues:'];

        foreach ($violations as $violation) {
            $instructions[] = "- {$violation['message']}";
            if (!empty($violation['details'])) {
                if (is_array($violation['details'])) {
                    $instructions[] = '  Details: ' . implode(', ', $violation['details']);
                } else {
                    $instructions[] = '  Details: ' . $violation['details'];
                }
            }
        }

        $instructions[] = "\nMaintain the same overall structure and key points, but rewrite problematic sections.";

        return implode("\n", $instructions);
    }

    /**
     * Reject content and log violations
     *
     * @param array $violations
     * @return void
     */
    protected function rejectContent(array $violations): void
    {
        Log::warning('Rejecting content draft', [
            'content_draft_id' => $this->contentDraft->id,
            'violations' => $violations,
        ]);

        // Store rejection details in metadata
        $seoMetadata = $this->contentDraft->seo_metadata ?? [];
        $seoMetadata['rejection'] = [
            'rejected_at' => now()->toISOString(),
            'violations' => $violations,
            'regeneration_attempts' => $this->regenerationAttempt,
        ];

        $this->contentDraft->update([
            'status' => ContentDraft::STATUS_REJECTED,
            'seo_metadata' => $seoMetadata,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::critical('Content moderation failed after all retries', [
            'content_draft_id' => $this->contentDraft->id,
            'brand_id' => $this->contentDraft->brand_id,
            'regeneration_attempt' => $this->regenerationAttempt,
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
