# AI Overview Optimizer

A WordPress plugin that generates articles specifically optimized for Google's AI overviews (formerly featured snippets). This plugin creates content with structured data markup to improve visibility in AI-powered search results.

## Features

- **AI-Powered Content Generation**: Uses Google Gemini or OpenAI to generate high-quality, SEO-optimized content
- **Multiple Content Types**: Support for FAQ articles, How-To guides, comparison articles, and listicles
- **Structured Data Markup**: Automatic generation of Schema.org markup for better AI overview visibility
- **Content Optimization**: Specifically designed prompts that create content likely to appear in AI overviews
- **Admin Interface**: Easy-to-use WordPress admin panel for configuration and content generation

## Content Types

### FAQ Articles
Creates comprehensive FAQ pages with question-and-answer format, optimized for AI overviews that display multiple Q&As.

### How-To Guides
Generates step-by-step tutorials with clear instructions, perfect for AI overviews that show procedural content.

### Comparison Articles
Creates detailed comparison content that helps users make informed decisions, often featured in AI overviews.

### Listicles
Generates numbered or bulleted list articles that provide quick, scannable information.

## Schema Markup Support

The plugin automatically adds the following structured data types:

- **FAQ Schema**: For question-and-answer content
- **How-To Schema**: For step-by-step guides
- **Article Schema**: For general article markup
- **Breadcrumb Schema**: For improved navigation

## Installation

1. Download the plugin files
2. Upload the `ai-overview-optimizer` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Configure your AI provider API key in the settings
5. Start generating optimized content

## Configuration

### API Setup

1. Choose your AI provider (Google Gemini or OpenAI)
2. Enter your API key:
   - **Gemini**: Get from [Google AI Studio](https://makersuite.google.com/app/apikey)
   - **OpenAI**: Get from [OpenAI Platform](https://platform.openai.com/api-keys)

### Content Settings

- **Post Status**: Choose whether to publish immediately or save as draft
- **Content Type**: Select the type of content to generate
- **Default Category**: Set the category for generated posts
- **Author Name**: Specify the author name for posts

### Schema Settings

Enable the structured data types you want to include:
- FAQ Schema
- How-To Schema
- Article Schema
- Breadcrumb Schema

## Usage

1. Navigate to the AI Overview Optimizer menu in your WordPress admin
2. Enter a topic or question in the content generation field
3. Click "Generate Optimized Post"
4. Review and publish the generated content

## How It Works

The plugin uses specialized prompts designed to create content that answers user intent directly and comprehensively. The generated content includes:

- Clear, descriptive titles
- Well-structured headings (H2, H3)
- Detailed, informative content
- Proper HTML formatting
- Schema markup for search engines

## AI Overview Optimization

Content is optimized for AI overviews through:

- **Question-based structure**: Content that directly answers common search queries
- **Comprehensive answers**: Detailed responses that satisfy user intent
- **Structured markup**: Schema.org data that helps search engines understand content
- **Clear formatting**: Easy-to-parse content structure
- **Authority signals**: Factual, well-researched information

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Valid API key for Google Gemini or OpenAI

## Support

For support or feature requests, please check the plugin documentation or contact the developer.

## Changelog

### Version 1.0.0
- Initial release
- Support for Gemini and OpenAI
- Multiple content types
- Schema markup generation
- Admin interface

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Divina Vibecodes