<?php

namespace App\Services\AI;

use App\Models\Brand;
use App\Models\Topic;
use App\Services\AI\Contracts\AIContentServiceInterface;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * OpenAI Content Service
 *
 * Implements AI content generation using OpenAI GPT models.
 */
class OpenAIContentService implements AIContentServiceInterface
{
    protected PromptRenderer $promptRenderer;
    protected string $model;
    protected float $temperature;

    public function __construct(PromptRenderer $promptRenderer)
    {
        $this->promptRenderer = $promptRenderer;
        $this->model = config('openai.model', 'gpt-4-turbo-preview');
        $this->temperature = config('openai.temperature', 0.7);
    }

    /**
     * Generate a strategy brief for content
     *
     * @param Topic $topic
     * @param Brand $brand
     * @return string
     */
    public function generateBrief(Topic $topic, Brand $brand): string
    {
        $prompt = $this->promptRenderer->render('brief', [
            'brand_name' => $brand->name,
            'brand_voice' => $brand->brand_voice,
            'topic_title' => $topic->title,
            'topic_description' => $topic->description,
            'keywords' => implode(', ', $topic->keywords),
            'source_urls' => implode("\n", $topic->source_urls),
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt($brand)],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 1000,
            ]);

            $brief = $response->choices[0]->message->content;

            Log::info('Generated content brief', [
                'brand_id' => $brand->id,
                'topic_id' => $topic->id,
                'tokens' => $response->usage->totalTokens,
            ]);

            return trim($brief);
        } catch (\Exception $e) {
            Log::error('Failed to generate brief', [
                'brand_id' => $brand->id,
                'topic_id' => $topic->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate an outline from a brief
     *
     * @param string $brief
     * @param Brand $brand
     * @return array
     */
    public function generateOutline(string $brief, Brand $brand): array
    {
        $prompt = $this->promptRenderer->render('outline', [
            'brand_name' => $brand->name,
            'brief' => $brief,
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt($brand)],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 1500,
            ]);

            $outlineText = $response->choices[0]->message->content;

            // Parse outline into structured array
            $outline = $this->parseOutline($outlineText);

            Log::info('Generated content outline', [
                'brand_id' => $brand->id,
                'sections' => count($outline),
                'tokens' => $response->usage->totalTokens,
            ]);

            return $outline;
        } catch (\Exception $e) {
            Log::error('Failed to generate outline', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate full content draft from outline
     *
     * @param array $outline
     * @param Brand $brand
     * @param Topic|null $topic
     * @return array
     */
    public function generateDraft(array $outline, Brand $brand, ?Topic $topic = null): array
    {
        $outlineText = $this->outlineToText($outline);

        $prompt = $this->promptRenderer->render('draft', [
            'brand_name' => $brand->name,
            'brand_voice' => $brand->brand_voice,
            'style_guide' => $brand->style_guide,
            'outline' => $outlineText,
            'topic_keywords' => $topic ? implode(', ', $topic->keywords) : '',
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt($brand)],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 3000,
            ]);

            $content = $response->choices[0]->message->content;

            // Extract title and body
            $parsed = $this->parseContent($content);

            // Generate SEO metadata
            $seoMetadata = $this->generateSEOMetadata($parsed['title'], $parsed['body'], $brand);

            Log::info('Generated content draft', [
                'brand_id' => $brand->id,
                'topic_id' => $topic?->id,
                'word_count' => str_word_count($parsed['body']),
                'tokens' => $response->usage->totalTokens,
            ]);

            return [
                'title' => $parsed['title'],
                'body' => $parsed['body'],
                'seo_metadata' => $seoMetadata,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate draft', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate platform-specific variant
     *
     * @param string $content
     * @param string $platform
     * @param Brand $brand
     * @return array
     */
    public function generateVariant(string $content, string $platform, Brand $brand): array
    {
        $platformConstraints = $this->getPlatformConstraints($platform);

        $prompt = $this->promptRenderer->render('variant', [
            'brand_name' => $brand->name,
            'platform' => $platform,
            'constraints' => $platformConstraints,
            'original_content' => $content,
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt($brand)],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => $this->getMaxTokensForPlatform($platform),
            ]);

            $variantContent = $response->choices[0]->message->content;

            $parsed = $this->parseVariant($variantContent, $platform);

            Log::info('Generated content variant', [
                'brand_id' => $brand->id,
                'platform' => $platform,
                'tokens' => $response->usage->totalTokens,
            ]);

            return $parsed;
        } catch (\Exception $e) {
            Log::error('Failed to generate variant', [
                'brand_id' => $brand->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Improve/rewrite content
     *
     * @param string $content
     * @param string $instruction
     * @param Brand $brand
     * @return string
     */
    public function improveContent(string $content, string $instruction, Brand $brand): string
    {
        $prompt = "Improve the following content based on this instruction: {$instruction}\n\nOriginal content:\n{$content}";

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt($brand)],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 3000,
            ]);

            return trim($response->choices[0]->message->content);
        } catch (\Exception $e) {
            Log::error('Failed to improve content', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate SEO metadata
     *
     * @param string $title
     * @param string $content
     * @param Brand $brand
     * @return array
     */
    public function generateSEOMetadata(string $title, string $content, Brand $brand): array
    {
        $prompt = "Generate SEO metadata for the following content. Return as JSON with keys: meta_description (max 160 chars), keywords (array of 5-10 keywords), og_title, og_description.\n\nTitle: {$title}\n\nContent excerpt:\n" . substr($content, 0, 500);

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an SEO expert. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 500,
            ]);

            $json = $response->choices[0]->message->content;

            // Extract JSON from response (in case it's wrapped in markdown code blocks)
            $json = preg_replace('/```json\s*|\s*```/', '', $json);

            $metadata = json_decode($json, true);

            if (!$metadata) {
                throw new \Exception('Failed to parse SEO metadata JSON');
            }

            return $metadata;
        } catch (\Exception $e) {
            Log::warning('Failed to generate SEO metadata, using defaults', [
                'error' => $e->getMessage(),
            ]);

            // Return default metadata
            return [
                'meta_description' => substr(strip_tags($content), 0, 160),
                'keywords' => [],
                'og_title' => $title,
                'og_description' => substr(strip_tags($content), 0, 200),
            ];
        }
    }

    /**
     * Get system prompt with brand context
     *
     * @param Brand $brand
     * @return string
     */
    protected function getSystemPrompt(Brand $brand): string
    {
        $voice = $brand->brand_voice;
        $guide = $brand->style_guide;

        return "You are a professional content writer for {$brand->name}. " .
               "Brand voice: {$voice['tone']}. Target audience: {$voice['audience']}. " .
               "Style: {$voice['style']}. " .
               "Do's: " . implode(', ', $guide['dos'] ?? []) . ". " .
               "Don'ts: " . implode(', ', $guide['donts'] ?? []) . ". " .
               "Always cite sources when making factual claims. " .
               "Use the brand's preferred terms: " . implode(', ', array_keys($voice['lexicon']['prefer'] ?? [])) . ". " .
               "Avoid: " . implode(', ', $voice['lexicon']['avoid'] ?? []) . ".";
    }

    /**
     * Parse outline text into structured array
     *
     * @param string $text
     * @return array
     */
    protected function parseOutline(string $text): array
    {
        $lines = explode("\n", $text);
        $outline = [];
        $currentSection = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Main section (starts with number or ##)
            if (preg_match('/^(\d+\.|\#{2,})\s*(.+)$/', $line, $matches)) {
                $currentSection = [
                    'title' => trim($matches[2]),
                    'points' => [],
                ];
                $outline[] = &$currentSection;
            }
            // Sub-point (starts with - or *)
            elseif (preg_match('/^[\-\*]\s*(.+)$/', $line, $matches) && $currentSection) {
                $currentSection['points'][] = trim($matches[1]);
            }
        }

        return $outline;
    }

    /**
     * Convert outline array to text
     *
     * @param array $outline
     * @return string
     */
    protected function outlineToText(array $outline): string
    {
        $text = '';
        foreach ($outline as $i => $section) {
            $text .= ($i + 1) . ". {$section['title']}\n";
            foreach ($section['points'] as $point) {
                $text .= "   - $point\n";
            }
            $text .= "\n";
        }
        return $text;
    }

    /**
     * Parse generated content into title and body
     *
     * @param string $content
     * @return array
     */
    protected function parseContent(string $content): array
    {
        // Try to extract title (first H1 or first line)
        if (preg_match('/^#\s*(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
            $body = preg_replace('/^#\s*.+$/m', '', $content, 1);
        } else {
            $lines = explode("\n", $content);
            $title = trim($lines[0]);
            $body = implode("\n", array_slice($lines, 1));
        }

        return [
            'title' => trim($title, '# '),
            'body' => trim($body),
        ];
    }

    /**
     * Parse platform variant
     *
     * @param string $content
     * @param string $platform
     * @return array
     */
    protected function parseVariant(string $content, string $platform): array
    {
        // Extract hashtags if present
        preg_match_all('/#[\w]+/', $content, $hashtags);

        // Extract mentions if present
        preg_match_all('/@[\w]+/', $content, $mentions);

        return [
            'title' => null, // Platform posts typically don't have separate titles
            'content' => trim($content),
            'formatting' => [
                'hashtags' => $hashtags[0] ?? [],
                'mentions' => $mentions[0] ?? [],
            ],
            'metadata' => [
                'platform' => $platform,
                'character_count' => mb_strlen($content),
            ],
        ];
    }

    /**
     * Get platform-specific constraints
     *
     * @param string $platform
     * @return string
     */
    protected function getPlatformConstraints(string $platform): string
    {
        return match($platform) {
            'twitter' => 'Max 280 characters. Use hashtags and emojis. Be concise and engaging.',
            'facebook' => 'Max 5000 characters. Use emojis. Include call-to-action. Can be conversational.',
            'linkedin' => 'Max 3000 characters. Professional tone. Use bullet points. Include relevant hashtags.',
            'website' => 'Full article format. Use headings (H2, H3). Include introduction and conclusion. SEO-optimized.',
            default => 'Optimize for the platform.',
        };
    }

    /**
     * Get max tokens for platform
     *
     * @param string $platform
     * @return int
     */
    protected function getMaxTokensForPlatform(string $platform): int
    {
        return match($platform) {
            'twitter' => 150,
            'facebook' => 1000,
            'linkedin' => 800,
            'website' => 3000,
            default => 1000,
        };
    }
}
