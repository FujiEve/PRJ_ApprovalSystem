<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
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
}

