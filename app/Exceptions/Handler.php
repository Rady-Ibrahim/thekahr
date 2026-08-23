<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    private array $duplicateKeyMessages = [
        'employees_phone_unique' => 'رقم الهاتف مستخدم من قبل. استخدم رقمًا آخر.',
        'employees_email_unique' => 'البريد الإلكتروني مستخدم من قبل. استخدم بريدًا آخر.',
        'employees_national_id_unique' => 'الرقم القومي مستخدم من قبل. اتركه فارغًا أو أدخل رقمًا مختلفًا.',
        'employees_employee_code_unique' => 'كود الموظف مستخدم من قبل. استخدم كودًا آخر.',
        'users_email_unique' => 'البريد الإلكتروني مستخدم من قبل في حساب مستخدم موجود.',
        'users_phone_unique' => 'رقم الهاتف مستخدم من قبل في حساب مستخدم موجود.',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ValidationException $e, $request) {
            if (!$this->wantsJsonResponse($request)) {
                return null;
            }

            $errors = $e->errors();
            $first = collect($errors)->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $first ?: 'بيانات غير صحيحة. راجع الحقول وأعد المحاولة.',
                'errors' => $errors,
            ], 422);
        });

        $this->renderable(function (QueryException $e, $request) {
            if (!$this->wantsJsonResponse($request)) {
                return null;
            }

            return $this->queryExceptionResponse($e);
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if (!$this->wantsJsonResponse($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'العنصر المطلوب غير موجود.',
            ], 404);
        });
    }

    protected function wantsJsonResponse($request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*')
            || $request->ajax();
    }

    protected function queryExceptionResponse(QueryException $e): JsonResponse
    {
        $sqlMessage = $e->getMessage();

        if ($this->isDuplicateEntry($e, $sqlMessage)) {
            return response()->json([
                'success' => false,
                'message' => $this->friendlyDuplicateMessage($sqlMessage),
            ], 422);
        }

        if ($this->isNotNullViolation($e)) {
            return response()->json([
                'success' => false,
                'message' => 'هناك بيانات ناقصة. راجع الحقول المطلوبة وأعد المحاولة.',
            ], 422);
        }

        if ($this->isForeignKeyViolation($sqlMessage)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إتمام العملية لوجود بيانات مرتبطة. احذف أو عدّل السجلات المرتبطة أولاً.',
            ], 422);
        }

        return response()->json([
            'success' => false,
            'message' => 'تعذر حفظ البيانات. راجع المدخلات وحاول مرة أخرى.',
        ], 500);
    }

    protected function isDuplicateEntry(QueryException $e, string $message): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        return $driverCode === 1062
            || $sqlState === '23505'
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
    }

    protected function isForeignKeyViolation(string $message): bool
    {
        return str_contains($message, 'foreign key constraint')
            || str_contains($message, 'FOREIGN KEY');
    }

    protected function isNotNullViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return $driverCode === 1048
            || str_contains($e->getMessage(), 'cannot be null');
    }

    protected function friendlyDuplicateMessage(string $sqlMessage): string
    {
        foreach ($this->duplicateKeyMessages as $key => $message) {
            if (str_contains($sqlMessage, $key)) {
                return $message;
            }
        }

        if (preg_match("/for key '([^']+)'/", $sqlMessage, $matches)) {
            $key = $matches[1];
            // Sometimes MySQL returns table.key
            $short = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;
            if (isset($this->duplicateKeyMessages[$short])) {
                return $this->duplicateKeyMessages[$short];
            }
            if (isset($this->duplicateKeyMessages[$key])) {
                return $this->duplicateKeyMessages[$key];
            }
        }

        return 'هذه البيانات مسجّلة من قبل (قيمة مكررة). راجع الهاتف أو الإيميل أو الكود أو الرقم القومي.';
    }

    protected function invalidJson($request, Throwable $exception)
    {
        if ($this->wantsJsonResponse($request) && !($exception instanceof HttpExceptionInterface)) {
            // fall through to parent for non-HTTP; parent handles many cases
        }

        return parent::invalidJson($request, $exception);
    }
}
