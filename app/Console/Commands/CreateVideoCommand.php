<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoJob;
use App\Http\Services\DirectorService;
use Illuminate\Console\Command;

class CreateVideoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'video:create {topic : The topic of the video} 
                            {--random : Pick a random title automatically} 
                            {--no-backups : Disable fallback image generators}
                            {--voice-driver= : Preferred voice driver}
                            {--image-driver= : Preferred image driver}
                            {--text-driver= : Preferred text driver}
                            {--voice-fallback= : Secondary voice driver}
                            {--image-fallback= : Secondary image driver}
                            {--text-fallback= : Secondary text driver}
                            {--duration= : Target video duration in seconds}
                            {--manual-render : Print python command instead of running it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a job to create a video for the given topic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $topic = $this->argument('topic');
        $random = $this->option('random');
        $useBackups = !$this->option('no-backups');
        
        $this->info("Generating catchy titles for: {$topic}...");
        
        $textDriver = $this->option('text-driver');
        $textFallback = $this->option('text-fallback');
        
        $director = new DirectorService($textDriver, $textFallback);
        $titles = $director->generateTitles($topic);
        
        if (empty($titles)) {
            $this->error("Failed to generate titles. Using default.");
            ProcessVideoJob::dispatch($topic, null, $useBackups);
            return;
        }

        // Display titles
        $this->info("Generated Titles:");
        foreach ($titles as $i => $title) {
            $this->line("  [$i] $title");
        }

        if ($random) {
            $choice = $titles[array_rand($titles)];
            $this->info("\nRandomly selected title: {$choice}");
        } else {
            $titles[] = "Custom Title (Enter your own)";
            $choice = $this->choice('Choose a title for your video:', $titles, 0);
            
            if ($choice === "Custom Title (Enter your own)") {
                $choice = $this->ask('Enter your custom title:');
            }
        }
        
        $preferences = [
            'voice_driver' => $this->option('voice-driver'),
            'image_driver' => $this->option('image-driver'),
            'text_driver' => $this->option('text-driver'),
            'voice_fallback' => $this->option('voice-fallback'),
            'image_fallback' => $this->option('image-fallback'),
            'text_fallback' => $this->option('text-fallback'),
        ];

        $duration = $this->option('duration') ? (int)$this->option('duration') : null;
        $manualRender = $this->option('manual-render');
        
        $this->info("Dispatching video creation job for: {$choice}");

        ProcessVideoJob::dispatch($topic, $choice, $useBackups, $preferences, null, $duration, $manualRender);
        
        $this->info("Job dispatched! Check the logs or storage/app/public/videos/ for progress.");
    }
}
