<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\YouTube;

class SetupYouTubeAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authenticate with YouTube and store the refresh token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clientId = env('YOUTUBE_CLIENT_ID');
        $clientSecret = env('YOUTUBE_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            $this->error('Please set YOUTUBE_CLIENT_ID and YOUTUBE_CLIENT_SECRET in .env first.');
            return;
        }

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes([YouTube::YOUTUBE_UPLOAD]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri('http://localhost');

        $authUrl = $client->createAuthUrl();

        $this->info('Open the following URL in your browser:');
        $this->line($authUrl);
        $this->newLine();
        $this->info('If you are redirected to a "Connection Refused" page, copy the "code" parameter from the URL bar.');
        $this->info('Ensure "http://localhost" is added to your Authorized Redirect URIs in Google Cloud Console.');

        $authCode = $this->ask('Enter the verification code:');

        try {
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            
            if (isset($accessToken['error'])) {
                $this->error('Error fetching access token: ' . $accessToken['error_description']);
                return;
            }

            $refreshToken = $accessToken['refresh_token'] ?? null;

            if ($refreshToken) {
                $this->updateEnv('YOUTUBE_REFRESH_TOKEN', $refreshToken);
                $this->info('Refresh token stored in .env successfully!');
            } else {
                $this->error('No refresh token returned. Make sure to revoke access and try again with prompt=consent.');
            }

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }

    protected function updateEnv($key, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                "$key=" . env($key),
                "$key=$value",
                file_get_contents($path)
            ));
            // Also handle case where key exists but is empty or not found in a way that str_replace works easily
            // For robustness, we might want a better regex replacer, but this is a simple start.
            // If the key was just added with empty value, env($key) might be null/empty.
            
            $content = file_get_contents($path);
            if (strpos($content, "$key=") !== false) {
                 // Simple regex replace for key=value or key=
                 $content = preg_replace("/^$key=.*$/m", "$key=$value", $content);
                 file_put_contents($path, $content);
            } else {
                file_put_contents($path, $content . "\n$key=$value");
            }
        }
    }
}
