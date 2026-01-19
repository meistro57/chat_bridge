<?php
// File: app/Console/Commands/ArtisanUi.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

class ArtisanUi extends Command
{
    protected $signature = 'ui:artisan
        {--no-color : Disable ANSI colors in the UI output}
        {--groups : Show group picker first (default behaviour)}
        {--all : Skip groups and show all commands}
    ';

    protected $description = 'Colorful interactive CLI UI for Artisan commands (search, group, run, stream output).';

    public function handle(): int
    {
        $this->ensureTtyHints();

        $allCommands = $this->getAllArtisanCommands();
        if (empty($allCommands)) {
            error('No Artisan commands found. Something is very wrong. 😅');
            return self::FAILURE;
        }

        $grouped = $this->groupCommands($allCommands);
        $disableColor = (bool) $this->option('no-color');

        while (true) {
            $mode = $this->pickMode();

            if ($mode === 'quit') {
                info('Alright. Closing the hatch. 🚪');
                return self::SUCCESS;
            }

            $commandsForMenu = $allCommands;

            if ($mode === 'groups') {
                $groupKey = $this->pickGroup($grouped);
                if ($groupKey === null) {
                    continue;
                }
                $commandsForMenu = $grouped[$groupKey] ?? [];
            }

            $picked = $this->pickCommand($commandsForMenu);
            if ($picked === null) {
                continue;
            }

            $args = $this->promptArgsIfWanted($picked);
            $this->runArtisanCommand($picked, $args, $disableColor);

            if (!confirm('Run another command?', true)) {
                info('Done. Terminal throne relinquished. 👑');
                return self::SUCCESS;
            }
        }
    }

    private function pickMode(): string
    {
        // CLI flags override the first screen
        if ($this->option('all')) {
            return 'all';
        }
        if ($this->option('groups')) {
            return 'groups';
        }

        $choice = select(
            label: 'Artisan UI',
            options: [
                'groups' => 'Browse by group (ai, bridge, make, etc.)',
                'all'    => 'Search across ALL commands',
                'quit'   => 'Quit',
            ],
            default: 'groups',
            hint: 'Tip: use arrow keys, type to filter, Enter to select.'
        );

        return $choice;
    }

    private function pickGroup(array $grouped): ?string
    {
        $keys = array_keys($grouped);
        sort($keys);

        $labels = [];
        foreach ($keys as $k) {
            $labels[$k] = sprintf('%s  (%d)', $this->paintGroupLabel($k), count($grouped[$k]));
        }

        $selected = select(
            label: 'Pick a command group',
            options: array_merge(['<<' => 'Back'], $labels),
            default: $keys[0] ?? '<<',
            hint: 'Examples: bridge, ai, make, migrate…'
        );

        if ($selected === '<<') {
            return null;
        }

        return $selected;
    }

    private function pickCommand(array $commands): ?string
    {
        if (empty($commands)) {
            warning('No commands in that group.');
            return null;
        }

        // Use a “search” prompt so you can type a few letters and it narrows instantly.
        $selected = search(
            label: 'Search & pick a command',
            options: function (string $value) use ($commands) {
                $value = Str::lower(trim($value));

                $filtered = array_filter($commands, function ($cmd) use ($value) {
                    if ($value === '') return true;
                    return Str::contains(Str::lower($cmd), $value);
                });

                // Display up to 50 to keep it snappy
                $filtered = array_slice(array_values($filtered), 0, 50);

                $out = [];
                foreach ($filtered as $cmd) {
                    $out[$cmd] = $this->paintCommandLabel($cmd);
                }
                return $out;
            },
            placeholder: 'e.g. bridge:chat, ai:test, migrate, test',
            hint: 'Type to filter. Enter to run. Esc to cancel (or choose Back).'
        );

        if (!$selected) {
            return null;
        }

        return $selected;
    }

    private function promptArgsIfWanted(string $command): string
    {
        if (!confirm('Add arguments/options?', false)) {
            return '';
        }

        $raw = text(
            label: 'Enter args/options (raw)',
            placeholder: '--help   OR   --env=local   OR   "my arg" --flag',
            hint: 'You can paste exactly what you’d type after the command.'
        );

        return trim((string) $raw);
    }

    private function runArtisanCommand(string $command, string $args, bool $disableColor): void
    {
        $full = trim("php artisan {$command} {$args}");

        note("Running: {$full}");

        // Stream output live, like a tiny theatre show where tests can heckle you in real-time.
        $process = Process::fromShellCommandline(
            $disableColor ? "{$full} --no-ansi" : $full,
            base_path(),
            null,
            null,
            null
        );

        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) {
            // passthru raw output
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            error("Command failed (exit {$process->getExitCode()}).");
            return;
        }

        info('Command finished successfully.');
    }

    private function getAllArtisanCommands(): array
    {
        // The cleanest way: ask Artisan itself.
        // We parse `php artisan list --format=json` where available.
        // If json format isn’t available for some reason, fallback to plain parsing.
        $json = $this->tryArtisanListJson();

        if ($json !== null) {
            $names = [];
            foreach (($json['commands'] ?? []) as $cmd) {
                if (!empty($cmd['name'])) {
                    $names[] = $cmd['name'];
                }
            }
            $names = array_values(array_unique($names));
            sort($names);
            return $names;
        }

        // Fallback: plain text parse
        $output = $this->runAndCapture('php artisan list --no-ansi');
        return $this->parseArtisanListText($output);
    }

    private function tryArtisanListJson(): ?array
    {
        $output = $this->runAndCapture('php artisan list --format=json --no-ansi');

        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function runAndCapture(string $cmd): string
    {
        $process = Process::fromShellCommandline($cmd, base_path());
        $process->run();

        // If it fails, we still might get useful output.
        return (string) $process->getOutput() . (string) $process->getErrorOutput();
    }

    private function parseArtisanListText(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $names = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || Str::startsWith($line, ['Usage:', 'Options:', 'Available commands:', 'Laravel Framework'])) {
                continue;
            }

            // Typical format: "bridge  bridge:chat  Description..."
            // Or: "  migrate  Run the database migrations"
            // We'll capture the first token that looks like a command name.
            if (preg_match('/^([a-zA-Z0-9:_-]+)\s{2,}/', $line, $m)) {
                $name = $m[1];

                // filter section headers like "ai", "bridge", "make" (they appear alone sometimes)
                if (!Str::contains($name, ':') && in_array($name, ['ai','auth','boost','bridge','cache','channel','config','db','env','event','inertia','install','key','lang','make','mcp','migrate','model','optimize','package','queue','reverb','roster','route','sail','sanctum','schedule','schema','storage','stub','vendor','view'], true)) {
                    continue;
                }

                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);
        return $names;
    }

    private function groupCommands(array $commands): array
    {
        $groups = [];

        foreach ($commands as $cmd) {
            $group = Str::contains($cmd, ':') ? Str::before($cmd, ':') : 'core';
            $groups[$group][] = $cmd;
        }

        ksort($groups);

        // Put your custom goodies near the top if you like
        $preferredOrder = ['bridge', 'ai', 'boost', 'mcp', 'core'];
        $ordered = [];

        foreach ($preferredOrder as $g) {
            if (isset($groups[$g])) {
                $ordered[$g] = $groups[$g];
                unset($groups[$g]);
            }
        }

        // then the rest
        foreach ($groups as $g => $list) {
            $ordered[$g] = $list;
        }

        return $ordered;
    }

    private function paintGroupLabel(string $group): string
    {
        return match ($group) {
            'bridge' => "🧠 {$group}",
            'ai'     => "🤖 {$group}",
            'make'   => "🧰 {$group}",
            'migrate'=> "🧱 {$group}",
            'test'   => "🧪 {$group}",
            'core'   => "⚙️ {$group}",
            default  => "📦 {$group}",
        };
    }

    private function paintCommandLabel(string $cmd): string
    {
        // Make the left side more readable: highlight namespaces like bridge:* and ai:*
        if (Str::contains($cmd, ':')) {
            $left = Str::before($cmd, ':');
            $right = Str::after($cmd, ':');
            return "{$left}:{$right}";
        }
        return $cmd;
    }

    private function ensureTtyHints(): void
    {
        // Helpful hints if someone runs this in a non-interactive environment
        if (!Process::isTtySupported()) {
            warning('TTY not supported here. UI still works, but output streaming may be less fancy.');
        }
    }
}
