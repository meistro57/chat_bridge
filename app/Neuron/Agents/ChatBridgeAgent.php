<?php

namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;

class ChatBridgeAgent extends Agent
{
    protected string $model;

    protected string $extraSystemPrompt = '';

    public function provider(): AIProviderInterface
    {
        $provider = config('neuron.default', 'openai');

        if ($provider === 'anthropic') {
            return new Anthropic(
                key: config('neuron.providers.anthropic.api_key'),
                model: config('neuron.providers.anthropic.model'),
            );
        }

        // Default to OpenAI
        return new OpenAI(
            key: config('neuron.providers.openai.api_key'),
            model: config('neuron.providers.openai.model'),
        );
    }

    public function instructions(): string
    {
        $instructions = 'You are a concise and helpful assistant for ChatBridge.';
        $instructions .= "\n- When context is missing, ask 1 clarifying question max.";
        $instructions .= "\n- Never hallucinate external actions; only respond with text.";

        if ($this->extraSystemPrompt) {
            $instructions .= "\n\nPersona Instructions:\n".$this->extraSystemPrompt;
        }

        return $instructions;
    }

    public function setPersona(?string $persona): self
    {
        if ($persona) {
            $this->extraSystemPrompt = $persona;
        }

        return $this;
    }
}
