<?php

namespace App\Http\Requests;

use App\Models\ApprovalFile;
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

        if ($this->boolean('sync_existing_files') && !array_key_exists('existing_file_ids', $this->all())) {
            $this->merge(['existing_file_ids' => []]);
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
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'sync_existing_files' => ['sometimes', 'boolean'],
            'existing_file_ids' => ['sometimes', 'array', 'max:10'],
            'existing_file_ids.*' => [
                'integer',
                'distinct',
                function ($attribute, $value, $fail): void {
                    $exists = ApprovalFile::where('id', (int) $value)
                        ->where('approval_id', (int) $this->route('id'))
                        ->where('is_deleted', false)
                        ->exists();

                    if (!$exists) {
                        $fail('この稟議に紐づく有効な添付資料を指定してください。');
                    }
                },
            ],
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

