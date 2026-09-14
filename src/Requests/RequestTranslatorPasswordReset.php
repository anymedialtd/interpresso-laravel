<?php

namespace AnyMedia\Interpresso\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestTranslatorPasswordReset extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['email' => 'required|string|email|max:255'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.*' => __('interpresso::passwords.invalid_email')];
    }
}
