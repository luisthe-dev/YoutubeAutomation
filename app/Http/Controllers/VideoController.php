<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVideoJob;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function create()
    {
        return view('video.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'voice_driver' => 'nullable|string',
            'image_driver' => 'nullable|string',
            'text_driver' => 'nullable|string',
            'voice_fallback' => 'nullable|string',
            'image_fallback' => 'nullable|string',
            'text_fallback' => 'nullable|string',
            'use_backups' => 'boolean',
        ]);

        $topic = $validated['topic'];
        $title = $validated['title'] ?? null;
        $useBackups = $request->has('use_backups'); // Checkbox logic

        $preferences = [
            'voice_driver' => $validated['voice_driver'] ?? null,
            'image_driver' => $validated['image_driver'] ?? null,
            'text_driver' => $validated['text_driver'] ?? null,
            'voice_fallback' => $validated['voice_fallback'] ?? null,
            'image_fallback' => $validated['image_fallback'] ?? null,
            'text_fallback' => $validated['text_fallback'] ?? null,
        ];

        // Remove nulls to keep it clean
        $preferences = array_filter($preferences);

        $jobId = uniqid('job_', true);
        ProcessVideoJob::dispatch($topic, $title, $useBackups, $preferences, $jobId);

        return redirect()->route('video.show', ['id' => $jobId])->with('success', "Video creation started for topic: {$topic}");
    }

    public function show($id)
    {
        return view('video.show', ['jobId' => $id]);
    }

    public function progress($id)
    {
        $logs = \Illuminate\Support\Facades\Cache::get("video_logs_{$id}", []);
        return response()->json(['logs' => $logs]);
    }
}
