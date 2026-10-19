<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemandApprovalRequest extends FormRequest
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
            'comment' => ['required', 'string', 'regex:/\S/u', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '操作ユーザIDが指定されていません。',
            'comment.required' => '差し戻し時は差し戻し理由コメントの入力が必須です。',
            'comment.regex' => '差し戻し理由コメントに空白のみの入力はできません。',
        ];
    }
}

