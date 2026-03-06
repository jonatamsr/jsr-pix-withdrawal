<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class CreateWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string'],
            'pix.type' => ['required', 'string', 'in:email'],
            'pix.key' => ['required', 'email'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'schedule' => ['nullable', 'date_format:Y-m-d H:i'],
        ];
    }
}
