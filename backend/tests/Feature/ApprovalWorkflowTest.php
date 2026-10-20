<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('database/migrations/2026_01_01_000001_create_approval_system_tables.php');
        $migration->down();
        $migration->up();

        (new DatabaseSeeder())->run();
    }

    public function test_masters_endpoint_returns_initial_data(): void
    {
        $response = $this->getJson('/api/masters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'users',
                'departments',
                'stores',
                'statuses',
            ]);

        $this->assertCount(4, $response->json('statuses'));
        $this->assertGreaterThanOrEqual(4, count($response->json('users')));
    }

    public function test_full_workflow_from_draft_to_remand_and_approval(): void
    {
        // 1. 起案者(user_id=1)が下書き保存 (status_id=3)
        $draftRes = $this->postJson('/api/approvals', [
            'applicant_user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 3,
            'subject' => 'テスト稟議：ノートPC購入',
            'amount' => 150000,
            'detail' => '',
            'reason' => '',
        ]);
        $draftRes->assertStatus(201);
        $approvalId = $draftRes->json('data.id');
        $this->assertSame(3, $draftRes->json('data.status_id'));

        // 2. 他ユーザ(承認者 user_id=3)からは下書きが見えないことを検証
        $this->getJson("/api/approvals/{$approvalId}", ['X-Current-User-Id' => '3'])
            ->assertStatus(403);

        // 3. 起案者(user_id=1)が下書きから本申請 (status_id=0)
        $applyRes = $this->putJson("/api/approvals/{$approvalId}", [
            'user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 0,
            'subject' => 'テスト稟議：ノートPC購入',
            'amount' => 150000,
            'detail' => '開発用ノートPC 1台の購入',
            'reason' => '増員対応のため',
        ]);
        $applyRes->assertStatus(200);
        $this->assertSame(0, $applyRes->json('data.status_id'));

        // 4. 申請中(status_id=0)の直接編集は 400 エラーになることを検証
        $this->putJson("/api/approvals/{$approvalId}", [
            'user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 0,
            'subject' => '直接編集テスト',
            'amount' => 150000,
            'detail' => '詳細',
            'reason' => '理由',
        ])->assertStatus(400);

        // 5. 起案者が一度取り下げて下書き(status_id=3)に戻す
        $withdrawRes = $this->postJson("/api/approvals/{$approvalId}/withdraw", [
            'user_id' => 1,
        ]);
        $withdrawRes->assertStatus(200);
        $this->assertSame(3, $withdrawRes->json('data.status_id'));

        // 6. 再度本申請(status_id=0)
        $this->putJson("/api/approvals/{$approvalId}", [
            'user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 0,
            'subject' => 'テスト稟議：ノートPC購入（修正版）',
            'amount' => 140000,
            'detail' => '開発用ノートPC 1台の購入',
            'reason' => '増員対応のため',
        ])->assertStatus(200);

        // 7. 承認者(user_id=3)がコメントなしで差し戻ししようとすると 422 エラー
        $this->postJson("/api/approvals/{$approvalId}/remand", [
            'user_id' => 3,
            'comment' => '   ',
        ])->assertStatus(422);

        // 8. 承認者(user_id=3)がコメント付きで差し戻し (status_id=2)
        $remandRes = $this->postJson("/api/approvals/{$approvalId}/remand", [
            'user_id' => 3,
            'comment' => '型番と保証年数を明記してください。',
        ]);
        $remandRes->assertStatus(200);
        $this->assertSame(2, $remandRes->json('data.status_id'));

        // 9. 起案者(user_id=1)が差し戻し案件を編集して再申請 (status_id=0)
        $reapplyRes = $this->putJson("/api/approvals/{$approvalId}", [
            'user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 0,
            'subject' => 'テスト稟議：ノートPC購入（再申請）',
            'amount' => 145000,
            'detail' => '開発用ノートPC 1台（3年保証付）の購入',
            'reason' => '増員対応のため',
        ]);
        $reapplyRes->assertStatus(200);
        $this->assertSame(0, $reapplyRes->json('data.status_id'));

        // 10. 起案者(user_id=1)が自分で承認しようとすると 403 エラー
        $this->postJson("/api/approvals/{$approvalId}/approve", [
            'user_id' => 1,
        ])->assertStatus(403);

        // 11. 承認者(user_id=3)が承認 (status_id=1)
        $approveRes = $this->postJson("/api/approvals/{$approvalId}/approve", [
            'user_id' => 3,
            'comment' => '内容確認しました。承認します。',
        ]);
        $approveRes->assertStatus(200);
        $this->assertSame(1, $approveRes->json('data.status_id'));

        // 12. 承認済み(status_id=1)の稟議は編集も取り下げも 400 エラーでロックされていることを検証
        $this->putJson("/api/approvals/{$approvalId}", [
            'user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 0,
            'subject' => '承認後の不正編集',
            'amount' => 1000,
            'detail' => '詳細',
            'reason' => '理由',
        ])->assertStatus(400);

        $this->postJson("/api/approvals/{$approvalId}/withdraw", [
            'user_id' => 1,
        ])->assertStatus(400);

        // 13. 履歴とコメントが記録されていることを確認
        $this->assertGreaterThanOrEqual(6, count($approveRes->json('data.histories')));
        $this->assertCount(2, $approveRes->json('data.comments'));
    }

    public function test_list_filters_and_hides_drafts_from_other_users(): void
    {
        $response = $this->getJson('/api/approvals?status_id=2&department_id=1', [
            'X-Current-User-Id' => '4',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 2);

        $this->getJson('/api/approvals?status_id=3', ['X-Current-User-Id' => '4'])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_approval_details_are_visible_only_to_applicant_or_assignee(): void
    {
        $this->getJson('/api/approvals/1', ['X-Current-User-Id' => '1'])->assertOk();
        $this->getJson('/api/approvals/1', ['X-Current-User-Id' => '3'])->assertOk();
        $this->getJson('/api/approvals/1', ['X-Current-User-Id' => '2'])->assertForbidden();
        $this->getJson('/api/approvals/1')->assertForbidden();

        $this->getJson('/api/approvals/4', ['X-Current-User-Id' => '1'])->assertOk();
        $this->getJson('/api/approvals/4', ['X-Current-User-Id' => '3'])->assertForbidden();
    }

    public function test_approval_list_contains_only_applications_involving_current_user(): void
    {
        $approverResponse = $this->getJson('/api/approvals', ['X-Current-User-Id' => '3']);
        $approverResponse->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([1, 3], array_column($approverResponse->json('data'), 'id'));

        $otherApplicantResponse = $this->getJson('/api/approvals', ['X-Current-User-Id' => '2']);
        $otherApplicantResponse->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(3, $otherApplicantResponse->json('data.0.id'));

        $this->getJson('/api/approvals')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_create_and_show_validation_errors_are_returned(): void
    {
        $this->postJson('/api/approvals', [
            'applicant_user_id' => 1,
            'status_id' => 0,
            'subject' => '必須項目不足',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'detail', 'reason']);

        $this->getJson('/api/approvals/99999')->assertNotFound();
    }

    public function test_applicant_cannot_be_the_approval_assignee(): void
    {
        $this->postJson('/api/approvals', [
            'applicant_user_id' => 1,
            'assignee_user_id' => 1,
            'status_id' => 3,
            'subject' => '担当者重複の稟議',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_user_id']);

        $approvalId = $this->postJson('/api/approvals', [
            'applicant_user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 3,
            'subject' => '担当者重複の稟議',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/approvals/{$approvalId}", [
            'user_id' => 1,
            'assignee_user_id' => 1,
            'status_id' => 3,
            'subject' => '担当者重複の稟議',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_user_id']);
    }

    public function test_create_approval_accepts_and_stores_uploaded_attachments(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('estimate.pdf', "%PDF-1.4\nTest estimate");

        $response = $this->post('/api/approvals', [
            'applicant_user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 0,
            'subject' => '添付ファイル付きの申請',
            'amount' => 1000,
            'detail' => '添付ファイルのダウンロード確認',
            'reason' => 'Featureテスト',
            'files' => [$file],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.files.0.file_name', 'estimate.pdf');
        $storedPath = $response->json('data.files.0.file_url');
        Storage::disk('local')->assertExists($storedPath);

        $fileId = $response->json('data.files.0.id');
        $this->getJson("/api/approvals/{$response->json('data.id')}/files/{$fileId}", [
            'X-Current-User-Id' => '1',
        ])->assertOk();
        $this->getJson("/api/approvals/{$response->json('data.id')}/files/{$fileId}", [
            'X-Current-User-Id' => '3',
        ])->assertOk();
        $this->getJson("/api/approvals/{$response->json('data.id')}/files/{$fileId}", [
            'X-Current-User-Id' => '2',
        ])->assertForbidden();
    }

    public function test_edit_can_keep_delete_and_add_uploaded_attachments(): void
    {
        Storage::fake('local');
        $keptUpload = UploadedFile::fake()->createWithContent('keep.pdf', "%PDF-1.4\nKeep");
        $removedUpload = UploadedFile::fake()->createWithContent('remove.pdf', "%PDF-1.4\nRemove");
        $createResponse = $this->post('/api/approvals', [
            'applicant_user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 3,
            'subject' => '添付編集テスト',
            'files' => [$keptUpload, $removedUpload],
        ], ['Accept' => 'application/json'])->assertCreated();

        $approvalId = $createResponse->json('data.id');
        $originalFiles = $createResponse->json('data.files');
        $keptFile = collect($originalFiles)->firstWhere('file_name', 'keep.pdf');
        $removedFile = collect($originalFiles)->firstWhere('file_name', 'remove.pdf');
        $keptPath = $keptFile['file_url'];
        $removedPath = $removedFile['file_url'];
        $this->assertDatabaseHas('approval_files', [
            'id' => $keptFile['id'],
            'approval_id' => $approvalId,
            'is_deleted' => false,
        ]);
        $newUpload = UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nNew");

        $updateResponse = $this->post("/api/approvals/{$approvalId}", [
            '_method' => 'PUT',
            'user_id' => 1,
            'assignee_user_id' => 3,
            'status_id' => 3,
            'subject' => '添付編集テスト',
            'existing_file_ids' => [$keptFile['id']],
            'sync_existing_files' => '1',
            'files' => [$newUpload],
        ], ['Accept' => 'application/json'])->assertOk();

        $updatedFiles = $updateResponse->json('data.files');
        $this->assertEqualsCanonicalizing(['keep.pdf', 'new.pdf'], array_column($updatedFiles, 'file_name'));
        Storage::disk('local')->assertExists($keptPath);
        Storage::disk('local')->assertMissing($removedPath);
        Storage::disk('local')->assertExists(
            collect($updatedFiles)->firstWhere('file_name', 'new.pdf')['file_url']
        );

        $this->getJson("/api/approvals/{$approvalId}/files/{$keptFile['id']}", [
            'X-Current-User-Id' => '1',
        ])->assertOk();
        $this->getJson("/api/approvals/{$approvalId}/files/{$removedFile['id']}", [
            'X-Current-User-Id' => '1',
        ])->assertNotFound();
    }

    public function test_only_the_applicant_can_edit_a_remanded_approval(): void
    {
        $payload = [
            'user_id' => 2,
            'assignee_user_id' => 4,
            'status_id' => 3,
            'subject' => '編集権限確認',
            'amount' => 1000,
            'detail' => '詳細',
            'reason' => '理由',
        ];

        $this->putJson('/api/approvals/2', $payload)->assertForbidden();

        $payload['user_id'] = 1;
        $this->putJson('/api/approvals/2', $payload)
            ->assertOk()
            ->assertJsonPath('data.status_id', 3);
    }

    public function test_withdraw_rejects_non_pending_and_non_applicant_requests(): void
    {
        $this->postJson('/api/approvals/3/withdraw', ['user_id' => 2])->assertStatus(400);
        $this->postJson('/api/approvals/1/withdraw', ['user_id' => 2])->assertForbidden();
    }

    public function test_only_applicant_can_advance_comment_status_in_order(): void
    {
        $comment = $this->postJson('/api/approvals/1/comments', [
            'user_id' => 1,
            'content' => '確認コメント',
        ])->assertCreated()->json('data');

        $commentStatusUrl = "/api/approvals/1/comments/{$comment['id']}/status";

        $this->patchJson($commentStatusUrl, ['user_id' => 3, 'status' => 1])
            ->assertForbidden();
        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 2])
            ->assertUnprocessable();

        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 1);
        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 0])
            ->assertUnprocessable();
        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 1])
            ->assertUnprocessable();
        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 2);
        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 1])
            ->assertUnprocessable();

        $this->patchJson($commentStatusUrl, ['user_id' => 1, 'status' => 9])
            ->assertUnprocessable();
        $this->patchJson('/api/approvals/1/comments/99999/status', ['user_id' => 1, 'status' => 1])
            ->assertNotFound();
        $this->patchJson($commentStatusUrl, ['status' => 1])->assertUnprocessable();
        $this->postJson('/api/approvals/1/comments', ['user_id' => 1, 'content' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }
}
