<?php
/**
 * AI Overview Schema Generator Class
 * Generates structured data markup for AI overview optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIOverview_Schema_Generator {

    /**
     * Generate schema markup for a post
     */
    public function generate_schema($post, $schema_types) {
        if (!is_array($schema_types)) {
            $schema_types = array($schema_types);
        }

        $schema_data = array();
        $content_type = get_post_meta($post->ID, '_ai_overview_content_type', true);
        $structured_data = get_post_meta($post->ID, '_ai_overview_structured_data', true);

        // Base schema data
        $base_schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->post_title,
            'description' => wp_trim_words(strip_tags($post->post_content), 30),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => array(
                '@type' => 'Person',
                'name' => get_post_meta($post->ID, '_ai_overview_author_name', true) ?: get_bloginfo('name')
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo_url()
                )
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post)
            )
        );

        // Add featured image if exists
        if (has_post_thumbnail($post)) {
            $thumbnail_id = get_post_thumbnail_id($post);
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            if ($thumbnail_url) {
                $base_schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $thumbnail_url,
                    'width' => wp_get_attachment_image_src($thumbnail_id, 'full')[1],
                    'height' => wp_get_attachment_image_src($thumbnail_id, 'full')[2]
                );
            }
        }

        // Generate specific schema types
        if (in_array('faq', $schema_types) && ($content_type === 'faq' || $this->has_faq_content($post->post_content))) {
            $faq_schema = $this->generate_faq_schema($post, $structured_data);
            if ($faq_schema) {
                $schema_data[] = $faq_schema;
            }
        }

        if (in_array('howto', $schema_types) && ($content_type === 'howto' || $this->has_howto_content($post->post_content))) {
            $howto_schema = $this->generate_howto_schema($post, $structured_data);
            if ($howto_schema) {
                $schema_data[] = $howto_schema;
            }
        }

        if (in_array('article', $schema_types)) {
            $article_schema = $this->generate_article_schema($post, $base_schema);
            $schema_data[] = $article_schema;
        }

        if (in_array('breadcrumb', $schema_types)) {
            $breadcrumb_schema = $this->generate_breadcrumb_schema($post);
            if ($breadcrumb_schema) {
                $schema_data[] = $breadcrumb_schema;
            }
        }

        // If no specific schemas, return the base article schema
        if (empty($schema_data)) {
            $schema_data[] = $base_schema;
        }

        // Return single schema or array of schemas
        return count($schema_data) === 1 ? $schema_data[0] : $schema_data;
    }

    /**
     * Generate FAQ schema
     */
    private function generate_faq_schema($post, $structured_data = null) {
        // If we have pre-generated structured data, use it
        if ($structured_data && isset($structured_data['mainEntity'])) {
            $faq_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $structured_data['mainEntity']
            );
            return $faq_schema;
        }

        // Extract FAQ from content
        $content = $post->post_content;
        $faqs = $this->extract_faqs_from_content($content);

        if (empty($faqs)) {
            return null;
        }

        $main_entity = array();
        foreach ($faqs as $faq) {
            $main_entity[] = array(
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                )
            );
        }

        return array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $main_entity
        );
    }

    /**
     * Generate How-To schema
     */
    private function generate_howto_schema($post, $structured_data = null) {
        // If we have pre-generated structured data, use it
        if ($structured_data && isset($structured_data['step'])) {
            $howto_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'HowTo',
                'name' => $post->post_title,
                'description' => wp_trim_words(strip_tags($post->post_content), 30),
                'step' => $structured_data['step']
            );
            return $howto_schema;
        }

        // Extract steps from content
        $content = $post->post_content;
        $steps = $this->extract_steps_from_content($content);

        if (empty($steps)) {
            return null;
        }

        $step_entities = array();
        foreach ($steps as $index => $step) {
            $step_entities[] = array(
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => $step['title'],
                'text' => $step['content']
            );
        }

        return array(
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $post->post_title,
            'description' => wp_trim_words(strip_tags($post->post_content), 30),
            'step' => $step_entities
        );
    }

    /**
     * Generate Article schema
     */
    private function generate_article_schema($post, $base_schema) {
        $article_schema = $base_schema;
        $article_schema['@type'] = 'Article';

        // Add article-specific properties
        $article_schema['articleSection'] = $this->get_post_categories($post);
        $article_schema['keywords'] = $this->get_post_tags($post);

        // Add word count
        $word_count = str_word_count(strip_tags($post->post_content));
        $article_schema['wordCount'] = $word_count;

        // Add time required (estimated reading time)
        $reading_time = ceil($word_count / 200); // Assuming 200 words per minute
        $article_schema['timeRequired'] = 'PT' . $reading_time . 'M';

        return $article_schema;
    }

    /**
     * Generate Breadcrumb schema
     */
    private function generate_breadcrumb_schema($post) {
        $breadcrumbs = array();

        // Add home
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => get_home_url()
        );

        // Add categories
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $category = $categories[0]; // Use first category
            $breadcrumbs[] = array(
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $category->name,
                'item' => get_category_link($category->term_id)
            );

            $position = 3;
        } else {
            $position = 2;
        }

        // Add current post
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $post->post_title,
            'item' => get_permalink($post)
        );

        return array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs
        );
    }

    /**
     * Extract FAQs from content
     */
    private function extract_faqs_from_content($content) {
        $faqs = array();

        // Look for H2 headings as questions
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>(.*?)(?=<h2|$)/is', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $question) {
                $answer = $matches[2][$index];

                // Clean up the content
                $question = wp_strip_all_tags($question);
                $answer = wp_strip_all_tags($answer);
                $answer = wp_trim_words($answer, 100); // Limit answer length

                if (!empty($question) && !empty($answer)) {
                    $faqs[] = array(
                        'question' => trim($question),
                        'answer' => trim($answer)
                    );
                }
            }
        }

        return array_slice($faqs, 0, 10); // Limit to 10 FAQs
    }

    /**
     * Extract steps from content
     */
    private function extract_steps_from_content($content) {
        $steps = array();

        // Look for H2 headings as step titles
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>(.*?)(?=<h2|$)/is', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $title) {
                $step_content = $matches[2][$index];

                // Clean up the content
                $title = wp_strip_all_tags($title);
                $step_content = wp_strip_all_tags($step_content);
                $step_content = wp_trim_words($step_content, 150); // Limit step content

                if (!empty($title) && !empty($step_content)) {
                    $steps[] = array(
                        'title' => trim($title),
                        'content' => trim($step_content)
                    );
                }
            }
        }

        return array_slice($steps, 0, 15); // Limit to 15 steps
    }

    /**
     * Check if content has FAQ structure
     */
    private function has_faq_content($content) {
        // Check for question patterns
        $question_patterns = array(
            '/<h2[^>]*>\s*what\s+/i',
            '/<h2[^>]*>\s*how\s+/i',
            '/<h2[^>]*>\s*why\s+/i',
            '/<h2[^>]*>\s*when\s+/i',
            '/<h2[^>]*>\s*where\s+/i',
            '/<h2[^>]*>\s*which\s+/i',
            '/<h2[^>]*>\s*can\s+/i',
            '/<h2[^>]*>\s*do\s+/i',
            '/<h2[^>]*>\?\s*<\/h2>/i'
        );

        foreach ($question_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content has How-To structure
     */
    private function has_howto_content($content) {
        // Check for step patterns
        $step_patterns = array(
            '/<h2[^>]*>\s*step\s+\d+/i',
            '/<h2[^>]*>\s*how\s+to/i',
            '/<h2[^>]*>\s*guide/i',
            '/<h2[^>]*>\s*tutorial/i'
        );

        foreach ($step_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get post categories as string
     */
    private function get_post_categories($post) {
        $categories = get_the_category($post->ID);
        if (empty($categories)) {
            return '';
        }

        return $categories[0]->name; // Return first category
    }

    /**
     * Get post tags as comma-separated string
     */
    private function get_post_tags($post) {
        $tags = get_the_tags($post->ID);
        if (empty($tags)) {
            return '';
        }

        $tag_names = array();
        foreach ($tags as $tag) {
            $tag_names[] = $tag->name;
        }

        return implode(', ', $tag_names);
    }

    /**
     * Get site logo URL
     */
    private function get_site_logo_url() {
        $logo_url = get_site_icon_url();
        if ($logo_url) {
            return $logo_url;
        }

        // Fallback to theme logo or default
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            return wp_get_attachment_image_url($custom_logo_id, 'full');
        }

        return get_home_url() . '/wp-content/themes/' . get_template() . '/images/logo.png';
    }

    /**
     * Validate schema data
     */
    public function validate_schema($schema_data) {
        if (!is_array($schema_data)) {
            return false;
        }

        // Check for required @context
        if (!isset($schema_data['@context']) || $schema_data['@context'] !== 'https://schema.org') {
            return false;
        }

        // Check for required @type
        if (!isset($schema_data['@type'])) {
            return false;
        }

        return true;
    }
}