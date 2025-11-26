<?php

namespace App\Console\Commands;

use Gemini;
use Illuminate\Console\Command;

class ListModelsCommand extends Command
{
    protected $signature = 'gemini:models';
    protected $description = 'List available Gemini models';

    public function handle()
    {
        $gemini = Gemini::client(env('GEMINI_API_KEY'));
        $response = $gemini->models()->list();
        
        foreach ($response->models as $model) {
            $this->info("Name: {$model->name}");
            $this->line("Supported Generation Methods: " . implode(', ', $model->supportedGenerationMethods));
            $this->newLine();
        }
    }
}
