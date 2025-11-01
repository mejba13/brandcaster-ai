<?php

namespace App\Services\AI;

use App\Models\Brand;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Content Moderator
 *
 * Performs safety checks on generated content including toxicity detection,
 * PII detection, and brand safety checks.
 */
class ContentModerator
{
    /**
     * Moderate content through all safety checks
     *
     * @param string $content
     * @param Brand $brand
     * @return array ['passed' => bool, 'violations' => array, 'score' => float]
     */
    public function moderate(string $content, Brand $brand): array
    {
        $violations = [];
        $scores = [];

        // 1. Toxicity check (OpenAI Moderation API)
        $toxicityResult = $this->checkToxicity($content);
        if (!$toxicityResult['passed']) {
            $violations[] = [
                'type' => 'toxicity',
                'message' => 'Content flagged for toxicity or harmful content',
                'details' => $toxicityResult['categories'],
            ];
        }
        $scores[] = $toxicityResult['passed'] ? 1.0 : 0.0;

        // 2. PII detection
        $piiResult = $this->detectPII($content);
        if (!$piiResult['passed']) {
            $violations[] = [
                'type' => 'pii',
                'message' => 'Content contains potential PII',
                'details' => $piiResult['found'],
            ];
        }
        $scores[] = $piiResult['passed'] ? 1.0 : 0.5; // PII is warning, not hard fail

        // 3. Brand safety (blocklist check)
        $brandSafetyResult = $this->checkBrandSafety($content, $brand);
        if (!$brandSafetyResult['passed']) {
            $violations[] = [
                'type' => 'brand_safety',
                'message' => 'Content contains blocked keywords',
                'details' => $brandSafetyResult['blocked_terms'],
            ];
        }
        $scores[] = $brandSafetyResult['passed'] ? 1.0 : 0.0;

        // 4. Check required keywords (if any)
        $requiredResult = $this->checkRequiredKeywords($content, $brand);
        if (!$requiredResult['passed']) {
            $violations[] = [
                'type' => 'missing_required',
                'message' => 'Content missing required keywords',
                'details' => $requiredResult['missing'],
            ];
        }
        $scores[] = $requiredResult['passed'] ? 1.0 : 0.8; // Soft requirement

        // Calculate overall score
        $overallScore = count($scores) > 0 ? array_sum($scores) / count($scores) : 0.0;

        $passed = empty($violations) || $this->canOverrideSoftViolations($violations);

        Log::info('Content moderation completed', [
            'brand_id' => $brand->id,
            'passed' => $passed,
            'score' => $overallScore,
            'violations_count' => count($violations),
        ]);

        return [
            'passed' => $passed,
            'violations' => $violations,
            'score' => $overallScore,
        ];
    }

    /**
     * Check content for toxicity using OpenAI Moderation API
     *
     * @param string $content
     * @return array
     */
    protected function checkToxicity(string $content): array
    {
        try {
            $response = OpenAI::moderations()->create([
                'model' => 'text-moderation-latest',
                'input' => $content,
            ]);

            $result = $response->results[0];

            $flaggedCategories = [];
            foreach ($result->categories as $category => $flagged) {
                if ($flagged) {
                    $flaggedCategories[] = $category;
                }
            }

            return [
                'passed' => !$result->flagged,
                'categories' => $flaggedCategories,
            ];
        } catch (\Exception $e) {
            Log::warning('Toxicity check failed, allowing content', [
                'error' => $e->getMessage(),
            ]);

            // Fail open - if moderation API is down, allow content
            return ['passed' => true, 'categories' => []];
        }
    }

    /**
     * Detect PII (Personally Identifiable Information)
     *
     * @param string $content
     * @return array
     */
    protected function detectPII(string $content): array
    {
        $found = [];

        // Email addresses
        if (preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content, $matches)) {
            $found['emails'] = $matches[0];
        }

        // Phone numbers (basic patterns)
        if (preg_match_all('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', $content, $matches)) {
            $found['phones'] = $matches[0];
        }

        // Social Security Numbers (XXX-XX-XXXX)
        if (preg_match_all('/\b\d{3}-\d{2}-\d{4}\b/', $content, $matches)) {
            $found['ssn'] = $matches[0];
        }

        // Credit card numbers (basic pattern)
        if (preg_match_all('/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', $content, $matches)) {
            $found['credit_cards'] = $matches[0];
        }

        return [
            'passed' => empty($found),
            'found' => $found,
        ];
    }

    /**
     * Check brand safety (blocked keywords)
     *
     * @param string $content
     * @param Brand $brand
     * @return array
     */
    protected function checkBrandSafety(string $content, Brand $brand): array
    {
        $blocklist = $brand->style_guide['blocklist'] ?? [];

        if (empty($blocklist)) {
            return ['passed' => true, 'blocked_terms' => []];
        }

        $contentLower = strtolower($content);
        $blockedTerms = [];

        foreach ($blocklist as $term) {
            if (stripos($contentLower, strtolower($term)) !== false) {
                $blockedTerms[] = $term;
            }
        }

        return [
            'passed' => empty($blockedTerms),
            'blocked_terms' => $blockedTerms,
        ];
    }

    /**
     * Check for required keywords
     *
     * @param string $content
     * @param Brand $brand
     * @return array
     */
    protected function checkRequiredKeywords(string $content, Brand $brand): array
    {
        // Get required keywords from brand settings (if any)
        $required = $brand->settings['required_keywords'] ?? [];

        if (empty($required)) {
            return ['passed' => true, 'missing' => []];
        }

        $contentLower = strtolower($content);
        $missing = [];

        foreach ($required as $keyword) {
            if (stripos($contentLower, strtolower($keyword)) === false) {
                $missing[] = $keyword;
            }
        }

        return [
            'passed' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Check if violations are soft and can be overridden
     *
     * @param array $violations
     * @return bool
     */
    protected function canOverrideSoftViolations(array $violations): bool
    {
        $hardViolations = ['toxicity', 'brand_safety'];

        foreach ($violations as $violation) {
            if (in_array($violation['type'], $hardViolations)) {
                return false;
            }
        }

        return true; // All violations are soft
    }

    /**
     * Quick toxicity check without full moderation
     *
     * @param string $content
     * @return bool
     */
    public function isSafe(string $content): bool
    {
        $result = $this->checkToxicity($content);
        return $result['passed'];
    }
}
