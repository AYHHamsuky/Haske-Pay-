<?php

namespace App\Services\Providers;

use InvalidArgumentException;

class ProviderManager
{
    /** @var array<string, BillProvider> */
    private array $resolved = [];

    public function get(string $name): BillProvider
    {
        return $this->resolved[$name] ??= $this->make($name);
    }

    private function make(string $name): BillProvider
    {
        if (config('haske.drivers.bills') === 'fake') {
            return new FakeProvider($name);
        }

        return match ($name) {
            'vtpass' => new VtpassProvider(config('haske.vtpass')),
            'baxi' => new BaxiProvider(config('haske.baxi')),
            default => throw new InvalidArgumentException("Unknown provider [$name]"),
        };
    }

    /** A route can only be used once its keys are in .env. */
    public function configured(string $name): bool
    {
        if (config('haske.drivers.bills') === 'fake') {
            return true;
        }

        return match ($name) {
            'vtpass' => filled(config('haske.vtpass.api_key')) && filled(config('haske.vtpass.secret_key')),
            'baxi' => filled(config('haske.baxi.api_key')) && filled(config('haske.baxi.agent_id')),
            default => false,
        };
    }

    /** @return list<string> */
    public function names(): array
    {
        return ['vtpass', 'baxi'];
    }
}
