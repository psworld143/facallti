<?php
/**
 * AI Providers Configuration
 * 
 * This file contains configuration for multiple AI providers.
 * You can switch between providers or use fallback chains.
 */

// AI Provider Configuration
$ai_providers = [
    'openai' => [
        'name' => 'OpenAI GPT-3.5-turbo',
        'api_url' => 'https://api.openai.com/v1/chat/completions',
        'model' => 'gpt-3.5-turbo',
        'api_key' => '', // Set your OpenAI API key here
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'enabled' => true,
        'priority' => 1
    ],
    'anthropic' => [
        'name' => 'Anthropic Claude',
        'api_url' => 'https://api.anthropic.com/v1/messages',
        'model' => 'claude-3-haiku-20240307',
        'api_key' => '', // Set your Anthropic API key here
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'enabled' => false, // Set to true when you have an API key
        'priority' => 2
    ],
    'google' => [
        'name' => 'Google Gemini',
        'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
        'model' => 'gemini-pro',
        'api_key' => '', // Set your Google API key here
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'enabled' => false, // Set to true when you have an API key
        'priority' => 3
    ],
    'azure' => [
        'name' => 'Azure OpenAI',
        'api_url' => '', // Set your Azure OpenAI endpoint here
        'model' => 'gpt-35-turbo',
        'api_key' => '', // Set your Azure API key here
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'enabled' => false, // Set to true when you have an API key
        'priority' => 4
    ]
];

// Load API keys from environment variables or config
foreach ($ai_providers as $provider => &$config) {
    $env_key = strtoupper($provider) . '_API_KEY';
    if (getenv($env_key)) {
        $config['api_key'] = getenv($env_key);
    }
}

// Fallback chain - providers will be tried in this order
$fallback_chain = ['openai', 'anthropic', 'google', 'azure'];

// Default provider (will be used first)
$default_provider = 'openai';

// Instructions for getting API keys
$api_key_instructions = [
    'openai' => [
        'url' => 'https://platform.openai.com/api-keys',
        'instructions' => '1. Go to OpenAI Platform 2. Create account/login 3. Navigate to API Keys 4. Create new secret key'
    ],
    'anthropic' => [
        'url' => 'https://console.anthropic.com/',
        'instructions' => '1. Go to Anthropic Console 2. Create account/login 3. Navigate to API Keys 4. Create new key'
    ],
    'google' => [
        'url' => 'https://makersuite.google.com/app/apikey',
        'instructions' => '1. Go to Google AI Studio 2. Create account/login 3. Create API key 4. Enable Gemini API'
    ],
    'azure' => [
        'url' => 'https://portal.azure.com/',
        'instructions' => '1. Go to Azure Portal 2. Create OpenAI resource 3. Get endpoint and API key 4. Deploy model'
    ]
];
?>
