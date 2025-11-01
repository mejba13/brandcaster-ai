<?php

namespace App\Services\AI;

use App\Models\PromptTemplate;
use App\Models\Brand;

/**
 * Prompt Renderer
 *
 * Renders AI prompt templates with variable substitution.
 */
class PromptRenderer
{
    /**
     * Render a prompt template with variables
     *
     * @param string $type Template type (brief, outline, draft, variant)
     * @param array $variables Variables to substitute
     * @param Brand|null $brand Optional brand for brand-specific templates
     * @return string Rendered prompt
     */
    public function render(string $type, array $variables, ?Brand $brand = null): string
    {
        $template = $this->getTemplate($type, $brand);

        return $this->substitute($template, $variables);
    }

    /**
     * Get template by type and brand
     *
     * @param string $type
     * @param Brand|null $brand
     * @return string Template content
     */
    protected function getTemplate(string $type, ?Brand $brand = null): string
    {
        // Try to find brand-specific template first
        if ($brand) {
            $template = PromptTemplate::where('brand_id', $brand->id)
                ->where('type', $type)
                ->active()
                ->latestVersion()
                ->first();

            if ($template) {
                return $template->template;
            }
        }

        // Fall back to global template
        $template = PromptTemplate::whereNull('brand_id')
            ->where('type', $type)
            ->active()
            ->latestVersion()
            ->first();

        if ($template) {
            return $template->template;
        }

        // Fall back to default hard-coded template
        return $this->getDefaultTemplate($type);
    }

    /**
     * Substitute variables in template
     *
     * @param string $template
     * @param array $variables
     * @return string
     */
    protected function substitute(string $template, array $variables): string
    {
        $rendered = $template;

        foreach ($variables as $key => $value) {
            // Handle array values (convert to string)
            if (is_array($value)) {
                $value = $this->arrayToString($value);
            }

            // Replace {variable} with value
            $rendered = str_replace("{{$key}}", $value, $rendered);
        }

        // Remove any unsubstituted variables
        $rendered = preg_replace('/\{[^}]+\}/', '', $rendered);

        return trim($rendered);
    }

    /**
     * Convert array to readable string
     *
     * @param array $array
     * @return string
     */
    protected function arrayToString(array $array): string
    {
        // If associative array, convert to key: value format
        if ($this->isAssoc($array)) {
            $parts = [];
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $parts[] = "$key: $value";
            }
            return implode(', ', $parts);
        }

        // If indexed array, just join with commas
        return implode(', ', $array);
    }

    /**
     * Check if array is associative
     *
     * @param array $array
     * @return bool
     */
    protected function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Get default hard-coded templates
     *
     * @param string $type
     * @return string
     */
    protected function getDefaultTemplate(string $type): string
    {
        return match($type) {
            'brief' => $this->getDefaultBriefTemplate(),
            'outline' => $this->getDefaultOutlineTemplate(),
            'draft' => $this->getDefaultDraftTemplate(),
            'variant' => $this->getDefaultVariantTemplate(),
            default => '',
        };
    }

    /**
     * Default brief template
     *
     * @return string
     */
    protected function getDefaultBriefTemplate(): string
    {
        return <<<'PROMPT'
You are creating content for {brand_name}.

Topic: {topic_title}
Description: {topic_description}
Keywords: {keywords}

Sources:
{source_urls}

Create a strategic content brief that includes:
1. Target audience and their pain points
2. Key message and value proposition
3. Angle or unique perspective to take
4. Main points to cover (3-5 bullet points)
5. Tone and style guidelines
6. Call-to-action

Keep the brief concise (max 300 words) but comprehensive enough to guide content creation.
PROMPT;
    }

    /**
     * Default outline template
     *
     * @return string
     */
    protected function getDefaultOutlineTemplate(): string
    {
        return <<<'PROMPT'
Based on this content brief for {brand_name}, create a detailed outline:

{brief}

Structure your outline with:
- An engaging introduction
- 3-5 main sections with descriptive headings
- 2-4 key points under each section
- A conclusion with takeaways

Format:
1. Section Title
   - Key point
   - Key point

Use clear, descriptive headings that would work as H2/H3 tags.
PROMPT;
    }

    /**
     * Default draft template
     *
     * @return string
     */
    protected function getDefaultDraftTemplate(): string
    {
        return <<<'PROMPT'
Write a comprehensive article for {brand_name} following this outline:

{outline}

Brand Voice: {brand_voice}
Style Guide: {style_guide}
Keywords to include: {topic_keywords}

Requirements:
- Start with an engaging title (H1)
- Write an attention-grabbing introduction
- Expand each outline section into well-developed paragraphs
- Use H2 headings for main sections and H3 for subsections
- Include relevant examples, statistics, or case studies
- Cite sources when making factual claims
- Write a strong conclusion with key takeaways
- Add a call-to-action at the end
- Use markdown formatting

Target length: 1000-1500 words
PROMPT;
    }

    /**
     * Default variant template
     *
     * @return string
     */
    protected function getDefaultVariantTemplate(): string
    {
        return <<<'PROMPT'
Adapt this content for {platform} for {brand_name}:

Original content:
{original_content}

Platform constraints:
{constraints}

Create an engaging {platform} post that:
- Captures the key message from the original content
- Follows {platform} best practices
- Uses appropriate tone and style for the platform
- Includes relevant hashtags (2-5)
- Stays within platform limits
- Ends with a clear call-to-action

Return only the adapted content, ready to post.
PROMPT;
    }
}
