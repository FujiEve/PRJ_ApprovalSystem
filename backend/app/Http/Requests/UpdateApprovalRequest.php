<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalRequest extends FormRequest
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

        if (!$this->has('status_id')) {
            $this->merge(['status_id' => 3]);
        }

        if ($this->has('assignee_user_id') && ($this->input('assignee_user_id') === null || $this->input('assignee_user_id') === '')) {
            $this->merge(['assignee_user_id' => 0]);
        }
    }

    public function rules(): array
    {
        $isApplying = (int) $this->input('status_id', 3) === 0;

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_user_id' => ['required', 'integer', 'exists:users,id'],
            'assignee_name' => ['nullable', 'string', 'max:100'],
            'status_id' => ['required', 'integer', Rule::in([0, 2, 3])],
            'subject' => ['required', 'string', 'min:1', 'max:255'],
            'amount' => $isApplying
                ? ['required', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'],
            'detail' => $isApplying
                ? ['required', 'string', 'regex:/\S/u']
                : ['nullable', 'string'],
            'reason' => $isApplying
                ? ['required', 'string', 'regex:/\S/u']
                : ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*.file_name' => ['required_with:files', 'string', 'max:255'],
            'files.*.file_url' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => '件名は必須です。',
            'subject.max' => '件名は255文字以内で入力してください。',
            'amount.required' => '申請時は金額の入力が必須です。',
            'amount.min' => '金額は0円以上の整数を入力してください。',
            'detail.required' => '申請時は詳細の入力が必須です。',
            'detail.regex' => '詳細に空白のみの入力はできません。',
            'reason.required' => '申請時は理由の入力が必須です。',
            'reason.regex' => '理由に空白のみの入力はできません。',
        ];
    }
}

