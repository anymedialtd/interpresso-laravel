<?php

namespace AnyMedia\Interpresso\Requests;

class ResetTranslatorPassword extends RequestTranslatorPasswordReset
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return parent::rules() + [
            'token' => 'required|string|size:64',
            'password' => 'required|string|min:8|max:255',
            'password_confirmation' => 'required|string|same:password',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return parent::messages() + [
            'token.*' => __('interpresso::passwords.invalid_token'),
            'password.*' => __('interpresso::passwords.password_rules'),
            'password_confirmation.*' => __('interpresso::passwords.confirmation_mismatch'),
        ];
    }
}
