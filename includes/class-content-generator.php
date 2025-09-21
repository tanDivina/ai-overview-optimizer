<?php
/**
 * AI Overview Content Generator Class
 * Generates content specifically optimized for Google's AI overviews
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIOverview_Content_Generator {

    /**
     * Generate AI overview optimized post
     */
    public function generate_overview_post($topic, $api_key = null) {
        try {
            // Check API key
            $provider = get_option('ai_overview_provider', 'gemini');

            // If API key not provided, try to get from options
            if (empty($api_key)) {
                $api_key_option = 'ai_overview_' . $provider . '_key';
                $api_key = get_option($api_key_option, '');
            }

            if (empty($api_key)) {
                return new WP_Error('no_api_key', 'API key not configured for ' . $provider);
            }

            // Get content type and generate appropriate content
            $content_type = get_option('ai_overview_content_type', 'faq');

            // Generate content based on type
            $content_data = $this->generate_content_by_type($topic, $content_type, $provider, $api_key);

            if (is_wp_error($content_data)) {
                return $content_data;
            }

            // Get author information
            $author_name = get_option('ai_overview_author_name', get_bloginfo('name'));
            $author_user = get_user_by('display_name', $author_name);
            if (!$author_user) {
                $author_user = get_user_by('login', sanitize_user($author_name));
            }
            $post_author = $author_user ? $author_user->ID : get_current_user_id();

            // Create post
            $post_data = array(
                'post_title' => sanitize_text_field($content_data['title']),
                'post_content' => wp_kses_post($content_data['content']),
                'post_status' => get_option('ai_overview_post_status', 'draft'),
                'post_category' => array(get_option('ai_overview_category', 1)),
                'post_author' => $post_author,
                'meta_input' => array(
                    '_ai_overview_generated' => true,
                    '_ai_overview_topic' => sanitize_text_field($topic),
                    '_ai_overview_content_type' => $content_type,
                    '_ai_overview_provider' => $provider,
                    '_ai_overview_schema_types' => get_option('ai_overview_schema_types', array('faq')),
                    '_ai_overview_generation_date' => current_time('mysql'),
                    '_ai_overview_author_name' => sanitize_text_field($author_name)
                )
            );

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                return $post_id;
            }

            // Store structured data for schema generation
            if (isset($content_data['structured_data'])) {
                update_post_meta($post_id, '_ai_overview_structured_data', $content_data['structured_data']);
            }

            return $post_id;

        } catch (Exception $e) {
            return new WP_Error('generation_error', 'Content generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate content based on type
     */
    private function generate_content_by_type($topic, $content_type, $provider, $api_key) {
        $prompt = $this->build_content_prompt($topic, $content_type);

        $response = $this->make_api_request($provider, $api_key, $prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_content_response($response, $content_type);
    }

    /**
     * Build content prompt based on type
     */
    private function build_content_prompt($topic, $content_type) {
        $base_prompt = "You are an expert content writer specializing in SEO and AI overview optimization. ";

        switch ($content_type) {
            case 'faq':
                $prompt = $base_prompt . "Create a comprehensive FAQ article about: {$topic}

REQUIREMENTS:
- Create an engaging title that includes the main question or topic
- Structure as FAQ with clear H2 headings for each question
- Each question should be a common search query
- Provide detailed, helpful answers (150-300 words each)
- Include 8-12 frequently asked questions
- Use natural, conversational language
- Include relevant facts, statistics, and examples
- End with a conclusion paragraph

IMPORTANT FORMATTING:
- Use proper HTML: <h2> for questions, <p> for answers
- NO JSON, NO curly braces, NO structured data in the content
- Write naturally like a blog post

OUTPUT FORMAT:
Respond with ONLY a clean JSON object:

{
  \"title\": \"Your SEO-optimized title\",
  \"content\": \"<h2>What is [topic]?</h2><p>Detailed answer here...</p><h2>How does [topic] work?</h2><p>Another detailed answer...</p>\"
}";
                break;

            case 'howto':
                $prompt = $base_prompt . "Create a detailed How-To guide about: {$topic}

REQUIREMENTS:
- Create a compelling title
- Structure with clear steps using H2 headings
- Include introduction, step-by-step instructions, and conclusion
- Each step should be actionable and detailed
- Include tips, warnings, and best practices
- Use bullet points and numbered lists
- Total length: 1500-2500 words

IMPORTANT FORMATTING:
- Use proper HTML: <h2> for steps, <p> for instructions, <ul>/<ol> for lists
- NO JSON, NO curly braces, NO structured data in the content
- Write naturally like a blog post

OUTPUT FORMAT:
Respond with ONLY a clean JSON object:

{
  \"title\": \"How to [Topic] - Complete Guide\",
  \"content\": \"<p>Introduction paragraph...</p><h2>Step 1: First Step</h2><p>Detailed instructions...</p><h2>Step 2: Next Step</h2><p>More instructions...</p>\"
}";
                break;

            case 'comparison':
                $prompt = $base_prompt . "Create a detailed comparison article about: {$topic}

REQUIREMENTS:
- Create an engaging comparison title
- Compare at least 3-5 options/alternatives
- Structure with clear sections for each option
- Include pros, cons, features, pricing, and recommendations
- Use comparison tables in HTML format
- Provide unbiased analysis
- End with clear recommendations

IMPORTANT FORMATTING:
- Use proper HTML: <h2> for sections, <p> for descriptions, <table> for comparisons
- NO JSON, NO curly braces, NO structured data in the content
- Write naturally like a blog post

OUTPUT FORMAT:
Respond with ONLY a clean JSON object:

{
  \"title\": \"[Option A] vs [Option B] vs [Option C] - Complete Comparison\",
  \"content\": \"<p>Introduction to comparison...</p><h2>What is [Topic]?</h2><p>Explanation...</p><h2>Comparison Table</h2><table>...</table>\"
}";
                break;

            case 'listicle':
                $prompt = $base_prompt . "Create an engaging listicle article about: {$topic}

REQUIREMENTS:
- Create a click-worthy title with a number
- Structure as a numbered or bulleted list with detailed explanations
- Each list item should be substantial (200-400 words)
- Include 10-15 list items
- Use engaging subheadings for each item
- Include examples, tips, and practical advice

IMPORTANT FORMATTING:
- Use proper HTML: <h2> for list items, <p> for descriptions
- NO JSON, NO curly braces, NO structured data in the content
- Write naturally like a blog post

OUTPUT FORMAT:
Respond with ONLY a clean JSON object:

{
  \"title\": \"[Number] Best [Topic] - Complete Guide\",
  \"content\": \"<p>Introduction...</p><h2>1. First Item</h2><p>Detailed explanation...</p><h2>2. Second Item</h2><p>More details...</p>\"
}";
                break;

            default:
                $prompt = $base_prompt . "Create an informative article about: {$topic}

REQUIREMENTS:
- SEO-optimized title
- Well-structured content with H2/H3 headings
- Comprehensive coverage of the topic
- Include facts, examples, and practical information

IMPORTANT FORMATTING:
- Use proper HTML: <h2> for sections, <p> for paragraphs
- NO JSON, NO curly braces, NO structured data in the content
- Write naturally like a blog post

OUTPUT FORMAT:
Respond with ONLY a clean JSON object:

{
  \"title\": \"Your SEO Title\",
  \"content\": \"<p>Introduction paragraph...</p><h2>First Section</h2><p>Content here...</p><h2>Second Section</h2><p>More content...</p>\"
}";
        }

        return $prompt . "

IMPORTANT:
- Write naturally and conversationally
- Focus on providing value and answering user intent
- Use proper HTML formatting
- Ensure content is original and comprehensive
- NO external links or references
- Return ONLY the JSON object, no additional text";
    }

    /**
     * Make API request
     */
    private function make_api_request($provider, $api_key, $prompt) {
        try {
            switch ($provider) {
                case 'gemini':
                    return $this->request_gemini_content($api_key, $prompt);
                case 'openai':
                    return $this->request_openai_content($api_key, $prompt);
                default:
                    return new WP_Error('invalid_provider', 'Invalid provider: ' . $provider);
            }
        } catch (Exception $e) {
            return new WP_Error('api_error', 'API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Parse API response
     */
    private function parse_content_response($response, $content_type) {
        // Clean the response first - remove markdown code blocks
        $cleaned_response = $this->clean_api_response($response);

        // Try to parse as JSON first
        $json_data = json_decode($cleaned_response, true);
        if ($json_data && isset($json_data['title']) && isset($json_data['content'])) {
            // Ensure content is properly formatted HTML
            $json_data['content'] = $this->ensure_html_content($json_data['content']);
            $json_data['title'] = $this->clean_title($json_data['title']);
            // Add structured_data as empty since we'll generate it separately
            $json_data['structured_data'] = array();

            // Final cleanup of any remaining artifacts in the content
            $json_data['content'] = $this->final_content_cleanup($json_data['content']);

            return $json_data;
        }

        // Try to extract JSON from mixed response
        if (strpos($cleaned_response, '{') !== false && strpos($cleaned_response, '}') !== false) {
            // Look for JSON object in the response
            preg_match('/\{(?:[^{}]|(?R))*\}/s', $cleaned_response, $matches);
            if (!empty($matches[0])) {
                $json_data = json_decode($matches[0], true);
                if ($json_data && isset($json_data['title']) && isset($json_data['content'])) {
                    $json_data['content'] = $this->ensure_html_content($json_data['content']);
                    $json_data['title'] = $this->clean_title($json_data['title']);
                    $json_data['structured_data'] = array();
                    return $json_data;
                }
            }
        }

        // Fallback: try to parse as plain text and structure it
        return $this->parse_as_plain_text($cleaned_response, $content_type);
    }

    /**
     * Clean API response before parsing
     */
    private function clean_api_response($response) {
        // Remove markdown code blocks
        $response = preg_replace('/```json\s*/i', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = preg_replace('/```/', '', $response);

        // Remove any leading/trailing whitespace
        $response = trim($response);

        return $response;
    }

    /**
     * Ensure content is properly formatted HTML
     */
    private function ensure_html_content($content) {
        // First, clean the content aggressively
        $content = $this->clean_content($content);

        // If content already has HTML tags, clean it and ensure proper structure
        if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
            // Remove any remaining JSON fragments but keep HTML
            $content = preg_replace('/"[^"]*":\s*\{[^}]*\}/s', '', $content);
            $content = preg_replace('/"[^"]*":\s*\[[^\]]*\]/s', '', $content);
            $content = preg_replace('/\},\s*"/', '', $content);
            $content = preg_replace('/\s*\{\s*"[^"]*":/', '', $content);

            // Clean up any remaining artifacts
            $content = preg_replace('/^[\"\}\]\{\[\s\.,;:-]+/', '', $content);
            $content = preg_replace('/[\"\}\]\{\[\s\.,;:-]+$/', '', $content);

            $content = trim($content);

            // If content became empty after cleaning, provide fallback
            if (empty($content)) {
                return '<p>Content could not be generated properly. Please try again.</p>';
            }

            // Ensure we have proper paragraph tags if it's just text
            if (!preg_match('/<p[^>]*>/', $content) && !preg_match('/<h[1-6][^>]*>/', $content)) {
                $content = '<p>' . $content . '</p>';
            }

            return $content;
        }

        // Convert plain text to HTML
        if (empty($content)) {
            return '<p>Content could not be generated properly. Please try again.</p>';
        }

        // Split into paragraphs and wrap with <p> tags
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $html_content = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Skip if paragraph is just punctuation/artifacts
                if (preg_match('/^[\"\}\]\{\[\s\.,;:-]+$/', $paragraph)) {
                    continue;
                }
                $html_content .= '<p>' . $paragraph . '</p>' . "\n";
            }
        }

        return $html_content ?: '<p>' . $content . '</p>';
    }

    /**
     * Parse response as plain text when JSON parsing fails
     */
    private function parse_as_plain_text($response, $content_type) {
        // Try to extract title from first line
        $lines = explode("\n", $response);
        $title = '';
        $content = '';

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (empty($title) && !preg_match('/^[<{]/', $line)) {
                $title = $this->clean_title($line);
                continue;
            }

            if (!empty($title)) {
                $content .= $line . "\n";
            }
        }

        if (empty($title)) {
            $title = ucfirst($content_type) . ' About ' . date('Y-m-d H:i:s');
        }

        $content = $this->ensure_html_content($content);

        return array(
            'title' => $title,
            'content' => $content,
            'structured_data' => $this->generate_basic_structured_data($title, $content, $content_type)
        );
    }

    /**
     * Generate basic structured data
     */
    private function generate_basic_structured_data($title, $content, $content_type) {
        $base_data = array(
            '@context' => 'https://schema.org',
            'headline' => $title,
            'description' => wp_trim_words(strip_tags($content), 30),
            'datePublished' => current_time('c'),
            'dateModified' => current_time('c'),
            'author' => array(
                '@type' => 'Person',
                'name' => get_option('ai_overview_author_name', get_bloginfo('name'))
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                )
            )
        );

        switch ($content_type) {
            case 'faq':
                $base_data['@type'] = 'FAQPage';
                // FAQ structured data will be generated by schema class
                break;
            case 'howto':
                $base_data['@type'] = 'HowTo';
                break;
            default:
                $base_data['@type'] = 'Article';
        }

        return $base_data;
    }

    /**
     * Clean content - aggressive cleaning to remove JSON artifacts and unwanted text
     */
    private function clean_content($content) {
        // Remove markdown code blocks
        $content = preg_replace('/```[^`]*```/s', '', $content);

        // Remove JSON objects and fragments (more aggressive)
        $content = preg_replace('/\{[^}]*\}/s', '', $content);
        $content = preg_replace('/\[[^\]]*\]/s', '', $content);

        // Remove JSON-like key-value pairs
        $content = preg_replace('/"[^"]*"\s*:\s*("[^"]*"|\d+|true|false|null)/', '', $content);

        // Remove standalone quotes and brackets
        $content = preg_replace('/^\s*["\}\]\{\[]+\s*$/m', '', $content);
        $content = preg_replace('/["\}\]\{\[]+\s*$/', '', $content);
        $content = preg_replace('/^\s*["\}\]\{\[]+/', '', $content);

        // Remove URLs (but keep them if they're in href attributes)
        $content = preg_replace('/https?:\/\/[^\s<>"\']+/i', '', $content);

        // Remove common unwanted phrases that AI sometimes adds
        $unwanted_phrases = [
            'Before you make changes, please push our current version to Github',
            'Please push our current version to Github',
            'push our current version to Github',
            'GitHub repository',
            'version control',
            'commit changes'
        ];

        foreach ($unwanted_phrases as $phrase) {
            $content = str_ireplace($phrase, '', $content);
        }

        // Clean up extra whitespace and empty lines
        $content = preg_replace('/\s{2,}/', ' ', $content);
        $content = preg_replace('/>\s+</', '><', $content);
        $content = preg_replace('/^\s*$/m', '', $content); // Remove empty lines
        $content = preg_replace('/\n\s*\n/', "\n", $content); // Remove multiple newlines

        // Remove leading/trailing punctuation that might be artifacts
        $content = preg_replace('/^[\"\}\]\{\[\s\.,;:-]+/', '', $content);
        $content = preg_replace('/[\"\}\]\{\[\s\.,;:-]+$/', '', $content);

        $content = trim($content);

        return $content;
    }

    /**
     * Clean title
     */
    private function clean_title($title) {
        $title = preg_replace('/```[^`]*```/s', '', $title);
        $title = preg_replace('/https?:\/\/[^\s]+/i', '', $title);
        $title = preg_replace('/[{}"\[\]]/i', '', $title);
        $title = preg_replace('/\s{2,}/', ' ', $title);
        $title = trim($title);

        return $title;
    }

    /**
     * Final cleanup of content to remove any remaining artifacts
     */
    private function final_content_cleanup($content) {
        // Remove any remaining JSON artifacts
        $content = preg_replace('/["\}\]\{\[]+\s*$/', '', $content);
        $content = preg_replace('/^\s*["\}\]\{\[]+/', '', $content);

        // Remove common unwanted endings
        $content = preg_replace('/\s*}\s*$/', '', $content);
        $content = preg_replace('/\s*]\s*$/', '', $content);
        $content = preg_replace('/\s*"\s*$/', '', $content);

        // Clean up multiple spaces and newlines
        $content = preg_replace('/\s{2,}/', ' ', $content);
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);

        return trim($content);
    }

    /**
     * Request Gemini content
     */
    private function request_gemini_content($api_key, $prompt) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . $api_key;

        $body = wp_json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => 8000
            )
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 120,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => $body
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error('api_error', 'Invalid response from Gemini API');
    }

    /**
     * Request OpenAI content
     */
    private function request_openai_content($api_key, $prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = wp_json_encode(array(
            'model' => 'gpt-4',
            'messages' => array(
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens' => 8000,
            'temperature' => 0.7
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => $body
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        return new WP_Error('api_error', 'Invalid response from OpenAI API');
    }
}