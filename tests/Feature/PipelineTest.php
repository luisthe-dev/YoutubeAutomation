<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use App\Http\Services\DirectorService;
use App\Jobs\ProcessVideoJob;
use Mockery;

class PipelineTest extends TestCase
{
    public function test_pipeline_execution()
    {
        Storage::fake('public');

        // Mock Gemini Service to avoid real API calls
        $mockDirector = Mockery::mock(DirectorService::class);
        $mockDirector->shouldReceive('createBlueprint')
            ->once()
            ->andReturn([
                'title' => 'Test Video',
                'script' => 'Hello world',
                'visuals' => ['self.play(Create(Circle()))']
            ]);

        // We can't easily mock the Process class in a unit test without a wrapper, 
        // but we can test that the job runs and attempts to create files.
        // For a true integration test, we might want to let it run the python script if available.
        
        // Let's try to run the job synchronously
        $job = new ProcessVideoJob('Test Topic');
        $job->handle($mockDirector);

        // Assert blueprint was created
        // The job ID is random/unique, so we need to find the directory
        $directories = Storage::disk('public')->directories('videos');
        $this->assertNotEmpty($directories);
        
        $jobDir = $directories[0];
        $this->assertTrue(Storage::disk('public')->exists("$jobDir/blueprint.json"));
        
        // Check if Python script would have run (we can't check output file unless we really run it)
        // If we want to really test the python integration, we shouldn't mock the process, 
        // but we need to ensure the environment has python/manim.
    }
}
