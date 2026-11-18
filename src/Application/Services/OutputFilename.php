<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Validation\ResizeRequest;

final readonly class OutputFilename
{
    public function for(ResizeRequest $request): string
    {
        $baseName = $request->outputFilename ?? $this->generatedBaseName($request->sourceName);

        return $baseName . '.' . $request->format->extension();
    }

    // The client's file name only shapes the default name, so whatever it holds is reduced to safe characters
    private function generatedBaseName(string $sourceName): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($sourceName, PATHINFO_FILENAME)) ?? '';

        return 'resized_' . substr($safeName, 0, 100) . '_' . uniqid();
    }
}
