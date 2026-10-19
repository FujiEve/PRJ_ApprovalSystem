<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $headerUserId = $this->header('X-Current-User-Id');
        if (!$this->has('user_id') && $headerUserId !== null && $headerUserId !== '') {
            $this->merge(['user_id' => (int) $headerUserId]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'content' => ['required', 'string', 'regex:/\S/u', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '操作ユーザIDが指定されていません。',
            'content.required' => 'コメント内容を入力してください。',
            'content.regex' => 'コメント内容に空白のみの入力はできません。',
        ];
    }
}

