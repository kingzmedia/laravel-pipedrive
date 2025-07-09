<?php

namespace Keggermont\LaravelPipedrive\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Services\PipedriveWebhookService;
use Keggermont\LaravelPipedrive\Http\Middleware\VerifyPipedriveWebhook;

class PipedriveWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware(VerifyPipedriveWebhook::class);
    }

    /**
     * Handle incoming Pipedrive webhook
     */
    public function handle(Request $request, PipedriveWebhookService $webhookService)
    {
        try {
            // Log webhook reception for debugging
            Log::info('Pipedrive webhook received', [
                'event' => $request->input('event'),
                'meta' => $request->input('meta'),
                'retry' => $request->input('retry', 0),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Process the webhook
            $result = $webhookService->processWebhook($request->all());

            // Log successful processing
            Log::info('Pipedrive webhook processed successfully', [
                'event' => $request->input('event'),
                'object_id' => $request->input('meta.id'),
                'result' => $result,
            ]);

            // Return 200 OK to acknowledge receipt
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully',
                'processed' => $result['processed'],
                'action' => $result['action'],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // Log error for debugging
            Log::error('Pipedrive webhook processing failed', [
                'event' => $request->input('event'),
                'object_id' => $request->input('meta.id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'retry' => $request->input('retry', 0),
            ]);

            // Return 500 to trigger Pipedrive retry
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Health check endpoint for webhook URL validation
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'Laravel Pipedrive Webhooks',
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_OK);
    }
}
