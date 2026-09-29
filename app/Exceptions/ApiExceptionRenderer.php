<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * One JSON shape for every error the API can produce.
 *
 * Unhandled exceptions used to reach the app as Laravel's debug page in JSON:
 * the SQL that failed, absolute file paths, the database name and a full stack
 * trace. Nothing there is useful to a farmer and all of it is useful to an
 * attacker. Every response now looks the same:
 *
 *     { "status": false, "message": "...", "errors": { ... } }
 *
 * `errors` appears only for validation. The real exception is logged with a
 * reference the support team can search for.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (!$this->wantsJson($request)) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException     => $this->validation($e),
            $e instanceof AuthenticationException => $this->fail('Your session has ended. Please sign in again.', 401),
            $e instanceof AuthorizationException  => $this->fail($e->getMessage() ?: 'You are not allowed to do that.', 403),
            $e instanceof ModelNotFoundException  => $this->fail('That record no longer exists.', 404),
            $e instanceof NotFoundHttpException   => $this->fail('That address does not exist.', 404),
            $e instanceof TooManyRequestsHttpException => $this->fail('Too many attempts. Please wait a moment and try again.', 429),
            $e instanceof FeedBackfillException   => $this->fail($e->getMessage(), 422),
            $e instanceof QueryException          => $this->database($e, $request),
            $e instanceof HttpExceptionInterface  => $this->fail(
                $e->getMessage() ?: 'The request could not be completed.',
                $e->getStatusCode()
            ),
            default => $this->unexpected($e, $request),
        };
    }

    private function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function validation(ValidationException $e): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $e->validator->errors()->first() ?: 'Please check the details and try again.',
            'errors'  => $e->errors(),
        ], $e->status);
    }

    /**
     * A database error the caller may be able to act on.
     *
     * Out-of-range and duplicate-key are the two the app can actually fix by
     * changing what it sent, so they get their own wording. Everything else is
     * a fault on this side and says so without detail.
     */
    private function database(QueryException $e, Request $request): JsonResponse
    {
        $reference = $this->log('Database error', $e, $request);

        $message = match ((string) ($e->errorInfo[1] ?? '')) {
            '1264'          => 'One of the numbers entered is too large. Please check and try again.',
            '1062'          => 'That already exists.',
            '1451', '1452'  => 'This is still linked to other records and cannot be changed.',
            default         => 'Something went wrong saving your changes. Please try again.',
        };

        return $this->fail($message, 422, $reference);
    }

    private function unexpected(Throwable $e, Request $request): JsonResponse
    {
        $reference = $this->log('Unhandled API exception', $e, $request);

        return $this->fail('Something went wrong. Please try again.', 500, $reference);
    }

    private function log(string $label, Throwable $e, Request $request): string
    {
        $reference = strtoupper(bin2hex(random_bytes(4)));

        Log::error($label, [
            'reference' => $reference,
            'url'       => $request->fullUrl(),
            'method'    => $request->method(),
            'user'      => optional($request->user())->id,
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile() . ':' . $e->getLine(),
        ]);

        return $reference;
    }

    private function fail(string $message, int $status, ?string $reference = null): JsonResponse
    {
        $body = ['status' => false, 'message' => $message];

        if ($reference !== null) {
            $body['reference'] = $reference;
        }

        return response()->json($body, $status);
    }
}
